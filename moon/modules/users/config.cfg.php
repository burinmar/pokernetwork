<?php
$cfg = array();
$cfg['users'] = array(
	'vocabulary' => '{dir.multilang}{module}.txt;{dir.multilang}shared.txt',
	'tb.Users' => 'users',
	'tb.UsersRooms' => 'users_rooms',
	'tb.UsersAccess' => 'users_levels',
	'tb.UsersCriminals' => 'users_criminals',
	'tb.Subscribers' => 'subscribers', 
	'tb.Rooms' => 'rw2_rooms',

    'page.Signup' => 'xml.1col,fake',
    'page.Profile' => 'xml.1col,fake',
    'page.Users' => 'xml.1col,fake',

	'comp.login_object' => 'MoonShared.login_object',

	//'var.srcAvatars' => '/w/avatars/',
	//'var.srcRoomsLogo' => '/w/rw-logo/'
);
?>