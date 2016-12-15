<?php

class StopForumSpamTest extends MediaWikiTestCase {

	protected function setUp() {
		parent::setUp();
		// Setup a fake cache
		$this->setMwGlobals( 'wgMemc', new HashBagOStuff() );
	}

	protected function loadBlacklist( $list ) {
		$this->setMwGlobals( 'wgSFSIPListLocation',  __DIR__ . '/' . $list );
		$upd = new BlacklistUpdate();
		$upd->doUpdate();
	}

	public static function provideSimpleBlacklisting() {
		return array(
			array( '112.111.191.178', true ),
			array( '127.0.0.1', false ),
			array( 'not an IP address', false, 'Non-IP addresses' ),
			array( '2001:0db8:0000:0000:0000:ff00:0042:8329', false, 'Long IPv6 address' ),
			array( '2001:db8::ff00:42:8329', false, 'Shorter IPv6 address' ),
		);
	}
	/**
	 * @dataProvider provideSimpleBlacklisting
	 */
	public function testSimpleBlacklisting( $ip, $res ) {
		$this->loadBlacklist( 'sample_blacklist.txt' );
		$this->assertEquals( StopForumSpam::isBlacklisted( $ip ), $res );
	}
	public static function provideThresholdBlacklisting() {
		return array(
			array( '99.7.75.101', false, 'IP with 1 hit' ),
			array( '99.8.132.217', true, 'IP with 6 hits' ),
			array( '127.0.0.1', false, 'IP address not on blacklist' ),
		);
	}
	/**
	 * @dataProvider provideThresholdBlacklisting
	 */
	public function testThresholdBlacklisting( $ip, $res ) {
		$this->setMwGlobals( 'wgSFSIPThreshold', 5 );
		$this->loadBlacklist( 'sample_blacklist_all.txt' );
		$this->assertEquals( StopForumSpam::isBlacklisted( $ip ), $res );
	}
}
