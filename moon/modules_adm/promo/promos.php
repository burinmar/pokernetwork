<?php

class promos extends moon_com_ext
{
	use promos_base;
	function events($event, $argv)
	{
		if ($event == 'prepared-promos') { // accessed from js
			echo $this->renderPreparedPromos();
			exit;
		} elseif ($event == 'active-sites-for-room') { // accessed from js
			echo $this->renderActiveSitesForRoom(is_array($_POST['room_ids'])
				? $_POST['room_ids']
				: array());
			exit;
		}

		switch ($event) {
			case 'save':
				$data = $this->eventPostData(array_merge(array_keys($this->entryFromVoid()), [
					'stayhere', 'delete_img_list', 'delete_img_main'
				]), ['img_list', 'img_main']);
				$savedId = $this->saveEntry($data);
				return $this->eventSaveRedirect($savedId, $data);

			case 'delete':
				$data = $this->eventPostData('ids');
				$this->deleteEntry(explode(',', $data['ids']));
				$this->redirect('#');
				exit;

			case 'new':
				$this->set_var('render', 'entry');
				break;

			default:
				if (isset($argv[0]) && false !== ($id = filter_var($argv[0], FILTER_VALIDATE_INT))) {
					$this->set_var('render', 'entry');
					$this->set_var('id', $id);
				}
				if (isset ($_GET['page'])) {
					$this->set_var('page', (int)$_GET['page']);
				}
				break;
		}

		if (isset($_GET['sync']) || isset($_POST['sync'])) {
			$this->sync();
			moon_close(); exit;
		}

		$this->use_page('Common');
		moon::page()->js('/js/modules_adm/promo.js');
	}

	function main($argv)
	{
		moon::shared('admin')->active($this->my('fullname'));

		return parent::main($argv);
	}

	/**
	 * List
	 */
	protected function renderList($argv, &$e)
	{
		$page   = moon::page();
		$tpl    = $this->load_template();

		$page->js('/js/modules_adm/ng-list.js');

		$iAmHere = array_reverse(moon::shared('admin')->breadcrumb());
		$mainArgv  = array(
			'title' => isset($iAmHere[0])
				? $iAmHere[0]['name']
				: '',
			'url.add_entry' => $this->link('#new', ''),
			'event.delete'  => $this->my('fullname') . '#delete',
			'list.entries'  => '',
			'paging' => '',
			'synced_notice' => $this->isSlaveHost(),
			'show_sync' => !$this->isSlaveHost()
		);

		$pn = moon::shared('paginate');
		$pn->set_curent_all_limit($argv['page'], $this->getEntriesCount(), $this->get_var('entriesPerPage'));
		$pn->set_url($this->link('#', '', ['page' => '{pg}']), $this->link('#', ''));
		$pnInfo = $pn->get_info();
		$mainArgv['paging'] = $pn->show_nav();

		$list = $this->getEntriesList($pnInfo['sqllimit']);
		$this->eventEntriesGotItems($list);

		foreach ($list as $row) {
			$rowArgv = array(
				'id' => $row['id'],
				'class' => $row['is_hidden'] ? 'item-hidden' : '',
				'url' => $this->link('#', $row['id']),
				'name' => htmlspecialchars($this->getEntryTitle($row)),
				'deletable' => empty($row['remote_id'])
			);
			if (!empty($row['remote_id'])) {
				$rowArgv['synced'] = true;
				$rowArgv['sync_state'] = intval($row['updated_on'])>intval($row['remote_updated_on']) ? 1 : 2;
			}
			$this->partialRenderListRow($row, $rowArgv, $tpl);
			$mainArgv['list.entries'] .= $tpl->parse('list:entries.item', $rowArgv);
		}
		return $tpl->parse('list:main', $mainArgv);
	}

	private function getEntriesCount()
	{
		$cnt = $this->db->single_query_assoc('
			SELECT count(*) cnt
			FROM ' . $this->table('Entries') . ' e
			WHERE is_hidden<2'
		);
		return $cnt['cnt'];
	}

	private function getEntriesList($limit)
	{
		$fields = array(
			'id', 'title', 'is_hidden', 'UNIX_TIMESTAMP(updated_on) updated_on',
			'date_start', 'date_end', 'room_id', 'sites', 'timezone', 'alias', 'weight'
		);
		if ($this->isSlaveHost()) {
			$fields[] = 'remote_id';
			$fields[] = 'UNIX_TIMESTAMP(remote_updated_on) remote_updated_on';
		}
		return $this->db->array_query_assoc('
			SELECT ' . implode(',', $fields) . '
			FROM ' . $this->table('Entries') . ' e
			WHERE is_hidden<2
			ORDER BY date_start DESC ' .
			$limit
		);
	}

	private $rooms = array();
	private function eventEntriesGotItems($list)
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

	private function partialRenderListRow($row, &$argv, $tpl)
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
		if ($argv['class'] == '') {
			$argv['weight'] = $row['weight'];
		}
		if (!$row['is_hidden']) {
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
		}
	}

	/**
	 * Entry
	 */
	protected function renderEntry($argv, &$e)
	{
		$page   = moon::page();
		$tpl    = $this->load_template();
		$locale = moon::locale();
		$text = moon::shared('text');
		$mainArgv  = array(
			'url.back' => $this->link('#'),
			'event.save' => $this->my('fullname') . '#save'
		);

		if (NULL === $argv['id']) {
			$entryData = $this->entryFromVoid();
		} else {
			if (NULL === ($entryData = $this->entryFromDB($argv['id']))) {
				$messages = $tpl->parse_array('messages');
				$e  = $messages['e.entry_not_found'];
				return ;
			}
		}
		$entryData = array_merge($entryData, $this->popFailedFormData());

		if (NULL !== $argv['id']) {
			$mainArgv['submenu'] = $this->getPreferredSubmenu($argv['id'], $entryData, $this->my('fullname'));
			$mainArgv['edits'] = ''; // $this->object('sys.blame_edits')->getEditsSnippet('promo', $argv['id']);
		}
		if (isset($entryData['is_live_league'])) {
			$mainArgv['form.is_live_league.show'] = true;
			$mainArgv['form.is_live_league'] = empty($entryData['is_live_league']) ? '1' : '1" checked="checked';
		}
		if (!isset($mainArgv['title'])) {
			$mainArgv['title'] = htmlspecialchars($this->getEntryTitle($entryData));
		}

		//
		foreach ($entryData as $key => $value) {
			$mainArgv['entry.' . $key] = htmlspecialchars($value);
		}
		// varchar maxlen
		foreach ($this->getEntryCharLength() as $key => $value) {
			$mainArgv['form.' . $key . '.maxlen'] = $value;
		}
		// images
		$storage = moon::shared('storage');
		foreach ($this->getEntryImageFields() as $k) {
			$mainArgv['form.' . $k[0]] = !empty($entryData[$k[0]])
				? $storage->location($k[2])->url($entryData[$k[0]], 0)
				: NULL;
		}
		// text toolbars
		$rtf = $this->object('rtf');
		if ('' != ($varRtf = $this->get_var('rtf'))) {
			$rtf->setInstance($varRtf);
		}
		foreach ($this->getEntryRtfFields() as $k) {
			$mainArgv['entry.' . $k . '.toolbar'] = $rtf->toolbar($k, (int)$entryData['id']);
		}
		//
		$mainArgv['form.hide'] = empty($entryData['is_hidden']) && !(NULL === $argv['id']) ? '1' : '1" checked="checked';

		// pokernews cup promotion checkbox
		$mainArgv['form.pokernewscup'] = empty($entryData['pokernewscup']) ? '1' : '1" checked="checked';

		//
		if ($this->isSlaveHost() && !empty($entryData['remote_id'])) {
			$mainArgv['syncStatus'] = intval($entryData['updated_on'])>intval($entryData['remote_updated_on']) ? 1 : 2;
			$mainArgv['remote_id'] = $entryData['remote_id'];
			$remote = $this->getOriginInfo($entryData['remote_id']);
			foreach ($this->getEntryTranslatables() as $k) {
				$mainArgv['form.origin_' . $k] = !empty($remote[$k . '_prev']) || $entryData['updated_on'] != '0'
					? nl2br($text->htmlDiff($remote[$k . '_prev'], $remote[$k]))
					: nl2br(htmlspecialchars(@$remote[$k]));
			}
		}

		if (empty($entryData['remote_id'])) {
			$this->partialRenderEntryFormOrigin($mainArgv, $entryData, $tpl);
		} else {
			$this->partialRenderEntryFormSlave($mainArgv, $entryData, $tpl);
		}

		$tplName = empty($entryData['remote_id'])
			? 'entry:main'
			: 'entry:slaveMain';
		return $tpl->parse($tplName, $mainArgv);
	}

	private function partialRenderEntryFormOrigin(&$mainArgv, $entryData, $tpl)
	{
		$mainArgv['form.rooms'] = '';
		$roomIds = explode(',', $entryData['room_id']);
		foreach ($this->getRoomsList() as $entry) {
			$mainArgv['form.rooms'] .= $tpl->parse('entry:rooms.item', array(
				'value' => $entry['id'],
				'name' => htmlspecialchars($entry['name']),
				'selected' => in_array($entry['id'], $roomIds)
			));
		}

		$mainArgv['form.timezones'] = '';
		foreach ($this->locale()->select_timezones() as $k => $v) {
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

	private function partialRenderEntryFormSlave(&$mainArgv, $entryData, $tpl)
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
			UNION
			SELECT -id id, name FROM ' . $this->table('CustomRooms') . '
			ORDER BY name
		');
	}

	private function getOriginInfo($id)
	{
		return $this->db->single_query_assoc('
			SELECT * FROM ' . $this->table('EntriesMaster') . '
			WHERE id=' . intval($id)
		);
	}

	/**
	 * Entry/save shared
	 */
	private function entryFromVoid()
	{
		return $this->entryFromMetadata($this->table('Entries'));
	}

	private function entryFromDB($id)
	{
		if (false === filter_var($id, FILTER_VALIDATE_INT)) {
			return NULL;
		}
		$fields = array('*');
		foreach ($this->getEntryTimestampFields() as $field) {
			$fields[] = 'UNIX_TIMESTAMP(' . $field . ') ' . $field . '';
		}
		$entry = $this->db->single_query_assoc('
			SELECT ' . implode(', ', $fields) . '
			FROM ' . $this->table('Entries') . '
			WHERE id=' . $id . '
		');
		if (empty($entry)) {
			return NULL;
		}
		return $entry;
	}

	protected function entryFromPost($data)
	{
		if (!$this->isSlaveHost() || empty($data['remote_id'])) {
			$this->eventSaveSerializeOrigin($data);
		} else {
			$this->eventSaveSerializeSlave($data);
		}
		return $data;
	}
	private function eventSaveSerializeOrigin(&$saveData)
	{
		if (isset($saveData['sites']) && is_array($saveData['sites'])) {
			$saveData['sites'] = implode(',', $saveData['sites']);
		} else
			$saveData['sites'] = '';

		if (isset($saveData['room_id']) && is_array($saveData['room_id'])) {
			$saveData['room_id'] = implode(',', $saveData['room_id']);
		} else
			$saveData['room_id'] = '';

		if (is_array($saveData['descr_steps']))
			$saveData['descr_steps'] = implode("\n", $saveData['descr_steps']);
		if (is_array($saveData['descr_steps_images']))
			$saveData['descr_steps_images'] = implode("\n", $saveData['descr_steps_images']);
	}
	private function eventSaveSerializeSlave(&$saveData)
	{
		if (is_array($saveData['descr_steps']))
			$saveData['descr_steps'] = implode("\n", $saveData['descr_steps']);
	}
	protected function getEntryImageFields()
	{
		return array(
			array('img_list', 1, 'promo-list'),
			array('img_main', 1, 'promo-main'),
		);
	}

	/**
	 * Save
	 */
	protected function saveEntry($data)
	{
		// $data['pokernewscup'] = empty($data['pokernewscup']) ? 0 : 1;
		return $this->saveEntryFlow($data, [
			'origin_validate'  => function($data) {return $this->eventSaveCustomValidateOrigin($data);},
			'origin_pre_save'  => function(&$saveData) {return $this->eventSavePreSaveMaster($saveData);},
			'origin_post_save' => function($saveData, $id) {return $this->eventSavePostSaveOrigin($saveData, $id);},
			'slave_post_save' => function($saveData, $id) {return $this->eventSavePostSaveSlave($saveData, $id);}
		]);
	}
	private function eventSaveCustomValidateOrigin($data)
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
	private function eventSavePreSaveMaster(&$saveData)
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
	private function eventSavePostSaveOrigin($saveData, $id)
	{
		if (!empty($saveData['id'])) {
			$this->eventUpdatedPlayerPoints($saveData['id']);
		}

		// $store = $saveData; unset($store['updated_on']);
		// store_edit($this->my('module'), $id, $store);
	}
	private function eventSavePostSaveSlave($saveData, $id)
	{
		// $store = $saveData; unset($store['updated_on']);
		// store_edit($this->my('module'), $id, $store);
	}

	/**
	 * Delete
	 */
	private function deleteEntry($ids)
	{
		return $this->deleteEntryWorkflow($ids);
	}

	/**
	 * Other
	 */
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

		$dataCustomPages = $this->db->array_query_assoc('
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
		// do not send broken items; the remote end will have inconsistent data
		foreach ($data as $k => $v) {
			if (!isset($v['self']))
				unset($data[$k]);
		}

		$cutoffDate = gmdate('Y-m-d', time());
		foreach ($sites as $siteId => $null) {
			if (!isset($sendStates[$siteId]))
				continue;
			$siteData = array();
			foreach ($data as $row) {
				// mark site-disabled entries as deleted
				if (!in_array($siteId, explode(',', $row['self']['sites'])))
					$row['self']['is_hidden'] = 2;
				// skip sending promos, which are obviously too old
				if ($row['self']['date_end'] !== null && $row['self']['date_end'] < $cutoffDate)
					continue ;
				$siteData[] = $row;
			}
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

	private function renderActiveSitesForRoom($roomIds)
	{
		if (count($roomIds)) {
			$roomIds = array_map('intval', $roomIds);
			callPnEvent('adm', 'reviews.export#get-room-active',   array('room_ids'=>$roomIds), $response);
			callPnEvent('pnw:com', 'promo.promos#get-room-active', array('room_ids'=>$roomIds), $answer);
			if (true === $answer)
				$response[] = 'pnw:com';
		} else
			$response = array();

		header('content-type: application/json');
		echo json_encode($response);
		ob_start();
		moon_close();
		ob_end_clean();
		exit;
	}
}
