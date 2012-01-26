<?php
require_once ('moon/classes/moon.php');
require_once ('moon/classes/functions.php');
if (is_dev()) {
	ini_set('display_errors', TRUE);
	ini_set('error_reporting', E_ALL);
}
/*elseif (isset ($_SERVER['HTTP_HOST'])) {
	//	DEMO SECURITY
	$sites = array('bg','nl','de','gr','hu','it','ro','si');
	foreach ($sites as $id) {
		if (strpos($_SERVER['HTTP_HOST'], $id.'.casinogrinder')!==FALSE) {

			if(isset($_POST['demopass']) && $_POST['demopass']=='welcome-' . $id){
				setcookie('demoin',1,time()+3600*6,'/');
			} else if((!isset($_COOKIE['demoin']) || $_COOKIE['demoin']!=1) && (!isset($_COOKIE['voter2']) || $_COOKIE['voter2']!=='Transporter-Pokernews')){
		        include('soon.htm');
				die();
			}
		}
	}

	//DEMO SECURITY
}*/
moon_init('ini/moon.ini', 'engine');
$e = & moon :: engine();
$ini = & moon :: moon_ini();
$e->ini_set('home_url', $ini->get('site', 'home_url'));
$p = & moon :: page();
$p->home_url = $e->ini('home_url');
$all = moon_process();
echo $all;
if (strpos($all, '{!') !== FALSE) {
	moon :: error('{! bug');
}
moon_close();
?>