<?php
class sitemap_sort extends moon_com {

function events($event, $par)
{
	switch ($event) {

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

function main($vars)
{
	$page = &moon::page();
	$win = &moon::shared('admin');
	$win->active($this->my('fullname'));
	$title = $win->current_info('title');
	$page->title($title);

	$tpl = &$this->load_template();
	$win = &moon::shared('admin');
	$page = &moon::page();

	if ($this->canEditAll()) {
		$page->js('/js/tablednd_0_5.js');
	}

	$submenu = $win->subMenu();

	$items = $this->getItems();
	$itemsList = '';
	foreach ($items as $item) {
		$item['uriMenuTab'] = (substr($item['uri'], 0, 1) == '/') ? $page->home_url() . substr($item['uri'], 1) : $page->home_url() . $item['uri'];
		$item['classHidden'] = ($item['hide'] == 1) ? 'class="item-hidden"' : '';
		$itemsList .= $tpl->parse('item', $item);
	}

	$main = array();
	$main['submenu'] = $submenu;
	$main['items'] = $itemsList;
	$main['pageTitle'] = $win->current_info('title');
	$main['goSort'] = $this->my('fullname') . '#sort';

	return $tpl->parse('main', $main);
}
function getItems()
{
	$sql = 'SELECT id, title, uri, hide
		FROM ' . $this->table('Pages') . '
		WHERE 	active_tab = 1 AND parent_id = 0 AND is_deleted = 0
		ORDER BY sort ASC';
	$result = $this->db->array_query_assoc($sql);
	return $result;
}

function updateSortOrder($rows) {
		$rows = explode(';',$rows);
		$order = array();
		$when = '';
		$i = 1;
		$ids = array();
		foreach ($rows as $id) {
			$key = intval(substr($id, 3));
			if (!$key) continue;
			$ids[] = $key;
			$when .= 'WHEN id = ' . $key . ' THEN ' . $i++ . ' ';
		}
		if (count($ids)) {
	    	$sql = 'UPDATE ' . $this->table('Pages') . '
				SET sort =
					CASE
					' . $when . '
					END
				WHERE id IN (' . implode(', ', $ids) . ')';
			$this->db->query($sql);
		}
	}
function canEditAll() {
	$user = &moon::user();
	return $user->i_admin('developer');
}

}

?>