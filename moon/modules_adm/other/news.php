<?php


class news extends moon_com {


	function onload() {

		/* form of item */
		$this->form = & $this->form();
		$this->form->names('id', 'hide', 'date', 'time', 'uri', 'title', 'meta_description', 'category', 'authors', 'summary', 'content', 'recompile', 'tags', 'img', 'img_alt');
		$this->form->fill(array('date' => time()));

		/* form of filter */
		$this->formFilter = & $this->form('f2');
		$this->formFilter->names('hidden', 'text', 'tag');

		/* main table */
		$this->myTable = $this->table('News');
		$this->imgWH = '490x350';
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

			case 'deleteall' :
				$this->deleteByFilter();
				$this->redirect('#');
				break;

			case 'filter' :
				$filter = isset ($_POST['filter']) ? $_POST['filter'] : '';
				$this->set_var('filter', $filter);
				$this->set_var('psl', 1);
				//forget reikia kai nuimti filtra
				$this->forget();
				break;

			case 'imgtool' :
				if (is_object($tool = & moon :: shared('imgtool'))) {
					$id = empty ($par[0]) ? 0 : $par[0];
					if (isset ($_POST['id'])) {
						//cia img apdorojimas
						$this->imgReplace($_POST['id']);
						$tool->close();
					}
					$m = $this->getItem($id);
					$src = $this->get_var('srcImg') . substr_replace($m['img'], '_orig', 13, 0);
					$tool->show(array('id' => $id, 'src' => $src, 'minWH' => $this->imgWH, 'fixedProportions' => TRUE));
				}
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

		/******* LIST **********/
		$m = array('items' => '');
		$pn = & moon :: shared('paginate');

		/* rusiavimui */
		$ord = & $pn->ordering();
		//laukai, ir ju defaultine kryptis
		//antras parametras kuris lauko numeris defaultinis.
		$ord->set_values(array('date' => 0, 'created' => 0), 1);
		//gauna linkus orderby{nr}
		$m += $ord->get_links($this->linkas('#', '', array('ord' => '{pg}')), $vars['sort']);


		/* Filtras */

		/*$rooms = $this->getRooms();
		$selRooms = array();
		foreach ($rooms as $v) {
		$selRooms[$v['id']] = $v['name'];
		} */
		$f = & $this->formFilter;
		$f->fill($vars['filter']);
		$filter = $f->get_values();
		$fm = array();
		$fm['text'] = $f->html_values('text');
		$fm['tag'] = $f->html_values('tag');
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
			foreach ($dat as $d) {
				$d['class'] =

				/*$d['date'] < $now ||*/
				$d['hide'] ? 'item-hidden' : '';
				//$d['class'] = '';
				$d['styleTD'] = $d['date'] >= $now ? ' class="item-hidden"' : '';

				/*if (!empty($d['master_id'])) {
				$sType = (int)$d['master_updated']<(int)$d['updated'] ? 1 : 2;
				$d['styleSync'] = ' style="background: url({!_AIMG_}sync'.$sType.'.png) right 2px no-repeat;background-color:inherit;"';
				}*/

				//kita
				$d['title'] = htmlspecialchars($d['title']);
				$d['date'] = $locale->datef($d['date'], 'DateTime');
				$d['tags'] = htmlspecialchars(str_replace(',', ', ', $d['tags']));
				$d['created'] = $locale->datef($d['created'], 'Date');
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

		/******* FORM **********/
		$err = (isset ($vars['error'])) ? $vars['error'] : 0;
		$f = $this->form;
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
		$m['hide'] = $f->checked('hide', 1);
		$m += $f->html_values();
		// Other settings
		if ($f->get('hide') > 0) {
			$f->fill(array('hide' => 1));
		}
		$m['hide'] = $f->checked('hide', 1);
		$sitemap = & moon :: shared('sitemap');
		$m['uriPrefix'] = $sitemap->getLink('news');
		// date and time
		//$timestamp = strtotime($m['date']);
		$timestamp = $m['date'];
		$m['date'] = date('Y-m-d', $timestamp);
		$m['time'] = date('H:i', $timestamp);
		$m['imgAllowed'] = $this->get_var('articleHasImg');
		$m['imgAllowed'] = 1;
		$m['imgWH'] = $this->imgWH;

		/* paveiksliukas */
		if ($m['img']) {
			$m['imgSrc'] = $this->get_var('srcImg') . $m['img'];
			$m['imgSrcThumb'] = $this->get_var('srcImg') . substr_replace($m['img'], '_', 13, 0);
			$m['imgTool'] = $this->linkas('#imgtool', $id);
			$m['selfUrl'] = $this->linkas('#edit', $id);
		}

        /* pridedam attachmentus ir toolbara */
		if (is_object($rtf = $this->object('rtf'))) {
			$rtf->setInstance($this->get_var('rtf'));
			$m['toolbar'] = $rtf->toolbar('i_content', (int) $m['id']);
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
		//$w[] = 'hide<2';
		if ($a['text'] !== '') {
			$w[] = "title like '%" . $this->db->escape($a['text'], TRUE) . "%'";
		}

		/*if ($a['room_id']) {
		$w[] = "room_id=" . intval($a['room_id']);
		}*/
		if (empty ($a['hidden'])) {
			$w[] = "hide<2";
		}
		if ($a['tag'] !== '') {
			$w[] = "FIND_IN_SET('" . $this->db->escape($a['tag']) . "',tags)";
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
		$d['date'] = $this->makeTime($d['date'], $d['time']);
		$d['hide'] = empty ($d['hide']) ? 0 : 1;
		if ($d['uri'] === '') {
			$txt = & moon :: shared('text');
			$d['uri'] = $txt->make_uri($d['title']);
		}
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
		elseif ($d['date'] === FALSE) {
			$err = 2;
		}

		/*elseif (empty($d['room_id'])) {
		$err = 5;
		}*/
		elseif (!is_object($rtf = $this->object('rtf'))) {
			$err = 9;
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
			$form->fill($d, false);
			$this->set_var('error', $err);
			return false;
		}

		/* jei refresh, nesivarginam */
		if ($wasRefresh = $form->was_refresh()) {
			return $id;
		}

		/* save to database */
		$ins = $form->get_values('hide', 'date', 'uri', 'title', 'meta_description', 'summary', 'category', 'content', 'tags', 'img', 'img_alt');

		/* dabar image */
		$del = isset ($_POST['del_img']) && $_POST['del_img'] ? TRUE : FALSE;
		$img = $this->saveImage($id, 'img', $errorMsg2, $del);
		if (!$errorMsg2) {
			$ins['img'] = $img;
		}
		else {
			$page = & moon :: page();
			$page->alert("Image error: $errorMsg2!");
		}

		/* iskarpa ir kompiliuojam i html */
		$rtf->setInstance($this->get_var('rtf'));
		list(, $ins['content_html']) = $rtf->parseText($id, $ins['content'], TRUE);
		$txt = & moon :: shared('text');
		if ($ins['summary'] === '') {
			$ins['summary'] = $txt->excerpt($txt->strip_tags($ins['content']), 250);
		}
		else {
			$ins['summary'] = $txt->excerpt($txt->strip_tags($ins['summary']), 250);
		}
		//
		$ins['updated'] = time();
		$db = & $this->db();
		if ($id) {
			$db->update_query($ins, $this->myTable, array('id' => $id));
			// log this action
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
		//$this->db_add_search($id,$ins);
		if ($id) {
			//"prisegam" objektus
			$rtf->assignObjects($id);
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
			$r = strtotime($d . " $h:$m:00 " . date('O'));
			if ($r === - 1 || $r === FALSE) {
				return FALSE;
			}
			//$r = gmdate('Y-m-d H:i:s', $r);
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
		//$this->updateRoomTable();
		return true;
	}


	function deleteByFilter() {
		$v = $this->my('vars');
		$this->formFilter->fill($v['filter']);
		$this->db->query('UPDATE ' . $this->myTable . ' SET hide=2 ' . $this->_where());
		// log this action
		blame($this->my('fullname'), 'Deleted', 'by filter');
		//$this->updateRoomTable();
	}


	function saveImage($id, $name, & $err, $del = false) {
		$err = 0;
		$dir = $this->get_var('dirImg');
		$sql = 'SELECT img
	FROM ' . $this->myTable . ' WHERE id=' . $id;
		$is = $this->db->single_query_assoc($sql);
		$f = & moon :: file();
		if (($isUpload = $f->is_upload($name, $e)) && !$f->has_extension('jpg,jpeg,gif,png')) {
			//neleistinas pletinys
			$err = 1;
			return;
		}
		$newPhoto = $curPhoto = isset ($is[$name]) ? $is[$name] : '';
		//ar reikia sena trinti?
		if (($isUpload || $del) && $curPhoto) {
			$fDel = & moon :: file();
			if ($fDel->is_file($dir . $curPhoto)) {
				//gaunam failo pav. pagrindine dali ir extensiona
				$fnameBase = substr($curPhoto, 0, 13);
				$fnameExt = rtrim('.' . $fDel->file_ext(), '.');
				//trinamas pagrindinis img
				$fDel->delete();
				$newPhoto = null;
				//dabar dar trinam maziausia img
				if ($fDel->is_file($dir . $fnameBase . '_' . $fnameExt))
					$fDel->delete();
				//dabar dar trinam originalu img
				if ($fDel->is_file($dir . $fnameBase . '_orig' . $fnameExt))
					$fDel->delete();
			}
		}
		if ($isUpload) {
			//isaugom faila
			$fnameBase = uniqid('');
			$fnameExt = rtrim('.' . $f->file_ext(), '.');
			$img = & moon :: shared('img');
			//pernelyg dideli img susimazinam bent iki 800x800
			$nameSave = $dir . $fnameBase . '_orig' . $fnameExt;
			if ($img->resize($f, $nameSave, 800, 800) && $f->is_file($nameSave)) {
				$newPhoto = $fnameBase . $fnameExt;
				//pagaminam thumbnailus is paveiksliuko
				list($w, $h) = explode('x', $this->imgWH);
				$img->resize_exact($f, $dir . $fnameBase . '' . $fnameExt, $w, $h);
				if ($f->is_file($dir . $fnameBase . '' . $fnameExt))
					$img->resize_exact($f, $dir . $fnameBase . '_' . $fnameExt, 140, 90);
			}
			else {
				//technine klaida
				$err = 3;
			}
		}
		if ($newPhoto === '')
			$newPhoto = null;
		return $newPhoto;
	}


	//pakeiciam paveiksliuka gauta su crop toolsu
	function imgReplace($id)
		//insertina irasa
		{
		$dir = $this->get_var('dirImg');
		$is = $this->db->single_query_assoc('
			SELECT img
			FROM ' . $this->myTable . ' WHERE id=' . intval($id));
		$f = & moon :: file();
		if ($f->is_file($dir . substr_replace($is['img'], '_orig', 13, 0))) {
			$nw = $_POST['newwidth'];
			$nh = $_POST['newheight'];
			$left = $_POST['left'];
			$top = $_POST['top'];
			$img = & moon :: shared('img');
			$newName = uniqid('') . '.' . $f->file_ext();
			//pernelyg dideli img susimazinam bent iki 800x800
			$nameSave = $dir . substr_replace($newName, '_orig', 13, 0);
			$img = & moon :: shared('img');
			//padarom kopijas
			if ($f->copy($nameSave)) {
				//crop is originalo pagal imgtool duomenis
				if ($img->crop($f, $dir . $newName, $nw, $nh, $left, $top)) {
					if ($f->is_file($dir . $newName)) {
						list($w, $h) = explode('x', $this->imgWH);
						$img->resize_exact($f, $dir . $newName, $w, $h);
					}
				}
				if ($f->is_file($dir . $newName)) {
					$img->resize_exact($f, $dir . substr_replace($newName, '_', 13, 0), 140, 93);
				}
				$this->db->update(array('img' => $newName), $this->myTable, $id);
				//dabar trinam senus
				$del = array($is['img'], substr_replace($is['img'], '_orig', 13, 0), substr_replace($is['img'], '_', 13, 0));
				foreach ($del as $name) {
					if ($f->is_file($dir . $name)) {
						$f->delete();
					}
				}
			}
		}
	}


	function getHtmlAuthors() {
		$m = $this->db->array_query('SELECT name FROM ' . $this->table('Authors') . ' WHERE duplicates=0 AND is_deleted=0');
		$a = array();
		foreach ($m as $v) {
			$a[] = str_replace('"', '\"', $v[0]);
		}
		return $a;
	}


	//ajaxui istraukia
	function ajaxGetAuthors($starts) {
		$limit = 10;
		$m = $this->db->array_query('SELECT name FROM ' . $this->table('Authors') . " WHERE name like '" . $this->db->escape($starts) . "%' LIMIT " . ($limit + 1));
		header('Content-Type: text/plain;charset=utf-8');
		header('Expires: Mon, 26 Jul 2000 05:00:00 GMT');
		header('Last-Modified: ' . gmdate('r'));
		header('Cache-Control: no-cache, must-revalidate');
		header('Pragma: no-cache');
		foreach ($m as $k => $v) {
			if ($k == $limit) {
				echo '<span>...</span>';
			}
			echo '<div>' . $v['name'] . '</div>';
		}
	}


	//***************************************
	//           --- OTHER ---
	//***************************************
	function getCategories($id = 0) {
		$sql = 'SELECT id, title FROM ' . $this->table('ArticlesCategories') . ' WHERE ' . ($id ? 'id=' . $id . ' OR ' : '') . 'hide<2 ORDER BY sort_order ASC';
		return $this->db->array_query($sql, TRUE);
	}


}

?>