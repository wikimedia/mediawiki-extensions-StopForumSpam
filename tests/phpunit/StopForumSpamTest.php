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

use MediaWiki\StopForumSpam\DenyListManager;
use MediaWiki\StopForumSpam\DenyListUpdate;

/**
 * @group StopForumSpam
 * @covers \MediaWiki\StopForumSpam\DenyListManager
 * @covers \MediaWiki\StopForumSpam\DenyListUpdate
 */
class StopForumSpamTest extends MediaWikiIntegrationTestCase {

	private const DENY_LIST_KEY = 'sfs-denylist-unit-tests';

	protected function setUp() : void {
		parent::setUp();

		$this->setMwGlobals( 'wgSFSDenyListKey', self::DENY_LIST_KEY );

		// Set up mock wancache as an MW service
		$cache = new WANObjectCache( [ 'cache' => new HashBagOStuff() ] );
		$this->setService( 'MainWANObjectCache', $cache );
	}

	protected function loadDenyListFile( $list ) {
		$this->setMwGlobals( 'wgSFSIPListLocation', __DIR__ . '/' . $list );
		$upd = new DenyListUpdate();
		$upd->doUpdate();
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
		$this->loadDenyListFile( 'sample_denylist.txt' );
		$this->assertSame( $res, DenyListManager::isDenyListed( $ip ) );
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
		$this->setMwGlobals( 'wgSFSIPThreshold', 5 );
		$this->loadDenyListFile( 'sample_denylist_all.txt' );
		$this->assertSame( $res, DenyListManager::isDenyListed( $ip ) );
	}
}
