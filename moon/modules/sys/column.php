<?php


class column extends moon_com {


	function main($vars) {
		return $this->load_template()->parse('main');

		$tpl = & $this->load_template();
		$navi = & moon :: shared('sitemap');
		$bCrumb = $navi->breadcrumb();
		$activeMainMenu = isset ($bCrumb[1]['id']) ? $bCrumb[1]['id'] : 0;
		// main menu
		$mainMenu = $navi->listLevel(2);
		if (!count($mainMenu)) {
			return '';
		}
		$m = array('menu' => '');
		foreach ($mainMenu as $id => $d) {
			$class = '';
			if ($d['class']) {
				$class = $d['class'];
			}
			if ($activeMainMenu && $activeMainMenu == $id) {
				$class = ltrim($class . ' active');
			}
			$d['td:class'] = $class ? ' class="' . $class . '"' : '';
			$d['title'] = htmlspecialchars($d['title']);
			$m['menu'] .= $tpl->parse('menu', $d);
		}
		$m['url.search'] = $navi->getLink('search');
		$m['isHome'] =  'home' == $navi->on();

		//$page->js('/js/pnslider.js');
		$m['url.search'] = $this->link('search');
		return $tpl->parse('main', $m);
	}


}

?>