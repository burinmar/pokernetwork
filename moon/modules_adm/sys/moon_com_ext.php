<?php

abstract class moon_com_ext extends moon_com
{
	function properties()
	{
		return array(
			'render' => NULL,
			'id' => NULL,
			'page' => 1
		);
	}

	function main($args)
	{
		$window = moon::shared('admin');
		$page = moon::page();
		$page->title(
			$window->current_info('title')
		);

		$e = NULL;

		if (null === $args['render']) {
			$output = $this->renderList($args, $e);
		} else {
			$methodName = 'render' . str_replace(' ', '', ucwords(str_replace(array('_', '-'), ' ', $args['render'])));
			if (method_exists($this, $methodName))
				$output = $this->$methodName($args, $e);
			else
				$page->page404();
		}

		switch ($e) {
			case NULL:
				return $output;

			default:
				$page->alert($e);
				$this->redirect('#');
		}
	}

	final protected function eventPostData($names, $uploadNames = array())
	{
		$page = moon::page();
		$form = $this->form();

		$form->names($names);
		$form->fill($_POST);
		$data = $form->get_values();

		foreach ($uploadNames as $k) {
			$file[$k] = new moon_file;
			$data[$k] = $file[$k]->is_upload($k, $fe)
				? $file[$k]
				: NULL;
		}

		return $data;
	}

	final protected function popFailedFormData()
	{
		$page = moon::page();
		$data = $page->get_local($this->my('fullname').'.failed-form-data');
		if ('' == $data) {
			$data = $page->get_global($this->my('fullname').'.failed-form-data');
			$page->set_global($this->my('fullname').'.failed-form-data', '');
		}
		return '' == $data
			? array()
			: $data;
	}

	final protected function eventSaveRedirect($savedId, $data, $listRedirectArgs = array('#'), $itemRedirectArgs = array('#', '{id}'))
	{
		if (NULL === $savedId) {
			$page = moon::page();
			$page->set_local($this->my('fullname').'.failed-form-data', $this->entryFromPost($data));
			return $page->call_event(
				$page->requested_event('get'),
				$page->requested_event('param')
			);
		} else if (!empty($data['stayhere'])) {
			call_user_func_array(array($this, 'redirect'), array_map(function($chunk) use ($savedId) {
				return str_replace('{id}', $savedId, $chunk);
			}, $itemRedirectArgs));
			moon_close(); exit;
		} else {
			call_user_func_array(array($this, 'redirect'), $listRedirectArgs);
			moon_close(); exit;
		}
	}

	protected function entryFromPost($data)
	{
		return $data;
	}

	final protected function entryFromMetadata($table)
	{
		$entry = $this->getTableData($table);
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

	private $tableData = array();
	private function getTableData($table)
	{
		if (!isset($this->tableData[$table])) {
			$database = moon::moon_ini()->get('database', 'database');
			$this->tableData[$table] = $this->db->array_query_assoc('
				SELECT column_name, column_default, data_type, character_maximum_length, column_comment
				FROM information_schema.columns
				WHERE table_name="' . $this->db->escape($table) . '" AND table_schema="' . $this->db->escape($database) . '"
			', 'column_name');
			foreach ($this->tableData[$table] as $col => $descr)
				$this->tableData[$table][$col]['column_comment'] = $descr['column_comment']
					? array_map('trim', explode(',', $descr['column_comment']))
					: array();
		}
		return $this->tableData[$table];
	}
}