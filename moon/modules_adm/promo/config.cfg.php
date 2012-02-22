<?php

$cfg['promo'] = array(
	'page.Common' => 'sys.adm,fake',

	'tb.Entries{promos}'             => 'promos',
	'tb.EntriesMaster{promos}'       => 'promos_push',

	'tb.Entries{custom_pages}'       => 'promos_pages',
	'tb.EntriesMaster{custom_pages}' => 'promos_pages_push',

	'tb.Entries{schedule}'           => 'promos_events',
	'tb.EntriesMaster{schedule}'     => 'promos_events_push',
	
	'tb.Entries{leaderboard}'        => 'promos',
	'tb.EntriesMaster{leaderboard}'  => 'promos_push',

	'dir.srcCustomRooms{rooms}' => '/w/lrooms/',
	'dir.CustomRooms{rooms}'    => _W_DIR_ . 'lrooms/',	
	'tb.Leagues{rooms}'         => 'promos',
	'tb.Events{rooms}'          => 'promos_events',

	'var.entriesPerPage' => 25,
	'var.rtf' => 'promos',

	'tb.Rooms' => 'rw2_rooms',
	'tb.CustomRooms' => 'promos_rooms',
	'comp.rtf' => 'MoonShared.rtf',
);