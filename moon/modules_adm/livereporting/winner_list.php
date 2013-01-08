<?php

class winner_list extends moon_com
{
	function onload()
	{}

	function properties()
	{
		return array(
			'render' => NULL,
			'id' => NULL
		);
	}

	function events($event, $argv)
	{
		switch ($event) {
			case 'save':
				$this->forget();
				$page = &moon::page();
				$form = &$this->form();

				$form->names('id','tournament_id','event_id','winner','runner_up',
					'winning_hand','losing_hand',
					'prize','stayhere');
				$form->fill($_POST);
				$data = $form->get_values();
				$file = new moon_file;

				$gData = $page->get_global($this->my('fullname'));
				if ('' === $gData) {
					$gData = array();
				}
				$gData['one-run'] = array();
				if ('' != $data['stayhere']) {
					$gData['one-run']['form-stay'] = 1;
					$page->set_global($this->my('fullname'), $gData);
				}

				$saved = $this->saveEntry_($data);
				if (NULL === $saved) {
					$gData['one-run']['failed-form-data'] = $data;
					$page->set_global($this->my('fullname'), $gData);
					switch ($data['id']) {
						case '':
							$this->redirect('#new', 'by-event.' . $data['event_id']);
						default:
							$this->redirect('#', $data['id']);
					}
				} else {
					switch ($data['stayhere']) {
						case '':
							$this->redirect('event_list#','by-tour.' . $data['tournament_id']);
						default:
							$this->redirect('#', $saved);
					}
				}
				exit;

			case 'new':
				if (isset($argv[1]) && $argv[0] == 'by-event'
				    && false !== ($id = filter_var($argv[1], FILTER_VALIDATE_INT))) {
					$this->set_var('event-id', $id);
					$this->set_var('render', 'entry');
				}
				break;
 
			default:
				if (isset($argv[1]) && $argv[0] == 'by-event'
				    && false !== ($id = filter_var($argv[1], FILTER_VALIDATE_INT))) {
					$this->set_var('event-id', $id);
				} elseif (isset($argv[0]) && false !== ($id = filter_var($argv[0], FILTER_VALIDATE_INT))) {
					$this->set_var('render', 'entry');
					$this->set_var('id', $id);
				}
				break;
		}
		$this->use_page('Common');
	}

	function main($argv)
	{
		$window = &moon::shared('admin');
		$page   = &moon::page();
		$window->active($this->my('module').'.tour_list');
		$page->title(
			$window->current_info('title')
		);

		$gArgv = $page->get_global($this->my('fullname'));
		if (isset($gArgv['one-run']) && is_array($gArgv['one-run'])) {
			foreach ($gArgv['one-run'] as $key => $value) {
				$argv[$key] = $value;
			}
		}
		if (isset($gArgv['one-run'])) {
			unset($gArgv['one-run']);
		}
		if (!empty($gArgv)) {
			$page->set_global($this->my('fullname'), $gArgv);
		} else {
			$page->set_global($this->my('fullname'), '');
		}

		$e = NULL;
		switch ($argv['render']) {
			case 'entry':
				$output = $this->renderEntry_($argv, $e);
				break;

			default:
				$output = '';
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

	function getLocation($id)
	{
		$tour = $this->db->single_query_assoc('
			SELECT t.id tournament_id, t.name tournament_name,
				e.id event_id, e.name event_name, t.currency
			FROM ' . $this->table('Tournaments') . ' t
			LEFT JOIN ' . $this->table('Events') . ' e
				ON t.id=e.tournament_id
			WHERE e.id="' . $this->db->escape($id) . '" AND e.is_live>=0 AND t.is_live>=0
		');
		if (empty($tour)) {
			return NULL;
		}
		return $tour;
	}

	function renderEntry_($argv, &$e)
	{
		$page     = &moon::page();
		$tpl      = &$this->load_template();
		$mainArgv  = array(
			'event.save' => $this->my('fullname') . '#save',
			'stayhere' => !empty($argv['form-stay'])
		);
		
		if (NULL === $argv['id']) {
			$location = $this->getLocation($argv['event-id']);
			$entryData = array(
				'id' => '',
				'event_id' => $location['event_id'],
				'tournament_id' => $location['tournament_id'],
				'winner' => '',
				'runner_up' => '',
				'winning_hand' => '',
				'losing_hand' => '',
				'prize' => ''
			);
			$mainArgv['title'] = 'New winner';
		} else {
			if (NULL === ($entryData = $this->getEntry_($argv['id']))) {
				$messages = $tpl->parse_array('messages');
				$e  = $messages['e.entry_not_found'];
				return ;
			}
			$location = $this->getLocation($entryData['event_id']);
			
			$mainArgv['title'] = htmlspecialchars($entryData['winner']);
		}
		if (isset($argv['failed-form-data'])) {
			$entryData = array_intersect_key(
				array_merge($entryData, $argv['failed-form-data']),
				$entryData);
		}

		$mainArgv['url.back'] = $this->linkas('event_list#', 'by-tour.' . $location['tournament_id']);
		// $mainArgv['toururi']  = htmlspecialchars($location['alias']);
		$mainArgv['tourname']  = htmlspecialchars($location['tournament_name']);
		$mainArgv['evname']    = htmlspecialchars($location['event_name']);
		$mainArgv['tourcurr'] = htmlspecialchars($location['currency']);
		
		if (empty($entryData['winner']) || empty($entryData['runner_up'])) {
			$suggestions = $this->db->array_query_assoc('
				SELECT name, place FROM ' . $this->table('Players') . '
				WHERE event_id=' . $location['event_id'] . '
				  AND place IN (1,2)
			', 'place');
			if (empty($entryData['winner']) && !empty($suggestions[1])) {
				$mainArgv['suggested_winner'] = htmlspecialchars($suggestions[1]['name']);
			}
			if (empty($entryData['runner_up']) && !empty($suggestions[2])) {
				$mainArgv['suggested_runnerup'] = htmlspecialchars($suggestions[2]['name']);
			}
		}
		if (empty($entryData['prize'])) {
			$suggestion = $this->db->single_query_assoc('
				SELECT prize FROM ' . $this->table('Payouts') . '
				WHERE event_id=' . $location['event_id'] . '
				  AND place=1
			');
			if (!empty($suggestion)) {
				$mainArgv['suggested_prize'] = $suggestion['prize'];
			}
		}

		// add toolbar
		$mainArgv['toolbar'] = '';
		if (is_object( $rtf = $this->object('rtf') )) {
			$rtf->setInstance( $this->get_var('rtf') );
			$mainArgv['toolbar'] = $rtf->toolbar('i_winning_hand',0);
		}
		
		foreach ($entryData as $key => $value) {
			$mainArgv['entry.' . $key] = htmlspecialchars($value);
		}

		return $tpl->parse('entry:main', $mainArgv);
	}

	function getEntry_($id)
	{
		if (false === filter_var($id, FILTER_VALIDATE_INT)) {
			return NULL;
		}
		$entry = $this->db->single_query_assoc('
			SELECT * FROM ' . $this->table('Winners') . '
			WHERE id=' . $id);
		if (empty($entry)) {
			return NULL;
		}
		return $entry;
	}

	/* data *must* be an array of strings */
	function saveEntry_($data)
	{
		$page     = &moon::page();
		$tpl      = &$this->load_template();
		$messages = $tpl->parse_array('messages');

		$saveData = array();
		foreach (array(
				'id','tournament_id','event_id','winner','runner_up',
				'winning_hand','losing_hand',
				'prize'
			) as $key) {
			if (isset($data[$key])) {
				$saveData[$key] = $data[$key];
			}
		}
		
		if (false !== filter_var($saveData['id'], FILTER_VALIDATE_INT)) {
			$check = $this->db->single_query_assoc('
				SELECT id FROM ' . $this->table('Events') . '
				WHERE tournament_id="' .$this->db->escape($data['tournament_id']). '"
					AND id="' . $this->db->escape($data['event_id']) . '"
			');
			if (empty($check)) {
				return ;
			}
		}
		
		if (0 === count($saveData)) {
			return ;
		}

		$isInvalid = FALSE;
		if (isset($saveData['id']) && '' !== $saveData['id']) {
			$saveData['id'] = filter_var($saveData['id'], FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
			if (NULL === $saveData['id']) {
				$page->alert($messages['e.invalid_id']);
				$isInvalid = TRUE;
			}
		} else {
			$saveData['id'] = NULL; // autoincrement ok, later used
		}

		$required = array();

		foreach ($required as $key) {
			if (empty($saveData[$key])) {
				$page->alert($messages['e.empty_' . $key]);
				$isInvalid = TRUE;
			}
		}
		
		if (TRUE === $isInvalid) {
			return NULL;
		}
		
		if (!empty($saveData['prize'])) {
			$saveData['prize'] = preg_replace('/[^0-9]/i', '', $saveData['prize']);
		}
		
		if (NULL === $saveData['id']) {
			$saveData['created_on'] = $saveData['updated_on'] = time();
			$this->db->insert($saveData, $this->table('Winners'));
			$rId = $this->db->insert_id();
			blame($this->my('fullname'), 'Created', $rId);
			livereporting_adm_alt_log($saveData['tournament_id'], $saveData['event_id'], 0, 'insert', 'winners', $rId);
			return $rId;
		} else {
			$saveData['updated_on'] = time();
			$this->db->update($saveData, $this->table('Winners'), array(
				'id' => $saveData['id']
			));
			blame($this->my('fullname'), 'Updated', $saveData['id']);
			livereporting_adm_alt_log($saveData['tournament_id'], $saveData['event_id'], 0, 'update', 'winners', $saveData['id']);
			return $saveData['id'];
		}
	}
}