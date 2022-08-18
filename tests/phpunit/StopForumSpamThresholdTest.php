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
 */

namespace MediaWiki\StopForumSpam\Tests;

use HashBagOStuff;
use MediaWiki\MediaWikiServices;
use MediaWiki\StopForumSpam\DenyListManager;
use MediaWikiIntegrationTestCase;
use WANObjectCache;

/**
 * @group StopForumSpam
 * @covers \MediaWiki\StopForumSpam\DenyListManager
 */
class StopForumSpamThresholdTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();

		$this->setMwGlobals( 'wgSFSIPThreshold', 5 );
		$this->setMwGlobals( 'wgSFSIPListLocation', __DIR__ . '/sample_denylist_all.txt' );
	}

	public static function provideThresholdDenyListing() {
		return [
			'IPv4 with 1 hit' => [ '99.7.75.101', false ],
			'IPv4 with 6 hits' => [ '99.8.132.217', true ],
			'IPv4 address not in DenyList' => [ '127.0.0.1', false ],
			'IPv6 with 1 hit' => [ '2a02:4780:8:d::', false ],
			'IPv6 with 17 hits' => [ '2a02:7aa0:1619::' , true ],
			'IPv6 with 5 hits' => [ '2a02:8071:82da:9a00::', true ],
		];
	}

	/**
	 * @dataProvider provideThresholdDenyListing
	 */
	public function testThresholdDenyListing( $ip, $res ) {
		$srvCache = new HashBagOStuff();
		$wanCache = new WANObjectCache( [ 'cache' => new HashBagOStuff() ] );
		$http = MediaWikiServices::getInstance()->getHttpRequestFactory();
		$denyListManager = new DenyListManager( $http, $srvCache, $wanCache, null );

		$this->assertSame( $res, $denyListManager->isIpDenyListed( $ip ) );
	}
}
