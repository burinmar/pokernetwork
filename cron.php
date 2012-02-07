<?php
require_once ('moon/classes/moon.php');
require_once ('moon/classes/functions.php');
if (is_dev()) {
	ini_set('display_errors', TRUE);
	ini_set('error_reporting', E_ALL);
}
moon_init('ini/moon.ini', 'adm');
moon_reconfig();
$e = & moon :: engine();
if (isset ($_SERVER['argc']) && $_SERVER['argc'] > 1) {
	$e->ini_set('startup', 'sys.cron#background');
}
else {
	$e->ini_set('startup', 'sys.cron#jobs');
}
moon_process();
moon_close();
?>