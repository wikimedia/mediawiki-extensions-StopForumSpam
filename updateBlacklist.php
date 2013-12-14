<?php

/**
 * Reads the blacklist file and sticks it in memcache
 */

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../..';
}

// Require base maintenance class
require_once( "$IP/maintenance/Maintenance.php" );

class SFSBlacklistUpdate extends Maintenance {

	public function execute() {
		global $wgSFSIPListLocation;
		if ( $wgSFSIPListLocation === false ) {
			$this->error( '$wgSFSIPListLocation has not been configured properly.' );
		}
		$this->output( "Starting...\n" );
		$before = microtime( true );
		StopForumSpam::makeBlacklist();
		$diff = microtime( true ) - $before;
		$this->output( "Done!\n" );
		$this->output( "Took {$diff} seconds\n" );
	}

}

$maintClass = 'SFSBlacklistUpdate';
require_once( RUN_MAINTENANCE_IF_MAIN );


