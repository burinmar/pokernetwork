<?php

require_once 'base_inplace_syncable.php';
class schedule extends base_inplace_syncable
{
	private $promoId;
	private $promo;
	function events($event, $argv)
	{
		switch ($event) {
		case 'save':
			include_class('moon_file');
			$file = new moon_file;
			if ($file->is_upload('results_file', $e5)) {
				$_POST['results'] = $this->xlsToCsv($file->file_path());
			}
		}
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

	private function getPromo($id)
	{
		$flds = $this->isSlaveHost()
			? 'id, alias, title, lb_auto, currency, remote_id'
			: 'id, alias, title, lb_auto, currency';
		$promo = $this->db->single_query_assoc('
			SELECT ' . $flds . ' FROM promos
			WHERE id=' . intval($id) . '
			  AND is_hidden<2
		');
		return $promo;
	}

	protected function getUrlNew() 
	{
		return array('#new', $this->promoId);
	}

	protected function getUrlEdit($id)
	{
		return array('#', $id . '.' . $this->promoId);
	}

	protected function getUrlList()
	{
		return array('#', $this->promoId);
	}

	function main($argv)
	{
		$argv['submenu'] = $this->object('promos')->getPreferredSubmenu($this->promoId, $this->promo, $this->my('fullname'));

		return parent::main($argv);
	}

	protected function partialRenderList(&$mainArgv, $tpl, $argv)
	{
		$mainArgv['title'] = htmlspecialchars($this->promo['title']) . ': ' . $mainArgv['title'];
		$mainArgv['submenu'] = $argv['submenu'];
	}

	protected function getEntriesAdditionalFields()
	{
		return array('start_date', 'entry_fee', 'fee');
	}

	protected function partialRenderListRow($row, &$rowArgv, $tpl)
	{
		$rowArgv['start_date'] = $row['start_date'];
		$rowArgv['entry_fee'] = $row['entry_fee'];
		$rowArgv['fee'] = $row['fee'];
	}

	protected function getEntriesAdditionalWhere()
	{
		return array(
			'promo_id=' . $this->promoId
		);
	}

	protected function getEntriesAdditionalOrderBy()
	{
		return array('start_date');
	}

	protected function getEntriesCanBeAddedDeleted()
	{
		return !$this->getEntriesCanBeSynced();
	}

	protected function getEntriesCanBeSynced()
	{
		return $this->isSlaveHost() && !empty($this->promo['remote_id']);
	}

	protected function getEntryTextFields()
	{
		return array(
			'description'
		);
	}

	protected function getEntryDefault()
	{
		$entry = parent::getEntryDefault();
		$entry['promo_id'] = $this->promo['id'];
		$entry['room_id'] = $this->db->single_query_assoc('
			SELECT room_id FROM promos WHERE id=' . $this->promo['id'] . '
		');
		$entry['room_id'] = $entry['room_id']['room_id'];
		return $entry;
	}

	protected function partialRenderEntry($argv, &$mainArgv, $entryData) 
	{
		$mainArgv['submenu'] = $argv['submenu'];
	}

	protected function partialRenderEntryFormOrigin(&$mainArgv, $entryData, $tpl)
	{
		$mainArgv['promo_uri'] = htmlspecialchars($this->promo['alias']);

		$mainArgv['form.rooms'] = '';
		foreach ($this->getRoomsList() as $entry) {
			$mainArgv['form.rooms'] .= $tpl->parse('entry:rooms.item', array(
				'value' => $entry['id'],
				'name' => htmlspecialchars($entry['name']),
				'selected' => $entry['id'] == $entryData['room_id']
			));
		}

		$mainArgv['form.currency'] = $this->promo['currency'];

		$locale = moon::locale();
		if (!empty($entryData['start_date'])) {
			$mainArgv['form.start_date_date'] = $locale->gmdatef($entryData['start_date'], '%Y-%m-%d');
			$mainArgv['form.start_date_time'] = $locale->gmdatef($entryData['start_date'], '%H:%I');
		} else {
			$mainArgv['form.start_date_date'] = '';
			$mainArgv['form.start_date_time'] = '';
		}
		if (!empty($entryData['start_date'])) {
			$mainArgv['form.pwd_date_date'] = $locale->gmdatef($entryData['pwd_date'], '%Y-%m-%d');
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

	protected function getSaveRequiredNoEmptyFields()
	{
		return array('title', 'start_date');
	}

	protected function getSaveCustomValidationErrors($data)
	{
		$errors = array();

		if (NULL == ($resultChunks = $this->object('promos')->parseResults('', $data['results_columns']))) {
			$errors[] = 'e.bad_results_columns';
		}

		return $errors;
	}

	protected function eventSavePreSaveOrigin(&$saveData)
	{
		if ('' === $saveData['room_id']) {
			$saveData['room_id'] = null;
		}
	}

	protected function eventSavePostSaveOrigin($saveData)
	{
		$this->object('promos')->eventUpdatedPlayerPoints($saveData['promo_id']);
	}

	private function xlsToCsv($p) {
		return $this->object('promos')->xlsToCsv($p);
	}
}
