<?php

/* ======================================================================*\
  || #################################################################### ||
  || # vBulletin 4.2.2 Patch Level 4 - Licence Number VBF4B68250
  || # ---------------------------------------------------------------- # ||
  || # Copyright ©2000-2015 vBulletin Solutions Inc. All Rights Reserved. ||
  || # This file may not be redistributed in whole or significant part. # ||
  || # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
  || # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
  || #################################################################### ||
  \*====================================================================== */

/**
 * Class to update the popularity score of stream items
 *
 * @package	vBulletin
 * @version	$Revision: 57655 $
 * @date		$Date: 2012-01-09 12:08:39 -0800 (Mon, 09 Jan 2012) $
 */
class vB_ActivityStream_Popularity_Forum_Thread extends vB_ActivityStream_Popularity_Base
{
	/**
	 * Constructor - set Options
	 *
	 */
	public function __construct()
	{
		return parent::__construct();
	}

	/*
	 * Update popularity score
	 *
	 */
	public function updateScore()
	{
		if (!vB::$vbulletin->activitystream['forum_thread']['enabled'])
		{
			return;
		}

		$typeid = vB::$vbulletin->activitystream['forum_thread']['typeid'];
		vB::$db->query_write("
			UPDATE " . TABLE_PREFIX . "activitystream AS a
			INNER JOIN " . TABLE_PREFIX . "thread AS t ON (a.contentid = t.threadid)
			SET
				a.score = (1 + ((t.replycount + t.postercount) / 10) + (t.votenum / 100) + (t.views / 1000) )
			WHERE
				a.typeid = {$typeid}
		");
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 09:13, Fri Apr 3rd 2015
|| # CVS: $RCSfile$ - $Revision: 57655 $
|| ####################################################################
\*======================================================================*/