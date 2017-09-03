<?php
/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 */

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
		foreach ( [ 'email', 'ip', 'username' ] as $key ) {
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
				wfDebugLog( 'StopForumSpam',
					"Skipped fetching confidence for {$user->getName()}, already over over 20k limit"
				);
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
		return $wgMemc->get( self::getBlacklistKey() ) !== false
			&& $wgMemc->get( self::getBlacklistUpdateStateKey() ) === false;
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
	 * Returns key for BlacklistUpdate state
	 * @private This is only public so SFSBlacklistUpdate::execute can access it
	 * @return string
	 */
	public static function getBlacklistUpdateStateKey() {
		return 'sfs:blacklist:updatestate';
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
		return [ $bucket, $offset ];
	}
}
