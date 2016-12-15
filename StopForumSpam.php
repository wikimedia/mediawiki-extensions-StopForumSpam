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
 * Whether to validate the IP addresses in the blacklist file
 * Adds a bit of processing time, but safer.
 */
$wgSFSValidateIPList = true;

/**
 * Whether to update the IP blacklist as a DeferredUpdate at the end of a pageload
 * (warning: can be very slow depending on your processor speed and cache settings)
 * If disabled, you will need to run the updateBlacklist.php maintenance script,
 * setting up a cron job to do this is recommended.
 */
$wgSFSEnableDeferredUpdates = true;

/**
 * How many times an IP needs to be reported before it is added to the blacklist
 * This corresponds to the 2nd field in the record summary CSV
 */
$wgSFSIPThreshold = 5;

/*
 * How long (in seconds) the IP blacklist should be cached for
 * If you are using the 7 day blacklist, try 5 days (432000)
 * If you are using the 30 day blacklist, try 14 days (1209600)
 * The default is 5 days
 */
$wgSFSBlacklistCacheDuration = 432000;

/**
 * Whether to enable the confidence variable for Extension:AbuseFilter
 * The variable will make external API requests
 */
$wgSFSEnableConfidenceVariable = true;

$wgExtensionCredits['antispam'][] = array(
	'path' => __FILE__,
	'name' => 'StopForumSpam',
	'author' => array( 'Kunal Mehta', 'Ryan Schmidt' ),
	'url' => 'https://www.mediawiki.org/wiki/Extension:StopForumSpam',
	'descriptionmsg' => 'stopforumspam-desc',
	'version' => '0.2.0',
	'license-name' => 'GPL-2.0+',
);

$wgAutoloadClasses['SFSHooks'] = __DIR__ . '/StopForumSpam.hooks.php';
$wgAutoloadClasses['StopForumSpam'] = __DIR__ . '/StopForumSpam.body.php';
$wgAutoloadClasses['BlacklistUpdate'] = __DIR__ . '/BlacklistUpdate.php';

$wgHooks['SpecialPageBeforeFormDisplay'][] = 'SFSHooks::onSpecialPageBeforeFormDisplay';
$wgHooks['SpecialBlockModifyFormFields'][] = 'SFSHooks::onSpecialBlockModifyFormFields';
$wgHooks['BlockIpComplete'][] = 'SFSHooks::onBlockIpComplete';
$wgHooks['AbuseFilter-computeVariable'][] = 'SFSHooks::abuseFilterComputeVariable';
$wgHooks['AbuseFilter-generateUserVars'][] = 'SFSHooks::abuseFilterGenerateUserVars';
$wgHooks['AbuseFilter-builder'][] = 'SFSHooks::abuseFilterBuilder';
$wgHooks['getUserPermissionsErrorsExpensive'][] = 'SFSHooks::onGetUserPermissionsErrorsExpensive';
$wgHooks['OtherBlockLogLink'][] = 'SFSHooks::onOtherBlockLogLink';
$wgHooks['UnitTestsList'][] = 'SFSHooks::onUnitTestsList';

$wgMessagesDirs['StopForumSpam'] = __DIR__ . '/i18n';

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
$wgAvailableRights[] = 'sfsblock-bypass';
$wgAvailableRights[] = 'stopforumspam';

