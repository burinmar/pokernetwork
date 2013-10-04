<?php

class base_inplace_syncable extends moon_com
{
	function onload()
	{
		$this->filter = array();
		$this->formFilter = &$this->form();
	}

	function properties()
	{
		return array(
			'render' => NULL,
			'id' => NULL,
			'page' => 1
		);
	}

	function events($event, $argv)
	{
		switch ($event) {
			case 'filter':
				$this->setFilter();
				break;

			case 'save':
				$keys = array_keys($this->getEntryDefault());
				foreach ($this->getEntryImageFields() as $field) {
					unset($keys[$field[0]]);
					$keys[] = 'delete_' . $field[0];
				}
				foreach ($this->getEntryAdditionalFields() as $field) {
					$keys[] = $field;
				}
				$keys[] = 'stayhere';

				$form = $this->form();
				$form->names($keys);
				$form->fill($_POST);
				$data = $form->get_values();

				$page = moon::page();

				foreach ($this->getEntryImageFields() as $field) {
					$k = $field[0];
					$file[$k] = new moon_file;
					$data[$k] = $file[$k]->is_upload($k, $fe)
						? $file[$k]
						: NULL;
				}

				$gData = $page->get_global($this->my('fullname'));
				if ('' === $gData) {
					$gData = array();
				}
				$gData['one-run'] = array();
				if ('' != $data['stayhere']) {
					$gData['one-run']['form-stay'] = 1;
					$page->set_global($this->my('fullname'), $gData);
				}

				$saved = $this->saveEntry($data);
				if (NULL === $saved) {
					foreach ($this->getEntryImageFields() as $field) {
						unset($data[$field[0]]);
					}
					$gData['one-run']['failed-form-data'] = $data;
					$page->set_global($this->my('fullname'), $gData);
					switch ($data['id']) {
					case '':
						call_user_func_array(array($this, 'redirect'), $this->urlNew());
					default:
						call_user_func_array(array($this, 'redirect'), $this->urlEdit($data['id']));
					}
				} else {
					switch ($data['stayhere']) {
					case '':
						call_user_func_array(array($this, 'redirect'), $this->urlList());
					default:
						call_user_func_array(array($this, 'redirect'), $this->urlEdit($saved));
					}
				}
				exit;

			case 'delete':
				$form = $this->form();
				$form->names('ids');
				$form->fill($_POST);
				$data = $form->get_values();
				$this->deleteEntry(explode(',', $data['ids']));
				call_user_func_array(array($this, 'redirect'), $this->urlList());
				break;

			case 'new':
				$this->set_var('render', 'entry');
				break;

			default:
				if (preg_match('~^([a-z\-]+)$~', $event, $possibleEvent)) {
					$possibleEvent = 'eventsEvent' . str_replace(' ', '', ucwords(str_replace('-', ' ', $possibleEvent[1])));
					if (method_exists($this, $possibleEvent)) {
						$this->$possibleEvent($argv);
						break;
					}
				}
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
	}

	protected function urlNew()
	{
		return array('#new', '');
	}

	protected function urlEdit($id)
	{
		return array('#', $id);
	}

	protected function urlList()
	{
		return array('#', '');
	}

	function main($argv)
	{
		$page   = moon::page();
		$gArgv = $page->get_global($this->my('fullname'));
		$window = moon::shared('admin');
		$window->active($this->my('fullname'));
		if (isset($gArgv['one-run']) && is_array($gArgv['one-run'])) {
			foreach ($gArgv['one-run'] as $key => $value) {
				$argv[$key] = $value;
			}
			unset($gArgv['one-run']);
		}
		$page->set_global($this->my('fullname'), !empty($gArgv)
			? $gArgv
			: '');

		$e = NULL;
		switch ($argv['render']) {
		case 'entry':
			$output = $this->renderEntry($argv, $e);
			break;

		default:
			$possibleRender = preg_match('~^[a-z]+$~', $argv['render'])
				? 'render' . ucfirst($argv['render'])
				: null;
			$output = ($possibleRender && method_exists($this, $possibleRender))
				? $this->$possibleRender($argv, $e)
				: $this->renderList($argv, $e);
			break;
		}

		switch ($e) {
		case NULL:
			return $output;

		default:
			$page->alert($e);
			$this->redirect('#');
		}
	}

	private function setFilter()
	{
		if (!empty($_POST)) {
			$this->formFilter->fill($_POST);
			$this->filter = $this->formFilter->get_values();
			moon::page()->set_global($this->my('fullname') . '.filter', $this->filter);
		} else {
			moon::page()->set_global($this->my('fullname') . '.filter', '');
		}
	}

	protected function renderList($argv, &$e)
	{
		$page   = moon::page();
		$tpl    = $this->load_template();
		$locale = moon::locale();

		$page->js('/js/modules_adm/ng-list.js');

		$iAmHere = array_reverse(moon::shared('admin')->breadcrumb());
		$mainArgv  = array(
			'title' => isset($iAmHere[0])
				? $iAmHere[0]['name']
				: '',
			'url.add_entry' => $this->getMasterEntriesCanBeAddedDeleted()
				? call_user_func_array(array($this, 'linkas'), $this->urlNew())
				: NULL,
			'event.delete'  => $this->getMasterEntriesCanBeAddedDeleted()
				? $this->my('fullname') . '#delete'
				: NULL,
			'list.entries'  => '',
			'filter' => $this->partialRenderFilter(),
			'paging' => '',
			'synced_notice' => $this->getEntriesCanBeSynced(),
			'show_sync' => !$this->isSlaveHost()
		);
		$this->partialRenderList($mainArgv, $tpl, $argv);

		$pn = moon::shared('paginate');
		$pn->set_curent_all_limit($argv['page'], $this->getEntriesCount(), $this->get_var('entriesPerPage'));
		$pn->set_url(call_user_func_array(array($this, 'linkas'),
			$this->urlList() + array(2 => array('page' => '{pg}'))
		));
		$pnInfo = $pn->get_info();
		$mainArgv['paging'] = $pn->show_nav();

		$list = $this->getEntriesList($pnInfo['sqllimit']);
		$this->eventEntriesGotItems($list);

		foreach ($list as $row) {
			$rowArgv = array(
				'id' => $row['id'],
				'class' => $row['is_hidden'] ? 'item-hidden' : '',
				'url' => call_user_func_array(array($this, 'linkas'), $this->urlEdit($row['id'])),
				'name' => htmlspecialchars($this->getEntryTitle($row)),
				'deletable' => $this->getMasterEntriesCanBeAddedDeleted() && empty($row['remote_id'])
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

	protected function getMasterEntriesCanBeAddedDeleted()
	{
		return true;
	}

	protected function getEntriesCanBeSynced()
	{
		return $this->isSlaveHost();
	}

	protected function eventEntriesGotItems($list)
	{}

	protected function partialRenderList(&$argv, $tpl, $argv)
	{}

	protected function partialRenderListRow($row, &$argv, $tpl)
	{}

	protected function getEntriesWhere()
	{
		$where = array(
			'is_hidden<2'
		);
		foreach ($this->filter as $filterName => $filterValue) {
			if (!empty($filterValue)) {
				$where[] = '`' . $this->db->escape($filterName) . '`' .
				    ' LIKE "%' . $this->db->escape($filterValue). '%"';
			}
		}
		foreach ($this->getEntriesListAdditionalWhere() as $value) {
			$where[] = $value;
		}
		return !empty($where)
			? ' WHERE ' . implode(' AND ', $where)
			: '';
	}

	protected function getEntriesListAdditionalWhere()
	{
		return array();
	}

	protected function getEntriesListOrderBy()
	{
		return array('updated_on DESC');
	}

	protected function getEntriesCount()
	{
		$cnt = $this->db->single_query_assoc('
			SELECT count(*) cnt
			FROM ' . $this->table('Entries') . ' e ' .
			$this->getEntriesWhere()
		);
		return $cnt['cnt'];
	}

	protected function getEntriesList($limit)
	{
		$fields = array(
			'id', 'title', 'is_hidden', 'UNIX_TIMESTAMP(updated_on) updated_on'
		);
		if ($this->isSlaveHost()) {
			$fields[] = 'remote_id';
			$fields[] = 'UNIX_TIMESTAMP(remote_updated_on) remote_updated_on';
		}
		foreach ($this->getEntriesListAdditionalFields() as $field) {
			$fields[] = $field;
		}
		$orderBy = array();
		foreach ($this->getEntriesListOrderBy($orderBy) as $field) {
			$orderBy[] = $field;
		}
		return $this->db->array_query_assoc('
			SELECT ' . implode(',', $fields) . '
			FROM ' . $this->table('Entries') . ' e ' .
			$this->getEntriesWhere() .
			(0 != count($orderBy)
				? ' ORDER BY ' . implode(',', $orderBy)
				: '') .
			$limit
		);
	}

	protected function getEntriesListAdditionalFields()
	{
		return array();
	}

	private function partialRenderFilter()
	{
		if (empty($this->formFilter->names)) {
			return;
		}

		$page = &moon::page();
		$tpl = $this->load_template();
		$savedFilter = $page->get_global($this->my('fullname') . '.filter');

		if (!empty($savedFilter)) {
			$this->filter = $savedFilter;
		} else {
			$this->formFilter->fill(array());
			$this->filter = $this->formFilter->get_values();
		}

		$filter['is_active'] = '';

		foreach ($this->filter as $filterName => $filterValue) {
			$filter['filter.' . $filterName] = $this->partialRenderFilterElement($filterName, $filterValue, $tpl);
		}

		foreach ($this->filter as $filterValue) {
			if (!empty($filterValue)) {
				$filter['is_active'] = 1;
				break;
			}
		}

		$filter += array(
			'event.filter' => $this->my('fullname') . '#filter',
			'url.reset-filter' => $this->linkas('#filter')
		);
		return $tpl->parse('list:filter', $filter);
	}

	protected function partialRenderFilterElement($filterName, $filterValue, $tpl)
	{}
/*	protected function partialRenderFilterElement($filterName, $filterValue, $tpl)
	{
		switch ($filterName) {
		case 'network':
			$networks = $this->getNetworks();
			$result = '';
			foreach ($networks as $network) {
				$result .= $tpl->parse('list:networks.item', array(
					'value' => $network['id'],
					'name' => htmlspecialchars($network['name']),
					'selected' => $network['id'] == $filterValue
				));
			}
			return $result;
		}
	}
*/

	private function renderEntry($argv, &$e)
	{
		$page   = moon::page();
		$tpl    = $this->load_template();
		$locale = moon::locale();
		$text = moon::shared('text');
		$mainArgv  = array(
			'url.back' => call_user_func_array(array($this, 'linkas'), $this->urlList()),
			'event.save' => $this->my('fullname') . '#save'
		);

		if (NULL === $argv['id']) {
			$entryData = $this->getEntryDefault();
		} else {
			if (NULL === ($entryData = $this->getEntry($argv['id']))) {
				$messages = $tpl->parse_array('messages');
				$e  = $messages['e.entry_not_found'];
				return ;
			}

		}
		$this->partialRenderEntry($argv, $mainArgv, $entryData, $tpl);
		if (!isset($mainArgv['title'])) {
			$mainArgv['title'] = htmlspecialchars($this->getEntryTitle($entryData));
		}

		if (isset($argv['failed-form-data'])) {
			if (!($this->isSlaveHost() && !empty($entryData['remote_id']))) {
				$entryData = array_merge($entryData, $argv['failed-form-data']);
				$this->eventSaveSerializeOrigin($entryData);
			} else {
				$entryData = array_merge($entryData, $argv['failed-form-data']);
				$this->eventSaveSerializeSlave($entryData);
			}
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
		foreach ($this->getEntryImageFields() as $k) {
			$mainArgv['form.' . $k[0]] = !empty($entryData[$k[0]])
				? (!empty($entryData['remote_id'])
						? $this->get_var('imagesDmn')
						: '')
					. $this->get_var($k[2]) . $entryData[$k[0]]
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
		$mainArgv['form.hide'] = empty($entryData['is_hidden']) ? '1' : '1" checked="checked';
		//
		if ($this->isSlaveHost() && !empty($entryData['remote_id'])) {
			$mainArgv['syncStatus'] = intval($entryData['updated_on'])>intval($entryData['remote_updated_on']) ? 1 : 2;
			$mainArgv['remote_id'] = $entryData['remote_id'];
			$remote = $this->getOriginInfo($entryData['remote_id']);
			foreach ($this->getEntryTranslatables() as $k) {
				$mainArgv['form.origin_' . $k] = !empty($remote[$k . '_prev']) || $entryData['updated_on'] != '0'
					? nl2br($text->htmlDiff(
						htmlspecialchars($remote[$k . '_prev']),
						htmlspecialchars($remote[$k])))
					: nl2br(htmlspecialchars(@$remote[$k]));
			}
		}
		// if ($entryData['remote_id']) {
		// 	$mainArgv['form.uri'] = moon::shared('sitemap')->getLink('free-games') . rawurlencode($entryData['uri']) . '.htm';
		// }

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

	protected function getEntryTitle($entryData)
	{
		if (NULL == $entryData['id']) {
			return 'New entry';
		}
		return $entryData['title'];
	}

	protected function partialRenderEntry($argv, &$mainArgv, $entryData, $tpl)
	{}

	protected function partialRenderEntryFormOrigin(&$mainArgv, $entryData, $tpl)
	{}
	protected function partialRenderEntryFormSlave(&$mainArgv, $entryData, $tpl)
	{}
/*	protected function partialRenderEntryFormOrigin(&$mainArgv, $entryData)
	{
		$mainArgv['form.category'] = '';
		foreach ($this->getCategories() as $category) {
			$mainArgv['form.category'] .= $tpl->parse('entry:category.item', array(
				'name' => htmlspecialchars($category['name']),
				'value' => $category['id'],
				'selected' => $category['id'] == $entryData['category']
			));
		}
		$mainArgv['form.image'] = !empty($entryData['image'])
			? ($entryData['remote_id'] ? $this->get_var('imagesDmn') : '') . $this->get_var('imagesSrc') . $entryData['image']
			: NULL;
	}
*/

	protected function getEntry($id)
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

	private function getOriginInfo($id)
	{
		return $this->db->single_query_assoc('
			SELECT * FROM ' . $this->table('EntriesMaster') . '
			WHERE id=' . intval($id)
		);
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

	protected function getEntryDefault()
	{
		$entry = $this->getTableData('Entries');
		foreach ($entry as $k => $column) {
			switch($column['column_default']) {
			case 'CURRENT_TIMESTAMP':
				$entry[$k] = time();
				break;
			case '0000-00-00 00:00:00':
			// @todo case any date
				$entry[$k] = '';
				break;
			case NULL:
				$entry[$k] = '';
				break;
			default:
				$entry[$k] = $column['column_default'];
			}
		}
		return $entry;
	}

	private function getEntryCharLength()
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

	protected function getEntryRetainFieldsSlave()
	{
		return array('id', 'is_hidden', 'updated_on');
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

	protected function getEntryTimestampFields()
	{
		$columns = array();
		$entry = $this->getTableData('Entries');
		foreach ($entry as $column)
			if ($column['data_type'] == 'timestamp')
				$columns[] = $column['column_name'];
		return $columns;
	}

	protected function getEntryAdditionalFields()
	{
		return array();
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

	protected function saveEntry($data)
	{
		$page     = moon::page();
		$tpl      = $this->load_template();
		$messages = $tpl->parse_array('messages');

		$saveData = $data;
		foreach ($this->getEntryImageFields() as $field) {
			unset($saveData[$field[0]]);
			unset($saveData['delete_' . $field[0]]);
		}
		$saveData['updated_on'] = array('FROM_UNIXTIME', time());

		$errors = array();
		if (isset($saveData['id']) && '' !== $saveData['id']) {
			$saveData['id'] = filter_var($saveData['id'], FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
			if (NULL === $saveData['id'])
				$errors[] = $messages['e.invalid_id'];
		} else {
			$saveData['id'] = NULL; // autoincrement ok, later used
		}
		$saveData['is_hidden'] = empty($saveData['is_hidden']) ? 0 : 1;

		if (!($this->isSlaveHost() && !empty($data['remote_id']))) {
			foreach ($this->getEntryMasterNoSave() as $field)
				unset($saveData[$field]);
			$this->eventSaveSerializeOrigin($saveData);
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
			foreach($this->eventSaveCustomValidateOrigin($saveData) as $key)
				$errors[] = $messages[$key];

			foreach ($this->getEntryImageFields() as $k) {
				if (NULL !== ($file[$k[0]] = $data[$k[0]]) && !$file[$k[0]]->has_extension('jpg,gif,png')) {
					$errors[] = $messages['e.invalid_file'];
					$file[$k[0]] = NULL;
				}

				if (NULL !== $saveData['id'] && (
					(NULL !== $file[$k[0]])
					 ||
					(NULL === $file[$k[0]] && isset($data['delete_' . $k[0]]) && '' !== $data['delete_' . $k[0]]))
				   )
				{
					$mediaDir = $this->get_dir($k[1]);
					$oldFile = $this->db->single_query_assoc('
						SELECT `' . $k[0] . '` as file FROM '.$this->table('Entries').'
						WHERE id=' . $saveData['id']
					);
					if (isset($oldFile['file'])) {
						$deleteFile = new moon_file;
						if ($deleteFile->is_file($mediaDir. $oldFile['file'])) {
							$deleteFile->delete();
						}
						$this->db->query('
							UPDATE '.$this->table('Entries').'
							SET `' . $k[0] . '`=NULL
							WHERE id=' . $saveData['id']
						);
					}
				}
				if (NULL !== $file[$k[0]]) {
					$mediaDir = $this->get_dir($k[1]);
					$fileName = uniqid('') . '.' . $file[$k[0]]->file_ext();
					if ($file[$k[0]]->save_as($mediaDir . $fileName)) {
						$saveData[$k[0]] = $fileName;
					} else {
						$errors[] = $messages['e.file_save_error'];
					}
				}
			}

			// foreach date field
			// foreach nullable field

			$saveData = array_intersect_key($saveData, $this->getEntryDefault());

			$this->eventSavePreSaveMaster($saveData);
		} else {
			if (NULL === $saveData['id'])
				$page->page404();

			$retainFields = $this->getEntryRetainFieldsSlave();
			foreach ($this->getEntryTranslatables() as $fld)
				$retainFields[] = $fld;
			foreach ($saveData as $k => $v)
				if (!in_array($k, $retainFields))
					unset($saveData[$k]);

			$this->eventSaveSerializeSlave($saveData);
			foreach ($this->getEntrySlaveRequiredFields() as $key)
				if (empty($saveData[$key]))
					$errors[] = $messages['e.empty_' . $key];
			foreach($this->eventSaveCustomValidateSlave($saveData) as $key)
				$errors[] = $messages[$key];
		}

		if (0 !== count($errors)) {
			foreach ($errors as $error)
				$page->alert($error);
			return NULL;
		}

		if (NULL === $saveData['id']) {
			$this->dbInsert($saveData, $this->table('Entries'));
			$rId = $this->db->insert_id();
			blame($this->my('fullname'), 'Created', $this->db->insert_id());
			if (empty($rId)) {
				$page->alert($messages['e.save_error']);
				return NULL;
			}
		} else {
			$this->dbUpdate($saveData, $this->table('Entries'), array(
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
		} else {
			$this->eventSavePostSaveOrigin($saveData);
		}
		return $rId;
	}

	protected function eventSaveCustomValidateOrigin($saveData)
	{
		return array();
	}

	protected function eventSaveCustomValidateSlave($saveData)
	{
		return array();
	}

	protected function eventSaveSerializeOrigin(&$saveData)
	{}
	//$saveData['date'] = strtotime($data['date_date'] . ' ' . $data['date_time']);
	//$saveData['date'] = array('FROM_UNIXTIME', $saveData['date']);

	protected function eventSaveSerializeSlave(&$saveData)
	{}

	protected function eventSavePreSaveMaster(&$saveData)
	{}

	protected function eventSavePostSaveOrigin($saveData)
	{}

	private function deleteEntry($ids)
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

	private function dbInsert($row, $table)
	{
		foreach ($row as $k => $v) {
			$row[$k] = is_null($v) ? 'NULL' : (
				is_array($v)
					? ($v[0] . '(\'' . $this->db->escape($v[1]) . '\')')
					: ("'" . $this->db->escape($v) . "'")
			);
		}
		$sql = "INSERT INTO `". $table . "` (`" . implode("`, `", array_keys($row)) . "`) VALUES (" . implode(',', array_values($row)) . ')';
		$r = &$this->db->query($sql);
		return $r
			? $this->db->insert_id()
			: FALSE;
	}

	protected function dbUpdate($row, $table, $id = false)
	{
		$where = '';
		if (is_array($id)) {
			foreach ($id as $k => $v) {
				$where .= $where === '' ? ' WHERE ' : ' AND ';
				$where .= "(`$k`='" . $this->db->escape($v) . "')";
			}
		}
		$set = array();
		foreach ($row as $k => $v) {
			$set[] = '`' . $k . '`=' . (is_null($v) ? 'NULL' : (
				is_array($v)
					? ($v[0] . (isset($v[1]) ? ('(\'' . $this->db->escape($v[1]) . '\')') : ''))
					: ("'" . $this->db->escape($v) . "'")
			));
		}
		$sql = "UPDATE `" . $table . "` SET " . implode(',', $set) . $where;
		$r = $this->db->query($sql);
		return $r
			? $this->db->insert_id()
			: FALSE;
	}

	protected function dbEnumList($table, $column)
	{
		$database = moon::moon_ini()->get('database', 'database');
		$categories = $this->db->single_query_assoc('
			SELECT column_type FROM information_schema.columns
			WHERE column_name="' . $this->db->escape($column) . '" and table_name="' . $this->db->escape($table) . '" AND table_schema="' . $this->db->escape($database) . '"
		');
		preg_match_all("~'([^']+)'~", $categories['column_type'], $ms);
		return $ms[1];
	}

	private $moon_locale;
	protected function locale()
	{
		if (!$this->moon_locale) {
			$this->moon_locale = moon::locale();
		}
		return $this->moon_locale;
	}

	protected function isSlaveHost()
	{
		return _SITE_ID_ != 'com';
	}
}
