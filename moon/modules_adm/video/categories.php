<?php


class categories extends moon_com {


	function onload() {

		/* form of item */
		$this->form = & $this->form();
		$this->form->names('id', 'category_type', 'title', 'uri', 'meta_description', 'description', 'hide', 'tags');

		/* main table */
		$this->myTable = $this->table('VideosCategories');
	}


	function events($event, $par) {
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

			case 'sort' :
				if (isset ($_POST['rows'])) {
					$this->updateSortOrder($_POST['rows']);
				}
				$this->redirect('#');
				break;

			default :
				break;
		}
		$this->use_page('Common');
	}


	function properties() {
		return array('view' => 'list');
	}


	function main($vars) {
		$win = & moon :: shared('admin');
		$win->active($this->my('fullname'));
		$page = & moon :: page();
		$vars['pageTitle'] = $win->getTitle();
		$vars['uriPrefix'] = moon::shared('sitemap')->getLink('video');
		if ($vars['view'] == 'form') {
			return $this->viewForm($vars);
		}
		else {
			return $this->viewList($vars);
		}
	}


	function viewList($vars) {
		$t = & $this->load_template();
		$page = & moon :: page();

		/******* LIST **********/
		$page->js('/js/tablednd_0_5.js');
		//$page->js('/js/common-table-sorting.js');
		$m = array('items' => '');

		/* generuojam sarasa */
		$dat = $this->getList();
		$goEdit = $this->linkas('#edit', '{id}');
		$t->save_parsed('items', array('goEdit' => $goEdit, 'uriPrefix'=>$vars['uriPrefix']));
		$usageCount = $this->usageCount();
		//$locale = & moon :: locale();
		//$now = $locale->now();
		foreach ($dat as $d) {
			$d['class'] = $d['hide'] ? 'item-hidden' : '';
			//$d['goUri'] = ($d['uri'] != '') ? $page->home_url() . 'editors/' . $d['uri'] : '';
			$d['title'] = htmlspecialchars($d['title']);
			$d['tags'] = htmlspecialchars(str_replace(',', ', ', $d['tags']));
			//$d['created_on'] = $locale->datef($d['created_on']);
			//$d['created'] = $locale->datef($d['created'], 'Date');
			$d['usageCount'] = isset ($usageCount[$d['id']]) ? $usageCount[$d['id']] : 0;
			$m['items'] .= $t->parse('items', $d);
		}
		$m['goNew'] = $this->linkas('#edit');
		$m['goDelete'] = $this->my('fullname') . '#delete';
		$m['goSort'] = $this->my('fullname') . '#sort';
		$m['pageTitle'] = htmlspecialchars($vars['pageTitle']);
		$res = $t->parse('viewList', $m);

		/*$save = array('psl' => $vars['psl'], 'sort' => (int) $vars['sort']);
		foreach ($filter as $k => $v) {
		if ($v !== '') {
		$save['filter'] = $filter;
		break;
		}
		}
		$this->save_vars($save);*/
		return $res;
	}


	function viewForm($vars) {
		$t = & $this->load_template();
		$info = $t->parse_array('info');
		$page = & moon :: page();

		/******* FORM **********/
		$err = (isset ($vars['error'])) ? $vars['error'] : 0;
		//$page->css($t->parse('cssForm'));
		$f = $this->form;
		$title = $f->get('id') ? $info['titleEdit'] : $info['titleNew'];
		// main settings
		$m = array();
		$m['error'] = $err ? $info['error' . $err] : '';
		$m['event'] = $this->my('fullname') . '#save';
		$m['refresh'] = $page->refresh_field();
		$m['id'] = ($id = $f->get('id'));
		$m['goBack'] = $this->linkas('#');
		$m['pageTitle'] = $vars['pageTitle'];
		$m['formTitle'] = htmlspecialchars($title);
		$m['uriPrefix']=$vars['uriPrefix'];
		$m += $f->html_values();
		// Other settings
		if ($f->get('hide') > 0) {
			$f->fill(array('hide' => 1));
		}
		$m['hide'] = $f->checked('hide', 1);
		$res = $t->parse('viewForm', $m);

		/* resave vars for list */
		//$save = array('psl' => $vars['psl'], 'sort' => $vars['sort'], 'filter' => $vars['filter']);
		//$this->save_vars($save);
		return $res;
	}


	//***************************************
	//           --- DB AND OTHER ---
	//***************************************
	function getList($limit = '') {
		$sql = '
			SELECT id,title,uri,hide,tags
			FROM ' . $this->myTable . '
			WHERE hide<2 ORDER BY sort_order ASC' . $limit;
		return $this->db->array_query_assoc($sql);
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
		$d['uri'] = str_replace('/', '', $d['uri']);
		$d['hide'] = empty ($d['hide']) ? 0 : 1;
		//tagai
		if ($d['tags']) {
			$a = explode(',', $d['tags']);
			$b = array();
			foreach ($a as $v) {
				if (($v = trim($v)) !== '') {
					$b[] = $v;
				}
			}
			$d['tags'] = implode(',', array_unique($b));
		}
		//jei bus klaida
		$form->fill($d, false);

		/* validacija */
		$err = 0;
		if ($d['title'] === '') {
			$err = 1;
		}
		elseif ($d['uri'] === '') {
			$err = 2;
		}
		else {
			//check for uri duplicates
			$sql = "SELECT id	FROM " . $this->myTable . "
					WHERE hide < 2 AND uri = '" . $this->db->escape($d['uri']) . "' AND id <> " . $id;
			if (count($a = $this->db->single_query($sql))) {
				$err = 3;
			}
		}
		if ($err) {
			$this->set_var('error', $err);
			return false;
		}

		/* jei refresh, nesivarginam */
		if ($wasRefresh = $form->was_refresh()) {
			return $id;
		}

		/* save to database */
		$ins = $form->get_values('title', 'uri', 'meta_description', 'description', 'hide', 'tags');
		$db = & $this->db();
		if ($id) {
			$db->update_query($ins, $this->myTable, array('id' => $id));
			// log this action
			blame($this->my('fullname'), 'Updated', $id);
		}
		else {
			$id = $db->insert_query($ins, $this->myTable, 'id');
			// log this action
			blame($this->my('fullname'), 'Created', $id);
		}
		$form->fill(array('id' => $id));
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
			UPDATE ' . $this->myTable . ' SET hide = 2 WHERE id IN (' . implode(',', $ids) . ')
		');
		// log this action
		blame($this->my('fullname'), 'Deleted', $ids);
		return true;
	}


	function updateSortOrder($rows) {
		$rows = explode(';', $rows);
		$order = array();
		$when = '';
		$i = 1;
		$ids = array();
		foreach ($rows as $id) {
			if ($key = intval(substr($id, 3))) {
				$ids[] = $key;
				$when .= 'WHEN id = ' . $key . ' THEN ' . $i++. ' ';
			}
		}
		if (count($ids)) {
			$sql = 'UPDATE ' . $this->myTable . '
				SET sort_order =
					CASE
					' . $when . '
					END
				WHERE id IN (' . implode(', ', $ids) . ')';
			$this->db->query($sql);
			blame($this->my('fullname'), 'Updated', 'Changed order');
		}
	}


	function usageCount() {
		//return array();
		$a = $this->db->array_query('
			SELECT category,count(*) FROM ' . $this->table('Videos') . '
			WHERE category<>""
			GROUP BY category ORDER BY NULL
			', TRUE);
		$c = array();
		foreach ($a as $k=>$v) {
			$b = strpos($k,',') ? explode(',', $k) : array($k);
			foreach ($b as $k) {
				$c[$k] = isset($c[$k]) ? ($c[$k] + $v) : $v;
			}
		}
		return $c;
	}


}

?>