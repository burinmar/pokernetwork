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
		/*$activeMainMenu = isset ($bCrumb[0]['id']) 
			? $bCrumb[0]['id'] 
			: 0;*/
		// breadcrumb
		$last = count($bCrumb)-1;
		foreach ($bCrumb as $k=>$d) {
			$d['class-current'] = $k == $last ? ' class="current"' : '';
			$d['title'] = htmlspecialchars($d['title']);
			$tplArgv['breadcrumb'] .= $tpl->parse('breadcrumb',$d);
		}
		$tplArgv['isHome'] = 'home' == $navi->on();

		$mainMenu = $this->getMenuTree($navi->items);
		$page->set_local('sys.footer:menu', $mainMenu);

		foreach ($mainMenu as $item) {
			$tplArgv['menu'] .= $this->partialRenderMenuItem($item, $tpl/*, $activeMainMenu*/);
		}

		return $tpl->parse('main', $tplArgv);
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
			$tplArgv['url.forgot']   = $this->linkas('users.forgot#');
			$tplArgv['url.register'] = $this->linkas('users.signup#');
			return moon::page()->get_local('header.hidesignIn') 
				? '' 
				: $tpl->parse('user_block.nouser', $tplArgv);
		}
	}

	private function partialRenderMenuItem($item, $tpl/*, $activeMainMenu*/)
	{
		$class = '';
		if ($item['class']) {
			$class = $item['class'];
		}
		/*if ($activeMainMenu && $activeMainMenu == $item['id']) {
			$class = ltrim($class . ' on');
		}*/
		$tplArgv['url'] = $item['url'];
		$tplArgv['title'] = htmlspecialchars($item['title']);
		$tplArgv['td:class'] = $class ? ' class="' . $class . '"' : '';
		$tplArgv['submenu'] = '';
		if (0 != count($item['children'])) {
			foreach ($item['children'] as $child) {
				$tplArgv['submenu'] .= $this->partialRenderMenuItem($child, $tpl/*, $activeMainMenu*/);
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

	private function isMenuItemHidden($item) 
	{
		return !empty ($item['hide']) || !isset ($item['url']);
	}
}