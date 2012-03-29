<?php
class categories extends moon_com {

function onload()
{
	// item form
	$this->formItem = &$this->form();
	$this->formItem->names('id', 'name', 'uri', 'short_description', 'filter_tags', 'is_hidden');

}
function events($event, $par)
{
	switch ($event) {
		case 'edit':
			$id = isset($par[0]) ? sprintf("%.0f", $par[0]) : 0;
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
		case 'sort':
			$itemId = 0;
			if (isset($_POST['rows'])) {
				$rows = explode(';', $_POST['rows']);
				if (!is_array($rows)) {
					exit;
				}
				$order = array();
				$when = '';
				$i = 1;
				foreach ($rows as $k => $id) {
					$key = substr($id, 3);
					$itemId = $key;
					if ($key == '') continue;
					$when .= 'WHEN id = ' . $key . ' THEN ' . $i++ . ' ';
				}
				$this->updateSortOrder($when);
			}
			break;
		default:
			break;
	}

	if (isset($_GET['save-order'])) {
		$itemId = 0;
		if (isset($_GET['sorting-tbl'])) {
			if (!is_array($_GET['sorting-tbl'])) {
				exit;
			}
			$order = array();
			$when = '';
			$i = 1;
			foreach ($_GET['sorting-tbl'] as $k => $id) {
				$key = substr($id, 5);
				$itemId = $key;
				if ($key == '') continue;
				$when .= 'WHEN id = ' . $key . ' THEN ' . $i++ . ' ';
			}
			$this->updateSortOrder($when);
		}
		$page = &moon::page();
		$page->redirect($this->linkas('#'));
	}

	$this->use_page('Common');
}
function properties()
{
	$vars = array();
	$vars['view'] = 'list';
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
	$sitemap = &moon::shared('sitemap');
	$page = &moon::page();

	$page->js('/js/tablednd_0_5.js');

	$submenu = $win->subMenu();

	$info = $tpl->parse_array('info');
	$goEdit = $this->linkas('#edit','{id}');
	$tpl->save_parsed('item',array('goEdit' => $goEdit));
	
	$uriPrefix = $sitemap->getLink('video');
	
	$items = $this->getItems();
	$itemsList = '';
	foreach ($items as $item) {
		$item['uriPrefix'] = $uriPrefix;
		$item['classHidden'] = ($item['is_hidden'] == 1) ? 'class="item-hidden"' : '';
		$itemsList .= $tpl->parse('item', $item);
	}

	$main = array();
	$main['viewList'] = TRUE;
	$main['submenu'] = $submenu;
	$main['items'] = $itemsList;
	$main['pageTitle'] = $win->current_info('title');
	$main['goDelete'] = $this->my('fullname') . '#delete';
	$main['goSort'] = $this->my('fullname') . '#sort';

	return $tpl->parse('main', $main);
}
function renderForm($vars)
{
	$tpl = &$this->load_template();
	$win = &moon::shared('admin');
	$sitemap = &moon::shared('sitemap');
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
	$main['goBack'] = $this->linkas('#');
	$main['pageTitle'] = $win->current_info('title');
	$main['formTitle'] = htmlspecialchars($title);
	$main['uriPrefix'] = $sitemap->getLink('video');
	$main['refresh'] = $page->refresh_field();
	$main += $form->html_values();
	$main['is_hidden'] = $form->checked('is_hidden', 1);

	return $tpl->parse('main', $main);
}
function getItems()
{
	$sql = 'SELECT p.id, p.name, p.uri, p.is_hidden, count(v.id) as videosCount
		FROM ' . $this->table('VideosPlaylists') . ' as p
			LEFT JOIN ' . $this->table('Videos') . ' as v
			ON FIND_IN_SET(p.id, v.playlist_ids)
		WHERE p.is_hidden < 2
		GROUP BY p.id
		ORDER BY sort_order ASC';
	$result = $this->db->array_query_assoc($sql);
	return $result;
}
function getItemsCount()
{
	$sql = 'SELECT count(*) as cnt
		FROM ' . $this->table('VideosPlaylists') . '
		WHERE is_hidden < 2';
	$result = $this->db->single_query_assoc($sql);
	return $result['cnt'];
}
function getItem($id)
{
	$sql = 'SELECT *
		FROM ' . $this->table('VideosPlaylists') . '
		WHERE id = ' . sprintf("%.0f", $id);
	return $this->db->single_query_assoc($sql);
}
/**
 * strip multiple slashes at the end of uri field
 */
function saveItem()
{
	$form = &$this->formItem;
	$form->fill($_POST);
	$values = $form->get_values();
	// Filtering
	$data['id'] = sprintf("%.0f", $values['id']);
	$data['name'] = strip_tags($values['name']);
	$data['uri'] = str_replace('/', '', $values['uri']);
	$data['short_description'] = $values['short_description'];
	$data['is_hidden'] = (empty($values['is_hidden'])) ? 0 : 1;
	$id = $data['id'];

	// Validation
	$errorMsg = 0;
	if ($data['name'] == '') {
		$errorMsg = 1;
	} elseif ($data['uri'] == '') {
		$errorMsg = 2;
	} else {
		//check for uri duplicates
		$sql = 'SELECT count(*) as cnt
			FROM ' . $this->table('VideosPlaylists') . '
			WHERE 	is_hidden < 2 AND
				uri = \'' . $this->db->escape($data['uri']) . '\' AND
				id <> ' . $id;
		$result = $this->db->single_query_assoc($sql);
		if ($result['cnt'] != 0) {
			$errorMsg = 3;
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

	$ins = $form->get_values('name', 'uri', 'short_description', 'is_hidden');

	if ($id) {
		$this->db->update($ins, $this->table('VideosPlaylists'), array('id' => $id));
		
		// log this action
		blame($this->my('fullname'), 'Updated', $id);
	} else {
		$errorMsg = 9;
		$this->set_var('error', $errorMsg);
		return FALSE;
		
		//$id = $this->db->insert($ins, $this->table('ArticlesCategories'), 'id');
		
		// log this action
		//blame($this->my('fullname'), 'Created', $id);
	}

	$form->fill(array('id' => $id));
	return $id;
}
function deleteItem($ids)
{
	if (!is_array($ids) || !count($ids)) return;
	foreach ($ids as $k => $v) {
		$ids[$k] = sprintf("%.0f", $v);
	}
	$this->db->query('UPDATE ' . $this->table('VideosPlaylists') . ' SET is_hidden = 2 WHERE id IN (' . implode(',', $ids) . ')');

	// log this action
	blame($this->my('fullname'), 'Deleted', $ids);

	return TRUE;
}
function updateSortOrder($when)
{
	$sql = 'UPDATE ' . $this->table('VideosPlaylists') . '
		SET sort_order =
			CASE
			' . $when . '
			END';
	$this->db->query($sql);
}

}

?>