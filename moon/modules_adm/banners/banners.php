<?php
class banners extends moon_com
{
	function onload()
	{
		$this->filter = array();
		$this->formFilter = &$this->form();
		$this->formFilter->names('title', 'rooms', 'is_hidden');
		
		$this->formItem = &$this->form('collection');
		$this->formItem->names('id', 'title', 'type', 'room_id', 'url', 'img_alt', 'target_blank', 'is_hidden');
		
		$this->sqlWhere = ''; // set by filter
		$this->sqlOrder = '';
		$this->sqlLimit = ''; // set by paging
		
		$this->tblBanners = $this->table('Banners');
		$this->tblBannersMedia = $this->table('BannersMedia');
		$this->tblCampaigns = $this->table('Campaigns');
		$this->tblCampaignsBanners = $this->table('CampaignsBanners');
		
		$this->env = $this->get_var('env');
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
				$currPage = moon::page()->get_global($this->my('fullname') . '.currPage');
				$this->redirect('#', '', array('page'=>$currPage));
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
		$vars['currPage'] = 1;
		$vars['listLimit'] = 10;
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
		
		$ordering = $this->getOrdering();
		$filter = $this->getFilter();
		$paging = $this->getPaging($vars['currPage'], $this->getItemsCount(), $vars['listLimit']);
		
		$goEdit = $this->linkas('#edit','{id}');
		$tpl->save_parsed('items',array('goEdit' => $goEdit));
		
		// get active banners
		$sql = '
			SELECT b.id as id, SUBSTRING(c.date_intervals FROM LOCATE(",", c.date_intervals)+1) as date_to, SUBSTRING(c.date_intervals FROM 1 FOR LOCATE(",", c.date_intervals)-1) as date_from
			FROM ' . $this->table('Banners') . ' b
			INNER JOIN ' . $this->table('CampaignsBanners') . ' cb ON b.id = cb.banner_id
			INNER JOIN ' . $this->table('Campaigns') . ' c ON cb.campaign_id = c.id

			WHERE
				cb.is_hidden = 0 AND c.is_hidden = 0 AND 
				cb.sites != "" AND cb.zone_target != "" AND 
				c.environment = "' . $this->env . '"
		';
		$res = $this->db->array_query_assoc($sql, 'id');
		$activeBnIds = array();
		foreach ($res as $r) {
			if ($r['date_from'] <= time() && $r['date_to'] >= time()) $activeBnIds[] = $r['id'];
		}


		$items = $this->getItems();
		
		$bnIds = array_keys($items);
		$bnAssignedSites = $this->getBannersAssignedSites($bnIds);
		
		$rooms = $this->getRooms();
		$imgSrc = $this->get_var('srcBanners');
		
		$limitedToSites = $this->get_var('limitedToSites');
		$isLimitedAccess = count($limitedToSites) > 0;
		
		$itemsList = '';
		$now = round(time(), -2);
		foreach ($items as $id => $item) {
			$item['notActive'] = !in_array($id, $activeBnIds);
			$item['title'] = htmlspecialchars($item['title']);
			$item['roomName'] = array_key_exists($item['room_id'], $rooms) ? $rooms[$item['room_id']] : '';
			$item['sizes'] = $item['media_width'] . 'x' . $item['media_height'];
			
			$item['sites'] = '';
			if (isset($bnAssignedSites[$id])) {
				//if($isLimitedAccess && count(array_intersect($limitedToSites, $bnAssignedSites[$id])) === 0) continue;
				asort($bnAssignedSites[$id]);
				$item['sites'] = implode(',', $bnAssignedSites[$id]);
			}
			
			$item['mediaSrc'] = $imgSrc . $item['filename'];
			$item['media_width'] = $item['media_width'];
			$item['media_height'] = $item['media_height'];
			
			$item['imgInfo'] = ($item['type'] == 'media' && $item['media_type'] == 'image' && $item['filename']) ? true : false;
			$item['flashInfo'] = ($item['type'] == 'media' && $item['media_type'] == 'flash' && $item['filename']) ? true : false;
			$item['flashXmlInfo'] = ($item['type'] == 'flashXml' && $item['media_type'] == 'flashXml' && $item['filename']) ? true : false;
			$item['htmlInfo'] = ($item['type'] == 'html' && $item['alternative']) ? true : false;
			$item['alternative'] = $item['alternative'];
			$item['html'] = $item['alternative'];
			
			if ($item['flashXmlInfo']) {
				$params = '';
				if ($item['alternative']) {
					$data = unserialize($item['alternative']);
					if (!empty($data[0]) || !empty($data[1])) {
						$params = 'f=' . $data[0] . '&t=' . $data[1];
					}
				}
				$item['params'] = $params;
			}
			
			$item['classHidden'] = ($item['is_hidden'] == 1) ? 'class="item-hidden"' : '';
			$itemsList .= $tpl->parse('items', $item);
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
		$env = $this->env;
		
		$tpl = &$this->load_template();
		$win = &moon::shared('admin');
		$page = &moon::page();
		$info = $tpl->parse_array('info');
		
		$err = ($vars['error'] !== FALSE) ? $vars['error'] : FALSE;
		$form = $this->formItem;
		$title = $form->get('id') ? $info['titleEdit'] . ' :: ' . $form->get('title') : $info['titleNew'];
		$id = $form->get('id');
		if (!$id) $form->fill(array('target_blank' => 1));
		$optRooms = $this->getRooms();
		
		$main = array(
			'id' => $id,
			'viewList' => false,
			'showNav' => $id ? true : false,
			'url.settings' => $this->linkas('#edit', $id),
			'url.media' => $this->linkas((($env) ? $env.'_' : '') . 'media#', $id),
			'error' => ($err !== FALSE) ? $info['error' . $err] : '',
			'event' => $this->my('fullname') . '#save',
			'goBack' => $this->linkas('#') . '?page=' . $vars['currPage'],
			'pageTitle' => $win->current_info('title'),
			'formTitle' => htmlspecialchars($title),
			'refresh' => $page->refresh_field(),
			'typeMedia' => !$form->get('type') ? 'media" checked="checked"' : $form->checked('type', 'media'),
			'typeHtml' => $form->checked('type', 'html'),
			'typeFlashXml' => $form->checked('type', 'flashXml'),
			'typeVideo' => $form->checked('type', 'video'),
			'is_hidden' => $form->checked('is_hidden', 1),
			'target_blank' => $form->checked('target_blank', 1),
			'optRooms' => $form->options('room_id', $optRooms)
		) + $form->html_values();
		
		return $tpl->parse('main', $main);
	}
	
	function getItems()
	{
		$sql = 'SELECT b.id, b.title, b.type, b.media_width, b.media_height, b.room_id, b.is_hidden, max(bm.filename) as filename, max(bm.alternative) as alternative, bm.media_type, bm.media_width, bm.media_height
			FROM ' . $this->tblBanners . ' as b
				LEFT JOIN ' . $this->tblBannersMedia . ' as bm
				ON b.id = bm.banner_id AND bm.site_id != 0 ' . ' ' .
			$this->sqlWhere . '
			GROUP BY b.id ' .
			$this->sqlOrder . ' ' .
			$this->sqlLimit;
		$result = $this->db->array_query_assoc($sql, 'id');
		return $this->getEnglishBannersData($result);
	}
	
	function getItemsCount()
	{
		$sql = 'SELECT count(distinct(b.id)) as cnt
			FROM ' . $this->tblBanners . ' as b
				LEFT JOIN ' . $this->tblBannersMedia . ' as bm
				ON b.id = bm.banner_id AND bm.site_id != 0 ' . ' ' .
			$this->sqlWhere;
		$result = $this->db->single_query_assoc($sql);
		return $result['cnt'];
	}
	
	function setSqlWhere()
	{
		$where = array();
		$where[] = 'WHERE environment = "' . $this->env . '"';
		
		$limitedToSites = $this->get_var('limitedToSites');
		$isLimitedAccess = count($limitedToSites) > 0;
		
		if ($isLimitedAccess) {
			$where[] = '(bm.site_id IN (' . implode(',',array_keys($limitedToSites)) . '))';
		}
		
		if (!empty($this->filter)) {
			if ($this->filter['title'] != '') {
				$where[] = 'b.title LIKE \'%' . $this->db->escape($this->filter['title']) . '%\'';
			}
			if ($this->filter['room'] != '') {
				$where[] = 'b.room_id = ' . $this->filter['room'];
			}
		}

		if (!empty($this->filter['is_hidden'])) {
			//$where[] = 'c.is_hidden = 1';
		} else {
			$where[] = 'b.is_hidden = 0';
		}
		
		$this->sqlWhere = implode(' AND ', $where);
	}
	
	function getItem($id)
	{
		$sql = 'SELECT *
			FROM ' . $this->tblBanners . '
			WHERE	id = ' . intval($id);
		return $this->db->single_query_assoc($sql);
	}
	
	function getRooms()
	{
		$sql = 'SELECT id, name
			FROM ' . $this->table('Rooms') . '
			WHERE is_hidden = 0
			ORDER BY name ASC';
		$result = $this->db->array_query_assoc($sql);
		
		$rooms = array();
		foreach ($result as $item) {
			$rooms[$item['id']] = $item['name'];
		}
		return $rooms;
	}
	
	function getEnglishBannersData($banners)
	{
		// get COM filename & alternative to show in lists
		$sql = 'SELECT banner_id, filename, alternative 
			FROM ' . $this->tblBannersMedia . '
			WHERE site_id = 10';
		$resultCom = $this->db->array_query_assoc($sql, 'banner_id');
		$items = array();
		if (!empty($resultCom)) {
			foreach ($banners as $id => $b) {
				if (array_key_exists($b['id'], $resultCom)) {
					$data = $resultCom[$b['id']];
					$b['filename'] = $data['filename'] != '' ? $data['filename'] : $b['filename'];
					$b['alternative'] = $data['alternative'] != '' ? $data['alternative'] : $b['alternative'];
				}
				$items[$id] = $b;
			}
		} else {
			$items = $banners;
		}
		return $items;
	}
	
	function getBannersAssignedSites($bnIds)
	{
		$items = array();
		if (count($bnIds)) {
			$sql = 'SELECT site_id,banner_id
				FROM ' . $this->tblBannersMedia . '
				WHERE banner_id IN (' . implode(',', $bnIds) . ')';
			$result = $this->db->array_query_assoc($sql);
			
			$allSiteIds = $this->getAllSiteIds();
			foreach ($result as $r) {
				if (!empty($allSiteIds[$r['site_id']])) {
					$items[$r['banner_id']][] = $allSiteIds[$r['site_id']];
				}
				
			}
		}
		return $items;
	}
	
	function getAllSiteIds()
	{
		$items = array();
		$m = $this->db->single_query("show tables like '".$this->table('Servers')."'");
		$exist = (count($m) ? true : false);
		if ($exist) {
			$result = $this->db->array_query_assoc('
				SELECT site_id,id
				FROM ' . $this->table('Servers') . '
				WHERE server_disabled = 0
			', 'id');
			foreach ($result as $id => $r) {
				$items[$id] = $r['site_id'];
			}
			return $items;
		}
		foreach ($this->get_var('sitesNames') as $id => $r) {
			$items[$id] = $r['site_id'];
		}
		return $items;
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
		
		$data['is_hidden'] = (empty($values['is_hidden'])) ? 0 : 1;
		$data['target_blank'] = (empty($values['target_blank'])) ? 0 : 1;
		$data['room_id'] = intval($values['room_id']);
		$id = $data['id'];
		
		// Validation
		$errorMsg = 0;
		if ($data['title'] == '') {
			$errorMsg = 1;
		} elseif (!in_array($data['type'], array('media','html', 'flashXml', 'video'))) {
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
		
		$ins = $form->get_values('title', 'type', 'room_id', 'url', 'img_alt', 'is_hidden', 'target_blank');
		$ins['updated'] = time();
		$ins['environment'] = $this->env;
		
		if ($id) {
			// if url changed - update media items urls
			$sql = 'SELECT url, type
				FROM ' . $this->tblBanners . ' 
				WHERE id = ' . intval($id);
			$res = $this->db->single_query_assoc($sql);
			if (!empty($res['url']) && ($res['url'] != $ins['url'])) {
				$sql = 'UPDATE ' . $this->tblBannersMedia . '
					SET url = "' . $this->db->escape($ins['url']) . '"
					WHERE	banner_id = ' . intval($id) . ' AND
					     	site_id != 0';
				$this->db->query($sql);
			}

			// if media type changed - hide current media items assigned
			if (!empty($res['type']) && ($res['type'] != $ins['type'])) {
				$sql = 'UPDATE ' . $this->tblBannersMedia . '
					SET is_hidden = 1
					WHERE	banner_id = ' . intval($id) . ' AND
					     	site_id != 0';
				$this->db->query($sql);
			}
			
			$this->db->update($ins, $this->tblBanners, array('id' => $id));
			
			// log this action
			blame($this->my('fullname'), 'Updated', $id);
			
		} else {
			$ins['created'] = $ins['updated'];
			$id = $this->db->insert($ins, $this->tblBanners, 'id');
			
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
		$this->db->query('DELETE FROM ' . $this->tblBanners . ' WHERE id IN (' . implode(',', $ids) . ')');
		
		// delete all banners in this group
		$bannersDir = $this->get_dir('Banners');
		$result = $this->db->array_query_assoc('
			SELECT filename
			FROM ' . $this->tblBannersMedia . '
			WHERE banner_id IN (' . implode(',', $ids) . ')'
		);
		foreach ($result as $res) {
			// delete file
			if (isset($res['filename'])) {
				$deleteFile = new moon_file;
				if ($deleteFile->is_file($bannersDir.$res['filename'])) {
					$deleteFile->delete();
				}
			}
		}
		$this->db->query('DELETE FROM ' . $this->tblBannersMedia . ' WHERE banner_id IN (' . implode(',', $ids) . ')');
		
		// delete this banner from all campaigns
		$this->db->query('DELETE FROM ' . $this->tblCampaignsBanners . ' WHERE banner_id IN (' . implode(',', $ids) . ')');
		
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
		$filter['title'] = $this->formFilter->get('title');
		$filter['room'] = $this->formFilter->get('room');
		$filter['is_hidden'] = $this->formFilter->checked('is_hidden', 1);
		
		$rooms = $this->getRooms();
		$filter['rooms'] = $this->formFilter->options('room', $rooms);
		
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
			array('b.created' => 1, 'title' => 1),
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