<?php

$cfg = array();
$cfg['livereporting'] = array(
	'page.LiveReporting1col' => 'xml.livereporting1col,fake',
	'page.LiveReporting2col' => 'xml.livereporting2col,fake',

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
	'tb.PlayersBluff'=> 'reporting_ng_players_bluff',
	'tb.Payouts'     => 'reporting_ng_payouts',
	'tb.Photos'      => 'reporting_ng_photos',
	'tb.SyncLog'     => 'reporting_ng_sync',
	'tb.Tours'       => 'reporting_ng_tours',
	'tb.Winners'     => 'reporting_ng_winners',
	'tb.WinnersList' => 'reporting_ng_winners_list',
	'tb.PlayersPoker'=> 'players_poker',
	'tb.Shoutbox'    => 'reporting_ng_shoutbox',
	'tb.Slider'      => 'reporting_supp_slider',
	'tb.Hands' => 'hands',
	'tb.hLs' => 'hands_list',
	'tb.Constants'   => 'page_constants',

	'comp.rtf' => 'MoonShared.rtf',
	'var.rtf' => 'live-reporting',

	'var.root' => moon::shared('sitemap')->getLink('reporting'),

	'vocabulary' => '{dir.multilang}{module}/{name}.txt',
	'vocabulary{livereporting_event_chips}'   => '{dir.multilang}{module}/livereporting_event.txt',
	'vocabulary{livereporting_event_day}'     => '{dir.multilang}{module}/livereporting_event.txt',
	'vocabulary{livereporting_event_event}'   => '{dir.multilang}{module}/livereporting_event.txt',
	'vocabulary{livereporting_event_photos}'  => '{dir.multilang}{module}/livereporting_event.txt',
	'vocabulary{livereporting_event_tweet}'   => '{dir.multilang}{module}/livereporting_event.txt',
	'vocabulary{livereporting_event_post}'    => '{dir.multilang}{module}/livereporting_event.txt',
	'vocabulary{livereporting_event_profile}' => '{dir.multilang}{module}/livereporting_event.txt',
	'vocabulary{livereporting_event_round}'   => '{dir.multilang}{module}/livereporting_event.txt',
	'vocabulary{livereporting_tournament}'    => '{dir.multilang}{module}/livereporting_tour.txt',
	'vocabulary{livereporting_tour}'          => '{dir.multilang}{module}/livereporting_category.txt',
	'vocabulary{reporting_news}'    => '{dir.multilang}{module}/livereporting_index.txt',

	'dir.web:LogosBigBg' => '/w/lrep/lbbg/',
	'dir.web:LogosSmall' => '/w/lrep/ls/',
	'dir.web:LogosMid'   => '/w/lrep/lm/',
	'dir.web:LogosIdx'   => '/w/lrep/li/',
	'dir.web:LogosM1'    => '/w/lrep/m1/',
	'dir.web:LogosM2'    => '/w/lrep/m2/',
	'dir.fs:LogosBigBg' => _W_DIR_ . 'lrep/lbbg/',
	'dir.fs:LogosSmall' => _W_DIR_ . 'lrep/ls/',
	'dir.fs:LogosMid'   => _W_DIR_ . 'lrep/lm/',
	'dir.fs:LogosIdx'   => _W_DIR_ . 'lrep/li/',
	'dir.fs:LogosIdx'   => _W_DIR_ . 'lrep/li/',
	'dir.web:SliderImgs' => '/w/lrep/slider/',
	'dir.web:SliderDefimg' => '/img/lr-slider-temp.png',

	'var.ipnReadBase'  => is_dev()
		? 'http://imgsrv.dev'
		: 'http://pnimg.net',
	'var.ipnWriteBase' => is_dev()
		? 'http://imgsrv.pokernews.dev'
		: 'http://imgsrv.pokernews.com',
	'var.ipnLoginUrl'  => '/app/',
	'var.ipnUploadUrl' => '/app/app-upload/q/',
	'var.ipnBrowseUrl' => '/app/app-browse/q/',
	'var.ipnReviewUrl' => '/app/app-manage/q/',
	'var.ipnBrowseNewsUrl' => '/app/app-browse_news/q/',
	'var.ipnPwd' => moon::user()->i_admin()
		? 'larry-the-cow'
		: NULL,
	'var.ipnDataReq' => array(
		'b' => array(
				'qs' => 'r:250,250,F;w:02.png,-1,1,T,8,6;',
				'name' => 'Blog post'
			),
		's' => array(
				'qs' => 'c:89,89,T,0,0',
				'name' => 'Gallery small'
			),
		'm' => array(
				'qs' => 'r:800,600,F;w:01.png,-1,1,T,10,10;',
				'name' => 'Gallery big'
			),
		'ms' => array(
				'qs' => 'r:560,420,F;w:03.png,-1,1,T,10,10;',
				'name' => 'Gallery medium-big'
			),
	),

	'tb.Rooms'        => 'rw2_rooms',
	'tb.RoomsSorting' => 'rw2_rooms_sorting',

	'var.skins' => array(
		'img' => '/img/live_poker/default/%d-%s.png',
		'color' => array(
			1 => '#336085',
			2 => '#00625d',
			3 => '#00597a',
			4 => '#005ec8',
			5 => '#000000'
		),
		'tours' =>  '/img/live_poker/tours/%s.png',
	),

	'var.wsopxml' => array(
		148, 167, 194, 253
	),
	
	'var.twitter' => array('Kj7Hvj3E1UjaZt6Da05Ow', 'GJxqUgimQtejlTkWEj7Vge7DiKfdYutjOLkzf5i96as', '41773339-QZA2n0GYQZjWYolmw6nRxr6fBeF6wRQL9qs1V1n9k', 'i6jMUTJ5KbX6xpCicVujkjUjbzfQNXhxXPnGcMRkqDo'),

	'var.hotelsLanguageConfig' => array(
		'com' => array('en_US'),
		'pt' => array('pt_PT')
	)
);

if (is_dev()) {
	$cfg['livereporting']['var.wsopxml'] = array(150, 156, 154);
}

if (is_dev()) {
	$cfg['livereporting']['var.twitter'] = array('oqY0t7uKsjyTN4Vb6nQ', 'XdyKYe9MyrfWE9247x5E4KQ49ClY6JUXbyzJvRZr30', '297837556-nlHyQvRt2Klc4E8DkS1XNYKQtAZp7PhYo2ZdFR5j', 'yQcn7FWiFtaK6eQu8JvP9nA5v98tvvitVOIJfCF1sU');
}

if (moon::user()->i_admin())
	moon::page()->set_global('adminView', 1);