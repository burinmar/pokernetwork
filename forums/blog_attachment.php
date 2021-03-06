<?php
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 4.2.2 Patch Level 4 - Licence Number VBF4B68250
|| # ---------------------------------------------------------------- # ||
|| # Copyright ?2000-2015 vBulletin Solutions Inc. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
|| #################################################################### ||
\*======================================================================*/

/*
 * This file is present for backwards compatibility only.
 * No current links should point here, instead they should use the 
 * integrated attachment functionality.
 */


// ####################### SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE);

// #################### DEFINE IMPORTANT CONSTANTS #######################
define('NOSHUTDOWNFUNC', 1);
define('NOCOOKIES', 1);
define('THIS_SCRIPT', 'blog_attachment');
define('CSRF_PROTECTION', true);
define('VB_AREA', 'Forum');
define('SKIP_SESSIONCREATE', 1);
define('SKIP_USERINFO', 1);
define('SKIP_DEFAULTDATASTORE', 1);
define('NOPMPOPUP', 1);
define('NONOTICES', 1);

// #################### PRE-CACHE TEMPLATES AND DATA ######################
// get special phrase groups
$phrasegroups = array();

// get special data templates from the datastore
$specialtemplates = array();

// pre-cache templates used by all actions
$globaltemplates = array();

// pre-cache templates used by specific actions
$actiontemplates = array();

// ######################### REQUIRE BACK-END ############################
define('CWD', (($getcwd = getcwd()) ? $getcwd : '.'));
require_once(CWD . '/includes/init.php');

$vbulletin->input->clean_array_gpc('r', array(
    'attachmentid' => TYPE_UINT,
));

// #######################################################################
// ######################## START MAIN SCRIPT ############################
// #######################################################################

$imageinfo = false;

if ($vbulletin->GPC['attachmentid'])
{
    $imageinfo = $db->query_first_slave("
        SELECT newattachmentid
        FROM " . TABLE_PREFIX . "blog_attachmentlegacy
        WHERE oldattachmentid = " . $vbulletin->GPC['attachmentid'] . "
    ");
}

if ($imageinfo)
{
    exec_header_redirect("attachment.php?attachmentid=$imageinfo[newattachmentid]", 301);
}
else
{
    $filedata = vb_base64_decode('R0lGODlhAQABAIAAAMDAwAAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw==');
    $filesize = strlen($filedata);
    header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');             // Date in the past
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT'); // always modified
    header('Cache-Control: no-cache, must-revalidate');           // HTTP/1.1
    header('Pragma: no-cache');                                   // HTTP/1.0
    header("Content-disposition: inline; filename=clear.gif");
    header('Content-transfer-encoding: binary');
    header("Content-Length: $filesize");
    header('Content-type: image/gif');
    echo $filedata;
    exit;
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 09:13, Fri Apr 3rd 2015
|| # CVS: $RCSfile$ - $Revision: 32138 $
|| ####################################################################
\*======================================================================*/
?>
