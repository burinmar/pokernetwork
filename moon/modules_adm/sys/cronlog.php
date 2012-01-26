<?php

class cronlog extends moon_com {


	function onload() {
		//paruosiam formas
		$this->form = & $this->form();
		$this->form->names('id', 'task_id', 'start_time', 'end_time', 'message');

		//filtras
		$this->formFilter = & $this->form('task', 'text');

		$this->myTable = $this->table('CronLog');
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
					$this->set_var('view', 'form');
				}
				break;

			case 'delete' :
				if (isset ($_POST['it'])) {
					$this->deleteItem($_POST['it']);
				}
				$this->redirect('#');
				break;

			case 'deleteall' :
				$this->deleteByFilter();
				$this->redirect('#');
				break;

			case 'filter' :
				$filter = isset ($_POST['filter']) ? $_POST['filter'] : '';
				if (isset ($_GET['task'])) {
					$filter = array('task' => intval($_GET['task']));
				}
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
		$p = & moon :: page();
		$t = & $this->load_template();
		$info = $t->parse_array('info');
		$win = & moon :: shared('admin');
		$win->active($this->my('fullname'));
		$locale = & moon :: locale();
		$submenu = $win->subMenu();
		if ($vars['view'] == 'form') {
			//******* FORMA **********
			$err = (isset ($vars['error'])) ? $vars['error'] : 0;
			$f = $this->form;
			$title = $info['title'];
			$m = array(
				'error' => $err ? $info['error' . $err] : '',
				'goBack' => $this->linkas('#'),
				'pageTitle' => $win->current_info('title'),
				'formTitle' => htmlspecialchars($title),
				) + $f->html_values();
			$m['submenu'] = $submenu;
			$m['duration'] = $this->duration($m['end_time'] - $m['start_time']);
			$m['start_time'] = $locale->datef($m['start_time'], 'DateTime');
			$m['end_time'] = $locale->datef($m['end_time'], 'DateTime');
			$tasks = $this->getTasks();
			$m['event'] = isset ($tasks[$m['task_id']]) ? $tasks[$m['task_id']] : $m['task_id'];
			$m['message'] = $f->get('message');
			$res = $t->parse('viewForm', $m);
			//resave vars for list
			$save = array('psl' => $vars['psl'], 'sort' => $vars['sort'], 'filter' => $vars['filter']);
			$this->save_vars($save);
		}
		else {
			//******* SARASAS **********
			$m = array('items' => '');
			$pn = & moon :: shared('paginate');

			// rusiavimui
			$ord = & $pn->ordering();
			$ord->set_values(
				array('start_time' => 0, 'title' => 1, 'category' => 0),
				1
				);
			//gauna linkus orderby{nr}
			$m += $ord->get_links($this->linkas('#', '', array('ord' => '{pg}')), $vars['sort']);

			//Filtras
			$f = & $this->formFilter;
			$f->fill($vars['filter']);
			$filter = $f->get_values();
			$fm = array(
				'text' => $f->html_values('text'),
				'task' => $f->options('task', $tasks = $this->getTasks()),
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
				$pn->set_curent_all_limit($vars['psl'], $count, 30);
				$pn->set_url($this->linkas('#', '', array('page' => '{pg}')));
				$m['puslapiai'] = $pn->show_nav();
				$psl = $pn->get_info();

				$dat = $this->getList($psl['sqllimit'], $ord->sql_order());
				$goEdit = $this->linkas('#edit', '{id}');
				$t->save_parsed('items', array('goEdit' => $goEdit));
				foreach ($dat as $d) {
					if (strlen($d['message']) > 300) {
						$d['message'] = substr($d['message'], 0, 300) . '...';
					}
					$d['message'] = htmlspecialchars(wordwrap(strip_tags($d['message']), 20, ' ', TRUE));
					$d['started'] = date('Y-m-d H:i:s', $d['start_time']);
					$d['duration'] = $this->duration($d['end_time'] - $d['start_time']);
					$d['event'] = isset ($tasks[$d['task_id']]) ? $tasks[$d['task_id']] : '?' . $d['task_id'];
					$m['items'] .= $t->parse('items', $d);
				}
			}
			else {
				//filtras nerodomas kai tuscias sarasas
				if (!$fm['isOn']) {
					//	$m['filtras'] = '';
				}
			}
			$m['submenu'] = $submenu;
			$m['goDelete'] = $this->my('fullname') . '#delete';
			$m['goClear'] = $this->my('fullname') . '#deleteall';
			$title = $win->current_info('title');
			$m['title'] = htmlspecialchars($title);
			$res = $t->parse('viewList', $m);

			$save = array('psl' => $vars['psl'], 'sort' => (int) $vars['sort']);
			foreach ($filter as $k => $v) {
				if ($v !== '') {
					$save['filter'] = $filter;
					break;
				}
			}
			$this->save_vars($save);
		}
		//*****************************
		$p->title($title);
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
		if ($a['text'] !== '') {
			$w[] = "message like '%" . $this->db->escape($a['text'], TRUE) . "%'";
		}
		if ($a['task']) {
			$w[] = "task_id=" . intval($a['task']);
		}
		$where = count($w) ? (' WHERE ' . implode(' AND ', $w)) : '';
		return ($this->tmpWhere = $where);
	}


	function getItem($id) {
		return $this->db->single_query_assoc('
			SELECT * FROM ' . $this->myTable . ' WHERE
			id = ' . intval($id)
			);
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


	function deleteByFilter() {
		$v = $this->my('vars');
		$this->formFilter->fill($v['filter']);
		$this->db->query('DELETE FROM ' . $this->myTable . $this->_where());
		// log this action
		blame($this->my('fullname'), 'Deleted', 'by filter');
	}


	function getTasks($with_empty = true) {
		return $r = $this->db->array_query('
			SELECT id,event FROM ' . $this->table('CronTasks'),
			TRUE
			);
	}


	function duration($s) {
		$r = '';
		if ($s > 3600) {
			$r .= floor($s / 3600) . 'h ';
			$s = $s % 3600;
		}
		if ($s > 60) {
			$r .= floor($s / 60) . 'min ';
			$s = $s % 60;
		}
		$r .= $s . 's';
		return $r;
	}


}

?>