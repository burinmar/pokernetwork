<?php
class sitemap extends moon_com {


	function events($event, $par) {
		$this->use_page('2col');
	}


	function main($vars) {
		$tpl = & $this->load_template();
		$this->siteMap = $this->getStructure();
		$siteMap = & $this->siteMap;
		$siteMap[0] = array();
		//kai kuriu nerodom
		$ignore = array('home', 'sitemap', 'search', 'signup');
		//surenkam vaikus
		foreach ($siteMap as $i => $d) {
			if ($i < 1 || in_array($d['page_id'], $ignore)) {
				continue;
			}
			//isimtis (pridedam roomsus)
			if ($d['page_id'] == 'rooms') {
				$siteMap[- 1] = $d;
				$siteMap[- 1]['parent_id'] = $i;
				$siteMap[- 1]['page_id'] = 'poker-rooms';
				$siteMap[$i]['child'] = array(- 1);
			}
			$pid = $d['parent_id'];
			if (!isset ($siteMap[$pid]['child'])) {
				$siteMap[$pid]['child'] = array();
			}
			$siteMap[$pid]['child'][] = $i;
		}
		$m = array();
		$page = & moon :: page();
		$m['pageTitle'] = htmlspecialchars($page->title());
		//rekursija
		$m['items'] = $this->iterate(0);
		$res = $tpl->parse('main', $m);
		return $res;
	}


	//***************************************
	//           --- DB AND OTHER ---
	//***************************************
	function getStructure() {
		$sql = '
			SELECT	id,	page_id, parent_id, title, uri, IF(sort>0, sort, 999) as sort
			FROM sitemap
			WHERE is_deleted = 0 AND hide = 0 AND (options & 1) = 0
			ORDER BY parent_id,sort,title ';
		return $this->db->array_query_assoc($sql, 'id');
	}


	function iterate($id) {
		$a = & $this->siteMap[$id];
		$m = array();
		$m['child'] = '';
		if (isset ($a['child'])) {
			foreach ($a['child'] as $ch) {
				$m['child'] .= $this->iterate($ch);
			}
		}
		elseif (!empty ($a['page_id'])) {
			//papildomi punktai
			$m['child'] .= $this->get_data_by_pageId($a['page_id']);
		}
		if ($id) {
			$m['title'] = $a['title'];
			$m['uri'] = $a['uri'];
			$tpl = & $this->load_template();
			return $tpl->parse('items', $m);
		}
		else {
			return $m['child'];
		}
	}


	function get_data_by_pageId($pageId) {
		switch ($pageId) {

			case 'poker-rooms' :
				$sql = "
					SELECT name AS title, alias AS uri
					FROM " . $this->table('Rooms') . "
					WHERE is_hidden = 0 ORDER BY sort_1 ASC";
				$res = $this->db->array_query_assoc($sql);
				$s = '';
				$t = & $this->load_template();
				foreach ($res as $d) {
					$d['uri'] = '/' . $d['uri'] . '/';
					$s .= $t->parse('items', $d);
				}
				return $s;


			default :
				return '';
		}
	}


}

?>