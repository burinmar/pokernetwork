<?php
class header extends moon_com 
{
	function main($argv) 
	{
		$navi   = moon :: shared('sitemap');
		$page   = moon::page();
		$tpl    = $this->load_template();
		$tplArgv= array(
			'menu'  => '',
			'user_block' => $this->partialRenderUserBlock($tpl),
			'breadcrumb' => '',
		);

		$bCrumb   = $navi->breadcrumb();
		$activeMainMenu = isset ($bCrumb[0]['id'])
			? $bCrumb[0]['id'] 
			: 0;
		$tplArgv['isHome'] = 'home' == $navi->on();

		$mainMenu = $this->getMenuTree($navi->items);
		$page->set_local('sys.footer:menu', $mainMenu);

		foreach ($mainMenu as $item) {
			$tplArgv['menu'] .= $this->partialRenderMenuItem($item, $tpl, $activeMainMenu);
		}
		$tplArgv['url.search'] = $navi->getLink('search');

		$res = $tpl->parse('main', $tplArgv);

		$isHomepage = 'home' == $navi->on();
		if (!$isHomepage) {
			return $res;
		}
		

		/*skrill: june 7-8*/
		$bgURL = 'http://www.moneybookers.com/ads/score-with-skrill/?rid=6930492&promo_id=16258191';
		$res = '<script type="text/javascript">var bgURL = "'.$bgURL.'";</script>' . $res;
		$page->css('<style type="text/css">/*<![CDATA[*/ html { background: #730b46 url(\'/img/skrill_score_wallpaper.jpg\') no-repeat fixed top center; cursor: pointer;} /*]]>*/</style>');
		return $res;

		$bgURL = 'http://www.pokernetwork.com/leagues/10-500-poker770-weekend-wonders/';
		$res = '<script type="text/javascript">var bgURL = "'.$bgURL.'";</script>' . $res;
		$page->css('<style type="text/css">/*<![CDATA[*/ html { background: #000000 url(\'/img/p770_wonders_wallpaper.jpg\') no-repeat fixed top center; cursor: pointer;} /*]]>*/</style>');
		return $res;
		/* Toliau baneris */
		$cookieVar = 'b0521';
		if (!isset($_COOKIE[$cookieVar]) || $_COOKIE[$cookieVar] !='h') {
			if(isset($_GET['bps']) && $_GET['bps'] == 'hide') {
				setcookie($cookieVar, 'h', time()+86400);
			}
			$res = '<script type="text/javascript">function closeFlashLayer(){var d=new Date();d.setTime(Date.UTC(d.getFullYear(),d.getMonth(),d.getDate(),23,59,59,999)+d.getTimezoneOffset()*60000+1);document.cookie=\''.$cookieVar.'=h; expires=\'+d.toGMTString()+\'; path=/\';document.getElementById(\'bn0902\').style.display=\'none\'}</script><div style="position: fixed; top: 0; right: 50%; margin-right: -508px; width: 1012px; height: 611px; z-index: 9999;" id="bn0902" onclick="closeFlashLayer()"><object classid="clsid:d27cdb6e-ae6d-11cf-96b8-444553540000" codebase="http://download.macromedia.com/pub/shockwave/cabs/flash/swflash.cab#version=6,0,40,0" width="1012" height="611" id="mymoviename""><param name="movie" value="/img/p770_wonders_layer_banner.swf?clickTAG='.$bgURL.'" /><param name="quality" value="high" /><param name="allowscriptaccess" value="always"><param name="quality" value="high" /><param name="wmode" value="transparent" /><param name="bgcolor" value="transparent" /><embed src="/img/p770_wonders_layer_banner.swf?clickTAG='.$bgURL.'" wmode="transparent" quality="high" bgcolor="transparent" width="1012" height="611" name="mymoviename" allowscriptaccess="always" align="" type="application/x-shockwave-flash" pluginspage="http://www.macromedia.com/go/getflashplayer"></embed></object></div>' . $res;
		}
		


		$bgURL = '/leagues/30k-winner-wednesday-dozen/';
		$res = '<script type="text/javascript">var bgURL = "'.$bgURL.'";</script>' . $res;
		$page->css('<style type="text/css">/*<![CDATA[*/ html { background: #1e0000 url(\'/img/winner_30k_wallpaper.jpg\') no-repeat fixed top center; cursor: pointer;} /*]]>*/</style>');

		return $res;
		
		$bgURL = '/leagues/pkr-daily-dollar-wsop-rake-chase/';
		$res = '<script type="text/javascript">var bgURL = "'.$bgURL.'";</script>' . $res;
		$page->css('<style type="text/css">/*<![CDATA[*/ html { background: #000 url(\'/img/pkr_wsop_wallpaper.jpg\') no-repeat fixed top center; cursor: pointer;} /*]]>*/</style>');


		/************* SLAMSTAS *************/

		// PokerStars SCOOP 2012
		//banner
		$psb =$tpl->parse('pokerstars', array('siteID'=>'EN'));
		$tb = '<div style="background: #000; margin: 0 auto; text-align: center; width: 1000px;">' .$psb. '</div>';
		//moon::page()->set_local('topbanner', $tb);
		//background
		$bgURL = 'http://pokerstars.com/EN/ad/11216421/1000x150zoomtakeover.gif.click?rq=noscript&vs=';
		$res = '<script type="text/javascript">var bgURL = "'.$bgURL.'";</script>' . $tb . $res;
		$page->css('<style type="text/css">/*<!CDATA*/ body {background: #000 url(\'/img/zoom_wallpaper_2000x1200.jpg\') no-repeat fixed top} html {background: #000; cursor: pointer} #bodyBlock, #footerBlock {cursor: default} /*>*/</style>');
		return $res;

		//wallpaper
		//banner
		$psb =$tpl->parse('pokerstars', array('siteID'=>'EN'));
		$tb = '<div style="background: #000; margin: 0 auto; text-align: center; width: 1000px;">' .$psb. '</div>';
		//moon::page()->set_local('topbanner', $tb);
		//background
		$bgURL = 'http://pokerstars.com/EN/ad/11216421/1000x150scoop.gif.click?rq=noscript&vs=';
		$res = '<script type="text/javascript">var bgURL = "'.$bgURL.'";</script>' . $tb . $res;
		$page->css('<style type="text/css">/*<!CDATA*/ body {background: #000 url(\'/img/ps_scoop2012_wallpaper.jpg\') no-repeat fixed top} html {background: #000; cursor: pointer} #bodyBlock, #footerBlock {cursor: default} /*>*/</style>');


	}

	private function partialRenderUserBlock($tpl)
	{
		$user = moon :: user();
		($uID = $user->get_user_id()) || ($uID = intval($user->get_user('tmpID')));
		// Userio (login/logout) blokas
		if ($uID != 0) {
			$tplArgv = array(
				'id' => $uID,
				'nick' => $user->get_user('nick'),
				'profile_url' => $this->linkas('users.profile'),
				'sign_out' => '/logout',
			);
			return $tpl->parse('user_block.user', $tplArgv);
		} else {
			$tplArgv = array();
			$tplArgv['url.forgot']   = '/forums/login.php?do=lostpw';
			$tplArgv['url.register'] = '/forums/register.php';
			$tplArgv['url.login']    = '/';
			$tplArgv['eventLogin'] = 'users.signup#login';
			/*if (moon::page()->get_local('header.hidesignIn'))
				return '';*/
			moon::page()->js('/js/jquery/placeholder.min.js');
			return $tpl->parse('user_block.nouser', $tplArgv);
		}
	}

	private function partialRenderMenuItem($item, $tpl, $activeMainMenu)
	{
		$class = '';
		if ($item['class']) {
			$class = $item['class'];
		}
		if ($activeMainMenu && $activeMainMenu == $item['id']) {
			$class = ltrim($class . ' on');
		}
		$tplArgv['url'] = $item['url'];
		$tplArgv['title'] = htmlspecialchars($item['title']);
		$tplArgv['td:class'] = $class ? ' class="' . $class . '"' : '';
		$tplArgv['submenu'] = '';
		if (0 != count($item['children'])) {
			foreach ($item['children'] as $child) {
				$tplArgv['submenu'] .= $this->partialRenderMenuItem($child, $tpl, $activeMainMenu);
			}
			$tplArgv['submenu'] = $tpl->parse('submenu', array(
				'menu' => $tplArgv['submenu']
			));
		}
		return $tpl->parse('menu.item', $tplArgv);
	}

	// max 2 levels
	private function getMenuTree($items)
	{
		uasort($items, array($this, 'childrenLast'));
		$items2D = array();
		foreach ($items as $itemNode) {
			if ($this->isMenuItemHidden($itemNode)) {
				continue;
			}
			if (!isset ($itemNode['class'])) {
				$itemNode['class'] = '';
			}
			$uri = array($itemNode['id']);
			if (0 != $itemNode['parent']) {
				$parentId = $itemNode['parent'];
				if (!isset($items[$parentId]) || $items[$parentId]['parent'] != 0 || $this->isMenuItemHidden($items[$parentId])) {
					continue;
				}
				array_unshift($uri, $parentId);
			}
			$root = &$items2D;
			foreach ($uri as $uriNode) {
				if (!isset ($root[$uriNode])) {
					$root[$uriNode] = array(
						'children' => array(),
					);
				}
				if ($uriNode == $uri[count($uri) - 1]) {
					$root[$uriNode] += $itemNode;
				} else {
					$root = &$root[$uriNode]['children'];
				}
			}
		}

		// usort($items2D, array($this, 'sortEntriesTree'));
		return $items2D;
	}

	private function childrenLast($a, $b)
	{
		if ($a['parent'] == $b['parent']) {
			if ($a['sort'] == $b['sort']) {
				return strcmp($a['title'], $b['title']);
			}
			return $a['sort'] > $b['sort'];
		}
		return $a['parent'] > $b['parent'];
	}

	private function isMenuItemHidden($item) 
	{
		return !empty ($item['hide']) || !isset ($item['url']);
	}
}