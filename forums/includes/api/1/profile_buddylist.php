<?php
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 4.2.2 Patch Level 4 - Licence Number VBF4B68250
|| # ---------------------------------------------------------------- # ||
|| # Copyright �2000-2015 vBulletin Solutions Inc. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
|| #################################################################### ||
\*======================================================================*/
if (!VB_API) die;

$VB_API_WHITELIST = array(
	'response' => array(
		'HTML' => array(
			'buddycount',
			'buddylist' => array(
				'*' => array(
					'container', 'friendcheck_checked',
					'user' => array(
						'userid', 'usertitle', 'avatarurl', 'avatarwidth', 'avatarheight',
						'username', 'type', 'checked'
					),
					'show' => array(
						'incomingrequest', 'outgoingrequest', 'friend_checkbox'
					)
				)
			),
			'buddy_username',
			'incominglist' => array(
				'*' => array(
					'container', 'friendcheck_checked',
					'user' => array(
						'userid', 'usertitle', 'avatarurl', 'avatarwidth', 'avatarheight',
						'username', 'type', 'checked'
					),
					'show' => array(
						'incomingrequest', 'outgoingrequest', 'friend_checkbox'
					)
				)
			),
			'perpage', 'pagenumber', 'pagenav'
		)
	),
	'show' => array(
		'friend_controls', 'incomingrequest', 'outgoingrequest', 'friend_checkbox',
		'incominglist', 'buddylist', 'avatars'
	)
);

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 09:13, Fri Apr 3rd 2015
|| # CVS: $RCSfile$ - $Revision: 35584 $
|| ####################################################################
\*======================================================================*/