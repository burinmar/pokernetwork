<?php


class admins extends moon_com {


	function onload() {
		//paruosiam formas
		$this->form = & $this->form();
		//redagavimo forma
		$this->form->names('id', 'nick', 'name', 'email', 'keys', 'role');
		//filtras
		$this->formFilter = & $this->form('key');
		//search
		$this->formSearch = & $this->form('nick');
	}


	function events($event, $par) {
		$this->use_page('Common');
		switch ($event) {

			case 'edit' :
				$id = isset ($par[0]) ? intval($par[0]) : 0;
				if ($id) {
					if (count($values = $this->getItem($id))) {
						$this->form->fill($values);
						$this->set_var('view', 'form');
						break;
					}
					else {
						$this->set_var('error', '404');
					}
				}
				$this->set_var('view', 'search');
				break;

			case 'save' :
				if ($id = $this->saveItem()) {
					if (isset ($_POST['return'])) {
						$this->redirect('#edit', $id);
					}
				}
				$this->redirect('#');
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

			case 'search' :
				$this->formSearch->fill($_POST);
				//forget reikia kai nuimti filtra
				$this->forget();
				$this->set_var('view', 'search');
				break;

			case 'permissions' :
				$id = isset ($par[0]) ? intval($par[0]) : 0;
				if ($id) {
					if (count($values = $this->getItem($id))) {
						$this->form->fill($values);
					}
					else {
						$page = & moon :: page();
						$page->alert('Item not found!');
						$this->redirect('#');
					}
					$this->set_var('view', 'permissions');
				}
				break;

			case 'save-permissions' :
				if ($id = $this->savePermissions()) {
					if (isset ($_POST['return'])) {
						$this->redirect('#permissions', $id);
					}
					else {
						$this->redirect('#');
					}
				}
				$this->redirect('#');
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
		return array('filter' => '', 'view' => 'list');
	}


	function main($vars) {
		$win = & moon :: shared('admin');
		$win->active($this->my('fullname'));
		//$win->submenu();
		$vars['pageTitle'] = $win->current_info('title');
		if ($vars['view'] == 'form') {
			unset ($vars['view']);
			$this->save_vars($vars);
			return $this->viewForm($vars);
		}
		elseif ($vars['view'] == 'search') {
			return $this->viewSearch($vars);
		}
		else {
			return $this->viewList($vars);
		}
	}


	//***************************************
	//           --- DB ---
	//***************************************
	function viewList($vars) {
		$t = & $this->load_template();
		$page = & moon :: page();

		/******* LIST **********/
		$m = array('items' => '');
		$pn = & moon :: shared('paginate');

		/* rusiavimui */
		$ord = & $pn->ordering();
		$ord->set_values(array('nick' => 1, 'created' => 0), 1);
		//gauna linkus orderby{nr}
		$m += $ord->get_links($this->linkas('#', '', array('ord' => '{pg}')), $vars['sort']);

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
		$f = & $this->formFilter;
		$f->fill($vars['filter']);
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
		$fm['classIsOn'] = $fm['isOn'] ? ' filter-on' : '';
		$m['filtras'] = $t->parse('filtras', $fm);

		/* generuojam sarasa */
		if (count($dat = $this->getList($ord->sql_order()))) {

			/* sarasas */
			$goEdit = $this->linkas('#edit', '{id}');
			$t->save_parsed('items', array('goEdit' => $goEdit));
		$loc = & moon :: locale();
			$txt = & moon :: shared('text');
		// kas as
		$user = & moon :: user();
		$iDeveloper = $user->i_admin('developer');
		$sitemap = & moon :: shared('sitemap');
		$urlUsers = $sitemap->getLink('users');
			foreach ($dat as $d) {
				$d['class'] = '';
			$d['url.profile'] =$urlUsers. urlencode($d['nick']) . '/';
			//$d['goUri'] = ($d['uri'] != '') ? $page->home_url() . 'editors/' . $d['uri'] : '';
			$d['name'] = htmlspecialchars($d['name']);
				$d['nick'] = htmlspecialchars($d['nick']);
			$d['created'] = $loc->datef($d['created']);
			if ($d['access']) {
				$d['agroup'] = $d['access'];
				$pos = strpos($d['agroup'], '@');
				if ( $pos !== FALSE) {
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
			//$d['created'] = $loc->datef($d['created'], 'Date');
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
		$save = array('sort' => (int) $vars['sort']);
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
		$tpl = & $this->load_template();
		$m = array();
		$m['servers'] = '';
		$usr = $this->form->html_values();
		$m['nick'] = $usr['nick'];
		$m['id'] = $usr['id'];
		$m['email'] = $usr['email'];
		$m['name'] = $usr['name'];
		if (!$usr['id']) {
			// userio nera
			$info = $tpl->parse_array('info');
			$page = & moon :: page();
			$page->alert($info['404']);
			$this->redirect('#');
		}
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
		$userID = $usr['id'];
		$keys = $this->form->get('access');
		$keys = $keys ? explode(',',$keys) : array();
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
		$myRole = isset ($keys[0]) ? $keys[0] : '';
		if ($myRole !== '') {
			$myRole = ($myRole[0] === '@') ? ltrim($myRole, '@') : - 1;
			if ($myRole !== - 1 && !isset ($roles[$myRole])) {
				$myRole = - 1;
			}
		}
		$f = $this->form('role');
		$f->fill(array('role' => $myRole));
		$roles = $f->options('role', $roles);
		$m['keys'] = $sKeys;
		$m['roles'] = $roles;
		$m['classRowHide'] = $myRole !== '' ? '' : ' class="empty"';
		$m['classHideCustom'] = $myRole == - 1 ? '' : ' class="hide"';
		$m['classHide'] = $myRole != - 1 ? '' : ' class="hide"';
		$m['predefined'] = $myRole != - 1 ? implode(', ', $this->getPermissionGroups($myRole)) : '';
		///*********************
		$m['event'] = $this->my('fullname') . '#save';
		$m['goBack'] = $this->linkas('#');
		$page = & moon :: page();
		$m['refresh'] = $page->refresh_field();
		$res = $tpl->parse('viewPermissions', $m);
		return $res;
	}


	function viewSearch($vars) {
		$t = & $this->load_template();
		unset ($vars['view']);
		$this->save_vars($vars);

		/******* LIST **********/
		$m = array('items' => '');

		/* Filtras */
		$f = & $this->formSearch;
		$f->fill();
		$filter = $f->get_values();
		$fm = array();
		$m['nick'] = htmlspecialchars($nick = $filter['nick']);
		$m['goFilter'] = $this->my('fullname') . '#search';
		$m['noFilter'] = $this->linkas('#filter');
		$m['isOn'] = '';
		foreach ($filter as $k => $v) {
			if ($v) {
				$m['isOn'] = 1;
				break;
			}
		}
		$m['classIsOn'] = $m['isOn'] ? ' filter-on' : '';

		/* generuojam sarasa */
		if ($m['isOn']) {
			if (count($dat = $this->getSearch($nick))) {
				if ($dat[0]['nick'] === $nick) {
					$this->redirect('#edit', $dat[0]['id']);
				}

				/* sarasas */
				$goEdit = $this->linkas('#edit', '{id}');
				$t->save_parsed('items-search', array('goEdit' => $goEdit));
				$txt = & moon :: shared('text');
				foreach ($dat as $d) {
					$d['class'] = '';
					$d['styleTD'] = '';
					//kita
					$d['nick'] = htmlspecialchars($d['nick']);
					$d['email'] = htmlspecialchars($d['email']);
					$m['items'] .= $t->parse('items-search', $d);
				}
			}
		}
		$m['goBack'] = $this->linkas('#');
		$m['pageTitle'] = htmlspecialchars($vars['pageTitle']);
		$res = $t->parse('viewSearch', $m);
		return $res;
	}


	//***************************************
	//           --- DB ---
	//***************************************
	function getPermissionGroups($group=false, $perm=false) {
		$oLogin = $this->object('login_object');
		return $oLogin->getPermissionGroups($group,$perm);
	}


	function getList($order = '') {
		if ($order) {
			$order = ' ORDER BY ' . $order;
		}
		$sql = '
			SELECT id, nick, email, name, created, login_date, avatar, status, access
			FROM ' . $this->table('Users') . $this->_where() . $order . ' limit 1000';
		return $this->db->array_query_assoc($sql);
	}


	function _where() {
		$a = $this->formFilter->get_values();
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
		$where = count($w) ? (' WHERE ' . implode(' AND ', $w)) : '';
		return ($this->tmpWhere = $where);
	}


	//***** Formai
	function getItem($id) {
		$db = & $this->db();
		$sql = "SELECT * FROM " . $this->table('Users') . " WHERE id=" . intval($id);
		$m = $db->single_query_assoc($sql);
		return $m;
	}


	function saveItem() {
		$id = intval($_POST['id']);
		$permissions = isset ($_POST['keys']) ? $_POST['keys'] : array();
		$role = $_POST['role'];
		$db = & $this->db();
		$db->query('DELETE FROM ' . $this->table('Access') . ' WHERE user_id=' . $id);
		$ins = array('user_id' => $id);
		if ($role == '-1') {
			// Custom
			foreach ($permissions as $zone) {
				$ins['key'] = $zone;
				$db->insert($ins, $this->table('Access'));
			}
		}
		elseif ($role) {
			$ins['key'] = '@' . $role;
			$db->insert($ins, $this->table('Access'));
		}
		blame($this->my('fullname'), 'Updated', $id);
		return $id;
	}


	function getAdmins() {
		//$sql = 'SELECT DISTINCT user_id,`key` FROM ' . $this->table('Access') . $this->_where();
		$sql = 'SELECT DISTINCT user_id, GROUP_CONCAT(DISTINCT `key` SEPARATOR \', \') FROM ' . $this->table('Access') . $this->_where() . ' GROUP BY user_id';
		return $this->db->array_query($sql, TRUE);
	}


	function getSearch($nick) {
		return $this->db->array_query_assoc('
			SELECT * FROM ' . $this->table('Users') . "
			WHERE nick like '" . $this->db->escape($nick, TRUE) . "%'
			ORDER BY length(nick), nick
			LIMIT 50
			");
	}


}

?>