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
		$page = moon :: page();
		$postTitle = '';
		if (isset($_GET['go_twitter'])) {
			$thisUrl = $page->home_url() . ltrim($page->uri_segments(0),'/');
			if (empty($_GET['go_twitter'])) {
				$page->head_link($thisUrl, 'canonical');
			} else {
				$postUrl = ($shortUrl = short_url($thisUrl)) ? $shortUrl : $thisUrl;
				$twitterMsg = $page->title();
				if (isset($opt['title'])) {
					$twitterMsg = $opt['title'];
				}
				if (strlen($twitterMsg) > 139) {
					$twitterMsg = substr($twitterMsg, 0, 140);
				}
				$twitterUrl = 'http://twitter.com/intent/tweet?text=' . urlencode($twitterMsg).'&original_referer='.urlencode(short_url($postUrl)).'&url='.urlencode(short_url($postUrl)).'&source=tweetbutton';

				if (!empty($opt['twitterTags'])) $twitterUrl .= '&hashtags='.urlencode($opt['twitterTags']);
				if (_SITE_ID_ == 'com') $twitterUrl .= '&via=PokerNews';

				/* url example
				http://twitter.com/intent/tweet?
				original_referer=http%3A%2F%2Fwww.guardian.co.uk%2Fculture%2F2011%2Fjun%2F08%2Fshakespeare-real-ophelia-link
				&related=GuardianCulture
				&source=tweetbutton
				&text=The+real+Ophelia%3F+1569+coroner%27s+report+suggests+Shakespeare+link
				&url=http%3A%2F%2Fgu.com%2Fp%2F2pj3v%2Ftw
				*/
				$page->redirect($twitterUrl, 301);
			}
		}

		if (!is_array($opt)) {
			$opt = array();
		}

		$m = array();
		if (!empty ($opt['rss'])) { //rss mygtukas
			$page->head_link($opt['rss'], 'rss','RSS xml feed');
		}
		if (empty ($opt['notitle'])) { //ar rodyt 'Share this' antraste
			$m['showTitle'] = 1;
		}

		$thisUrl = $page->home_url() . ltrim($page->uri_segments(0),'/');
		if (isset($opt['url'])) {
			$thisUrl = $page->home_url() . ltrim($opt['url'],'/');
		}

		$twitterUrl = $thisUrl.'?go_twitter';

		$tplSuffix = !empty($opt['variant'])
			? '_' . $opt['variant']
			: '';

		$socialArgv = array(
			'twitterURL' => $twitterUrl,
			'jsURL' => urlencode($thisUrl),
			'lang' => moon::locale()->language()
		);
		$socialArgv = array(
			'facebook' => $this->tpl->parse('social' . $tplSuffix . ':facebook', array(
				'jsURL' => $socialArgv['jsURL']
			)),
			'twitter' => $this->tpl->parse('social' . $tplSuffix . ':twitter', array(
				'twitterURL' => $socialArgv['twitterURL']
			)),
			'gplus' => $this->tpl->parse('social' . $tplSuffix . ':gplus', array(
				'lang' => $socialArgv['lang'],
				'firstuse' => isset($opt['firstuse'])
					? $opt['firstuse']
					: true
			)),
			'vkontakte' => $this->tpl->parse('social' . $tplSuffix . ':vkontakte', array(
				'jsURL' => $socialArgv['jsURL']
			)),
			'svejo' => $this->tpl->parse('social' . $tplSuffix . ':svejo', array(
				'jsURL' => $socialArgv['jsURL']
			))
		);
		$nrButtons = isset($opt['nrbuttons'])
			? $opt['nrbuttons']
			: 3;
		for ($i = 1; $i <= $nrButtons; $i++) {
			$tplName = $this->tpl->has_part('toolbar' . $tplSuffix . ':socialNetwork' . $i . ':' . _SITE_ID_)
				? 'toolbar' . $tplSuffix . ':socialNetwork' . $i . ':' . _SITE_ID_
				: 'toolbar' . $tplSuffix . ':socialNetwork' . $i;
			$m['socialNetwork' . $i] = $this->tpl->parse($tplName, $socialArgv);
		}
		
		if (empty($opt['variant'])) {
			if (!empty($opt['socialTracker'])) {
				$m['b'] = $this->tpl->parse('socialTracker');
			} else {
				$m['b'] = '';
			}
		}

		return $this->tpl->parse('toolbar' . $tplSuffix, $m);
	}

	function facebookLike() {
		if (isset ($_GET['print'])) {
			return '';
		}
		$page = & moon :: page();
		$thisUrl = $page->home_url() . ltrim($page->uri_segments(0),'/');
		return $this->tpl->parse('facebook_like', array(
			'jsURL' => urlencode($thisUrl)
		));
	}

	function socialTrackerWxfbml() {
		$p = &moon::page();
		$p->set_local('html.class', ' xmlns:fb="http://www.facebook.com/2008/fbml"');
		$locale = moon::locale();
		return $this->tpl->parse('xfbml', array('fl' => $locale->get('fb')));
	}
	
	function fbLike($w255 = FALSE) {
		return $this->tpl->parse('fblike', array('p' => TRUE === $w255 ? ' width="255"' : ''));
	}

	function fbLikeWst() {
		return $this->tpl->parse('fblike', array('p' => '')) . $this->socialTrackerWxfbml();
	}
}
?>