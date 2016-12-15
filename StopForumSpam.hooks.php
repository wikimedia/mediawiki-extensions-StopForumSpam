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

class SFSHooks {

	/**
	 * Some JS to add our checkbox
	 * @param string $name
	 * @param HTMLForm $form
	 * @return bool
	 */
	public static function onSpecialPageBeforeFormDisplay( $name, HTMLForm $form ) {
		if ( $name === 'Block' && $form->getUser()->isAllowed( 'stopforumspam' ) ) {
			$form->getOutput()->addModules( 'ext.SFS.formhack' );
		}

		return true;
	}

	/**
	 * @param SpecialPage $sp
	 * @param array $fields
	 * @return bool
	 */
	public static function onSpecialBlockModifyFormFields( SpecialPage $sp, &$fields ) {
		if ( $sp->getUser()->isAllowed( 'stopforumspam' ) ) {
			$fields['SFS'] = array(
				'type' => 'check',
				'label-message' => 'stopforumspam-checkbox',
				'default' => false,
			);
		}

		return true;
	}

	/**
	 * Triggers the data submission process
	 * @param Block $block
	 * @param User $user who made the block
	 * @return bool
	 */
	public static function onBlockIpComplete( Block $block, User $user ) {
		$context = RequestContext::getMain();
		$title = $context->getTitle();
		$request = $context->getRequest();
		if ( $title && $title->isSpecial( 'Block' ) ) {
			if ( $user->isAllowed( 'stopforumspam' ) && $request->getBool( 'wpSFS' ) ) {
				$target = $block->getTarget();
				if ( $target instanceof User ) {
					StopForumSpam::submit( $target );
				} else {
					$target = User::newFromName( $target );
					if ( $target && $target->exists() ) {
						StopForumSpam::submit( $target );
					} else {
						wfDebugLog( 'StopForumSpam', "Could not detect valid user from \"{$block->getTarget()}\"" );
					}
				}

			}
		}

		return true;
	}

	/**
	 * Computes the sfs-confidence variable
	 * @param string $method
	 * @param AbuseFilterVariableHolder $vars
	 * @param array $parameters
	 * @param null &$result
	 * @return bool
	 */
	static function abuseFilterComputeVariable( $method, $vars, $parameters, &$result ) {
		if ( $method == 'sfs-confidence' ) {
			$result = StopForumSpam::getConfidence( $parameters['user'] );
			return false;
		} else {
			return true;
		}
	}

	/**
	 * Load our confidence variable
	 * @param AbuseFilterVariableHolder $vars
	 * @param User $user
	 * @return bool
	 */
	static function abuseFilterGenerateUserVars( $vars, $user ) {
		global $wgSFSEnableConfidenceVariable;
		if ( $wgSFSEnableConfidenceVariable ) {
			$vars->setLazyLoadVar( 'sfs_confidence', 'sfs-confidence', array( 'user' => $user ) );
		}
		return true;
	}

	/**
	 * Tell AbuseFilter about our sfs-confidence variable
	 * @param array &$builderValues
	 * @return bool
	 */
	static function abuseFilterBuilder( &$builderValues ) {
		global $wgSFSEnableConfidenceVariable;
		if ( $wgSFSEnableConfidenceVariable ) {
			// Uses: 'abusefilter-edit-builder-vars-sfs-confidence'
			$builderValues['vars']['sfs_confidence'] = 'sfs-confidence';
		}
		return true;
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

		if ( $user->isAnon() ) {
			$ip = $user->getName();
		} else {
			$context = RequestContext::getMain();
			if ( $context->getUser()->getName() === $user->getName() ) {
				$ip = $context->getRequest()->getIP();
			} else {
				// Some other user is making an action, stay on the safe side
				return true;
			}
		}

		if ( $wgBlockAllowsUTEdit && $title->equals( $user->getTalkPage() ) ) {
			// Let a user edit their talk page
			return true;
		}

		if ( $wgSFSEnableDeferredUpdates && !StopForumSpam::isBlacklistUpToDate() ) {
			// Note that this doesn't necessarily mean our blacklist
			// is out of date, that it just needs updating.
			DeferredUpdates::addUpdate( new BlacklistUpdate() );
		}

		if ( StopForumSpam::isBlacklisted( $ip ) ) {
			wfDebugLog( 'StopForumSpam', "{$user->getName()} tripped blacklist doing $action by using $ip on \"{$title->getPrefixedText()}\"." );
			if ( $user->isAllowed( 'sfsblock-bypass' ) ) {
				wfDebugLog( 'StopForumSpam', "{$user->getName()} is exempt from SFS blocks." );
				return true;
			}
			// I just copied this from TorBlock, not sure if it actually makes sense.
			if ( Block::isWhitelistedFromAutoblocks( $ip ) ) {
				wfDebugLog( 'StopForumSpam', "$ip is in autoblock whitelist. Exempting from SFS blocks." );
				return true;
			}

			$result = array( 'stopforumspam-blocked', $ip );
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
		if ( IP::isIPAddress( $ip ) && StopForumSpam::isBlacklisted( $ip ) ) {
			$msg[] = Html::rawElement(
				'span',
				array( 'class' => 'mw-stopforumspam-blacklisted' ),
				wfMessage( 'stopforumspam-is-blocked', $ip )->parse()
			);
		}

		return true;
	}

	/**
	 * Load our unit tests
	 */
	public static function onUnitTestsList( &$files ) {
		$files = array_merge( $files, glob( __DIR__ . '/tests/*Test.php' ) );

		return true;
	}
}
