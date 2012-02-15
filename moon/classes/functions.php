<?php
date_default_timezone_set('Australia/Melbourne');

function moon_reconfig() {
	$e = & moon :: engine();
	$ini = & moon :: moon_ini();
	$e->ini_set('home_url', $ini->get('site', 'home_url'));
	$p = & moon :: page();
	$p->home_url = $e->ini('home_url');
 	if (is_dev()) {
		//$e->ini_set('error.file', 'tmp/error.log');
		$e->ini_set('error.display', 1);
		//$e->ini_set('dir.cache',$e->ini('dir.cache'));
		moon::cache()->on(FALSE);
	}

}

function is_dev() {
	static $_isDev = NULL;
	if (NULL === $_isDev) {
		return $_isDev = ((isset ($_SERVER['HTTP_HOST']) && strpos($_SERVER['HTTP_HOST'], '.dev')) ? 1 : 0);
	}
	return $_isDev;
}
define('_W_DIR_', 'w/');

function url_logic($ev = false, $par = false) {
	$e = & moon :: engine();
	if ($ev === false) {
		$p = & moon :: page();
		// request
		$uri = (isset ($_SERVER['REQUEST_URI'])) ? urldecode($_SERVER['REQUEST_URI']) : '';
		list($uri) = explode('?', $uri);

		if (preg_match('~^/google\-~', $uri)) {
			$_GET['url_'] = '/sys-google/';
		}

		if (!empty($_GET['url_'])) {
			$uri = $_GET['url_'];
		}

		switch ($uri) {

			case '/captcha.php':
				return array('sys.captcha#','');
			case '/homepage-rooms/js.php':
				return array('reviews.homepage_rooms#js','');

			case '/sys-google/':
				return array('sys.google#','');
		}

		$db = & moon :: db();

		//sitemap is sitemap lentos
		$m = $db->array_query_assoc("
			SELECT id,page_id,uri,control,
				IF(content_html<>'' OR control<>'' OR xml<>'',0,1) as empty
			FROM sitemap
			WHERE is_deleted = 0
			ORDER BY LENGTH(uri) DESC, sort
		");
		$found = FALSE;
		$id2uri = array();
		$evMap = array();
		foreach ($m as $d) {
			$id2uri[$d['page_id']] = $d['uri'];
			if ($d['empty']) {
				//continue;
			}
			if ($k = $d['control']) {
				$in = substr($k, - 1) === '*' ? - 2 : - 1;
				if (substr($k, $in, 1) === '#') {
					$k = substr_replace($k, '', $in, 1);
				}
				//$v = ltrim($d['uri'],'/');
				$v = $d['uri'];
				$evMap[$k] = ltrim($v, '/');
				if ($uri . '/' === $v && $v !== '/') {
					// truksta slasho
					$p->redirect($v);
				}
				if (strpos($uri, $v) === 0) {
					$ev = $k;
					$par = trim(substr($uri, strlen($v)));
					if (substr($v, - 1) !== '/' && $par !== '' && $par[0] !== '/') {
						continue;
					}
					$par = ltrim($par, '/');
					if (substr($ev, - 1) === '*') {
						$ev = trim($ev, '*');
						if ($k = strpos($par, '/')) {
							$ev .= '#' . substr($par, 0, $k);
							//$par=substr($par,$k+1);
						}
					}
					elseif ($par !== '') {
						continue;
					}
					if ($found === FALSE) {
						$d['control'] = $ev;
						$d['par'] = $par;
						$d['mask'] = substr($uri, 0, - strlen($par));
						$found = $d;
					}
				}
			}
			elseif ($found === FALSE) {
				if ($uri == $d['uri']) {
					$d['par'] = $d['id'];
					$d['control'] = 'sys.page#custom-page';
					$d['mask'] = $d['uri'];
					$found = $d;
				}
			}
		}
		//replace original urlmap
		$engine = & moon :: engine();
		$mapOrig = $engine->eventsMap;
		foreach ($mapOrig as $k => $v) {
			if (strpos($v, '{page:') !== FALSE) {
				if (preg_match('/\{page:([^\}]+)\}?/', $v, $a) && isset ($id2uri[$a[1]])) {
					$mapOrig[$k] = ltrim(str_replace('{page:' . $a[1] . '}', $id2uri[$a[1]], $v), '/');
				}
			}
		}
		$mapOrig += $evMap;
		$engine->eventsMap = $mapOrig;
		if ($found !== FALSE) {

			$GLOBALS['review.roomID'] = 0;
			$p->set_request_mask($found['mask'], $found['par']);
			$p->set_local('customPageId', $found['id']);
			$p->set_local('pageID', $found['page_id']);
			$sitemap = & moon :: shared('sitemap');
			$sitemap->id($found['id']);
			$pg = $sitemap->getPage();
			$p->title($pg['meta_title'] ? $pg['meta_title'] : $pg['title']);
			$p->meta('keywords', $pg['meta_keywords']);
			$p->meta('description', $pg['meta_description']);
			if ($pg['css'] != '') {
				$a = explode(';', $pg['css']);
				foreach ($a as $v) {
					if ($v = trim($v)) {
						$p->css($v);
					}
				}
			}
			//$engine = & moon :: engine();
			//$engine->disable_event();
			//print_r($found);
			return array($found['control'], $found['par']);
		}
		else {
			$sql = array();

			//redirect
			$eUri = $db->escape($uri);

			//reviewso aptikimas
			$d = explode('/', $uri);
			$isReview = false;
			//gal tai roomsas
			if (count($d) >= 1 && !in_array($d[1], array('', 'djs', 'live-reporting', 'register', 'banners', 'video'))) {
				//tikrinama, gal nuoroda yra reviews puslapis
				if (substr($uri, - 4) == '.htm') {
					$sql[] = "(SELECT 2 as w,room_id,id FROM rw2_pages WHERE uri='" . $db->escape(substr($uri, 1, - 4)) . "' AND hide=0 AND is_link=0 LIMIT 1)";
				}
				//tikrinama, gal nuoroda yra reviewsas
				$sql[] = "(SELECT 3 as w,id,0 FROM rw2_rooms WHERE alias='" . $db->escape($d[1]) . "' ORDER BY is_hidden LIMIT 1)";
			}

			$sql = implode(' UNION ALL ', $sql) . (count($sql) > 1 ? ' ORDER BY w LIMIT 1' : '');
			$is = $sql ? $db->single_query($sql) : FALSE;
			if (isset ($is[0])) {
				if ($is[0] == 1) {
					if ($is[1] !== '') {
						$p->redirect($is[1], 301);
					}
					else {
						$p->page404();
					}
				}
				else {
					$GLOBALS['review.roomID'] = $is[1];
					if ($is[2]) {
						$GLOBALS['review.roomPageID'] = $is[2];
					}
				}
			}
		}

		$uri = (isset ($_SERVER['REQUEST_URI'])) ? urldecode($_SERVER['REQUEST_URI']) : '';
		$r = $e->url_parse('/' . $uri);
		return $r;
	}
	// construct URL
	else {
		$url = $e->url_construct($ev, $par);
		return $url;
	}
}


function blame($component, $action, $ids) {
	if (is_array($ids)) {
		$ids = implode(',', $ids);
	}
	if (strlen($ids) > 20) {
		$ending = '...';
		$ids = substr($ids, 0, 17);
		$ids = (strpos($ids, ',')) ? substr($ids, 0, - (strlen(strrchr($ids, ',')))) : $ids;
		$ids = $ids . $ending;
	}
	$db = & moon :: db();
	$user = & moon :: user();
	$ins = array();
	$ins['user_id'] = $user->get_user_id();
	$ins['time'] = time();
	$ins['component'] = $component;
	$ins['action'] = $action;
	$ins['item_id'] = (string) $ids;
	$db->insert($ins, 'sys_blame');
	return TRUE;
}

//perduoda informacija kazkuriam pokernews saitui
function callPnEvent($siteID,$event,$data,&$answer,$usePassword=TRUE)
{
	$isLocal=is_dev();
	$domain = 'pokernews.' . ($isLocal ? (strpos($_SERVER['HTTP_HOST'], '.dev-1') ? 'dev-1.ntsg.lt':'dev') : 'com') . '/';
	$password = 'vemail';
	switch ($siteID) {
		case 'adm':
			$url = 'http://adm.' . $domain . 'adm/';
			$password = 'pokernews.db';
			break;
		default:
			switch ($siteID) {
				case 'com': $url = 'www.';	break;
				default: $url = $siteID . '.';
			}
			$url = 'http://' . $url . $domain . '';
	}
	include_class('transporter');
	$t = new transporter();
	if ($usePassword) $t->set_key($password);
	$t->set_timeout(5);
	$t->set_curl_option(CURLOPT_COOKIE,'voter2=Transporter-Pokernews');
	$t->add_event($event, $data);
	$t->send($url);
	if(!$t->was_error()) {
		$answer = $t->get_event_answer();
		//print_r($t->trans_response());
		return true;
	} else {
		$answer=$t->errno().': '.$t->errtext();
		moon::error('callPnEvent() ' . $siteID . ':' . $event . ' error: ' . $answer);
		return false;
	}
}

function img($dir, $name, $arg3=null) {
	$s = FALSE;
	$com = is_dev() ? 'com' : 'com';
	if (!empty($arg3)) {
		$name = $name . '-' . $arg3;
	}
	switch ($dir) {
		case 'player':
		case 'avatar':
		case 'rw' :
		case 'rwc' :
		case 'rw-gallery' :
		case 'rwc-gallery' :
		case 'game' :
		case 'gamec' :
		case 'deposit' :

			$s = 'http://i.pokernews.' . $com . '/' . $dir . '/' . $name;
		break;
		default:
	}
	return $s;
}

function geo_my_country() {
	static $id;//$id='us';
	if (is_null($id)) {
		$page = & moon :: page();
		if ($id = $page->get_global('geo.id')) {
			if ($page->history_step() % 10 == 0) {
				$id = '';
			}
		}
		if ($id === '') {
			$user = & moon :: user();
			$ip = $user->get_ip();
			include_once (MOON_CLASSES . 'geoip/geoip.inc');
			$gi = geoip_open(MOON_CLASSES . 'geoip/GeoIP.dat', GEOIP_STANDARD);
			//$id=strtolower(geoip_country_code_by_addr($gi, "217.28.251.173"));
			$id = strtolower(geoip_country_code_by_addr($gi, $ip));
			geoip_close($gi);
			$page->set_global('geo.id', $id);
		}
	}
	return $id;
}

function geo_my_id(){
	$code = geo_my_country();
	switch ($code) {
		case 'us':
			return 1;
		case 'gb':
			return 2;
		case 'au':
		case 'tv':
		case 'sb':
		case 'pf':
		case 'cc':
		case 'wf':
		case 'nu':
		case 'nr':
		case 'fj':
		case 'to':
		case 'pn':
		case 'nz':
		case 'cx':
		case 'vu':
		case 'tk':
		case 'ki':
		case 'ws':
		case 'pg':
		case 'nc':
			return 3;
		case 'ca':
			return 4;
		default:
			return 0;
	}
}


function geo_zones()
{
	$z = array(
		'us' => 1, 'gb' => 2, 'aa' => 3, 'ca' => 4
	);
	return $z;
}

?>