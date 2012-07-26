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
		'home:2col:1' => array(
			'title' => 'Homepage below Twitter (468x80)',
			'bn' => 'Home2Col'
		),
		'std:right:1' => array(
			'title' => 'Right column (200x*)',
			'bn' => 'RightColumn'
		),
		'home:top:1' => array(
			'title' => '728x* - Homepage top',
			'bn' => 'HomeTop'
		),
	),
	'var.zones.preroll' => array(
		'home' => array(
			'title' => 'Pre-roll Homepage',
			'bn' => 'PrerollHomepage'
		),
		'reporting' => array(
			'title' => 'Pre-roll Live Reporting',
			'bn' => 'PrerollLiveReporting'
		),
		'video' => array(
			'title' => 'Pre-roll Video page',
			'bn' => 'PrerollVideoPage'
		),
		'embed' => array(
			'title' => 'Pre-roll Embed',
			'bn' => 'PrerollEmbed'
		)
	),
	'var.urlTargets' => array(
		'video' => array(
			'Videos' => array()
		),
		'rooms' => array(
			'Online Poker' => array()
		),
		'news' => array(
			'News' => array()
		),
		'freerolls-special' => array(
			'Freerolls' => array()
		),
		'reporting' => array(
			'Live Reporting' => array()
		),
		'blogs' => array(
			'Blogs' => array()
		)
	),
	'var.sitesNames' => array(
		1 => array('id' => 1, 'site_id' => 'www.pokernetwork.com')
	),
	'var.sitesUrls' => array(1 => moon::page()->home_url()),
	'var.limitedToSites' => array()
);
?>