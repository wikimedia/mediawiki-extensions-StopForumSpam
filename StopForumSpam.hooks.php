<?php


class SFSHooks {

	/**
	 * Some JS to add our checkbox
	 * @param HTMLForm $form
	 * @return bool
	 */
	public static function onSpecialBlockBeforeFormDisplay( HTMLForm $form ) {
		if ( $form->getUser()->isAllowed( 'stopforumspam' ) ) {
			$form->getOutput()->addModules( 'ext.SFS.formhack' );
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
						wfDebug( "Could not detect valid user from \"{$block->getTarget()}\"" );
					}
				}

			}
		}

		return true;
	}
}