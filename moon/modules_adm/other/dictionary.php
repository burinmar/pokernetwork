<?php
class dictionary extends moon_com {

function onload()
{
	// filter
	$this->filter = array();
	$this->formFilter = &$this->form();
	$this->formFilter->names('name');

	// item form
	$this->formItem = &$this->form('item');
	$this->formItem->names('id', 'name', 'uri', 'description_raw', 'usg_raw');

	//
	$this->sqlWhere = ''; // set by filter
	$this->sqlOrder = '';
	$this->sqlLimit = ''; // set by paging
}
function events($event, $par)
{
	switch ($event) {
		case 'decode-texts':
			// !note: after import - search db for usage and description fields that contain html entity (select * from dictionary_en where usg like '%&lt;%')
			$this->decodeDbTexts();
			break;
		case 'fix-uri':
			$this->fixDbUri();
			break;
		case 'filter':
			$this->setFilter();
			break;
		case 'edit':
			$id = isset($par[0]) ? intval($par[0]) : 0;
			if ($id) {
				if (count($values = $this->getItem($id))) {
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
		case 'deleteall':
			$this->deleteItemsByFilter();
			$this->redirect('#');
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
	$vars = array();
	$vars['view'] = 'list';
	$vars['currPage'] = '1';
	$vars['listLimit'] = '50';
	$vars['error'] = FALSE;
	return $vars;
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

	$ordering = $this->getOrdering();
	$filter = $this->getFilter();
	$paging = $this->getPaging($vars['currPage'], $this->getItemsCount(), $vars['listLimit']);

	$goEdit = $this->linkas('#edit','{id}');
	$tpl->save_parsed('items',array('goEdit' => $goEdit));

	$items = $this->getItems();
	$itemsList = '';
	$maxLen = 100;
	foreach ($items as $item) {
		$desc = $item['description_html'];
		if (strlen($desc) > $maxLen) {
			$short = substr($desc, 0, $maxLen);
			if (($offset = strrpos($short, ' ')) !== FALSE) {
				$item['description'] = substr($short, 0, $offset) . '...';
			} else {
				$item['description'] = substr($short, 0, $maxLen) . '...';
			}
		} else {
			$item['description'] = $desc;
		}

		$itemsList .= $tpl->parse('items', $item);
	}

	$main = array();
	$main['viewList'] = TRUE;
	$main['filter'] = $tpl->parse('filter', $filter);
	$main['items'] = $itemsList;
	$main['paging'] = $paging;
	$main['pageTitle'] = $win->current_info('title');
	$main['goNew'] = $this->linkas('#edit');
	$main['goDelete'] = $this->my('fullname') . '#delete';
	$main['goClear'] = $this->my('fullname') . '#deleteall';
	$main += $ordering;

	return $tpl->parse('main', $main);
}
function renderForm($vars)
{
	$tpl = &$this->load_template();
	$win = &moon::shared('admin');
	$page = &moon::page();
	$info = $tpl->parse_array('info');

	$err = ($vars['error'] !== FALSE) ? $vars['error'] : FALSE;

	$form = $this->formItem;
	$title = $form->get('id') ? $info['titleEdit'] . ' :: ' . $form->get('name') : $info['titleNew'];

	$main = array();
	$main['viewList'] = FALSE;
	$main['error'] = ($err !== FALSE) ? $info['error' . $err] : '';
	$main['event'] = $this->my('fullname') . '#save';
	$main['id'] = $form->get('id');
	$main['goBack'] = $this->linkas('#') . '?page=' . $vars['currPage'];
	$main['pageTitle'] = $win->current_info('title');
	$main['formTitle'] = htmlspecialchars($title);
	$main['uriPrefix'] = $this->get_var('uriPrefixDictionary');
	$main['refresh'] = $page->refresh_field();
	$main['toolbar'] = '';
	$main += $form->html_values();

	// add toolbar
	if (is_object( $rtf = $this->object('rtf') )) {
		$rtf->setInstance( $this->get_var('rtf') );
		$main['toolbarDesc'] = $rtf->toolbar('i_description_raw',(int)$main['id']);
		$main['toolbarUsg'] = $rtf->toolbar('i_usg_raw',(int)$main['id']);
	}

	return $tpl->parse('main', $main);
}
function getItems()
{
	$sql = 'SELECT id, name, description_html, uri
		FROM ' . $this->table('Dictionary') . ' ' .
		$this->sqlWhere . ' ' .
		$this->sqlOrder . ' ' .
		$this->sqlLimit;
	$result = $this->db->array_query_assoc($sql);
	return $result;
}
function getItemsCount()
{
	$sql = 'SELECT count(*) as cnt
		FROM ' . $this->table('Dictionary') . ' ' .
		$this->sqlWhere;
	$result = $this->db->single_query_assoc($sql);
	return $result['cnt'];
}
function getItem($id)
{
	$sql = 'SELECT *
		FROM ' . $this->table('Dictionary') . '
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
	$data['uri'] = $values['uri'];
	$data['description_raw'] = $values['description_raw'];
	$data['usg_raw'] = $values['usg_raw'];
	$id = $data['id'];

	// Validation
	$errorMsg = 0;
	// name
	if ($data['name'] == '') {
		$errorMsg = 1;
	// uri
	} elseif ($data['uri'] == '') {
		$errorMsg = 2;
	// description
	} elseif ($data['description_raw'] == '') {
		$errorMsg = 3;
	} elseif (!is_object($rtf = $this->object('rtf'))) {
		$errorMsg = 9;
	} else {
		//check for uri duplicates
		$sql = 'SELECT count(*) as cnt
			FROM ' . $this->table('Dictionary') . '
			WHERE uri = \'' . $this->db->escape($data['uri']) . '\' AND id <> ' . $id;
		$result = $this->db->single_query_assoc($sql);
		if ($result['cnt'] != 0) {
			$errorMsg = 4;
		}
	}

 	if ($errorMsg) {
		$this->set_var('error', $errorMsg);
		return FALSE;
	}

	// if was refresh skip other steps and return
	if ($wasRefresh = $form->was_refresh()) {
		return $id;
	}

	$ins = $form->get_values('name', 'uri', 'description_raw', 'usg_raw');

	//iskarpa ir kompiliuojam i html
	$rtf->setInstance( $this->get_var('rtf') );
	list(, $ins['description_html']) = $rtf->parseText($id, $ins['description_raw'], TRUE);
	list(, $ins['usg_html']) = $rtf->parseText($id, $ins['usg_raw'], TRUE);

	if ($id) {
		$this->db->update($ins, $this->table('Dictionary'), array('id' => $id));

		// log this action
		blame($this->my('fullname'), 'Updated', $id);
	} else {
		$id = $this->db->insert($ins, $this->table('Dictionary'), 'id');

		// log this action
		blame($this->my('fullname'), 'Created', $id);
	}

	$form->fill(array('id' => $id));
	return $id;
}
function deleteItem($ids)
{
	if (!is_array($ids) || !count($ids)) return;
	foreach ($ids as $k => $v) {
		$ids[$k] = intval($v);
	}
	$this->db->query('DELETE FROM ' . $this->table('Dictionary') . ' WHERE id IN (' . implode(',', $ids) . ')');

	// log this action
	blame($this->my('fullname'), 'Deleted', $ids);
	
	return TRUE;
}
function deleteItemsByFilter()
{
	$filter = $this->getFilter(); // sets $this->sqlWhere
	$this->db->query('DELETE FROM ' . $this->table('Dictionary') . $this->sqlWhere);
	//$this->db->query('UPDATE ' . $this->table('Dictionary') . ' SET is_hidden = 1 ' . $this->sqlWhere);
}
function setSqlWhere()
{
	$where = array();
	if (!empty($this->filter)) {
		$where[] = 'WHERE 1';
		if ($this->filter['name'] != '') {
			$where[] = 'name LIKE \'%' . $this->db->escape($this->filter['name']) . '%\'';
		}
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
		$sort = 11;
	}

	$links = array();
	$pn = &moon::shared('paginate');
	$ord = &$pn->ordering();
	$ord->set_values(
		//laukai, ir ju defaultine kryptis
		array('name' => 1) ,
		//antras parametras kuris lauko numeris defaultinis.
		0
	);

	$links = $ord->get_links(
		$this->linkas('#', '', array('ord' => '{pg}')),
		$sort
	);
	$this->sqlOrder = 'ORDER BY ' . $ord->sql_order();
	//gauna linkus orderby{nr}
	return $links;
}
function decodeDbTexts()
{
	$sql = 'SELECT id, description, usg
		FROM ' . $this->table('Dictionary');
	$result = $this->db->array_query_assoc($sql);

	$spec = &moon::shared('spec_symbols');

	foreach ($result as $item) {
		$upd = array();
		$upd['description_raw'] = $spec->decode($item['description']);
		$upd['description_html'] = $spec->urlToAHref($spec->encode($item['description'], $make_urls=0, $kiek=80, $specChars=0, $keiks=0));
		$upd['usg_raw'] = $spec->decode($item['usg']);
		$upd['usg_html'] = $spec->urlToAHref($spec->encode($item['usg'], $make_urls=0, $kiek=80, $specChars=0, $keiks=0));

		$this->db->update($upd, $this->table('Dictionary'), array('id' => $item['id']));
	}
}
function fixDbUri() {
	$sql = 'SELECT id, uri
		FROM ' . $this->table('Dictionary');
	$result = $this->db->array_query_assoc($sql);

	foreach ($result as $item) {
		$upd = array();
		$upd['uri_new'] = preg_replace('/.*\/(.*)\.html?/', '$1', $item['uri']);
		$this->db->update($upd, $this->table('Dictionary'), array('id' => $item['id']));
	}
}

}
?>