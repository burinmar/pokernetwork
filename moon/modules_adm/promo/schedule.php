<?php
/**
 * Everything below is just terrible
 */
require_once 'base_inplace_syncable.php';
class schedule extends base_inplace_syncable
{
	private $promoId;
	private $promo;
	function events($event, $argv)
	{
		if ('' === $argv) {
			moon::page()->page404();
		}
		$promoId = array_pop($argv);
		if (empty($promoId) || !($this->promo = $this->getPromo($promoId))) {
			moon::page()->page404();
		}
		$this->promoId = intval($promoId);
		parent::events($event, $argv);
	}

	protected function eventsEventCopy($args)
	{
		$this->set_var('render', 'copy');
	}

	protected function eventsEventSaveCopy($args)
	{
		$this->saveCopy();
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

	protected function urlNew()
	{
		return array('#new', $this->promoId);
	}

	protected function urlEdit($id)
	{
		return array('#', $id . '.' . $this->promoId);
	}

	protected function urlList()
	{
		return array('#', $this->promoId);
	}

	function main($argv)
	{
		$argv['submenu'] = $this->object('promos')->getPreferredSubmenu($this->promoId, $this->promo, $this->my('fullname'));

		return parent::main($argv);
	}

	protected function getEntriesListAdditionalFields()
	{
		return array('start_date', 'entry_fee', 'fee');
	}

	protected function getEntriesListAdditionalWhere()
	{
		return array('promo_id=' . $this->promoId);
	}

	protected function getEntriesListOrderBy()
	{
		return array('start_date');
	}

	protected function partialRenderList(&$mainArgv, $tpl, $argv)
	{
		$mainArgv['title'] = htmlspecialchars($this->promo['title']) . ': ' . $mainArgv['title'];
		$mainArgv['submenu'] = $argv['submenu'];
		$mainArgv['url.copy_entry'] = $this->getMasterEntriesCanBeAddedDeleted()
			? $this->linkas('#copy', $this->promoId)
			: NULL;
	}

	protected function partialRenderListRow($row, &$rowArgv, $tpl)
	{
		$rowArgv['start_date'] = $row['start_date'];
		$rowArgv['entry_fee'] = $row['entry_fee'];
		$rowArgv['fee'] = $row['fee'];
	}

	protected function getMasterEntriesCanBeAddedDeleted()
	{
		return !$this->getEntriesCanBeSynced();
	}

	protected function getEntriesCanBeSynced()
	{
		return parent::getEntriesCanBeSynced() && !empty($this->promo['remote_id']);
	}

	protected function renderCopy($argv)
	{
		$page   = moon::page();
		$tpl    = $this->load_template();
		$locale = moon::locale();
		$text = moon::shared('text');
		$mainArgv  = array(
			'url.back' => call_user_func_array(array($this, 'linkas'), $this->urlList()),
			'event.save' => $this->my('fullname') . '#save-copy',
			'entry.promo_id' => $this->promoId,
			'submenu' => $argv['submenu'],
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

	protected function getEntryDefault()
	{
		$entry = parent::getEntryDefault();
		$entry['promo_id'] = $this->promo['id'];
		return $entry;
	}

	protected function partialRenderEntry($argv, &$mainArgv, $entryData)
	{
		$mainArgv['submenu'] = $argv['submenu'];
	}

	protected function partialRenderEntryFormOrigin(&$mainArgv, $entryData, $tpl)
	{
		$mainArgv['promo_uri'] = htmlspecialchars($this->promo['alias']);

		$mainArgv['form.currency'] = $this->promo['currency'];

		$locale = moon::locale();
		if (!empty($entryData['start_date'])) {
			$mainArgv['form.start_date_date'] = $locale->gmdatef($entryData['start_date'], '%Y-%M-%d');
			$mainArgv['form.start_date_time'] = $locale->gmdatef($entryData['start_date'], '%H:%I');
		} else {
			$mainArgv['form.start_date_date'] = '';
			$mainArgv['form.start_date_time'] = '';
		}
		if (!empty($entryData['start_date'])) {
			$mainArgv['form.pwd_date_date'] = $locale->gmdatef($entryData['pwd_date'], '%Y-%M-%d');
			$mainArgv['form.pwd_date_time'] = $locale->gmdatef($entryData['pwd_date'], '%H:%I');
		} else {
			$mainArgv['form.pwd_date_date'] = '';
			$mainArgv['form.pwd_date_time'] = '';
		}

		if (NULL != ($resultChunks = $this->object('promos')->parseResults($entryData['results'], $entryData['results_columns']))) {
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

	protected function getEntryAdditionalFields()
	{
		return array('start_date_date', 'start_date_time', 'pwd_date_date', 'pwd_date_time');
	}

	protected function eventSaveSerializeOrigin(&$saveData)
	{
		foreach(array('start_date', 'pwd_date') as $key) {
			$saveData[$key] = '0000-00-00 00:00:00';
			if (!empty($saveData[$key . '_date'])) {
				$date = strtotime($saveData[$key . '_date'] . ' ' . $saveData[$key . '_time'] . ' +0000');
				if ($date) {
					$saveData[$key] = array('FROM_UNIXTIME', $date);
				}
			}
		}
		foreach ($this->getEntryAdditionalFields() as $field) {
			unset($saveData[$field]);
		}
	}

	protected function eventSaveCustomValidateOrigin($data)
	{
		$errors = array();

		if (NULL == ($resultChunks = $this->object('promos')->parseResults('', $data['results_columns']))) {
			$errors[] = 'e.bad_results_columns';
		}

		return $errors;
	}

	protected function eventSavePostSaveOrigin($saveData)
	{
		$this->object('promos')->eventUpdatedPlayerPoints($saveData['promo_id']);
	}
}
