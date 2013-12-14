<?php

class StopForumSpam {

	/**
	 * Actual submission process to stopforumspam.com
	 * @param User $user our bad spammer to report
	 * @return bool indicating success of operation
	 */
	public static function submit( User $user ) {
		global $wgSFSAPIKey;

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
}
