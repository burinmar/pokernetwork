<?php

class schedule extends moon_com_ext
{
	use promos_base;
	private $promoId;
	private $promo;
	function events($event, $argv)
	{
		if ('' === $argv)
			moon::page()->page404();
		$promoId = array_pop($argv);
		if (empty($promoId) || !($this->promo = $this->getPromo($promoId)))
			moon::page()->page404();
		$this->promoId = intval($promoId);

		switch ($event) {
			case 'save':
				$data = $this->eventPostData(array_merge(array_keys($this->entryFromVoid()), [
					'stayhere', 'start_date_date', 'start_date_time', 'pwd_date_date', 'pwd_date_time'
				]));
				$savedId = $this->saveEntry($data);
				return $this->eventSaveRedirect($savedId, $data, ['#', $this->promoId], ['#', '{id}.' . $this->promoId]);

			case 'save-copy':
				$this->saveCopy();
				exit;

			case 'delete':
				$data = $this->eventPostData('ids');
				$this->deleteEntry(explode(',', $data['ids']));
				$this->redirect('#', $this->promoId);
				exit;

			case 'new':
				$this->set_var('render', 'entry');
				break;

			case 'copy':
				$this->set_var('render', 'copy');
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

		$this->use_page('Common');
		moon::page()->js('/js/modules_adm/promo.js');
	}

	private function getPromo($id)
	{
		$flds = $this->isSlaveHost()
			? 'p.id, p.alias, p.title, p.lb_auto, p.timezone, p.room_id, p.remote_id'
			: 'p.id, p.alias, p.title, p.lb_auto, p.timezone, p.room_id';
		$promo = $this->db->single_query_assoc('
			SELECT ' . $flds . ', r.currency FROM promos p
			LEFT JOIN ' . $this->table('Rooms') . ' r
				ON r.id=p.room_id
			WHERE p.id=' . intval($id) . '
			  AND p.is_hidden<2
		');
		return $promo;
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
			'url.add_entry' => !$this->isSlaveHost() || empty($this->promo['remote_id'])
				? $this->link('#new', $this->promoId) : null,
			'url.copy_entry' => !$this->isSlaveHost() || empty($this->promo['remote_id'])
				? $this->linkas('#copy', $this->promoId) : null,
			'event.delete'  => !$this->isSlaveHost() || empty($this->promo['remote_id'])
				? $this->my('fullname') . '#delete' : null,
			'list.entries'  => '',
			'paging' => '',
			'submenu' => $this->getPreferredSubmenu($this->promoId, $this->promo, $this->my('fullname')),
			'synced_notice' => $this->isSlaveHost() && !empty($this->promo['remote_id']),
		);

		$pn = moon::shared('paginate');
		$pn->set_curent_all_limit($argv['page'], $this->getEntriesCount(), $this->get_var('entriesPerPage'));
		$pn->set_url($this->link('#', $this->promoId, ['page' => '{pg}']), $this->link('#', $this->promoId));
		$pnInfo = $pn->get_info();
		$mainArgv['paging'] = $pn->show_nav();

		$list = $this->getEntriesList($pnInfo['sqllimit']);
		foreach ($list as $row) {
			$rowArgv = array(
				'id' => $row['id'],
				'class' => $row['is_hidden'] ? 'item-hidden' : '',
				'url' => $this->link('#', $row['id'] . '.' . $this->promoId),
				'name' => htmlspecialchars($this->getEntryTitle($row)),
				'deletable' => empty($row['remote_id']),
				'start_date' => $row['start_date'],
				'entry_fee' => $row['entry_fee'],
				'fee' => $row['fee'],
			);
			if (!empty($row['remote_id'])) {
				$rowArgv['synced'] = true;
				$rowArgv['sync_state'] = intval($row['updated_on'])>intval($row['remote_updated_on']) ? 1 : 2;
			}
			$mainArgv['list.entries'] .= $tpl->parse('list:entries.item', $rowArgv);
		}
		return $tpl->parse('list:main', $mainArgv);
	}

	private function getEntriesCount()
	{
		$cnt = $this->db->single_query_assoc('
			SELECT count(*) cnt
			FROM ' . $this->table('Entries') . ' e
			WHERE is_hidden<2 AND promo_id=' . $this->promoId
		);
		return $cnt['cnt'];
	}

	private function getEntriesList($limit)
	{
		$fields = array(
			'id', 'title', 'is_hidden', 'UNIX_TIMESTAMP(updated_on) updated_on',
			'start_date', 'entry_fee', 'fee'
		);
		if ($this->isSlaveHost()) {
			$fields[] = 'remote_id';
			$fields[] = 'UNIX_TIMESTAMP(remote_updated_on) remote_updated_on';
		}
		return $this->db->array_query_assoc('
			SELECT ' . implode(',', $fields) . '
			FROM ' . $this->table('Entries') . ' e
			WHERE is_hidden<2 AND promo_id=' . $this->promoId .'
			ORDER BY start_date ' .
			$limit
		);
	}

	/**
	 * Copy
	 */
	protected function renderCopy($argv)
	{
		$page   = moon::page();
		$tpl    = $this->load_template();
		$locale = moon::locale();
		$text = moon::shared('text');
		$mainArgv  = array(
			'url.back' => $this->link('#', $this->promoId),
			'event.save' => $this->my('fullname') . '#save-copy',
			'entry.promo_id' => $this->promoId,
			'submenu' => $this->getPreferredSubmenu($this->promoId, $this->promo, $this->my('fullname')),
			'list.freerolls' => '',
		);

		$where = array();
		if ($this->promo['room_id'])
			$where[] = 'room_id=' . $this->promo['room_id'];
		$where[] = 'date>' . time();
		$where[] = 'hide=0';
		if (_SITE_ID_ != 'com')
			$where[] = 'master_id=0';
		foreach($this->db->array_query_assoc('
			SELECT id, name FROM ' . $this->table('Freerolls') . '
			WHERE ' . implode(' AND ', $where) . '
		') as $freeroll) {
			$mainArgv['list.freerolls'] .= $tpl->parse('copy:freerolls.item', array(
				'id' => $freeroll['id'],
				'title' => htmlspecialchars($freeroll['name']),
			));
		}
		return $tpl->parse('copy:main', $mainArgv);
	}

	protected function saveCopy()
	{
		$freerolls = is_array($_POST['freeroll'])
			? array_map('intval', $_POST['freeroll'])
			: array();
		$promoId = intval($_POST['promo_id']);
		$existingSchedule = $this->db->array_query_assoc('
			SELECT id, freeroll_id
			FROM ' . $this->table('Entries') . ' e
			WHERE promo_id=' . $promoId . ' AND freeroll_id IS NOT NULL AND ' . (_SITE_ID_ != 'com' ? 'remote_id=0' : '1') . '
		', 'freeroll_id');
		// $locale = moon::locale();

		foreach ($freerolls as $freeroll) {
			$freeroll = $this->db->single_query_assoc('
				SELECT * FROM ' . $this->table('Freerolls') . '
				WHERE id=' . $freeroll . '
			');
			if (empty($freeroll))
				continue;
			// list($tzOffset) = $locale->timezone($freeroll['timezone']);
			$ins = array(
				'title'       => $freeroll['name'],
				'promo_id'    => $promoId,
				'freeroll_id' => $freeroll['id'],
				'start_date'  => array('FROM_UNIXTIME(' . ($freeroll['date']/*+$tzOffset*/) . ')'),
				'is_hidden'   => 0,
				'entry_fee'   => $freeroll['entry_fee'],
				'fee'         => $freeroll['fee'],
				'pwd'         => $freeroll['password'],
				'pwd'         => $freeroll['password'],
				'pwd_date'    => $freeroll['password_from']
					? array('FROM_UNIXTIME(' . ($freeroll['password_from']/*+$tzOffset*/) . ')')
					: '0'
			);
			if (isset($existingSchedule[$freeroll['id']]))
				$this->db->update($ins, $this->table('Entries'), array(
					'id' => $existingSchedule[$freeroll['id']]['id']
				));
			else
				$this->db->insert($ins, $this->table('Entries'));
		}

		$this->redirect('#', $promoId);
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
			'url.back' => $this->link('#', $this->promoId),
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
			$mainArgv['submenu'] = $this->getPreferredSubmenu($this->promoId, $this->promo, $this->my('fullname'));
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
			// pass
		}

		$tplName = empty($entryData['remote_id'])
			? 'entry:main'
			: 'entry:slaveMain';
		return $tpl->parse($tplName, $mainArgv);
	}
	private function getOriginInfo($id)
	{
		return $this->db->single_query_assoc('
			SELECT * FROM ' . $this->table('EntriesMaster') . '
			WHERE id=' . intval($id)
		);
	}
	protected function partialRenderEntryFormOrigin(&$mainArgv, $entryData, $tpl)
	{
		$mainArgv['promo_uri'] = htmlspecialchars($this->promo['alias']);

		$mainArgv['form.currency'] = $this->promo['currency'];

		$locale = moon::locale();
		if (!empty($entryData['start_date'])) {
			$mainArgv['form.start_date_date'] = $locale->gmdatef($entryData['start_date'], '%Y-%M-%D');
			$mainArgv['form.start_date_time'] = $locale->gmdatef($entryData['start_date'], '%H:%I');
		} else {
			$mainArgv['form.start_date_date'] = '';
			$mainArgv['form.start_date_time'] = '';
		}
		if (!empty($entryData['start_date'])) {
			$mainArgv['form.pwd_date_date'] = $locale->gmdatef($entryData['pwd_date'], '%Y-%M-%D');
			$mainArgv['form.pwd_date_time'] = $locale->gmdatef($entryData['pwd_date'], '%H:%I');
		} else {
			$mainArgv['form.pwd_date_date'] = '';
			$mainArgv['form.pwd_date_time'] = '';
		}

		if (NULL != ($resultChunks = $this->parseResults($entryData['results'], $entryData['results_columns']))) {
			if (count($resultChunks['data']) > 0) {
				$dataError = false;
				$mainArgv += array(
					'results.show' => true,
					'result.data.rows' => '',
					'result.headers' => ''
				);
				foreach ($resultChunks['columns'] as $row) {
					$mainArgv['result.headers'] .= $tpl->parse('entry:result.header', array(
						'name' => htmlspecialchars($row)
					));
				}
				foreach($resultChunks['data'] as $row) {
					$rowData = '';
					foreach ($row as $v) {
						$rowData .= $tpl->parse('entry:result.data', array(
							'value' => htmlspecialchars($v)
						));
					}
					if (!isset($row[$resultChunks['idx.points']]) || !isset($row[$resultChunks['idx.player']]))
						$dataError = true;
					$mainArgv['result.data.rows'] .= $tpl->parse('entry:result.data.row', array(
						'result.data' => $rowData
					));
				}
				if ($dataError) {
					$errorMessages = $tpl->parse_array('messages');
					moon::page()->alert($errorMessages['e.results_broken']);
				}
			}
		}
	}

	/**
	 * Entry/save shared
	 */
	protected function entryFromVoid()
	{
		$entry = $this->entryFromMetadata($this->table('Entries'));
		$entry['promo_id'] = $this->promo['id'];
		return $entry;
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
		if (!$this->isSlaveHost() || empty($this->promo['remote_id'])) {
			foreach(array('start_date', 'pwd_date') as $key) {
				$data[$key] = '0000-00-00 00:00:00';
				if (!empty($data[$key . '_date'])) {
					$date = strtotime($data[$key . '_date'] . ' ' . $data[$key . '_time'] . ' +0000');
					if ($date) {
						$data[$key] = $date;
					}
				}
			}
			foreach (array('start_date_date', 'start_date_time', 'pwd_date_date', 'pwd_date_time') as $field) {
				unset($data[$field]);
			}
		}
		return $data;
	}

	/**
	 * Save
	 */
	protected function saveEntry($data)
	{
		return $this->saveEntryFlow($data, [
			'origin_validate' => function($data) {
				$errors = array();

				if (NULL == ($resultChunks = $this->parseResults('', $data['results_columns']))) {
					$errors[] = 'e.bad_results_columns';
				}

				return $errors;
			},
			'origin_pre_save' => function(&$saveData) {
				if (!empty($saveData['start_date']))
				$saveData['start_date'] = ['FROM_UNIXTIME(' . intval($saveData['start_date']) . ')'];
				if (!empty($saveData['pwd_date']))
				$saveData['pwd_date'] = ['FROM_UNIXTIME(' . intval($saveData['pwd_date']) . ')'];
			},
			'origin_post_save' => function($saveData, $id) {
				$this->eventUpdatedPlayerPoints($saveData['promo_id']);
			}
		]);
	}

	/**
	 * Delete
	 */
	private function deleteEntry($ids)
	{
		return $this->deleteEntryWorkflow($ids);
	}
}
