<?php
class sitemap extends moon_com {

function onload()
{
	$this->filter = array();
	$this->formFilter = &$this->form();
	$this->formFilter->names('is_deleted');
	$this->sqlWhere = ''; // set by filter

	$this->formItem = &$this->form('item');
	$this->formItem->names('id', 'page_id', 'parent_id', 'room_id', 'uri', 'hide', 'xml', 'control', 'title', 'meta_title', 'meta_keywords', 'meta_description', 'sort', 'content_type', 'content', 'content_html', 'recompile', 'created', 'updated', 'options', 'is_deleted', 'changed_by', 'active_tab', 'menu_tab_class', 'css', 'geo_target');
}
function events($event, $par)
{
	switch ($event) {
		case 'filter':
			$this->setFilter();
			break;
		case 'edit':
			$id = isset($par[0]) ? intval($par[0]) : 0;
			if (!empty($_GET['link'])) {
				$this->formItem->fill(array('options'=>1, 'hide'=>0));
			}
			if ($id) {
				if (count($values = $this->getItem($id))) {
					$values['content_type'] = ($values['content'] != '' OR ($values['content'] == '' AND $values['content_html'] == '')) ? 'text' : 'html';
					$this->formItem->fill($values);
				}
				else {
					$this->set_var('error', '404');
				}
			} else {

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
		case 'save-link':
			if ($id=$this->saveLink()) {
				if (isset($_POST['return']) ) $this->redirect('#edit',$id);
				else $this->redirect('#');
			} else {
				$this->set_var('view','formLink');
			}
			break;
		case 'delete':
			if (isset($_POST['it'])) $this->deleteItem($_POST['it']);
			$this->redirect('#');
			break;
		case 'sort':
			if (isset ($_POST['rows'])) {
				$this->updateSortOrder($_POST['rows']);
			}
			$parentId = isset($_POST['parent_id']) ? $_POST['parent_id'] : 0;
			if ($parentId) {
				$this->redirect('#edit', $parentId);
			} else {
				$this->redirect('#');
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
	$vars['view'] = 'tree';
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
	if ($vars['view'] == 'form' && (1 & $this->formItem->get('options'))) {
		$vars['view'] = 'formLink';
	}

	if ($vars['view'] == 'form') {
		return $this->renderForm($vars);
	} elseif ($vars['view'] == 'formLink') {
		return $this->renderLinkForm($vars);
	}
	else {
		return $this->renderTree($vars);
	}
}
function renderTree($vars)
{
	$tpl = &$this->load_template();
	$info = $tpl->parse_array('info');
	$win = &moon::shared('admin');
	$submenu = $win->subMenu();

	$filter = $this->getFilter();
	$items = $this->getItems();
	$tmp = '';
	$html = $this->createTree($items, 0, $tmp);

	$main = array();
	$main['submenu'] = $submenu;
	$main['viewList'] = TRUE;
	$main['pageTitle'] = $win->current_info('title');
	$main['items'] = $html;
	$main['addNew'] = $info['addNew'];
	$main['goNew'] = $this->linkas('#edit');
	$main['goDelete'] = $this->my('fullname') . '#delete';
	$main['canEditAll'] = $this->canEditAll();
	$main['goNewLink']=$this->linkas('#edit',FALSE,'link=1');
	$main['filter'] = $tpl->parse('filter', $filter);

	return $tpl->parse('main', $main);
}
function renderForm($vars)
{
	$tpl = &$this->load_template();
	$win = &moon::shared('admin');
	$page = &moon::page();
	$info = $tpl->parse_array('info');

	//$page->js('/js/modules_adm/navigation.sitemap_sort.js');

	$err = ($vars['error'] !== FALSE) ? $vars['error'] : FALSE;

	$form = $this->formItem;
	$title = $form->get('id') ? $info['titleEdit'] . ' :: ' . $form->get('title') : $info['titleNew'];

	$main = array();
	$main['viewList'] = FALSE;
	$main['error'] = ($err !== FALSE) ? $info['error' . $err] : '';
	$main['event'] = $this->my('fullname') . '#save';
	$main['id'] = $id = $form->get('id');
	$main['goBack'] = $this->linkas('#');
	$main['pageTitle'] = $win->current_info('title');
	$main['formTitle'] = htmlspecialchars($title);

	$main['refresh'] = $page->refresh_field();
	$main['toolbar'] = '';
	$main += $form->html_values();

	$main['showViewPageLink'] = FALSE;
	$main['showLastEdited'] = FALSE;
	if (is_numeric($form->get('changed_by')) and $this->canEditAll()) {
		$main['lastEditedTime'] = date('Y-m-d H:i', $form->get('updated'));
		$main['lastEditedUsername'] = $this->getUserName($form->get('changed_by'));
		$main['showLastEdited'] = TRUE;
	}

	$main['hide'] = $form->checked('hide', 1);
	$main['show_is_deleted'] = $form->get('is_deleted');
	$main['is_deleted'] = $form->checked('is_deleted', 1);
	$main['active_tab'] = $form->checked('active_tab', 1);
	$main['limitedRights'] = !$this->canEditAll();
	$main['canEditAll'] = $this->canEditAll();

	$items = $this->getItems();
	$dummy = array();
	$tree = $this->getTree($items, 0, $dummy);
	if ($form->get('id')) {
		unset($tree[$form->get('id')]);
		$main['showViewPageLink'] = TRUE;
	}
	$main['optTree'] = $form->options('parent_id', $tree);

	// add toolbar
	if (is_object( $rtf = $this->object('rtf') )) {
		$rtf->setInstance( $this->get_var('rtf') );
		$main['toolbar'] = $rtf->toolbar('i_content',(int)$main['id']);
	}

	$displayToolbar = ($main['content'] != '' OR ($main['content'] == '' AND $main['content_html'] == ''));;

	$main['type-text'] = (is_numeric($form->get('id'))) ? $form->checked('content_type', 'text') : 'text" checked="checked';
	$main['type-html'] = $form->checked('content_type', 'html');
	$main['not-type-text-css'] = !$displayToolbar;
	$main['not-type-html-css'] = $displayToolbar;

	// audrius. TRUE pagal PN-1829
	if (TRUE || $this->canEditAll()) {
		$page->js('/js/tablednd_0_5.js');
		$subcategories = $this->getSubcategoryItems($id);
		$subcategoriesList = '';
		foreach ($subcategories as $item) {
			$item['is_link'] = $item['options'] & 1;
			$item['uriMenuTab'] = (substr($item['uri'], 0, 1) == '/') ? $page->home_url() . substr($item['uri'], 1) : $page->home_url() . $item['uri'];
			$item['classHidden'] = ($item['hide'] == 1) ? 'class="item-hidden"' : '';
			$subcategoriesList .= $tpl->parse('item.subcat', $item);
		}
		$main['items.subcat'] = $subcategoriesList;
		$main['goSort'] = $this->my('fullname') . '#sort';
	}

	return $tpl->parse('main', $main);
}
function renderLinkForm($vars) {
	//******* FORM **********
	$t = &$this->load_template();
	$win = &moon::shared('admin');
	$p = &moon::page();
	$info = $t->parse_array('info');
	$err=(isset($vars['error'])) ? $vars['error']:0;
	//$p->css($t->parse('cssForm'));

	$f = $this->formItem;
	$title= $f->get('id') ? $info['titleLinkEdit'].' :: '.$f->get('title') : $info['titleLinkNew'];
	$m = array(
		'error' => $err ? $info['error'.$err] : '',
		'event' => $this->my('fullname').'#save-link',
		'refresh' => $p->refresh_field(),
		'id' => ($id = $f->get('id')),
		'goBack' => $this->linkas('#'),

		'pageTitle' => $win->current_info('title'),
		'formTitle' => htmlspecialchars($title),
		'hide' =>$f->checked('hide',1),
		'geo_zones' => ''
	) + $f->html_values();
	$items = $this->getItems();
	$dummy = array();
	$tree = $this->getTree($items, 0, $dummy);
	$m['optTree'] = $f->options('parent_id', $tree);


	// geo zones
	/*include_once(MOON_CLASSES."geoip/geoip.inc");
	$gi=new GeoIP;
	$geo = geo_zones();
	foreach ($geo as $name=>$k) {
		$c  = strtoupper($name);
		$id = isset($gi->GEOIP_COUNTRY_CODE_TO_NUMBER[$c]) ? $gi->GEOIP_COUNTRY_CODE_TO_NUMBER[$c]:0;
		switch ($c) {
			case 'AA':
				$title= 'Austral-Asia';
				break;
			default:
				$title= $id ? $gi->GEOIP_COUNTRY_NAMES[$id] : NULL;
				break;
		}
		$m['geo_zones'].= $t->parse('geo_zones', array(
			'value'   => $name,
			'name'    => htmlspecialchars($title),
			'checked' => ($m['geo_target'] & (1<<$k))
		)). ' ';
	}
	$m['geo_zones'] .= $t->parse('geo_zones', array(
		'value'   => '*',
		'name'    => 'Other countries',
		'checked' => ($m['geo_target'] & 1)
	));*/

	return $t->parse('viewFormLink',$m);
}
function getItems()
{
	$sql = 'SELECT id, page_id, parent_id, title, uri, sort, hide, active_tab, xml, control, options, is_deleted, geo_target
		FROM	' . $this->table('Pages') . ' ' .
		$this->sqlWhere . '
		ORDER BY active_tab DESC, sort, title';
	$result = $this->db->array_query_assoc($sql);
	$categories = array();
	foreach ($result as $r) {
		$categories[$r['id']] = $r;
	}
	return $categories;
}
function getSubcategoryItems($parentId = null)
{
	$items = array();
	if ($parentId) {
		$sql = 'SELECT id, page_id, parent_id, title, uri, sort, hide, active_tab, xml, control, options, geo_target
			FROM	' . $this->table('Pages') . '
			WHERE is_deleted = 0 AND parent_id = ' . intval($parentId) . '
			ORDER BY sort, title';
		$items = $this->db->array_query_assoc($sql);
	}
	return $items;
}
function getItem($id)
{
	$sql = 'SELECT *
		FROM ' . $this->table('Pages') . '
		WHERE 	id = ' . intval($id);
	return $this->db->single_query_assoc($sql);
}
function saveItem()
{
	$canEditAll = $this->canEditAll();
	$postData = $_POST;

	$form = &$this->formItem;
	$form->fill($postData);
	$values = $form->get_values();

	// Filtering
	$data = array();
	$data = $values;
	$data['id'] = intval($values['id']);
	if ($data['content'] === '') {
		$data['content'] = NULL;
	}
	if ($data['uri'] === '') {
		$data['uri'] = NULL;
	}

	$data['hide'] = (empty($values['hide'])) ? 0 : 1;
	$data['active_tab'] = (empty($values['active_tab'])) ? 0 : 1;
	$id = $data['id'];
	// Validation
	$errorMsg = 0;
	if ($data['title'] == '') {
		$errorMsg = 1;
	} elseif (!is_object($rtf = $this->object('rtf'))) {
		$errorMsg = 9;
	} elseif ($canEditAll AND $data['uri'] != NULL) {
		//check for uri duplicates
		$sql = 'SELECT count(*) as cnt
			FROM ' . $this->table('Pages') . '
			WHERE 	uri = "' . $this->db->escape($data['uri']) . '" AND
				id <> ' . $id;
		$result = $this->db->single_query_assoc($sql);
		if ($result['cnt'] != 0) {
			$errorMsg = 5;
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

	$ins = array();
	if ($this->canEditAll()) {
		$ins = $form->get_values('parent_id', 'title', 'uri', 'meta_title', 'meta_keywords', 'meta_description', 'content', 'content_html', 'page_id', 'xml', 'control', 'hide', 'active_tab', 'menu_tab_class', 'css', 'is_deleted');
	} else {
		$ins = $form->get_values('title', 'uri', 'meta_title', 'meta_keywords', 'meta_description', 'content', 'content_html');
	}

	if ($ins['uri'] === '') {
		$ins['uri'] = NULL;
	}
	$ins['updated'] = time();
	$ins['changed_by'] = $this->getUserId();
	if (!$canEditAll) {
		unset($ins['uri']);
	}
	if ($postData['content_type'] == 'text') {
		$rtf->setInstance( $this->get_var('rtf') );
		list(,$ins['content_html']) = $rtf->parseText($id, $ins['content'], TRUE);
	} else {
		$ins['content'] = '';
	}

	if ($id) {
		$this->db->update($ins, $this->table('Pages'), array('id' => $id));

		// log this action
		blame($this->my('fullname'), 'Updated', $id);
	} else {
		$id = $this->db->insert($ins, $this->table('Pages'), 'id');

		// log this action
		blame($this->my('fullname'), 'Created', $id);
	}

	if ($id) {
		$form->fill(array('id' => $id));
	}

	if ($postData['content_type'] == 'text') {
		$rtf->assignObjects($id);
	}

	return $id;
}
function saveLink()
{
	$form=&$this->formItem;
	$form->fill($_POST);
	$d=$form->get_values();
	$id=intval($d['id']);

	//gautu duomenu apdorojimas
	$d['hide'] = isset($_POST['hide']) && $_POST['hide'] ? 1:0;
	$form->fill($d, false); //jei bus klaida

	$geoTarget = 0;
	if (isset($_POST['geo_target']) && is_array($_POST['geo_target'])) {
		$zones = geo_zones();
	   	foreach ($_POST['geo_target'] as $zone) {
		   	if (isset($zones[$zone])) {
		   		$bt = $zones[$zone];
		   	} elseif ('*' === $zone) {
		   		$bt = 0;
		   	} else {
		   		continue;
		   	}
			$geoTarget += 1 << ($bt);
		}
	}
	$geoTarget = (0 !== $geoTarget) ? $geoTarget : NULL;
	$form->fill(array('geo_target' => $geoTarget));

	//validacija
	$err=0;
	if ($d['title']==='') $err=1;
	elseif (empty($d['parent_id'])) $err=6;
	//uri
	elseif ($d['uri']==='') $err=2;
	elseif ($d['uri']) {
		//check for uri duplicates
		$sql = 'SELECT count(*) as cnt
			FROM ' . $this->table('Pages') . '
			WHERE 	uri = "' . $this->db->escape($d['uri']) . '" AND
				id <> ' . $id;
		$result = $this->db->single_query_assoc($sql);
		if ($result['cnt'] != 0) {
			$err = 5;
		}
		elseif (!$this->canEditAll()) {
			// tikrinam, ar ne per daug linku
			$m = $this->db->single_query('
				SELECT count(*) FROM ' . $this->table('Pages') . '
					WHERE hide = 0 AND is_deleted=0 AND (options & 1)
				'
				);
			if (!empty($m[0]) && $m[0]>20) {
				$err = 7;
			}
		}
	}
	if ($err) {
		$this->set_var('error',$err);
		return false;
	}

	//jei refresh, nesivarginam
	if ($wasRefresh=$form->was_refresh()) return $id;

	//save to database
	$ins=$form->get_values('parent_id', 'hide', 'uri', 'title', 'geo_target');
	$ins['options'] = 1;
	$ins['updated'] = time();
	$ins['changed_by'] = $this->getUserId();

	$db=&$this->db();
	if ($id) {
		$db->update_query($ins, $this->table('Pages'), array('id'=>$id));
		blame($this->my('fullname'), 'Updated', $id);
	}
	else {
		$ins['created'] = $ins['updated'];
		$id=$db->insert_query($ins, $this->table('Pages'), 'id');
		blame($this->my('fullname'), 'Created', $id);
	}
	$form->fill( array('id'=>$id) );
	return $id;
}

function deleteItem($ids)
{
	if (!is_array($ids) || !count($ids)) return;
	foreach ($ids as $k => $v) {
		$ids[$k] = intval($v);
	}
	$this->db->query('UPDATE ' . $this->table('Pages') . ' SET is_deleted = 1 WHERE id IN (' . implode(',', $ids) . ') OR parent_id IN (' . implode(',', $ids) . ')');

	// log this action
	blame($this->my('fullname'), 'Deleted', $ids);

	return TRUE;
}
function getTree($items, $currentParent, &$tree, $currLevel = 0, $prevLevel = -1)
{
	foreach ($items as $categoryId => $category) {
		if ($currentParent == $category['parent_id']) {

			$title = $category['title'];
			$pageId = $category['page_id'];
			for ($i = 0;$i < $currLevel;$i++) {
				$title = ' -- ' . $title;
			}

			if ($pageId)  {
				$title .= ' [' . $pageId . ']';
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
	$tpl = &$this->load_template();
	foreach ($array as $categoryId => $category) {
		if ($currentParent == $category['parent_id']) {

			$goEdit = $this->linkas('#edit','{id}');
			$tpl->save_parsed('item',array('goEdit' => $goEdit));

			$item = $category;
			$item['id'] = $categoryId;
			$item['indentLeft'] = $currLevel * 20;
			$item['activeTab'] = ($category['active_tab'] AND $category['parent_id'] == 0) ? '<span class="star">Parent</span>' : '&nbsp;';
			$item['classHidden'] = ($item['hide'] == 1) ? 'class="item-hidden"' : '';
			$item['markHasXml'] = ($item['xml'] != '');
			$item['markHasControl'] = ($item['control'] != '');
			if ($item['options'] & 1) {
				$item['is_link'] = 1;
				if (strlen($item['uri'])>40) {
					$item['uri'] = substr($item['uri'],0,37) . '...';
				}
			}
			$item['enableDeleteItem'] = ($this->canEditAll() || !empty($item['is_link']));
			$html .= $tpl->parse('item', $item);

			if ($currLevel > $prevLevel) { $prevLevel = $currLevel; }
			$currLevel++;
		 	$this->createTree($array, $categoryId, $html, $currLevel, $prevLevel);
		 	$currLevel--;
		}
	}
	return $html;
}
function getUserId() {
	$user = & moon :: user();
	return $user->get_user_id();
}
function getUserName($userId) {
	return 'admin';
	$sql = 'SELECT nick
		FROM ' . $this->table('Users') . '
		WHERE id = ' . $userId;
	$result = $this->db->single_query_assoc($sql);
	return (isset ($result['nick'])) ? $result['nick'] : '';
}
function canEditAll() {
	$user = &moon::user();
	return $user->i_admin('developer');
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
function setSqlWhere()
{
	$where = array();
	$where[] = 'WHERE 1';

	if (!empty($this->filter['is_deleted'])) {
		//$where[] = 'is_deleted = 1';
	} else {
		$where[] = 'is_deleted = 0';
	}
	$this->sqlWhere = implode(' AND ', $where);
}

}
?>