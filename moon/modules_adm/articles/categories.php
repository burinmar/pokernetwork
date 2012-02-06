<?php
class categories extends moon_com {

function onload()
{
	// item form
	$this->formItem = &$this->form();
	$this->formItem->names('id', 'parent_id', 'category_type', 'title', 'uri', 'meta_keywords', 'meta_description', 'description', 'is_hidden');
}
function events($event, $par)
{
	switch ($event) {
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
	$page = &moon::page();
	$sitemap = moon::shared('sitemap');
	$submenu = $win->subMenu();

	$info = $tpl->parse_array('info');
	
	$prefix = '';
	switch ($this->get_var('articlesType')) {
		case 2:
			$prefix = $sitemap->getLink('strategy');
			break;
		case 1:
		default:
			$prefix = $sitemap->getLink('news');
			break;
	}
	
	$items = $this->getItems();
	$tmp = '';
	$itemsListHtml = $this->createTree($items, 0, $tmp);
	
	$m = array(
		'viewList' => TRUE,
		'submenu' => $submenu,
		'items' => $itemsListHtml,
		'pageTitle' => $win->current_info('title'),
		'addNew' => $info['addNew'],
		'goNew' => $this->linkas('#edit'),
		'goDelete' => $this->my('fullname') . '#delete'
	);
	
	// sorting
	$page->js('/js/tablednd_0_5.js');
	$subcategories = $this->getSubcategoryItems(0);
	$subcategoriesList = '';
	if (count($subcategories) > 1) {
		foreach ($subcategories as $item) {
			$item['classHidden'] = ($item['is_hidden'] == 1) ? 'class="item-hidden"' : '';
			$subcategoriesList .= $tpl->parse('item.subcat', $item);
		}
	}
	$m['items.subcat'] = $subcategoriesList;
	$m['goSort'] = $this->my('fullname') . '#sort';

	return $tpl->parse('main', $m);
}
function renderForm($vars)
{
	$tpl = &$this->load_template();
	$win = &moon::shared('admin');
	$page = &moon::page();
	$sitemap = moon::shared('sitemap');
	$info = $tpl->parse_array('info');
	$submenu = $win->subMenu();

	$err = ($vars['error'] !== FALSE) ? $vars['error'] : FALSE;

	$form = $this->formItem;
	$title = $form->get('id') ? $info['titleEdit'] . ' :: ' . $form->get('title') : $info['titleNew'];
	
	$m = array(
		'viewList' => FALSE,
		'submenu' => $submenu,
		'error' => ($err !== FALSE) ? $info['error' . $err] : '',
		'event' => $this->my('fullname') . '#save',
		'id' => $form->get('id'),
		'goBack' => $this->linkas('#'),
		'pageTitle' => $win->current_info('title'),
		'formTitle' => htmlspecialchars($title),
		'uriPrefix' => $this->getMyUriPrefix(),
		'refresh' => $page->refresh_field(),
	) + $form->html_values();
	$m['is_hidden'] = $form->checked('is_hidden', 1);
	
	$items = $this->getItems();
	if ($form->get('id')) {
		unset($items[$form->get('id')]);
	}
	
	// allow only for news
	if ($m['enableParentCategories'] = ($this->get_var('articlesType') == 1)) {
		$dummy = array();
		$tree = $this->getTree($items, 0, $dummy);
		$m['optCategories'] = !empty($tree) ? $form->options('parent_id', $tree) : '';
	}
	
	// sorting
	$page->js('/js/tablednd_0_5.js');
	$subcategories = $this->getSubcategoryItems($form->get('id'));
	$subcategoriesList = '';
	if (count($subcategories) > 1) {
		foreach ($subcategories as $item) {
			$item['classHidden'] = ($item['is_hidden'] == 1) ? 'class="item-hidden"' : '';
			$subcategoriesList .= $tpl->parse('item.subcat', $item);
		}
	}
	$m['items.subcat'] = $subcategoriesList;
	$m['goSort'] = $this->my('fullname') . '#sort';
	
	return $tpl->parse('main', $m);
}
function getItems()
{
	$sql = 'SELECT c.id, c.parent_id, c.title, c.uri, c.is_hidden, count(a.id) as articlesCount
		FROM ' . $this->table('ArticlesCategories') . ' as c
		     	LEFT JOIN ' . $this->table('Articles') . ' as a
		     		ON c.id = a.category_id
		WHERE	c.category_type = ' . $this->get_var('articlesType') . ' AND
		     	c.is_hidden < 2
		GROUP BY c.id
		ORDER BY sort_order ASC, c.id ASC';
	$result = $this->db->array_query_assoc($sql, 'id');
	return $result;
}
function getItemsCount()
{
	$sql = 'SELECT count(*) as cnt
		FROM ' . $this->table('ArticlesCategories') . '
		WHERE 	category_type = ' . $this->get_var('articlesType') . ' AND
			is_hidden < 2';
	$result = $this->db->single_query_assoc($sql);
	return $result['cnt'];
}
function getItem($id)
{
	$sql = 'SELECT *
		FROM ' . $this->table('ArticlesCategories') . '
		WHERE id = ' . intval($id);
	return $this->db->single_query_assoc($sql);
}
function getSubcategoryItems($parentId = null)
{
	$items = array();
	if ($parentId !== null) {
		$sql = 'SELECT id, parent_id, title, sort_order, is_hidden
			FROM	' . $this->table('ArticlesCategories') . '
			WHERE is_hidden < 2 AND parent_id = ' . intval($parentId) . ' AND category_type = ' . $this->get_var('articlesType') . '
			ORDER BY sort_order, id ASC';
		$items = $this->db->array_query_assoc($sql);
	}
	return $items;
}
function getTree($items, $currentParent, &$tree, $currLevel = 0, $prevLevel = -1)
{
	foreach ($items as $categoryId => $category) {
		if ($currentParent == $category['parent_id']) {
			$title = $category['title'];
			for ($i = 0;$i < $currLevel;$i++) {
				$title = ' -- ' . $title;
			}
			
			$tree[$categoryId] = $title;
			
			if ($currLevel > $prevLevel) { 
				$prevLevel = $currLevel;
			}
			$currLevel++;
		 	$this->getTree($items, $categoryId, $tree, $currLevel, $prevLevel);
		 	$currLevel--;
		}
	}
	return $tree;
}
function createTree($array, $currentParent, &$html, $currLevel = 0, $prevLevel = -1)
{
	$prefix = $this->getMyUriPrefix();
	
	$tpl = &$this->load_template();
	foreach ($array as $categoryId => $category) {
		if ($currentParent == $category['parent_id']) {
			
			$goEdit = $this->linkas('#edit','{id}');
			$tpl->save_parsed('item',array('goEdit' => $goEdit));
			
			$item = $category;
			$item['id'] = $categoryId;
			$item['title'] = htmlspecialchars($item['title']);
			$item['uriPrefix'] = $prefix;
			$item['indentLeft'] = $currLevel * 30;
			$item['classHidden'] = ($item['is_hidden'] == 1) ? 'class="item-hidden"' : '';
			$html .= $tpl->parse('item', $item);
			
			if ($currLevel > $prevLevel) { $prevLevel = $currLevel; }
			$currLevel++;
			$this->createTree($array, $categoryId, $html, $currLevel, $prevLevel);
			$currLevel--;
		}
	}
	return $html;
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
	$data['id'] = intval($values['id']);
	$data['parent_id'] = intval($values['parent_id']);
	$data['title'] = strip_tags($values['title']);
	$data['uri'] = str_replace('/', '', $values['uri']);
	$data['meta_keywords'] = $values['meta_keywords'];
	$data['meta_description'] = $values['meta_description'];
	$data['description'] = $values['description'];
	$data['is_hidden'] = (empty($values['is_hidden'])) ? 0 : 1;
	$id = $data['id'];

	// Validation
	$errorMsg = 0;
	if ($data['title'] == '') {
		$errorMsg = 1;
	} elseif ($data['uri'] == '') {
		$errorMsg = 2;
	} else {
		//check for uri duplicates
		$sql = 'SELECT count(*) as cnt
			FROM ' . $this->table('ArticlesCategories') . '
			WHERE	is_hidden < 2 AND
			     	uri = \'' . $this->db->escape($data['uri']) . '\' AND
			     	category_type = ' . $this->get_var('articlesType') . ' AND
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

	$ins = $form->get_values('title', 'uri', 'meta_keywords', 'meta_description', 'description', 'is_hidden', 'parent_id');
	$ins['category_type'] = $this->get_var('articlesType');
	
	if ($id) {
		$res = $this->db->single_query_assoc('
			SELECT parent_id
			FROM ' . $this->table('ArticlesCategories') . '
			WHERE id = ' . $id
		);
		$newParent = isset($res['parent_id']) && ($res['parent_id'] != $ins['parent_id']);
		
		$this->db->update($ins, $this->table('ArticlesCategories'), array('id' => $id));
		
		// log this action
		blame($this->my('fullname'), 'Updated', $id);
	} else {
		$id = $this->db->insert($ins, $this->table('ArticlesCategories'), 'id');
		
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
	$this->db->query('UPDATE ' . $this->table('ArticlesCategories') . ' SET is_hidden = 2 WHERE id IN (' . implode(',', $ids) . ')');

	// log this action
	blame($this->my('fullname'), 'Deleted', $ids);

	return TRUE;
}
function updateSortOrder($when)
{
	$sql = 'UPDATE ' . $this->table('ArticlesCategories') . '
		SET sort_order =
			CASE
			' . $when . '
			END
		WHERE category_type = ' . $this->get_var('articlesType') . '
	';
	$this->db->query($sql);
}

// article type specific
function getMyUriPrefix()
{
	return moon::shared('sitemap')->getLink('news');
}

}

?>