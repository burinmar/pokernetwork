<?php
class errors404 extends moon_com {


	function onload() {
		//form of filter
		$this->formFilter = & $this->form('f2');
		$this->formFilter->names('referer', 'request', 'ref', 'group');

		//main table
		$this->myTable = $this->table('404errors');
	}


	function events($event, $par) {
		$this->use_page('Common');
		switch ($event) {
			case 'delete' :
				if (isset ($_POST['it'])) {
					$this->deleteItem($_POST['it']);
				}
				$this->redirect('#');
				break;

			case 'deleteall' :
				$this->deleteByFilter();
				$this->redirect('#filter');
				break;

			case 'filter' :
				$filter = isset ($_POST['filter']) ? $_POST['filter'] : '';
				$this->set_var('filter', $filter);
				$this->set_var('psl', 1);
				//forget reikia kai nuimti filtra
				$this->forget();
				break;

			default :
				if (isset ($_GET['page'])) {
					$this->set_var('psl', (int) $_GET['page']);
				}
		}
	}


	function properties() {
		return array('psl' => 1, 'filter' => '');
	}


	function main($vars) {
		$p = & moon :: page();
		$t = & $this->load_template();
		$win = & moon :: shared('admin');
		$win->active($this->my('fullname'));

		//******* LIST **********
		$m = array('items' => '');
		$pn = & moon :: shared('paginate');

		//Filtras
		$f = & $this->formFilter;
		$f->fill($vars['filter']);
		$filter = $f->get_values();
		$fm = array(
			'referer' => $f->html_values('referer'),
			'request' => $f->html_values('request'),
			'ref0' => $f->checked('ref', - 1),
			'ref1' => $f->checked('ref', 1),
			'refx' => $f->checked('ref', ''),
			'group' => $f->checked('group', 1),
			'goFilter' => $this->my('fullname') . '#filter',
			'noFilter' => $this->linkas('#filter'),
			'isOn' => ''
			);
		foreach ($filter as $k => $v) {
			if ($v) {
				$fm['isOn'] = 1;
				break;
			}
		}
		$fm['classIsOn'] = $fm['isOn'] ? ' filter-on' : '';
		$m['filtras'] = $t->parse('filtras', $fm);

		//generuojam sarasa
		if ($count = $this->getListCount()) {
			//puslapiavimui
			if (!isset ($vars['psl'])) {
				$vars['psl'] = 1;
			}
			$pn->set_curent_all_limit($vars['psl'], $count, 50);
			$pn->set_url($this->linkas('#', '', array('page' => '{pg}')));
			$m['puslapiai'] = $pn->show_nav();
			$psl = $pn->get_info();

			$dat = $this->getList($psl['sqllimit']);
			$decode = isset ($_GET['urldecode']) ? 'urldecode' : 'ltrim';
			foreach ($dat as $d) {
				$d['styleTD'] = isset ($d['cnt']) ? ' class="num"' : '';
				//kita
				$d['date'] = (isset ($d['date'])) ? date('Y-m-d H:i:s', $d['date']) : $d['cnt'];
				$d['agent'] = htmlspecialchars($d['agent']);
				$d['uri'] = htmlspecialchars(wordwrap($decode($d['uri']), 75, "\n", TRUE));
				$d['referer'] = htmlspecialchars($decode($d['referer']));
				$d['countClass'] = (isset ($d['cnt'])) ? 'td-count' : 'td-date';
				$m['items'] .= $t->parse('items', $d);
			}
			$m['groupBy'] = (isset ($dat[0]['cnt'])) ? 'Count' : 'Date';
			$m['self'] = $this->linkas('#');
		}
		else {
			//filtras nerodomas kai tuscias sarasas
			if (!$fm['isOn']) {
				//	$m['filtras'] = '';
			}
		}
		$m['goNew'] = $this->linkas('#edit');
		$m['goDelete'] = $this->my('fullname') . '#delete';
		$m['goClear'] = $this->my('fullname') . '#deleteall';
		$title = $win->current_info('title');
		$m['title'] = htmlspecialchars($title);
		$res = $t->parse('viewList', $m);
		$save = array('psl' => $vars['psl']);
		foreach ($filter as $k => $v) {
			if ($v !== '') {
				$save['filter'] = $filter;
				break;
			}
		}
		$this->save_vars($save);

		$p->title($title);
		return $res;
	}


	//***************************************
	//           --- DB AND OTHER ---
	//***************************************
	function getListCount() {
		$gr = $this->formFilter->get('group');
		if ($gr) {
			$sql = 'SELECT count(*) FROM (SELECT id FROM ' . $this->myTable . $this->_where() . ' ORDER BY NULL) as tmp';
		}
		else {
			$sql = 'SELECT count(*) FROM ' . $this->myTable . $this->_where();
		}
		$m = $this->db->single_query($sql);
		return (count($m) ? $m[0] : 0);
	}


	function getList($limit = '') {
		$gr = $this->formFilter->get('group');
		if ($gr) {
			$sql = 'SELECT COUNT(uri) AS cnt, uri, referer, agent, ip, id ';
			$order = ' ORDER BY cnt DESC';
		}
		else {
			$sql = 'SELECT date, uri, referer, agent, ip, id ';
			$order = ' ORDER BY date DESC';
		}
		$sql .= ' FROM ' . $this->myTable . $this->_where() . $order . $limit;
		return $this->db->array_query_assoc($sql);
	}


	function _where() {
		if (isset ($this->tmpWhere)) {
			return $this->tmpWhere;
		}
		$a = $this->formFilter->get_values();
		$w = array();
		if ($a['request'] !== '') {
			$w[] = "uri like '%" . $this->db->escape($a['request'], TRUE) . "%'";
		}
		if ($a['referer'] !== '') {
			$w[] = "referer like '%" . $this->db->escape($a['referer'], TRUE) . "%'";
		}
		if ($a['ref'] == 1) {
			$w[] = "referer<>''";
		}
		elseif ($a['ref'] == - 1) {
			$w[] = "referer=''";
		}
		$where = count($w) ? (' WHERE ' . implode(' AND ', $w)) : '';

		if (!empty ($a['group'])) {
			if ($a['ref'] OR $a['referer'] !== '') {
				$where .= ' GROUP BY uri, referer';
			}
			else {
				$where .= ' GROUP BY uri';
			}
		}
		return ($this->tmpWhere = $where);
	}


	function deleteItem($ids) {
		if (!is_array($ids) || !count($ids)) {
			return;
		}
		foreach ($ids as $k => $v) {
			$ids[$k] = intval($v);
		}
		$this->db->query('
			DELETE FROM ' . $this->myTable . ' WHERE id IN (' . implode(',', $ids) . ')
		');
		return true;
	}


	function deleteByFilter() {
		$v = $this->my('vars');
		$v['filter']['group'] = '';
		$this->formFilter->fill($v['filter']);
		$this->db->query('DELETE FROM ' . $this->myTable . $this->_where());
	}

}
?>