/**
 * Stolen from mediawiki.special.block.js
 */
( function ( mw, $ ) {
	$( function () {
		var $blockTarget = $( '#mw-bi-target' ),
			$SFSbox = $( '#mw-input-wpSFS' );

		function updateBlockOptions( instant ) {
			var blocktarget = $.trim( $blockTarget.val() ),
				isEmpty = blocktarget === '',
				isIp = mw.util.isIPv4Address( blocktarget, true ) || mw.util.isIPv6Address( blocktarget, true ),
				isIpRange = isIp && blocktarget.match( /\/\d+$/ );

			if ( !isIp && !isEmpty ) {
				$SFSbox.goIn( instant );
			} else {
				$SFSbox.goOut( instant );
			}
		}

		if ( $blockTarget.length ) {
			// Bind functions so they're checked whenever stuff changes
			$blockTarget.keyup( updateBlockOptions );

			// Call them now to set initial state (ie. Special:Block/Foobar?wpBlockExpiry=2+hours)
			updateBlockOptions( /* instant= */ true );
		}
	} );
}( mediaWiki, jQuery ) );

