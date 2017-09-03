<?php
/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 */

namespace MediaWiki\StopForumSpam;

use AbuseFilterVariableHolder;
use Block;
use DeferredUpdates;
use Html;
use IP;
use RequestContext;
use Title;
use User;

class Hooks {

	/**
	 * Computes the sfs-blocked variable
	 * @param string $method
	 * @param AbuseFilterVariableHolder $vars
	 * @param array $parameters
	 * @param null &$result
	 * @return bool
	 */
	static function abuseFilterComputeVariable( $method, $vars, $parameters, &$result ) {
		if ( $method == 'sfs-blocked' ) {
			$ip = self::getIPFromUser( $parameters['user'] );
			$result = $ip !== false ? BlacklistManager::isBlacklisted( $ip ) : false;

			return false;
		} else {
			return true;
		}
	}

	/**
	 * Load our blocked variable
	 * @param AbuseFilterVariableHolder $vars
	 * @param User $user
	 * @return bool
	 */
	static function abuseFilterGenerateUserVars( $vars, $user ) {
		global $wgSFSEnableConfidenceVariable;
		if ( $wgSFSEnableConfidenceVariable ) {
			$vars->setLazyLoadVar( 'sfs_blocked', 'sfs-blocked', [ 'user' => $user ] );
		}

		return true;
	}

	/**
	 * Tell AbuseFilter about our sfs-blocked variable
	 * @param array &$builderValues
	 * @return bool
	 */
	static function abuseFilterBuilder( &$builderValues ) {
		global $wgSFSIPListLocation;
		if ( $wgSFSIPListLocation ) {
			// Uses: 'abusefilter-edit-builder-vars-sfs-blocked'
			$builderValues['vars']['sfs_blocked'] = 'sfs-blocked';
		}

		return true;
	}

	/**
	 * Get an IP address for a User if possible
	 *
	 * @param User $user
	 * @return bool|string IP address or false
	 */
	private static function getIPFromUser( User $user ) {
		if ( $user->isAnon() ) {
			return $user->getName();
		}

		$context = RequestContext::getMain();
		if ( $context->getUser()->getName() === $user->getName() ) {
			// Only use the main context if the users are the same
			return $context->getRequest()->getIP();
		}

		// Couln't figure out an IP address
		return false;
	}

	/**
	 * If an IP address is blacklisted, don't let them edit.
	 *
	 * @param Title $title Title being acted upon
	 * @param User $user User performing the action
	 * @param string $action Action being performed
	 * @param array &$result Will be filled with block status if blocked
	 * @return bool
	 */
	public static function onGetUserPermissionsErrorsExpensive( &$title, &$user, $action, &$result ) {
		global $wgSFSIPListLocation, $wgSFSEnableDeferredUpdates, $wgBlockAllowsUTEdit;
		if ( !$wgSFSIPListLocation ) {
			// Not configured
			return true;
		}
		if ( $action === 'read' ) {
			return true;
		}

		$ip = self::getIPFromUser( $user );
		if ( $ip === false ) {
			return true;
		}

		if ( $wgBlockAllowsUTEdit && $title->equals( $user->getTalkPage() ) ) {
			// Let a user edit their talk page
			return true;
		}

		if ( $wgSFSEnableDeferredUpdates && !BlacklistManager::isBlacklistUpToDate() ) {
			// Note that this doesn't necessarily mean our blacklist
			// is out of date, that it just needs updating.
			DeferredUpdates::addUpdate( new BlacklistUpdate() );
		}

		if ( BlacklistManager::isBlacklisted( $ip ) ) {
			wfDebugLog( 'StopForumSpam',
				"{$user->getName()} tripped blacklist doing $action "
				. "by using $ip on \"{$title->getPrefixedText()}\"."
			);
			if ( $user->isAllowed( 'sfsblock-bypass' ) ) {
				wfDebugLog( 'StopForumSpam', "{$user->getName()} is exempt from SFS blocks." );

				return true;
			}
			// I just copied this from TorBlock, not sure if it actually makes sense.
			if ( Block::isWhitelistedFromAutoblocks( $ip ) ) {
				wfDebugLog( 'StopForumSpam', "$ip is in autoblock whitelist. Exempting from SFS blocks." );

				return true;
			}

			$result = [ 'stopforumspam-blocked', $ip ];

			return false;
		}

		return true;
	}

	/**
	 * @param array $msg
	 * @param string $ip
	 * @return bool
	 */
	public static function onOtherBlockLogLink( &$msg, $ip ) {
		global $wgSFSIPListLocation;
		if ( !$wgSFSIPListLocation ) {
			return true;
		}
		if ( IP::isIPAddress( $ip ) && BlacklistManager::isBlacklisted( $ip ) ) {
			$msg[] = Html::rawElement(
				'span',
				[ 'class' => 'mw-stopforumspam-blacklisted' ],
				wfMessage( 'stopforumspam-is-blocked', $ip )->parse()
			);
		}

		return true;
	}
}
