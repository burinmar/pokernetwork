<?php

trait promos_base
{
	/**
	 * Workflows
	 */
	protected function saveEntryFlow($data, $hooks)
	{
		$page     = moon::page();
		$tpl      = $this->load_template();
		$messages = $tpl->parse_array('messages');

		$saveData = $data;
		$saveData['updated_on'] = array('CURRENT_TIMESTAMP');

		$errors = array();
		if (isset($saveData['id']) && '' !== $saveData['id']) {
			$saveData['id'] = filter_var($saveData['id'], FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
			if (NULL === $saveData['id'])
				$errors[] = $messages['e.invalid_id'];
		} else {
			return ;
		}
		$saveData['is_hidden'] = empty($saveData['is_hidden']) ? 0 : 1;

		if (NULL === $saveData['id'])
			$page->page404();

		$retainFields = $this->getEntryRetainFieldsSlave();
		foreach ($this->getEntryTranslatables() as $fld)
			$retainFields[] = $fld;
		$saveData = $this->entryFromPost($saveData);
		foreach ($saveData as $k => $v)
			if (!in_array($k, $retainFields))
				unset($saveData[$k]);
		foreach ($this->getEntrySlaveRequiredFields() as $key)
			if (empty($saveData[$key]))
				$errors[] = $messages['e.empty_' . $key];
		if (isset($hooks['slave_validate']))
		foreach($hooks['slave_validate']($saveData) as $key)
			$errors[] = $messages[$key];

		if (0 !== count($errors)) {
			foreach ($errors as $error)
				$page->alert($error);
			return NULL;
		}

		$this->db->update($saveData, $this->table('Entries'), array(
			'id' => $saveData['id']
		));
		if ($this->db->error()) {
			$page->alert($messages['e.save_error']);
			return NULL;
		}
		blame($this->my('fullname'), 'Updated', $saveData['id']);
		$rId = $saveData['id'];

		//update master table
		$pushData = array();
		foreach ($this->getEntryTranslatables() as $value) {
			$pushData[] = $value . '_prev=' . $value;
		}
		$this->db->query('UPDATE ' . $this->table('EntriesMaster') . '
			SET ' .
			implode(', ', $pushData) . '
			WHERE id=' . intval($data['id'])
		);
		if (isset($hooks['slave_post_save']))
		$hooks['slave_post_save']($saveData, $rId);

		$store = $saveData; unset($store['id'], $store['version']);
		// store_edit($this->my('fullname'), $rId, array_map(function($val) {
		// 	if (!is_scalar($val))
		// 		return json_encode($val);
		// 	return $val;
		// }, $store));

		return $rId;
	}

	protected function parseResults($results, $columns)
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
			$data[$k] = preg_split('~[\t;]~', $row);
		}

		return array(
			'columns' => $columns,
			'idx.player' => $playerCol,
			'idx.points' => $pointsCol,
			'data' => $data
		);
	}

	/**
	 * Rendering helpers
	 */
	public function getPreferredSubmenu($promoId, $entryData, $active)
	{
		$skip = array();
		if (!$this->hasCustomPages($promoId)) {
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

	protected function getEntryTitle($entryData)
	{
		return $entryData['title'];
	}

	/**
	 * DB tools
	 */
	protected function getEntryCharLength()
	{
		$columns = array();
		$entry = $this->getTableData('Entries');
		foreach ($entry as $column) {
			if ($column['data_type'] == 'varchar') {
				$columns[$column['column_name']] = $column['character_maximum_length'];
			}
		}
		return $columns;
	}
	protected function getEntryTimestampFields()
	{
		$columns = array();
		$entry = $this->getTableData('Entries');
		foreach ($entry as $column)
			if ($column['data_type'] == 'timestamp')
				$columns[] = $column['column_name'];
		return $columns;
	}
	protected function getEntryRtfFields()
	{
		return [];
	}
	protected function getEntryTranslatables()
	{
		return [];
	}
	protected function getEntryRetainFieldsSlave()
	{
		return array('id', 'is_hidden', 'updated_on');
	}
	protected function getEntrySlaveRequiredFields()
	{
		return [];
	}
	private $tableData = array();
	private function getTableData($table)
	{
		if (!isset($this->tableData[$table])) {
			$database = moon::moon_ini()->get('database', 'database');
			$this->tableData[$table] = $this->db->array_query_assoc('
				SELECT column_name, column_default, data_type, character_maximum_length, column_comment
				FROM information_schema.columns
				WHERE table_name="' . $this->table($table) . '"	AND table_schema="' . $this->db->escape($database) . '"
			', 'column_name');
			foreach ($this->tableData[$table] as $col => $descr)
				$this->tableData[$table][$col]['column_comment'] = $descr['column_comment']
					? array_map('trim', explode(',', $descr['column_comment']))
					: array();
		}
		return $this->tableData[$table];
	}

	/**
	 * Etc
	 */
	private $moon_locale;
	protected function locale()
	{
		if (!$this->moon_locale) {
			$this->moon_locale = moon::locale();
		}
		return $this->moon_locale;
	}
}