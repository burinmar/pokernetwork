<?php

function vb_init_moon()
{
	if (defined('MOON_MODULES'))
		return ;
	define('MOON_MODULES', '../moon/modules/');
	require_once ('../moon/classes/moon.php');
	require_once ('../moon/classes/functions.php');
	if (is_dev()) {
		ini_set('display_errors', TRUE);
		ini_set('error_reporting', E_ALL);
	}
	$_GET['url_'] ='/vb-mock-noexist';
	moon_init('../ini/moon.ini', 'engine');
	moon_reconfig();
	$e = & moon :: engine();
	$e->ini_set('startup', 'sys.forum#startup');
	$e->ini_set('output', 'sys.forum');

	$all = moon_process();
	moon::shared('sitemap')->on('forum');
	unset($_GET['url_']);
}
vb_init_moon();

function vb_moon_userpage()
{
	$oldRequestUri = $_SERVER['REQUEST_URI'];
	if ($_SERVER['VBSEO_URI'])
		$_SERVER['REQUEST_URI'] = $_SERVER['VBSEO_URI'];
	moon::user()->dump_to_session();
	moon::page()->close();
	$_SERVER['REQUEST_URI'] = $oldRequestUri;
}
// moon_close();

