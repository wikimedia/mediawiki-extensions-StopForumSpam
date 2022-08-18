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
class StopForumSpamSimpleTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();

		$this->setMwGlobals( 'wgSFSIPListLocation', __DIR__ . '/sample_denylist.txt' );
	}

	public static function provideSimpleDenyListing() {
		return [
			'IPv4 in list' => [ '112.111.191.178', true ],
			'IPv4 not in list' => [ '127.0.0.1', false ],
			'Non-IP addresses' => [ 'not an IP address', false ],
			'Long IPv6' => [ '2001:0db8:0000:0000:0000:ff00:0042:8329', false ],
			'Shorter IPv6' => [ '2001:db8::ff00:42:8329', false ],
			'Long IPv6 in list' => [ '2001:0db8:0000:0000:0000:ff00:0042:8330', true ],
			'Shorter IPv6 in list' => [ '2001:db8::ff00:42:8330', true ],
		];
	}

	/**
	 * @dataProvider provideSimpleDenyListing
	 */
	public function testSimpleDenyListing( $ip, $res ) {
		$srvCache = new HashBagOStuff();
		$wanCache = new WANObjectCache( [ 'cache' => new HashBagOStuff() ] );
		$http = MediaWikiServices::getInstance()->getHttpRequestFactory();
		$denyListManager = new DenyListManager( $http, $srvCache, $wanCache, null );

		$this->assertSame( $res, $denyListManager->isIpDenyListed( $ip ) );
	}
}
