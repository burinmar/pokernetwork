<?php
class authors extends moon_com {

function onload()
{
	$this->filter = array();
	$this->formFilter = &$this->form();
	$this->formFilter->names('name');

	$this->formItem = &$this->form('item');
	$this->formItem->names('id', 'name', 'nickname', 'uri', 'email', 'about', 'img', 'name_before', 'is_deleted', 'twitter', 'gplus_url');

	$this->sqlWhere = ''; // set by filter
	$this->sqlOrder = '';
	$this->sqlLimit = ''; // set by paging

	$this->locale = &moon::locale();
	$this->language = $this->locale->language();
}
function events($event, $par)
{
	$p=&moon::page(); 
	switch ($event) {
		case 'fill_authors_table':
			$this->fillAuthorsDbTable();
			exit;
			break;
		case 'assign_authors_ids':
			$this->assignAuthorsIds();
			exit;
			break;
		case 'import_to_new_articles':
			$this->importToNewArticles();
			exit;
			break;
		case 'filter':
			$this->setFilter();
			break;
		case 'edit':
			$id = isset($par[0]) ? intval($par[0]) : 0;
			if ($id) {
				if (count($values = $this->getItem($id))) {
					$values['name_before'] = $values['name'];
					$this->formItem->fill($values);
				}
				else {
					$this->set_var('error', '404');
				}
			}
			$this->set_var('view', 'form');
			break;
		case 'save':
			if ($id = $this->saveItem()) {
				$p->alert('Saved','ok');
				if (isset($_POST['return']) ) {
					$this->redirect('#edit', $id);
				} else {
					$this->redirect('#');
				}
			} else {
				$this->set_var('view', 'form');
			}
			break;
		case 'save_confirmed':
			if ($id = $this->saveItemConfirmed()) {
				if (isset($_POST['return']) ) {
					$this->redirect('#edit', $id);
				} else {
					$this->redirect('#');
				}
			} else {
				$this->set_var('view', 'form');
			}
			break;
		case 'delete':
			if (isset($_POST['it'])) $this->deleteItem($_POST['it']);
			$this->redirect('#');
			break;
		case 'imgtool':
			
			if (is_object($tool = & moon::shared('imgtool'))) {
				$id = empty($par[0]) ? 0 : $par[0];
				
				if (isset($_POST['id'])) {
					//cia img apdorojimas
					$this->imgReplace($_POST['id']);
					$tool->close();
				}
				$m = $this->getItem($id);
				$tool->show(array(
					'id' => $id,
					'src' => $this->get_var('imagesSrc').substr_replace($m['img'], '_orig',13,0),
					'minWH' => '170x170',
					'fixedProportions' => TRUE
					));
			}
			//forget reikia kai nuimti filtra
			$this->forget();
			break;
		default:
			$this->setOrdering();
			$this->setPaging();
			break;
	}
	$this->use_page('Common');
}
function properties()
{
	return array(
		'view' => 'list',
		'currPage' => '1',
		'listLimit' => '50',
		'error' => false
	);
}
function main($vars)
{
	$page = &moon::page();
	$win = &moon::shared('admin');
	$win->active($this->my('fullname'));
	$title = $win->current_info('title');
	$page->title($title);

	$currPage = $page->get_global($this->my('fullname') . '.currPage');
	if (!empty($currPage)) {
		$vars['currPage'] = $currPage;
	}

	if ($vars['view'] == 'form') {
		return $this->renderForm($vars);
	} else {
		return $this->renderList($vars);
	}
}
function renderList($vars)
{
	$tpl = &$this->load_template();
	$win = &moon::shared('admin');
	$page = &moon::page();
	$loc = &moon::locale();
	$sitemap = & moon :: shared('sitemap');

	$ordering = $this->getOrdering();
	$filter = $this->getFilter();
	$paging = $this->getPaging($vars['currPage'], $this->getItemsCount(), $vars['listLimit']);

	$goEdit = $this->linkas('#edit','{id}');
	$tpl->save_parsed('item',array('goEdit' => $goEdit));
	
	$uriPrefix = $sitemap->getLink('editors');

	$items = $this->getItems();
	$itemsList = '';
	foreach ($items as $item) {
		$item['name'] = htmlspecialchars($item['name']);
		$item['created_on'] = ($item['created_on'] != 0) ? $loc->datef($item['created_on'], 'News') : '';
		$item['goUri'] = ($item['uri'] != '') ? $page->home_url() . ltrim($uriPrefix, '/') . $item['uri'] : '';
		$item['classHidden'] = ($item['is_deleted'] != 0) ? 'class="item-hidden"' : '';
		$itemsList .= $tpl->parse('item', $item);
	}

	$m = array(
		'viewList' => TRUE,
		'filter' => $tpl->parse('filter', $filter),
		'items' => $itemsList,
		'paging' => $paging,
		'pageTitle' => $win->current_info('title'),
		'goNew' => $this->linkas('#edit'),
		'goDelete' => $this->my('fullname') . '#delete'
	) + $ordering;

	return $tpl->parse('main', $m);
}
function renderForm($vars)
{
	$tpl = &$this->load_template();
	$win = &moon::shared('admin');
	$page = &moon::page();
	$sitemap = & moon :: shared('sitemap');
	$info = $tpl->parse_array('info');

	$err = ($vars['error'] !== FALSE) ? $vars['error'] : FALSE;
	$confirm = FALSE;
	if ($err == 5) {
		$confirm = TRUE;
	}

	$form = $this->formItem;
	$title = $form->get('id') ? $info['titleEdit'] . ' :: ' . $form->get('name_before') : $info['titleNew'];
	
	$uriPrefix = $sitemap->getLink('editors');
	
	$m = array(
		'viewList' => FALSE,
		'confirmDuplicate' => $confirm,
		'error' => ($err !== FALSE) ? $info['error' . $err] : '',
		'id' => $form->get('id'),
		'uriPrefix' => $uriPrefix,
		'pageTitle' => $win->current_info('title'),
		'formTitle' => htmlspecialchars($title),
		'refresh' => $page->refresh_field(),
		'toolbar' => '',
		'imgTool' => $this->linkas('#imgtool',$form->get('id')),
		'selfUrl' => $this->linkas('#edit',$form->get('id')),
		'vieAuthor' => $uriPrefix.$form->get('uri').'/',
		'showPicture' => false
	) + $form->html_values();

	if ($confirm === TRUE) {
		$m['event'] = $this->my('fullname') . '#save_confirmed';
		$m['goBack'] = $this->linkas('#edit', $form->get('id'));
	} else {
		$m['event'] = $this->my('fullname') . '#save';
		$m['goBack'] = $this->linkas('#') . '?page=' . $vars['currPage'];
	}
	
	// image
	if ($m['img']) {
		$m['imgSrc'] = $this->get_var('imagesSrc') . $m['img'];
		//$m['imgSrcThumb'] = $this->get_var('imagesSrc') . substr_replace($m['img'], '_', 13, 0);
	}
	
	if (is_object( $rtf = $this->object('rtf') )) {
		$rtf->setInstance( $this->get_var('rtf') );
		$m['toolbar'] = $rtf->toolbar('i_about',(int)$form->get('id'));
	}

	return $tpl->parse('main', $m);
}
/**********
 * STANDARD
 **********/
function getItems()
{
	$sql = 'SELECT *
		FROM ' . $this->table('Authors') . ' ' .
		$this->sqlWhere . ' ' .
		$this->sqlOrder . ' ' .
		$this->sqlLimit;
	$result = $this->db->array_query_assoc($sql);
	return $result;
}
function getItemsCount()
{
	$sql = 'SELECT count(*) as cnt
		FROM ' . $this->table('Authors') . ' ' .
		$this->sqlWhere;
	$result = $this->db->single_query_assoc($sql);
	return $result['cnt'];
}
function getItem($id)
{
	$sql = 'SELECT *
		FROM ' . $this->table('Authors') . '
		WHERE id = ' . intval($id);
	return $this->db->single_query_assoc($sql);
}

function saveItem()
{
	$form = &$this->formItem;
	$form->fill($_POST);
	$values = $form->get_values();
	// Filtering
	$data['id'] = intval($values['id']);
	$data['name'] = strip_tags($values['name']);
	$data['name_before'] = strip_tags($values['name_before']);
	$data['nickname'] = $values['nickname'];
	$data['uri'] = $values['uri'];
	$data['email'] = $values['email'];
	$data['about'] = $values['about'];
	$data['twitter'] = $values['twitter'];
	$data['gplus_url'] = $values['gplus_url'];
	$data['img'] = $values['img'];
	$this->formItem->values['is_deleted'] = (isset($_POST['del_img']) && $_POST['del_img']) ? TRUE : FALSE;
	$id = intval($data['id']);
	// Validation
	$errorMsg = 0;
	
	if (!$data['name'] ) {
		// name required
		$errorMsg = 1;
	} elseif (!$id) {
		//check for name duplicates if new author submit
		$sql = "SELECT count(*) as cnt FROM ".$this->table('Authors')."
				WHERE is_deleted = 0 AND name = '".$this->db->escape($data['name'])."' ";
		$result = $this->db->single_query_assoc($sql);
		if ($result['cnt'] != 0) $errorMsg = 3;

		//check for uri duplicates
		$sql = "SELECT count(*) as cnt	FROM ".$this->table('Authors')."
				WHERE is_deleted = 0 AND uri!='' AND uri = '".$this->db->escape($data['uri'])."' ";
		$result = $this->db->single_query_assoc($sql); 
		if ($result['cnt'] != 0) $errorMsg = 4;
	} elseif ($id) {
		//check for uri duplicates
		$sql = "SELECT count(*) as cnt	FROM ".$this->table('Authors')."
				WHERE is_deleted = 0 AND uri!='' AND uri = '".$this->db->escape($data['uri'])."' AND id <> ".$id;
		$result = $this->db->single_query_assoc($sql);
		if ($result['cnt'] != 0) $errorMsg = 4;
	} 

	if ($errorMsg) {
		$this->set_var('error', $errorMsg);
		return FALSE;
	}

	// check for attempt to change author's name
	if ($id && $data['name']) {
		$sql = "SELECT count(*) as cnt FROM ".$this->table('Authors')."
				WHERE is_deleted = 0 AND  name = '".$this->db->escape($data['name'])."' AND id <> ".$id;
		$result = $this->db->single_query_assoc($sql);
		if ($result['cnt'] != 0) {
			$errorMsg = 5;
		}
	}
	// if was refresh skip other steps and return
	if ($wasRefresh = $form->was_refresh()) {
		return $id;
	}
	
	$ins=array();
	if ($errorMsg==5)
		$ins = $form->get_values('nickname', 'email', 'about', 'twitter', 'gplus_url');
	else
		$ins = $form->get_values('name', 'nickname', 'uri', 'email', 'about', 'twitter', 'gplus_url');

	//dabar image
	$del = isset($_POST['del_img']) && $_POST['del_img'] ? TRUE : FALSE;
	$img = $this->saveImage($id, 'img', $errorMsg2, $del);
	if (!$errorMsg2) {
		$ins['img'] = $img;
	} else {
		$page = &moon::page();
		$page->alert("Image error: $errorMsg2!");
	}

	if ($id) {
		$this->db->update($ins, $this->table('Authors'), array('id' => $id));

		// log this action
		blame($this->my('fullname'), 'Updated', $id);
	} else {
		$ins['created_on'] = time();
		$id = $this->db->insert($ins, $this->table('Authors'), 'id');

		// log this action
		blame($this->my('fullname'), 'Created', $id);
	}

	$form->fill(array('id' => $id));
	
	if ($errorMsg) {
		$this->set_var('error', $errorMsg);
		return FALSE;
	}

	return $id;
}
function saveImage($id , $name, &$err, $del = FALSE) //insertina irasa
{
	$err=0;
	$dir=$this->get_dir('imagesDir');

	$sql='SELECT img
	FROM '.$this->table('Authors').' WHERE id='.$id;
    $is=$this->db->single_query_assoc($sql);

    $f=new moon_file;
	if (($isUpload=$f->is_upload($name,$e)) && !$f->has_extension('jpg,jpeg,gif,png')) {
		//neleistinas pletinys
    	$err=1;
		return;
	}
	$newPhoto=$curPhoto=isset($is[$name]) ? $is[$name] : '';
    //ar reikia sena trinti?
	if ( ($isUpload || $del) && $curPhoto) {
    	$fDel=new moon_file;
		if ($fDel->is_file($dir.$curPhoto)) {
			//gaunam failo pav. pagrindine dali ir extensiona
			$fnameBase = substr($curPhoto,0,13);
			$fnameExt = rtrim('.' . $fDel->file_ext(), '.');
			//trinamas pagrindinis img
			$fDel->delete();
			$newPhoto=null;
			//dabar dar trinam maziausia img

//			if ($fDel->is_file($dir.$fnameBase . '_' . $fnameExt)) $fDel->delete();
			//dabar dar trinam originalu img
			if ($fDel->is_file($dir.$fnameBase . '_orig' . $fnameExt)) $fDel->delete();
		}
	}
	if ($isUpload) { //isaugom faila
        $fnameBase = uniqid('');
		$fnameExt = rtrim('.' . $f->file_ext(), '.');
		$img=&moon::shared('img');
		//pernelyg dideli img susimazinam bent iki 800x800
		$nameSave = $dir . $fnameBase . '_orig' . $fnameExt;
        if ( $img->resize($f,$nameSave,800,800) && $f->is_file($nameSave) ) {
        	$newPhoto = $fnameBase . $fnameExt;
        	//pagaminam thumbnailus is paveiksliuko
			$img->resize_exact($f, $dir . $fnameBase . '' . $fnameExt, 170, 170);
//			if ($f->is_file($dir . $fnameBase . '' . $fnameExt))
//				$img->resize_exact($f, $dir . $fnameBase . '_' . $fnameExt, 140,93);
		} else {
			//technine klaida
			$err = 3;
		}
	}
	if ($newPhoto==='') $newPhoto=null;
	return $newPhoto;
}
/**
 * @todo
 * 1. review images (when name conflict - looses image)
 */
function saveItemConfirmed()
{	
	$form = &$this->formItem;
	$form->fill($_POST);
	$data = $form->get_values();
	$id = intval($data['id']);

	// if was refresh skip other steps and return
	if ($wasRefresh = $form->was_refresh())  return $id;

	if ($id) {
		// get existing record's id by name
		$sql = "SELECT id FROM ".$this->table('Authors')." WHERE name = '".addslashes($data['name'])."' ";	
		$result = $this->db->single_query_assoc($sql);
		$oldAuthorId = $result['id'];
		
		// mark new dublicated record as deleted and assign to old id
		$updNew = array();
		$updNew['is_deleted'] = '1';
		$updNew['duplicates'] = $oldAuthorId;		
		$this->db->update($updNew, $this->table('Authors'), array('id' => $id));

		// update new author id dublicated id as old id  
		$oldDublicatedIdByNewId = array();
		$oldDublicatedIdByNewId['duplicates'] = $oldAuthorId;
		$this->db->update($oldDublicatedIdByNewId, $this->table('Authors'), array('duplicates' => $id));
	}
	return $id;
}
function deleteItem($ids)
{
	if (!is_array($ids) || !count($ids)) return;
	foreach ($ids as $k => $v) {
		$ids[$k] = intval($v);
	}
	// don't delete really, just mark as deleted
	$this->db->query('UPDATE ' . $this->table('Authors') . ' SET is_deleted = 1, uri = "" WHERE id IN (' . implode(',', $ids) . ')');

	// log this action
	blame($this->my('fullname'), 'Deleted', $ids);

	return TRUE;
}
function setSqlWhere()
{
	$where = array();
	if (!empty($this->filter)) {
		$where[] = 'WHERE 1';
		if ($this->filter['name'] != '') {
			$where[] = 'name LIKE \'%' . $this->db->escape($this->filter['name']) . '%\'';
		}
		if (empty($this->filter['is_deleted'])) {
			$where[] = 'is_deleted = 0';
		}
	} else {
		$where[] = 'WHERE is_deleted = 0';
	}
	$this->sqlWhere = implode(' AND ', $where);
}
function setFilter()
{
	$page = &moon::page();
	if (isset($_POST['filter'])) {
		$this->filter = $_POST['filter'];
		$page->set_global($this->my('fullname') . '.filter', $this->filter);
	} else {
		$page->set_global($this->my('fullname') . '.filter', '');
	}
}
function getFilter()
{
	$page = &moon::page();
	$savedFilter = $page->get_global($this->my('fullname') . '.filter');
	if (!empty($savedFilter)) {
		$this->filter = $savedFilter;
	}
	$this->formFilter->fill($this->filter);

	$filter = $this->formFilter->html_values();
	$filter['goFilter'] = $this->my('fullname').'#filter';
	$filter['noFilter'] = $this->linkas('#filter');
	$filter['isOn'] = '';

	// custom fields
	$filter['name'] = $this->formFilter->get('name');
	$filter['is_deleted'] = $this->formFilter->checked('is_deleted', 1);

	foreach ($this->filter as $k => $v) {
		if ($v) {
			$filter['isOn'] = 1;
			break;
		}
	}
	$filter['classIsOn'] = $filter['isOn'] ? ' filter-on' : '';

	$this->setSqlWhere();

	return $filter;
}
function setPaging()
{
	$page = &moon::page();
	if (isset($_GET['page']) && is_numeric($_GET['page'])) {
		$currPage = $_GET['page'];
		$page->set_global($this->my('fullname') . '.currPage', $currPage);
	} else {
		$page->set_global($this->my('fullname') . '.currPage', 1);
	}
}
function getPaging($currPage, $itemsCnt, $listLimit)
{
	$pn = &moon::shared('paginate');
	$pn->set_curent_all_limit($currPage, $itemsCnt, $listLimit);
	$pn->set_url($this->linkas('#', '', array('page' => '{pg}')), $this->linkas('#'));
	$pnInfo = $pn->get_info();

	$this->sqlLimit = $pnInfo['sqllimit'];
	return $pn->show_nav();
}
function setOrdering()
{
	if (isset($_GET['ord'])) {
		$sort = (int)$_GET['ord'];
		$page = &moon::page();
		$page->set_global($this->my('fullname') . '.sort', $sort);
	}
}
function getOrdering()
{
	$page = &moon::page();
	$sort = $page->get_global($this->my('fullname') . '.sort');
	if (empty($sort)) {
		$sort = 2;
	}

	$links = array();
	$pn = &moon::shared('paginate');
	$ord = &$pn->ordering();
	$ord->set_values(
		//laukai, ir ju defaultine kryptis
		array('name' => 1, 'created_on' => 0),
		//antras parametras kuris lauko numeris defaultinis.
		0
	);

	$links = $ord->get_links(
		$this->linkas('#', '', array('ord' => '{pg}')),
		$sort
	);
	// hack. if order
	$byName = '';
	if (strpos($ord->sql_order(), 'created_on') !== FALSE) {
		$byName = ', name ASC';
	}
	$this->sqlOrder = 'ORDER BY ' . $ord->sql_order() . $byName;
	//gauna linkus orderby{nr}
	return $links;
}
/***************************
 * USED FOR EXTRACTING NAMES
 ***************************/
function getItemsContent()
{
	$authors = array();
	$nicknames = array();

	$sql = 'SELECT distinct(author_name) as name
		FROM ' . $this->table('ContentList') . '
		ORDER BY author_name';
	$result = $this->db->array_query_assoc($sql);

	$items = array();
	foreach ($result as $res) {
		$name = $res['name'];

		$authorData = array();
		$authorData = $this->extractNamesNicknames($name);

		foreach ($authorData as $aName => $aNick) {
			// add author
			if (in_array($aName, $authors) === FALSE) {
				$authors[] = $aName;
			}

			// add author nickname if he has
			if ((in_array($aName, $nicknames) === FALSE) AND ($aNick != '')) {
				$nicknames[$aName] = $aNick;
			}
		}

		$data = array();
		$data['name'] = $name;
		$data['name_filtered'] = $name;
		$items['data'][] = $data;
	}

	sort($authors);
	$items['authors'] = $authors;
	$items['nicknames'] = $nicknames;
	return $items;
}
function extractNamesNicknames($name) {
	$authorData = array();

	$name = str_ireplace(array('by:', 'by', '(aka '), array('', '', '('), $name);
	// separate multiple authors
	$name = str_ireplace(array('&amp;', ' & ', ' and ', ' with '), ',', $name);
	// remove dot from the end of the string
	$name = preg_replace('/(.*)([\.]{1})$/i', '$1', $name);

	$names = explode(',', $name);
	foreach ($names as $authorName) {
		$authorName = trim($authorName);

		if ($authorName == '' OR strlen($authorName) <= 2) continue;

		$nick = '';
		// extract nickname
		if (preg_match('/^.*[\'|"|\(]{1}(.*)[\'|"|\)]{1}.*$/i', $authorName, $matches)) {
			$nick = (isset($matches[1])) ? $matches[1] : '';
			// remove nickname from name
			$authorName = preg_replace('/^(.*)([\'|"|\(]{1}.*[\'|"|\)]{1})(.*)$/i', '$1$3', $authorName);
		}
		//remove more than one whitespace
		$authorName = preg_replace('/\s+/i', ' ', $authorName);

		$authorData[$authorName] = $nick;
	}
	return $authorData;
}
function getAuthors() {
	$sql = 'SELECT id, name
		FROM ' . $this->table('Authors');
	$result = $this->db->array_query_assoc($sql);
	$authors = array();
	foreach ($result as $r) {
		$authors[$r['name']] = $r['id'];
	}
	return $authors;
}
function getNewsWithAuthors() {
	$sql = 'SELECT id, author_name, author_id
		FROM ' . $this->table('ContentList') . '
		WHERE author_name != ""';
	$result = $this->db->array_query_assoc($sql);
	return $result;
}
function updateNewsAuthorsId($when) {
	$sql = 'UPDATE ' . $this->table('ContentList') . '
		SET author_id =
			CASE
			' . $when . '
			END
	';
	$this->db->query($sql);
}
function updateNewArticlesAuthorsId($when) {
	$sql = 'UPDATE ' . $this->table('Articles') . '
		SET authors =
			CASE
			' . $when . '
			END
	';
	$this->db->query($sql);
}
function fillAuthorsDbTable() {
	$items = $this->getItemsContent();
	foreach ($items['authors'] as $k => $v) {
		$item = array();
		$item['name'] = $v;
		$item['nickname'] = (isset($items['nicknames'][$v])) ? $items['nicknames'][$v] : '';
		$item['uri'] = make_uri($v);
		$item['created_on'] = time();
		$this->db->insert($item, $this->table('Authors'));
	}
	return;
}
function assignAuthorsIds() {
	$authors = $this->getAuthors();
	$news = $this->getNewsWithAuthors();

	$when = '';
	foreach ($news as $item) {

		$ids = array();
		$authorData = array();
		$authorData = $this->extractNamesNicknames($item['author_name']);
		foreach ($authorData as $aName => $aNick) {
			// add author
			if (array_key_exists($aName, $authors) === TRUE) {
				$ids[] = $authors[$aName];
			}
		}
		$when .= 'WHEN id = ' . $item['id'] . ' THEN "' . implode(',', $ids) . '" ';
	}
	$this->updateNewsAuthorsId($when);
	return TRUE;
}
function importToNewArticles() {
	$oldNews = $this->getNewsWithAuthors();

	$when = '';
	foreach ($oldNews as $item) {
		$when .= 'WHEN id = ' . $item['id'] . ' THEN "' . $item['author_id'] . '" ';
	}
	$this->updateNewArticlesAuthorsId($when);
	return TRUE;
}

//pakeiciam paveiksliuka gauta su crop toolsu
function imgReplace($id) //insertina irasa
{	
	$dir = $this->get_dir('imagesDir');
	$sql="SELECT img FROM ".$this->table('Authors')." WHERE id = ".intval($id)." ";
	$is=$this->db->single_query_assoc($sql);


	$f = new moon_file;
	if ($f->is_file($dir . substr_replace($is['img'], '_orig', 13, 0))) {
		$nw = $_POST['newwidth'];
		$nh = $_POST['newheight'];
		$left = $_POST['left'];
		$top = $_POST['top'];
		$img = &moon::shared('img');

		$newName = uniqid('') . '.' . $f->file_ext();

		//pernelyg dideli img susimazinam bent iki 800x800
		$nameSave = $dir . substr_replace($newName, '_orig',13,0);
		$img = & moon::shared('img');
		
		//padarom kopijas
		if ( $f->copy($nameSave) ) {
			
			//crop is originalo pagal imgtool duomenis
			if ($img->crop($f, $dir . $newName, $nw, $nh, $left, $top)) {
				if ($f->is_file($dir . $newName)) {
					$img->resize_exact($f, $dir . $newName, 170, 170);
				}
			}
//			if ($f->is_file($dir . $newName)) {
//				$img->resize_exact($f, $dir . substr_replace($newName, '_',13,0), 140, 93);
//			}
			$this->db->update(array('img'=>$newName), $this->table('Authors'), $id);
			
			//dabar trinam senus
			$del = array(
				$is['img'],
				substr_replace($is['img'], '_orig', 13, 0),
//				substr_replace($is['img'], '_', 13, 0)
				);
			foreach ($del as $name) {
				if ($f->is_file($dir.$name)) {
					$f->delete();
				}
			}
		}
	}
}


}
?>