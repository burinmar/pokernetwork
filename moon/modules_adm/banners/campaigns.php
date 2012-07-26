<?php
class campaigns extends moon_com
{
	function onload()
	{
		$this->filter = array();
		$this->formFilter = &$this->form();
		$this->formFilter->names('title', 'show_expired', 'is_hidden');

		$this->formItemCampaign = &$this->form('campaign');
		$this->formItemCampaign->names('id', 'title', 'date_intervals', 'geo_target', 'is_hidden');

		$this->sqlWhere = ''; // set by filter
		$this->sqlOrder = '';
		$this->sqlLimit = ''; // set by paging

		$this->tblCampaigns = $this->table('Campaigns');
		$this->tblCampaignsBanners = $this->table('CampaignsBanners');
		$this->tblBanners = $this->table('Banners');
		$this->tblBannersMedia = $this->table('BannersMedia');

		$this->env = $this->get_var('env');

		$this->minTime = -2147483647;
		$this->maxTime = 2147483647;
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
						$this->formItemCampaign->fill($values);
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
			case 'save-assigned-banners':
				if ($id = $this->saveAssignedBanners()) {
					if (isset($_POST['return']) ) {
						$this->redirect('#edit', $id);
					} else {
						$this->redirect('#');
					}
				} else {
					$this->set_var('view', 'form');
				}

				break;
			case 'add-banners':
				if ($id = $this->addBanners()) {
					$this->redirect('#edit', $id);
				} else {
					$this->set_var('view', 'form');
				}

				break;
			case 'delete':
				if (isset($_POST['it'])) $this->deleteItem($_POST['it']);
				$this->redirect('#');
				break;
			case 'remove': // removes banner from assigned banners
				if (isset($par[0]) && isset($par[1])) {
					$campaignId = $par[0];
					$campaignBnId = $par[1];
					$this->removeAssignedBanner($campaignBnId);
					$this->redirect('#edit', $campaignId);
				} else {
					$page = moon::page();
					$page->page404();
				}
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
		$vars['listLimit'] = 30;
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

		$goEdit = $this->linkas('#edit','{id}');
		$tpl->save_parsed('items',array('goEdit' => $goEdit));

		$items = $this->getItems();
		$sites = $this->getSites();

		$itemsCount = count($items);
		$itemsExcludeIds = array();

		$now = round(time(), -2);
		if (!isset($this->filter['show_expired'])) {
			foreach ($items as $item) {
				$expired = false;
				if ($item['date_intervals']) {
					$ranges = explode(';', $item['date_intervals']);
					$i = 1;
					foreach ($ranges as $range) {
						list($from, $to) = explode(',', $range);

						if ($to < $now) {
							$expired = true;
							break;
						}
					}
				}

				if ($expired) {
					--$itemsCount;
					$itemsExcludeIds[] = $item['id'];
					continue;
				}
			}
		}


		// getting it here because of 'show expired' filter
		$paging = $this->getPaging($vars['currPage'], $itemsCount, $vars['listLimit']);

		$items = array_diff_key($items, array_flip($itemsExcludeIds));
		$items = array_chunk($items, $vars['listLimit'], true);
		$items = isset($items[$vars['currPage']-1]) ? $items[$vars['currPage'] -1] : array();

		$itemsList = '';
		foreach ($items as $item) {
			$dateRanges = '';
			$active = true;

			if ($item['date_intervals']) {
				$active = false;
				$ranges = explode(';', $item['date_intervals']);
				$i = 1;
				foreach ($ranges as $range) {
					list($from, $to) = explode(',', $range);

					if ($now >= $from && $now <= $to) {
						$active = true;
						//continue; // break;
					}

					$expired = false;
					$scheduled = false;
					$s = '';
					if ($from > $this->minTime) {
						$s .= $tpl->parse('active_from', array('active_from' => gmdate('Y-m-d', $from)));
						$scheduled = !$active && $from > $now;
					}
					if ($to < $this->maxTime) {
						$s .= $tpl->parse('active_to', array('active_to' => gmdate('Y-m-d', $to)));
						$expired = !$active && $to < $now;
					}


					$status = $tpl->parse('status', array('expired' => $expired, 'scheduled' => $scheduled));

					if ($dateRanges != '') $dateRanges .= '<br />';
					if ($s != '') $dateRanges .= $s . $status;
				}
			}

			$item['geo_target'] = $this->getGeoTarget($item['geo_target']);

			if ($item['sites']) {
				$assignedSites = explode(',', $item['sites']);
				$sitesArr = array();
				foreach ($assignedSites as $v) {
					$v = trim($v);
					if (isset($sites[$v])) {
						$sitesArr[] = $sites[$v]['site_id'];
					}
				}
				$item['sites'] = implode(', ', $sitesArr);
			}

			$item['hasUrlTarget'] = strlen(trim($item['uri_target']));
			$item['active'] = $tpl->parse('active', array('active' => $active));
			$item['date_ranges'] = $dateRanges;
			$item['title'] = htmlspecialchars($item['title']);
			$item['classHidden'] = ($item['is_hidden'] == 1) ? 'class="item-hidden"' : '';
			$item['date'] = date('Y-m-d', $item['created']);
			$itemsList .= $tpl->parse('items', $item);
		}

		$main = array();
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
		$env = $this->env;

		$tpl = &$this->load_template();
		$win = &moon::shared('admin');
		$page = &moon::page();

		$page->css('/css/banners.media.css');
		$page->js('/js/jquery/livequery.js');
		$page->js('/js/modules_adm/banners.campaigns.js');
		$info = $tpl->parse_array('info');

		$err = ($vars['error'] !== FALSE) ? $vars['error'] : FALSE;
		$form = $this->formItemCampaign;
		$title = $form->get('id') ? $info['titleEdit'] . ' :: ' . $form->get('title') : $info['titleNew'];
		$campaignId = $form->get('id');

		$m = array(
			'id' => $campaignId,
			'viewList' => false,
			'error' => ($err !== FALSE) ? $info['error' . $err] : '',
			'event' => $this->my('fullname') . '#save',
			'eventAddBanner' => $this->my('fullname') . '#add-banners',
			'eventAssignedBanners' => $this->my('fullname') . '#save-assigned-banners',
			'goBack' => $this->linkas('#') . '?page=' . $vars['currPage'],
			'pageTitle' => $win->current_info('title'),
			'formTitle' => htmlspecialchars($title),
			'refresh' => $page->refresh_field(),
			'is_hidden' => $form->checked('is_hidden', 1),
			'date_ranges' => '',
			'geo_zones' => '',
			'now' => date('Y-m-d'),

			'list.assigned_banners' => '',
			'list.latest_banners' => ''
		) + $form->html_values();

		// date ranges
		$i = 1;
		$dateRanges = '';
		if ($m['date_intervals']) {
			$ranges = explode(';', $m['date_intervals']);
			foreach ($ranges as $r) {
				list($from, $to) = explode(',', $r); // error cases here

				$from = ((int)$from === $this->minTime) ? '' : $from;
				$to = ((int)$to === $this->maxTime) ? '' : $to;
				if ($from === '' && $to === '') {
					continue;
				}

				$range = array(
					'no' => $i++,
					'enableRemove' => $i > 2,
					'active-from' => $from !== '' ? gmdate('Y-m-d', intval($from)) : '',
					'active-to' => $to !== '' ? gmdate('Y-m-d', intval($to)) : ''
				);

				$m['date_ranges'] .= $tpl->parse('date_ranges', $range);
			}
		}

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
			$m['geo_zones'].= $tpl->parse('geo_zones', array(
				'value'   => $name,
				'name'    => htmlspecialchars($title),
				'checked' => ($m['geo_target'] & (1<<$k))
			)). ' ';
		}
		$m['geo_zones'] .= $tpl->parse('geo_zones', array(
			'value'   => '*',
			'name'    => 'Other countries',
			'checked' => ($m['geo_target'] & 1)
		));

		$imgSrc = $this->get_var('srcBanners');
		$videoSrc = 'http://www.pokernetwork.' . (is_dev() ? 'dev' : 'com') . '/w/ads/';

		$zones = $this->getZones();
		$zonesPreroll = $this->getZonesPreroll();

		$sites = $this->getSites();
		$urlTargets = $this->getUrlTargets();

		$assignedBanners = $this->getAssignedBanners($campaignId);
		//$assignedIds = array_keys($assignedBanners);

		// get active sites for current banners groups
		$bannerIds = array();
		foreach ($assignedBanners as $b) {
			$bannerIds[] = $b['banner_id'];
		}
		$activeSitesInBanners = $this->getActiveSitesInBanners($bannerIds);
		foreach($assignedBanners as $b) {
			// non existing banner group, probably garbage, it should have been deleted when deleting bn group
			if (!$b['banner_id']) continue;

			if ($b['type'] === 'video' && !isset($tmpIsVideoType)) {
				// pn video player
				$page->js('http://ajax.googleapis.com/ajax/libs/swfobject/2.2/swfobject.js');
				$page->js('http://www.pokernetwork.com/js/pnplayer.js');
				$page->css('http://www.pokernetwork.com/css/pnplayer.css');
				$tmpIsVideoType = true;
			}

			$bannerId = intval($b['banner_id']);
			$campaignBannerId = intval($b['id']);
			$item = array(
				'id' => $campaignBannerId,
				'banner_id' => $bannerId,
				'goRemove' => $this->linkas('#remove', $campaignId . '.' . $campaignBannerId),

				'mediaSrc' => ($b['type'] == 'media' ? $imgSrc : $videoSrc) . $b['filename'],
				'media_width' => $b['media_width'],
				'media_height' => $b['media_height'],
				'imgInfo' => ($b['type'] == 'media' && $b['media_type'] == 'image' && $b['filename']) ? true : false,
				'flashInfo' => ($b['type'] == 'media' && $b['media_type'] == 'flash' && $b['filename']) ? true : false,
				'flashXmlInfo' => ($b['type'] == 'flashXml' && $b['media_type'] == 'flashXml' && $b['filename']) ? true : false,
				'videoInfo' => ($b['type'] == 'video' && $b['media_type'] == 'video' && $b['filename']) ? true : false,
				'htmlInfo' => ($b['type'] == 'html' && $b['alternative']) ? true : false,
				'alternative' => $b['alternative'],
				'html' => $b['alternative'],

				'views_limit' => $b['views_limit'] ? $b['views_limit'] : '',
				'views_limit_session' => $b['views_limit_session'] ? $b['views_limit_session'] : '',
				'is_hidden' => ($b['is_hidden'] || $b['b_hidden']) ? '1" checked="checked"' : '',
				'title' => $b['title'],
				'uri_target' => $b['uri_target'],
				'zones' => '',
				'sites' => '',
				'urlMenu' => '',
				'assigned' => $b['assigned'],
				'goBanner' => $this->linkas((($env) ? $env.'_' : '') . 'media#edit',$bannerId)
			);

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

			$goEdit = $this->linkas('#edit','{id}');
			$tpl->save_parsed('items',array('goEdit' => $goEdit));

			// zone_target
			$assignedZones = explode(',', $b['zone_target']);
			$tmpZones = $b['type'] == 'video' ? $zonesPreroll : $zones;
			foreach($tmpZones as $value => $z) {
				$s = array(
					'id' => $campaignBannerId,
					'banner_id' => $bannerId,
					'value'   => $value,
					'name'    => isset($z['title']) ? $z['title'] : $value,
					'checked' => in_array($value, $assignedZones)
				);
				$item['zones'] .= $tpl->parse('zones', $s);
			}

			// sites
			/*
			$assignedSites = explode(',', $b['sites']);
			foreach($sites as $siteId=>$site) {
				$s = array(
					'id' => $campaignBannerId,
					'banner_id' => $bannerId,
					'value'   => $siteId,
					'name'    => str_replace('-c', '', $site['site_id']), // remove '-c' in casino site id
					'checked' => in_array($siteId, $assignedSites),
					'unavailable' => empty($activeSitesInBanners) || !in_array($siteId, $activeSitesInBanners[$bannerId])
				);
				$item['sites'] .= $tpl->parse('sites', $s);
			}
			*/

			// url target helpers
			foreach($urlTargets as $pageId => $target) {
				$title = key($target);
				$s = array(
					'id' => $campaignBannerId,
					'banner_id' => $bannerId,
					'value'   => $pageId,
					'name'    => $title,
					'checked' => 0,
					'urlSubMenu' => ''
				);

				foreach ($target[$title] as $pageId => $title) {
					$t = array(
						'id' => $campaignBannerId,
						'banner_id' => $bannerId,
						'value'   => $pageId,
						'name'    => $title,
						'checked' => 0
					);
					$s['urlSubMenu'] .= $tpl->parse('urlSubMenu', $t);
				}
				$item['urlMenu'] .= $tpl->parse('urlMenu', $s);
			}
			$m['list.assigned_banners'] .= $tpl->parse('list.assigned_banners', $item);
		}

		// add banners
		$latestBanners = $this->getLatestBanners();//$assignedIds); - allow multiple same banenrs
		foreach ($latestBanners as $b) {

			if ($b['type'] === 'video' && !isset($tmpIsVideoType)) {
				// pn video player
				$page->js('http://ajax.googleapis.com/ajax/libs/swfobject/2.2/swfobject.js');
				$page->js('http://www.pokernews.com/js/pnplayer.js');
				$page->css('http://www.pokernews.com/css/pnplayer.css');
				$tmpIsVideoType = true;
			}

			$item = array(
				'id' => $b['banner_id'],
				'title' => htmlspecialchars($b['title']),

				'mediaSrc' => ($b['type'] == 'media' ? $imgSrc : $videoSrc) . $b['filename'],
				'media_width' => $b['media_width'],
				'media_height' => $b['media_height'],
				'imgInfo' => ($b['type'] == 'media' && $b['media_type'] == 'image' && $b['filename']) ? true : false,
				'flashInfo' => ($b['type'] == 'media' && $b['media_type'] == 'flash' && $b['filename']) ? true : false,
				'flashXmlInfo' => ($b['type'] == 'flashXml' && $b['media_type'] == 'flashXml' && $b['filename']) ? true : false,
				'htmlInfo' => ($b['type'] == 'html' && $b['alternative']) ? true : false,
				'videoInfo' => ($b['type'] == 'video' && $b['media_type'] == 'video' && $b['filename']) ? true : false,
				'html' => $b['alternative'],
				'alternative' => $b['alternative']
			);

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

			$m['list.latest_banners'] .= $tpl->parse('list.latest_banners', $item);
		}

		return $tpl->parse('main', $m);
	}

	function getItems()
	{
		$sql = 'SELECT c.id, c.title, c.created, c.date_intervals, c.is_hidden, c.geo_target, max(cb.sites) as sites, max(cb.uri_target) as uri_target
			FROM ' . $this->tblCampaigns . ' c
				LEFT JOIN ' . $this->tblCampaignsBanners . ' cb ON c.id = cb.campaign_id ' .
			$this->sqlWhere . '
			GROUP BY c.id ' .
			$this->sqlOrder . ' ' .
			$this->sqlLimit;
		$result = $this->db->array_query_assoc($sql, 'id');
		return $result;
	}

	function getItemsCount()
	{
		$sql = 'SELECT count(*) as cnt
			FROM ' . $this->tblCampaigns . ' c
				LEFT JOIN ' . $this->tblCampaignsBanners . ' cb ON c.id = cb.campaign_id ' .
			$this->sqlWhere;
		$result = $this->db->single_query_assoc($sql);
		return $result['cnt'];
	}

	function setSqlWhere()
	{
		$where = array();
		$where[] = 'WHERE c.environment = "' . $this->env . '"';

		$limitedToSites = $this->get_var('limitedToSites');
		$isLimitedAccess = count($limitedToSites) > 0;

		if ($isLimitedAccess) {
			$w = array('cb.sites IS NULL');
			foreach ($limitedToSites as $id=>$siteId) {
				$w[] = 'FIND_IN_SET(' . $id . ', cb.sites)';
			}
			$where[] = '(' . implode(' OR ', $w) . ')';
		}

		if (!empty($this->filter)) {
			if ($this->filter['title'] != '') {
				$where[] = 'c.title LIKE \'%' . $this->db->escape($this->filter['title']) . '%\'';
			}
		}

		if (!empty($this->filter['is_hidden'])) {
			$where[] = 'c.is_hidden < 2';
		} else {
			$where[] = 'c.is_hidden = 0';
		}

		$this->sqlWhere = implode(' AND ', $where);
	}

	function getItem($id)
	{
		$sql = 'SELECT *
			FROM ' . $this->tblCampaigns . '
			WHERE	id = ' . intval($id);
		return $this->db->single_query_assoc($sql);
	}

	function getLatestBanners($idsExclude = array())
	{
		$limitedToSites = $this->get_var('limitedToSites');
		$isLimitedAccess = count($limitedToSites) > 0;

		if ($isLimitedAccess) {
			$w = array('cb.sites IS NULL');
			foreach ($limitedToSites as $id=>$siteId) {
				$w[] = 'FIND_IN_SET(' . $id . ', cb.sites)';
			}
			$where[] = '(' . implode(' OR ', $w) . ')';
		}

		$sql = 'SELECT b.id as banner_id, b.title, b.type, max(bm.filename) as filename, max(bm.alternative) as alternative, bm.media_type, bm.media_width, bm.media_height
			FROM ' . $this->tblBanners . ' as b
				LEFT JOIN ' . $this->tblBannersMedia . ' as bm
				ON b.id = bm.banner_id AND bm.is_hidden = 0
			WHERE environment = "' . $this->env . '"  AND bm.site_id > 0
			' . (!empty($idsExclude) ? ' AND b.id NOT IN ("' . implode('","', $idsExclude) . '")' : '') . '
			' . ($isLimitedAccess ? ' AND bm.site_id IN ("' . implode(',', array_keys($limitedToSites)) . '")' : '') . '
			GROUP BY b.id
			ORDER BY b.created DESC
			LIMIT 3';
		$result = $this->db->array_query_assoc($sql);
		return $this->getEnglishBannersData($result);
	}

	function getAssignedBanners($campaignId = 0)
	{
		if (!$campaignId) return array();$sql = 'SELECT	cb.id, cb.banner_id, cb.uri_target, cb.zone_target, cb.views_limit, cb.views_limit_session, cb.sites, cb.is_hidden, b.is_hidden as b_hidden, b.title, b.type, max(bm.filename) as filename, max(bm.alternative) as alternative, IF(MAX(bm.site_id) IS NOT NULL, 1, 0) AS assigned, bm.media_type, bm.media_width, bm.media_height
		FROM '.$this->tblCampaignsBanners . ' as cb
		LEFT JOIN ' . $this->tblBanners . ' as b
		ON b.id = cb.banner_id
		LEFT JOIN ' . $this->tblBannersMedia . ' as bm
		ON b.id = bm.banner_id AND bm.is_hidden = 0 AND bm.site_id != ""
		WHERE cb.campaign_id = ' . $campaignId . '
		GROUP BY cb.id';
		$result = $this->db->array_query_assoc($sql, 'id');
		return $this->getEnglishBannersData($result);
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
			foreach ($banners as $b) {
				if (array_key_exists($b['banner_id'], $resultCom)) {
					$data = $resultCom[$b['banner_id']];
					$b['filename'] = $data['filename'] != '' ? $data['filename'] : $b['filename'];
					$b['alternative'] = $data['alternative'] != '' ? $data['alternative'] : $b['alternative'];
				}
				$items[] = $b;
			}
		} else {
			$items = $banners;
		}
		return $items;
	}

	function saveItem()
	{
		$postData = $_POST;
		$form = &$this->formItemCampaign;
		$form->fill($postData);
		$values = $form->get_values();

		// Filtering
		$data = array();
		$data = $values;
		$data['id'] = intval($values['id']);
		$data['is_hidden'] = (empty($values['is_hidden'])) ? 0 : 1;
		$id = $data['id'];

		// filter date intervals
		$dbIntervals = array();
		$activeFrom = $postData['active_from'];
		$activeTo = $postData['active_to'];
		$intervals = count($activeFrom);
		for ($i = 0; $i < $intervals; $i++) {
			// from
			$timeFrom = $activeFrom[$i] ? strtotime($activeFrom[$i].' 00:00:00 +0000') : NULL;
			if ($timeFrom <= 0 && $activeFrom[$i] !== '') {
				$errorMsg = 2; // invalid date
			}
			if ($timeFrom <= 0) {
				$activeFrom[$i] = $this->minTime;
			} else {
				$activeFrom[$i] = $timeFrom;
			}
			// to
			$timeTo = $activeTo[$i] ? strtotime($activeTo[$i].' 23:59:59 +0000') : NULL;
			if ($timeTo <= 0 && $activeTo[$i] !== '') {
				$errorMsg = 2; // invalid date
			}
			if ($timeTo <= 0) {
				$activeTo[$i] = $this->maxTime;
			} else {
				$activeTo[$i] = $timeTo;
			}

			if ($timeFrom > $timeTo && $timeTo !== NULL) {
				$errorMsg = 2; // invalid date
			}

			if ($activeFrom[$i] !== NULL && $activeTo[$i] !== NULL) {
				$dbIntervals[] = $activeFrom[$i] . ',' . $activeTo[$i];
			}
		}
		$form->fill(array('date_intervals' => implode(';', $dbIntervals)));

		$geoTarget = 0;
		if (isset($postData['geo_target']) && is_array($postData['geo_target'])) {
			$zones = geo_zones();
			foreach ($postData['geo_target'] as $zone) {
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

		// Validation
		$errorMsg = 0;
		if ($data['title'] == '') {
			$errorMsg = 1;
		}

		if ($errorMsg) {
			$this->set_var('error', $errorMsg);
			return FALSE;
		}

		// if was refresh skip other steps and return
		if ($form->was_refresh()) {
			return $id;
		}

		$ins = $form->get_values('title', 'date_intervals', 'geo_target', 'is_hidden');
		$ins['updated'] = time();
		$ins['environment'] = $this->env;

		if ($id) {
			$this->db->update($ins, $this->tblCampaigns, array('id' => $id));

			// log this action
			blame($this->my('fullname'), 'Updated', $id);

		} else {
			$ins['created'] = $ins['updated'];
			$id = $this->db->insert($ins, $this->tblCampaigns, 'id');

			// log this action
			blame($this->my('fullname'), 'Created', $id);
		}

		$form->fill(array('id' => $id));
		return $id;
	}

	function saveAssignedBanners()
	{
		$postData = $_POST;

		$campaignId = intval($postData['campaign_id']);

		$zones = (isset($_POST['zones']) && is_array($_POST['zones'])) ? $_POST['zones'] : array();
		$uriTargets = (isset($_POST['uri_target']) && is_array($_POST['uri_target'])) ? $_POST['uri_target'] : array();
		$viewsLimit = (isset($_POST['views_limit']) && is_array($_POST['views_limit'])) ? $_POST['views_limit'] : array();
		$viewsLimitSession = (isset($_POST['views_limit_session']) && is_array($_POST['views_limit_session'])) ? $_POST['views_limit_session'] : array();
		$hidden = (isset($_POST['is_hidden']) && is_array($_POST['is_hidden'])) ? $_POST['is_hidden'] : array();

		$sites = $this->get_var('sitesNames');
		$siteId = isset($sites[1]) ? $sites[1]['id'] : 0;
		foreach ($zones as $id => $value) {
			$upd = array(
				'zone_target' => isset($zones[$id]) ? implode(',', $zones[$id]) : '',
				'sites' => $siteId,
				'uri_target' => isset($uriTargets[$id]) ? $uriTargets[$id] : '',
				'views_limit' => isset($viewsLimit[$id]) ? $viewsLimit[$id] : 0,
				'views_limit_session' => isset($viewsLimitSession[$id]) ? $viewsLimitSession[$id] : 0,
				'is_hidden' => isset($hidden[$id]) ? 1 : 0
			);
			$this->db->update($upd, $this->tblCampaignsBanners, array('id' => $id));
		}

		// log this action
		blame($this->my('fullname'), 'Updated', $campaignId . ': banners');
		return $campaignId;
	}

	function addBanners()
	{
		$postData = $_POST;
		$campaignId = intval($postData['id']);

		$ids = array();
		// banner ids in input
		if (!empty($_POST['banners_ids'])) {
			$bnIds = explode(',', str_replace(array(';', '.', ':'), ',', $_POST['banners_ids']));
			foreach ($bnIds as $id) {
				$id = intval(trim($id));
				if ($id > 0) {
					$ids[] = $id;
				}
			}
		}

		// latest banners list
		if (!empty($_POST['latest_banners']) && is_array($_POST['latest_banners'])) {
			foreach ($_POST['latest_banners'] as $id) {
				$ids[] = intval($id);
			}
		}
		$ids = array_unique($ids);

		// Validation
		$errorMsg = 0;
		if (empty($ids)) {
			$errorMsg = 'Bn1';
		} elseif (!$campaignId) {
			$errorMsg = 'Bn2';
		}

		if ($errorMsg) {
			$this->set_var('error', $errorMsg);
			return FALSE;
		}

		// if was refresh skip other steps and return
		$form = &$this->formItemCampaign;
		if ($form->was_refresh()) {
			return $id;
		}

		// look for existing banners
		//$result = $this->db->array_query_assoc('
		//	SELECT banner_id FROM ' . $this->tblCampaignsBanners . '
		//	WHERE campaign_id IN ("' . implode('","', $ids) . '")
		//', 'banner_id');
		//$ids = array_diff($ids, array_keys($result));

		foreach ($ids as $id) {
			$ins = array(
				'campaign_id' => $campaignId,
				'banner_id' => $id
			);
			$this->db->insert($ins, $this->tblCampaignsBanners);
		}

		// log this action
		blame($this->my('fullname'), 'Created', 'added banners: ' . implode(',', $ids));
		return $campaignId;
	}

	function deleteItem($ids)
	{
		if (!is_array($ids) || !count($ids)) return;
		foreach ($ids as $k => $v) {
			$ids[$k] = intval($v);
		}
		$this->db->query('UPDATE ' . $this->tblCampaigns . ' SET is_hidden = 2 WHERE id IN (' . implode(',', $ids) . ')');

		// delete all banners in this campaign (set deleted - is_hidden = 2)
		$this->db->query('UPDATE ' . $this->tblCampaignsBanners . ' SET is_hidden = 2 WHERE campaign_id IN (' . implode(',', $ids) . ')');

		// log this action
		blame($this->my('fullname'), 'Deleted', $ids);
		return TRUE;
	}

	function removeAssignedBanner($campaignBnId)
	{
		$sql = 'DELETE FROM ' . $this->tblCampaignsBanners . ' WHERE id = ' . intval($campaignBnId);
		$this->db->query($sql);
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
		$filter['show_expired'] = $this->formFilter->checked('show_expired', 1);
		$filter['is_hidden'] = $this->formFilter->checked('is_hidden', 1);

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
			array('created' => 0, 'title' => 1, 'geo_target' => 0, 'uri_target' => 0),
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

	//***************************************
	//        --- OTHER ---
	//***************************************
	function getZones()
	{
		return $this->get_var('zones.' . $this->env);
	}

	function getZonesPreroll()
	{
		return $this->get_var('zones.preroll.' . $this->env);
	}

	function getSites() {
		$limitedToSites = $this->get_var('limitedToSites');
		$isLimitedAccess = count($limitedToSites) > 0;
		if($isLimitedAccess) {
			$sites = array();
			foreach ($limitedToSites as $id=>$siteId) {
				$sites[$id] = array('id'=>$id, 'site_id'=>$siteId);
			}
			return $sites;
		}

		$m = $this->db->single_query("show tables like '".$this->table('Servers')."'");
		$exist = (count($m) ? true : false);
		if ($exist) {
			$category = $this->env == 'pn' ? 1 : 3;
			$sites = $this->db->array_query_assoc('
				SELECT id, site_id
				FROM ' . $this->table('Servers') . '
				WHERE server_disabled = 0 AND category = ' . $category . '
			', 'id');
			return $sites;
		}
		return $this->get_var('sitesNames');
	}

	function getUrlTargets() {
		return $this->get_var('urlTargets');
	}

	function getActiveSitesInBanners($bannerIds)
	{
		if (!is_array($bannerIds) || !count($bannerIds)) return array();
		foreach ($bannerIds as $k => $v) {
			$bannerIds[$k] = intval($v);
		}
		$sql = 'SELECT banner_id, site_id, media_type, alternative
			FROM ' . $this->tblBannersMedia . '
			WHERE site_id > 0 AND banner_id IN (' . implode(',',$bannerIds ) . ')';
		$result = $this->db->array_query_assoc($sql);
		$items = array();
		foreach ($result as $r) {
			if ($r['media_type'] == 'flashXml' && $r['alternative'] == '') continue;
			$items[$r['banner_id']][] = $r['site_id'];
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

}
?>