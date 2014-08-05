<?php

trait promos_base
{
	/**
	 * State detection
	 */
	protected function isSlaveHost()
	{
		return _SITE_ID_ != 'com';
	}

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
			$saveData['id'] = NULL; // autoincrement ok, later used
		}
		$saveData['is_hidden'] = empty($saveData['is_hidden']) ? 0 : 1;
		$saveData['pokernewscup'] = empty($saveData['pokernewscup']) ? 0 : 1;

		if (!$this->isSlaveHost() || empty($data['remote_id'])) {
			foreach ($this->getEntryMasterNoSave() as $field)
				unset($saveData[$field]);
			$saveData = $this->entryFromPost($saveData);
			foreach ($this->getEntryMasterRequiredFields() as $key)
				if (empty($saveData[$key]))
					$errors[] = $messages['e.empty_' . $key];
			foreach ($this->getEntryMasterUniqueFields() as $key) {
				$uriDupe = $this->db->single_query_assoc('
					SELECT COUNT(id) cid FROM ' . $this->table('Entries') . '
					WHERE `' . $this->db->escape($key) . '`="' . $this->db->escape($saveData[$key])  . '"' .
					(($saveData['id'] !== NULL)
						? ' AND id!=' . $saveData['id']
						: '') . '
				');
				if ('0' != $uriDupe['cid'])
					$errors[] = $messages['e.' . $key . '_duplicate'];
			}
			if (isset($hooks['origin_validate']))
			foreach($hooks['origin_validate']($saveData) as $key)
				$errors[] = $messages[$key];

			$storage = moon::shared('storage');
			foreach ($this->getEntryImageFields() as $k) {
				unset($saveData[$k[0]]); if (!is_object($data[$k[0]])) $data[$k[0]] = null;
				if (NULL !== ($file[$k[0]] = $data[$k[0]]) && !$file[$k[0]]->has_extension('jpg,gif,png')) {
					$page->alert($messages['e.invalid_file']);
					$file[$k[0]] = NULL;
					$isInvalid = TRUE;
				}

				if (NULL !== $saveData['id'] && ( // existing entry
					(NULL !== $file[$k[0]]) // and uploading file
					 ||
					(NULL === $file[$k[0]] && isset($data['delete_' . $k[0]]) && '' !== $data['delete_' . $k[0]])) // or checked "delete file"
				   )
				{
					if ($k[1]) {
						$storage->location($k[2]);
					} else {
						$mediaDir = $this->get_dir($k[2]);
					}
					$oldFile = $this->db->single_query_assoc('
						SELECT ' . $k[0] . ' as file FROM '.$this->table('Entries').'
						WHERE id=' . $saveData['id']
					);
					if (isset($oldFile['file'])) {
						if ($k[1]) {
							$storage->delete($oldFile['file']);
						} else {
							$deleteFile = new moon_file;
							if ($deleteFile->is_file($mediaDir. $oldFile['file'])) {
								$deleteFile->delete();
							}
						}
						$this->db->query('
							UPDATE '.$this->table('Entries').'
							SET ' . $k[0] . '=NULL
							WHERE id=' . $saveData['id']
						);
					}
				}
				if (NULL !== $file[$k[0]]) {
					if ($k[1]) {
						$savedImg = $storage->save($file[$k[0]], false);
						if ($savedImg != null)
							$saveData[$k[0]] = $savedImg[0];
					} else {
						$fileName = uniqid('') . '.' . $file[$k[0]]->file_ext();
						$mediaDir = $this->get_dir($k[2]);
						if ($file[$k[0]]->save_as($mediaDir . $fileName)) {
							$saveData[$k[0]]   = $fileName;
						} else {
							$page->alert($messages['e.file_save_error']);
							$isInvalid = TRUE;
						}
					}
				}
			}

			// foreach date field
			// foreach nullable field

			$saveData = array_intersect_key($saveData, $this->entryFromVoid());

			if (isset($hooks['origin_pre_save']))
			$hooks['origin_pre_save']($saveData);
		} else {
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
		}

		if (0 !== count($errors)) {
			foreach ($errors as $error)
				$page->alert($error);
			return NULL;
		}

		if (NULL === $saveData['id']) {
			$this->db->insert($saveData, $this->table('Entries'));
			$rId = $this->db->insert_id();
			blame($this->my('fullname'), 'Created', $this->db->insert_id());
			if (empty($rId)) {
				$page->alert($messages['e.save_error']);
				return NULL;
			}
		} else {
			$this->db->update($saveData, $this->table('Entries'), array(
				'id' => $saveData['id']
			));
			if ($this->db->error()) {
				$page->alert($messages['e.save_error']);
				return NULL;
			}
			blame($this->my('fullname'), 'Updated', $saveData['id']);
			$rId =  $saveData['id'];
		}

		if ($this->isSlaveHost() && !empty($data['remote_id'])) {
			//update master table
			$pushData = array();
			foreach ($this->getEntryTranslatables() as $value) {
				$pushData[] = $value . '_prev=' . $value;
			}
			$this->db->query('UPDATE ' . $this->table('EntriesMaster') . '
				SET ' .
				implode(', ', $pushData) . '
				WHERE id=' . intval($data['remote_id'])
			);
			if (isset($hooks['slave_post_save']))
			$hooks['slave_post_save']($saveData, $rId);
		} else {
			if (isset($hooks['origin_post_save']))
			$hooks['origin_post_save']($saveData, $rId);
		}
		return $rId;
	}

	private function deleteEntryWorkflow($ids)
	{
		$deleteIds = array();
		if (!is_array($ids)) {
			$ids = array($ids);
		}
		foreach ($ids as $id) {
			if (false !== ($id = filter_var($id, FILTER_VALIDATE_INT))) {
				$deleteIds[] = $id;
			}
		}
		if (empty($deleteIds)) {
			return ;
		}

		// $entries = $this->db->array_query_assoc('
		// 	SELECT id, image FROM ' . $this->table('Entries') . '
		// 	WHERE id IN (' . implode(',', $deleteIds) . ')
		// ');
		// foreach ($entries as $entry) {
		// 	foreach ($this->getEntryImageFields() as $field) {
		// 		$fn = $this->get_dir($field[1]) . $entry['image'];
		// 		if (is_file($fn)) {
		// 			@unlink($fn);
		// 		}
		// 	}
		// }

		$this->db->query('
			UPDATE ' . $this->table('Entries') . '
			SET is_hidden=2, updated_on=CURRENT_TIMESTAMP
			WHERE id IN (' . implode(',', $deleteIds) . ')
		');

		blame($this->my('fullname'), 'Deleted', $deleteIds);
	}

	/**
	 * Leaderboard stuff
	 */
	protected function eventUpdatedPlayerPoints($promoId)
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

		$this->db->update(array(
			'lb_data' => $results,
			'updated_on' => array('FROM_UNIXTIME(' . time() . ')')
		), 'promos', array(
			'id' => $promoId,
			'lb_auto' => 1
		));
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

	protected function getEntryTitle($entryData)
	{
		if (NULL == $entryData['id']) {
			return 'New entry';
		}
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
		$columns = array();
		foreach ($this->getTableData('Entries') as $column)
			if (in_array('rtf', $column['column_comment']))
				$columns[] = $column['column_name'];
		return $columns;
	}
	protected function getEntryImageFields()
	{
		$columns = array();
		foreach ($this->getTableData('Entries') as $column)
			if (in_array('image', $column['column_comment']))
				$columns[] = $column['column_name'];
		return $columns;
	}
	protected function getEntryTranslatables()
	{
		$columns = array();
		$entry = $this->getTableData('EntriesMaster');
		foreach ($entry as $column) {
			if (strpos($column['column_name'], '_prev') !== FALSE) {
				$columns[] = str_replace('_prev', '', $column['column_name']);
			}
		}
		return $columns;
	}
	protected function getEntryRetainFieldsSlave()
	{
		return array('id', 'is_hidden', 'updated_on');
	}
	protected function getEntryMasterNoSave()
	{
		$columns = array();
		foreach ($this->getTableData('Entries') as $column)
			if (in_array('master:no-save', $column['column_comment']))
				$columns[] = $column['column_name'];
		return $columns;
	}
	protected function getEntryMasterUniqueFields()
	{
		$columns = array();
		foreach ($this->getTableData('Entries') as $column)
			if (in_array('master:unique', $column['column_comment']))
				$columns[] = $column['column_name'];
		return $columns;
	}
	protected function getEntryMasterRequiredFields()
	{
		$columns = array();
		foreach ($this->getTableData('Entries') as $column) {
			if (in_array('master:required', $column['column_comment']))
				$columns[] = $column['column_name'];
			if (in_array('required', $column['column_comment']))
				$columns[] = $column['column_name'];
		}
		return $columns;
	}
	protected function getEntrySlaveRequiredFields()
	{
		$columns = array();
		foreach ($this->getTableData('Entries') as $column) {
			if (in_array('slave:required', $column['column_comment']))
				$columns[] = $column['column_name'];
			if (in_array('required', $column['column_comment']))
				$columns[] = $column['column_name'];
		}
		return $columns;
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