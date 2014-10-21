<?php

$cfg['promo'] = array(
	'page.Common' => 'sys.adm,fake',

	'tb.Entries{promos}'             => 'promos',
	'tb.EntriesMaster{promos}'       => 'promos_push',

	'tb.Entries{custom_pages}'       => 'promos_pages',
	'tb.EntriesMaster{custom_pages}' => 'promos_pages_push',

	'tb.Entries{schedule}'           => 'promos_events',
	'tb.EntriesMaster{schedule}'     => 'promos_events_push',
	'tb.Freerolls{schedule}'         => 'tournaments_special',

	'var.entriesPerPage' => 25,
	'var.rtf' => 'promos',

	'tb.Rooms' => 'rw2_rooms',
	'tb.CustomRooms' => 'promos_rooms',
	'comp.rtf' => 'MoonShared.rtf',
);

require_once dirname(__FILE__) . '/../sys/moon_com_ext.php';
require_once dirname(__FILE__) . '/promos_base.php';