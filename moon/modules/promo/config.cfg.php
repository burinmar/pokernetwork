<?php

$cfg = array();
$cfg['promo'] = array(
	'tb.Promos' => 'promos',
	'tb.PromosMaster' => 'promos_push',
	'tb.PromosPages' => 'promos_pages',
	'tb.PromosPagesMaster' => 'promos_pages_push',
	'tb.PromosEvents' => 'promos_events',
	'tb.PromosEventsMaster' => 'promos_events_push',
	'tb.Rooms' => 'rw2_rooms',
	'tb.Trackers' => 'rw2_trackers',
	'tb.CustomRooms'=>'promos_rooms',

	'dir.fs:Css' => (is_dev() ? getenv("CMS_PATH") . 'img/' : 'img/') . 'promo/',
	'dir.web:Css' => '/img/promo/',
	'dir.CustomRooms'=>'/w/lrooms/',

	'vocabulary'=>'{dir.multilang}{module}.txt',
	'page.Main' =>'xml.col,fake',
	'page.Main{promo}' =>'xml.col,fake',
	'comp.rtf'=>'MoonShared.rtf',
	'var.rtf' => 'promos',
);

