<?php
class statistics extends moon_com
{
	function onload()
	{
		$this->filter = array();
		$this->formFilter = &$this->form();
		$this->formFilter->names('site_id', 'from_date', 'to_date');
		
		$this->sqlWhere = ''; // set by filter
		$this->sqlOrder = '';
		$this->sqlLimit = ''; // set by paging
		
		$this->tblBanners = $this->table('Banners');
		$this->tblBannersMedia = $this->table('BannersMedia');
		$this->tblCampaigns = $this->table('Campaigns');
		$this->tblCampaignsBanners = $this->table('CampaignsBanners');
		$this->tblBannersStats = $this->table('BannersStats');
		
		$this->mode = 'campaigns';
		$this->env = $this->get_var('env');
	}
        
	function events($event, $par)
	{
		switch ($event) {
			case 'campaigns':
				$this->mode = 'campaigns';
				break;
			case 'zones':
				$this->mode = 'zones';
				break;
			case 'banners':
				$this->mode = 'banners';
				break;
			default:
				break;
		}
		
		$id = 0;
		if (isset($par[0])) {
			$par[0] = str_replace('-', ':', $par[0]);
			$this->set_var('view', 'form');
			$this->set_var('id', $par[0]); // campaign, banner or zone id
			$id = $par[0];
		}
		
		if ($id && isset($_GET['data-views'])) {
			header('content-type: application/json');
			header('content-type: text/plain');
			print $this->getGraphData($id, 'views');
			moon_close();
			exit;
		}
		if ($id && isset($_GET['data-clicks'])) {
			header('content-type: application/json');
			header('content-type: text/plain');
			print $this->getGraphData($id, 'clicks');
			moon_close();
			exit;
		}
		
		if(isset($_POST['setFilter']) || isset($_GET['noFilter'])) {
			$this->setFilter();
		}
		
		$this->setOrdering();
		$this->setPaging();
		$this->use_page('Common');
	}
        
	function properties()
	{
		return array(
			'view' => 'list',
			'id' => '',
			'currPage' => 1,
			'listLimit' => 25
		);
	}
        
	function main($vars)
	{
		$page = &moon::page();
		$win = &moon::shared('admin');
		$win->active($this->my('fullname').'#'.$this->mode);
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
		$mode = $this->mode;
		
		$filter = $this->getFilter();
		
		$m = array(
			'pageTitle' => $win->current_info('title'),
			'filter' => $tpl->parse('filter', $filter),
			'submenu' => $win->subMenu(),
			'paging' => ''//$this->getPaging($vars['currPage'], $this->getItemsCount(), $vars['listLimit'])
		) + $this->getOrdering();
		
		$items = $this->getItems();
		
		$goEdit = '';
		switch ($mode) {
			case 'campaigns':
				$campaigns = $this->getCampaignsByIds(array_keys($items));
				break;
			case 'zones':
				$zones = $this->getZones();
				break;
			case 'banners':
				$banners = $this->getBannersByIds(array_keys($items));
				break;
		}
		
		$goEdit = $this->linkas('#'.$mode,'{id}');
		$tpl->save_parsed('items',array('goEdit' => $goEdit));
		
		$itemsList = '';
		foreach ($items as $id => $item) {
			$title = '';
			switch ($mode) {
				case 'campaigns':
					$title = !empty($campaigns[$id]) ? htmlspecialchars($campaigns[$id]) : '';
					break;
				case 'zones':
					$title = !empty($zones[$item['zone']]) ? htmlspecialchars($zones[$item['zone']]) : '';
					break;
				case 'banners':
					$title = !empty($banners[$id]) ? htmlspecialchars($banners[$id]['title']) : '';
					break;
			}
			$item['title'] = htmlspecialchars($title);
			$item['id'] = $id;
			$item['goEdit'] = 1;
			$item['percent'] = $item['views'] && $item['clicks'] ? number_format($item['clicks']/$item['views']*100,3):0;
			$item['sizes'] = !empty($banners[$id]) ? $banners[$id]['media_width'].'x'.$banners[$id]['media_height'] : '';
			$itemsList .= $tpl->parse('items', $item);
		}
		
		$m['viewList'] = TRUE;
		$m['items'] = $itemsList;
		return $tpl->parse('main', $m);
	}
	
	function renderForm($vars)
	{
		if (!$vars['id']) return '';
		$id = $vars['id'];
		
		$page = &moon::page();
		$page->js('js/swfobject.js');
		
		$tpl = &$this->load_template();
		$win = &moon::shared('admin');
		$mode = $this->mode;
		
		$filter = $this->getFilter();
		
		$m = array(
			'id' => $id,
			'pageTitle' => $win->current_info('title'),
			'filter' => $tpl->parse('filter', $filter),
			'submenu' => $win->subMenu(),
			'viewList' => FALSE,
			'goBack' => $this->linkas('#'.$mode),
			'itemsCampaigns' => '',
			'itemsZones' => '',
			'itemsBanners' => '',
			'campaignsMode' => $mode == 'campaigns',
			'zonesMode' => $mode == 'zones',
			'bannersMode' => $mode == 'banners',
			
			'url.chart-data-views' => $this->linkas('#'.$mode, str_replace(':', '-', $id), array('data-views'=>1,'t'=>time())),
			'url.chart-data-clicks' => $this->linkas('#'.$mode, str_replace(':', '-', $id), array('data-clicks'=>1,'t'=>time()))
		);
		
		$itemsCampaigns = array();
		$itemsZones = array();
		$itemsBanners = array();
		
		$campaigns = array();
		$zones = array();
		$banners = array();
		
		$viewsTotal = 0;
		$clicksTotal = 0;
		$modeTitle = 'title';
		
		if ($mode != 'campaigns') {
			$itemsCampaigns = $this->getItemsById($id, 'campaigns');
			$campaigns = $this->getCampaignsByIds(array_keys($itemsCampaigns));
		} else {
			$campaigns = $this->getCampaignsByIds(array($id));
			$modeTitle = isset($campaigns[$id]) ? htmlspecialchars($campaigns[$id]) : '';
		}
		
		if ($mode != 'zones') {
			$itemsZones = $this->getItemsById($id, 'zones');
			$zones = $this->getZones();
			
			foreach ($itemsZones as $item) {
				$viewsTotal += $item['views'];
				$clicksTotal += $item['clicks'];
			}
		} else {
			$zones = $this->getZones();
			$modeTitle = isset($zones[$id]) ? htmlspecialchars($zones[$id]) : '';
		}
		
		if ($mode != 'banners') {
			$itemsBanners = $this->getItemsById($id, 'banners');
			$banners = $this->getBannersByIds(array_keys($itemsBanners));
			
			foreach ($itemsBanners as $item) {
				$viewsTotal += $item['views'];
				$clicksTotal += $item['clicks'];
			}
		} else {
			$banners = $this->getBannersByIds(array($id));
			
			$modeTitle = !empty($banners[$id]) ? htmlspecialchars($banners[$id]['title']) : '';
			
			$b = isset($banners[$id]) ? $banners[$id] : array();
			if (!empty($b)) {
				$imgSrc = $this->get_var('srcBanners');
				$m += array(
					'mediaSrc' => $imgSrc . $b['filename'],
					'type' => $b['type'],
					'media_width' => $b['media_width'],
					'media_height' => $b['media_height'],
					'imgInfo' => ($b['type'] == 'media' && $b['media_type'] == 'image' && $b['filename']) ? true : false,
					'flashInfo' => ($b['type'] == 'media' && $b['media_type'] == 'flash' && $b['filename']) ? true : false,
					'flashXmlInfo' => ($b['type'] == 'flashXml' && $b['media_type'] == 'flashXml' && $b['filename']) ? true : false,
					'htmlInfo' => ($b['type'] == 'html' && $b['alternative']) ? true : false,
					'alternative' => $b['alternative'],
					'html' => $b['alternative'],
					'title' => $b['title']
				);
				
				if ($m['flashXmlInfo']) {
					$params = '';
					if ($m['alternative']) {
						$data = unserialize($m['alternative']);
						if (!empty($data[0]) || !empty($data[1])) {
							$params = 'f=' . $data[0] . '&t=' . $data[1];
						}
					}
					$m['params'] = $params;
				}
			}
		}
		
		foreach ($itemsCampaigns as $id => $item) {
			$item['title'] = isset($campaigns[$id]) ? htmlspecialchars($campaigns[$id]) : '';
			$item['id'] = $id;
			$item['goItem'] = $this->linkas('#campaigns', $id);
			$item['percent'] = $item['views'] && $item['clicks'] ? number_format($item['clicks']/$item['views']*100,3):0;
			$m['itemsCampaigns'] .= $tpl->parse('itemsCampaigns', $item);
		}
		foreach ($itemsZones as $id => $item) {
			$item['title'] = isset($zones[$item['zone']]) ? htmlspecialchars($zones[$item['zone']]) : '';
			$item['id'] = $id;
			$item['goItem'] = $this->linkas('#zones', $id);
			$item['percent'] = $item['views'] && $item['clicks'] ? number_format($item['clicks']/$item['views']*100,3):0;
			$m['itemsZones'] .= $tpl->parse('itemsZones', $item);
		}
		foreach ($itemsBanners as $id => $item) {
			$item['title'] = !empty($banners[$id]) ? htmlspecialchars($banners[$id]['title']) : '';
			$item['sizes'] = !empty($banners[$id]) ? $banners[$id]['media_width'].'x'.$banners[$id]['media_height'] : '';
			$item['id'] = $id;
			$item['goItem'] = $this->linkas('#banners', $id);
			$item['percent'] = $item['views'] && $item['clicks'] ? number_format($item['clicks']/$item['views']*100,3):0;
			$m['itemsBanners'] .= $tpl->parse('itemsBanners', $item);
		}
		
		$m['modeTitle'] = $modeTitle;
		$m['viewsTotal'] = $viewsTotal;
		$m['clicksTotal'] = $clicksTotal;
		$m['percent'] = $viewsTotal && $clicksTotal ? number_format($clicksTotal/$viewsTotal*100,3):0;
		return $tpl->parse('main', $m);
	}
	
	function getItems()
	{
		$groupBy = '';
		switch ($this->mode) {
			case 'campaigns':
				$groupBy = 'campaign_id';
				break;
			case 'zones':
				$groupBy = 'zone';
				break;
			case 'banners':
				$groupBy = 'banner_id';
				break;
			default:
				break;
		}
		$groupBySql = $groupBy !== '' ? 'GROUP BY ' . $groupBy : '';
		
		$sql = 'SELECT banner_id, campaign_id, zone, SUM(views) as views, SUM(clicks) as clicks, (SUM(clicks)/SUM(views))*100 AS ctr
			FROM ' . $this->tblBannersStats . ' ' . 
			$this->sqlWhere . ' ' .
			$groupBySql . ' ' .
			$this->sqlOrder . ' ' .
			$this->sqlLimit;
		$result = $this->db->array_query_assoc($sql, $groupBy);
		return $result;
	}
	
	function getItemsById($id, $show = '')
	{
		$field = '';
		switch ($this->mode) {
			case 'campaigns':
				$field = 'campaign_id';
				break;
			case 'zones':
				$field = 'zone';
				break;
			case 'banners':
				$field = 'banner_id';
				break;
		}
		$fieldWhereSql = $field !== '' ? ' AND ' . $field . ' = \'' . $this->db->escape($id) . '\'' : '';
		
		$groupBy = '';
		switch ($show) {
			case 'campaigns':
				$groupBy = 'campaign_id';
				break;
			case 'zones':
				$groupBy = 'zone';
				break;
			case 'banners':
				$groupBy = 'banner_id';
				break;
		}
		$groupBySql = $groupBy !== '' ? 'GROUP BY ' . $groupBy : '';
		
		$sql = 'SELECT banner_id, campaign_id, zone, SUM(views) as views, SUM(clicks) as clicks
			FROM ' . $this->tblBannersStats . ' ' . 
			$this->sqlWhere . ' ' . $fieldWhereSql . ' ' .
			$groupBySql . ' ' .
			'ORDER BY views DESC ' . //$this->sqlOrder . ' ' .
			$this->sqlLimit;
		$result = $this->db->array_query_assoc($sql, $groupBy);
		return $result;
	}
	
	function setSqlWhere()
	{
		$where = array();
		$where[] = 'WHERE 1';
		
		if (!empty($this->filter)) {
			if (!empty($this->filter['from_date']) && !empty($this->filter['to_date'])) {
				$from = strtotime($this->filter['from_date']);
				$to = strtotime($this->filter['to_date']);
				$to = $to + (3600*24 - 1);
				
				if ($from <= $to) {
					$where[] = 'date BETWEEN ' . $from . ' AND ' . $to;
				}
			}
			if (!empty($this->filter['site_id'])) {
				$where[] = 'site_id = ' . intval($this->filter['site_id']);
			} else {
				$siteIds = array_keys($this->getSites());
				$where[] = 'site_id IN (' . implode(',', $siteIds) . ')';
			}
		}
		$this->sqlWhere = implode(' AND ', $where);
	}
	
	function getCampaignsByIds($ids)
	{
		if (empty($ids)) return array();
		$sql = 'SELECT id, title
			FROM ' . $this->tblCampaigns . '
			WHERE id IN (' . implode(',', $ids) . ')';
		$result = $this->db->array_query_assoc($sql, 'id');
		$items = array();
		foreach ($result as $id => $r) {
			$items[$id] = $r['title'];
		}
		return $items;
	}
	
	function getBannersByIds($ids)
	{
		if (empty($ids)) return array();
		$result = array();
		
		if (count($ids) == 1) {
			// try to get english banner first
			$sql = 'SELECT b.id, b.title, b.type, bm.filename as filename, max(bm.alternative) as alternative, bm.media_type, bm.media_width, bm.media_height
				FROM ' . $this->tblBanners . ' as b
					LEFT JOIN ' . $this->tblBannersMedia . ' as bm
					ON b.id = bm.banner_id
				WHERE b.id IN (' . implode(',', $ids) . ') AND bm.site_id = 10
				GROUP BY b.id';
			$result = $this->db->array_query_assoc($sql, 'id');
		}
		
		if (empty($result)) {
			$sql = 'SELECT b.id, b.title, b.type, max(bm.filename) as filename, max(bm.alternative) as alternative, bm.media_type, bm.media_width, bm.media_height
				FROM ' . $this->tblBanners . ' as b
					LEFT JOIN ' . $this->tblBannersMedia . ' as bm
					ON b.id = bm.banner_id
				WHERE b.id IN (' . implode(',', $ids) . ')
				GROUP BY b.id';
			$result = $this->db->array_query_assoc($sql, 'id');
		}
		
		$items = array();
		foreach ($result as $id => $r) {
			$items[$id] = $r;
		}
		return $items;
	}
	
	function getSites()
	{
		$items = array();
		$m = $this->db->single_query("show tables like '".$this->table('Servers')."'");
		$exist = (count($m) ? true : false);
		if ($exist) {
			$category = $this->env == 'pn' ? 1 : 3;
			$result = $this->db->array_query_assoc('
				SELECT site_id,id
				FROM ' . $this->table('Servers') . '
				WHERE server_disabled = 0 AND category = ' . $category . '
				ORDER BY site_id
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
	
	function getZones()
	{
		$zones = $this->get_var('zones.' . $this->env);
		$items = array();
		foreach ($zones as $id => $z) {
			$items[$id] = $z['title'];
		}
		return $items;
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
		
		$filter = array();
		
		foreach ($this->filter as $k => $v) {
			if ($v) {
				$filter['isOn'] = 1;
				break;
			}
		}
		$filter['classIsOn'] = !empty($filter['isOn']) ? ' filter-on' : '';
		
		// set default values
		if (
			(empty($this->filter['from_date']) && 
			empty($this->filter['to_date'])) ||
			$this->filter['from_date'] > $this->filter['to_date']
		) {
			$this->filter['from_date'] = date('Y-m-d', strtotime('-2 weeks'));
			$this->filter['to_date'] = date('Y-m-d');
		}
		
		$this->formFilter->fill($this->filter);
		$fitler = $this->formFilter->html_values();
		
		$filter['goFilter'] = $this->my('fullname').'#'.$this->mode;
		$filter['noFilter'] = $this->linkas('#'.$this->mode);//, 'filter');
		$filter['action'] = '';//$this->linkas('#'.$this->mode, 'filter');
		//$filter['isOn'] = '';
		
		// custom fields
		$filter['site_id'] = $this->formFilter->get('site_id');
		$sites = $this->getSites();
		$filter['sites'] = $this->formFilter->options('site_id', $sites);
		
		$filter['from_date'] = $this->formFilter->get('from_date');
		$filter['to_date'] = $this->formFilter->get('to_date');
		
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
		$pn->set_url($this->linkas('#'.$this->mode, '', array('page' => '{pg}')), $this->linkas('#'));
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
			array('views' => 0, 'clicks' => 0, 'ctr' => 0),
			//antras parametras kuris lauko numeris defaultinis.
			0
		);
		
		$links = $ord->get_links(
			$this->linkas('#'.$this->mode, '', array('ord' => '{pg}')),
			$sort
		);
		$this->sqlOrder = 'ORDER BY ' . $ord->sql_order();
		//gauna linkus orderby{nr}
		return $links;
	}
	
	
	function getGraphData($id, $show = 'views')
	{
		$tpl = &$this->load_template();
		$messages = $tpl->parse_array('messages');
		
		include_class('php-ofc-library/open-flash-chart');
		$data = array();
		$labels = array();
		$dataMax = 1;
		
		$stats = $this->getStatistics($id);
		
		$chart = new open_flash_chart();
		$line = new line();
		
		switch ($show) {
			case 'views':
				$line->set_key($messages['label_views'], 14);
				foreach ($stats as $stat) {
					$labels[] = gmdate('Y-m-d', $stat['date']);
					$data[] = (int)$stat['views'];
					$dataMax = max($dataMax, $stat['views']);
				}
				$line->set_colour('#5B56B6');
				break;
			case 'clicks':
				$line->set_key($messages['label_clicks'], 14);
				foreach ($stats as $stat) {
					$labels[] = gmdate('Y-m-d', $stat['date']);
					$data[] = (int)$stat['clicks'];
					$dataMax = max($dataMax, $stat['clicks']);
				}
				$line->set_colour('#6BA024');
				break;
		}
		$line->set_values($data);
		$chart->add_element($line);
		
		$x_labels = new x_axis_labels();
		$x_labels->set_steps(1);
		$x_labels->set_vertical();
		$x_labels->set_colour('#000000');
		$x_labels->set_labels($labels);
		
		$x = new x_axis();
		$x->set_colour('#D7E4A3');
		$x->set_grid_colour('#D7E4A3');
		$x->set_offset(FALSE);
		$x->set_steps(1);
		$x->set_labels($x_labels);
		
		$chart->set_x_axis($x);
		
		$y = new y_axis();
		$y->set_range(0, $dataMax, ceil($dataMax/10));
		$chart->add_y_axis($y);
		
		return $chart->toPrettyString();
	}
	
	function getStatistics($id = 0)
	{
		$filter = $this->getFilter();
		
		$fromDate = !empty($filter['from_date']) ? strtotime($filter['from_date']) : 0;
		$toDate = !empty($filter['to_date']) ? strtotime($filter['to_date']) : 0;
		$toDate = $toDate + (3600*24 - 1);
		$siteId = !empty($filter['site_id']) ? $filter['site_id'] : '';
		
		if (!$id || ($fromDate > $toDate)) {
			return array();
		}
		
		$diff = ceil(($toDate - $fromDate)/24/3600);
		$step = 'day';
		if ($diff > 21) {
			$step = 'week';
		}
		if ($diff > 140) {
		       $step = 'month';
		}
		
		switch ($step) {
			case 'day':
				$dbGroupFormat = '%y-%m-%d';
				$phpGroupFormat = 'y-m-d';
				break;
			case 'week':
				$dbGroupFormat = '%y-%u';
				$phpGroupFormat = 'y-W';
				break;
			case 'month':
				$dbGroupFormat = '%y-%m';
				$phpGroupFormat = 'y-m';
				break;
			default:
				return array();
		}
		
		$field = '';
		switch ($this->mode) {
			case 'campaigns':
				$field = 'campaign_id';
				break;
			case 'zones':
				$field = 'zone';
				break;
			case 'banners':
				$field = 'banner_id';
				break;
		}
		
		$where = array();
		$where[] = 'WHERE 1';
		$where[] = $field . ' = "' . $this->db->escape($id) . '"';
		if ($siteId) $where[] = 'site_id = ' . intval($siteId);
		$where[] = 'date BETWEEN ' . intval($fromDate) . ' AND ' . intval($toDate);
		
		$sqlWhere = implode(' AND ', $where);
		
		$sql = 'SELECT MIN(`date`) `date`,SUM(`views`) `views`,SUM(`clicks`) `clicks`,date_format(from_unixtime(date),"' . $dbGroupFormat . '") gd
			FROM ' . $this->tblBannersStats . ' ' . 
			$sqlWhere . '
			GROUP BY gd';
		$rows = $this->db->array_query_assoc($sql);
		$adRawStats = array();
		foreach ($rows as $row) {
			$adRawStats[$row['gd']] = $row;
		}
		
		$adStats = array();
		$emptyRow = array(
			'clicks'=>0,
			'views'=>0
		);
		for ($ts = $fromDate; $ts <= $toDate; $ts+=86400) {
			$key = gmdate($phpGroupFormat, $ts);
			if (isset($adStats[$key])) {
				continue;
			}
			if (isset($adRawStats[$key])) {
				$adStats[$key] = $adRawStats[$key];
				$adStats[$key]['date'] = $ts;
			} else {
				$emptyRow['date'] = $ts;
				$adStats[$key] = $emptyRow;
			}
		}
		return $adStats;
	}
	
}
?>