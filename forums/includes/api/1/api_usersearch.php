<?php
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin Blog 4.2.2 Patch Level 4 - Licence Number VBF4B68250
|| # ---------------------------------------------------------------- # ||
|| # Copyright ?2000-2015 vBulletin Solutions Inc. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
|| #################################################################### ||
\*======================================================================*/
if (!VB_API) die;

class vB_APIMethod_api_usersearch extends vBI_APIMethod
{
	public function output()
	{
		global $vbulletin, $db;

		$vbulletin->input->clean_array_gpc('p', array('fragment' => TYPE_STR));

		$vbulletin->GPC['fragment'] = convert_urlencoded_unicode($vbulletin->GPC['fragment']);

		if ($vbulletin->GPC['fragment'] != '' AND strlen($vbulletin->GPC['fragment']) >= 3)
		{
			$fragment = htmlspecialchars_uni($vbulletin->GPC['fragment']);
		}
		else
		{
			$fragment = '';
		}

		if ($fragment != '')
		{
			$users = $db->query_read_slave("
				SELECT user.userid, user.username FROM " . TABLE_PREFIX . "user
				AS user WHERE username LIKE('" . $db->escape_string_like($fragment) . "%')
				ORDER BY username
				LIMIT 15
			");
			while ($user = $db->fetch_array($users))
			{
				$data[$user['userid']] = $user['username'];
			}
		}

		return $data;

	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 09:13, Fri Apr 3rd 2015
|| # CVS: $RCSfile$ - $Revision: 26995 $
|| ####################################################################
\*======================================================================*/