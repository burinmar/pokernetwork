<?php
class deposits extends moon_com {

	function onload() {

		/* form of item */
		$this->form = & $this->form();
		$this->form->names('id', 'name', 'uri', 'img', 'hide', 'meta_title', 'meta_description', 'content');

		/* main table */
		$this->myTable = $this->table('Deposits');
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
				else {
					$this->set_var('error', '404');
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

			case 'hide' :
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
		$vars['pageTitle'] = $page->title();
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
		$t->save_parsed('items', array('goEdit' => $goEdit));
		//$srcLogo=$this->get_var('srcDeposit');
		foreach ($dat as $d) {
			$d['class'] = $d['hide'] ? 'item-hidden' : '';
			//$d['goUri'] = ($d['uri'] != '') ? $page->home_url() . 'editors/' . $d['uri'] : '';
			$d['title'] = htmlspecialchars($d['name']);
			$d['homepage_url'] = htmlspecialchars($d['homepage_url']);
			$d['uri'] = htmlspecialchars($d['uri']);
			if ($d['img']) {
				//$d['img'] = $srcLogo . $d['img'];
				$d['img'] = img('deposit',$d['id'],$d['img']);
			}
			$m['items'] .= $t->parse('items', $d);
		}
		$m['goHide'] = $this->my('fullname') . '#hide';
		$m['goSort'] = $this->my('fullname') . '#sort';
		$m['pageTitle'] = htmlspecialchars($vars['pageTitle']);
		$res = $t->parse('viewList', $m);
		return $res;
	}

	function viewForm($vars) {
		$t = & $this->load_template();
		$info = $t->parse_array('info');
		$page = & moon :: page();

		/******* FORM **********/
		$err = (isset ($vars['error'])) ? $vars['error'] : 0;
		if ($err == 404) {
			$page->alert($info['404']);
			$this->redirect('#');
		}
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
		$m += $f->html_values();
		// Other settings
		if ($f->get('hide') > 0) {
			$f->fill(array('hide' => 1));
		}
		$m['hide'] = $f->checked('hide', 1);
		if ($m['img']) {
			//$m['imgSrc']=$this->get_var('srcDeposit').$m['img'];
			$m['imgSrc'] = img('deposit',$m['id'],$m['img']);
		}

		if ($id) {
			$rooms = $this->linkedRooms($id);
			$m['rooms'] = '';
            $t->save_parsed('rooms',
				array('goRoom'=>$this->linkas('reviews#edit','{id}'))
			);
			foreach ($rooms as $i=>$v) {
				$m['rooms'] .= $t->parse('rooms',$v);
			}
		}
		// urlPrefix
		$sitemap = & moon :: shared('sitemap');
		$m['uriPrefix'] = $sitemap->getLink('deposits');

		/* pridedam attachmentus ir toolbara */
		if (is_object($rtf = $this->object('rtf'))) {
			$rtf->setInstance($this->get_var('rtf'));
			$m['toolbar'] = $rtf->toolbar('i_content', (int) $m['id']);
		}
		$res = $t->parse('viewForm', $m);
		return $res;
	}


	//***************************************
	//           --- DB AND OTHER ---
	//***************************************

    function getList($limit = '') {
		$sql = '
			SELECT id,name,uri,hide,img,homepage_url,has_review
			FROM ' . $this->myTable . '
			WHERE hide<2 ORDER BY sort_order ASC, name' . $limit;
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
		$d['hide'] = empty ($d['hide']) ? 0 : 1;
		//jei bus klaida
		$form->fill($d, false);

		/* validacija */
		$err = 0;
		if (!is_object($rtf = $this->object('rtf'))) {
			$err = 9;
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
		$ins = $form->get_values('hide', 'meta_title', 'meta_description', 'content');

		/* iskarpa ir kompiliuojam i html */
		$rtf->setInstance($this->get_var('rtf'));
		list(, $ins['content_html']) = $rtf->parseText($id, $ins['content'], TRUE);
		$txt = & moon :: shared('text');
		$ins['has_review'] = $ins['content'] == '' ? 0 : 1;
		//
		$ins['updated'] = time();
		$db = & $this->db();
		if ($id) {
			$db->update_query($ins, $this->myTable, array('id' => $id));
			// log this action
			blame($this->my('fullname'), 'Updated', $id);
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
			UPDATE ' . $this->myTable . ' SET hide = 1 WHERE id IN (' . implode(',', $ids) . ')
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

	function linkedRooms($depositId)
	{
        $sql = '
			SELECT id, name
			FROM ' . $this->table('DepositsRooms') . ',
				' . $this->table('Rooms') . '
			WHERE room_id=id AND deposit_id = ' . intval($depositId) . '
			ORDER BY name';
		return $this->db->array_query_assoc($sql);
	}


}
?>