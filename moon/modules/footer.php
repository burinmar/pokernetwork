<?php
class footer extends moon_com {


	function main($vars) {
		return $this->load_template()->parse('main');
/*
		$tpl = & $this->load_template();
		$navi = & moon :: shared('sitemap');
		// submenu
		$child = array();
		foreach ($navi->items as $id => $d) {
			if ($d['parent'] && empty ($d['hide'])) {
				if (empty ($child[$d['parent']])) {
					$child[$d['parent']] = '';
				}
				$d['title'] = htmlspecialchars($d['title']);
				$child[$d['parent']] .= $tpl->parse('submenu', $d);
			}
		}
		// main menu
		$mainMenu = $navi->listLevel(1);
		$m = array('menu' => '');
		foreach ($mainMenu as $id => $d) {
			if (isset ($child[$id])) {
				$d['submenu'] = $child[$id];
			}
			$d['title'] = htmlspecialchars($d['title']);
			$m['menu'] .= $tpl->parse('menu', $d);
		}
		return $tpl->parse('main', $m);
		*/
	}
	//***************************************
	//           --- DB AND OTHER ---
	//***************************************


}
?>