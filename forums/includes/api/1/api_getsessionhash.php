<?php
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin Blog 4.2.2 Patch Level 4 - Licence Number VBF4B68250
|| # ---------------------------------------------------------------- # ||
|| # Copyright �2000-2015 vBulletin Solutions Inc. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
|| #################################################################### ||
\*======================================================================*/
if (!VB_API) die;

class vB_APIMethod extends vBI_APIMethod
{
	public function output()
	{
		global $vbulletin;

		return $vbulletin->session->vars['dbsessionhash'];
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 09:13, Fri Apr 3rd 2015
|| # CVS: $RCSfile$ - $Revision: 26995 $
|| ####################################################################
\*======================================================================*/