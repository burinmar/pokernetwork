<?php

class output extends moon_com {

	function main($vars) {
		if (isset ($vars['layout']) && isset ($vars['parts'])) {
			$t = & $this->load_template('_layouts');
			//jeigu veikiam kaip layout komponentas
			if ($vars['layout'] !== '' && $t->has_part($vars['layout'])) {
				if (isset($vars['parts']['header'])) {
					$vars['parts']['header'] = $vars['parts']['header'] . $this->breadcrumb();
				}
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
		}*/
		if ($output === 'modal') {
			$p->css('/css/modal.css');
		}
		
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
			'main.css-timestamp' => filemtime('css/style.css')
		);
		$p->head_link('/favicon.ico', 'favicon');

		if (isset($p->fbMeta)) {
			foreach ($p->fbMeta as $k => $v) {
				$m['head.tags'] .= '<meta property="' . $k . '" content="' . $v . '"/>' . "\n";
			}
		}
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
				if (strpos($v, '<style') !== false) {
					$m['head.tags'] .= $v . "\n";
					continue;
				}
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
		$footScripts .= '<script type="text/javascript" src="https://platform.twitter.com/widgets.js"></script>';
		//Justino prasymu
		$footScripts .= '<img src="https://pixel.mathtag.com/event/img?mt_id=134403&mt_adid=101145&v1=&v2=&v3=&s1=&s2=&s3=" width="1" height="1" />';
		//PN-3748
		if (in_array(geo_my_country(),array('au','nz')) && moon::locale()->now()<1373846400) {
			$m['head.tags'] .= "\t" . '<script type="text/javascript" src="/js/popunder.js?' . $this->getFileModTime('/js/popunder.js') . '"></script>' . "\n";
			$footScripts .='<script type="text/javascript">
				jQuery(document).ready(function(){
					if (!readCookie("b130701eztrader")) {
						createCookie("b130701eztrader","1",1);
						var url="http://www.pokernews.com/eztutorial-pnet.htm";
						jQuery(document).ready(function(e){
							if (url!="") {
								makePopunder(url);
								url="";
							}
						});
					}
				});
				</script>';
		}
		//
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

		// banners
		if (is_object($obj = $this->object('sys.banners')) && !$p->get_local('nobanners')) {
			$bannerRoomId = $p->get_local('banner.roomID');
			$m += array(
				'bannersOn' => 1,
				'bnRoomId' => $bannerRoomId,
				'bnGeoTarget' => 1<<geo_my_id(),
				'bnDataUrl' => $p->home_url() . 'banners/getdata/',
				'bnStatsUrl' => $p->home_url() . 'banners/trackviews/',
				'uriList' => $obj->getUriList()
			);
		}

		$res = $t->parse($output, $m);
		return $res;
	}

	function getFileModTime($file) {
		if (strpos($file, 'http://') !== FALSE || substr($file,-4) == '.php') {
			return '';
		}
		return @ filemtime(ltrim($file, '/'));
	}

	function breadcrumb() {
		$tpl = & $this->load_template();
		$m = array('breadcrumb'=>'');
		$bCrumb = moon::shared('sitemap')->breadcrumb();
		$last = count($bCrumb)-1;
		if ($last<0) {
			return '';
		}
		foreach ($bCrumb as $k=>$d) {
			$d['class-current'] = $k == $last ? ' class="current"' : '';
			$d['title'] = htmlspecialchars($d['title']);
			$m['breadcrumb'] .= $tpl->parse('breadcrumb',$d);
		}
		return $tpl->parse('html:breadcrumb', $m);
	}


}

?>