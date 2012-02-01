<?php
class tags extends moon_com {

function onload()
{
	$this->filter = array();
	$this->formFilter = &$this->form();
	$this->formFilter->names('name');

	$this->formItem = &$this->form();
	$this->formItem->names('id', 'name', 'description', 'is_hidden');

	$this->sqlWhere = ''; // set by filter
	$this->sqlOrder = '';
	$this->sqlLimit = ''; // set by paging
}
function events($event, $par)
{
	switch ($event) {
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
		'error' => FALSE
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

	$filter = $this->getFilter();
	$ordering = $this->getOrdering();
	$paging = $this->getPaging($vars['currPage'], $this->getItemsCount(), $vars['listLimit']);
	
	$info = $tpl->parse_array('info');
	$goEdit = $this->linkas('#edit','{id}');
	$tpl->save_parsed('item',array('goEdit' => $goEdit));

	$items = $this->getItems();
	$itemsList = '';
	foreach ($items as $item) {
		$item['name'] = htmlspecialchars($item['name']);
		$item['classHidden'] = ($item['is_hidden'] == 1) ? 'class="item-hidden"' : '';
		$item['status'] = ($item['is_hidden'] == 1) ? 'Hidden' : 'Published';
		$itemsList .= $tpl->parse('item', $item);
	}

	$m = array(
		'viewList' => TRUE,
		'filter' => $tpl->parse('filter', $filter),
		'items' => $itemsList,
		'paging' => $paging,
		'pageTitle' => $win->current_info('title'),
		'addNew' => $info['addNew'],
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
	$info = $tpl->parse_array('info');

	$err = ($vars['error'] !== FALSE) ? $vars['error'] : FALSE;

	$form = $this->formItem;
	$title = $form->get('id') ? $info['titleEdit'] . ' :: ' . $form->get('name') : $info['titleNew'];

	$m = array(
		'viewList' => FALSE,
		'error' => ($err !== FALSE) ? $info['error' . $err] : '',
		'event' => $this->my('fullname') . '#save',
		'id' => $form->get('id'),
		'goBack' => $this->linkas('#'),
		'pageTitle' => $win->current_info('title'),
		'formTitle' => htmlspecialchars($title),
		'refresh' => $page->refresh_field(),
	) + $form->html_values();
	$m['is_hidden'] = $form->checked('is_hidden', 1);
	return $tpl->parse('main', $m);
}
function getItems()
{
	$sql = 'SELECT id, name, is_hidden
		FROM	' . $this->table('Tags') . ' ' .
		$this->sqlWhere . ' ' .
		$this->sqlOrder . ' ' .
		$this->sqlLimit;
	$result = $this->db->array_query_assoc($sql);
	return $result;
}
function getItemsCount()
{
	$sql = 'SELECT count(*) as cnt
		FROM ' . $this->table('Tags') . ' ' .
		$this->sqlWhere;
	$result = $this->db->single_query_assoc($sql);
	return $result['cnt'];
}
function getItem($id)
{
	$sql = 'SELECT *
		FROM ' . $this->table('Tags') . '
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
	$data['description'] = $values['description'];
	$data['is_hidden'] = (empty($values['is_hidden'])) ? 0 : 1;
	$id = $data['id'];

	// Validation
	$errorMsg = 0;
	if ($data['name'] == '') {
		$errorMsg = 1;
	} else {
		//check for tag duplicates
		$sql = 'SELECT count(*) as cnt
			FROM ' . $this->table('Tags') . '
			WHERE 	is_hidden < 2 AND
				name = \'' . $this->db->escape($data['name']) . '\' AND
				id <> ' . $id;
		$result = $this->db->single_query_assoc($sql);
		if ($result['cnt'] != 0) {
			$errorMsg = 2;
		}
	}

	if ($errorMsg) {
		$this->set_var('error', $errorMsg);
		return FALSE;
	}

	// if was refresh skip other steps and return
	if ($form->was_refresh()) {
		return $id;
	}

	$ins = $form->get_values('name', 'description', 'is_hidden');

	if ($id) {
		$this->db->update($ins, $this->table('Tags'), array('id' => $id));

		// log this action
		blame($this->my('fullname'), 'Updated', $id);
	} else {
		$id = $this->db->insert($ins, $this->table('Tags'), 'id');

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
	$this->db->query('UPDATE ' . $this->table('Tags') . ' SET is_hidden = 2 WHERE id IN (' . implode(',', $ids) . ')');

	// log this action
	blame($this->my('fullname'), 'Deleted', $ids);
	
	return TRUE;
}
function setSqlWhere()
{
	$where = array();
	$where[] = 'WHERE is_hidden < 2';

	if (!empty($this->filter)) {
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
		$sort = 1;
	}

	$links = array();
	$pn = &moon::shared('paginate');
	$ord = &$pn->ordering();
	$ord->set_values(
		//laukai, ir ju defaultine kryptis
		array('name' => 1, 'is_hidden' => 0) ,
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

}
?>