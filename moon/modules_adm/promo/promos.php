<?php

require_once 'base_inplace_syncable.php';
class promos extends base_inplace_syncable
{
	function events($event, $argv)
	{
		if ($event == 'prepared-promos') { // accessed from js
			echo $this->renderPreparedPromos();
			exit;
		}

		parent::events($event, $argv);
		if (isset($_GET['sync']) || isset($_POST['sync'])) {
			$this->sync();
			moon_close();exit;
		}
	}

	protected function getEntriesListAdditionalFields()
	{
		$return = array('date_start', 'date_end', 'room_id', 'sites', 'timezone', 'alias');
		if (_SITE_ID_ != 'com') {
			$return[] = 'remote_id';
		}
		return $return;
	}

	protected function getEntriesListOrderBy()
	{
		return array('date_start DESC');
	}

	private $rooms = array();
	protected function eventEntriesGotItems($list)
	{
		$roomIds = array();
		$cRoomIds = array();
		foreach ($list as $row) {
			foreach (explode(',', $row['room_id']) as $roomId) {
				$roomId = intval($roomId);
				if ($roomId > 0) {
					$roomIds[] = $roomId;
				} elseif ($roomId < 0) {
					$cRoomIds[] = -$roomId;
				}
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
			'room' => array(),
			'is_completed' => '',
			'date_start' => $row['date_start'],
			'date_end' => $row['date_end'] === null ? 'TBD' : $row['date_end'],
			'page_previews' => '',
		));
		foreach (explode(',', $row['room_id']) as $roomId)
			$argv['room'][]= htmlspecialchars($this->getRoomName($roomId));
		$argv['room'] = implode(', ', $argv['room']);
		$tz = $this->locale()->timezone($row['timezone']);
		if (empty($argv['class']) && null !== $row['date_end'] && strtotime($row['date_end'] . ' GMT') + $tz[0] < time()) {
			$argv['class'] = 'item-inactive';
		}
		// if (!$row['is_hidden'] && strtotime($row['date_end'] . ' GMT') + $tz[0] + 3600*24 > time()) {
			$sites = explode(',', $row['sites']);
			if ($this->isSlaveHost()) {
				$sites = array_intersect($sites, array('com', _SITE_ID_));
			} else {
				if (end($sites) == '') {
					array_pop($sites);
				}
			}
			foreach ($sites as $site) {
				switch ($site) {
				case 'pnw:com':
					$domain = 'www.pokernetwork';
					break;
				default:
					$domain = 'com' == $site
						? 'www.pokernews'
						: $site . '.pokernews';
				}
				$domain .= is_dev()
					? '.dev'
					: '.com';
				$argv['page_previews'] .= $tpl->parse('list:entries.page_previews.item', array(
					'url' => 'http://' . $domain . '/promo-promos/?promo_alias_redirect=' . rawurlencode($row['alias']),
					'title' => $site,
					'siteid'=> $site
				)) . ' ';
			}
		// }
	}

	protected function partialRenderEntry($argv, &$mainArgv, $entryData, $tpl)
	{
		if (NULL !== $argv['id']) {
			$mainArgv['submenu'] = $this->getPreferredSubmenu($argv['id'], $entryData, $this->my('fullname'));
		}

		if (isset($entryData['is_live_league'])) {
			$mainArgv['form.is_live_league.show'] = true;
			$mainArgv['form.is_live_league'] = empty($entryData['is_live_league']) ? '1' : '1" checked="checked';
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

		$checkedSites = explode(',', $entryData['sites']);
		$sites = array(_SITE_ID_);
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

		$stepsDescr = explode("\n", $entryData['descr_steps'] . "\n\n");
		$stepsDescrImages = explode("\n", $entryData['descr_steps_images'] . "\n\n");
		$stepsImages = $tpl->parse_array('entry:descr_steps_images.list');
		for ($i = 0; $i < 3; $i++) {
			$mainArgv['form.descr_steps_' . $i] = htmlspecialchars($stepsDescr[$i]);
			$mainArgv['form.descr_steps_images_' . $i] = '';
			foreach ($stepsImages as $stepsImageId => $stepsImageName)
				$mainArgv['form.descr_steps_images_' . $i] .= $tpl->parse('entry:descr_steps_images.item', array(
					'value' => $stepsImageId,
					'name' => $stepsImageName,
					'selected' => $stepsDescrImages[$i] == $stepsImageId
				));
		}

		foreach (array('date_start', 'date_end') as $key) {
			$mainArgv['form.' . $key] = in_array($entryData[$key], ['0000-00-00', null], true)
				? ''
				: $entryData[$key];
		}

		$mainArgv['form.is_master'] = !$this->isSlaveHost();

		$mainArgv['form.lb_auto'] = empty($entryData['lb_auto']) ? '1' : '1" checked="checked';
		if (!empty($entryData['id'])) {
			$mainArgv['url.preview'] = '/promo-promos/?promo_alias_redirect=' . rawurlencode($entryData['alias']);
		}
	}

	protected function partialRenderEntryFormSlave(&$mainArgv, $entryData, $tpl)
	{
		$mainArgv['form.not_lb_auto'] = empty($entryData['lb_auto']);
		if (!empty($entryData['id'])) {
			$mainArgv['url.preview'] = '/promo-promos/?promo_alias_redirect=' . rawurlencode($entryData['alias']);
		}

		$stepsDescr = explode("\n", $entryData['descr_steps'] . "\n\n");
		for ($i = 0; $i < 3; $i++) {
			$mainArgv['form.descr_steps_' . $i] = htmlspecialchars($stepsDescr[$i]);
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
		} else
			$saveData['sites'] = '';

		if (is_array($saveData['descr_steps']))
			$saveData['descr_steps'] = implode("\n", $saveData['descr_steps']);
		if (is_array($saveData['descr_steps_images']))
			$saveData['descr_steps_images'] = implode("\n", $saveData['descr_steps_images']);
	}

	protected function eventSaveSerializeSlave(&$saveData)
	{
		if (is_array($saveData['descr_steps']))
			$saveData['descr_steps'] = implode("\n", $saveData['descr_steps']);
	}

	protected function eventSaveCustomValidateOrigin($data)
	{
		$errors = array();

		if (!empty($data['lb_auto'])) {
			$columnsCount = count(explode(';', $data['lb_columns']));
			if ($columnsCount != 2) {
				$errors[] = 'e.bad_lb_auto_columns';
			}
		}
		if (NULL == ($resultChunks = $this->parseResults('', $data['lb_columns']))) {
			$errors[] = 'e.bad_lb_columns';
		}
		if (empty($data['date_start']) || !strtotime($data['date_start'])) {
			$errors[] = 'e.bad_date_start';
		}
		if (!empty($data['date_end']) && !strtotime($data['date_end'])) {
			$errors[] = 'e.bad_date_end';
		}

		return $errors;
	}

	protected function eventSavePreSaveMaster(&$saveData)
	{
		if ('' === $saveData['room_id']) {
			$saveData['room_id'] = null;
		}
		if ('' === $saveData['date_end']) {
			$saveData['date_end'] = null;
		}
		if ($this->isSlaveHost()) {
			$saveData['sites'] = _SITE_ID_;
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
				$playerName = isset($row[$resultChunks['idx.player']])
					? $row[$resultChunks['idx.player']]
					: '';
				$playerPts  = isset($row[$resultChunks['idx.points']])
					? $row[$resultChunks['idx.points']]
					: '';
				if ('' === $playerName || '' === $playerPts) {
					continue;
				}
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

	private function sync()
	{
		$sites = getSitesList();
		$sendSites = array();
		unset($sites['com']);
		$sites['pnw:com'] = null;
		$sites['learn'] = null;

		$return = array(
			'ok' => 1,
			'failed_sites' => array()
		);
		header('content-type: application/json');
		error_reporting(0);

		$sendStates = array();
		foreach ($sites as $siteId => $null) {
			$sent = callPnEvent($siteId, 'promo.promo_sync#pre-sync', array(
				'data' => $siteData
			), $syncedAt,FALSE);
			if (Ssent && is_int($syncedAt))
				$sendStates[$siteId] = $syncedAt;
			else {
				$return['ok'] = 0;
				$return['failed_sites'][] = $siteId;
			}
		}
		$fetchSince = min($sendStates);
		$fetchAddIds = array(0);
		$data = array();

		$dataCustomPages =  $this->db->array_query_assoc('
			SELECT *, UNIX_TIMESTAMP(updated_on) updated_on
			FROM promos_pages
			WHERE updated_on>FROM_UNIXTIME(' . $fetchSince . ')
			ORDER BY updated_on
		');
		foreach ($dataCustomPages as $k => $v) {
			$data[$v['promo_id']]['custom_pages'][] = $v;
			$fetchAddIds[] = $v['promo_id'];
		}

		$dataEvents =  $this->db->array_query_assoc('
			SELECT *, UNIX_TIMESTAMP(updated_on) updated_on, UNIX_TIMESTAMP(start_date) start_date, UNIX_TIMESTAMP(pwd_date) pwd_date,
				-freeroll_id freeroll_id
			FROM promos_events
			WHERE updated_on>FROM_UNIXTIME(' . $fetchSince . ')
			ORDER BY updated_on
		');
		foreach ($dataEvents as $k => $v) {
			$data[$v['promo_id']]['events'][] = $v;
			$fetchAddIds[] = $v['promo_id'];
		}

		$dataPromos = $this->db->array_query_assoc('
			SELECT *, UNIX_TIMESTAMP(updated_on) updated_on
			FROM ' . $this->table('Entries') . '
			WHERE updated_on>FROM_UNIXTIME(' . $fetchSince . ') OR id IN (' . implode(',', $fetchAddIds) . ')
			ORDER BY updated_on',
		'id');
		foreach ($dataPromos as $k => $v) {
			$data[$v['id']]['self'] = $v;
		}

		foreach ($sites as $siteId => $null) {
			if (!isset($sendStates[$siteId]))
				continue;
			$siteData = array_filter($data, function($row) use ($siteId) {
				return in_array($siteId, explode(',', $row['self']['sites']));
			});
			if (0 == count($siteData))
				continue;

			$sent = callPnEvent($siteId, 'promo.promo_sync#sync', array(
				'data' => $siteData
			), $answer,FALSE);
			if (!$sent) {
				$return['ok'] = 0;
				$return['failed_sites'][] = $siteId;
			}
		}

		echo json_encode($return);
	}

	public function updates()
	{
		if ('com'==_SITE_ID_ ) {
			return array();
		}
		// index
		$updatesBatch[0] = $this->db->array_query('
			SELECT id, title FROM promos
			WHERE remote_updated_on>=updated_on
			  AND is_hidden<2
		');
		$updatesBatch[1] = $this->db->array_query('
			SELECT pp.id, pp.promo_id, pp.title FROM promos_pages pp
			INNER JOIN promos p ON p.id=pp.promo_id
			WHERE pp.remote_updated_on>=pp.updated_on
			  AND pp.is_hidden<2 AND p.is_hidden<2
		');
		$updatesBatch[2] = $this->db->array_query('
			SELECT pe.id, pe.promo_id, pe.title FROM promos_events pe
			INNER JOIN promos p ON p.id=pe.promo_id
			WHERE pe.remote_updated_on>=pe.updated_on
			  AND pe.is_hidden<2 AND p.is_hidden<2
		');
		return $updatesBatch;
	}

	private function renderPreparedPromos()
	{
		$preparedPromos = array();
		$sites = getSitesList();
		$sites['pnw:com'] = null;
		$sites['learn'] = null;
		foreach ($sites as $siteId => $null) {
			callPnEvent($siteId, 'promo.promos#get-active-promos', null, $answer);
			if (!is_array($answer))
				continue;
			foreach ($answer as $id) {
				if (!isset($preparedPromos[$id]))
					$preparedPromos[$id] = array();
				$preparedPromos[$id][] = $siteId;
			}
		}

		echo json_encode($preparedPromos);
		ob_start();
		moon_close();
		ob_end_clean();
		exit;
	}
}
