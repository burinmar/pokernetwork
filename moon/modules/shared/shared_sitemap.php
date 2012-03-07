<?php

class shared_sitemap {
	var $items;


	function init() {
	}


	//konstruktorius
	function shared_sitemap() {
		$this->table = 'sitemap';
		$this->id = false;
		$this->_load();
		$this->tmp = array();
		$this->breadcrumbAdd = array();
	}


	//pazymi sitemap punkta pagal page_id
	function on($pageID = FALSE) {
		if ($pageID !== FALSE) {
			$this->id = $this->findID($pageID);
		}
		$pageID = isset ($this->items[$this->id]) ? $this->items[$this->id]['page_id'] : FALSE;
		return $pageID;
	}

	//pazymi sitemap punkta pagal id
	function id($id = FALSE) {
		if ($id !== FALSE) {
			if (isset ($this->items[(int) $id])) {
				$this->id = (int) $id;
			}
		}
		return $this->id;
	}


	//randa url pagal page_id
	function getLink($pageID = FALSE) {
		$id = $pageID === FALSE ? $this->id : $this->findID($pageID);
		return ($id ? $this->items[$id]['url'] : FALSE);
	}


	//randa title pagal page_id
	function getTitle($pageID = FALSE) {
		$id = $pageID === FALSE ? $this->id : $this->findID($pageID);
		return ($id ? $this->items[$id]['title'] : FALSE);
	}


	//randa page pagal id
	function getPage($id = FALSE) {
		if ($id === FALSE) {
			$id = $this->id;
		}
		$id = intval($id);
		if (!isset ($this->tmp[$id])) {
			$db = & moon :: db();
			$this->tmp[$id] = $db->single_query_assoc("
				SELECT page_id, xml, title, meta_title, meta_keywords, meta_description, uri, content_html, css,	IF(`content`='',1,0) as html
				FROM " . $this->table . "
				WHERE id = $id  AND is_deleted = 0
				");
		}
		return $this->tmp[$id];
	}


	//breadcrumb
	//Galima kelis parametrus, pavidalo array('url'=>'name')
	function breadcrumb($add = FALSE) {
		//papildom breadcrumb
		if ($add) {
			$this->breadcrumbAdd = array();
			$a = func_get_args();
			foreach ($a as $k => $arg) {
				if (is_array($arg)) {
					while (list($url, $name) = each($arg)) {
						$this->breadcrumbAdd[] = array('url' => $url, 'title' => $name);
					}
				}
			}
		}
		$a = array();
		$id = $this->id();
		$items = $this->items;
		do {
			if (isset ($items[$id])) {
				$i = $items[$id];
				$id = $i['parent'];
				if (count($a) || $i['url'] !== '/') {
					$info = array('url' => $i['url'], 'title' => $i['title'], 'id' => $i['id'], 'page_id' => $i['page_id']);
					array_unshift($a, $info);
				}
			}
			else {
				$id = FALSE;
			}
		} while ($id);
		if (count($this->breadcrumbAdd)) {
			$a = array_merge($a, $this->breadcrumbAdd);
		}
		return $a;
	}


	//aktyvaus punkto kazkurio lygio visi irasai
	function listLevel($level) {
		$m = array();
		$path = $this->breadcrumb();
		$isOn = - 1;
		if ($level < 2) {
			$parent = 0;
		}
		elseif (isset ($path[$level - 2])) {
			$parent = $path[$level - 2]['id'];
		}
		else {
			return $m;
		}
		foreach ($this->items as $k => $v) {
			if ($v['parent'] != $parent) {
				continue;
			}
			if (!empty ($v['hide']) || !isset ($v['url'])) {
				continue;
			}
			if (!isset ($v['class'])) {
				$v['class'] = '';
			}

			/*$v['ainfo'] = '';
			if (isset ($v['title']))
			$v['ainfo'] .= ' title="' . $v['title'] . '"';
			if (isset ($v['color']))
			$v['ainfo'] .= ' style="color:' . $v['color'] . '"';
			if (isset ($v['rel']))
			$v['ainfo'] .= ' rel="' . $v['rel'] . '"';*/
			$m[$k] = $v;
		}
		return $m;
	}


	function findID($pageID) {
		if ($pageID !== FALSE) {
			foreach ($this->items as $k => $v) {
				if (isset ($v['page_id']) && $v['page_id'] == $pageID) {
					return $k;
				}
			}
		}
		return FALSE;
	}


	function _load() {
		// loads menu from db. pages table
		$this->items = array();
		$db = & moon :: db();
		$sql = '
			SELECT id, page_id, parent_id, uri, title, active_tab, menu_tab_class, hide, options, geo_target, sort
			FROM ' . $this->table . '
			WHERE is_deleted = 0
			ORDER BY sort';
		$result = $db->array_query_assoc($sql);
		$geoId = 0;//geo_my_id();
		foreach ($result as $r) {
			if ($r['page_id'] == '') {
				//continue;
			}
			$item['isLink'] = ($r['options'] & 1) ? TRUE : FALSE;
			// if it's link - apply geo id filter
			if ($item['isLink'] && (!is_null($r['geo_target']) && $r['geo_target'] > 0 && !($r['geo_target'] & (1 << $geoId)))) {
				continue;
			}
			$no = $r['id'];
			$item = array();
			if (($r['parent_id'] == 0 && $r['active_tab'] == 0) || $r['hide']) {
				$item['hide'] = 1;
			}
			$item['url'] = $r['uri'];
			$item['id'] = $no;
			$item['parent'] = $r['parent_id'];
			$item['sort'] = $r['sort'];
			$item['page_id'] = $r['page_id'];
			$item['title'] = $r['title'];
			if ($r['menu_tab_class'] != '') {
				$item['class'] = $r['menu_tab_class'];
			}
			$this->items[$no] = $item;
		}
	}


}

?>