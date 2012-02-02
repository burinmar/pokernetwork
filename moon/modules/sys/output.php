<?php

class output extends moon_com {

/* Startup */
	function events($event, $par) {

		switch ($event) {

			case 'error404' :
				//header("HTTP/1.0 404 Not Found", TRUE, 404);
				$this->forget();
				$this->use_page('page404');
				return;
				break;

			default :
			   	$this->use_page('Default');
		}

		if (is_dev()) {
			$engine = & moon :: engine();
			$engine->debugOn = TRUE;
		}
		$page=&moon::page();
		//$page->css('/css/main.css');
		$page->js('/js/jquery.js');

	}


	function main($vars) {
		if (isset ($vars['layout']) && isset ($vars['parts'])) {
			$t = & $this->load_template('_layouts');
			//jeigu veikiam kaip layout komponentas
			if ($vars['layout'] !== '' && $t->has_part($vars['layout'])) {
				$body = $t->parse($vars['layout'], $vars['parts']);
			}
			else {
				$body = implode('', $vars['parts']);
			}
		}
		else {
			$body = $vars['content'];
		}

		$t = & $this->load_template();
		$p = & moon :: page();

		$m = array();
		$m['{!action}'] = $p->uri_segments(0);
		$body = str_replace(array_keys($m), array_values($m), $body);

		$output = $p->get_local('output');
		/*if ($output === 'print') {
			$p->meta('robots', 'noindex,nofollow');
			$p->js('/js/print.js');
			$p->css('/css/print.css');
			$url = $_SERVER['REQUEST_URI'];
			if (isset ($_GET['print'])) {
				$url = rtrim(str_replace('?print', '?', $url), '?');
			}
			$body = $t->parse('print', array('content' => $body, 'url' => $p->home_url() . ltrim($url, '/')));
			$output = 'page';
		}
		elseif ($output === 'modal') {
			$p->css('/css/modal.css');
		}
		*/
		$loc = & moon :: locale();
		//title
		$title = $p->title();
		$sub = '| PokerNetwork.com';
		if (strpos($title, $sub) === false) {
			$title .= ' ' . $sub;
		}
		$m = array(
			'home_url' => $p->home_url(),
			'lang' => $loc->language(),
			'title' => htmlspecialchars($title),
			'head.tags' => $p->get_local('head.tags'),
			'main.css-timestamp' => filemtime('i/style.css')
		);
		$p->head_link('/favicon.ico', 'favicon');

		// Meta information
		$meta = $p->meta();
		if (is_array($meta)) {
			foreach ($meta as $k => $v)
				if ($v)
					$m['head.tags'] .= "\t" . '<meta name="' . $k . '" content="' . htmlspecialchars($v) . '" />' . "\n";
		}
		// CSS files
		$css = $p->css();
		if (is_array($css)) {
			$garbage = '';
			foreach ($css as $v) {
				if (strpos($v, "?"))
					list($v, $garbage) = explode("?", $v);
				$mt = $this->getFileModTime($v, $garbage);
				if ($mt)
					$v = $v . "?" . $mt;
				$m['head.tags'] .= "\t" . '<link rel="stylesheet" href="' . $v . '" type="text/css" />' . "\n";
			}
		}
		// JS files
		$footScripts = '';
		$replace = array();
		$replace['/js/jquery.js'] = 'http://ajax.googleapis.com/ajax/libs/jquery/1.5.2/jquery.min.js';
		$replace['/js/swfobject.js'] = 'http://ajax.googleapis.com/ajax/libs/swfobject/2.2/swfobject.js';
		$js = $p->js();
		if (is_array($js)) {
			foreach ($js as $v) {
				if (isset ($replace[$v])) {
					$v = $replace[$v];
				}
				if (strpos($v, "http://") !== 0) {
					if (strpos($v, "?")) {
						list($vf) = explode("?", $v);
						$v .= '&amp;' . $this->getFileModTime($vf);
					}
					else {
						$v .= rtrim('?' . $this->getFileModTime($v), '?');
					}
				}
				if (strpos($v, 'brightcove')) {
					$footScripts .= "\t" . '<script type="text/javascript" src="' . $v . '"></script>' . "\n";
				}
				else {
					$m['head.tags'] .= "\t" . '<script type="text/javascript" src="' . $v . '"></script>' . "\n";
				}
			}
		}
		$hl = $p->head_link();
		foreach ($hl as $k => $v) {
			$m['head.tags'] .= "\t" . $v;
		}

		if (!headers_sent() && ($lastModified = $p->last_modified())) {
			header('Last-Modified: ' . gmdate('r', $lastModified));
		}


		/**/
		if (is_dev()) {
			$engine = & moon :: engine();
			$engine->debugOn = FALSE;
		}
		else {
			$ini = & moon :: moon_ini();
			$m['googleID'] = $ini->get('other', 'googleStatsID');
		}
		$head = $t->parse('common_header', $m);
		if ($output == '' || !$t->has_part($output)) {
			$output = 'page';
		}
		$m = array('localeID' => $loc->current_locale(), 'common_header' => $head, 'foot.scripts' => $footScripts, 'content' => $body);

		$res = $t->parse($output, $m);
		return $res;
	}


	function getFileModTime($file) {
		if (strpos($file, 'http://') !== FALSE || substr($file,-4) == '.php') {
			return '';
		}
		return @ filemtime(ltrim($file, '/'));
	}


}

?>