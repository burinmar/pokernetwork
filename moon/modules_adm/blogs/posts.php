<?php
class posts extends moon_com {

	function onload()
	{
		$this->filter = array();
		$this->formFilter = &$this->form();
		$this->formFilter->names('title');
		
		$this->formItem = &$this->form('item');
		$this->formItem->names('id', 'title', 'body', 'tags', 'is_hidden', 'created_on', 'disable_smiles');
		
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
		$loc = &moon::locale();

		$ordering = $this->getOrdering();
		$filter = $this->getFilter();
		$paging = $this->getPaging($vars['currPage'], $this->getItemsCount(), $vars['listLimit']);

		$goEdit = $this->linkas('#edit','{id}');
		$tpl->save_parsed('items',array('goEdit' => $goEdit));

		$items = $this->getItems();

		$userIds = array();
		foreach ($items as $item) {
			$userIds[$item['user_id']] = 1;
		}

		$usersData = $this->usersData(array_keys($userIds));

		$itemsList = '';
		foreach ($items as $item) {

			$userInfo = isset($usersData[$item['user_id']]) ? $usersData[$item['user_id']] : array();
			$userNick = !empty($userInfo['nick']) ? $userInfo['nick'] : '';
			$item['nick'] = htmlspecialchars($userNick);
			$item['created'] = date('Y-m-d H:i', $item['created_on']);
			$itemsList .= $tpl->parse('items', $item);
		}
		
		$m = array(
			'viewList' => TRUE,
			'filter' => $tpl->parse('filter', $filter),
			'items' => $itemsList,
			'paging' => $paging,
			'pageTitle' => $win->current_info('title'),
			'goDelete' => $this->my('fullname') . '#delete'
		) + $ordering;
		
		return $tpl->parse('main', $m);
	}
	function renderForm($vars)
	{
		$tpl = &$this->load_template();
		$page = moon::page();
		$win = &moon::shared('admin');
		$form = $this->formItem;
		$title = $form->get('title');
		
		$m = array(
			'viewList' => FALSE,
			'id' => $form->get('id'),
			'goBack' => $this->linkas('#') . '?page=' . $vars['currPage'],
			'pageTitle' => $win->current_info('title'),
			'formTitle' => htmlspecialchars($title),
			'toolbar' => '',
			'selfUrl' => $this->linkas('#edit',$form->get('id')),
			'refresh' => $page->refresh_field(),
			'event' => $this->my('fullname') . '#save',
			'goBack' => $this->linkas('#') . '?page=' . $vars['currPage']
		) + $form->get_values();

		// add toolbar
		if (is_object( $rtf = $this->object('rtf') )) {
			$rtf->setInstance( $this->get_var('rtf') );
			$m['toolbar'] = $rtf->toolbar('i_body',(int)$m['id']);
		}
		
		return $tpl->parse('main', $m);
	}
	function getItems()
	{
		$sql = 'SELECT p.id, p.title, p.is_hidden, p.created_on, p.user_id
			FROM ' . $this->table('Posts') . ' p ' . 
			$this->sqlWhere . ' ' .
			$this->sqlOrder . ' ' .
			$this->sqlLimit;
		$result = $this->db->array_query_assoc($sql);
		return $result;
	}
	function getItemsCount()
	{
		$sql = 'SELECT count(*) as cnt
			FROM ' . $this->table('Posts') . ' p ' . 
			$this->sqlWhere;
		$result = $this->db->single_query_assoc($sql);
		return $result['cnt'];
	}
	function getItem($id)
	{
		$sql = 'SELECT p.*, b.body
			FROM ' . $this->table('Posts') . ' p
				LEFT JOIN ' . $this->table('Bodies') . ' b
				ON p.id = b.post_id
			WHERE 	id = ' . intval($id);
		return $this->db->single_query_assoc($sql);
	}
	function saveItem()
	{
		$postData = $_POST;
		
		$form = &$this->formItem;
		$form->fill($postData);
		$values = $form->get_values();

		// Filtering
		$data = array();
		$data = $values;
		$data['id'] = intval($values['id']);
		if ($data['body'] === '') {
			$data['body'] = NULL;
		}

		$id = $data['id'];
		
		// Validation
		$errorMsg = 0;
		if(!$id) {
			$errorMsg = 9;
		} if (!is_object($rtf = $this->object('rtf'))) {
			$errorMsg = 9;
		}

	 	if ($errorMsg) {
			$this->set_var('error', $errorMsg);
			return FALSE;
		}

		// if was refresh skip other steps and return
		if ($form->was_refresh()) {
			return $id;
		}
		
		$upd = $form->get_values('body');
		$upd['updated_on'] = time();

		//iskarpa ir kompiliuojam i html
		$rtf->setInstance( $this->get_var('rtf') );
		$txt = &moon::shared('text');
		if ($data['disable_smiles']) {
			$rtf->parserFeatures['smiles'] = false;
			$txt->features['smiles'] = false;
		}
		list(,$bodyCompiled) = $rtf->parseText($id, $upd['body']);
		
		if ($bodyCompiled !== '') {
			if ($upd['body']) {
				$upd['body_short'] = $txt->message($txt->excerpt($txt->strip_tags($upd['body']), 250));
			} else {
				// images post
				list(,$upd['body_short']) = $rtf->parseText($id, $upd['body'], true);
			}
		} else {
			$upd['body_short'] = '';
		}

		// switch - original body will go to blog_posts_bodies table
		$bodyOrig = $upd['body'];
		$upd['body'] = $bodyCompiled;

		$this->db->update($upd, $this->table('Posts'), array('id' => $id));
		// update post body
		$is = $this->db->single_query('
			SELECT 1 FROM ' . $this->table('Bodies') . '
			WHERE post_id = ' . $id);
		if (isset($is[0])) {
			$this->db->update(array('body'=>$bodyOrig),$this->table('Bodies'),array('post_id' => $id));
		} else {
			$this->db->insert(array('post_id'=>$id,'body'=>$bodyOrig),$this->table('Bodies'));
		}
		$rtf->assignObjects($id);
		
		// log this action
		blame($this->my('fullname'), 'Updated', $id);
		
		$form->fill(array('id' => $id));
		return $id;
	}
	function setSqlWhere()
	{
		$where = array();
		$where[] = 'WHERE p.is_hidden < 2';
		
		if (!empty($this->filter)) {
			if ($this->filter['title'] != '') {
				$where[] = 'p.title LIKE \'%' . $this->db->escape($this->filter['title']) . '%\'';
			}
		}
		$this->sqlWhere = implode(' AND ', $where);
	}
	function deleteItem($ids)
	{
		if (!is_array($ids) || !count($ids)) return;
		foreach ($ids as $k => $v) {
			$ids[$k] = intval($v);
		}
		$this->db->query('UPDATE ' . $this->table('Posts') . ' SET is_hidden = 2 WHERE id IN (' . implode(',', $ids) . ')');
		// log this action
		blame($this->my('fullname'), 'Deleted', $ids);
		return TRUE;
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
		$filter['nick'] = $this->formFilter->get('nick');
		$filter['title'] = $this->formFilter->get('title');

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
			array('created_on' => 1) ,
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

	function usersData($ids) {
		if (!is_array($ids)) {
			$ids = array($ids);
		}
		foreach ($ids as $k=>$v) {
			$ids[$k]=intval($v);
		}
		$ids = array_unique($ids);
		if (!count($ids)) {
			return array();
		}

		$myTable = 'vb_user';
		$db = & moon::db('database-vb');

		$sql="SELECT userid as id, username as nick, avatarrevision as avatar FROM ".$myTable.' WHERE userid IN ('.implode(',',$ids).')';
		$n=$db->array_query_assoc(  $sql , 'id');
		foreach ($n as $id=>$v) {
			$n[$id]['avatar'] = $v['avatar'] ? '/forums/customavatars/avatar' . $v['id'] . '_' . $v['avatar'] . '.gif' : '';
		}
		return $n;
	}
}
?>