<?php

if ( !defined( 'MEDIAWIKI' ) ) {
	die;
}

/**
 * StopForumSpam.com is a website dedicated to
 * stopping spam. This extension helps
 * by contributing data when an administrator
 * blocks a user.
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

$wgExtensionCredits['antispam'][] = array(
	'path' => __FILE__,
	'name' => 'StopForumSpam',
	'author' => 'Kunal Mehta',
	'url' => 'https://www.mediawiki.org/wiki/Extension:StopForumSpam',
	'descriptionmsg' => 'stopforumspam-desc',
	'version' => '0.1',
);

$wgAutoloadClasses['SFSHooks'] = __DIR__ . '/StopForumSpam.hooks.php';
$wgAutoloadClasses['StopForumSpam'] = __DIR__ . '/StopForumSpam.body.php';

$wgHooks['SpecialBlockBeforeFormDisplay'][] = 'SFSHooks::onSpecialBlockBeforeFormDisplay';
$wgHooks['BlockIpComplete'][] = 'SFSHooks::onBlockIpComplete';

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
