<?php


class shared_admin {
	var $tpl;
	var $items;
	var $active;
	var $group;


	/* konstruktorius */
	function shared_admin($path) {
		$this->items = array();
		$engine = & moon :: engine();
		$this->tpl = & $engine->load_template($path . 'shared_admin.htm');
		$this->id = $this->group = FALSE;
	}


	/* kiekvienas iskvietimas issaukia, privalomas metodas pagal shared specifikacija */
	function init() {
	}


	//nurodyti, kuris meniu aktyvus
	function active($active = FALSE) {
		if ($active !== FALSE) {
			$this->id = FALSE;
			$group = '';
			foreach ($this->items as $i => $v) {
				if ($v['parent'] == 0) {
					$group = $v['group'];
				}
				if ($v['id'] == $active) {
					$this->id = $i;
					$this->group = $group;
					//$page = & moon :: page();
					//$page->title($v['name']);
					return $i;
				}
			}
		}
		return $this->id;
	}


	//randa url pagal page_id
	function getLink($pageID = FALSE) {
		$id = $pageID === FALSE ? $this->id : $this->findID($pageID);
		$hide = isset ($this->items[$id]['hide']) && $this->items[$id]['hide'] == - 1;
		return ($id && !$hide ? $this->items[$id]['url'] : FALSE);
	}


	//randa title pagal page_id
	function getTitle($pageID = FALSE) {
		$id = $pageID === FALSE ? $this->id : $this->findID($pageID);
		$hide = isset ($this->items[$id]['hide']) && $this->items[$id]['hide'] == - 1;
		return ($id && !$hide ? $this->items[$id]['name'] : FALSE);
	}


	function findID($pageID) {
		if ($pageID !== FALSE) {
			foreach ($this->items as $k => $v) {
				if ($v['id'] == $pageID) {
					return $k;
				}
			}
		}
		return FALSE;
	}


	/* Naikintinas */
	function current_info($need = FALSE) {
		$a = array('title' => $this->getTitle(), 'url' => $this->getLink());
		if ($need === FALSE) {
			return $a;
		}
		else {
			return (isset ($a[$need]) ? $a[$need] : '');
		}
	}


	//history
	function breadcrumb() {
		$a = array();
		$id = $this->active();
		do {
			if ($id) {
				$i = $this->items[$id];
				$id = $i['parent'];
				if (empty ($i['hide']) || $i['hide'] != - 1) {
					$info = array('url' => $i['url'], 'name' => $i['name'], 'i' => $i['i']);
					array_unshift($a, $info);
				}
			}
			else {
				$i = FALSE;
			}
		} while ($i);
		return $a;
	}


	function children($parentID = FALSE) {
		$m = array();
		$here = $this->breadcrumb();
		$aActive = array();
		foreach ($here as $v) {
			$aActive[] = $v['i'];
		}
		foreach ($this->items as $k => $v) {
			if ($v['parent'] != $parentID) {
				continue;
			}
			if (isset ($v['hide']) && ($v['hide'] == - 1 || ($v['hide'] && !in_array($k, $aActive)))) {
				continue;
			}

			/*if (!isset ($v['url'])) {
			continue;
			}*/
			$m[$k] = $v;
		}
		return $m;
	}


	function subMenu($replace = FALSE) {
		if (is_string($replace)) {
			$r = $replace;
		}
		else {
			$t = & $this->tpl;
			$breadcrumb = $this->breadcrumb();
			$active = isset ($breadcrumb[2]) ? $breadcrumb[2]['i'] : - 1;
			$parent = isset ($breadcrumb[1]) ? $breadcrumb[1]['i'] : - 1;
			$r = array('items' => '');
			$a = $this->children($parent);
			if (empty ($a)) {
				return '';
			}
			$d = array();
			foreach ($a as $k => $v) {
				$d['isOn'] = ($k == $active) ? ' class="active"' : '';
				if (is_array($replace)) {
					foreach ($replace as $kk => $vv) {
						$v['name'] = str_replace($kk, $vv, $v['name']);
						$v['url'] = str_replace($kk, $vv, $v['url']);
					}
				}
				$d['text'] = $v['name'];
				$d['url'] = $v['url'];
				$r['items'] .= $t->parse('subItem', $d);
			}
			$r = $t->parse('subMenu', $r);
		}
		$page = & moon :: page();
		$page->set_local('admin.subMenu', $r);
		return $r;
	}


	//************ PRIVATE ************
	//perskaito txt faila
	function sitemap($file) {
		$p = & moon :: page();
		$items = $deep = array();
		$group = '';
		$parent = array();
		$no = 0;
		$content = file_get_contents($file);
		$rows = explode("\n", $content);
		$siteID = defined('_SITE_ID_') ? _SITE_ID_ : FALSE;
		$u = & moon :: user();
		foreach ($rows as $row) {
			$s = ltrim($row);
			// ignoruojam kai kurias
			if ($s === '' || $s[0] === '#') {
				continue;
			}
			//gal yra grupe
			elseif ($s[0] === '[' && preg_match('/^\[([^\]]+)\]/', $row, $is)) {
				$group = trim($is[1]);
				continue;
			}
			$inf = array();
			//gal yra parametrø tarp {}
			if (strpos($row, '{') && preg_match('/\{([^\}]+)\}/', $row, $m)) {
				//gal yra parametrø
				$d = explode(',', $m[1]);
				foreach ($d as $v) {
					if ($i = strpos($v, '=')) {
						$parN = trim(substr($v, 0, $i));
						$parV = trim(substr($v, $i + 1));
						if ('' !== $parV)
							$inf[$parN] = $parV;
					}
				}
				$row = str_replace((string) $m[0], '', $row);
				//siteID jei nurodytas ir neatitinka, pazymim removed
				if ($siteID && !empty ($inf['sites'])) {
					$a = explode(';', $inf['sites']);
					$currentHide = isset ($inf['hide']) ? $inf['hide'] : '';
					$inf['hide'] = - 1;
					foreach ($a as $v) {
						$v = trim($v);
						if ($siteID === $v) {
							$inf['hide'] = $currentHide;
							break;
						}
					}
				}
			}
			if (!empty ($inf['admin']) && !$u->i_admin($inf['admin'])) {
				$inf['hide'] = - 1;
				//continue;
			}
			$i = 0;
			$level = 0;
			while (strlen($row) && $row[$i++] === "\t") {
				$level++;
			}
			$no++;
			$inf['i'] = $no;
			//$inf['level'] = $level;
			$curLevel = count($deep);
			if ($curLevel <= $level) {
				$level = min($curLevel + 1, $level);
			}
			else {
				for ($i = $curLevel; $i > $level; $i--) {
					array_pop($deep);
				}
			}
			$deep[$level] = $no;
			$parentID = $level ? $deep[$level - 1] : 0;
			if (!$parentID) {
				$inf['group'] = $group;
			}
			$inf['parent'] = $parentID;
			if ($parentID && !empty ($items[$parentID]['hide']) && $items[$parentID]['hide'] == - 1) {
				$inf['hide'] = - 1;
			}
			$row = trim($row);
			//jei nera lygybes, praleidziam
			$pos = strpos($row, '=');
			if ($pos) {
				$inf['id'] = trim(substr($row, 0, $pos));
				$row = substr($row, $pos + 1);
				if (!isset ($inf['link'])) {
					$inf['link'] = $inf['id'];
				}
			}
			else {
				$inf['id'] = '=' . $no;
			}
			if (!isset ($inf['url']) && isset ($inf['link'])) {
				$linkPar = '';
				if (strpos($inf['link'], '|')) {
					list($inf['link'], $linkPar) = explode('|', $inf['link']);
				}
				$inf['url'] = $p->sys_linkas($inf['link'], $linkPar);
			}
			$inf['name'] = trim($row);
			$items[$no] = $inf;
		}
		$this->items = $items;
		$k = count($this->items);
		for ($i = $k; $i > 0; $i--) {
			if (isset ($this->items[$i]['url'])) {
				$parent = $this->items[$i]['parent'];
				if ($parent && !isset ($this->items[$parent]['url'])) {
					if (empty ($this->items[$i]['hide'])) {
						$this->items[$parent]['childurl'] = $this->items[$i]['url'];
					}
				}
			}
			elseif (isset ($this->items[$i]['childurl'])) {
				$this->items[$i]['url'] = $this->items[$i]['childurl'];
			}
			else {
				$this->items[$i]['hide'] = - 1;
			}
		}
		//echo '<pre>', print_r($this->items), '</pre>';
		//exit;
		//nustatom, default aktyvu
		foreach ($this->items as $v) {
			if (empty ($v['hide']) && !isset ($v['childurl'])) {
				$this->active($v['id']);
				break;
			}
		}
	}


}

?>