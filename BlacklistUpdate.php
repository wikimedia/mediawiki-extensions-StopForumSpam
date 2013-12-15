<?php

class BlacklistUpdate implements DeferrableUpdate {

	public function doUpdate() {
		global $wgSFSIPListLocation, $wgSFSIPThreshold, $wgSFSValidateIPList, $wgMemc;
		if ( $wgSFSIPListLocation === false ) {
			wfDebugLog( 'StopForumSpam', '$wgSFSIPListLocation has not been configured properly.' );
			return;
		}

		// So that we don't start other concurrent updates
		// Have the key expire an hour early so we hopefully don't have a time where there is no blacklist
		$wgMemc->set( StopForumSpam::getBlacklistKey(), 1, StopForumSpam::CACHE_DURATION - 3600 );

		$data = array();
		$fh = fopen( $wgSFSIPListLocation, 'rb' );

		while ( !feof( $fh ) ) {
			$ip = fgetcsv( $fh, 4096, ',', '"' );
			if ( $ip === array( null ) || ( $wgSFSValidateIPList && ( !IP::isValid( $ip[0] ) || IP::isIPv6( $ip[0] ) ) ) ) {
				continue; // discard invalid lines
			}
			if ( isset( $ip[1] ) && $ip[1] < $wgSFSIPThreshold ) {
				continue; // wasn't hit enough times
			}
			list( $bucket, $offset ) = StopForumSpam::getBucketAndOffset( $ip[0] );
			if ( !isset( $data[$bucket] ) ) {
				$data[$bucket] = 0;
			}
			$data[$bucket] |= ( 1 << $offset );
		}

		foreach ( $data as $bucket => $bitfield ) {
			$wgMemc->set( StopForumSpam::getIPBlacklistKey( $bucket ), $bitfield, StopForumSpam::CACHE_DURATION );
		}

		fclose( $fh );
	}
}