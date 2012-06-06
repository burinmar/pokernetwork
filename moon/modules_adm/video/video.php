<?php


class video extends moon_com {


	function onload() {

		/* form of item */
		$this->form = & $this->form();
		$this->form->names('id', 'hide', 'yhide', 'category', 'uri', 'title', 'youtube_playlist_id', 'description', 'youtube_video_id', 'tags', 'thumbnail_url', 'updated', 'master_updated');
		$this->form->fill(array('date' => time()));

		/* form of filter */
		$this->formFilter = & $this->form('f2');
		$this->formFilter->names('hidden', 'text', 'tag');

		/* main table */
		$this->myTable = $this->table('Videos');
		$this->tbMaster = $this->table('VideosMaster');
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




			case 'filter' :
				$filter = isset ($_POST['filter']) ? $_POST['filter'] : '';
				$this->set_var('filter', $filter);
				$this->set_var('psl', 1);
				//forget reikia kai nuimti filtra
				$this->forget();
				break;

			case 'import' :
				$s = $this->syncRun();
				$page = & moon :: page();
				$page->set_local('cron', $s);
				return;


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
		//laukai, ir ju defaultine kryptis
		//antras parametras kuris lauko numeris defaultinis.
		$ord->set_values(array('created' => 0), 1);
		//gauna linkus orderby{nr}
		$m += $ord->get_links($this->linkas('#', '', array('ord' => '{pg}')), $vars['sort']);

		/* kategorijos */
		$categories = $this->getCategories();

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

			/* Gaunam autorius */
			$ids = array();
			foreach ($dat as $d) {
				if ($d['author_id']) {
					$ids[] = $d['author_id'];
				}
			}
			//$authors = $this->getAuthors($ids);
			$authors = array();



			/* sarasas */
			$goEdit = $this->linkas('#edit', '{id}');
			$t->save_parsed('items', array('goEdit' => $goEdit));
			$locale = & moon::locale();
			$info = $t->parse_array('info');
			$text = & moon::shared('text');
			$http = 'http://adm.pokernews.' . (is_dev() ? 'dev':'com');
			foreach ($dat as $d) {
				$d['class'] = $d['hide'] ? 'item-hidden' : '';

				/*if (!empty($d['master_id'])) {
				$sType = (int)$d['master_updated']<(int)$d['updated'] ? 1 : 2;
				$d['styleSync'] = ' style="background: url({!_AIMG_}sync'.$sType.'.png) right 2px no-repeat;background-color:inherit;"';
				}*/
				//autoriai
				if ($d['author_id'] && isset ($authors[$d['author_id']])) {
					$d['author'] = htmlspecialchars($authors[$d['author_id']]);
				}
				else {
					$d['author'] = '';
				}
				$d['img'] = $d['thumbnail_url'] ? $d['thumbnail_url'] :$http.'/i/video-placeholder.png';
				$d['description'] = htmlspecialchars($text->excerpt($d['description'], 300));
				$d['duration'] = $this->duration($d['duration']);

					//sync ikona
					$sType = (int)$d['master_updated']<(int)$d['updated'] ? 1 : 2;
					$d['styleTD'] = ' class="sync'.$sType.'"';

				//kategorijos
				if ($d['youtube_playlist_id'] && isset ($categories[$d['youtube_playlist_id']])) {
					$d['category'] = htmlspecialchars($categories[$d['youtube_playlist_id']]);
				}
				else {
					$d['category'] = '';
				}
				//kita
				$d['title'] = htmlspecialchars($d['title']);
				$d['tags'] = htmlspecialchars(str_replace(',', ', ', $d['tags']));
				$d['created'] = $locale->datef($d['created'], 'DateTime');
				$d['updated'] = $locale->datef($d['updated'], 'DateTime');
				$m['items'] .= $t->parse('items', $d);
			}
		}
		else {
			//filtras nerodomas kai tuscias sarasas
			if (!$fm['isOn']) {
				//	$m['filtras'] = '';
			}
		}
		$m['goNew'] = $this->linkas('myvideo#upload');
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

		//$yUrl = moon::shared('youtube')->getVideo('8l6cquxz3TM');

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
		$m['yhide'] = $f->checked('yhide', 1);
		$m += $f->html_values();
		// Other settings
		if ($f->get('hide') > 0) {
			$f->fill(array('hide' => 1));
		}
		$m['hide'] = $f->checked('hide', 1);


		$http = 'http://adm.pokernews.' . (is_dev() ? 'dev':'com');
		$m['thumbnail'] = $m['thumbnail_url'] ? $m['thumbnail_url'] : $http . '/i/video-placeholder.png';

		/* category */
		$categories = $this->getCategories();
		$myCategories = $m['category'] ? explode(',', $m['category']) : array();
		$m['categories'] = $m['td1'] = $m['td2'] = $m['td3'] ='';
		$perCol = ceil(count($categories) / 3);
		$i = 1;
		$col = 1;
		foreach ($categories as $id=>$name) {
			if (in_array($id, $myCategories)) {
				$id .='" checked="checked';
			}

			$m['td' . $col] .= $t->parse('check:category', array($id,htmlspecialchars($name)));
			if ($i++ == $perCol) {
				$col++;
				$i = 1;
			}
		}

		//master info
		$m['syncStatus'] = (int)$m['updated']>(int)$m['master_updated'] ? 1 : 2;
		$a = (int)$m['updated']<0 ? 0 : $this->getMasterInfo($m['id']);
		if (!empty($a)) {
			$m['master_title'] = nl2br(htmlspecialchars($a['title']));
			if ($a['prev_title']) {
				$m['master_title'] = nl2br(htmlDiff(htmlspecialchars($a['prev_title']),htmlspecialchars($a['title'])));
			}
			$m['master_description'] = nl2br(htmlspecialchars($a['description']));
			if ($a['prev_description']) {
				$m['master_description'] = nl2br(htmlDiff(htmlspecialchars($a['prev_description']),htmlspecialchars($a['description'])));
			}
			$m['master_tags'] = nl2br(htmlspecialchars($a['tags']));
			if ($a['prev_tags']) {
				$m['master_tags'] = nl2br(htmlDiff(htmlspecialchars($a['prev_tags']),htmlspecialchars($a['tags'])));
			}
		}


		/* Youtube video info */
		/*if ($yId = $f->get('youtube_video_id')) {
			$yt = moon::shared('youtube')->YouTube();
			try {
				$videoEntry = $yt->getVideoEntry($yId);
				$i = array();
				$i['id'] = $videoEntry->getVideoId();
				$i['Title'] = $videoEntry->getVideoTitle();
				$i['Description'] = $videoEntry->getVideoDescription();
				//$i['Updated'] = $videoEntry->getUpdated();
				//$i['Category'] = $videoEntry->getVideoCategory();
				$i['Tags'] = implode(", ", $videoEntry->getVideoTags());
				$i['Video Url'] = $videoEntry->getVideoWatchPageUrl();
				$i['Flash Url'] = $videoEntry->getFlashPlayerUrl();
				$i['Duration'] = $this->duration($videoEntry->getVideoDuration());
				$i['View count'] = $videoEntry->getVideoViewCount();
				$i['Rating'] = $videoEntry->getVideoRatingInfo();
				$i['Geo Location'] = $videoEntry->getVideoGeoLocation();
				$i['Recorded on'] = $videoEntry->getVideoRecorded();
				//$i['Developer tags'] = implode(', ',$videoEntry->getVideoDeveloperTags());
				$videoControl = $videoEntry->getControl();
				if (is_object($videoControl) && is_object($state = $videoControl->getState())) {
					$i['Upload status'] = $state->getName() . ' - ' . $state->getText();
				}
				$m['yinfo'] = '';
				foreach ($i as $k=>$v) {
					if ($v !='') {
						$m['yinfo'] .= $t->parse('yinfo', array(htmlspecialchars($k), htmlspecialchars($v)));
					}
				}
				$videoThumbnails = $videoEntry->getVideoThumbnails();
				if (count($videoThumbnails)) {
					$m['thumbnail'] = $videoThumbnails[0]['url'];
				}

			}
			catch (Zend_Gdata_App_HttpException $e) {
				moon::page()->alert($e->getRawResponseBody());
			}
			catch (Zend_Gdata_App_Exception $e) {
				moon::page()->alert($e->getMessage());
			}
		}*/
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
		if (!$id) {
			return 0;
		}

		/* gautu duomenu apdorojimas */
		$d['hide'] = empty ($d['hide']) ? 0 : 1;
		if ($d['uri'] === '') {
			$d['uri'] = make_uri($d['title']);
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
		//kategorijos
		$d['category'] = '';
		if (!empty($_POST['category']) && is_array($_POST['category'])) {
			$d['category'] = implode(',', $_POST['category']);
		}
		//jei bus klaida
		$form->fill($d, false);

		/* validacija */
		$err = 0;
		if ($d['title'] === '') {
			$err = 1;
		}
		elseif ($d['description'] === '') {
			$err = 2;
		}
		/*else {
			//check for uri duplicates
			$sql = "SELECT id	FROM " . $this->myTable . "
					WHERE hide < 2 AND uri = '" . $this->db->escape($d['uri']) . "' AND id <> " . $id;
			if (count($a = $this->db->single_query($sql))) {
				$err = 3;
			}
		}*/


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
		$ins = $form->get_values('hide', 'title', 'description', 'tags', 'category');
		$ins['updated'] = time();

		$db = & $this->db();
		if ($id) {
			$db->update_query($ins, $this->myTable, array('id' => $id));
			blame($this->my('fullname'), 'Updated', $id);

 			//update master table
			$db->query('UPDATE ' . $this->tbMaster . ' SET prev_title = title, prev_description = description, prev_tags = tags WHERE id=' . intval($id));

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
		// dabar trinam tik tuos, kur status <0
		$a = $this->db->array_query_assoc('
			SELECT id, file, youtube_video_id, created FROM ' . $this->myTable . ' WHERE id IN (' . implode(',', $ids) . ') AND (process_status<0 || process_status=1)
		');
		if (count($a) != count($ids)) {
			moon::page()->alert('Only incomplete videos can be deleted!', 'N');
		}
		if (!count($a)) {
			return;
		}
		$ids = array();
		$f = moon::file();
		$dir = $this->get_var('dirUploads');
		$yt = moon::shared('youtube')->YouTube();
		foreach ($a as $v) {
			$ids[] = $v['id'];
			$dname = $dir . gmdate('Ym', $v['created']) . '/';
			if ($f->is_info($dname, $v['file'])) {
				$xml = $f->file_path() . '.xml';
				$f->delete();
				if ($f->is_file($xml)) {
					$f->delete();
				}
			}
			if ($v['youtube_video_id']) {
				try {
 					$videoEntry = $yt->getFullVideoEntry($v['youtube_video_id']);
					$putUrl = 'https://gdata.youtube.com/feeds/api/users/default/uploads/' . $v['youtube_video_id'];
					$yt->delete($videoEntry, $putUrl);
				}
				catch (Zend_Gdata_App_HttpException $e) {
					moon::page()->alert($e->getRawResponseBody());
				}
				catch (Zend_Gdata_App_Exception $e) {
					moon::page()->alert($e->getMessage());
				}
			}
		}
		$this->db->query('
			UPDATE ' . $this->myTable . ' SET hide=2 WHERE id IN (' . implode(',', $ids) . ')
		');
		// log this action
		blame($this->my('fullname'), 'Deleted', $ids);
		//$this->updateRoomTable();
		return true;
	}


	//***************************************
	//           --- OTHER ---
	//***************************************

	function duration($n) {
		if ($n > 0) {
			$loc = moon :: locale();
			$s = ':' . $loc->zero($n % 60);
			$n = floor($n / 60);
			if ($n>60) {
				$s = $loc->zero(floor($n / 60)) . ':' . $loc->zero($n % 60) . $s;
			}
			else {
				$s = $loc->zero($n) . $s;
			}
		}
		else {
			$s = '';
		}
		return $s;
	}

	function getCategories($id = 0) {
		$sql = 'SELECT id, title FROM ' . $this->table('VideosCategories') . ' WHERE ' . ($id ? 'id=' . $id . ' OR ' : '') . 'hide<2 ORDER BY sort_order ASC';
		return $this->db->array_query($sql, TRUE);
	}

	function getAuthors($ids) {
		$ids = array_unique($ids);
		$r = array();
		if (count($ids)) {
			foreach ($ids as $k=>$v) {
				$ids[$k] = intval($v);
			}
			$sql = 'SELECT id, nick FROM ' . $this->table('Users') . ' WHERE id IN (' . implode(', ', $ids) . ')';
			$r = $this->db->array_query($sql, TRUE);
		}
		return $r;
	}

    /* ********************** Other **********************/

	 function getMasterInfo($id)
	{
		return $this->db->single_query_assoc('
			SELECT * FROM ' . $this->tbMaster . ' WHERE
			id = ' . intval($id)
		);
	}

	function assignCategory($tags) {
		static $c = NULL;
		if (is_null($c)) {
			$sql = 'SELECT id, tags FROM ' . $this->table('VideosCategories');
			$a = $this->db->array_query($sql, TRUE);
			$c = array();
			foreach ($a as $k=>$v) {
				if ($v != '') {
					$c[$k] = array('+'=>array(), '-'=>array(), ''=>array());
					$tgs = explode(',', strtolower($v));
					foreach ($tgs as $tag) {
						$tag = trim($tag);
						if ($tag!=='') {
							$h = $tag[0];
							switch ($h) {
							case '+':
							case '-':
								if ($tag !== $h) {
									$c[$k][$h][] = trim(substr($tag, 1));
								}
								break;

							default:
								$c[$k][''][] = $tag;
							}
						}
					}
				}
			}
		}
		$tags = explode(',', strtolower($tags));
		$cats = array();
		foreach ($tags as $tag) {
			 if ($tag != '') {
			 	// atmetam
				foreach ($c as $ct) {
					if (in_array($tag, $ct['-'])) {
						return '';
					}
				}
				//dabar ieskom ar gali buti
				foreach ($c as $cid=>$ct) {
					if (in_array($tag, $ct[''])) {
						//radom, nustatom kokioj kategorijoj
						$cats[] = $cid;
					}
				}
			 }
		}
		if (count($cats)) {
			$cats = array_unique($cats);
			foreach ($cats as $k=>$cid) {
				foreach ($c[$cid]['+'] as $ctag) {
					if (!in_array($ctag,$tags)) {
						unset($cats[$k]);
						break;
					}
				}
			}
			if (count($cats)) {
				return implode(',', $cats);
			}
		}
		return '';
	}

	function countTODO() {
		// kiek turnyru
		$a = $this->db->single_query('
			SELECT count(*) FROM ' . $this->myTable . '
			WHERE master_updated>updated AND hide<2
			'.$rooms);
		return empty($a[0]) ? 0 : $a[0];
	}


	/* ********************** SYNC **********************/

	function syncRun() {
		//$lastUpdate = $this->get_max_updated_timestamp();
		$sql = 'SELECT MAX(ABS(master_updated)) FROM ' . $this->myTable;
		$m = $this->db->single_query($sql);
		$lastUpdate = empty ($m[0]) ? 0 : $m[0];
		//
		if (callPnEvent('adm', 'video.remote#sync-export', array('timestamp' => $lastUpdate), $answer,FALSE)) {
			//randam kokie master_id atejo
			$ids = array();
			foreach ($answer AS $v) {
				$ids[] = $v['id'];
			}
			$masterExist = $this->syncCheckMasterExist($ids);
			foreach ($answer AS $v) {
				if (empty ($masterExist[$v['id']])) {
					$this->syncUpdateItem($v, array());
				}
				else {
					$this->syncUpdateItem($v, $masterExist[$v['id']]);
				}
			}
			//pravalom senus (daugiau kaip 30 d. )
			$seni = moon::locale()->now() - 86400 * 30;
			$this->db->query('UPDATE ' . $this->myTable . ' SET hide=2 WHERE hide=1 AND `created` < ' . $seni);
			return ' Videos imported: ' . count($answer);
		}
		else {
			return 'Error!';
		}

	}

	function syncCheckMasterExist($ids) {
		if (empty ($ids)) {
			return array();
		}
		$sql = "SELECT id,updated,title,description,tags,master_updated FROM " . $this->myTable . " WHERE id IN (" . implode(', ', $ids) . ")";
		$m = $this->db->array_query_assoc($sql, 'id');
		return $m;
	}

	function syncUpdateItem($item, $exist) {

		$fields = array( 'id', 'hide', 'duration', 'created', 'author_id', 'youtube_video_id', 'brightcove_id', 'flv_url', 'thumbnail_url');
		$ins = array();
		foreach ($fields as $v) {
			$ins[$v] = $item[$v];
		}
		//$autopublish = 'com' == _SITE_ID_ ? TRUE : FALSE;
		$autopublish = FALSE;
		if (empty($exist['updated']) || $autopublish) {
			$ins['title'] = $item['title'];
			$ins['description'] = $item['description'];
			$ins['tags'] = $item['tags'];
			$ins['category'] = $this->assignCategory($ins['tags']);
			$ins['hide'] = $ins['hide'] ? $ins['hide'] : ($autopublish ? 0 : 1);
			$ins['updated'] = $autopublish ? time() : 0;
			if (empty($exist['updated'])) {
				$txt = & moon::shared('text');
				$ins['uri'] = $txt->make_uri($item['title']);
			}
		}
		$ins['master_updated'] = $item['updated'];
		if (empty ($exist['id'])) {
			//insert
			$this->db->insert($ins, $this->myTable);
		}
		else {
			//update
			if ($item['title'] === $exist['title'] && $item['description'] === $exist['description'] && $item['tags'] === $exist['tags']
					&& $exist['updated']>=$exist['master_updated']) {
				// nereikia informuoti vertejus apie pasikeitimus
				$ins['updated'] = time();
			}
			$this->db->update($ins, $this->myTable, $exist['id']);
		}
		$ins = array();
		$ins['title'] = $item['title'];
		$ins['description'] = $item['description'];
		$ins['tags'] = $item['tags'];
		$is = $this->db->single_query('SELECT id FROM '. $this->tbMaster.' WHERE id=' .intval($item['id']));
		if (empty($is[0])) {
			$ins['id'] = $item['id'];
			$this->db->replace($ins, $this->tbMaster);
		}
		else {
			$this->db->update($ins, $this->tbMaster, $item['id']);
		}
	}


}

?>