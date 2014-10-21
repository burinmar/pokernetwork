<?php

class promos extends moon_com_ext
{
	use promos_base;
	function events($event, $argv)
	{
		switch ($event) {
			case 'save':
				$data = $this->eventPostData(array_merge(array_keys($this->entryFromVoid()), [
					'stayhere', 'delete_img_list', 'delete_img_main'
				]), ['img_list', 'img_main']);
				$savedId = $this->saveEntry($data);
				return $this->eventSaveRedirect($savedId, $data);

			case 'sync-tool':
				$this->set_var('render', 'sync-tool');
				$this->set_var('id', $argv[0]);
				$this->use_page('');
				return;

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

		if (isset($_GET['sync'])) {
			$this->sync($_GET['site_id'], $_GET['promo']);
			exit;
		}

		$this->use_page('Common');
	}

	function main($argv)
	{
		moon::shared('admin')->active($this->my('fullname'));

		return parent::main($argv);
	}

// List
	protected function renderList($argv, &$e)
	{
		$page   = moon::page();
		$page->js('/js/modal.window.js');
		$tpl    = $this->load_template();

		$mainArgv  = array(
			'title' => is_array($i_am_here = moon::shared('admin')->breadcrumb())
				? reset($i_am_here)['name']
				: '',
			'list.entries'  => '',
			'paging' => '',
		);

		// paging
		$pn = moon::shared('paginate');
		$pn->set_curent_all_limit($argv['page'], $this->getEntriesCount(), $this->get_var('entriesPerPage'));
		$pn->set_url($this->link('#', '', ['page' => '{pg}']), $this->link('#', ''));
		$pnInfo = $pn->get_info();
		$mainArgv['paging'] = $pn->show_nav();

		// list
		$list = $this->getEntriesList($pnInfo['sqllimit']);
		$this->eventEntriesGotItems($list);
		foreach ($list as $row) {
			$rowArgv = array(
				'id' => $row['id'],
				'class' => $row['is_hidden'] ? 'item-hidden' : '',
				'url' => $this->link('#', $row['id']),
				'name' => htmlspecialchars($this->getEntryTitle($row)),
				'pokernewscup' => $row['pokernewscup'] == '1',
				'sync_state' => intval($row['updated_on']) > intval($row['remote_updated_on']) ? 1 : 2,
			);
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
			'id', 'title', 'is_hidden', 'pokernewscup', 'UNIX_TIMESTAMP(updated_on) updated_on',
			'date_start', 'date_end', 'room_id', 'sites', 'timezone', 'alias', 'weight',
			'UNIX_TIMESTAMP(remote_updated_on) remote_updated_on'
		);
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
			$sites = array_intersect($sites, array('com', _SITE_ID_));
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
				if ($argv['pokernewscup'])
					$domain = str_replace('.pokernews', '.pokernewscup', $domain);
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
//

// Sync tool
	protected function renderSyncTool($args, &$e)
	{
		$tpl = $this->load_template();
		$page = moon::page();

		if (NULL === ($entryData = $this->entryFromDB($args['id']))) {
			$page->page404();
		}

		$sites = explode(',', $entryData['sites']);
		array_splice($sites, array_search('com', $sites), 1);

		$mainArgv = [];
		$mainArgv['sync_column_0'] = $mainArgv['sync_column_1'] = $mainArgv['sync_column_2'] = $mainArgv['sync_column_3'] = '';

		foreach (array_chunk($sites, ceil(count($sites) / 4)) as $sites_column_nr => $sites_column) {
			foreach ($sites_column as $sync_site) {
				$mainArgv['sync_column_' . $sites_column_nr] .= $tpl->parse('sync_site', [
					'site_id' => $sync_site,
					'promo_id' => $entryData['id'],
					'name' => $sync_site,
				]);
			}
		}

		$page->set_local('output', 'modal');
		return $tpl->parse('sync_tool:main', $mainArgv);
	}
//

// Entry
	protected function renderEntry($argv, &$e)
	{
		$page   = moon::page();
		$tpl    = $this->load_template();
		$locale = moon::locale();
		$text = moon::shared('text');
		$mainArgv  = array(
			'url.back' => $this->link('#'),
			'event.save' => $this->my('fullname') . '#save',
			'url.sync' => $this->link('#sync-tool', $argv['id']),
		);

		if (NULL === ($entryData = $this->entryFromDB($argv['id']))) {
			$messages = $tpl->parse_array('messages');
			$e  = $messages['e.entry_not_found'];
			return ;
		}
		$entryData = array_merge($entryData, $this->popFailedFormData());

		$mainArgv['submenu'] = $this->getPreferredSubmenu($argv['id'], $entryData, $this->my('fullname'));
		$mainArgv['edits'] = ''; // $this->object('sys.blame_edits')->getEditsSnippet($this->my('fullname'), $argv['id']);
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

		$mainArgv['syncStatus'] = intval($entryData['updated_on'])>intval($entryData['remote_updated_on']) ? 1 : 2;
		$remote = $this->getOriginInfo($entryData['id']);
		foreach ($this->getEntryTranslatables() as $k) {
			$mainArgv['form.origin_' . $k] = !empty($remote[$k . '_prev']) || $entryData['updated_on'] != '0'
				? nl2br($text->htmlDiff(@$remote[$k . '_prev'], @$remote[$k]))
				: nl2br(htmlspecialchars(@$remote[$k]));
		}

		$this->partialRenderEntryFormSlave($mainArgv, $entryData, $tpl);

		return $tpl->parse('entry:slaveMain', $mainArgv);
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

	private function getOriginInfo($id)
	{
		return $this->db->single_query_assoc('
			SELECT * FROM ' . $this->table('EntriesMaster') . '
			WHERE id=' . intval($id)
		);
	}
//

// Entry/save shared
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
		if (is_array($data['descr_steps']))
			$data['descr_steps'] = implode("\n", $data['descr_steps']);

		return $data;
	}

	protected function getEntryRtfFields()
	{
		return ['descr_prize', 'terms_conditions'];
	}
	protected function getEntryTranslatables()
	{
		return [
			'title', 'menu_title', 'prize', 'descr_intro', 'descr_meta', 'descr_steps',
			'descr_prize', 'descr_qualify', 'terms_conditions', 'lb_columns', 'lb_descr'
		];
	}
	protected function getEntrySlaveRequiredFields()
	{
		return ['title'];
	}

//

// Save
	protected function saveEntry($data)
	{
		// $data['pokernewscup'] = empty($data['pokernewscup']) ? 0 : 1;
		return $this->saveEntryFlow($data, [
			'slave_post_save' => function($saveData, $id) {return $this->eventSavePostSaveSlave($saveData, $id);}
		]);
	}
	private function eventSavePostSaveSlave($saveData, $id)
	{
	}
//

// Sync
	private function sync($site, $promo_id)
	{
		header('content-type: application/json');
		$return = array(
			'ok' => 1,
			'failed_sites' => []
		);

		$data = [];
		$fetch_root_ids = [0];

		$data_promos = $this->db->array_query_assoc('
			SELECT id, is_hidden, ' . implode(',', $this->getEntryTranslatables()) . ', UNIX_TIMESTAMP(updated_on) updated_on
			FROM ' . $this->table('Entries') . '
			WHERE id IN (' . intval($promo_id) . ')
			ORDER BY updated_on',
		'id');
		foreach ($data_promos as $k => $v) {
			$data[$v['id']]['self'] = $v;
			$fetch_root_ids = [$v['id']];
		}

		$data_custom_pages = $this->db->array_query_assoc('
			SELECT id, is_hidden, promo_id, ' . implode(',', $this->object('custom_pages')->getEntryTranslatables()) . ', UNIX_TIMESTAMP(updated_on) updated_on
			FROM promos_pages
			WHERE promo_id IN (' . implode(',', $fetch_root_ids) . ')
			ORDER BY updated_on
		');
		foreach ($data_custom_pages as $k => $v) {
			$data[$v['promo_id']]['custom_pages'][] = $v;
		}

		$data_events = $this->db->array_query_assoc('
			SELECT id, is_hidden, promo_id, ' . implode(',', $this->object('schedule')->getEntryTranslatables()) . ', UNIX_TIMESTAMP(updated_on) updated_on
			FROM promos_events
			WHERE promo_id IN (' . implode(',', $fetch_root_ids) . ')
			ORDER BY updated_on
		');
		foreach ($data_events as $k => $v) {
			$data[$v['promo_id']]['events'][] = $v;
		}

		if (0 != count($data)) {
			$sent = callPnEvent($site, 'promo.promo_sync#sync-text', array(
				'data' => $data
			), $answer,FALSE);
			if (!$sent) {
				$return['ok'] = 0;
				$return['failed_sites'][] = $site['siteid'];
			}
		}

		echo json_encode($return);

		ob_start();
		moon_close();
		ob_end_clean();
		exit;
	}
//

// Todo
	public function updates()
	{
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
//
}
