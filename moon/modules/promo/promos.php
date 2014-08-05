<?php

class promos extends moon_com
{
	function events($event, $argv)
	{
		if (isset($_GET{'promo_alias_redirect'})) {
			if (null != ($url = $this->getPromoAlias($_GET['promo_alias_redirect']))) {
				$url = moon::shared('sitemap')->getLink('promotions') . $url . '/';
				moon::page()->redirect($url, 301);
			} else {
				moon::page()->page404();
			}
		} elseif ($event == 'get-active-promos') {
			return moon::page()->set_local('transporter', ($this->getActivePromosExport()));
		} elseif ($event == 'get-room-active') {
			return moon::page()->set_local('transporter', ($this->getActiveRoomIs($argv)));
		}
		$segments = explode('/', moon::page()->requested_event('REST'));
		switch ($segments[0]) {
		case '':
			$this->set_var('render', 'index');
			break;

		default:
			return $this->object('promo')->synthEvents($segments);
		}
		$this->use_page('Main');
	}

	function main($argv)
	{
		switch ($argv['render']) {
		case 'index':
			return $this->renderIndex();
		default:
			moon::page()->page404();
		}
	}

	private function renderIndex()
	{
		$tpl = $this->load_template();
		$pageInfo = moon::shared('sitemap')->getPage();
		if (empty($pageInfo)) {
			moon::page()->page404();
		}
		$mainArgv = array(
			'title' => $pageInfo['title'],
			'description' => $pageInfo['content_html'],
			'list.inactive' => '',
		);

		$considerLiveLeagues = in_array(_SITE_ID_, array('si'));
		if ($considerLiveLeagues) {
			$mainArgv['list.active.live_league'] = '';
			$mainArgv['list.active.not_live_league'] = '';
		} else {
			$mainArgv['list.active'] = '';
		}

		$baseWhere = $this->getPromosBaseWhere('com' == _SITE_ID_ /* ignore geo */);
		$time = time();

		// active
		$storage = moon::shared('storage');
		foreach ($this->getActivePromos($baseWhere, $time, $considerLiveLeagues) as $row) {
			$logoFn = $this->get_dir('web:Css') . 'default/bg-list.jpg';
			if ('' != $row['img_list'])
				$logoFn = $storage->location('promo-list')->url($row['img_list'], 1);
			$tplTgt = $considerLiveLeagues
				? ($row['is_live_league']
					? 'list.active.live_league'
					: 'list.active.not_live_league')
				: 'list.active';
			$mainArgv[$tplTgt] .= $tpl->parse('index:active.item', array(
				'url' => $this->linkas('#' . $row['alias']),
				'logo' => $logoFn,
				'title' => htmlspecialchars($row['title']),
				'alias' => $row['skin_dir'],
			));
		}
		// inactive
		foreach ($this->getInactivePromos($baseWhere, $time) as $row) {
			$mainArgv['list.inactive'] .= $tpl->parse('index:inactive.item', array(
				'url' => $this->linkas('#' . $row['alias']),
				'title' => htmlspecialchars($row['title']),
			));
		}

		return $tpl->parse('index:main', $mainArgv);
	}

	private function getPromosBaseWhere($geoIgnore = false)
	{
		// $roomsWhere = array_map(function($roomId) { // oh my god why
		//	return 'FIND_IN_SET(' . $roomId . ',room_id)';
		// }, $this->object('reviews.review')->recomendRooms($geoIgnore));

		$hidePokernewsCup = (_SITE_ID_ === 'com') ? 'pokernewscup = 0' : 'pokernewscup < 2';

		return array(
			'is_hidden=0',
			$hidePokernewsCup,
			'FIND_IN_SET("' . _SITE_ID_ . '", sites)',
			// (0 != count($roomsWhere)
			// 	? '(room_id IS NULL OR ' . implode(' OR ', $roomsWhere) . ')'
			// 	: '(room_id IS NULL)')
		);
	}

	public function getSitemapPromos()
	{
		$promos = $this->db->array_query_assoc('
			SELECT menu_title title, alias url
			FROM promos
			WHERE ' . implode(' AND ', array_merge($this->getPromosBaseWhere(), array(
				'date_start<="' . gmdate('Y-m-d', time()) . '"',
				'(date_end>="' . gmdate('Y-m-d', time()) . '" OR date_end IS NULL)',
			))) . ' AND menu_title != ""
			ORDER BY date_start
		');
		foreach ($promos as $k => $promo) {
			$promos[$k]['url'] = $this->linkas('#' . $promo['url']);
		}
		return $promos;
	}

	private function getActivePromos($baseWhere, $time, $considerLiveLeagues)
	{
		return $this->db->array_query_assoc('
			SELECT title, alias, img_list, skin_dir' . ($considerLiveLeagues ? ', is_live_league' : '') . '
			FROM promos
			WHERE ' . implode(' AND ', array_merge($baseWhere, array(
				'(date_end>="' . gmdate('Y-m-d', $time) . '" OR date_end IS NULL)'
			))) . '
			ORDER BY date_start DESC
		');
	}

	private function getInactivePromos($baseWhere, $time)
	{
		return $this->db->array_query_assoc('
			SELECT title, alias
			FROM promos
			WHERE ' . implode(' AND ', array_merge($baseWhere, array(
				'(date_end<"' . gmdate('Y-m-d', $time) . '" OR date_end IS NULL)'
			))) . '
			ORDER BY date_start DESC
		');
	}

	private function getPromoAlias($alias)
	{
		$alias = $this->db->single_query_assoc('
			SELECT alias FROM promos WHERE alias="' . $this->db->escape($alias) . '"
		');
		return !empty($alias['alias'])
			? $alias['alias']
			: null;
	}

	private function getActivePromosExport()
	{
		$time = time();
		return array_keys($this->db->array_query_assoc('
			SELECT ' . (_SITE_ID_ == 'com' ? 'id' : 'remote_id') . ' id FROM promos
			WHERE is_hidden = 0
			  AND FIND_IN_SET("' . _SITE_ID_ . '", sites)
			  AND ' . (_SITE_ID_ == 'com' ? '1' : 'remote_id>0') . '
		', 'id'));
	}

	private function getActiveRoomIs($argv)
	{
		$roomIds = array_map('intval', $argv['room_ids']);
		$room = $this->db->single_query_assoc('
			SELECT id FROM ' . $this->table('Rooms') . '
			WHERE id IN (' . implode(',', $roomIds) . ') AND is_hidden=0
			LIMIT 1
		');
		return isset($room['id']);
	}
}
