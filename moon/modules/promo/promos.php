<?php

class promos extends moon_com
{
	function events($event, $argv)
	{
		if (isset($_GET['promo_id_redirect'])) {
			if (null != ($url = $this->getPromoAlias($_GET['promo_id_redirect']))) {
				$url = moon::shared('sitemap')->getLink('promotions') . $url . '/';
				moon::page()->redirect($url, 301);
			} else {
				moon::page()->page404();
			}
		} elseif (isset($_GET['promo_id_redirect_master'])) {
			if (null != ($url = $this->getPromoMasterAlias($_GET['promo_id_redirect_master']))) {
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
			'list.active' => '',
			'list.inactive' => '',
		);

		$time = time();
		// active
		foreach ($this->db->array_query_assoc('
			SELECT title, alias, skin_dir
			FROM promos
			WHERE is_hidden = 0
			  AND date_end>"' . gmdate('Y-m-d', $time/* - 86400*/) . '"
			  AND FIND_IN_SET("' . _SITE_ID_ . '", sites)
			ORDER BY date_start DESC
		') as $row) {
			$logoFn = rawurlencode($row['skin_dir']) . '/bg-list.jpg';
			$mainArgv['list.active'] .= $tpl->parse('index:active.item', array(
				'url' => $this->linkas('#' . $row['alias']),
				'logo' => '' != $row['skin_dir'] && file_exists($this->get_dir('fs:Css') . $logoFn)
					? $this->get_dir('web:Css') . $logoFn
					: $this->get_dir('web:Css') . 'default/bg-list.jpg',
				'title' => htmlspecialchars($row['title']),
			));
		}
		// inactive
		foreach ($this->db->array_query_assoc('
			SELECT title, alias
			FROM promos
			WHERE is_hidden=0 AND date_end<="' . gmdate('Y-m-d', $time) . '" AND FIND_IN_SET("' . _SITE_ID_ . '", sites) ORDER BY date_start DESC
		') as $row) {
			$mainArgv['list.inactive'] .= $tpl->parse('index:inactive.item', array(
				'url' => $this->linkas('#' . $row['alias']),
				'title' => htmlspecialchars($row['title']),
			));
		}

		return $tpl->parse('index:main', $mainArgv);
	}

	private function getPromoAlias($id)
	{
		$alias = $this->db->single_query_assoc('
			SELECT alias FROM promos WHERE id=' . intval($id) . '
		');
		return !empty($alias['alias'])
			? $alias['alias']
			: null;
	}
	private function getPromoMasterAlias($remoteId)
	{
		$alias = $this->db->single_query_assoc('
			SELECT alias FROM promos WHERE remote_id=' . intval($remoteId) . '
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
