<?php

$cfg = array();
$cfg['livereporting'] = array(
	'sys.moduleDir' => 'livereporting/',
	'sys.multiLang' => 0,
	'tb.Tournaments' => 'reporting_ng_tournaments',
	'tb.Events'      => 'reporting_ng_events',
	'tb.Days'        => 'reporting_ng_days',
	'tb.Log'         => 'reporting_ng_log',
	'tb.tPosts'      => 'reporting_ng_sub_posts',
	'tb.tTweets'     => 'reporting_ng_sub_tweets',
	'tb.tDays'       => 'reporting_ng_sub_days',
	'tb.tRounds'     => 'reporting_ng_sub_rounds',
	'tb.tChips'      => 'reporting_ng_sub_chips',
	'tb.tPhotos'     => 'reporting_ng_sub_photos',
	'tb.Tags'        => 'reporting_ng_tags',
	'tb.Chips'       => 'reporting_ng_chips',
	'tb.Players'     => 'reporting_ng_players',
	'tb.Payouts'     => 'reporting_ng_payouts',
	'tb.Photos'      => 'reporting_ng_photos',
	'tb.SyncLog'     => 'reporting_ng_sync',
	'tb.Tours'       => 'reporting_ng_tours',
	'tb.Winners'     => 'reporting_ng_winners',
	'tb.WinnersList' => 'reporting_ng_winners_list',
	'tb.Rooms'       => 'rw2_rooms',
	'tb.Constants'   => 'page_constants',
	'tb.Entries{slider}'   => 'reporting_supp_slider',
	'dir.fs:LogosBigBg' => _W_DIR_ . 'lrep/lbbg/',
	'dir.fs:LogosSmall' => _W_DIR_ . 'lrep/ls/',
	'dir.fs:LogosMid'   => _W_DIR_ . 'lrep/lm/',
	'dir.fs:LogosIdx'   => _W_DIR_ . 'lrep/li/',
	'dir.fs:LogosM1'    => _W_DIR_ . 'lrep/m1/',
	'dir.fs:LogosM2'    => _W_DIR_ . 'lrep/m2/',
	'dir.fs:ImgsSlider' => _W_DIR_ . 'lrep/slider/',
	'dir.web:LogosBigBg' => '/w/lrep/lbbg/',
	'dir.web:LogosSmall' => '/w/lrep/ls/',
	'dir.web:LogosMid'   => '/w/lrep/lm/',
	'dir.web:LogosIdx'   => '/w/lrep/li/',
	'dir.web:LogosM1'    => '/w/lrep/m1/',
	'dir.web:LogosM2'    => '/w/lrep/m2/',
	'dir.web:ImgsSlider' => '/w/lrep/slider/',
	'var.twitter' => array('Kj7Hvj3E1UjaZt6Da05Ow', 'GJxqUgimQtejlTkWEj7Vge7DiKfdYutjOLkzf5i96as', '41773339-QZA2n0GYQZjWYolmw6nRxr6fBeF6wRQL9qs1V1n9k', 'i6jMUTJ5KbX6xpCicVujkjUjbzfQNXhxXPnGcMRkqDo'),
	'var.entriesPerPage' => 50,
	'page.Common' => 'sys.adm,fake',
	'comp.rtf' => 'MoonShared.rtf',
	'var.rtf' => 'livereporting'
);

function livereporting_adm_alt_log($trnId = 0, $evId = 0, $dayId = 0, $type = 'other', $table = 'other', $id = '0', $comment = '', $userId = NULL)
{
	static $currentUserId, $db;
	if (!isset($db)) {
		$currentUserId = moon::user()->id();
		$db = moon::db();
	}
	if (0) {
		return ;
	}
	$userId = $userId == NULL
		? $currentUserId
		: intval($userId); // null => 0
	$db->query('
		INSERT INTO reporting_ng_alt_log
		(trn_id, ev_id, day_id, type, performed_by, object_table, object_id, comment)
		VALUES(
			"' . $db->escape($trnId) . '",
			"' . $db->escape($evId) . '",
			"' . $db->escape($dayId) . '",
			"' . $db->escape($type) . '",
			"' . $db->escape($userId) . '",
			"' . $db->escape($table) . '",
			"' . $db->escape($id) . '",
			"' . $db->escape($comment) . '"
		)
	');
}

$db = moon::db();
if (method_exists($db, 'operateOnMaster')) {
	$db->operateOnMaster();
}
