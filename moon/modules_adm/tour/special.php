<?php

class special extends moon_com {

	function onload() {
		//form of item
		$this->form = & $this->form();
		$this->form->names('id', 'hide', 'date', 'name', 'qualification_from', 'qualification_to', 'qualification_points', 'timezone', 'prizepool', 'room_id', 'spotlight_id', 'body', 'tags', 'dateTime', 'qFromTime', 'qToTime', 'master_id', 'master_updated', 'updated', 'exclusive', 'password', 'password_from', 'passFromTime', 'nosync', 'master_id', 'skin');
		$this->form->fill(array('date' => time(), 'timezone' => 0, 'qualification_points' => - 1, 'exclusive' => 0));
		//form of filter
		$this->formFilter = & $this->form('f2');
		$this->formFilter->names('room_id', 'text', 'hidden', 'tag');
		//main table
		$this->myTable = $this->table('SpecTournaments');
	}

	function events($event, $par) {
		switch ($event) {

			case 'edit':
				$id = isset ($par[0]) ? intval($par[0]):0;
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

			case 'save':
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

			case 'delete':
				if (isset ($_POST['it'])) {
					$this->deleteItem($_POST['it']);
				}
				$this->redirect('#');
				break;

			case 'deleteall':
				$this->set_var('deleteByFilter', TRUE);
				break;

			case 'filter':
				$filter = isset ($_POST['filter']) ? $_POST['filter']:'';
				$this->set_var('filter', $filter);
				$this->set_var('psl', 1);
				//forget reikia kai nuimti filtra
				$this->forget();
				break;

			case 'cron':
				// importuoja is pokernews
				$s = $this->syncRun();
				$page = & moon :: page();
				$page->set_local('cron', $s);
				return;

			default:
				if (isset ($_GET['ord'])) {
					$this->set_var('sort', (int) $_GET['ord']);
					$this->set_var('psl', 1);
					$this->forget();
				}
				if (isset ($_GET['page'])) {
					$this->set_var('psl', (int) $_GET['page']);
				}
		}
		$this->use_page('Common');
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
		//******* LIST **********
		$m = array('items' => '');
		$pn = & moon :: shared('paginate');
		// rusiavimui
		$ord = & $pn->ordering();
		//laukai, ir ju defaultine kryptis
		//antras parametras kuris lauko numeris defaultinis.
		$ord->set_values(array('date' => 1, 'created' => 1), 1);
		//gauna linkus orderby{nr}
		$m += $ord->get_links($this->linkas('#', '', array('ord' => '{pg}')), $vars['sort']);
		//kategorijos
		$rooms = $this->getRooms();
		$selRooms = array();
		foreach ($rooms as $v) {
			$selRooms[$v['id']] = $v['name'];
		}
		//Filtras
		$f = & $this->formFilter;
		$f->fill($vars['filter']);
		$filter = $f->get_values();
		$fm = array('text' => $f->html_values('text'), 'tag' => $f->html_values('tag'), 'rooms' => $f->options('room_id', $selRooms), 'hidden' => $f->checked('hidden', 1), 'goFilter' => $this->my('fullname') . '#filter', 'noFilter' => $this->linkas('#filter'), 'isOn' => '');
		foreach ($filter as $k => $v) {
			if ($v) {
				$fm['isOn'] = 1;
				break;
			}
		}
		$fm['classIsOn'] = $fm['isOn'] ? ' filter-on':'';
		$m['filtras'] = $t->parse('filtras', $fm);
		$win = & moon :: shared('admin');
		$m['tabs'] = $win->subMenu();
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
			$loc = & moon :: locale();
			$now = $loc->now();
			foreach ($dat as $d) {
				$d['class'] = $d['date'] < $now || $d['hide'] ? 'item-hidden':'';
				//$d['class'] = '';
				$d['styleTD'] = '';
				if (!empty ($d['master_id'])) {
					//sync ikona
					$sType = (int) $d['master_updated'] < (int) $d['updated'] ? 1:2;
					$d['styleTD'] = ' class="sync' . $sType . '"';
				}
				//rooms
				if ($d['room_id'] && isset ($rooms[$d['room_id']])) {
					$d['room'] = htmlspecialchars($rooms[$d['room_id']]['name']);
					$d['currency'] = $rooms[$d['room_id']]['currency'];
				}
				else {
					$d['room'] = '';
					$d['currency'] = 'USD';
				}
				//kita
				$d['title'] = htmlspecialchars($d['name']);
				list($shift, $d['gmt']) = $loc->timezone($d['timezone'], $d['date']);
				$d['tags'] = '';
				// htmlspecialchars(str_replace(',', ', ', $d['tags']));
				$d['date'] = $loc->gmdatef($d['date'] + $shift, 'DateTime');
				$d['created'] = $loc->datef($d['created'], 'Date');
				$d['qualification_from'] = ($d['qualification_from'] === '0000-00-00 00:00:00') ? '&nbsp;':substr($d['qualification_from'], 0, - 3);
				$d['qualification_to'] = ($d['qualification_to'] === '0000-00-00 00:00:00') ? '&nbsp;':substr($d['qualification_to'], 0, - 3);
				if ($d['qualification_points'] < 0) {
					$d['qualification_points'] = '-';
				}
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
		$m['goClear'] = $this->my('fullname') . '#deleteall';
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
		//******* FORM **********
		$err = (isset ($vars['error'])) ? $vars['error']:0;
		//$page->css($t->parse('cssForm'));
		$f = $this->form;
		$title = $f->get('id') ? $info['titleEdit']:$info['titleNew'];
		$page->title($title);
		$m = array('error' => $err ? $info['error' . $err]:'', 'event' => $this->my('fullname') . '#save', 'refresh' => $page->refresh_field(), 'id' => ($id = $f->get('id')), 'goBack' => $this->linkas('#'), 'pageTitle' => $vars['pageTitle'], 'formTitle' => htmlspecialchars($title), 'toolbar' => '', 'hide' => $f->checked('hide', 1)) + $f->html_values();
		if ($f->get('hide') > 0) {
			$f->fill(array('hide' => 1));
		}
		$m['hide'] = $f->checked('hide', 1);
		$m['nosync'] = $f->checked('nosync', 1);
		// date and time
		$datetime = 0;
		$locale = & moon :: locale();
		if (is_numeric($m['date']) && $m['date'] > 86400) {
			list($shift) = $locale->timezone($m['timezone'], $m['date']);
			$m['date'] += $shift;
			$datetime = $m['date'];
			$m['dateTime'] = gmdate('H:i', $m['date']);
			$m['date'] = gmdate('Y-m-d', $m['date']);
		}
		$m['timezones'] = $f->options('timezone', $locale->select_timezones());
		if ($m['qualification_from'] && count($a = explode(' ', $m['qualification_from'])) > 1) {
			list($m['qualification_from'], $m['qFromTime']) = $a;
			$m['qFromTime'] = substr($m['qFromTime'], 0, 5);
			if ($m['qualification_from'] === '0000-00-00') {
				$m['qualification_from'] = '';
			}
		}
		if ($m['qualification_to'] && count($a = explode(' ', $m['qualification_to'])) > 1) {
			list($m['qualification_to'], $m['qToTime']) = $a;
			$m['qToTime'] = substr($m['qToTime'], 0, 5);
			if ($m['qualification_to'] === '0000-00-00') {
				$m['qualification_to'] = '';
			}
		}
		if ($m['qualification_points'] < 0) {
			$m['qualification_points'] = '';
		}
		if (is_numeric($m['password_from']) && $m['password_from'] > 86400) {
			list($shift) = $locale->timezone($m['timezone'], $m['password_from']);
			$m['password_from'] += $shift;
			$m['passFromTime'] = gmdate('H:i', $m['password_from']);
			$m['password_from'] = gmdate('Y-m-d', $m['password_from']);
		}
		else {
			$m['password_from'] = $m['passFromTime'] = '';
		}
		$m['iDeveloper'] = moon::user()->i_admin('developer');
		$rooms = $this->getRooms($m['room_id']);
		$selRooms = array();
		$m['jsCurrency'] = '';
		foreach ($rooms as $v) {
			if ($v['currency'] !== 'USD') {
				$m['jsCurrency'] .= "roomC[" . $v['id'] . "] = '" . $v['currency'] . "'\n";
			}
			$selRooms[$v['id']] = $v['name'];
		}

		if ($id && isset($rooms[$m['room_id']])) {
			$uri = $rooms[$m['room_id']]['alias'];
			$now = $locale->now;
			if ($uri && !$f->get('hide') && $datetime > $now) {
				$m['landingURL'] = '/' . $uri . '/freerolls/' . $id . '.htm';
			}
		}
		/*if ($id) {
			$now = $locale->now;
			if (!$f->get('hide') && $datetime > $now) {
				$m['landingURL'] = moon :: shared('sitemap')->getLink('freerolls-special') . '?id=' . $id;
			}
		}*/
		$m['currency'] = isset ($rooms[$m['room_id']]) ? $rooms[$m['room_id']]['currency']:'USD';
		$m['rooms'] = $f->options('room_id', $selRooms);
		//spotlights
		$spotlights = $this->getSpotlights($m['spotlight_id']);
		$selSpotlights = array();
		//$m['jsCurrency'] = '';
		foreach ($spotlights as $v) {

			/*if ($v['currency'] !== 'USD') {
			$m['jsCurrency'] .= "roomC[".$v['id']."] = '" .$v['currency']. "'\n";
			}*/
			$selSpotlights[$v['id']] = $v['title'];
		}
		$m['spotlight'] = $f->options('spotlight_id', $selSpotlights);
		//pridedam attachmentus ir toolbara
		if (is_object($rtf = $this->object('rtf'))) {
			$rtf->setInstance($this->get_var('rtf'));
			$m['toolbar'] = $rtf->toolbar('i_content', (int) $m['id']);
		}
		//master info
		if ($m['master_id']) {
			list($shift, $gmt) = $locale->timezone($m['timezone'], $f->get('date'));
			$m['master_time'] = $locale->gmdatef($f->get('date'), 'DateTime', $shift) . ' ' . $gmt;
			$m['master_room'] = isset ($rooms[$m['room_id']]) ? $rooms[$m['room_id']]['name']:'unknown';
			$m['class-sync'] = (int) $m['updated'] > (int) $m['master_updated'] ? ' sync1':' sync2';
			$a = (int) $m['updated'] < 0 ? 0:$this->getMasterInfo($m['master_id']);
			if (!empty ($a)) {
				$txt = moon :: shared('text');
				//$m['master_title'] = htmlspecialchars($a['name']);
				$m['master_body'] = nl2br(htmlspecialchars($a['body']));
				if ($a['body_previous']) {
					$m['master_body'] = $txt->htmlDiff($a['body_previous'], $a['body']);
				}
			}
		}
		$m['exclusive0'] = $f->checked('exclusive', '0');
		$m['exclusive1'] = $f->checked('exclusive', '1');
		$res = $t->parse('viewForm', $m);
		//resave vars for list
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
		return (count($m) ? $m[0]:0);
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
		//$w[] = 'hide<2';
		if ($a['text'] !== '') {
			$w[] = "name like '%" . $this->db->escape($a['text'], TRUE) . "%'";
		}
		if ($a['room_id']) {
			$w[] = "room_id=" . intval($a['room_id']);
		}
		elseif (!$a['hidden']) {
			// rodom tik tuos turnyrus, kuriu rooms on
			$sql = 'SELECT id, id FROM ' . $this->table('Rooms') . ' WHERE is_hidden=0';
			$ids = array_keys($this->db->array_query($sql, TRUE));
			if (count($ids)) {
				$w[] = 'room_id IN (' . implode(', ', $ids) . ')';
			}
		}
		if (!$a['hidden']) {
			$w[] = "hide<2 AND `date`>" . time();
		}
		if ($a['tag'] !== '') {
			$w[] = "FIND_IN_SET('" . $this->db->escape($a['tag']) . "',tags)";
		}
		$where = count($w) ? (' WHERE ' . implode(' AND ', $w)):'';
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
		$masterID = $d['master_id'];
		if ($masterID && $id) {
			//gautu duomenu apdorojimas
			$d['hide'] = empty ($d['hide']) ? 0:1;
			$d = $form->get_values('body', 'hide', 'spotlight_id', 'skin') + $this->getItem($id);
			//validacija
			if ($d['name'] === '') {
				$form->fill($d, false);
				$this->set_var('error', 1);
				return false;
			}
			//save to database
			$ins = $form->get_values('body', 'hide', 'spotlight_id', 'skin');
			//iskarpa ir kompiliuojam i html
			$rtf = $this->object('rtf');
			$rtf->setInstance($this->get_var('rtf'));
			list(, $ins['body_html']) = $rtf->parseText($id, $ins['body'], TRUE);
			$ins['updated'] = time();
			$this->db->update($ins, $this->myTable, array('id' => $id));
			blame($this->my('fullname'), 'Updated', $id);
			//update master table
			$this->db->query('UPDATE ' . $this->table('SpecTournamentsMaster') . ' SET body_previous = body WHERE id=' . intval($masterID));
			return $id;
		}
		//gautu duomenu apdorojimas
		$d['date'] = $this->makeTime($d['date'], $d['dateTime']);
		$d['password_from'] = $this->makeTime($d['password_from'], $d['passFromTime']);
		$d['hide'] = empty ($d['hide']) ? 0:1;
		$d['nosync'] = empty ($d['nosync']) ? 0:1;
		$d['exclusive'] = empty ($d['exclusive']) ? 0:1;
		$d['qualification_from'] = $this->makeTime($d['qualification_from'], $d['qFromTime']);
		$d['qualification_to'] = $this->makeTime($d['qualification_to'], $d['qToTime']);
		if ($d['qualification_points'] === '') {
			$d['qualification_points'] = - 1;
		}
		//tagai

		/*if ($d['tags']) {
		$a = explode(',', $d['tags']);
		$b = array();
		foreach ($a as $v) {
		if (($v = trim($v)) !== '') {
		$b[] = $v;
		}
		}
		$d['tags'] = implode(',', array_unique($b));
		}*/
		//patikrinam, ar roomsas nera pasleptas
		if ($d['room_id']) {
			$a = $this->db->single_query('
				SELECT id FROM ' . $this->table('Rooms') . '
				WHERE id=' . intval($d['room_id']) . ' AND is_hidden=0');
			if (empty ($a)) {
				//vadinasi roomsas neaktyvus
				$d['hide'] = 1;
				$p = & moon :: page();
				$p->alert('This room is disabled!');
			}
		}
		//jei bus klaida
		$form->fill($d, false);
		//validacija
		$err = 0;
		if ($d['name'] === '') {
			$err = 1;
		}
		elseif ($d['date'] === FALSE) {
			$err = 2;
		}
		elseif (empty ($d['room_id'])) {
			$err = 5;
		}
		elseif ($d['qualification_from'] === FALSE) {
			$err = 3;
		}
		elseif ($d['qualification_to'] === FALSE) {
			$err = 4;
		}
		elseif (!is_object($rtf = $this->object('rtf'))) {
			$err = 9;
		}
		if ($err) {
			$d['date'] = $_POST['date'];
			$d['qualification_from'] = $_POST['qualification_from'];
			$d['qualification_to'] = $_POST['qualification_to'];
			$form->fill($d, false);
			$this->set_var('error', $err);
			return false;
		}
		//jei refresh, nesivarginam
		if ($wasRefresh = $form->was_refresh()) {
			return $id;
		}
		//save to database
		$ins = $form->get_values('date', 'name', 'qualification_from', 'qualification_to', 'qualification_points', 'timezone', 'exclusive', 'prizepool', 'room_id', 'spotlight_id', 'body', 'tags', 'hide', 'password', 'password_from', 'skin');
		$locale = & moon :: locale();
		list($shift) = $locale->timezone($ins['timezone'], $ins['date']);
		$ins['date'] -= $shift;
		$ins['updated'] = time();
		$ins['qualification_from'] = ($t = $ins['qualification_from']) ? gmdate('Y-m-d H:i:s', $t):'0000-00-00 00:00:00';
		$ins['qualification_to'] = ($t = $ins['qualification_to']) ? gmdate('Y-m-d H:i:s', $t):'0000-00-00 00:00:00';
		//iskarpa ir kompiliuojam i html
		$rtf->setInstance($this->get_var('rtf'));
		list(, $ins['body_html']) = $rtf->parseText($id, $ins['body'], TRUE);
		//
		$db = & $this->db();
		if ($id) {
			$db->update_query($ins, $this->myTable, array('id' => $id));
			blame($this->my('fullname'), 'Updated', $id);
		}
		else {
			$ins['created'] = $ins['updated'];
			//$u = &moon::user();
			//$ins['authors'] = $u->get_user_id();
			$id = $db->insert_query($ins, $this->myTable, 'id');
			// log this action
			blame($this->my('fullname'), 'Created', $id);
		}
		$form->fill(array('id' => $id));
		return $id;
	}

	function makeTime($d, $t) {
		$r = 0;
		if ($d) {
			if (count($a = explode('-', $d)) != 3 || !checkdate($a[1], $a[2], $a[0])) {
				return false;
			}
			if ($t) {
				if (count($a = explode(':', $t)) != 2 || ($t = $a[0] * 60 + $a[1]) >= 3600 || $t < 0) {
					$t = 0;
				}
			}
			$h = floor($t / 60);
			$m = $t % 60;
			$r = strtotime($d . " $h:$m:00 +0000");
			if ($r === - 1 || $r === FALSE) {
				return FALSE;
			}
		}
		return $r;
	}

	function deleteItem($ids) {
		if (!is_array($ids) || !count($ids)) {
			return;
		}
		foreach ($ids as $k => $v) {
			$ids[$k] = intval($v);
		}
		$this->db->query('
			UPDATE ' . $this->myTable . ' SET hide=2 WHERE id IN (' . implode(',', $ids) . ')
		');
		// log this action
		blame($this->my('fullname'), 'Deleted', $ids);
		return true;
	}

	function deleteByFilter($filter) {
		$this->formFilter->fill($filter);
		$this->db->query('UPDATE ' . $this->myTable . ' SET hide=2 ' . $this->_where());
		// log this action
		blame($this->my('fullname'), 'Deleted', 'by filter');
	}

	function getRooms($id = FALSE) {
		$sql = 'SELECT id, name, currency, alias FROM ' . $this->table('Rooms');
		if ($id !== FALSE) {
			$sql .= ' WHERE is_hidden=0' . ($id ? ' OR id=' . intval($id):'');
		}
		else {
			$a = $this->db->array_query('
				SELECT DISTINCT room_id FROM ' . $this->table('SpecTournaments'));
			if (!count($a)) {
				return array();
			}
			$ids = array();
			foreach ($a as $v) {
				$ids[] = $v[0];
			}
			$sql .= ' WHERE id IN (' . implode(', ', $ids) . ')';
		}
		return $this->db->array_query_assoc($sql . ' ORDER BY name', 'id');
	}

	function getSpotlights($id = FALSE) {
		$sql = 'SELECT id, title, img FROM spotlight';
		if ($id !== FALSE) {
			$sql .= ' WHERE is_hidden=0' . ($id ? ' OR id=' . intval($id):'');
		}
		return $this->db->array_query_assoc($sql . ' ORDER BY title', 'id');
	}

	function getMasterInfo($id) {
		return $this->db->single_query_assoc('
			SELECT * FROM ' . $this->table('SpecTournamentsMaster') . ' WHERE
			id = ' . intval($id));
	}

	function countFreerollsTODO() {
		$now = ceil(time() / 100) * 100;
		// rodom tik tuos turnyrus, kuriu rooms on
		$sql = 'SELECT id, id FROM ' . $this->table('Rooms') . ' WHERE is_hidden=0';
		$ids = array_keys($this->db->array_query($sql, TRUE));
		$rooms = '';
		if (count($ids)) {
			$rooms = ' AND room_id IN (' . implode(', ', $ids) . ')';
		}
		// kiek turnyru
		$a = $this->db->single_query('
			SELECT count(*) FROM ' . $this->myTable . '
			WHERE master_id>0 AND master_updated>updated AND hide<2 AND `date`>' . $now . $rooms . '
			');
		return empty ($a[0]) ? 0:$a[0];
	}

	//***********************
	//           SYNC         /
	//**********************/
	function syncRun() {
		// Mus domins tik aktyvus kambariai
		$sql = 'SELECT id, id FROM ' . $this->table('Rooms') . ' WHERE is_hidden=0';
		$this->roomID = array_keys($this->db->array_query($sql, TRUE));
		if (empty ($this->roomID)) {
			return 'Error: there are no active rooms!';
		}
		$lastUpdate = $this->syncGetTime();
		if (callPnEvent('com', 'tour.freerolls_special#sync-export', array('timestamp' => $lastUpdate), $answer, FALSE)) {
			//randam kokie master_id atejo
			$ids = array();
			foreach ($answer AS $v) {
				$ids[] = $v['id'];
			}
			$masterExist = $this->syncFindExisting($ids);
			$this->syncNow = time();
			foreach ($answer AS $v) {
				if (empty ($masterExist[$v['id']])) {
					$this->syncUpdateItem($v, array());
				}
				else {
					$this->syncUpdateItem($v, $masterExist[$v['id']]);
				}
			}
			//pravalom senus (daugiau kaip 60 d. )

			/*$seni = time() - 86400 * 60;
			$sql = "DELETE FROM " . $this->table('SpecTournamentsMaster') . " WHERE `date` < " . $seni . " ";
			$this->db->query($sql);*/
			return ' Freerolls imported: ' . count($answer);
		}
		else {
			return 'Error!';
		}
	}

	function syncGetTime() {
		$sql = 'SELECT MAX(ABS(master_updated)) FROM ' . $this->myTable . '  WHERE date>' . time();
		$m = $this->db->single_query($sql);
		return (empty ($m[0]) ? 0:$m[0]);
	}

	function syncFindExisting($ids) {
		if (empty ($ids)) {
			return array();
		}
		$sql = "SELECT master_id,id,updated FROM " . $this->myTable . " WHERE master_id IN (" . implode(', ', $ids) . ")";
		return $this->db->array_query_assoc($sql, 'master_id');
	}

	function syncUpdateItem($arrived, $existing) {
		$fields = array('name', 'qualification_from', 'qualification_to', 'qualification_points', 'date', 'timezone', 'prizepool', 'room_id', 'hide', 'created', 'exclusive', 'password', 'password_from');
		$ins = array();
		foreach ($fields as $v) {
			$ins[$v] = $arrived[$v];
		}
		if (empty ($existing['updated'])) {
			//autopublish
			//$ins['hide'] = in_array($ins['room_id'], $this->roomID) ? $ins['hide']:2;
			$ins['hide'] = in_array($ins['room_id'], $this->roomID) ? 1:2;
			$ins['updated'] = 0;
			$ins['body'] = $arrived['body'];
		}
		elseif (!$ins['hide']) {
			unset ($ins['hide']);
			//pastaba, dabar hidden neateina vis tiek
		}
		$ins['master_id'] = $arrived['id'];
		//$ins['master_updated'] = $arrived['updated'];
		$ins['master_updated'] = $this->syncNow;
		if (empty ($existing['id'])) {
			if (empty ($arrived['nosync'])) {
				//insert
				$this->db->insert($ins, $this->myTable);
			}
		}
		else {
			//update
			if (!empty ($arrived['nosync'])) {
				$ins['hide'] = 2;
			}
			$this->db->update($ins, $this->myTable, $existing['id']);
		}
		$ins = array();
		$ins['body'] = $arrived['body'];
		$tbMaster = $this->table('SpecTournamentsMaster');
		$is = $this->db->single_query('SELECT id FROM ' . $tbMaster . ' WHERE id=' . intval($arrived['id']));
		if (empty ($is[0])) {
			$ins['id'] = $arrived['id'];
			$this->db->replace($ins, $tbMaster);
		}
		else {
			$this->db->update($ins, $tbMaster, $arrived['id']);
		}
	}

}

?>