<?php

class shared_tools {


	function shared_tools($path) {
		$moon = & moon :: engine();
		$this->tpl = & $moon->load_template(
			$path . 'shared_tools.htm',
			$moon->ini('dir.multilang') . 'shared.txt'
			);
		//$this->init();
	}


	function init() {
	}


	function toolbar($opt = FALSE) {
		if (isset ($_GET['print'])) {
			return '';
		}
		$page = & moon :: page();

		if (isset($_GET['go_twitter'])) {
			$thisUrl = $page->home_url() . ltrim($page->uri_segments(0),'/');
			if (empty($_GET['go_twitter'])) {
				$page->head_link($thisUrl, 'canonical');
			}
			else {
				$postUrl = ($shortUrl = short_url($thisUrl)) ? $shortUrl : $thisUrl;
				$postTitle = $page->title();
				if (strlen($postTitle . $postUrl) > 139) {
					$twitterMsg = substr($postTitle, 0, 140 - strlen($postUrl) - 4) . '... ' . $postUrl;
				} else {
					$twitterMsg = $postTitle . ' ' . $postUrl;
				}
				$twitterUrl = 'http://twitter.com/home?status=' . urlencode($twitterMsg);
				$page->redirect($twitterUrl, 301);
			}
		}

		
		//Sitaip idejus problemos su IE6
		//$page->js('http://s7.addthis.com/js/200/addthis_widget.js');
		//$page->js('http://w.sharethis.com/widget/?publisher=c95353dc-1195-45db-93ec-802f1dc91ab8&amp;type=website');
		if (!is_array($opt)) {
			$opt = array();
		}
		//$opt += array('email' => TRUE, 'print' => TRUE);

		$m = array();
		//email mygtukas
		if (!empty ($opt['email'])) {
			$m['email'] = 1;
		}
		//print mygtukas
		if (!empty ($opt['print'])) {
			if ($opt['print'] === TRUE) {
				$m['print'] = $page->uri_segments(0);
				$m['print'] .= strpos($m['print'], '?') ? '&print' : '?print';
				$m['print'] = htmlspecialchars($m['print']);
			}
		}
		//rss mygtukas
		if (!empty ($opt['rss'])) {
			$page->head_link($opt['rss'], 'rss','RSS xml feed');
			$m['rss'] = $opt['rss'];
		}
		//skirtukas ar reikalingas
		if (!empty ($m['rss']) || !empty ($m['print'])) {
			$m['separator'] = 1;
		}
		
		$thisUrl = $page->home_url() . ltrim($page->uri_segments(0),'/');
		$twitterMsg = $page->title() . ' ' . $thisUrl;
		$twitterUrl = 'http://twitter.com/home?status=' . urlencode($twitterMsg);
		if (strlen($twitterMsg) > 140) {
			$twitterUrl = $thisUrl . '?go_twitter';
		}
		$m['jsTitle'] = urlencode($page->title());
		$m['twitterURL'] = $twitterUrl;
		$m['jsURL'] = urlencode($thisUrl);

		$tplName = 'toolbar';
		if (!empty($opt['variant'])) {
			$tplName .= '_' . $opt['variant'];
		}
		
		return $this->tpl->parse($tplName, $m);
	}
	
	function facebookLike()
	{
		if (isset ($_GET['print'])) {
			return '';
		}
		$page = & moon :: page();
		$thisUrl = $page->home_url() . ltrim($page->uri_segments(0),'/');
		$m = array(
			'jsURL' => urlencode($thisUrl)
		);
		return $this->tpl->parse('facebook_like', $m);
	}
	
}

?>