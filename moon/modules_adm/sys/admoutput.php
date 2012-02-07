<?php


class admoutput extends moon_com {


	function main($vars) {
		$page = & moon :: page();
		// body and layout
		if (isset ($vars['layout']) && isset ($vars['parts'])) {
			$t = & $this->load_template('_layouts');
			//jeigu veikiam kaip layout komponentas
			if ($vars['layout'] !== '' && $t->has_part($vars['layout'])) {
				$vars['parts']['kaire'] = $this->menuLeft();
				$vars['parts']['tabs'] = $this->menuTabs();
				$vars['parts']['submenu'] = $page->get_local('admin.subMenu');
				$body = $t->parse($vars['layout'], $vars['parts']);
			}
			else {
				$body = implode('', $vars['parts']);
			}
		}
		else {
			$body = $vars['content'];
		}
		$action = isset ($_SERVER['REQUEST_URI']) ? htmlspecialchars($_SERVER['REQUEST_URI']) : $page->php_script();
		$body = str_replace('{!action}', $action, $body);
		//
		$t = & $this->load_template();

		/* Page title */
		$oNav = & moon :: shared('admin');
		$iAmHere = array_reverse($oNav->breadcrumb());
		$a = array();
		if ($title = $page->title()) {
			$a[] = $title;
		}
		foreach ($iAmHere as $v) {
			if (!isset ($a[0]) || $a[0] != $v['name']) {
				$a[] = $v['name'];
			}
		}
		$pageTitle = $page->title(implode(' | ', $a));

		/* */
		$locale = & moon :: locale();
		$m = array();
		$m['home_url'] = $page->home_url();
		$m['lang'] = $locale->language();
		$m['title'] = htmlspecialchars($pageTitle);

		/* head tags */
		$m['head.tags'] = '';
		if (is_array($a = $page->meta())) {
			foreach ($a as $k => $v) {
				if ($v) {
					$m['head.tags'] .= '<meta name="' . $k . '" content="' . $v . '" />' . "\n";
				}
			}
		}

		/* css */
		if (is_array($a = $page->css())) {
			foreach ($a as $v) {
				if (strpos($v, '<style') !== false) {
					$m['head.tags'] .= $v . "\n";
				}
				else {
					if (strpos($v, '?')) {
						list($v) = explode('?', $v);
					}
					$v = $v . '?' . $this->getFileModTime($v);
					$m['head.tags'] .= '<link rel="stylesheet" href="' . $v . '" type="text/css" />' . "\n";
				}
			}
		}

		/* js */
		if (is_array($a = $page->js())) {
			$replace = array();
			$replace['/js/jquery.js'] = 'http://ajax.googleapis.com/ajax/libs/jquery/1.5.2/jquery.min.js';
			$replace['/js/swfobject.js'] = 'http://ajax.googleapis.com/ajax/libs/swfobject/2.2/swfobject.js';
			foreach ($a as $v) {
				if (isset ($replace[$v])) {
					$v = $replace[$v];
				}
				if (strpos($v, 'http://') !== 0) {
					if (strpos($v, '?')) {
						list($vf) = explode('?', $v);
						$v .= '&amp;' . $this->getFileModTime($vf);
					}
					else {
						$v = $v . '?' . $this->getFileModTime($v);
					}
				}
				$m['head.tags'] .= '<script type="text/javascript" src="' . $v . '" ></script>' . "\n";
			}
		}
		/* */
		$outputType = $page->get_local('output');
		switch ($outputType) {

			case "popup" :
				$m['content'] = $body;
				$res = $t->parse('popup', $m);
				break;

			default :
				$head = $t->parse('common_header', $m);
				$m = array('header' => $head, 'content' => $body, 'alerts' => '', 'todoHeader' => '');
				if ($outputType == '' || !$t->has_part($outputType)) {
					$outputType = 'page';
					$oTodo = $this->object('sys.todo');
					$todoHeader = $oTodo->getNotifyingHeader();
					$m['todoHeader'] = $todoHeader;
				}
				$m['goSite'] = '/';
				$m['goLogout'] = $this->linkas('login#logout');
				$user = & moon :: user();
				if ($user->get_user_id()) {
					$m['username'] = htmlspecialchars($user->get_user('nick'));
				}
				$errObj = & moon :: error();
				if ($errObj->count_errors('nfw')) {
					$page->alert('An error has occured. See the error log.');
				}
				if (count($alerts = $page->alert())) {
					foreach ($alerts as $err) {
						list($msg, $tipas) = $err;
						if ($tipas === 'ok') {
							$tipas = 'n';
						}
						$a = array('msg' => $msg, 'tipas' => strtolower($tipas));
						$m['alerts'] .= $t->parse('alerts', $a);
					}
				}
				$res = $t->parse($outputType, $m);
				break;
		}
		$res = str_replace('{!_AIMG_}', '/i/adm/', $res);
		return $res;
	}


	function getFileModTime($file) {
		if (strpos($file, 'http://') !== FALSE) {
			return '';
		}
		return @ filemtime($GLOBALS['CMS_PATH'] . ltrim($file, '/'));
	}


	function menuTabs() {
		$oNav = & moon :: shared('admin');
		$iAmHere = $oNav->breadcrumb();
		$active = isset ($iAmHere[1]) ? $iAmHere[1]['i'] : - 1;
		$activeSubmenu = isset ($iAmHere[2]) ? $iAmHere[2]['i'] : - 1;
		$parent = isset ($iAmHere[0]) ? $iAmHere[0]['i'] : - 1;
		$r = array('tabs' => '');
		$a = $oNav->children($parent);
		$d = array();
		$t = & $this->load_template();
		foreach ($a as $k => $v) {
			$subMenu = '';
			$children = $oNav->children($k);
			foreach ($children as $kk=>$vv) {
				if (!empty($vv['url']) && strpos($vv['url'], '*') !== FALSE) {
					// dinaminius punktus ignoruojam
					continue;
				}
				$vv['text'] = htmlspecialchars($vv['name']);
				$vv['classOn'] = $kk == $activeSubmenu ? 'class="on"' : '';
				$subMenu .= $t->parse('submenu', $vv);
			}
			$class = ($k == $active) ? 'on ' : '';
			if ($subMenu !== '') {
				$class .= 'hasMore ';
			}
			$d['submenu']= $subMenu;
			$d['classes'] = $class ? ' class="'.trim($class).'"' : '';
			$d['text'] = $v['name'];
			$d['url'] = $v['url'];
			$r['tabs'] .= $t->parse('tab', $d);
		}
		return $t->parse('tabs', $r);
	}


	function menuLeft() {
		$t = & $this->load_template();
		$oNav = & moon :: shared('admin');
		$items = $oNav->children(0);
		$groups = array();
		foreach ($items as $i => $v) {
			if (!$v['parent']) {
				$gr = $v['group'];
				if (!isset ($groups[$gr])) {
					$groups[$gr] = array();
				}
				$groups[$gr][] = $v;
			}
		}
		$res = '';
		$iAmHere = $oNav->breadcrumb();
		$active = isset ($iAmHere[0]) ? $iAmHere[0]['i'] : - 1;
		$hasGroups = count($groups) > 1 ? true : false;
		$currentGroup = $oNav->group;
		foreach ($groups as $group => $items) {
			if ($hasGroups && count($items)) {
				$d['name'] = $group;
				if ($currentGroup === $group) {
					$res .= $t->parse('group_active', $d);
				}
				else {
					$res .= $t->parse('group', $d);
				}
			}
			if ($currentGroup == $group) {
				foreach ($items as $k => $v) {
					if ($active == $k) {
						$v['class'] = 'active';
					}

					/*if ('live' == $v['id']) {
					$v['class'] .= ' live';
					$v['flag'] = 'live';
					}
					if ($v['class']) {
					$v['class'] = ' class="' . ltrim($v['class']) . '"';
					}*/
					$d = array('text' => $v['name'], 'url' => $v['url']);
					$d['classOn'] = ($active == $v['i']) ? ' class="on"' : '';
					$d['ico'] = isset ($v['ico']) ? $v['ico'] : '';
					$res .= $t->parse('item', $d);
				}
			}
		}
		return $t->parse('left_meniu', array('items' => $res));
	}


}

?>