<?php

class redirects extends moon_com {


	function onload() {
		// item form
		$this->form = & $this->form();
		$this->form->names('id', 'uri_from', 'uri_to');
		//main table
		$this->myTable = $this->table('Redirects');
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

			case 'save-new' :
				if ($id = $this->saveItem()) {
					$this->redirect('#');
				}
				else {
					$this->set_var('view', 'form');
				}
				break;

			case 'save' :
				$this->updateItems();
				$this->redirect('#');
				break;

			case 'delete' :
				if (isset ($_POST['it'])) {
					$this->deleteItem($_POST['it']);
				}
				$this->redirect('#');
				break;

			default :
				break;
		}
	}


	function properties() {
		return array('view' => 'list');
	}


	function main($vars) {
		$page = & moon :: page();
		$t = & $this->load_template();
		$info = $t->parse_array('info');
		$win = & moon :: shared('admin');
		$win->active($this->my('fullname'));
		//$submenu = $win->subMenu();
		$locale = & moon :: locale();

		//******* FORM **********
		$err = (isset ($vars['error'])) ? $vars['error'] : 0;
		$f = $this->form;
		$m = array(
			'error' => $err ? $info['error' . $err] : '',
			'event' => $this->my('fullname') . '#save-new',
			'refresh' => $page->refresh_field(),
		) + $f->html_values();
		$m['hide'] = $err ? '' : ' hide';
		$form = $t->parse('viewForm', $m);

		//******* LIST **********
		$m = array('items' => '', 'form' => $form);
		//generuojam sarasa
		$dat = $this->getList();
		foreach ($dat as $d) {
			$d['uri_from'] = htmlspecialchars($d['uri_from']);
			$d['uri_to'] = htmlspecialchars($d['uri_to']);
			$m['items'] .= $t->parse('items', $d);
		}
		//$m['goNew'] = $this->linkas('#edit');
		$m['goDelete'] = $this->my('fullname') . '#delete';
		$m['evSave'] = $this->my('fullname') . '#save';
		$title = $win->current_info('title');
		$m['title'] = htmlspecialchars($title);
		$res = $t->parse('viewList', $m);

		$page->title($title);
		return $res;
	}


	//***************************************
	//           --- DB AND OTHER ---
	//***************************************
	function getList() {
		$sql = '
			SELECT id,uri_from,uri_to
			FROM ' . $this->myTable . '
			ORDER BY uri_from ASC';
		return $this->db->array_query_assoc($sql);
	}


	function saveItem() {
		$form = & $this->form;
		$form->fill($_POST);
		$d = $form->get_values();

		//gautu duomenu apdorojimas
		$d['uri_from'] = urldecode($d['uri_from']);
		$d['uri_to'] = urldecode($d['uri_to']);
		$uriParts = parse_url($d['uri_from']);
		$d['uri_from'] = !empty ($uriParts['path']) ? $uriParts['path'] : '';
		$d['uri_from'] = '/' . ltrim($uriParts['path'], '/');
		if ($d['uri_to'] !== '' && substr($d['uri_to'], 0, 7) != 'http://') {
			$d['uri_to'] = '/' . ltrim($d['uri_to'], '/');
		}

		//jei bus klaida
		$form->fill($d, false);

		//validacija
		$err = 0;
		if ($d['uri_from'] === '/') {
			$err = 1;
		}
		else {
			//check for uri duplicates
			$sql = "SELECT id	FROM " . $this->myTable . "
					WHERE uri_from = '" . $this->db->escape($d['uri_from']) . "'";
			if (count($a = $this->db->single_query($sql))) {
				$err = 2;
			}
		}
		if ($err) {
			$this->set_var('error', $err);
			return FALSE;
		}

		//jei refresh, nesivarginam
		if ($wasRefresh = $form->was_refresh()) {
			return TRUE;
		}

		//save to database
		$ins = $form->get_values('uri_from', 'uri_to');

		$db = & $this->db();
		$id = $db->insert_query($ins, $this->myTable, 'id');
		// log this action
		blame($this->my('fullname'), 'Created', $id);
		//$form->fill(array('id' => $id));
		return $id;
	}


	function updateItems() {
		if (isset ($_POST['uri_from']) && is_array($_POST['uri_from']) && is_array($_POST['uri_to'])) {
			$dat = array();
			$uri1 = array();
			foreach ($_POST['uri_from'] as $id => $v) {
				$v = urldecode($v);
				$uriParts = parse_url($v);
				$v = !empty ($uriParts['path']) ? $uriParts['path'] : '';
				$v = '/' . ltrim($uriParts['path'], '/');
				$uri1[] = $v;
				$dat[$id] = array($v, '');
			}
			foreach ($_POST['uri_to'] as $id => $v) {
				if (isset ($dat[$id])) {
					$v = urldecode($v);
					if ($v !== '' && substr($v, 0, 7) != 'http://') {
						$v = '/' . ltrim($v, '/');
					}
					$dat[$id][1] = $v;
				}
			}
			//dabar validacija ir update
			$sql = 'SELECT id,uri_from,uri_to FROM ' . $this->myTable;
			$is = $this->db->array_query_assoc($sql, 'id');
			$uri2 = array();
			foreach ($is as $v) {
				$uri2[] = $v['uri_from'];
			}
			//kokie uri dubliuosis
			$uri = array_intersect($uri1, $uri2);
			$err = 0;
			$page = & moon :: page();
			foreach ($dat as $id => $d) {
				if (!isset ($is[$id]) || $d[0] === '/' || $d[0] === $d[1] || ($d[0] != $is[$id]['uri_from'] AND in_array($d[0], $uri)) || in_array($d[1], $uri2)) {
					$err++;
                    $page->alert('Uri conflict in FROM: ' . $d[0]);
					continue;
				}
				if (($is[$id]['uri_from'] != $d[0] || $is[$id]['uri_to'] != $d[1])) {
					$this->db->update(array('uri_from' => $d[0], 'uri_to' => $d[1]), $this->myTable, $id);
				}
			}
			if ($err) {
				$page = & moon :: page();
				$page->alert($err . ' error(s) occured.');
			}
			blame($this->my('fullname'), 'Updated', '');
		}
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


}

?>