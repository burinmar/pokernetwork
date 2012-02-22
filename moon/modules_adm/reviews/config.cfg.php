<?php
$cfg = array();
$cfg['reviews'] = array(

	'sys.moduleDir' => 'reviews/',
	'sys.multiLang' => 0,
	'sys.db' => '',

	'tb.Rooms' => 'rw2_rooms',
	'tb.PromoList' => 'rw2_promo_list',
	'tb.PromotionsMaster' => 'rw2_promo_master',
	'tb.Reviews' => 'rw2_reviews',
	'tb.Pages' => 'rw2_pages',
	'tb.Users' => 'users_master',
	'tb.Deposits' => 'rw2_deposits',
	'tb.DepositsRooms' => 'rw2_deposits_rooms',
	'tb.Stats' => 'rw2_stats',

	'dir.dirAttachments' => _W_DIR_.'rw-attachments/',

	'dir.charts' => _W_DIR_ . 'charts/',

	'var.avatar200' => '/img/avatar200x200.gif',
	'var.avatar100' => '/img/avatar100x100.gif',
	'var.avatar50' => '/img/avatar50x50.gif',
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