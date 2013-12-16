<?php

class StopForumSpam {

	/**
	 * How long the confidence level should be cached for (1 day)
	 */
	const CACHE_DURATION = 86400;

	/**
	 * Used in determining cache keys
	 * Not const due to the fact this changes based on whether PHP is 32-bit or 64-bit
	 */
	protected static $SHIFT_AMOUNT = null;
	protected static $BUCKET_MASK;
	protected static $OFFSET_MASK;

	/**
	 * Actual submission process to stopforumspam.com
	 * @param User $user our bad spammer to report
	 * @return bool indicating success of operation
	 */
	public static function submit( User $user ) {
		global $wgSFSAPIKey;

		if ( !$wgSFSAPIKey ) {
			wfDebugLog( 'StopForumSpam', 'No API key set' );
			return false;
		}

		if ( !$user->isLoggedIn() ) {
			return false;
		}

		$ips = self::getUserIPs( $user );
		if ( $ips === false ) {
			wfDebugLog( 'StopForumSpam', 'IP info is not stored in recentchanges nor CheckUser extension, bailing' );
			return false;
		} elseif( !$ips ) {
			wfDebugLog( 'StopForumSpam', "Couldn't find any IPs for \"{$user->getName()}\"");
			return false;
		}

		$email = $user->getEmail();
		if ( !$email ) {
			wfDebugLog( 'StopForumSpam', "User:{$user->getName()} does not have an email associated with the account.");
			return false;
		}


		$params = array(
			'username' => $user->getName(),
			'email' => $email,
			'evidence' => self::getUserEvidenceLink( $user ),
			'api_key' => $wgSFSAPIKey,
		);
		// Each IP must be submitted separately I guess.
		// @todo find a way to batch submit.
		foreach ( $ips as $ip ) {
			$params['ip_addr'] = $ip;
			$url = wfAppendQuery( 'http://www.stopforumspam.com/add.php', $params );
			$req = MWHttpRequest::factory( $url );
			$req->execute();
			$json = $req->getContent();
			$decoded = FormatJson::decode( $json, true );
			wfDebugLog( 'StopForumSpam', "Sent data for {$user->getName()}/{$ip}" );
		}

		return true;
	}

	/**
	 * Our evidence is the user's contributions. Though they've
	 * probably been deleted by now...
	 * @param User $user
	 * @return String
	 */
	public static function getUserEvidenceLink( User $user ) {
		return SpecialPage::getTitleFor( 'Contributions', $user->getName() )
			->getFullURL( '', false, PROTO_HTTPS );
	}

	/**
	 * @param User $user
	 * @return array|bool
	 */
	public static function getUserIPs( User $user ) {
		global $wgPutIPinRC;
		if ( $wgPutIPinRC ) {
			return self::getUserIPsFromRC( $user );
		} elseif ( class_exists( 'CheckUser' ) ) {
			return self::getUserIPsFromCU( $user );
		} else {
			return false;
		}
	}

	/**
	 * Uses the rc_ip field in recentchanges
	 * @param User $user
	 * @return array
	 */
	public static function getUserIPsFromRC( User $user ) {
		$dbr = wfGetDB( DB_SLAVE );
		$rows = $dbr->select(
			'recentchanges',
			array( 'DISTINCT(rc_ip)' ),
			array( 'rc_user' => $user->getId() ),
			__METHOD__
		);

		$ips = array();
		foreach ( $rows as $row ) {
			if ( !in_array( $row->rc_ip, $ips ) ) {
				$ips[] = $row->rc_ip;
			}
		}

		return $ips;
	}

	/**
	 * Uses the cuc_ip field in Checkuser
	 * @param User $user
	 * @return array|bool
	 */
	public static function getUserIPsFromCU( User $user ) {
		$dbr = wfGetDB( DB_SLAVE );
		$rows = $dbr->select(
			'cu_changes',
			array( 'DISTINCT(cuc_ip)' ),
			array( 'cuc_user' => $user->getId() ),
			__METHOD__
		);

		$ips = array();
		foreach ( $rows as $row ) {
			if ( !in_array( $row->cuc_ip, $ips ) ) {
				$ips[] = $row->cuc_ip;
			}
		}

		return $ips;
	}

	/**
	 * Actually makes a request and returns the average confidence
	 * @see StopForumSpam::getConfidence
	 * @param array $params
	 * @return float
	 */
	protected static function getConfidenceInternal( $params ) {
		$params['f'] = 'json';
		$url = wfAppendQuery( 'http://www.stopforumspam.com/api', $params );
		$req = MWHttpRequest::factory( $url );
		$req->execute();
		$data = $req->getContent();
		$json = FormatJson::decode( $data );
		if ( $json['success'] !== 1 ) {
			// Blegh. Set 0 confidence.
			return 0.0;
		}
		$div = 0;
		$total = 0;
		foreach( array( 'email', 'ip', 'username' ) as $key ) {
			if ( isset( $params[$key] ) && isset( $json[$key] ) ) {
				if ( isset( $json[$key]['confidence'] ) ) {
					$total += $json[$key]['confidence'];
				}
				$div++;
			}
		}

		return $total / $div;
	}

	/**
	 * Get a user's confidence level, might be cached
	 * @param User $user
	 * @return float
	 */
	public static function getConfidence( User $user ) {
		global $wgMemc;

		$main = RequestContext::getMain();

		if ( $user->isLoggedIn() ) {
			$params['username'] = $user->getName();
			if ( $user->getEmail() ) {
				$params['username'] = $user->getEmail();
			}
			if ( $main->getUser()->getName() === $user->getName() ) {
				$params['ip'] = $main->getRequest()->getIP();
			}
		} else {
			$params['ip'] = $user->getName();
		}

		$key = 'sfs:conf:' . md5( serialize( $params ) );
		$conf = $wgMemc->get( $key );
		if ( $conf === false ) {
			// Check that we haven't gone over our 20,000/day limit
			$limitKey = 'sfs:confidence:limit:' . date( 'mDY' );
			if ( $wgMemc->get( $limitKey ) === false ) {
				$limit = 1;
				$wgMemc->set( $limitKey, 1, 86400 );
			} else {
				$limit = $wgMemc->incr( $limitKey );
			}
			if ( $limit > 20000 ) {
				// We're over, so return 0 to be safe, but don't cache this failure
				wfDebugLog( 'StopForumSpam', "Skipped fetching confidence for {$user->getName()}, already over over 20k limit");
				$conf = 0.0;
			} else {
				$conf = self::getConfidenceInternal( $params );
				$wgMemc->set( $key, $conf, self::CACHE_DURATION );
			}

		}

		return $conf;
	}

	/**
	 * @return bool true if blacklist has not expired
	 */
	public static function isBlacklistUpToDate() {
		global $wgMemc;
		return $wgMemc->get( self::getBlacklistKey() ) !== false;
	}

	/**
	 * Returns key for main blacklist
	 * @return string
	 */
	public static function getBlacklistKey() {
		return 'sfs:blacklist:set';
	}

	/**
	 * Get memcached key
	 * @private This is only public so SFSBlacklistUpdate::execute can access it
	 * @param int $bucket
	 * @return string
	 */
	public static function getIPBlacklistKey( $bucket ) {
		return 'sfs:blacklisted:' . $bucket;
	}

	/**
	 * Checks if a given IP address is blacklisted
	 * @param string $ip
	 * @return bool
	 */
	public static function isBlacklisted( $ip ) {
		global $wgMemc;
		if ( !IP::isValid( $ip ) || IP::isIPv6( $ip ) ) {
			return false;
		}
		list( $bucket, $offset ) = self::getBucketAndOffset( $ip );
		$bitfield = $wgMemc->get( self::getIPBlacklistKey( $bucket ) );
		return (bool)( $bitfield & ( 1 << $offset ) );
	}

	/**
	 * Gets the bucket (cache key) and offset (bit within the cache)
	 * @private This is only public so SFSBlacklistUpdate::execute can access it
	 * @param string $ip
	 * @return array of two ints (bucket and offset)
	 */
	public static function getBucketAndOffset( $ip ) {
		if ( self::$SHIFT_AMOUNT === null ) {
			self::$SHIFT_AMOUNT = ( PHP_INT_SIZE == 4 ) ? 5 : 6;
			self::$BUCKET_MASK = ( PHP_INT_SIZE == 4 ) ? 134217727 : 67108863;
			self::$OFFSET_MASK = ( PHP_INT_SIZE == 4 ) ? 31 : 63;
		}
		$ip = ip2long( $ip );
		$bucket = ( $ip >> self::$SHIFT_AMOUNT ) & self::$BUCKET_MASK;
		$offset = $ip & self::$OFFSET_MASK;
		return array( $bucket, $offset );
	}
}
