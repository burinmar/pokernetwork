<?php

class polls extends moon_com
{
	var $formFilter_ = NULL;
	var $registry_   = array();

	function onload()
	{
		$this->isTrivia = ($this->get_var('pollType') === 'trivia') ? 1 : 0;
	}

	function properties()
	{
		return array(
			'render' => NULL,
			'id' => NULL,
			'page'   => NULL,
			'sort'   => NULL
		);
	}

	function events($event, $argv)
	{
		switch ($event) {
			case 'save':
				$this->forget();
				$page = &moon::page();
				$form = &$this->form();
				$form->names('id', 'question', 'places', 'answer', 'restrictions', 'is_hidden', 'position', 'stayhere', 'answer_text', 'is_trivia', 'is_new', 'is_correct_answer');
				$form->fill($_POST);
				$data = $form->get_values();
				$data['is_trivia'] = $this->isTrivia;
				
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
							$this->redirect('#new');
						default:
							$this->redirect('#', $data['id']);
					}
				} else {
					switch ($data['stayhere']) {
						case '':
							$this->redirect('#');
						default:
							$this->redirect('#', $saved);
					}
				}
				exit;

			case 'delete':
				$form = &$this->form();
				$form->names('ids');
				$form->fill($_POST);
				$data = $form->get_values();
				$this->deleteEntry_(explode(',', $data['ids']));
				$this->redirect('#');
				break;

			case 'new':
				$this->set_var('render', 'entry');
				break;

			default:
				if (isset($argv[0]) && NULL !== ($id = filter_var($argv[0], FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE))) {
					$this->set_var('render', 'entry');
					$this->set_var('id', $id);
				}
				break;
		}
		if (isset($_GET['page'])) {
			$this->set_var('page', intval($_GET['page']));
		}
		$this->use_page('Common');
	}

	function main($argv)
	{
		$window = &moon::shared('admin');
		$page   = &moon::page();
		$window->active($this->my('fullname'));
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
				$output = $this->renderList_($argv, $e);
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

	function renderList_($argv, &$e)
	{
		$page	= &moon::page();
		$tpl	= &$this->load_template();
		$win	= &moon::shared('admin');

		$page->js('/js/modules_adm/ng-list.js');

		$mainArgv  = array(
			'url.add_entry' => $this->linkas('#new'),
			'event.delete'  => $this->my('fullname') . '#delete',
			'list.entries'  => '',
			'title' 	=> $win->current_info('title')
		);

		$paginator  = &moon::shared('paginate');
		$paginator->set_curent_all_limit($argv['page'], $this->countEntries_(), $this->get_var('paginateBy'));
		$paginator->set_url( $this->linkas('#', '', array('page'=>'{pg}')));
		$mainArgv['paging']   = $paginator->show_nav();
		$paginatorInfo = $paginator->get_info();
		$entries = $this->getEntries_($paginatorInfo['sqllimit']);
		$places = $this->get_var('pollZones');
		foreach ($entries as $key => $entry) {
			$ePlaces = array();
			$ePlaceKeys = explode(',', $entry['places']);
			foreach ($ePlaceKeys as $key) {
				if (isset($places[$key])) {
					$ePlaces[] = $places[$key];
				}
			}
			$mainArgv['list.entries'] .= $tpl->parse('list:entries.item', array(
				'id'  => $entry['id'],
				'url' => $this->linkas('#',$entry['id']),
				'places' => implode(', ', $ePlaces),
				'question' => htmlspecialchars($entry['question']),
				'is_not_active' => $entry['is_hidden'] || '' == $entry['places']
			));
		}

		return $tpl->parse('list:main', $mainArgv);
	}

	function getEntries_($limit = NULL)
	{
		$limit = (NULL !== $limit && preg_match('`^\s*limit\s*[0-9]+(,[0-9]+)?$`i', $limit))
			? $limit
			: '';
		return $this->db->array_query_assoc('
			SELECT * FROM ' . $this->table('Questions') . '
			WHERE is_trivia = ' . $this->isTrivia . '
			ORDER BY id DESC ' .
			$limit);
	}

	function countEntries_()
	{
		$count = $this->db->single_query_assoc('
			SELECT COUNT(id) cid
			FROM ' . $this->table('Questions') . '
			WHERE is_trivia = ' . $this->isTrivia
		);
		return $count['cid'];
	}

	function renderEntry_($argv, &$e)
	{
		$page     = &moon::page();
		$tpl      = &$this->load_template();
		$mainArgv  = array(
			'url.back' => $this->linkas('#'),
			'event.save' => $this->my('fullname') . '#save',
			'stayhere' => !empty($argv['form-stay']),
			'list.places' => '',
			'list.answers' => '',
			'list.restrictions' => '',
			'timestamp' => time(),
			'is_trivia' => $this->isTrivia,
			'answer_text' => ''
		);
		
		$page->js('/js/modules_adm/ng-entry.js');
		$page->js('/js/jquery/livequery.js');
		$page->js('/js/jquery/maskedinput-1.1.3.js');
		
		$mainArgv['answers.item.empty'] = addcslashes(addslashes($tpl->parse('answers.item', array(
			'id' => '',
			'question' => '',
			'position' => '',
			'allow_add_answers' => TRUE,
			'is_new' => '1',
			'is_trivia' => $this->isTrivia,
			'correct' => FALSE
		))),"\n\r\t");
		$mainArgv['answers.item.empty.primary'] = addcslashes(addslashes($tpl->parse('answers.item', array(
			'id' => '',
			'question' => '',
			'position' => '',
			'allow_add_answers' => TRUE,
			'is_new' => '1',
			'is_trivia' => $this->isTrivia,
			'correct' => TRUE,
			'correctAnsVal' => 0
		))),"\n\r\t");
	
		if (NULL === $argv['id']) {
			$entryData = array(
				'id' => '',
				'question' => '',
				'is_hidden' => '',
				'restrictions' => '1',
				'places' => array(),
				'answers' => array(),
				'answer_text' => '',
				'places' => array(
					'home'
				)
			);
			$mainArgv['allow_add_answers'] = TRUE;
			$mainArgv['title'] = 'New poll';
		} else {
			if (NULL === ($entryData = $this->getEntry_($argv['id']))) {
				$messages = $tpl->parse_array('messages');
				$e  = $messages['e.entry_not_found'];
				return ;
			}
			$entryData = array_merge($entryData, array(
				'places' => explode(',', $entryData['places'])
			));
			$mainArgv['allow_add_answers'] = TRUE; // ?
			$mainArgv['show-stats'] = TRUE;
			$mainArgv['title'] = htmlspecialchars($entryData['question']);
			$mainArgv['answer_text'] = htmlspecialchars($entryData['answer_text']);
			$mainArgv['list.stats'] = '';
			if (!empty($entryData['answers'])) {
				$max_width = 3.00;
				$total = 0;
				foreach ($entryData['answers'] as $ans) {
					$total += $ans['votes'];
				}
				$mainArgv['total'] = $total;
				$total = $total ? $total : 1;
				foreach ($entryData['answers'] as $ans) {
					$prcnt = round($ans['votes']*100/$total);
					$mainArgv['list.stats'] .= $tpl->parse('stats.item', array(
						'answer' => htmlspecialchars($ans['answer']),
						'width' => round($prcnt*$max_width+2),
						'percent' => $prcnt
					));
				}
			}
		}
		
		if (isset($argv['failed-form-data'])) {
			if (is_array($argv['failed-form-data']['places'])) {
				$argv['failed-form-data']['places'] = array_keys($argv['failed-form-data']['places']);
			} else {
				unset($argv['failed-form-data']['places']);
			}

			if (is_array($argv['failed-form-data']['answer'])) {
				// new poll save error
				$answers = array();
				$i = 0;
				foreach ($argv['failed-form-data']['answer'] as $k=>$answer) {
					$answers[] = array(
						'id' => $k,
						'answer' => $answer,
						'position' => $argv['failed-form-data']['position'][$k],
						'is_correct_answer' => ($argv['failed-form-data']['is_correct_answer'] == $i++) ? 1 : 0
					);
				}
				$argv['failed-form-data']['answers']  = $answers;
				$argv['failed-form-data']['answer']   = '';
				$argv['failed-form-data']['position'] = '';
			} else {
				unset($argv['failed-form-data']['answer']);
			}

			if (is_array($argv['failed-form-data']['position'])) {
				// existing poll save error
				foreach ($entryData['answers'] as $k=>$answer) {
					if (isset($argv['failed-form-data']['position'][$answer['id']])) {
						$entryData['answers'][$k]['position'] = filter_var($argv['failed-form-data']['position'][$answer['id']], FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
					}
				}
			} else {
				unset($argv['failed-form-data']['position']);
			}

			$entryData = array_intersect_key(
				array_merge($entryData, $argv['failed-form-data']),
				$entryData);
		}
		
		foreach ($entryData['answers'] as $k => $answer) {
			$mainArgv['list.answers'] .= $tpl->parse('answers.item', array(
				'id' => $answer['id'],
				'position' => $answer['position'],
				'question' => htmlspecialchars($answer['answer']),
				'is_new' => 0,
				'is_trivia' => $this->isTrivia,
				'correct' => $answer['is_correct_answer'],
				'correctAnsVal' => $k,
				'allow_add_answers' => true // ?
			));
		}

		if (is_array($entryData['places'])) {
			$entryData['places'] = array_flip($entryData['places']);
		}
		$places = $this->get_var('pollZones');
		foreach ($places as $placeId =>$placeName) {
			$mainArgv['list.places'] .= $tpl->parse('places.item', array(
				'name' => htmlspecialchars($placeName),
				'id' => $placeId,
				'checked' => (isset($entryData['places'][$placeId]))
			));
		}

		$restrictions = $this->get_var('restrictions');
		foreach ($restrictions as $restrictionId => $restrictionName) {
			$mainArgv['list.restrictions'] .= $tpl->parse('restrictions.item', array(
				'name' => htmlspecialchars($restrictionName),
				'value' => $restrictionId,
				'selected' => ($entryData['restrictions'] == (string)$restrictionId)
			));
		}

		foreach ($entryData as $key => $value) {
			if (is_string($value)) {
				$mainArgv['entry.' . $key] = htmlspecialchars($value);
			}
		}

		return $tpl->parse('entry:main', $mainArgv);
	}

	function getEntry_($id)
	{
		if (NULL === filter_var($id, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE)) {
			return NULL;
		}
		$entry = $this->db->single_query_assoc('
			SELECT * FROM ' . $this->table('Questions') . '
			WHERE id=' . $id . '
		');
		if (empty($entry)) {
			return NULL;
		}
		$entry['answers'] = $this->db->array_query_assoc('
			SELECT id, answer, position, votes, is_correct_answer
			FROM '.$this->table('Answers').'
			WHERE question_id=' . $id . '
			ORDER BY position ASC
		');
		return $entry;
	}

	/* data *must* be an array of strings */
	function saveEntry_($data)
	{
		$page     = &moon::page();
		$tpl      = &$this->load_template();
		$messages = $tpl->parse_array('messages');

		$saveData = array();
		foreach (array('id', 'question', 'places', 'restrictions', 'is_hidden', 'is_trivia', 'answer_text') as $key) {
			if (isset($data[$key])) {
				$saveData[$key] = $data[$key];
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
		foreach (array('question') as $key) {
			if (empty($saveData[$key])) {
				$page->alert($messages['e.empty_' . $key]);
				$isInvalid = TRUE;
			}
		}
		
		if ($this->isTrivia) {
			foreach (array('answer_text') as $key) {
				if (empty($saveData[$key])) {
					$page->alert($messages['e.empty_' . $key]);
					$isInvalid = TRUE;
				}
			}
		}
		if (isset($saveData['places']) && is_array($saveData['places'])) {
			$placesValid = $this->get_var('pollZones');
			$placesSaved = array();
			foreach ($saveData['places'] as $placeId=>$placeName) {
				if (isset($placesValid[$placeId])) {
					$placesSaved[] = $placeId;
				}
			}
			$saveData['places'] = implode(',', $placesSaved);
		}

		if (isset($saveData['places']) && (!isset($saveData['is_hidden']) || (1 !==(filter_var($saveData['is_hidden'], FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE))))) {
			foreach (explode(',', $saveData['places']) as $placeId) {
				if ('' == $placeId) {
					continue;
				}
				// dunno wether there is better way
				$overlap = $this->db->single_query_assoc('
					SELECT COUNT(*) cid FROM poll_questions
					WHERE is_hidden=0 AND is_trivia = ' . $this->isTrivia . ' ' .
					(empty($saveData['places'])
						? ''
						: ' AND FIND_IN_SET("' . addslashes($placeId) . '", places)').
					(NULL === $saveData['id']
						? ''
						: ' AND id!=' . $saveData['id'])
				);
				if (intval($overlap['cid']) > 0) {
					$isInvalid = TRUE;
					$places = $this->get_var('pollZones');
					if (isset($places[$placeId])) {
						$page->alert(sprintf($messages['e.date_overlap_s'], $places[$placeId]));
					} else {
						$page->alert($messages['e.date_overlap']);
					}
					break;
				}
			}
		}

		$answers = array();
		$i = 0;
		foreach ($data['answer'] as $k=>$answer) {
			if ('' == trim($answer)) {
				continue;
			}
			$answers[$k] = array(
				'answer' => $answer,
				'position' => $data['position'][$k],
				'is_correct_answer' => ($data['is_correct_answer'] !== '' 
					&& $data['is_correct_answer'] == $i++) ? 1 : 0
			);
		}
		if (count($answers) < 2) {
			$isInvalid = TRUE;
			$page->alert($messages['e.low_answers']);
		}
		if (count($answers) > 10) {
			$isInvalid = TRUE;
			$page->alert($messages['e.high_answers']);
		}

		if (TRUE === $isInvalid) {
			return NULL;
		}
		
		if (NULL === $saveData['id']) {
			$saveData['created_on'] = time();
			$user = &moon::user();
			$saveData['created_by'] = $user->id();
			$this->db->insert($saveData, $this->table('Questions'));
			$id = $this->db->insert_id();
			blame($this->my('fullname'), 'Created', $id);
			if (!empty($id)) {
				foreach ($answers as $answer) {
					$answer['question_id'] = $id;
					$this->db->insert($answer, $this->table('Answers'));
				}
			}
			return $id;
		} else {
			$saveData['updated_on'] = time();
			$this->db->update($saveData, $this->table('Questions'), array(
				'id' => $saveData['id']
			));
			blame($this->my('fullname'), 'Updated', $saveData['id']);
			$oldAnswers = $this->db->array_query_assoc('
				SELECT id FROM ' . $this->table('Answers') . '
				WHERE question_id=' . intval($saveData['id']) . '
			', 'id');
			foreach ($answers as $id => $answer) {
				$answer['question_id'] = $saveData['id'];
				if (!empty($data['is_new'][$id])) {
					$this->db->insert($answer, $this->table('Answers'));
				} else {
					unset($oldAnswers[$id]);
					$this->db->update($answer, $this->table('Answers'), array(
						'id' => $id, 
						'question_id' => $answer['question_id']
					));
				}
			}
			if (0 != count($oldAnswers)) {
				$deleteAnswers = array_keys($oldAnswers);
				$this->db->query('
					DELETE FROM ' . $this->table('Answers') . '
					WHERE id IN (' . implode(',', $deleteAnswers) . ')
				');
				$this->db->query('
					DELETE FROM ' . $this->table('Votes') . '
					WHERE answer_id IN (' . implode(',', $deleteAnswers) . ')
				');
			}
			return $saveData['id'];
		}
	}

	function deleteEntry_($ids)
	{
		$deleteIds = array();
		if (!is_array($ids)) {
			$ids = array($ids);
		}
		foreach ($ids as $id) {
			if (NULL !== ($id = filter_var($id, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE))) {
				$deleteIds[] = $id;
			}
		}
		if (empty($deleteIds)) {
			return ;
		}
		$this->db->query('
			DELETE FROM ' . $this->table('Votes') . '
			WHERE question_id IN ('.implode(',', $deleteIds).')
		');
		$this->db->query('
			DELETE FROM ' . $this->table('Answers') . '
			WHERE question_id IN ('.implode(',', $deleteIds).')
		');
		$this->db->query('
			DELETE FROM ' . $this->table('Questions') . '
			WHERE id IN ('.implode(',', $deleteIds).')
		');
		blame($this->my('fullname'), 'Deleted', $deleteIds);
	}
}