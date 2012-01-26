<?php
class header extends moon_com {


	function main($vars) {
		$page = & moon :: page();
		$tpl = & $this->load_template();
		$navi = & moon :: shared('sitemap');
		$bCrumb = $navi->breadcrumb();
		$activeMainMenu = isset ($bCrumb[0]['id']) ? $bCrumb[0]['id'] : 0;
		// main menu
		$mainMenu = $navi->listLevel(1);
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
		$m['isHome'] =  'home' == $navi->on();
		return $tpl->parse('main', $m);
	}
	//***************************************
	//		   --- DB AND OTHER ---
	//***************************************


}

?>
