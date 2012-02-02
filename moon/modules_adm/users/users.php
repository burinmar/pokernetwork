<?php


class users extends moon_com {


	function onload() {

		/* form of item */
		$this->form = & $this->form();
		$this->form->names('id', 'nick', 'email', 'name', 'created', 'login_date', 'avatar', 'status');
		$this->form->fill();

		/* form of filter */
		$this->formFilter = & $this->form('f2');
		$this->formFilter->names('hidden', 'text', 'kur');

		/* main table */
		$this->myTable = $this->table('Users');
	}


	function events($event, $par) {
		$this->use_page('Common');
		switch ($event) {

			case 'edit' :
				$id = isset ($par[0]) ? intval($par[0]) : 0;
				if ($id) {
					if (count($values = $this->getItem($id))) {
						if ($values['status'] === 'A') {
							$values['status'] = '';
						}
						$this->form->fill($values);
					}
					else {
						$this->set_var('error', '404');
					}
				}
				$this->set_var('view', 'form');
				break;

			case 'save' :
				if ($id = $this->saveItem()) {
					if (isset ($_POST['return'])) {
						$this->redirect('#edit', $id);
					}
					else {
						$this->redirect('#');
					}
				}
				else {
					$this->set_var('view', 'form');
				}
				break;

			case 'filter' :
				$filter = isset ($_POST['filter']) ? $_POST['filter'] : '';
				$this->set_var('filter', $filter);
				$this->set_var('psl', 1);
				//forget reikia kai nuimti filtra
				$this->forget();
				break;

			default :
				if (isset ($_GET['ord'])) {
					$this->set_var('sort', (int) $_GET['ord']);
					$this->set_var('psl', 1);
					$this->forget();
				}
				if (isset ($_GET['page'])) {
					$this->set_var('psl', (int) $_GET['page']);
				}
		}
	}


	function properties() {
		return array('psl' => 1, 'filter' => '', 'sort' => '', 'view' => 'list');
	}


	function main($vars) {
		$win = & moon :: shared('admin');
		$win->active($this->my('fullname'));
		$vars['pageTitle'] = $win->current_info('title');
		$page = & moon :: page();
		$page->title($vars['pageTitle']);
		if ($vars['view'] == 'form') {
			return $this->viewForm($vars);
		}
		else {
			return $this->viewList($vars);
		}
	}


	function viewList($vars) {
		$t = & $this->load_template();

		/******* LIST **********/
		$m = array('items' => '');
		$pn = & moon :: shared('paginate');

		/* rusiavimui */
		$ord = & $pn->ordering();
		//laukai, ir ju defaultine kryptis
		//antras parametras kuris lauko numeris defaultinis.
		$ord->set_values(array('nick' => 1, 'email' => 1, 'login_date' => 0, 'created' => 0), 4);
		//gauna linkus orderby{nr}
		$m += $ord->get_links($this->linkas('#', '', array('ord' => '{pg}')), $vars['sort']);

		/* Filtras */
		$f = & $this->formFilter;
		$f->fill($vars['filter']);
		$filter = $f->get_values();
		$selKur = array('nick' => 'Nick', 'email' => 'E-mail', 'name' => 'Name');
		$fm = array();
		$fm['text'] = $f->html_values('text');
		$fm['tag'] = $f->html_values('tag');
		$fm['kur'] = $f->options('kur', $selKur);
		$fm['hidden'] = $f->checked('hidden', 1);
		$fm['goFilter'] = $this->my('fullname') . '#filter';
		$fm['noFilter'] = $this->linkas('#filter');
		$fm['isOn'] = '';
		foreach ($filter as $k => $v) {
			if ($v) {
				$fm['isOn'] = 1;
				break;
			}
		}
		$fm['classIsOn'] = $fm['isOn'] ? ' filter-on' : '';
		$m['filtras'] = $t->parse('filtras', $fm);

		/* generuojam sarasa */
		if ($count = $this->getListCount()) {
			//puslapiavimui
			if (!isset ($vars['psl'])) {
				$vars['psl'] = 1;
			}
			$pn->set_curent_all_limit($vars['psl'], $count, 50);
			$pn->set_url($this->linkas('#', '', array('page' => '{pg}')));
			$m['puslapiai'] = $pn->show_nav();
			$psl = $pn->get_info();
			$dat = $this->getList($psl['sqllimit'], $ord->sql_order());
			$goEdit = $this->linkas('#edit', '{id}');
			$t->save_parsed('items', array('goEdit' => $goEdit));
			$loc = & moon :: locale();
			$now = $loc->now();
			foreach ($dat as $d) {
				$d['class'] = $d['status'] == 'N' || $d['status'] == 'B' ? 'item-hidden' : '';
				$d['email'] = htmlspecialchars($d['email']);
				$d['name'] = htmlspecialchars($d['name']);
				$d['nick'] = htmlspecialchars($d['nick']);
				$d['login_date'] = $loc->datef($d['login_date'], 'Date');
				$d['created'] = $loc->datef($d['created'], 'Date');
				$m['items'] .= $t->parse('items', $d);
			}
		}
		else {
			//filtras nerodomas kai tuscias sarasas
			if (!$fm['isOn']) {
				//	$m['filtras'] = '';
			}
		}
		$m['goNew'] = $this->linkas('#edit');
		$m['goDelete'] = $this->my('fullname') . '#delete';
		$m['pageTitle'] = htmlspecialchars($vars['pageTitle']);
		$res = $t->parse('viewList', $m);
		$save = array('psl' => $vars['psl'], 'sort' => (int) $vars['sort']);
		foreach ($filter as $k => $v) {
			if ($v !== '') {
				$save['filter'] = $filter;
				break;
			}
		}
		$this->save_vars($save);
		return $res;
	}


	function viewForm($vars) {
		$t = & $this->load_template();
		$info = $t->parse_array('info');
		$page = & moon :: page();

		/******* FORM **********/
		$err = (isset ($vars['error'])) ? $vars['error'] : 0;
		$f = & $this->form;
		$title = $f->get('id') ? $info['titleEdit'] : $info['titleNew'];
		$page->title($title);
		// main settings
		$m = array();
		$m['error'] = $err ? $info['error' . $err] : '';
		$m['event'] = $this->my('fullname') . '#save';
		$m['refresh'] = $page->refresh_field();
		$m['id'] = ($id = $f->get('id'));
		$m['goBack'] = $this->linkas('#');
		$m['pageTitle'] = $vars['pageTitle'];
		$m['formTitle'] = htmlspecialchars($title);
		$m['toolbar'] = '';
		$m += $f->html_values();
		// Other
		$locale = & moon :: locale();
		$m['created'] = $locale->datef($m['created'], 'Date');
		$m['login_date'] = $locale->datef($m['login_date'], 'Date');
		if ($m['avatar']) {
			$m['avatar'] = img('avatar', $id . '-' . $m['avatar'] . '?o');
		}
		else {
			$m['avatar'] = _MYPN_URI_ . 'i/avatar200x200.gif';
		}
		$statuses = array('' => 'Active', 'N' => 'Not Active', 'B' => 'Banned');
		$m['status'] = $f->options('status', $statuses);
		$i = $f->get('status');
		$m['status'] = isset($statuses[$i]) ? $statuses[$i] : $i;
		$res = $t->parse('viewForm', $m);

		/* resave vars for list */
		$save = array('psl' => $vars['psl'], 'sort' => $vars['sort'], 'filter' => $vars['filter']);
		$this->save_vars($save);
		return $res;
	}


	//***************************************
	//           --- DB AND OTHER ---
	//***************************************
	function getListCount() {
		$sql = 'SELECT count(*) FROM ' . $this->myTable . $this->_where();
		$m = $this->db->single_query($sql);
		return (count($m) ? $m[0] : 0);
	}


	function getList($limit = '', $order = '') {
		if ($order) {
			$order = ' ORDER BY ' . $order;
		}
		$sql = 'SELECT * FROM ' . $this->myTable . $this->_where() . $order . $limit;
		return $this->db->array_query_assoc($sql);
	}


	function _where() {
		if (isset ($this->tmpWhere)) {
			return $this->tmpWhere;
		}
		$a = $this->formFilter->get_values();
		$w = array();
		//$w[] = "login_date<>'0000-00-00'";
		//$w[] = 'hide<2';
		if (empty ($a['hidden'])) {
			$w[] = "status<>0";
		}
		if ($a['text'] !== '') {
			$find = $this->db->escape($a['text'], TRUE);
			switch ($a['kur']) {

				case 'email' :
				case 'nick' :
				case 'name' :
					$w[] = $a['kur'] . " like '%$find%'";
					break;

				default :
			}
		}
		$where = count($w) ? (' WHERE ' . implode(' AND ', $w)) : '';
		return ($this->tmpWhere = $where);
	}


	function getItem($id) {
		return $this->db->single_query_assoc('
			SELECT * FROM ' . $this->myTable . ' WHERE
			id = ' . intval($id));
	}


	function saveItem() {

	/* nebenaudojam */
		return FALSE;
		$form = & $this->form;
		$form->fill($_POST);
		$d = $form->get_values('id','status');
		$id = intval($d['id']);

		/* gautu duomenu apdorojimas */

		/* jei bus klaida */
		$form->fill($this->getItem($id),FALSE);
		$form->fill($d);
		$mail = & moon :: mail();

		/* validacija */
		$err = 0;

		/* jei refresh, nesivarginam */
		if ($wasRefresh = $form->was_refresh()) {
			return $id;
		}

		/* save to database */
		$ins = $form->get_values('status');

		/* valio galim aktyvuoti */
		$this->object('sys.login_object')->update($id, array('status' => $ins['status']), $err);
		if ($err) {
			// technine klaida
			$err = $err == 5 ? 2 : 1;
			$this->set_var('error', $err);
			return false;
		}

		/* save to database */
		$ins = $form->get_values('status');
		$db = & $this->db();
		if ($id) {
			$db->update_query($ins, $this->myTable, array('id' => $id));
			// log this action
			blame($this->my('fullname'), 'Updated', $id);
		}
		else {
			return FALSE;
		}
		return $id;
	}

	/****************************************
	/           --- OTHER ---
	/***************************************/


}

?>