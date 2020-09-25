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
 * https://www.gnu.org/copyleft/gpl.html
 *
 * @file
 */

namespace MediaWiki\StopForumSpam;

use MediaWiki\MediaWikiServices;
use Wikimedia\IPSet;
use Wikimedia\IPUtils;

/**
 * @internal
 */
class DenyListManager {

	/**
	 * @return bool true if denylist has not expired
	 */
	public static function isDenyListUpToDate() {
		global $wgSFSDenyListKey;
		$wanCache = MediaWikiServices::getInstance()->getMainWANObjectCache();
		return $wanCache->get(
			$wanCache->makeGlobalKey( $wgSFSDenyListKey )
		) !== false;
	}

	/**
	 * Checks if a given IP address is denylisted
	 * @param string $ip
	 * @return void|bool
	 */
	public static function isDenyListed( $ip ) {
		global $wgSFSDenyListKey;

		if ( IPUtils::sanitizeIP( $ip ) === null ) {
			return;
		}

		$wanCache = MediaWikiServices::getInstance()->getMainWANObjectCache();
		$wanCacheResults = $wanCache->get(
			$wanCache->makeGlobalKey( $wgSFSDenyListKey )
		);
		if ( $wanCacheResults == false ) {
			// attempt to rebuild in cache
			$dlu = new DenyListUpdate();
			if ( !$dlu->doUpdate() ) {
				throw new \Exception( "Cache not updated with SFS Data." );
			}
		}

		$set = new IPSet( $wanCacheResults );
		return $set->match( $ip );
	}
}
