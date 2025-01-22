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
 * https://www.gnu.org/copyleft/gpl.html
 *
 * @file
 */

namespace MediaWiki\Extension\StopForumSpam;

use MediaWiki\Config\Config;
use MediaWiki\Context\RequestContext;
use MediaWiki\Hook\OtherBlockLogLinkHook;
use MediaWiki\Html\Html;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\Permissions\Hook\GetUserPermissionsErrorsExpensiveHook;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use Wikimedia\IPUtils;

class Hooks implements
	GetUserPermissionsErrorsExpensiveHook,
	OtherBlockLogLinkHook
{

	/** @var Config */
	private Config $config;

	/**
	 * @param Config $config
	 */
	public function __construct( Config $config ) {
		$this->config = $config;
	}

	/**
	 * Get an IP address for a User if possible
	 *
	 * @param User $user
	 * @return bool|string IP address or false
	 */
	public static function getIPFromUser( User $user ) {
		$context = RequestContext::getMain();
		if ( $context->getUser()->getName() === $user->getName() ) {
			// Only use the main context if the users are the same
			return $context->getRequest()->getIP();
		}

		// Couldn't figure out an IP address
		return false;
	}

	/**
	 * If an IP address is deny-listed, don't let them edit.
	 *
	 * @param Title $title Title being acted upon
	 * @param User $user User performing the action
	 * @param string $action Action being performed
	 * @param array &$result Will be filled with block status if blocked
	 * @return bool
	 */
	public function onGetUserPermissionsErrorsExpensive( $title, $user, $action, &$result ) {
		if ( !$this->config->get( 'SFSIPListLocation' ) ) {
			// Not configured
			return true;
		}
		if ( $action === 'read' ) {
			return true;
		}
		if ( $this->config->get( 'BlockAllowsUTEdit' ) && $title->equals( $user->getTalkPage() ) ) {
			// Let a user edit their talk page
			return true;
		}

		$exemptReasons = [];
		$logger = LoggerFactory::getInstance( 'StopForumSpam' );
		$ip = self::getIPFromUser( $user );

		// attempt to get ip from user
		if ( $ip === false ) {
			$exemptReasons[] = "Unable to obtain IP information for {user}";
		}

		// allow if user has sfsblock-bypass
		if ( $user->isAllowed( 'sfsblock-bypass' ) ) {
			$exemptReasons[] = "{user} is exempt from SFS blocks";
		}

		// allow if user is exempted from autoblocks (borrowed from TorBlock)
		if ( MediaWikiServices::getInstance()->getAutoblockExemptionList()->isExempt( $ip ) ) {
			$exemptReasons[] = "{clientip} is in autoblock exemption list, exempting from SFS blocks";
		}

		$denyListManager = DenyListManager::singleton();
		if ( !$this->config->get( 'SFSReportOnly' ) ) {
			// enforce mode + ip not deny-listed = allow action
			if ( !$denyListManager->isIpDenyListed( $ip ) || count( $exemptReasons ) > 0 ) {
				return true;
			}
		} elseif (
			$denyListManager->isIpDenyListed( $ip ) &&
			count( $exemptReasons ) > 0
		) {
			// report-only mode + ip deny-listed = allow action and log
			$exemptReasonsStr = implode( ', ', $exemptReasons );
			$logger->info(
				$exemptReasonsStr,
				[
					'action' => $action,
					'clientip' => $ip,
					'title' => $title->getPrefixedText(),
					'user' => $user->getName(),
					'reportonly' => $this->config->get( 'SFSReportOnly' )
				]
			);

			return true;

		} elseif ( !$denyListManager->isIpDenyListed( $ip ) ) {
			// report-only mode + ip NOT deny-listed
			return true;
		}

		// log blocked action, regardless of report-only mode
		$blockVerb = ( $this->config->get( 'SFSReportOnly' ) ) ? 'would have been' : 'was';
		$logger->info(
			"{user} {$blockVerb} blocked by SFS from doing {action} "
			. "by using {clientip} on \"{title}\".",
			[
				'action' => $action,
				'clientip' => $ip,
				'title' => $title->getPrefixedText(),
				'user' => $user->getName(),
				'reportonly' => $this->config->get( 'SFSReportOnly' )
			]
		);

		// final catch-all for report-only mode
		if ( $this->config->get( 'SFSReportOnly' ) ) {
			return true;
		}

		// default: set error msg result and return false
		$result = [ 'stopforumspam-blocked', $ip ];
		return false;
	}

	/**
	 * @param array &$msg
	 * @param string $ip
	 * @return bool
	 */
	public function onOtherBlockLogLink( &$msg, $ip ) {
		if (
			!$this->config->get( 'SFSIPListLocation' ) ||
			$this->config->get( 'SFSReportOnly' )
		) {
			return true;
		}

		$denyListManager = DenyListManager::singleton();
		if ( IPUtils::isIPAddress( $ip ) && $denyListManager->isIpDenyListed( $ip ) ) {
			$msg[] = Html::rawElement(
				'span',
				[ 'class' => 'mw-stopforumspam-denylisted' ],
				wfMessage( 'stopforumspam-is-blocked', $ip )->parse()
			);
		}

		return true;
	}
}
