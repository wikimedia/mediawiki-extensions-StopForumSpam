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

use DeferrableUpdate;
use Exception;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\MediaWikiServices;
use Wikimedia\IPUtils;

class DenyListUpdate implements DeferrableUpdate {

	/**
	 * perform update (get deny list, load into cache)
	 *
	 * @return bool|string[] List of denylisted IP addresses
	 */
	public function doUpdate() {
		global $wgSFSIPListLocation;
		if ( $wgSFSIPListLocation === false ) {
			wfDebugLog( 'StopForumSpam', '$wgSFSIPListLocation has not been configured properly.' );
			return false;
		}
		return self::loadDenyListIPs();
	}

	/**
	 * get array of denylisted IPs from cache
	 *
	 * @return string[] List of denylisted IP addresses
	 */
	public static function getDenyListIPs() {
		global $wgSFSDenyListKey;
		$wanCache = MediaWikiServices::getInstance()->getMainWANObjectCache();
		return $wanCache->get(
			$wanCache->makeGlobalKey( $wgSFSDenyListKey )
		);
	}

	/**
	 * purge cache of denylist IPs
	 *
	 * @return bool
	 */
	public static function purgeDenyListIPs() {
		global $wgSFSDenyListKey;
		$wanCache = MediaWikiServices::getInstance()->getMainWANObjectCache();
		return $wanCache->delete(
			$wanCache->makeGlobalKey( $wgSFSDenyListKey )
		);
	}

	/**
	 * Update cache with IPs and return them
	 *
	 * @return string[] List of denylisted IP addresses
	 */
	public static function loadDenyListIPs() {
		global $wgSFSDenyListCacheDuration, $wgSFSDenyListKey;
		$wanCache = MediaWikiServices::getInstance()->getMainWANObjectCache();
		return $wanCache->getWithSetCallback(
			$wanCache->makeGlobalKey( $wgSFSDenyListKey ),
			$wgSFSDenyListCacheDuration,
			function () {
				global $wgSFSIPListLocation;
				$IPs = is_file( $wgSFSIPListLocation ) ?
					self::fetchDenyListIPsLocal() : self::fetchDenyListIPsRemote();
				return $IPs;
			},
			[
				'lockTSE' => $wgSFSDenyListCacheDuration,
				'staleTTL' => $wgSFSDenyListCacheDuration,
				'busyValue' => []
			]
		);
	}

	/**
	 * Fetch gunzipped/unzipped SFS deny list from local file
	 *
	 * @return void|string[] list of SFS denylisted IP addresses
	 */
	private static function fetchDenyListIPsLocal() {
		global $wgSFSIPListLocation,
			$wgSFSValidateIPList,
			$wgSFSIPThreshold;

		if ( !is_file( $wgSFSIPListLocation ) ) {
			throw new Exception( "wgSFSIPListLocation does not appear to be a valid file path." );
		}

		$ipList = [];
		$fh = fopen( $wgSFSIPListLocation, 'rb' );

		if ( !$fh ) {
			return;
		}

		// Set up output buffering so we don't accidentally try to send stuff
		ob_start();
		while ( !feof( $fh ) ) {
			$ip = fgetcsv( $fh, 4096, ',', '"' );
			if ( $ip === false ) {
				break;
			}

			if (
				$ip === null ||
				$ip === [ null ] ||
				// @phan-suppress-next-line PhanTypeMismatchArgumentNullable
				( $wgSFSValidateIPList && IPUtils::sanitizeIP( $ip[0] ) === null )
			) {
				continue;
			} elseif ( isset( $ip[1] ) && $ip[1] < $wgSFSIPThreshold ) {
				continue;
			} else {
				// add to list
				$ipList[] = $ip[0];
			}
		}
		fclose( $fh );
		ob_end_clean();

		return $ipList;
	}

	/**
	 * Fetch a network file's contents via HttpRequestFactory
	 *
	 * @param HttpRequestFactory $factory
	 * @param array $httpOptions
	 * @param string $fileUrl
	 * @return null|string
	 */
	private static function fetchRemoteFile(
		HttpRequestFactory $factory,
		array $httpOptions,
		string $fileUrl
	) {
		$req = $factory->create( $fileUrl, $httpOptions );
		if ( !$req->execute()->isOK() ) {
			throw new Exception( "Failed to download resource at {$fileUrl}" );
		}
		if ( $req->getStatus() !== 200 ) {
			throw new Exception( "Unexpected HTTP {$req->getStatus()} response from {$fileUrl}" );
		}
		return $req->getContent();
	}

	/**
	 * Fetch SFS IP deny list file from SFS site, validate MD5 and returns array of IPs
	 * (https://www.stopforumspam.com/downloads - use gz files)
	 *
	 * @return string[] list of SFS denylisted IP addresses
	 */
	private static function fetchDenyListIPsRemote() {
		global $wgSFSIPListLocation, $wgSFSIPListLocationMD5, $wgSFSProxy;

		// check for zlib function for later processing
		if ( !function_exists( 'gzdecode' ) ) {
			throw new Exception( "Zlib does not appear to be configured for php!" );
		}

		if ( !filter_var( $wgSFSIPListLocation, FILTER_VALIDATE_URL ) ) {
			throw new Exception( "wgSFSIPListLocation does not appear to be a valid URL." );
		}

		// fetch vendor http resources
		$reqFac = MediaWikiServices::getInstance()->getHttpRequestFactory();

		$options = [
			'followRedirects' => true,
		];

		if ( $wgSFSProxy !== false ) {
			$options['proxy'] = $wgSFSProxy;
		}

		$fileData = self::fetchRemoteFile(
			$reqFac,
			$options,
			$wgSFSIPListLocation
		);
		$fileDataMD5 = self::fetchRemoteFile(
			$reqFac,
			$options,
			$wgSFSIPListLocationMD5
		);

		// check vendor-provided md5
		if ( $fileData == null || md5( $fileData ) !== $fileDataMD5 ) {
			throw new Exception( "SFS IP file contents and file md5 do not match!" );
		}

		// ungzip and process vendor file
		$fileDataProcessed = explode( "\n", gzdecode( $fileData ) );
		array_walk( $fileDataProcessed, function ( &$item, $key ) {
			global $wgSFSValidateIPList, $wgSFSIPThreshold;
			$ipData = str_getcsv( $item );
			if ( $wgSFSValidateIPList
				&& IPUtils::sanitizeIP( (string)$ipData[0] ) === null ) {
				$item = '';
				return;
			}
			if ( !( $ipData[1] ) && $ipData[1] < $wgSFSIPThreshold ) {
				$item = '';
				return;
			}
			$item = !( $ipData[0] ) ? $ipData[0] : '';
		} );
		return array_filter( $fileDataProcessed );
	}
}