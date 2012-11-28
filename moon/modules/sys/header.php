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

		// *** WALLPAPERS ***
		//$isHomepage = 'home' == $navi->on();
		//if (!$isHomepage) {
		//	return $res;
		//}

// PS PCA FlashBack
if ($this->wallpaper($res, array(
'url'=> '/leagues/6-000-pokerstars-pca-flashback/',
'imgPath'=> '/img/wallpaper/ps_flashback_wallpaper.jpg',
'bgColor'=> '#000',
'endDate'=> '2012-12-25 23:59:59',
'showIn'=> 'home, /pokerstars/'
)
)) return $res;

// Bet365 Vaults
if ($this->wallpaper($res, array(
'url'=> '/leagues/10-000-bet365-open-vaults-freerolls/',
'imgPath'=> '/img/wallpaper/bet365_openvaults_wallpaper.jpg',
'bgColor'=> '#000',
'endDate'=> '2013-01-06 23:59:59',
'showIn'=> '/bet365-poker/'
)
)) return $res;

//poker770
if ($this->wallpaper($res, array(
'url'=> 'http://poker770.pokernews.com/',
'imgPath'=> '/img/wallpaper/poker70_free50_wallpaper.jpg',
'bgColor'=> '#000',
'showIn'=> '/poker770/'
)
)) return $res;

//  $67,500 PokerStars PokerNews Freeroll Series 
//if ($this->wallpaper($res, array(
//'url'=> '/pokerstars/freerolls/?upcoming',
//'imgPath'=> '/img/wallpaper/ps_frseries_wallpaper.jpg',
//'bgColor'=> '#000',
//'endDate'=> '2012-12-06 23:59:59',
//)
//)) return $res;

		/************* SLAMSTAS *************/
		/*
		// PokerStars SCOOP 2012
		//banner
		$psb =$tpl->parse('pokerstars', array('siteID'=>'EN'));
		$tb = '<div style="background: #000; margin: 0 auto; text-align: center; width: 1000px;">' .$psb. '</div>';
		//background


		//wallpaper
		//banner
		$psb =$tpl->parse('pokerstars', array('siteID'=>'EN'));
		$tb = '<div style="background: #000; margin: 0 auto; text-align: center; width: 1000px;">' .$psb. '</div>';
		//moon::page()->set_local('topbanner', $tb);
        //background
		$bgURL = 'http://pokerstars.com/EN/ad/11216421/1000x150scoop.gif.click?rq=noscript&vs=';
		*/
	return $res;

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

    /*
if ($this->wallpaper($res, array(
'roomID'=> 53,
//paprastas urlas. Jei prasideda +, tai kartu su roomID naudojamas ( '+ext/')
// +freerolls/1365.htm?master
// +freerolls/?upcoming
// +download/
'url'=> 'http://www.site.com/xx.htm',
'imgPath'=> '/i/xx.jpg',
'bgColor'=> '#000',
'geoTarget'=> 'us, in, bg',
'startDate'=> '2012-06-08 23:59:59',
'endDate'=> '2012-06-10 23:59:59',
'showIn'=> 'home, rules, reporting',
//galima gal netgi apsiraðyti  flash layer banner?
'layerPath'=> '/i/xxx.swf',
'layerSize'=> '1024x100',
'layerDivCSS'=> 'position: absolute, top: 0',
)
)) return $res;
*/


function wallpaper(&$res, $w) {
	//
	if (!empty($w['startDate'])) {
		$from = strtotime($w['startDate']);
		if ($from === FALSE || $from > moon::locale()->now()) {
			$delta = $from - moon::locale()->now();
			$res .= '<!-- delta '.(floor($delta/3600) .':'.floor(($delta % 3600)/60)).':'.floor($delta % 60).' -->';
			return FALSE;
		}
	}
	if (!empty($w['endDate'])) {
		$to = strtotime($w['endDate']);
		if ($to === FALSE || $to < moon::locale()->now()) {
			return FALSE;
		}
	}

	//
	if (empty($w['geoTarget'])) {
		$w['geoTarget'] = '-us,gb,in,gi';
	}
	$minus = $w['geoTarget']{0} === '-';
	if ($minus && $w['geoTarget'] == '-') {
		$w['geoTarget'] .= '*';
	}
	$sites = explode(',', str_replace(array(' ','-'), '', $w['geoTarget']));
	$country = geo_my_country();
	if ((!$minus && !in_array($country, $sites)) || ($minus && in_array($country, $sites))) {
		return FALSE;
	}
	//
	$navi = moon::shared('sitemap');
	if (!empty($w['showIn'])) {
		$filterPassed = false;

		$on = $navi->on();
		$pageUrl = moon::page()->uri_segments(0);
		foreach (explode(',', ltrim($w['showIn'], '-')) as $showIn) {
			$showIn = trim($showIn);
			if ($showIn{0} == '/') {
				if (strpos($pageUrl, $showIn) === 0) {
					$filterPassed = true;
					break;
				}
			} elseif ($showIn{0} == '~') {
				$showIn = substr($showIn, 1);
				if (strpos($pageUrl, $showIn) !== false) {
					$filterPassed = true;
					break;
				}
			} else {
				if ($on == $showIn) {
					$filterPassed = true;
					break;
				}
			}
		}

		if ($w['showIn']{0} === '-')
			$filterPassed = !$filterPassed;
		if (!$filterPassed)
			return false;
	}
	$bgURL = FALSE;
	if (!empty($w['roomID'])) {
		$is = $this->db->single_query('SELECT alias FROM rw2_rooms WHERE id=' . (int)$w['roomID'] . ' AND is_hidden=0');
		if (!empty($is[0])) {
			$bgURL = '/' . $is[0] . '/';
			if (!empty($w['url']) && $w['url']{0} ==='+') {
				$bgURL .= ltrim($w['url'], '+');
			}
		}
		else {
			return FALSE;
		}
	}
	if (!empty($w['url']) && $w['url']{0} !=='+') {
		$bgURL = $w['url'];
	}

	if ($bgURL !== FALSE) {
		$bgColor = empty($w['bgColor']) ? '#000000' : $w['bgColor'];
		if (!empty($w['imgPath'])) {
			$res .= '<script type="text/javascript">var bgURL = "'.$bgURL.'";</script>';
			moon::page()->css('<style type="text/css">/*<![CDATA[*/ body {background: '.$bgColor.' url(\''.$w['imgPath'].'\') no-repeat fixed top} html {background: '.$bgColor.'; cursor: pointer} #bodyBlock, #footerBlock {cursor: default} /*]]>*/</style>');
		}

		/* Toliau baneris */
		if (!empty($w['layerPath'])) {
			$cookieVar = 'b'.substr(md5($w['layerPath']),-10);
			if (!isset($_COOKIE[$cookieVar]) || $_COOKIE[$cookieVar] !='h') {
				if(isset($_GET['bps']) && $_GET['bps'] == 'hide') {
					setcookie($cookieVar, 'h', moon::locale()->now() + 86400);
				}
				$width = $height = 0;
				if (!empty($w['layerPath'])) {
					list($width, $height) = explode('x', $w['layerSize'] . 'x0');
				}
				$css = empty($w['layerDivCSS']) ? '' : $w['layerDivCSS'];
				$res = '<script type="text/javascript">function closeFlashLayer(){var d=new Date();d.setTime(Date.UTC(d.getFullYear(),d.getMonth(),d.getDate(),23,59,59,999)+d.getTimezoneOffset()*60000+1);document.cookie=\''.$cookieVar.'=h; expires=\'+d.toGMTString()+\'; path=/\';document.getElementById(\'bn0902\').style.display=\'none\'}</script><div style="position: fixed; top: 0; right: 50%; margin-right: -507px; width: '.$width.'px; height: '.$height.'px; z-index: 9999;'.$css.'" id="bn0902" onclick="closeFlashLayer()"><object classid="clsid:d27cdb6e-ae6d-11cf-96b8-444553540000" codebase="http://download.macromedia.com/pub/shockwave/cabs/flash/swflash.cab#version=6,0,40,0" width="'.$width.'" height="'.$height.'" id="mymoviename""><param name="movie" value="' . $w['layerPath'] . '?clickTAG='.$bgURL.'" /><param name="quality" value="high" /><param name="allowscriptaccess" value="always"><param name="quality" value="high" /><param name="wmode" value="transparent" /><param name="bgcolor" value="transparent" /><embed src="' . $w['layerPath'] . '?clickTAG='.$bgURL.'" wmode="transparent" quality="high" bgcolor="transparent" width="'.$width.'" height="'.$height.'" name="mymoviename" allowscriptaccess="always" align="" type="application/x-shockwave-flash" pluginspage="http://www.macromedia.com/go/getflashplayer"></embed></object></div>' . $res;
			}
		}
		return TRUE;
	}
	return FALSE;

}

}