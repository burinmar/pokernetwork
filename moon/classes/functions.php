<?php
date_default_timezone_set('Europe/Vilnius');

function is_dev() {
	static $_isDev = NULL;
	if (NULL === $_isDev) {
		return $_isDev = ((isset ($_SERVER['HTTP_HOST']) && strpos($_SERVER['HTTP_HOST'], '.dev')) ? 1 : 0);
	}
	return $_isDev;
}


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

/**
 * Inserts task into cron queue
 * @param string $event
 */
function cronTask($event)
{
    $db=& moon :: db();
	$myTable = 'sys_cron_tasks';
	$m=$db->single_query_assoc('
		SELECT id,last_run,next_run,disabled FROM '.$myTable."
		WHERE event='".$db->escape($event)."'"
		);
	$start = $now = time();
	$id = 0;
    if (count($m)) {
    	$id = $m['id'];
		if ($m['next_run']==-10) {
			//taskas dabar vykdomas
			$msg = 'Queue: the running task was found!.. Overwritten...';
			//jei vyksta, duosim dar 10 min
			$start += 600;
		}
        elseif ($m['next_run'] > 0 && $m['next_run'] < $now) {
			//taskas jau uzstatytas
            $msg = 'Queue: the task already is queued...';
			$start=0;
		}
		else {
			$msg = 'Queue: task updated';
		}
		if ($start) {
        	$a=array('next_run'=>$start);
			$db->update_query($a, $myTable, $m['id']);
		}
	}
	else {
		//naujas taskas
        $ins = array();
		$ins['event'] = $event;
		$ins['comment'] = 'Queued task.';
		$ins['next_run'] = $start;
		$id=$db->insert_query($ins,$myTable,'id');
		$msg = 'Queue: task created';
	}
	$ins=array('task_id'=>$id,'start_time'=>$now,'end_time'=>$now,'message'=>$msg);
    $db->insert_query($ins,'sys_cron_log');
}

// diff functions
function diff($old, $new){
	$maxlen = 0;
	foreach($old as $oindex => $ovalue){
		$nkeys = array_keys($new, $ovalue);
		foreach($nkeys as $nindex){
			$matrix[$oindex][$nindex] = isset($matrix[$oindex - 1][$nindex - 1])
				? $matrix[$oindex - 1][$nindex - 1] + 1
				: 1;
			if($matrix[$oindex][$nindex] > $maxlen){
				$maxlen = $matrix[$oindex][$nindex];
				$omax = $oindex + 1 - $maxlen;
				$nmax = $nindex + 1 - $maxlen;
			}
		}
	}
	if($maxlen == 0) {
		return array(array('d'=>$old, 'i'=>$new));
	}
	return array_merge(
			diff(array_slice($old, 0, $omax), array_slice($new, 0, $nmax)),
			array_slice($new, $nmax, $maxlen),
			diff(array_slice($old, $omax + $maxlen), array_slice($new, $nmax + $maxlen)));
}


function htmlDiff($old, $new){
	$diff = diff(explode(' ', $old), explode(' ', $new));
	$ret = '';
	foreach($diff as $k){
		if(is_array($k)) {
			$ret .= (!empty($k['d'])?'<span style="background-color:#FFD8D3; text-decoration: line-through;">'.implode(' ',$k['d'])."</span> ":'').
					(!empty($k['i'])?'<span style="background-color:#CCFFCC;">'.implode(' ',$k['i'])."</span> ":'');
		}
		else {
			$ret .= $k . ' ';
		}
	}
	return $ret;
}


?>