<?php

$messages = array();

/** English
 * @author Kunal Mehta
 */
$messages['en'] = array(
	'stopforumspam-desc' => 'Allows administrators to send data to [http://stopforumspam.com/ stopforumspam.com]',
	'stopforumspam-checkbox' => 'Send user information to stopforumspam.com',
	'abusefilter-edit-builder-vars-sfs-confidence' => 'stopforumspam.com confidence level',
	'stopforumspam-blocked' => 'Editing from your IP address ($1) has been blocked since it has recently been used to spam websites.',
	'stopforumspam-is-blocked' => 'The IP address <strong>[http://stopforumspam.com/ipcheck/$1 $1]</strong> has been blocked for spamming.',
);

/** Message documentation (Message documentation)
 * @author Kunal Mehta
 */
$messages['qqq'] = array(
	'stopforumspam-desc' => '{{desc|name=StopForumSpam|url=http://www.mediawiki.org/wiki/Extension:StopForumSpam}}',
	'stopforumspam-checkbox' => 'Checkbox on Special:Block for administrators to submit a user\'s information',
	'abusefilter-edit-builder-vars-sfs-confidence' => 'Abuse filter syntax option in a dropdown from the group {{msg-mw|abusefilter-edit-builder-group-vars}}.',
	'stopforumspam-blocked' => 'Message a user sees when their IP address is blacklisted and they cannot edit.

* $1 is the user\'s current IP address.',
	'stopforumspam-is-blocked' => 'Shown when going to block an IP address that is currently blacklisted. $1 is the IP address.'
);
