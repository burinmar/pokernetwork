<?php

class leaderboard extends moon_com
{
	function events($event, $argv)
	{
		switch ($event) {
		case 'save':
			$page = &moon::page();
			$form = &$this->form();
			$form->names('id', 'lb_data');
			$form->fill($_POST);
			$data = $form->get_values();

			include_class('moon_file');
			$file = new moon_file;
			if ($file->is_upload('lb_data_file', $e5)) {
				$data['lb_data'] = $this->xlsToCsv($file->file_path());
			}

			$this->saveEntry($data);
			$this->redirect('#', $data['id']);
			exit;
		}
		if (isset($argv[0]) && NULL !== ($id = getInteger($argv[0]))) {
			$this->set_var('id', $id);
		} else {
			moon::page()->page404();
		}
		$this->use_page('Common');
	}

	function main($argv)
	{
		$page     = &moon::page();
		$tpl      = $this->load_template();
		$mainArgv  = array(
			'url.back' => $this->linkas('#'),
			'event.save' => $this->my('fullname') . '#save'
		);
		if (NULL === ($entryData = $this->getEntry_($argv['id']))) {
			$messages = $tpl->parse_array('messages');
			$e  = $messages['e.entry_not_found'];
			moon::page()->page404();
		}

		$mainArgv['submenu'] = $this->object('promos')->getPreferredSubmenu($argv['id'], $entryData, $this->my('fullname'));

		foreach ($entryData as $key => $value) {
			$mainArgv['entry.' . $key] = htmlspecialchars($value);
		}

		if (NULL != ($resultChunks = $this->object('promos')->parseResults($entryData['lb_data'], $entryData['lb_columns']))) {
			if (count($resultChunks['data']) > 0) {
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
					$mainArgv['result.data.rows'] .= $tpl->parse('entry:result.data.row', array(
						'result.data' => $rowData
					));
				}
			}
		}

		return $tpl->parse('entry:main', $mainArgv);
	}

	private function getEntry_($id)
	{
		if (NULL === getInteger($id)) {
			return NULL;
		}
		$entry = $this->db->single_query_assoc('
			SELECT id, title, lb_auto, lb_columns, lb_data' . (_SITE_ID_ != 'com'
				? ', remote_id'
				: '') . '
			FROM promos
			WHERE id=' . $id . '
		');
		if (empty($entry)) {
			return NULL;
		}
		return $entry;
	}

	private function saveEntry($data)
	{
		$this->db->update(array(
			'lb_data' => $data['lb_data']
			// updated_on must be automatic in-db
		), 'promos', array(
			'id' => $data['id'],
			'lb_auto' => 0
		));
		blame($this->my('fullname'), 'Updated', $saveData['id']);
		return $saveData['id'];
	}

	private function xlsToCsv($p) {
		return $this->object('promos')->xlsToCsv($p);
	}
}