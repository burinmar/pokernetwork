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

function parse_video_bbcode($pagetext)
{
	global $vbulletin;

	($hook = vBulletinHook::fetch_hook('data_parse_bbcode_video')) ? eval($hook) : false;

	if (stripos($pagetext, '[video]') !== false)
	{
		require_once(DIR . '/includes/class_bbcode_alt.php');
		$parser = new vB_BbCodeParser_Video_PreParse($vbulletin, array());
		$pagetext = $parser->parse($pagetext);
	}

	return $pagetext;
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 09:13, Fri Apr 3rd 2015
|| # CVS: $RCSfile$ - $Revision: 27207 $
|| ####################################################################
\*======================================================================*/
?>
