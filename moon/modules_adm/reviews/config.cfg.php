<?php
$cfg = array();
$cfg['reviews'] = array(

	'sys.moduleDir' => 'reviews/',
	'sys.multiLang' => 0,
	'sys.db' => '',

	'tb.Rooms' => 'rw2_rooms',
	'tb.RoomsSorting' => 'rw2_rooms_sorting',
	'tb.PromoList' => 'rw2_promo_list',
	'tb.PromotionsMaster' => 'rw2_promo_master',
	'tb.Reviews' => 'rw2_reviews',
	'tb.Pages' => 'rw2_pages',
	'tb.Team' => 'rw2_team',
	'tb.Members' => 'team_members',
	'tb.Tournaments' => 'team_tournaments',
	'tb.Sponsors' => 'rooms_and_sponsors',
	'tb.SponsorsUri' => 'rooms_and_sponsors_uri',
	'tb.Users' => 'users_master',
	'tb.PageStructure' => 'team_structure',
	'tb.Reporting' => 'team_reporting',
	'tb.Deposits' => 'rw2_deposits',
	'tb.DepositsRooms' => 'rw2_deposits_rooms',
	'tb.Stats' => 'rw2_stats',
	'tb.SiteInfo' => 'rw2_siteinfo',

	'dir.dirAttachments' => _W_DIR_.'rw-attachments/',

	'dir.charts' => _W_DIR_ . 'charts/',

	'var.avatar200' => '/i/avatar200x200.gif',
	'var.avatar100' => '/i/avatar100x100.gif',
	'var.avatar50' => '/i/avatar50x50.gif',
'dir.dirGallery' => _W_DIR_ . 'rw-screenshots/',
	'dir.dirDeposit' => _W_DIR_ . 'rw-deposits/',
	'var.dirAvatar' => 'members/',

	'page.Common' => 'sys.adm,fake',

	'comp.sorting_linux' => 'sorting',
	'comp.sorting_mac' => 'sorting',

	#rtf
    'comp.rtf' => 'MoonShared.rtf',
	'var.rtf{reviews}' => 'reviews',
	'var.rtf{pages}' => 'reviews:1',
	'var.rtf' => '',


);
?>