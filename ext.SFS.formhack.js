/**
 * Stolen from mediawiki.special.block.js
 */
( function ( mw, $ ) {
	$( function () {
		var $blockTarget = $( '#mw-bi-target' ),
			$SFSbox;

		// this will be needed until gerrit change #101063 is merged
		$SFSbox = $('<tr>')
			.addClass( 'mw-htmlform-field-HTMLCheckField')
			.append( '<td class="mw-label"><label>&#160;</label></td>' )
			.append( $('<td>')
				.addClass('mw-input')
				.append('<input name="wpSFS" type="checkbox" value="1" id="mw-input-wpSFS" />')
				.append('&#160;')
				.append( $('<label>')
					.attr('for', 'mw-input-wpSFS')
					.text( mw.message( 'stopforumspam-checkbox').text() )
				)
			);

		$( '#mw-input-wpHardBlock' ).closest( 'tr').after( $SFSbox );

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

