<?php
class rotation extends moon_com
{
	function onload()
	{
		$this->filter = array();
		$this->formFilter = &$this->form();
		$this->formFilter->names('site_id', 'zones');

		$this->sqlWhere = ''; // set by filter
		$this->sqlOrder = '';
		$this->sqlLimit = ''; // set by paging

		$this->tblBanners = $this->table('Banners');
		$this->tblBannersMedia = $this->table('BannersMedia');
		$this->tblCampaigns = $this->table('Campaigns');
		$this->tblCampaignsBanners = $this->table('CampaignsBanners');
		$this->tblRooms = $this->table('Rooms');

		$this->env = $this->get_var('env');
	}

	function events($event, $par)
	{
		switch ($event) {
			case 'filter':
				$this->setFilter();
				break;
			default:
				break;
		}
		$this->setOrdering();
		$this->setPaging();
		$this->use_page('Common');
	}

	function properties()
	{
		$vars = array();
		$vars['view'] = 'list';
		$vars['currPage'] = 1;
		$vars['listLimit'] = 25;
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
			return '';//$this->renderForm($vars);
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

		$goEdit = $this->linkas($this->env . '_media#','{id}');
		$tpl->save_parsed('items',array('goEdit' => $goEdit));

		$rooms = $this->getRooms();
		$zones = $this->getZones();
		$serversUrls = $this->getServersUrls();
		$imgSrc = $this->get_var('srcBanners');

		$items = $this->getItems();

		$itemsList = '';
		foreach ($items as $item) {

			$item['id'] = $item['bannerId'];
			$item['title'] = htmlspecialchars($item['title']);

			$item['zones'] = '';
			if ($item['zone_target']) {
				$zonesAssigned = explode(',', $item['zone_target']);
				$zonesA = array();
				foreach ($zonesAssigned as $z) {
					if(isset($zones[$z])) $zonesA[] = $zones[$z];
				}
				$item['zones'] = implode(', ', $zonesA);
			}

			$item['roomName'] = array_key_exists($item['room_id'], $rooms) ? $rooms[$item['room_id']] : '';
			$item['sizes'] = $item['media_width'] . 'x' . $item['media_height'];

			$item['mediaSrc'] = $imgSrc . $item['filename'];
			$item['media_width'] = $item['media_width'];
			$item['media_height'] = $item['media_height'];
			$item['timestamp'] = time();

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

			$redirectUrl = '/';
			if ($item['redirectUrl'] !== '') {
				$redirectUrl = $item['redirectUrl'];

				// look if relative url
				if ($redirectUrl && strpos($redirectUrl, 'http://') === false) {
					if (!empty($serversUrls[$item['site_id']])) {
						$redirectUrl = $serversUrls[$item['site_id']] . ltrim($redirectUrl, '/');
					}
				}
			} elseif ($item['roomUri'] !== NULL) {

				if (!empty($serversUrls[$item['site_id']])) {
					$redirectUrl = $serversUrls[$item['site_id']] . $item['roomUri'] . '/ext/';
				}
			}
			$item['redirect_url'] = $redirectUrl;

			$item['geo_target'] = $this->getGeoTarget($item['geo_target']);
			$item['uri_target'] = str_replace("\n", '<br />', trim($item['uri_target']));

			$itemsList .= $tpl->parse('items', $item);
		}

		$m = array();
		$m['viewList'] = TRUE;
		$m['filter'] = $tpl->parse('filter', $filter);
		$m['items'] = $itemsList;
		$m['paging'] = $paging;
		$m['pageTitle'] = $win->current_info('title');
		$m += $ordering;

		return $tpl->parse('main', $m);
	}

	function getItems()
	{
		$sql = 'SELECT
				c.id as campaignId,
				c.date_intervals,
				c.geo_target,
				cb.uri_target,
				cb.zone_target,
				b.id as bannerId,
				b.title,
				b.type,
				b.room_id,
				b.url AS groupUrl,
				b.img_alt,
				bm.id as mediaId,
				bm.site_id,
				bm.filename,
				bm.media_type,
				bm.media_width,
				bm.media_height,
				bm.alternative,
				bm.url,
				bm.created,
				r.alias AS roomUri,
				IF(bm.url != "", bm.url, b.url) AS redirectUrl
			FROM ' . $this->tblCampaigns . ' c
			  LEFT JOIN ' . $this->tblCampaignsBanners . ' cb
			    ON c.id = cb.campaign_id
			  LEFT JOIN ' . $this->tblBanners . ' b
			    ON cb.banner_id = b.id
			  LEFT JOIN ' . $this->tblBannersMedia . ' bm
			    ON cb.banner_id = bm.banner_id ' . '
			  LEFT JOIN ' . $this->tblRooms . ' r ON r.id = b.room_id ' .
			$this->sqlWhere . '
			GROUP BY cb.id ' .
			$this->sqlOrder . ' ' .
			$this->sqlLimit;
		$result = $this->db->array_query_assoc($sql, 'id');



		$locale = &moon::locale();
		$nowDay = floor($locale->now() / 86400) * 86400;

		$banners = array();
		$bannerIds = array();

		// filter by date intervals
		foreach ($result as $r) {
			if ($r['date_intervals']) {
				$skip = TRUE;
				$ranges = explode(';', $r['date_intervals']);
				foreach ($ranges as $range) {
					list($from, $to) = explode(',', $range);
					if ($nowDay >= $from && $nowDay <= $to) {
						$skip = FALSE;
						break;
					}
				}
				// skip the banner
				if ($skip) continue;
			}

			$banners[] = $r;
			$bannerIds[] = $r['mediaId'];
		}

		return $banners;
	}

	function getItemsCount()
	{
		$sql = 'SELECT count(*) as cnt
			FROM ' . $this->tblCampaigns . ' c
			  LEFT JOIN ' . $this->tblCampaignsBanners . ' cb
			    ON c.id = cb.campaign_id
			  LEFT JOIN ' . $this->tblBanners . ' b
			    ON cb.banner_id = b.id
			  LEFT JOIN ' . $this->tblBannersMedia . ' bm
			    ON cb.banner_id = bm.banner_id ' . ' ' .
			$this->sqlWhere;
		$result = $this->db->single_query_assoc($sql);
		return $result['cnt'];
	}

	function setSqlWhere()
	{
		$where = array();
		$where[] = 'WHERE c.environment = "' . $this->env . '"';
		$where[] = 'c.is_hidden = 0';
		$where[] = 'cb.is_hidden = 0';
		$where[] = 'b.is_hidden = 0';
		$where[] = 'bm.is_hidden = 0';

		if (!empty($this->filter)) {
			if ($this->filter['site_id'] != '') {
				$where[] = '(FIND_IN_SET(' . intval($this->filter['site_id']) . ', cb.sites))';
				$where[] = 'bm.site_id = ' . intval($this->filter['site_id']);
			}
			if (!empty($this->filter['zone'])) {
				$where[] = '(FIND_IN_SET("' . $this->db->escape($this->filter['zone']) . '", cb.zone_target))';
			}
		}
		$this->sqlWhere = implode(' AND ', $where);
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

	function getServersUrls()
	{
		$items = array();
		$m = $this->db->single_query("show tables like '".$this->table('Servers')."'");
		$exist = (count($m) ? true : false);
		if ($exist) {
			// get server home urls
			$result = $this->db->array_query_assoc('
				SELECT id,url
				FROM '. $this->table('Servers')
			, 'id');
			foreach ($result as $id => $r) {
				$items[$id] = $r['url'];
			}
			return $items;
		}
		foreach ($this->get_var('sitesUrls') as $id => $r) {
			$items[$id] = $r['url'];
		}
		return $items;
	}

	function getGeoTarget($geoTarget)
	{
		if ($geoTarget === null)  return '';

		static $geoValues = array();
		static $geoTargets = array();

		if (!empty($geoValues[$geoTarget])) {
			return $geoValues[$geoTarget];
		}

		if (empty($geoTargets)) {
			// geo zones
			include_once(MOON_CLASSES."geoip/geoip.inc");
			$gi=new GeoIP;
			$geo = geo_zones();

			foreach ($geo as $name=>$k) {
				$c  = strtoupper($name);
				$geoId = isset($gi->GEOIP_COUNTRY_CODE_TO_NUMBER[$c]) ? $gi->GEOIP_COUNTRY_CODE_TO_NUMBER[$c]:0;
				switch ($c) {
					case 'AA':
						$title= 'Austral-Asia';
						break;
					case 'NV':
						$title= 'Nevada';
						break;
					default:
						$title= $geoId ? $gi->GEOIP_COUNTRY_NAMES[$geoId] : NULL;
						break;
				}

				$geoTargets[] = array(
					'value' => $name,
					'title' => htmlspecialchars($title),
					'k' => $k
				);
			}
			$geoTargets[] = array(
				'value' => '*',
				'title' => 'Other countries',
				'k' => 0
			);
		}

		$geoValues[$geoTarget] = '';
		foreach ($geoTargets as $g) {
			$geoValues[$geoTarget] .= ($geoTarget & (1<<$g['k'])) ? $g['title'] . ' ' : '';
		}
		$geoValues[$geoTarget];
		return $geoValues[$geoTarget];
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

		// set default values
		if (empty($this->filter['site_id'])) $this->filter['site_id'] = $this->env == 'pn' ? 10 : 13;

		$this->formFilter->fill($this->filter);

		$filter = $this->formFilter->html_values();
		$filter['goFilter'] = $this->my('fullname').'#filter';
		$filter['noFilter'] = $this->linkas('#filter');
		$filter['isOn'] = '';

		// custom fields
		$filter['site_id'] = $this->formFilter->get('site_id');
		$sites = $this->getSites();
		$filter['sites'] = $this->formFilter->options('site_id', $sites);

		$filter['zone'] = $this->formFilter->get('zone');
		$zones = $this->getZones();
		$filter['zones'] = $this->formFilter->options('zone', $zones);

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
			$sort = 11;
		}

		$links = array();
		$pn = &moon::shared('paginate');
		$ord = &$pn->ordering();
		$ord->set_values(
			//laukai, ir ju defaultine kryptis
			array('title' => 1),
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