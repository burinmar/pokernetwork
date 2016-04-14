<?php

if (!defined('NOHEADER') && !defined('NOCOOKIES')) {
	require_once(dirname(dirname(__FILE__)) . '/vb_moon.php');

	// track moon history and login moon if logged in on forum
	moon::engine()->call_event('sys.startup#forum');

	vb_moon_userpage();
}

