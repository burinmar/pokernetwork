<?php

class promotions extends moon_com {

	function onload()
	{
		//form of item
		$this->form = & $this->form();
		$this->form->names('id', 'room_id', 'title', 'url', 'active_from', 'active_to', 'hide',  'master_id', 'master_updated', 'updated');
		$this->form->fill();

		//form of filter
		$this->formFilter = & $this->form('f2');
		$this->formFilter->names('room_id', 'text', 'hidden', 'tag');

		//main table
		$this->myTable = $this->table('PromoList');
	}


	function events($event, $par)
	{
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

			case 'deleteall' :
				$this->set_var('deleteByFilter', TRUE);
				break;

			case 'filter' :
				$filter = isset ($_POST['filter']) ? $_POST['filter'] : '';
				$this->set_var('filter', $filter);
				$this->set_var('psl', 1);
				//forget reikia kai nuimti filtra
				$this->forget();
				break;

			case 'sync-init':
				// paruoðia praneðimà, kad reikia visiems persiøsti atnaujinimà
				$s = $this->syncInit();
                $page = & moon :: page();
				$page->set_local('cron', $s);
				break;

			case 'cron':
			case 'sync-do':
				// Siurbia is master promotions
				$s = 'Ignored...';
				//if (_SITE_ID_ !== 'com') {
					$s = $this->runSync();
				//}
                $page = & moon :: page();
				$page->set_local('cron', $s);
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
		$this->use_page('Common');
	}


	function properties()
	{
		return array('psl' => 1, 'filter' => '', 'sort' => '', 'view' => 'list');
	}

	function main($vars)
	{
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
		$ord->set_values(
			array('created' => 0, 'sync'=>0), 2
			//_SITE_ID_=='com' ? 1 : 2
		);
		//gauna linkus orderby{nr}
		$m += $ord->get_links($this->linkas('#', '', array('ord' => '{pg}')), $vars['sort']);

        //kategorijos
		$f = & $this->formFilter;
		$f->fill($vars['filter']);
		$rooms = $this->getRooms();
		$selRooms = array();
		foreach ($rooms as $v) {
			$selRooms[$v['id']] = $v['name'] . ($v['is_hidden'] ? ' (hidden)' : '');
		}

		//Filtras
		$f = & $this->formFilter;
		$f->fill($vars['filter']);
		$filter = $f->get_values();
		$fm = array(
			'text' => $f->html_values('text'),
			'tag' => $f->html_values('tag'),
			'rooms' => $f->options('room_id', $selRooms),
			'hidden' => $f->checked('hidden', 1),
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
		$win = &moon::shared('admin');
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
			$today = $loc->to_days(gmdate('Y-m-d'));
			foreach ($dat as $d) {
				$d['site-slave'] = 1;//_SITE_ID_ == 'com' ? 0 : 1;
				$d['class'] = $d['hide'] ? 'item-hidden' : '';
				//$d['class'] = '';
				$d['styleTD'] = '';
				if (!empty($d['master_id'])) {
					//sync ikona
					$sType = (int)$d['master_updated']<(int)$d['updated'] ? 1 : 2;
					$d['styleTD'] = ' class="sync'.$sType.'"';
				}

				//rooms
				if ($d['room_id'] && isset ($rooms[$d['room_id']])) {
					$d['room'] =  htmlspecialchars($rooms[$d['room_id']]['name']);
				}
				else {
					$d['room'] = '';
				}

				//kita
				$d['title'] = htmlspecialchars($d['title']);
				$d['url'] = htmlspecialchars($d['url']);
				$d['created'] = $d['created'] ? $loc->datef($d['created'], 'Date') : '&nbsp;';
				$d['timer'] = '';
				if ($d['active_from'] && $d['active_from'] !== '0000-00-00') {
						$d['timer'] .= ' from ' . $d['active_from'];
				}
				if ($d['active_to'] && $d['active_to'] !== '0000-00-00') {
						$d['timer'] .= ' till ' . $d['active_to'];
                        if ($loc->to_days($d['active_to']) < $today) {
                        	$d['expired'] = 1;
						}
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
		$m['site-slave'] = 1;//_SITE_ID_ == 'com' ? 0 : 1;
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
		$err = (isset ($vars['error'])) ? $vars['error'] : 0;
		$page->css($t->parse('cssForm'));
		$f = $this->form;
		$title = $f->get('id') ? $info['titleEdit'] : $info['titleNew'];
		$page->title($title);
		$m = array(
			'error' => $err ? $info['error' . $err] : '',
			'event' => $this->my('fullname') . '#save',
			'refresh' => $page->refresh_field(),
			'id' => ($id = $f->get('id')),
			'goBack' => $this->linkas('#'),
			'pageTitle' => $vars['pageTitle'],
			'formTitle' => htmlspecialchars($title),
			'toolbar' => '',
			'hide' => $f->checked('hide', 1)
			) + $f->html_values();
		if ($f->get('hide')>0) {
			$f->fill(array('hide'=>1));
		}
		$m['hide'] = $f->checked('hide', 1);

        $rooms = $this->getRooms($m['room_id']);
		$selRooms = array();
		foreach ($rooms as $v) {
			$selRooms[$v['id']] = $v['name'];
		}
		/*if ($id && isset($rooms[$m['room_id']])) {
			$uri = $rooms[$m['room_id']]['alias'];
			$now = $locale->now;
			if ($uri && !$f->get('hide') && $datetime > $now) {
				$m['landingURL'] = '/' . $uri . '/freerolls/' . $id . '.htm';
			}
		}*/

		$m['rooms'] = $f->options('room_id', $selRooms);

		//master info
		if ($m['master_id']) {
			$m['syncStatus'] = (int)$m['updated']>(int)$m['master_updated'] ? 1 : 2;
			$a = (int)$m['updated']<0 ? 0 : $this->getMasterInfo($m['master_id']);
			if (!empty($a)) {
				$m['master_title'] = htmlspecialchars($a['title']);
				$m['master_url'] = nl2br(htmlspecialchars($a['url']));
			}
		}
		$res = $t->parse('viewForm', $m);
		//resave vars for list
		$save = array('psl' => $vars['psl'], 'sort' => $vars['sort'], 'filter' => $vars['filter']);
		$this->save_vars($save);

		return $res;
	}


	//***************************************
	//           --- DB AND OTHER ---
	//***************************************
	function getListCount()
	{
		$sql = 'SELECT count(*) FROM ' . $this->myTable . $this->_where();
		$m = $this->db->single_query($sql);
		return (count($m) ? $m[0] : 0);
	}


	function getList($limit = '', $order = '')
	{
		if ($order) {
			$order = ' ORDER BY ' . $order;
		}
		$sql = 'SELECT *, IF(master_updated>updated, 2, IF(master_id, 1 ,0)) as sync FROM ' . $this->myTable . $this->_where() . $order . $limit;
		return $this->db->array_query_assoc($sql);
	}


	function _where()
	{
		if (isset ($this->tmpWhere)) {
			return $this->tmpWhere;
		}
		$a = $this->formFilter->get_values();
		$w = array();
		//$w[] = 'hide<2';
		if ($a['text'] !== '') {
			$w[] = "title like '%" . $this->db->escape($a['text'], TRUE) . "%'";
		}
		if ($a['room_id']) {
			$w[] = "room_id=" . intval($a['room_id']);
		}
		elseif (/*_SITE_ID_ !== 'com' &&*/ !$a['hidden']) {
			// rodom tik tuos turnyrus, kuriu rooms on
			$sql = 'SELECT id, id FROM ' . $this->table('Rooms'). ' WHERE is_hidden=0';
			$ids = array_keys($this->db->array_query($sql, TRUE));
			if (count($ids)) {
				$w[] = 'room_id IN (' . implode(', ', $ids) . ')';
			}
		}
		if (!$a['hidden']) {
			$w[] = "hide<2" ;
		}
		$where = count($w) ? (' WHERE ' . implode(' AND ', $w)) : '';
		return ($this->tmpWhere = $where);
	}


	function getItem($id)
	{
		return $this->db->single_query_assoc('
			SELECT * FROM ' . $this->myTable . ' WHERE
			id = ' . intval($id)
		);
	}


	function saveItem()
	{
		$form = & $this->form;
		$form->fill($_POST);
		$d = $form->get_values();
		$id = intval($d['id']);

		//gautu duomenu apdorojimas
		$d['hide'] = empty($d['hide']) ? 0 : 1;
		$d['active_from'] = $this->makeTime($d['active_from']);
		$d['active_to'] = $this->makeTime($d['active_to']);

		//jei bus klaida
		$form->fill($d, false);

		//validacija
		$err = 0;
		if ($d['title'] === '') {
			$err = 1;
		}
		elseif (empty($d['room_id'])) {
			$err = 2;
		}

		if ($err) {
			$d['active_from'] = $_POST['active_from'];
			$d['active_to'] = $_POST['active_to'];
			$form->fill($d, false);
			$this->set_var('error', $err);
			return false;
		}
		//jei refresh, nesivarginam
		if ($wasRefresh = $form->was_refresh()) {
			return $id;
		}

		//save to database
		$ins=$form->get_values('title', 'url', 'active_from', 'active_to', 'room_id', 'hide');
		$ins['updated'] = time();

		$db = & $this->db();
		if ($id) {
			$db->update_query($ins, $this->myTable, array('id' => $id));
			blame($this->my('fullname'), 'Updated', $id);
		}
		else {
			$ins['created'] = $ins['updated'];
			$id = $db->insert_query($ins, $this->myTable, 'id');
            // log this action
			blame($this->my('fullname'), 'Created', $id);
		}
		$form->fill(array('id' => $id));

		if (_SITE_ID_ === 'com') {
			cronTask($this->my('fullname') . '#sync-init');
		}
		return $id;
	}


	function makeTime($d) {
		if ($d) {
			if (count($a = explode('-', $d)) != 3 || !checkdate($a[1], $a[2], $a[0])) {
				return NULL;
			}
			return $d;
		}
		return NULL;
	}


	function deleteItem($ids)
	{
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


	function deleteByFilter($filter)
	{
		$this->formFilter->fill($filter);
		$this->db->query('UPDATE ' . $this->myTable . ' SET hide=2 ' . $this->_where());
        // log this action
		$this->updateRoomTable();
	}


	function getRooms($id = FALSE)
	{
		$a = $this->formFilter->get_values();
		if ($id === FALSE && !empty($a['room_id'])) {
			$id = (int) $a['room_id'];
		}

		$sql = 'SELECT id, name, is_hidden FROM ' . $this->table('Rooms');
		if ($id !== FALSE || empty($a['hidden'])) {
			$sql .= ' WHERE is_hidden=0' . ($id ? ' OR id=' . intval($id) : '');
		}
		else {
			$a = $this->db->array_query('
				SELECT DISTINCT room_id FROM ' . $this->myTable
				);
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


    function getMasterInfo($id)
	{
		return $this->db->single_query_assoc('
			SELECT * FROM ' . $this->table('PromotionsMaster') . ' WHERE
			id = ' . intval($id)
		);
	}

	function countPromotionsTODO() {
		$now = ceil(time() / 100) * 100;
		// rodom tik tuos turnyrus, kuriu rooms on
		$sql = 'SELECT id, id FROM ' . $this->table('Rooms'). ' WHERE is_hidden=0';
		$ids = array_keys($this->db->array_query($sql, TRUE));
		$rooms = '';
		if (count($ids)) {
				$rooms = ' AND room_id IN (' . implode(', ', $ids) . ')';
		}
		// kiek turnyru
		$a = $this->db->single_query('
            SELECT count(*) FROM ' . $this->myTable . '
			WHERE master_id>0 AND master_updated>updated AND hide<2' . $rooms . '
			');
		return empty($a[0]) ? 0 : $a[0];
	}


	//***************************************
	//           --- SYNC ---
	//***************************************

	function syncInit() {
		return;
		//siurbsim is pokernews
		$sites = array('bg', 'de', 'ee', 'it', 'nl', 'no', 'se', 'si','china', 'dk', 'gr', 'il', 'es', 'asia', 'lt', 'cz', 'tr', 'fr','pt', 'pl', 'ru', 'uk', 'fi', 'hu', 'ro', 'br', 'kr', 'jp', 'lv', 'balkan', 'in');
		$s = '';
		if (_SITE_ID_ === 'com') {
			foreach ($sites as $id) {
	        	$ok = callPnEvent($id, 'reviews.promotions#sync-activate', FALSE, $answer,FALSE);
				$s .= $id . ' : ' . ($ok ? 'ok' : '<span style="color:red">Error</span>') . "<br/>\n";
			}
		}
        return $s;
	}


	function runSync() {
		if (_SITE_ID_ == 'fr') {
			return 'Disabled in FR';
		}
		$this->findActiveRooms();
		if (empty($this->roomID)) {
			return 'Error: there are no active rooms!';
		}
		$lastUpdate = $this->get_max_updated_timestamp();
		$serverID = _SITE_ID_ == 'com' ? 'pn:com' : 'com';
		if (callPwEvent($serverID, 'reviews.promotions#sync-export', array('timestamp' => $lastUpdate), $answer,FALSE)) {
			//randam kokie master_id atejo
			$ids = array();
			foreach ($answer AS $v) {
				$ids[] = $v['id'];
			}
			$masterExist = $this->check_master_exist($ids);
			$locale = & moon :: locale();
			$today = $locale->to_days(gmdate('Y-m-d'));
			$expired = 0;
			foreach ($answer AS $v) {
				if (empty ($masterExist[$v['id']])) {
					if ($v['active_to'] && $v['active_to'] !== '0000-00-00') {
                        if ($locale->to_days($v['active_to']) < $today) {
                        	$expired++;
							continue;
						}
					}
					$this->update_tournament($v, array());
				}
				else {
					$this->update_tournament($v, $masterExist[$v['id']]);
				}
			}
			return ' Promotions imported: ' . count($answer) . ($expired ? ', ignored: ' . $expired : '');
		}
		else {
			return 'Error!';
		}

	}

	//***********************
	//         SYNC  DB     /
	//**********************/

	function get_max_updated_timestamp() {
		$sql = 'SELECT MAX(ABS(master_updated)) FROM ' . $this->myTable;
		$m = $this->db->single_query($sql);
		return (empty ($m[0]) ? 0 : $m[0]);
	}

	function findActiveRooms() {
		$sql = 'SELECT id, id FROM ' . $this->table('Rooms'). ' WHERE is_hidden=0';
		$this->roomID  = array_keys($this->db->array_query($sql, TRUE));
	}


	function check_master_exist($ids) {
		if (empty ($ids)) {
			return array();
		}
		$sql = "SELECT master_id,id,updated FROM " . $this->myTable . " WHERE master_id IN (" . implode(', ', $ids) . ")";
		$m = $this->db->array_query_assoc($sql, 'master_id');
		return $m;
	}


	function update_tournament($tournament, $exist) {
		$fields = array( 'active_from', 'active_to', 'room_id', 'hide', 'created');
		$ins = array();
		foreach ($fields as $v) {
			$ins[$v] = $tournament[$v];
		}
		if (empty($exist['updated'])) {
			//update body too
			$ins['hide'] = in_array($ins['room_id'], $this->roomID) ? 1 : 2;
			$ins['updated'] = 0;
			$ins['title'] = $tournament['title'];
			$ins['url'] = $tournament['url'];
		}
		$ins['master_id'] = $tournament['id'];
		$ins['master_updated'] = $tournament['updated'];
		if (empty ($exist['id'])) {
			//insert
			$this->db->insert($ins, $this->myTable);
		}
		else {
			//update
			$this->db->update($ins, $this->myTable, $exist['id']);
		}
		$ins = array();
		$ins['id'] = $tournament['id'];
		$ins['title'] = $tournament['title'];
		$ins['url'] = $tournament['url'];
		$this->db->replace($ins, $this->table('PromotionsMaster'));
	}

}

?>