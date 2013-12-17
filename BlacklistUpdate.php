<?php

class BlacklistUpdate implements DeferrableUpdate {
	private $lineNo, $usedBuckets, $data, $skipLines, $finished = false;

	public function doUpdate() {
		global $wgSFSIPListLocation, $wgSFSIPThreshold, $wgSFSValidateIPList, $wgSFSBlacklistCacheDuration, $wgMemc;
		if ( $wgSFSIPListLocation === false ) {
			wfDebugLog( 'StopForumSpam', '$wgSFSIPListLocation has not been configured properly.' );
			return;
		}

		// Set up output buffering so we don't accidentally try to send stuff
		ob_start();

		// So that we don't start other concurrent updates
		// Have the key expire an hour early so we hopefully don't have a time where there is no blacklist
		$wgMemc->set( StopForumSpam::getBlacklistKey(), 1, $wgSFSBlacklistCacheDuration - 3600 );

		// Grab and then clear any update state
		$state = $wgMemc->get( StopForumSpam::getBlacklistUpdateStateKey() );
		if ( $state !== false ) {
			$wgMemc->delete( StopForumSpam::getBlacklistUpdateStateKey() );
		}

		// For batching purposes, this saves our current progress so we know where to pick up in case we run out of time
		register_shutdown_function( array( $this, 'saveState' ) );

		// Try to keep this running even if the user hits the stop button
		ignore_user_abort( true );

		$this->data = array();
		$this->lineNo = 0;
		$this->usedBuckets = array();
		$this->skipLines = 0;
		$this->restoreState( $state );
		$fh = fopen( $wgSFSIPListLocation, 'rb' );

		while ( !feof( $fh ) ) {
			$ip = fgetcsv( $fh, 4096, ',', '"' );
			$this->lineNo++;
			if ( $this->lineNo < $this->skipLines ) {
				continue;
			} elseif ( $ip === array( null ) || ( $wgSFSValidateIPList && ( !IP::isValid( $ip[0] ) || IP::isIPv6( $ip[0] ) ) ) ) {
				continue; // discard invalid lines
			} elseif ( isset( $ip[1] ) && $ip[1] < $wgSFSIPThreshold ) {
				continue; // wasn't hit enough times
			}
			list( $bucket, $offset ) = StopForumSpam::getBucketAndOffset( $ip[0] );
			if ( !isset( $this->data[$bucket] ) ) {
				if ( in_array( $bucket, $this->usedBuckets ) ) {
					$this->data[$bucket] = $wgMemc->get( StopForumSpam::getIPBlacklistKey( $bucket ) );
				} else {
					$this->data[$bucket] = 0;
				}
			}
			$this->data[$bucket] |= ( 1 << $offset );
		}

		foreach ( $this->data as $bucket => $bitfield ) {
			$wgMemc->set( StopForumSpam::getIPBlacklistKey( $bucket ), $bitfield, $wgSFSBlacklistCacheDuration );
		}

		fclose( $fh );

		// End output buffering
		ob_end_clean();

		$this->finished = true;
	}

	/**
	 * Saves the current progress in doUpdate() so we can pick it up at a later request
	 */
	public function saveState() {
		global $wgSFSBlacklistCacheDuration, $wgSFSIPListLocation, $wgMemc;

		if ( $this->finished ) {
			// Yay, we're done!
			return;
		}

		// Save the buckets
		foreach ( $this->data as $bucket => $bitfield ) {
			$wgMemc->set( StopForumSpam::getIPBlacklistKey( $bucket ), $bitfield, $wgSFSBlacklistCacheDuration );
		}

		// Save where we left off
		$wgMemc->set( StopForumSpam::getBlacklistUpdateStateKey(), array(
				'skipLines' => $this->lineNo,
				'usedBuckets' => array_keys( $this->data ),
				'filemtime' => filemtime( $wgSFSIPListLocation )
			), 0 );

		if ( ob_get_level() ) {
			ob_end_clean();
		}
	}

	/**
	 * Restores the state of doUpdate() to when the script exited the previous time
	 */
	private function restoreState( $state ) {
		global $wgSFSIPListLocation;
		if ( $state === false ) {
			return; // no state to restore
		} elseif ( filemtime( $wgSFSIPListLocation ) != $state['filemtime'] ) {
			return; // file was modified since we last ran, so our state is invalid
		}
		$this->lineNo = $state['lineNo'];
		$this->usedBuckets = $state['usedBuckets'];
	}
}