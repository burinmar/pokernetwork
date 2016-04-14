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

loadCommonWhiteList();

$VB_API_WHITELIST = array(
	'response' => array(
		'content' => array(
			'attachmentoption' => $VB_API_WHITELIST_COMMON['attachmentoption'],
			'disablesmiliesoption',
			'bloginfo' => $VB_API_WHITELIST_COMMON['bloginfo'],
			'blogtextinfo' => array('blogtextid'),
			'messagearea' => array(
				'newpost'
			),
			'notification', 'posthash', 'postpreview','reason',
			'title',
			'human_verify' => $VB_API_WHITELIST_COMMON['humanverify']
		)
	)
);

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 09:13, Fri Apr 3rd 2015
|| # CVS: $RCSfile$ - $Revision: 35584 $
|| ####################################################################
\*======================================================================*/