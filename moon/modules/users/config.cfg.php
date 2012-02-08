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

    'page.Signup' => 'xml.col1,fake',
    'page.Profile' => 'xml.col1,fake',
    'page.Users' => 'xml.col1,fake',

	'comp.login_object' => 'MoonShared.login_object',

	//'var.srcAvatars' => '/w/avatars/',
	//'var.srcRoomsLogo' => '/w/rw-logo/'
);
?>