<?php
class twitter_messages extends moon_com {

function onload()
{
	$this->filter = array();
	$this->formFilter = &$this->form();
	$this->formFilter->names('name', 'message');

	$this->formItem = &$this->form('item');
	$this->formItem->names('message_id', 'name', 'message', 'is_hidden', 'screen_name', 'is_last_message');

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
			$id = isset($par[0]) ? $par[0] : 0;
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
		case 'toggle-featured':
			$this->forget();
			$page = &moon::page();
			if (!empty($par[0])) {
				$this->db->query('
					UPDATE ' . $this->table('TwitterMessages') . ' SET is_featured = 0'
				);
				$this->db->query('
					UPDATE ' . $this->table('TwitterMessages') . '
					SET is_featured = !is_featured
					WHERE message_id = ' . $this->db->escape($par[0])
				);
				$page->back(1);
			} else {
				$page->page404();
			}
			break;
		/*case 'delete':
			if (isset($_POST['it'])) $this->deleteItem($_POST['it']);
			$this->redirect('#');
			break*/;
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
	$vars['listLimit'] = '40';
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

	$submenu = $win->subMenu();

	$ordering = $this->getOrdering();
	$filter = $this->getFilter();
	$paging = $this->getPaging($vars['currPage'], $this->getItemsCount(), $vars['listLimit']);

	$goEdit = $this->linkas('#edit','{message_id}');
	$tpl->save_parsed('items',array('goEdit' => $goEdit));

	$items = $this->getItems();
	$itemsList = '';
	foreach ($items as $item) {
		$item['classHidden'] = ($item['is_hidden'] == 1) ? 'class="item-hidden"' : '';
		$item['created'] = date('Y-m-d H:s', $item['created']);
		$item['url.setFeatured'] = $this->linkas('#toggle-featured', $item['message_id']);
		$itemsList .= $tpl->parse('items', $item);
	}

	$main = array();
	$main['submenu'] = $submenu;
	$main['viewList'] = TRUE;
	$main['filter'] = $tpl->parse('filter', $filter);
	$main['items'] = $itemsList;
	$main['paging'] = $paging;
	$main['pageTitle'] = $win->current_info('title');
	$main['goNew'] = $this->linkas('#edit');
	$main['goDelete'] = $this->my('fullname') . '#delete';
	$main += $ordering;

	return $tpl->parse('main', $main);
}
function renderForm($vars)
{
	$tpl = &$this->load_template();
	$win = &moon::shared('admin');
	$page = &moon::page();

	$submenu = $win->subMenu();
	$info = $tpl->parse_array('info');

	$err = ($vars['error'] !== FALSE) ? $vars['error'] : FALSE;

	$form = $this->formItem;
	$editTitle = ($form->get('name') != '') ? $form->get('name') : $form->get('screen_name');
	$title = $form->get('message_id') ? $info['titleEdit'] . ' :: ' . $editTitle : $info['titleNew'];

	$main = array();
	$main['submenu'] = $submenu;
	$main['viewList'] = FALSE;
	$main['error'] = ($err !== FALSE) ? $info['error' . $err] : '';
	$main['event'] = $this->my('fullname') . '#save';
	$main['message_id'] = $form->get('message_id');
	$main['goBack'] = $this->linkas('#');
	$main['pageTitle'] = $win->current_info('title');
	$main['formTitle'] = htmlspecialchars($title);
	$main['refresh'] = $page->refresh_field();
	$main += $form->html_values();

	$main['is_hidden'] = $form->checked('is_hidden', 1);
	return $tpl->parse('main', $main);
}
function getItems()
{
	$sql = 'SELECT message_id, name, message, created, is_hidden, is_featured
		FROM ' . $this->table('TwitterMessages') . ' ' .
		$this->sqlWhere . ' ' .
		$this->sqlOrder . ' ' .
		$this->sqlLimit;
	$result = $this->db->array_query_assoc($sql);
	return $result;
}
function getItemsCount()
{
	$sql = 'SELECT count(*) as cnt
		FROM ' . $this->table('TwitterMessages') . ' ' .
		$this->sqlWhere;
	$result = $this->db->single_query_assoc($sql);
	return $result['cnt'];
}
function getItem($id)
{
	$sql = 'SELECT *
		FROM ' . $this->table('TwitterMessages') . '
		WHERE message_id = ' . $this->db->escape($id);
	return $this->db->single_query_assoc($sql);
}
function saveItem()
{
	$form = &$this->formItem;
	$form->fill($_POST);
	$values = $form->get_values();

	// Filtering
	$data['message_id'] = $values['message_id'];
	$data['name'] = strip_tags($values['name']);
	$data['message'] = $values['message'];
	if ($data['message'] === '') {
		$data['message'] = NULL;
	}
	$data['is_hidden'] = (empty($values['is_hidden'])) ? 0 : 1;
	$id = $data['message_id'];

	// Validation
	$errorMsg = 0;
	if ($data['name'] == '') {
		$errorMsg = 1;
	} elseif (!$data['message']) {
		$errorMsg = 2;
	}

	if ($errorMsg) {
		$this->set_var('error', $errorMsg);
		return FALSE;
	}

	// if was refresh skip other steps and return
	if ($wasRefresh = $form->was_refresh()) {
		return $id;
	}

	$ins = $form->get_values('name', 'message', 'is_hidden');

	if ($id) {
		$this->db->update($ins, $this->table('TwitterMessages'), array('message_id' => $id));

		// check if last message is not hidden
		$this->db->query('UPDATE ' . $this->table('TwitterMessages') . ' SET is_last_message = 0 WHERE screen_name = \'' . $values['screen_name'] . '\'');

		// set is_last_message, make sure its not hidden
		$res = $this->db->single_query_assoc('
			SELECT max(message_id) as id
			FROM ' . $this->table('TwitterMessages') . '
			WHERE screen_name = \'' . $values['screen_name'] . '\' AND
			is_hidden = 0'
		);
		$lastId = !empty($res['id']) ? $res['id'] : 0;
		if ($lastId) {
			$this->db->query('
				UPDATE ' . $this->table('TwitterMessages') . '
				SET is_last_message = 1
				WHERE message_id = ' . $lastId
			);
		}

		// log this action
		blame($this->my('fullname'), 'Updated', $id);
	} else {
		//$id = $this->db->insert($ins, $this->table('TwitterMessages'), 'id');

		// log this action
		//blame($this->my('fullname'), 'Created', $id);
	}

	$form->fill(array('id' => $id));
	return $id;
}
/*function deleteItem($ids)
{
	if (!is_array($ids) || !count($ids)) return;
	foreach ($ids as $k => $v) {
		$ids[$k] = intval($v);
	}
	$this->db->query('DELETE FROM ' . $this->table('TwitterPlayers') . ' WHERE id IN (' . implode(',', $ids) . ')');

	// log this action
	blame($this->my('fullname'), 'Deleted', $ids);
	return TRUE;
}*/
function setSqlWhere()
{
	$where = array();
	$where[] = 'WHERE 1';

	if (!empty($this->filter)) {
		if ($this->filter['name'] != '') {
			$where[] = 'name LIKE \'%' . $this->db->escape($this->filter['name']) . '%\'';
		}
		if ($this->filter['message'] != '') {
			$where[] = 'message LIKE \'%' . $this->db->escape($this->filter['message']) . '%\'';
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
	$filter['message'] = $this->formFilter->get('message');

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
		array('created' => 1, 'name' => 0),
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