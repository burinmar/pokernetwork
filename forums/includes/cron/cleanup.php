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

// ######################## SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE);
if (!is_object($vbulletin->db))
{
	exit;
}

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

$vbulletin->db->query_write("
	DELETE FROM " . TABLE_PREFIX . "session
	WHERE lastactivity < " . intval(TIMENOW - $vbulletin->options['cookietimeout']) . "
");

$vbulletin->db->query_write("
	DELETE FROM " . TABLE_PREFIX . "cpsession
	WHERE dateline < " . ($vbulletin->options['timeoutcontrolpanel'] ? intval(TIMENOW - $vbulletin->options['cookietimeout']) : TIMENOW - 3600) . "
");

require_once(DIR . '/vb/search/results.php');
vB_Search_Results::clean();

// expired lost passwords and email confirmations after 4 days
$vbulletin->db->query_write("
	DELETE FROM " . TABLE_PREFIX . "useractivation
	WHERE dateline < " . (TIMENOW - 345600) . " AND
	(type = 1 OR (type = 0 and usergroupid = 2))
");

// old forum/thread read marking data
$vbulletin->db->query_write("
	DELETE FROM " . TABLE_PREFIX . "threadread
	WHERE readtime < " . (TIMENOW - ($vbulletin->options['markinglimit'] * 86400))
);
$vbulletin->db->query_write("
	DELETE FROM " . TABLE_PREFIX . "forumread
	WHERE readtime < " . (TIMENOW - ($vbulletin->options['markinglimit'] * 86400))
);
$vbulletin->db->query_write("
	DELETE FROM " . TABLE_PREFIX . "groupread
	WHERE readtime < " . (TIMENOW - ($vbulletin->options['markinglimit'] * 86400))
);
$vbulletin->db->query_write("
	DELETE FROM " . TABLE_PREFIX . "discussionread
	WHERE readtime < " . (TIMENOW - ($vbulletin->options['markinglimit'] * 86400))
);

// delete expired thread redirects
$threads = $vbulletin->db->query_read("
	SELECT threadid
	FROM " . TABLE_PREFIX . "threadredirect
	WHERE expires < " . TIMENOW . "
");

while ($thread = $vbulletin->db->fetch_array($threads))
{
	$thread['open'] = 10;
	$threadman =& datamanager_init('Thread', $vbulletin, ERRTYPE_SILENT, 'threadpost');
	$threadman->set_existing($thread);
	$threadman->delete(false, true, NULL, false);
	unset($threadman);
}

vB_Cache::instance()->clean();

($hook = vBulletinHook::fetch_hook('cron_script_cleanup_hourly')) ? eval($hook) : false;

log_cron_action('', $nextitem, 1);

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 09:13, Fri Apr 3rd 2015
|| # CVS: $RCSfile$ - $Revision: 62098 $
|| ####################################################################
\*======================================================================*/
