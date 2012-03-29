<?php


class dictionary extends moon_com {


	function onload() {

		/* form of item */
		$this->form = & $this->form();
		$this->form->names('id', 'name', 'uri', 'description');
		//$this->form->fill();

		/* form of filter */
		$this->formFilter = & $this->form('name');

		/* main table */
		$this->myTable = $this->table('Dictionary');
	}


	function events($event, $par) {
		$this->use_page('Common');
		switch ($event) {

			case 'edit' :
				$id = isset ($par[0]) ? intval($par[0]) : 0;
				if ($id) {
					if (count($values = $this->getItem($id))) {
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

			case 'delete' :
				if (isset ($_POST['it'])) {
					$this->deleteItem($_POST['it']);
				}
				$this->redirect('#');
				break;

			case 'filter' :
				$filter = isset ($_POST['filter']) ? $_POST['filter'] : '';
				$this->set_var('filter', $filter);
				$this->set_var('psl', 1);
				//forget reikia kai nuimti filtra
				$this->forget();
				break;

			case 'import': 
				$this->import('pokerne2043');
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
		$vars['pageTitle'] = $win->getTitle();
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
		$ord->set_values(array('name' => 1), 1);
		//gauna linkus orderby{nr}
		$m += $ord->get_links($this->linkas('#', '', array('ord' => '{pg}')), $vars['sort']);

		/* Filtras */
		$f = & $this->formFilter;
		$f->fill($vars['filter']);
		$filter = $f->get_values();
		$fm = array();
		$fm['name'] = $f->html_values('name');
		//$fm['rooms'] = $f->options('room_id', $selRooms);
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

			/* puslapiavimui */
			if (!isset ($vars['psl'])) {
				$vars['psl'] = 1;
			}
			$pn->set_curent_all_limit($vars['psl'], $count, 30);
			$pn->set_url($this->linkas('#', '', array('page' => '{pg}')));
			$m['puslapiai'] = $pn->show_nav();
			$psl = $pn->get_info();
			$dat = $this->getList($psl['sqllimit'], $ord->sql_order());

			/* sarasas */
			$goEdit = $this->linkas('#edit', '{id}');
			$t->save_parsed('items', array('goEdit' => $goEdit));
			$locale = & moon :: locale();
			$now = $locale->now();
			$txt = moon::shared('text');
			foreach ($dat as $d) {
				//kita
				$d['name'] = htmlspecialchars($d['name']);
				$d['description'] = htmlspecialchars($txt->excerpt($d['description'],100));
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
		$f = $this->form;
		$title = $f->get('id') ? $info['titleEdit'] . ' :: ' . $f->get('name') : $info['titleNew'];
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
		$m['hide'] = $f->checked('hide', 1);
		$m += $f->html_values();
		// Other settings
		$m['uriPrefix'] = moon::shared('sitemap')->getLink('terms');
		if ($f->get('hide') > 0) {
			$f->fill(array('hide' => 1));
		}
		$m['hide'] = $f->checked('hide', 1);

		/* pridedam attachmentus ir toolbara */
		if (is_object( $rtf = $this->object('rtf') )) {
			$rtf->setInstance( $this->get_var('rtf') );
			$m['toolbar'] = $rtf->toolbar('i_description',(int)$m['id']);
		}
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
		if ($a['name'] !== '') {
			$w[] = "name like '%" . $this->db->escape($a['name'], TRUE) . "%'";
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
		$form = & $this->form;
		$form->fill($_POST);
		$d = $form->get_values();
		$id = intval($d['id']);

		/* gautu duomenu apdorojimas */
		if ($d['uri'] === '') {
			$d['uri'] = make_uri($d['name']);
		}
		//jei bus klaida
		$form->fill($d, false);

		/* validacija */
		$err = 0;
		if ($d['name'] === '') {
			$err = 1;
		}
		elseif ($d['uri'] === '') {
			$err = 2;
		}
		elseif ($d['description'] === '') {
			$err = 3;
		}
		elseif (!is_object($rtf = $this->object('rtf'))) {
			$err = 9;
		}
		else {
			//check for uri duplicates
			$sql = "SELECT id	FROM " . $this->myTable . "
					WHERE uri = '" . $this->db->escape($d['uri']) . "' AND id <> " . $id;
			if (count($a = $this->db->single_query($sql))) {
				$err = 4;
			}
		}
		if ($err) {
			$form->fill($d, false);
			$this->set_var('error', $err);
			return false;
		}

		/* jei refresh, nesivarginam */
		if ($wasRefresh = $form->was_refresh()) {
			return $id;
		}

		/* save to database */
		$ins = $form->get_values('name', 'uri', 'description');

		/* iskarpa ir kompiliuojam i html */
		$rtf->setInstance($this->get_var('rtf'));
		list(, $ins['description_html']) = $rtf->parseText($id, $ins['description']);
		if ($id) {
			$this->db->update_query($ins, $this->myTable, array('id' => $id));
			// log this action
			blame($this->my('fullname'), 'Updated', $id);
		}
		else {
			$id = $this->db->insert_query($ins, $this->myTable, 'id');
			// log this action
			blame($this->my('fullname'), 'Created', $id);
		}
		if ($id) {
			//"prisegam" objektus
			//$rtf->assignObjects($id);
		}
		return $id;
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
		// log this action
		blame($this->my('fullname'), 'Deleted', $ids);
		return true;
	}

	function import($oldDb)
	{
		$this->db->query('truncate ' . $this->table('Dictionary'));
		foreach ($this->db->array_query_assoc('SELECT * FROM ' . $oldDb . '.glossary') as $row) {
			$ins = array(
				'id' => $row['id'],
				'name' => $row['title'],
				'description' => $row['content'],
				'description_html' => htmlspecialchars($row['content']),
				'uri' => make_uri($row['title'])
			);
			$this->db->insert($ins, $this->table('Dictionary'));
		}
	}	
}

?>