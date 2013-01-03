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
	'tb.PlayersPoker'=> 'players_poker',
	'tb.Shoutbox'    => 'reporting_ng_shoutbox',
	'tb.Slider'      => 'reporting_supp_slider',
	'tb.Hands' => 'hands',
	'tb.hLs' => 'hands_list',
	'tb.Constants'   => 'page_constants',

	'comp.rtf' => 'MoonShared.rtf',
	'var.rtf' => 'live-reporting',

	'var.root' => moon::shared('sitemap')->getLink('reporting'),

	'vocabulary' => '{dir.multilang}livereporting/{name}.txt',
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
	'vocabulary{reporting_news}'              => '{dir.multilang}{module}/livereporting_index.txt',
	'vocabulary{reporting_hotels}'            => '{dir.multilang}/hotels.txt',
	'vocabulary{rtf}'                    => '{dir.multilang}/shared.txt',

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
	'var.pnetworkIpnPubKey' => moon::user()->i_admin() ?
		'-----BEGIN PRIVATE KEY-----'. "\n" . 'MIICdgIBADANBgkqhkiG9w0BAQEFAASCAmAwggJcAgEAAoGBAK1MXQw2Ft3y2voo' . "\n" . 
		'f3UERqrK52DmVcFoDEmoxLzRogNIWbBDDs7Jg6jt2UFeIjOeQG03Ve4+stTBQFlW' . "\n" . 'DPmyxYiJZTFczDw9IMncqwZrEKyPoYZn2rnwuaCC9uS0K0ZizM4KRWE+Gtm9/rGp' . "\n" .
		'oaI3DTCOAlr5DjZfyqrvJ4ou3ZyrAgMBAAECgYBySdtPk1hvVZtuwUiVjpVgS+Je' . "\n" . 'OGmz3H/GEER3VjVnvs5PfVoFrEZlIT9XadJagnJw1L6aGcVspGpgwGxcPLciJvOh' . "\n" .
		'DWakjsixhD2HiVzqeBZgbeFaJyQ4cL3fOhJ7JGs5M+dQntH6/vru0efFq9/r7M34' . "\n" . 'tsj1R139kjQH6zGg8QJBAOPpUcvO/MdrbUvyIJr8mWEOnZafbnlC+/XYVZX5Xf+7' . "\n" .
		'3q7x5eeBRRJEv9BYu0piavAgF1X+are+tManPCQu/WMCQQDCp/tyKuKdD0cPIze8' . "\n" . 'L6dUQ1wgK1sNncvtie6mrnNNACnE4jHKf6CF5vYICa0Qd+fvva+yzp4x85Ccwtf4' . "\n" .
		'awoZAkBL5gqAZfkWbDckwIiNNKvbf6OJYqTGq0D/WUD/ReFA5g69kf8vPoSDFfQO' . "\n" . '7U3J8saMGxPKZoShllfbhBvqLN/jAkEAsuP4NJru+lJhBqth/GN7Dvh8wN+3ZVMg' . "\n" .
		'Z+Q8XBi0xA4jVNpf0e7q0kRI1Wg3EYxwriYQQ26ZeeSSgU6bdqTAeQJAEeOoWBzJ' . "\n" . 'cj+MUjiC/XXm6b0yy83MGE6dGCZY6sI1zzsVwlFMyUsJBL4b6C8JudnZBcH6CoyZ' . "\n" .
		'KtLw4RjRdh780Q==' . "\n" . '-----END PRIVATE KEY-----' : null,

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

	'var.hotelsLanguageConfig' => array(
		'com' => array('en_US'),
		'pt' => array('pt_PT')
	)
);

		
if (moon::page()->get_global('adminView')) {
	$db = moon::db();
	if (method_exists($db, 'operateOnMaster')) {
		$db->operateOnMaster();
	}
}

if (!is_dev()) {
	unset($cfg['livereporting']['var.hotelsLanguageConfig']['pt']);
}
if (moon::user()->i_admin())
	moon::page()->set_global('adminView', 1);
