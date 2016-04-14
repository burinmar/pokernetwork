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

error_reporting(E_ALL & ~E_NOTICE);

require_once('./includes/class_bootstrap.php');

define('VB_AREA', 'Forum');

$bootstrap = new vB_Bootstrap_Forum();
$bootstrap->datastore_entries = $specialtemplates;
$bootstrap->cache_templates = vB_Bootstrap::fetch_required_template_list(
	empty($_REQUEST['do']) ? '' : $_REQUEST['do'],
	$actiontemplates, $globaltemplates
);

$bootstrap->bootstrap();

// Deprecated as of release 4.0.2, replaced by global_bootstrap_init_start
($hook = vBulletinHook::fetch_hook('global_start')) ? eval($hook) : false;

$bootstrap->load_style();

// legacy code needs this
$permissions = $vbulletin->userinfo['permissions'];

// Deprecated as of release 4.0.2, replaced by global_bootstrap_complete
($hook = vBulletinHook::fetch_hook('global_setup_complete')) ? eval($hook) : false;

if (!empty($db->explain))
{
	$aftertime = microtime(true) - TIMESTART;
	echo "End call of global.php: $aftertime\n";
	echo "\n<hr />\n\n";
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 09:13, Fri Apr 3rd 2015
|| # CVS: $RCSfile$ - $Revision: 62099 $
|| ####################################################################
\*======================================================================*/
