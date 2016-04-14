<?php
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 4.2.2 Patch Level 4 - Licence Number VBF4B68250
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2015 vBulletin Solutions Inc. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
|| #################################################################### ||
\*======================================================================*/
if (!VB_API) die;

define('VB_API_LOADLANG', true);

loadCommonWhiteList();

$VB_API_WHITELIST = array(
	'response' => array(
		'header' => array(
			'pmbox', 'notifications_menubits', 'notifications_total', 'notices'
		),
		'activemembers', 'activeusers', 'birthdays',
		'forumbits' => array(
			'*' => $VB_API_WHITELIST_COMMON['forumbit']
		),
		'newuserinfo', 'numberguest', 'numbermembers', 'numberregistered',
		'recorddate', 'recordtime', 'recordusers',
		'template_hook' => array(
			'forumhome_wgo_stats' => array(
				'blogstats',
				'latestentry' => array(
					'username', 'userid', 'title', 'blogid', 'postedby_username', 'postedby_userid', 'blogtitle'
				)
			)
		),
		'today', 'totalonline', 'totalposts', 'totalthreads', 'upcomingevents',
	),
	'show' => array(
		'birthdays', 'todaysevents', 'notices', 'dismiss_link', 'notifications',
		'loggedinusers', 'pmlink', 'homepage', 'addfriend', 'emaillink', 'activemembers'
	)
);

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 09:13, Fri Apr 3rd 2015
|| # CVS: $RCSfile$ - $Revision: 35584 $
|| ####################################################################
\*======================================================================*/