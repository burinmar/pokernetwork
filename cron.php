<?php
require_once ('moon/classes/moon.php');
require_once ('moon/classes/functions.php');
if (is_dev()) {
	ini_set('display_errors', TRUE);
	ini_set('error_reporting', E_ALL);
}
moon_init('ini/moon.ini', 'adm');
$e = & moon :: engine();
if (isset ($_SERVER['argc']) && $_SERVER['argc'] > 1) {
	$e->ini_set('startup', 'sys.cron#background');
}
else {
	$e->ini_set('startup', 'sys.cron#jobs');
}
$ini = & moon :: moon_ini();
$e->ini_set('home_url', $ini->get('site', 'home_url'));
$p = & moon :: page();
$p->home_url = $e->ini('home_url');
moon_process();
moon_close();
?>