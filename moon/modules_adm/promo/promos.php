<?php

require_once 'base_inplace_syncable.php';
class promos extends base_inplace_syncable
{
	function onload()
	{
		parent::onload();
		foreach (array('lb_data') as $k) {
			$this->dataNoSave[] = $k;
		}
	}

	function events($event, $argv)
	{
		if (isset($_GET['import'])) {
			$this->importData();
		}
		parent::events($event, $argv);
		moon::page()->js('/js/modules_adm/promo.js');
		if (isset($_GET['sync']) || isset($_POST['sync'])) {
			$this->sync();
			moon_close();exit;
		}
	}

	protected function getEntriesAdditionalFields()
	{
		$return = array('date_start', 'date_end', 'room_id', 'sites', 'timezone');
		if (base_inplace_syncable::_SITE_ID_ != 'com') {
			$return[] = 'remote_id';
		}
		return $return;
	}
	
	protected function getEntriesAdditionalOrderBy()
	{
		return array('date_start DESC');
	}

	private $rooms = array();
	protected function eventEntriesGotItems($list)
	{
		$roomIds = array();
		$cRoomIds = array();
		foreach ($list as $row) {
			$row['room_id'] = intval($row['room_id']);
			if ($row['room_id'] > 0) {
				$roomIds[] = $row['room_id'];
			} elseif ($row['room_id'] < 0) {
				$cRoomIds[] = -$row['room_id'];
			} 
		}
		$this->rooms = array();
		if (0 != count($roomIds)) {
			foreach($this->db->array_query_assoc('
				SELECT id, name FROM ' . $this->table('Rooms') . '
				WHERE id IN (' . implode(',', array_unique($roomIds)) . ')
			', 'id') as $id => $room) {
				$this->rooms[$id] = $room;
			}
		}
		if (0 != count($cRoomIds)) {
			foreach($this->db->array_query_assoc('
				SELECT id, name FROM ' . $this->table('CustomRooms') . '
				WHERE id IN (' . implode(',', array_unique($cRoomIds)) . ')
			', 'id') as $id => $room) {
				$this->rooms[-$id] = $room;
			}
		}
	}

	private function getRoomName($id)
	{
		return isset($this->rooms[$id])
			? $this->rooms[$id]['name']
			: NULL;
	}

	protected function partialRenderListRow($row, &$argv, $tpl)
	{
		$argv = array_merge($argv, array(
			'room' => htmlspecialchars($this->getRoomName($row['room_id'])),
			'is_completed' => '',
			'date_start' => $row['date_start'],
			'date_end' => $row['date_end'],
			'page_previews' => '',
		));
		$tz = $this->locale()->timezone($row['timezone']);
		if (empty($argv['class']) && strtotime($row['date_end'] . ' GMT') + $tz[0] < time()) {
			$argv['class'] = 'item-inactive';
		}
		// if (!$row['is_hidden'] && strtotime($row['date_end'] . ' GMT') + $tz[0] + 3600*24 > time()) {
			$sites = explode(',', $row['sites']);
			if ($this->isSlaveHost()) {
				$sites = array_intersect($sites, array('com', base_inplace_syncable::_SITE_ID_));
			} else {
				if (end($sites) == '') {
					array_pop($sites);
				}
			}
			foreach ($sites as $site) {
				switch ($site) {
				case 'com':
					$domain = 'pokernews';
					break;
				case 'pnw:com':
					$domain = 'pokernetwork';
					break;
				}
				$domain .= is_dev()
					? '.dev'
					: '.com';
				$redirect = '';
				if ('com' == $site && !empty($row['remote_id'])) {
					$redirect = '?promo_id_redirect=' . $row['remote_id'];
				} else {
					$redirect = '?promo_id_redirect=' . $row['id'];
				}
				$argv['page_previews'] .= $tpl->parse('list:entries.page_previews.item', array(
					'url' => 'http://' . $domain . '/promo-promos/' . $redirect,
					'title' => $site
				)) . ' ';
			}
		// }
	}

	protected function partialRenderEntry($argv, &$mainArgv, $entryData)
	{
		if (NULL !== $argv['id']) {
			$mainArgv['submenu'] = $this->getPreferredSubmenu($argv['id'], $entryData, $this->my('fullname'));
		}
	}

	public function getPreferredSubmenu($promoId, $entryData, $active)
	{
		$skip = array();
		if (!empty($entryData['remote_id']) || $entryData['lb_auto']) {
			$skip[] = 'promo.leaderboard';
		}
		if (!empty($entryData['remote_id']) && !$this->hasCustomPages($promoId)) {
			$skip[] = 'promo.custom_pages';
		}
		$win = moon::shared('admin');
		$win->active($active);
		return $win->subMenu(array('*id*' => $promoId), $skip);		
	}

	private function hasCustomPages($id)
	{
		$has = $this->db->single_query_assoc('
			SELECT 1 FROM promos_pages
			WHERE promo_id=' . intval($id) . ' AND is_hidden!=2
			LIMIT 1
		');
		return !empty($has);
	}

	protected function eventEntryFormFailedMerge(&$data)
	{
		if (is_array($data['sites'])) {
			$data['sites'] = implode(',', $data['sites']);
		}
	}

	protected function getEntryTextFields()
	{
		return array(
			'terms_conditions'
		);
	}
	
	protected function partialRenderEntryFormOrigin(&$mainArgv, $entryData, $tpl)
	{
		$mainArgv['form.rooms'] = '';
		foreach ($this->getRoomsList() as $entry) {
			$mainArgv['form.rooms'] .= $tpl->parse('entry:rooms.item', array(
				'value' => $entry['id'],
				'name' => htmlspecialchars($entry['name']),
				'selected' => $entry['id'] == $entryData['room_id']
			));
		}

		$mainArgv['form.timezones'] = '';
		foreach (moon::locale()->select_timezones() as $k => $v) {
			$mainArgv['form.timezones'] .= $tpl->parse('entry:timezones.item', array(
				'value' => $k,
				'name' => $v,
				'selected' => $k == $entryData['timezone']
			));
		}

		$mainArgv['form.currency'] = '';
		foreach ($this->dbEnumList($this->table('Entries'), 'currency') as $entry) {
			$mainArgv['form.currency'] .= $tpl->parse('entry:currency.item', array(
				'value' => $entry,
				'name' => $entry,
				'selected' => $entry == $entryData['currency']
			));
		}

		$checkedSites = explode(',', $entryData['sites']);
		$sites = array(base_inplace_syncable::_SITE_ID_);
		$siteChunks = array_chunk($sites, ceil(count($sites) / 4), TRUE);
		foreach ($siteChunks as $k => $chunk) {
			$siteChunkResult = '';
			foreach ($chunk as $sId => $title) {
				$siteChunkResult .= $tpl->parse('entry:sites.item', array(
					'value' => $sId,
					'name' => $title,
					'selected' => in_array($sId, $checkedSites)
				));
			}
			$mainArgv['languages' . $k] = $siteChunkResult;
		}

		foreach (array('date_start', 'date_end') as $key) {
			$mainArgv['form.' . $key] = $entryData[$key] == '0000-00-00'
				? ''
				: $entryData[$key];
		}

		$mainArgv['form.is_master'] = !$this->isSlaveHost();

		$mainArgv['form.lb_auto'] = empty($entryData['lb_auto']) ? '1' : '1" checked="checked';
		if (!empty($entryData['id'])) {
			$mainArgv['url.preview'] = '/promo-promos/?promo_id_redirect=' . $entryData['id'];
		}

		/*if (!empty($entryData['alias'])) {
			$sitemap = moon::shared('sitemap');
			$mainArgv['url.view_promo'] = sitemap->getLink('promos');
		}*/
	}

	protected function partialRenderEntryFormSlave(&$mainArgv, $entryData, $tpl)
	{
		$mainArgv['form.not_lb_auto'] = empty($entryData['lb_auto']);
		if (!empty($entryData['id'])) {
			$mainArgv['url.preview'] = '/promo-promos/?promo_id_redirect=' . $entryData['id'];
		}
	}

	private function getRoomsList()
	{
		return $this->db->array_query_assoc('
			SELECT id, name FROM ' . $this->table('Rooms') . '
			WHERE is_hidden=0
			UNION
			SELECT -id id, name FROM ' . $this->table('CustomRooms') . '
			ORDER BY name
		');
	}

	protected function eventSaveSerializeOrigin(&$saveData)
	{
		if (isset($saveData['sites']) && is_array($saveData['sites'])) {
			$saveData['sites'] = implode(',', $saveData['sites']);
		}
	}

	protected function getSaveRequiredNoEmptyFields()
	{
		return array('title', 'alias');
	}
	
	protected function getSaveNoDupeFields()
	{
		return array('alias');
	}
	
	protected function getSaveCustomValidationErrors($data)
	{
		$errors = array();
		foreach (array('date_start', 'date_end') as $date) {
			if (empty($data[$date]) || !strtotime($data[$date])) {
				$errors[] = 'e.bad_' . $date;
			}
		}

		return $errors;
	}

	protected function eventSavePreSaveOrigin(&$saveData)
	{
		if ('' === $saveData['room_id']) {
			$saveData['room_id'] = null;
		}
		if ($this->isSlaveHost()) {
			$saveData['sites'] = base_inplace_syncable::_SITE_ID_;
		}
	}

	protected function eventSavePostSaveOrigin($saveData)
	{
		if (!empty($saveData['id'])) {
			$this->eventUpdatedPlayerPoints($saveData['id']);
		}
	}

	public function eventUpdatedPlayerPoints($promoId)
	{
		$auto = $this->db->single_query_assoc('
			SELECT id, lb_auto, lb_columns FROM promos
			WHERE id=' . intval($promoId) . '
		');
		if (0 == count($auto) || empty($auto['lb_auto'])) {
			return ;
		}

		$events = $this->db->array_query_assoc('
			SELECT id, results, results_columns
			FROM promos_events
			WHERE promo_id=' . intval($promoId) . ' AND is_hidden=0
		');
		$results = array();
		foreach ($events as $event) {
			if (NULL == ($resultChunks = $this->parseResults($event['results'], $event['results_columns']))) {
				continue;
			}
			foreach($resultChunks['data'] as $row) {
				$playerName = $row[$resultChunks['idx.player']];
				$playerPts  = $row[$resultChunks['idx.points']];
				if (!isset($results[$playerName])) {
					$results[$playerName] = 0;
				}
				$results[$playerName] += $playerPts;
			}
		}
		arsort($results);

		if (NULL == ($resultChunks = $this->parseResults('', $auto['lb_columns']))) {
			$results = '';
		} else {
			$results_ = array();
			foreach ($results as $uname => $points) {
				if ($resultChunks['idx.points'] == 0) {
					$results_[] = trim($points) . "\t" . trim($uname);
				} else {
					$results_[] = trim($uname) . "\t" . trim($points);
				}
			}
			$results = implode("\n", $results_);
		}

		$this->dbUpdate(array(
			'lb_data' => $results,
			'updated_on' => array('FROM_UNIXTIME', time())
		), 'promos', array(
			'id' => $promoId,
			'lb_auto' => 1
		));
	}

	public function parseResults($results, $columns)
	{
		$columns = explode(';', $columns);
		if (end($columns) == '') {
			array_pop($columns);
		}
		$playerCol = null;
		$pointsCol = null;
		foreach ($columns as $k => $column) {
			if (strpos($column, '*') !== false) {
				$playerCol = $k;
				$column = str_replace('*', '', $column);
			} elseif (strpos($column, '+') !== false) {
				$pointsCol = $k;
				$column = str_replace('+', '', $column);
			}
			$columns[$k] = trim($column);
		}
		if ($playerCol === null || $pointsCol === null) {
			return ;
		}

		$data = explode("\n", $results);
		if (end($data) == '') {
			array_pop($data);
		}
		foreach ($data as $k => $row) {
			$data[$k] = explode("\t", $row);
		}

		return array(
			'columns' => $columns,
			'idx.player' => $playerCol,
			'idx.points' => $pointsCol,
			'data' => $data
		);
	}

	public function xlsToCsv($p)
	{
		$r = '';
		include_class('excel_reader/reader');
		$o = new Spreadsheet_Excel_Reader();
		$o->setOutputEncoding('UTF-8');
		$o->setRowColOffset(0);
		$o->read($p);
		$a = &$o->sheets[0]['cells'];
		$r = array();
		foreach ($a as $v) $r[] = implode("\t", $v);
		return implode("\r\n", $r);
	}	
	
	private function sync()
	{
		$sites = array(base_inplace_syncable::_SITE_ID_);
		$sendSites = array();
		unset($sites['com']);

		$data = $this->db->array_query_assoc('
			SELECT *, UNIX_TIMESTAMP(updated_on) updated_on, 
			( updated_on>CURRENT_TIMESTAMP-INTERVAL 2 DAY ) as `update` 
			FROM ' . $this->table('Entries'), 'id');
		foreach ($data as $k => $v) {
			$data[$k]['custom_pages'] = array();
		}

		$dataCustomPages =  $this->db->array_query_assoc('
			SELECT *, UNIX_TIMESTAMP(updated_on) updated_on 
			FROM promos_pages
			WHERE updated_on>CURRENT_TIMESTAMP-INTERVAL 2 DAY
		');
		foreach ($dataCustomPages as $k => $v) {
			$data[$v['promo_id']]['custom_pages'][] = $v;
		}

		$dataEvents =  $this->db->array_query_assoc('
			SELECT *, UNIX_TIMESTAMP(updated_on) updated_on, UNIX_TIMESTAMP(start_date) start_date, UNIX_TIMESTAMP(pwd_date) pwd_date 
			FROM promos_events
			WHERE updated_on>CURRENT_TIMESTAMP-INTERVAL 2 DAY
		');
		foreach ($dataEvents as $k => $v) {
			$data[$v['promo_id']]['events'][] = $v;
		}

		foreach ($sites as $siteId => $null) {
			$this->currentFilter = $siteId;
			$siteData = array_filter($data, array($this, 'syncSiteDataFilter'));

			if (0 == count($siteData)) {
				continue;
			}
			callPnEvent($siteId, 'promo.promo_sync#sync', array(
			 	'data' => $siteData
			), $answer,FALSE);
			header('content-type: text/plain');
			print_R($answer);
		}
	}

	private $currentFilter;
	private function syncSiteDataFilter($row) 
	{
		return in_array($this->currentFilter, explode(',', $row['sites'])) && 
			(
				!empty($row['update'])
				 ||
				0 != count($row['custom_pages'])
			);
	}

	public function updates()
	{
		if ('com'==_SITE_ID_ ) {
			return array();
		}
		// index
		$updatesBatch[0] = $this->db->array_query('
			SELECT id, title FROM promos
			WHERE remote_updated_on>updated_on
			  AND is_hidden<2
		');
		$updatesBatch[1] = $this->db->array_query('
			SELECT pp.id, pp.promo_id, pp.title FROM promos_pages pp
			INNER JOIN promos p ON p.id=pp.promo_id
			WHERE pp.remote_updated_on>pp.updated_on
			  AND pp.is_hidden<2 AND p.is_hidden<2
		');
		$updatesBatch[2] = $this->db->array_query('
			SELECT pe.id, pe.promo_id, pe.title FROM promos_events pe
			INNER JOIN promos p ON p.id=pe.promo_id
			WHERE pe.remote_updated_on>pe.updated_on
			  AND pe.is_hidden<2 AND p.is_hidden<2
		');
		return $updatesBatch;
	}
}