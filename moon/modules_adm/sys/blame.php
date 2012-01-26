<?php
class blame extends moon_com {

function onload()
{
	$this->filter = array();
	$this->formFilter = &$this->form();
	$this->formFilter->names('user', 'module', 'action', 'item_id');

	$this->formItem = &$this->form('item');
	$this->formItem->names('id', 'user', 'component', 'file', 'action', 'id');

	$this->sqlWhere = ''; // set by filter
	$this->sqlOrder = '';
	$this->sqlLimit = ''; // set by paging

	$this->users = $this->getUsers();
}
function events($event, $par)
{
	switch ($event) {
		case 'filter':
			$this->setFilter();
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
	$vars = array();
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
	return $this->renderList($vars);
}
function renderList($vars)
{
	$tpl = &$this->load_template();
	$win = &moon::shared('admin');

	$ordering = $this->getOrdering();
	$filter = $this->getFilter();
	$paging = $this->getPaging($vars['currPage'], $this->getItemsCount(), $vars['listLimit']);

	$goEdit = $this->linkas('#edit','{id}');
	$tpl->save_parsed('items',array('goEdit' => $goEdit));

	$items = $this->getItems();
	$itemsList = '';
	foreach ($items as $item) {
		$item['date'] = date('Y-m-d', $item['time']);
		$item['time'] = date('H:i', $item['time']);
		$item['user'] = array_key_exists($item['user_id'], $this->users) ? $this->users[$item['user_id']] : '';
		$item['userUrl'] = '/adm/users-admins/edit/' . $item['user_id'] . '.htm';
		$component = explode('.', $item['component']);
		$item['module'] = (isset($component[0])) ? ucfirst($component[0]) : $item['component'];

		$actionStyle = '';
		if (strcasecmp($item['action'], 'Created') == 0) {
			$actionStyle = 'style="background-color:#CBF9C2;"';
		} elseif (strcasecmp($item['action'], 'Updated') == 0) {
			$actionStyle = 'style="background-color:#F7E8C6;"';
		} elseif (strcasecmp($item['action'], 'Deleted') == 0) {
			$actionStyle = 'style="background-color:#FECFC0;"';
		}
		$item['actionStyle'] = $actionStyle;

		$item['itemUri'] = '';
		if ($item['action'] != 'Deleted' AND is_numeric($item['item_id']) AND strpos($item['component'], ' ') === FALSE) {
			// @todo: review. maybe save item uri in db
			$item['itemUri'] = '/adm/' . str_replace('.', '-', $item['component']) . '/edit/' . $item['item_id'] . '.htm';
		}

		$itemsList .= $tpl->parse('items', $item);
	}

	$main = array();
	$main['filter'] = $tpl->parse('filter', $filter);
	$main['items'] = $itemsList;
	$main['paging'] = $paging;
	$main['pageTitle'] = $win->current_info('title');
	$main['goDelete'] = $this->my('fullname') . '#delete';
	$main += $ordering;

	return $tpl->parse('main', $main);
}
function getItems()
{
	$sql = 'SELECT id, time, user_id, component, action, item_id
		FROM ' . $this->table('Blame') . ' ' .
		$this->sqlWhere . ' ' .
		$this->sqlOrder . ' ' .
		$this->sqlLimit;
	$result = $this->db->array_query_assoc($sql);
	return $result;
}
function getItemsCount()
{
	$sql = 'SELECT count(*) as cnt
		FROM ' . $this->table('Blame') . ' ' .
		$this->sqlWhere;
	$result = $this->db->single_query_assoc($sql);
	return $result['cnt'];
}
function deleteItem($ids)
{
	if (!is_array($ids) || !count($ids)) return;
	foreach ($ids as $k => $v) {
		$ids[$k] = intval($v);
	}
	$this->db->query('DELETE FROM ' . $this->table('Blame') . ' WHERE id IN (' . implode(',', $ids) . ')');
	return TRUE;
}
function getModules() {

	$w = & moon :: shared('admin');
	$items = $w->children();
	$mod = array();
	foreach ($items as $item) {
		$uriChunks = explode('/', $item['url']);
		if (isset($uriChunks[2])) {
			$module = explode('-', $uriChunks[2]);
			$m = (isset($module[1])) ? $module[0] : 'sys';
			$mod[$m] = ucfirst($m);
		}
	}
	return $mod;
}
function getUsers() {
	return array('-1'=>'admin');
	$sql = 'SELECT u.nick, u.id
		FROM ' . $this->table('Blame') . ' b LEFT JOIN ' . $this->table('Users') . ' u ON b.user_id=u.id
		GROUP BY b.user_id
		ORDER BY u.nick';
	$result = $this->db->array_query_assoc($sql);

	$items = array();
	foreach ($result as $r) {
		$items[$r['id']] = $r['nick'];
	}
	return $items;
}
function getActions() {
	$actions = array();
	$actions[0]['id'] = 'Created';
	$actions[0]['title'] = 'Created';
	$actions[1]['id'] = 'Updated';
	$actions[1]['title'] = 'Updated';
	$actions[2]['id'] = 'Deleted';
	$actions[2]['title'] = 'Deleted';
	$items = array();
	foreach ($actions as $a) {
		$items[$a['id']] = $a['title'];
	}
	return $items;
}
function setSqlWhere()
{
	$where = array();
	$where[] = 'WHERE 1';
	if (!empty($this->filter)) {
		if ($this->filter['module'] != '') {
			$where[] = 'component LIKE \'' . $this->filter['module'] . '%\'';
		}
		if ($this->filter['user_id'] != '') {
			$where[] = 'user_id = ' . $this->filter['user_id'];
		}
		if ($this->filter['action'] != '') {
			$where[] = 'action = \'' . $this->filter['action'] . '\'';
		}
		if ($this->filter['item_id'] != '') {
			$where[] = 'FIND_IN_SET(\'' . $this->filter['item_id'] . '\', item_id)';
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
	$filter['action'] = $this->formFilter->get('action');
	$actions = $this->getActions();
	$filter['actions'] = $this->formFilter->options('action', $actions);
	$filter['user_id'] = $this->formFilter->get('user_id');
	$users = $this->users;
	$filter['users'] = $this->formFilter->options('user_id', $users);

	$filter['module'] = ($this->formFilter->get('component') != '') ? strstr($this->formFilter->get('component'), ',', TRUE) : '';
	$modules = $this->getModules();
	$filter['modules'] = $this->formFilter->options('module', $modules);

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
		array('time' => 0, 'user_id' => 1, 'action' => 1) ,
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