<?php

class users extends moon_com {

	function onload() {
		$this->compA = $this->my('name') == 'admins';

		/* form of item */
		$this->form = & $this->form();
		$this->form->names('id', 'nick', 'password', 'email', 'name', 'timezone', 'status', 'access');
		$this->form->fill(array('status' => 1));

		/* form of filter */
		$this->formFilter = & $this->form('hidden', 'text', 'kur');
		$this->formFilterA = & $this->form('key');

		/* main table */
		$this->myTable = $this->table('Users');

		$this->dbvb = & moon::db('database-vb');
		$this->myTable = 'vb_user';
	}

	function events($event, $par) {
		$this->use_page('Common');
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

			case 'filter':
				$filter = isset ($_POST['filter']) ? $_POST['filter']:'';
				if ($this->compA) {
					$this->set_var('filterA', $filter);
				}
				else {
					$this->set_var('filter', $filter);
					$this->set_var('psl', 1);
				}
				//forget reikia kai nuimti filtra
				$this->forget();
				break;

			default:
				if (isset ($_GET['ord'])) {
					if ($this->compA) {
						$this->set_var('sortA', (int) $_GET['ord']);
					}
					else {
						$this->set_var('sort', (int) $_GET['ord']);
						$this->set_var('psl', 1);
					}
					$this->forget();
				}
				if (isset ($_GET['page'])) {
					$this->set_var('psl', (int) $_GET['page']);
				}
		}
	}

	function properties() {
		return array('psl' => 1, 'filter' => '', 'filterA' => '', 'sort' => '', 'sortA' => '', 'view' => 'list');
	}

	function main($vars) {
		$win = & moon :: shared('admin');
		$win->active($this->my('fullname'));
		$page = & moon :: page();
		$vars['pageTitle'] = $win->getTitle();
		if ($vars['view'] == 'form') {
			return $this->viewForm($vars);
		}
		elseif ($this->my('name') == 'admins') {
			return $this->viewAdmins($vars);
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
		$ord->set_values(array('nick' => 1, 'email' => 1, 'login_date' => 0, 'created' => 0), 4);
		//gauna linkus orderby{nr}
		$m += $ord->get_links($this->linkas('#', '', array('ord' => '{pg}')), $vars['sort']);

		/* Filtras */
		$f = & $this->formFilter;
		$f->fill($vars['filter']);
		$filter = $f->get_values();
		$selKur = array('nick' => 'Nick', 'email' => 'E-mail');
		$fm = array();
		$fm['text'] = $f->html_values('text');
		$fm['kur'] = $f->options('kur', $selKur);
		$fm['hidden'] = $f->checked('hidden', 1);
		$fm['goFilter'] = $this->my('fullname') . '#filter';
		$fm['noFilter'] = $this->linkas('#filter');
		$fm['isOn'] = '';
		foreach ($filter as $k => $v) {
			if ($k != 'kur' && $v) {
				$fm['isOn'] = 1;
				break;
			}
		}
		$fm['classIsOn'] = $fm['isOn'] ? ' filter-on':'';
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
				$d['class'] = $d['usergroupid'] != 2 && $d['usergroupid'] < 5 ? 'item-hidden':'';
				$d['admin'] = FALSE && /*$d['status'] == 1 &&*/ $d['access'] ? 1:0;
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
		$m['pageTitle'] = htmlspecialchars($vars['pageTitle']);
		$res = $t->parse('viewList', $m);
		$save = array('psl' => $vars['psl'], 'sort' => (int) $vars['sort'], 'filterA' => $vars['filterA'], 'sortA' => (int) $vars['sortA']);
		foreach ($filter as $k => $v) {
			if ($v !== '') {
				$save['filter'] = $filter;
				break;
			}
		}
		$this->save_vars($save);
		return $res;
	}

	function viewAdmins($vars) {
		$t = & $this->load_template();
		$page = & moon :: page();

		/******* LIST **********/
		$m = array('items' => '');
		$pn = & moon :: shared('paginate');

		/* rusiavimui */
		$ord = & $pn->ordering();
		$ord->set_values(array('nick' => 1, 'created' => 0), 1);
		//gauna linkus orderby{nr}
		$m += $ord->get_links($this->linkas('#', '', array('ord' => '{pg}')), $vars['sortA']);

		/* Filtras */
		$a = $this->getPermissionGroups();
		$agroups = array();
		foreach ($a as $k => $v) {
			if ($k) {
				$agroups['@' . $k] = strtoupper($k);
			}
		}
		$agroups['@custom'] = 'Custom permissions';
		$keys = array_combine($a['administrator'], $a['administrator']);
		//
		$f = & $this->formFilterA;
		$f->fill($vars['filterA']);
		$filter = $f->get_values();
		$fm = array();
		$fm['agroups'] = $f->options('key', $agroups);
		$fm['permissions'] = $f->options('key', $keys);
		$fm['goFilter'] = $this->my('fullname') . '#filter';
		$fm['noFilter'] = $this->linkas('#filter');
		$fm['isOn'] = '';
		foreach ($filter as $k => $v) {
			if ($v) {
				$fm['isOn'] = 1;
				break;
			}
		}
		$fm['classIsOn'] = $fm['isOn'] ? ' filter-on':'';
		$m['filtrasA'] = $t->parse('filtrasA', $fm);

		/* generuojam sarasa */
		if (count($dat = $this->getAdmins($ord->sql_order()))) {

			/* sarasas */
			$goEdit = $this->linkas('#edit', '{id}');
			$t->save_parsed('itemsA', array('goEdit' => $goEdit));
			$loc = & moon :: locale();
			$txt = & moon :: shared('text');
			// kas as
			$user = & moon :: user();
			$iDeveloper = $user->i_admin('developer');
			$sitemap = & moon :: shared('sitemap');
			foreach ($dat as $d) {
				$d['class'] = '';
				$d['name'] = htmlspecialchars($d['name']);
				$d['nick'] = htmlspecialchars($d['nick']);
				$d['created'] = $loc->datef($d['created']);
				if ($d['access']) {
					$d['agroup'] = $d['access'];
					$pos = strpos($d['agroup'], '@');
					if ($pos !== FALSE) {
						$d['agroup'] = substr($d['agroup'], $pos);
						list($d['agroup']) = explode(',', $d['agroup']);
						$d['agroup'] = strtoupper(ltrim($d['agroup'], '@'));
						if (!$iDeveloper && $d['agroup'] === 'DEVELOPER') {
							// tik developeriams rodom developerius
							continue;
						}
					}
					else {
						$d['agroup'] = $txt->excerpt($d['agroup'], 40);
					}
				}
				else {
					$d['agroup'] = '<i style="color:red">unknown</i>';
				}
				$m['items'] .= $t->parse('itemsA', $d);
			}
		}
		else {
			//filtras nerodomas kai tuscias sarasas
			if (!$fm['isOn']) {
				//	$m['filtras'] = '';
			}
		}
		$m['pageTitle'] = htmlspecialchars($vars['pageTitle']);
		$res = $t->parse('viewListAdmins', $m);
		$save = array('psl' => $vars['psl'], 'sort' => (int) $vars['sort'], 'filter' => $vars['filter'], 'sortA' => (int) $vars['sortA']);
		foreach ($filter as $k => $v) {
			if ($v !== '') {
				$save['filterA'] = $filter;
				break;
			}
		}
		$this->save_vars($save);
		return $res;
	}

	function viewForm($vars) {
		$tpl = & $this->load_template();
		$info = $tpl->parse_array('info');
		$page = & moon :: page();

		/******* FORM **********/
		$err = (isset ($vars['error'])) ? $vars['error']:0;
		$f = $this->form;
		$title = $f->get('id') ? $info['titleEdit']:$info['titleNew'];
		$page->title($title);

		/* main settings */
		$m = array();
		$m['error'] = $err ? $info['error' . $err]:'';
		$m['event'] = $this->my('fullname') . '#save';
		$m['refresh'] = $page->refresh_field();
		$m['id'] = ($id = $f->get('id'));
		$m['goBack'] = $this->linkas('#');
		$m['pageTitle'] = $vars['pageTitle'];
		$m['formTitle'] = htmlspecialchars($title);
		$m += $f->html_values();

		/* Other settings */
		$m['password'] = '';
		$statuses = array(1 => 'Active', 0 => 'Not Active', - 1 => 'Banned');
		$m['status'] = $f->options('status', $statuses);
		//$i = $f->get('status');
		//$m['status'] = isset($statuses[$i]) ? $statuses[$i] : $i;

		/* permissions */
		$permArr = $this->getPermissionGroups();
		$roles = array();
		$m['jsRolesArray'] = '';
		foreach ($permArr as $k => $v) {
			if ($k) {
				$roles[$k] = strtoupper($k);
				$m['jsRolesArray'] .= 'rolesArr["' . $k . '"]= new Array("' . implode('","', $v) . '");' . "\n";
			}
		}
		$roles[- 1] = 'Custom permissions';
		$u = & moon :: user();
		if (!$u->i_admin('developer')) {
			unset ($roles['developer']);
		}
		$userID = $id;
		$keys = $this->form->get('access');
		$keys = $keys ? explode(',', $keys):array();
		///*********************
		$allKeys = $this->getPermissionGroups('');
		$sKeys = '';
		$d = array();
		foreach ($allKeys as $kID) {
			$d['key'] = $kID;
			$d['kName'] = htmlspecialchars($kID);
			if (in_array($kID, $keys)) {
				$d['key'] .= '" checked="checked';
			}
			$sKeys .= $tpl->parse('keys', $d);
		}
		$myRole = isset ($keys[0]) ? $keys[0]:'';
		if ($myRole !== '') {
			$myRole = ($myRole[0] === '@') ? ltrim($myRole, '@'):- 1;
			if ($myRole !== - 1 && !isset ($roles[$myRole])) {
				$myRole = - 1;
			}
		}
		$f = $this->form('role');
		$f->fill(array('role' => $myRole));
		$roles = $f->options('role', $roles);
		$m['keys'] = $sKeys;
		$m['roles'] = $roles;
		$m['classRowHide'] = $myRole !== '' ? '':' class="empty"';
		$m['classHideCustom'] = $myRole == - 1 ? '':' class="hide"';
		$m['classHide'] = $myRole != - 1 ? '':' class="hide"';
		$m['predefined'] = $myRole != '' && $myRole != - 1 ? implode(', ', $this->getPermissionGroups($myRole)):'';
		$res = $tpl->parse('viewForm', $m);

		/* dabar paskutiniai IP */
		$m = array('ipitems' => '');
		if (count($dat = $this->getUsedIP($id))) {
			foreach ($dat as $d) {
				$m['ipitems'] .= $tpl->parse('ipitems', $d);
			}
			$res .= $tpl->parse('usedIP', $m);
		}

		/* resave vars for list */
		$save = array('psl' => $vars['psl'], 'sort' => $vars['sort'], 'filter' => $vars['filter']);
		$this->save_vars($save);
		return $res;
	}

	//***************************************
	//           --- DB AND OTHER ---
	//***************************************
	function getAdmins($order = '') {
		if ($order) {
			$order = ' ORDER BY ' . $order;
		}
		// where
		$a = $this->formFilterA->get_values();
		$w = array();
		if (!empty ($a['key'])) {
			$v = $this->db->escape($a['key']);
			if ($v[0] === '@') {
				if ($v === '@custom') {
					$w[] = "access NOT LIKE '@%'";
				}
				else {
					$w[] = "FIND_IN_SET('$v', access)";
				}
			}
			else {
				$groups = $this->getPermissionGroups(FALSE, $v);
				$s = array("FIND_IN_SET('$v', access)");
				foreach ($groups as $vv) {
					$s[] = "FIND_IN_SET('$vv', access)";
				}
				$w[] = '(' . implode(' OR ', $s) . ')';
			}
		}
		$w[] = "access<>''";
		$sql = 'SELECT id, access FROM ' . $this->table('Users') . ' WHERE ' . implode(' AND ', $w);
		$access = $this->db->array_query($sql, TRUE);
		$ids = array_keys($access);
		$m = array();
		if (count($ids)) {
		//sql
			$sql = '
				SELECT userid as id, username as nick, `password`, email, `usertitle` as name, lastvisit as login_date, joindate as created, usergroupid
				FROM ' . $this->myTable . ' WHERE userid IN ('.implode(',', $ids).')' . $order . ' limit 1000';
			$m = $this->dbvb->array_query_assoc($sql);
			foreach ($m as $k => $v) {
				$m[$k]['access'] = isset($access[$v['id']]) ? $access[$v['id']] : '';
			}
		}
		return $m;
	}

	function getListCount() {
		$sql = 'SELECT count(*) FROM ' . $this->myTable . $this->_where();
		$m = $this->dbvb->single_query($sql);
		return (count($m) ? $m[0]:0);
	}

	function getList($limit = '', $order = '') {
		if ($order) {
			$order = ' ORDER BY ' . $order;
		}
		$sql = '
			SELECT userid as id, username as nick, `password`, email, `usertitle` as name, lastvisit as login_date, joindate as created, usergroupid
			FROM ' . $this->myTable . $this->_where() . $order . $limit;
		return $this->dbvb->array_query_assoc($sql);
	}

	function _where() {
		if (isset ($this->tmpWhere)) {
			return $this->tmpWhere;
		}
		$a = $this->formFilter->get_values();
		$w = array();
		//$w[] = "login_date<>'0000-00-00'";
		/*if (empty ($a['hidden'])) {
			$w[] = "status<>0";
		}*/
		if ($a['text'] !== '') {
			$find = (strlen($a['text']) > 2 ? '%':'') . $this->db->escape($a['text'], TRUE);
			switch ($a['kur']) {

				case 'email':
					$w[] = $a['kur'] . " like '$find%'";
					break;
				case 'nick':
					$w[] = "username like '$find%'";
					break;

				default:
			}
		}
		$where = count($w) ? (' WHERE ' . implode(' AND ', $w)):'';
		return ($this->tmpWhere = $where);
	}

	function getItem($id) {
		$m = $this->dbvb->single_query_assoc('
			SELECT userid as id, username as nick, `password`, email, `usertitle` as name, lastvisit as login_date, joindate as created, usergroupid
			FROM ' . $this->myTable . ' WHERE
			userid = ' . intval($id));
		if (count($m)) {
			$sql = 'SELECT access FROM ' . $this->table('Users') . ' WHERE id = ' . intval($id);
			$a = $this->db->single_query($sql);
			$m['access'] = empty($a[0]) ? '' : $a[0];
		}
		return $m;
	}

	function saveItem() {
		$form = & $this->form;
		$form->fill($_POST);
		$d = $form->get_values();
		$id = intval($d['id']);

		/* gautu duomenu apdorojimas */
		$d['timezone'] = 0;
		//permissions
		$permissions = isset ($_POST['keys']) ? $_POST['keys']:array();
		$role = $_POST['role'];
		$ins = array('user_id' => $id);
		if ($role == '-1') {
			// Custom
			$d['access'] = implode(',', $permissions);
		}
		elseif ($role) {
			$d['access'] = '@' . $role;
		}
		//jei bus klaida
		$form->fill($d, false);



		/* validacija */
		$err = 0;
		$ilg = strlen($d['nick']);
		if ($d['nick'] === '') {
			$err = 1;
		}
		elseif ($d['email'] === '') {
			$err = 2;
		}
		elseif ($ilg < 1 || $ilg > 100) {
			//|| preg_match('/[^a-z0-9_.-]/i', $d['nick'], $rMas)
			$err = 3;
		}
		elseif (!moon :: mail()->is_email($d['email'])) {
			$err = 4;
		}
		else {
			//check for duplicates
			$sql = "SELECT SUM( IF(username='" . $this->db->escape($d['nick']) . "',100,1) )
			FROM " . $this->myTable . "
			WHERE (username='" . $this->db->escape($d['nick']) . "' OR email='" . $this->db->escape($d['email']) . "') AND userid<>" . $id;
			if (count($a = $this->dbvb->single_query($sql)) && $a[0]) {
				$err = $a[0] > 99 ? 6:5;
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
		//$was = $this->getItem($id);

		/* save to database */
		$ins = $form->get_values('email');
		$ins['username'] = $d['nick'];
		if (!$id || $d['password'] !== '') {
			$ins['salt'] = uniqid('');
			$ins['password'] = md5(md5($d['password']) .  $ins['salt']);
			$ins['passworddate'] = gmdate('Y-m-d');
		}
		//
		$db = & $this->dbvb;
		if ($id) {
			$db->update_query($ins, $this->myTable, array('userid' => $id));
			// log this action
			blame($this->my('fullname'), 'Updated', $id);
		}
		else {
			$ins['usergroupid'] = '2';
			$ins['usertitle'] = 'PNW Novice';
			$ins['joindate'] = time();
			$ins['reputationlevelid'] = '5';
			$ins['options'] = '45108311';
			$id = $db->insert_query($ins, $this->myTable, 'userid');
			// log this action
			blame($this->my('fullname'), 'Created', $id);
		}

		/* save to database access */
		$ins = $form->get_values('access');
		if ($id) {
			$ins['id'] = $id;
			$this->db->replace($ins, $this->table('Users'), array('id' => $id));
		}
		$form->fill(array('id' => $id));
		return $id;
	}

	/****************************************
	/           --- OTHER ---
	/***************************************/
	function getUsedIP($userID) {
		if ($userID) {
			$sql = "SELECT ip,created FROM " . $this->table('UsedIP') . "
			WHERE user_id = '" . intval($userID) . "'
			ORDER BY created DESC LIMIT 50";
			$m = $this->db->array_query_assoc($sql);
		}
		else {
			$m = array();
		}
		return $m;
	}

	function getPermissionGroups($group = false, $perm = false) {
		$oLogin = $this->object('login_object');
		return $oLogin->getPermissionGroups($group, $perm);
	}

}

?>