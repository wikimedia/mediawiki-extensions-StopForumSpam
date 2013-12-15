<?php

if ( !defined( 'MEDIAWIKI' ) ) {
	die;
}

/**
 * StopForumSpam.com is a website dedicated to
 * stopping spam. This extension helps
 * by utilizing and contributing to their
 * data.
 *
 * @see http://stopforumspam.com/faq
 * @author Kunal Mehta <legoktm@gmail.com>
 * @license GPL v2 or higher
 */

/**
 * Your API key for stopforumspam.com
 * @see http://stopforumspam.com/signup
 */
$wgSFSAPIKey = '';

/**
 * Location on the server where the IP blacklist can be found
 * File should be unzipped and in the "Record summary" format
 * @see http://www.stopforumspam.com/downloads/
 */
$wgSFSIPListLocation = false;

/**
 * How many times an IP needs to be reported before it is added to the blacklist
 * This corresponds to the 2nd field in the record summary CSV
 */
$wgSFSIPThreshold = 5;

$wgExtensionCredits['antispam'][] = array(
	'path' => __FILE__,
	'name' => 'StopForumSpam',
	'author' => array( 'Kunal Mehta', 'Ryan Schmidt' ),
	'url' => 'https://www.mediawiki.org/wiki/Extension:StopForumSpam',
	'descriptionmsg' => 'stopforumspam-desc',
	'version' => '0.1',
);

$wgAutoloadClasses['SFSHooks'] = __DIR__ . '/StopForumSpam.hooks.php';
$wgAutoloadClasses['StopForumSpam'] = __DIR__ . '/StopForumSpam.body.php';

$wgHooks['SpecialBlockBeforeFormDisplay'][] = 'SFSHooks::onSpecialBlockBeforeFormDisplay';
$wgHooks['SpecialBlockModifyFormFields'][] = 'SFSHooks::onSpecialBlockModifyFormFields';
$wgHooks['BlockIpComplete'][] = 'SFSHooks::onBlockIpComplete';
$wgHooks['AbuseFilter-computeVariable'][] = 'SFSHooks::abuseFilterComputeVariable';
$wgHooks['AbuseFilter-generateUserVars'][] = 'SFSHooks::abuseFilterGenerateUserVars';
$wgHooks['AbuseFilter-builder'][] = 'SFSHooks::abuseFilterBuilder';
$wgHooks['getUserPermissionsErrorsExpensive'][] = 'SFSHooks::onGetUserPermissionsErrorsExpensive';
$wgHooks['OtherBlockLogLink'][] = 'SFSHooks::onOtherBlockLogLink';

$wgExtensionMessagesFiles['StopForumSpam'] = __DIR__ . '/StopForumSpam.i18n.php';


$wgResourceModules['ext.SFS.formhack'] = array(
	'scripts' => array(
		'ext.SFS.formhack.js',
	),
	'dependencies' => array(
		'mediawiki.jqueryMsg',
		'mediawiki.special.block', // not sure if this is actually required
	),
	'localBasePath' => __DIR__,
	'remoteExtPath' => 'StopForumSpam',
);

$wgGroupPermissions['sysop']['stopforumspam'] = true;
$wgGroupPermissions['sysop']['sfsblock-bypass'] = true;
