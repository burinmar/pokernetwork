<?php
require_once ('moon/classes/moon.php');
require_once ('moon/classes/functions.php');
if (is_dev()) {
	ini_set('display_errors', TRUE);
	ini_set('error_reporting', E_ALL);
}
/*elseif (isset ($_SERVER['HTTP_HOST'])) {
	//	DEMO SECURITY
	if(isset($_POST['demopass']) && $_POST['demopass']=='welcome'){
		setcookie('demoin',1,time()+3600*6,'/');
	} else if((!isset($_COOKIE['demoin']) || $_COOKIE['demoin']!=1) && (!isset($_COOKIE['voter2']) || $_COOKIE['voter2']!=='Transporter-Pokernews')){?>
		<html><body><form method="POST">Enter password: <input type="password" name="demopass"> <input type="submit" value="Login"></form></body></html>
		<?	die();
	}
	//DEMO SECURITY
}*/
moon_init('ini/moon.ini', 'engine');
moon_reconfig();
$all = moon_process();
echo $all;
if (strpos($all, '{!') !== FALSE) {
	moon :: error('{! bug');
}
moon_close();
?>