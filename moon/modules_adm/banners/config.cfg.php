<?php
$cfg = array();
$cfg['banners'] = array(
	'sys.moduleDir' => 'banners/',
	'sys.multiLang' => 0,

	'tb.Banners' => 'banners',
	'tb.BannersMedia' => 'banners_media',
	'tb.Campaigns' => 'banners_campaigns',
	'tb.CampaignsBanners' => 'banners_campaigns_banners',
	'tb.BannersStats' => 'banners_stats',
	'tb.Rooms' => 'rw2_rooms',
	'tb.Servers' => 'servers',
 
	'var.env' => '',

	'var.srcBanners' => '/w/ads/',
	'dir.Banners' => 'w/ads/',

	'page.Common' => 'sys.adm,fake',

	// environment:place:position
	'var.zones' => array(
		'std:wide:1' => array(
			'title' => 'Top wide (728x90)',
			'bn' => 'TopWide'
		),
		'home:wide:1' => array(
			'title' => 'Homepage wide (728x90)',
			'bn' => 'HomeWide'
		),
		'std:right:1' => array(
			'title' => 'Right column (200x*)',
			'bn' => 'RightColumn'
		),
		'std:right:2' => array(
			'title' => 'Right column 2 (200x*)',
			'bn' => 'RightColumn2'
		),
	),
	'var.urlTargets' => array(
		'news' => array(
			'News' => array(
				'video'        	=> 'Video',
				'poker-players'	=> 'Players'
			)
		),
		'learn-poker' => array(
			'Learn Poker' => array(
				'rules'	=> 'Rules',
				'strategy' => 'Strategy'
			)
		),
		'online-poker' => array(
			'Play Poker' => array(
				'rooms'	=> 'Poker Rooms'
			)
		),
		'reporting' => array(
			'Live Reporting' => array()
		)
	),
	'var.sitesNames' => array(
		1 => array('id' => 1, 'site_id' => 'www.pokernetwork.com')
	),
	'var.sitesUrls' => array(1 => moon::page()->home_url()),
	'var.limitedToSites' => array()
);
?>