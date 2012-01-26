<?php
require_once ('moon/classes/moon.php');
require_once ('moon/classes/functions.php');
if (is_dev()) {
	ini_set('display_errors', TRUE);
	ini_set('error_reporting', E_ALL);
}
moon_init('ini/moon.ini', 'adm');
$e = & moon :: engine();
$ini = & moon :: moon_ini();
$e->ini_set('home_url', $ini->get('site', 'home_url'));
$p = & moon :: page();
$p->home_url = $e->ini('home_url');
$all = moon_process();
echo $all;
moon_close();
?>