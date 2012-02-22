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
		}
		if (isset($_GET['promo_id_redirect_master'])) {
			if (null != ($url = $this->getPromoMasterAlias($_GET['promo_id_redirect_master']))) {
				$url = moon::shared('sitemap')->getLink('promotions') . $url . '/';
				moon::page()->redirect($url, 301);
			} else {
				moon::page()->page404();
			}
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
		switch (array_get_del($argv, 'render')) {
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
		// date_start<"' . gmdate('Y-m-d', $time + 604800) . '" AND 
		foreach ($this->db->array_query_assoc('
			SELECT title, alias, prize, skin_dir,
			       descr_list, date_start, date_end, timezone
			FROM promos
			WHERE is_hidden = 0 
			  AND date_end>"' . gmdate('Y-m-d', $time/* - 86400*/) . '"
			  AND FIND_IN_SET("' . _SITE_ID_ . '", sites) 
			ORDER BY date_start DESC
		') as $row) {
			$logoFn = rawurlencode($row['skin_dir']) . '/logo-list.png';
			$mainArgv['list.active'] .= $tpl->parse('index:active.item', array(
				'url' => $this->linkas('#' . $row['alias']),
				'logo' => '' != $row['skin_dir'] && file_exists($this->get_dir('fs:Css') . $logoFn)
					? $this->get_dir('web:Css') . $logoFn
					: '/img/promo/logo.png',
				'title' => htmlspecialchars($row['title']),
				'prize' => htmlspecialchars($row['prize']),
				'description' => $row['descr_list']
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
}