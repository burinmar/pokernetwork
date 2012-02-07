<?php
require_once ('moon/classes/moon.php');
require_once ('moon/classes/functions.php');
if (is_dev()) {
	ini_set('display_errors', TRUE);
	ini_set('error_reporting', E_ALL);
}
moon_init('ini/moon.ini', 'adm');
moon_reconfig();
$all = moon_process();
echo $all;
moon_close();
?>