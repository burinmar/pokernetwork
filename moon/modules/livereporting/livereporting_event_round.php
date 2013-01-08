<?php
/**
 * @package livereporting
 */
/**
 */
require_once 'livereporting_event_pylon.php';
/**
 * @package livereporting
 */
class livereporting_event_round extends livereporting_event_pylon
{
	protected function synthEvent($event, $argv)
	{
		switch ($event) {
			case 'save-round':
				$data = $this->helperEventGetData(array(
					'round_id', 'round', 'day_id', 'duration', 'limit_not_blind', 'small_blind', 'big_blind', 'small_limit', 'big_limit', 'ante', 'description', 'has_break', 'break_duration'
				));
				$data['datetime'] = null;
				$roundId = $this->saveRoundAndBreak($data);
				$this->redirectAfterSave($roundId, 'round', array(
					'add' => (!empty($_POST['master'])
						? array('master' => 'round')
						: NULL
					),
					'noanchor' => !empty($_POST['master'])
				));
				exit;
			case 'save-round-datetime':
				$_POST['datetime_options'] = 'sct_dt';
				$data = $this->helperEventGetData(array('round_id', 'day_id', 'datetime_options'));
				
				$roundId = $this->saveDatetime($data);
				$this->redirectAfterSave($roundId, 'round');
				exit;
			case 'save-start': // states
			case 'save-stop':
			case 'save-move':
				if (!$this->lrep()->instTools()->isAllowed('writeContent')) { // cosmetic
					moon::page()->page404();
				}
				switch ($argv['uri']['argv'][2]) {
				case 'start':
					$this->saveStart(array(
						'round_id' => $argv['uri']['argv'][3],
						'event_id' => $argv['event_id'],
						'state' => $argv['uri']['argv'][2]
					));
					break;
				case 'stop':
					$this->saveStop(array(
						'round_id' => $argv['uri']['argv'][3],
						'event_id' => $argv['event_id'],
						'state' => $argv['uri']['argv'][2]
					));
					break;
				case 'move':
					$this->saveMove(array(
						'round_id' => $argv['uri']['argv'][3],
						'event_id' => $argv['event_id'],
						'day_from_id' => $argv['uri']['argv'][4],
						'day_to_id' => $argv['uri']['argv'][5]
					));
					break;
				}
				$this->redirect('event#view', array(
					'event_id' => filter_var($argv['event_id'], FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE),
					'path' => $this->getUriPath($this->requestArgv('day_id'))
				), $this->getUriFilter(array('master'=>'round'), TRUE));
				exit;
			case 'delete':
				if (FALSE === $this->delete($argv['uri']['argv'][2])) {
					moon::page()->page404();
				}
				$this->redirectAfterDelete($argv['event_id'], $this->requestArgv('day_id'), 
					isset($_GET['master']) && $_GET['master'] == 'round'
						? array('master' => 'round')
						: NULL);
				exit;
			case 'load-rounds':
				$this->forget();
				echo json_encode(array(
					'status' => 0,
					'data' => $this->subRenderRoundAndBreakList($argv['event_id'], $argv['uri']['argv'][3])
				));
				moon_close();
				exit;
			default:
				moon::page()->page404();
		}
	}

	protected function render($data, $argv = NULL)
	{
		if ($argv['variation'] == 'logControl') {
			return $this->renderRoundAndBreakControl(array_merge($data, array(
				'unhide' => (!empty($_GET['master']) && $_GET['master'] == 'round')
			)));
		}
		
		$lrep = $this->lrep();
		$page = moon::page();
		$tpl = $this->load_template();
		
		$rArgv = $this->helperRenderCommonArgv($data, $argv, $tpl);

		$rArgv += array(
			'round' => $data['contents']['round'],
			'duration' => $data['contents']['duration'],
			'custom' => !empty($data['contents']['description']),
		);
		foreach (array('ante', 'description', 'small_blind', 'big_blind') as $optionalKey) {
			$rArgv[$optionalKey] = isset($data['contents'][$optionalKey])
				? $data['contents'][$optionalKey]
				: '';
		}

		if ($argv['variation'] == 'logEntry') {
			$rArgv += array(
				'is_hidden'  => $data['is_hidden']
			);
			if (!empty($rArgv['show_controls'])) {
				unset($rArgv['url.delete']);
				$eventInfo = $lrep->instEventModel('_src_event')->getEventData($data['event_id']);
				// if last round?
				$rArgv += array(
					'url.stop' => $this->linkas('event#save', array(
						'event_id' => $data['event_id'],
						'path' => $this->getUriPath(),
						'type' => 'round',
						'id' => 'stop.' . $data['id']
					), $this->getUriFilter(array('master' => 'round'), true)),
				);
			}
			return $tpl->parse('logEntry:round.' . $this->helperGetRoundVariety($data['contents']), $rArgv);
		} elseif ($argv['variation'] == 'individual') {
			if ($argv['action'] == 'view') {
				$page->page404();
			}
			if ($rArgv['show_controls']) {
				if (isset($_GET['display']) && $_GET['display'] == 'full') {
					$entry = $this->getEditableData($data['id'], $data['event_id']);
					// depends on subtype (should be round-breaks or round-limits), which is not auto-checked
					if (empty($entry))
						$page->page404();
					$rArgv['control'] = $this->renderRoundAndBreakControl(array_merge($entry, array(
						'unhide' => true,
						'master' => (isset($_GET['master']) && $_GET['master'] == 'round'),
					)));
				} else {
					$rArgv['control'] = $this->renderDatetimeControl($data);
				}
			}
			return $tpl->parse('entry:round', $rArgv);
		}
	}

	private function helperGetRoundVariety($dataContents)
	{
		$roundVariety = isset($dataContents['variety'])
			? $dataContents['variety']
			: (!empty($dataContents['limit_not_blind'])
				? 'limits-round'
				: 'blinds-round');
		if (!empty($dataContents['description']))
			$roundVariety = 'custom';
		return $roundVariety;
	}

	private function getEditableData($id, $eventId)
	{
		$entry = $this->db->single_query_assoc('
			SELECT l.tournament_id, l.event_id, l.day_id, l.created_on, l.updated_on, l.is_hidden, r.*, rb.id break_id, rb.duration break_duration
			FROM ' . $this->table('Log') . ' l
			INNER JOIN ' . $this->table('tRounds') . ' r
				ON l.id=r.id AND r.variety IN("blinds-round", "limits-round")
			LEFT JOIN (
				SELECT rb.id, rb.round, rb.duration, lb.day_id
				FROM ' . $this->table('Log') . ' lb
				INNER JOIN ' . $this->table('tRounds') . ' rb
					ON rb.id=lb.id AND rb.variety IN("break")
				WHERE lb.event_id=' . intval($eventId) . ' AND lb.type="round" 
			) rb
				ON (r.round=rb.round AND l.day_id=rb.day_id)
			WHERE l.id=' . intval($id) . ' AND l.type="round"
				AND l.event_id=' . intval($eventId)
		);
		if (empty($entry)) {
			return NULL;
		}
		return $entry;
	}
	
	// in ascending(!) order
	private function getRounds($dayId)
	{
		return $this->db->array_query_assoc('
			SELECT l.is_hidden, l.created_on, r.*, rb.id break_id, rb.duration break_duration, rb.is_hidden break_is_hidden
			FROM ' . $this->table('Log') . ' l
			INNER JOIN ' . $this->table('tRounds') . ' r
				ON r.id=l.id AND r.variety IN("blinds-round", "limits-round")
			LEFT JOIN (
				SELECT rb.id, rb.round, rb.duration, lb.is_hidden
				FROM ' . $this->table('Log') . ' lb
				INNER JOIN ' . $this->table('tRounds') . ' rb
					ON rb.id=lb.id AND rb.variety IN("break")
				WHERE lb.day_id=' . intval($dayId) . ' AND lb.type="round" 
			) rb
				ON (r.round=rb.round)
			WHERE l.day_id=' . intval($dayId) . ' AND l.type="round" 
			ORDER BY r.round ASC
		');
	}

	private function renderDatetimeControl($argv)
	{
		if (empty($argv['id'])) {
			return ;
		}

		$controlsArgv = array(
			'cr.save_event'      => $this->parent->my('fullname') . '#save-round-datetime',
			'cr.round_id'        => $argv['id'],
			'cr.day_id'          => $argv['day_id'],
			'cr.custom_datetime' => $this->lrep()->instTools()->helperCustomDatetimeWrite('+Y #m +d +H:M -S -z', (isset($argv['created_on']) ? intval($argv['created_on']) : time()) + $argv['tzOffset'], $argv['tzOffset']),
			'cr.custom_tz'       => $argv['tzName'],
		);

		return $this->load_template()->parse('controls.datetime:round', $controlsArgv);
	}

	private function saveDatetime($data)
	{
		if (NULL == ($prereq = $this->helperSaveCheckPrerequisites($data['day_id'], $data['round_id'], 'round'))) {
			return FALSE;
		}
		list (
			$location,
			$entry
		) = $prereq;

		$saveDataLog = array(
			'updated_on' => time(),
		);
		if (NULL != $data['datetime']) {
			$saveDataLog['created_on'] = $data['datetime'];
		}

		$this->db->update($saveDataLog, $this->table('Log'), array(
			'id' => $entry['id'],
			'type' => 'round'
		));
			
		// for sync: unhide hidden rounds on save
		$this->db->update(array(
			'is_hidden' => 0,
			'author_id' => intval(moon::user()->get_user_id())
		), $this->table('Log'), array(
			'id' => $entry['id'],
			'type' => 'round',
			'is_hidden' => 1
		));

		return $entry['id'];
	}

	private function renderRoundAndBreakControl($argv)
	{
		if (empty($argv['day_id'])) {
			return ;
		}

		$controlsArgv = array(
			'cr.save_event' => $this->parent->my('fullname') . '#save-round',
			'cr.round_id' => isset($argv['id'])
				? intval($argv['id'])
				: '',
			'cr.day_id' => $argv['day_id'],
			'cr.unhide' => !empty($argv['unhide']),
			'cr.limit_not_blind' => !empty($argv['variety']) && $argv['variety'] == 'limits-round',
			'cr.has_break' => !empty($argv['break_id'])
		);
		foreach (array('round', 'duration', 'small_blind', 'big_blind', 'ante', 'description', 'break_duration') as $cname) {
			$controlsArgv['cr.' . $cname] = isset($argv[$cname])
				? htmlspecialchars($argv[$cname])
				: '';
		}
		
		if (empty($argv['id'])) { // list control
			$controlsArgv += array(
				'cr.url.ajax_rounds' => htmlspecialchars_decode($this->linkas('event#load', array(
					'event_id' => $argv['event_id'],
					'path' => $this->getUriPath(0),
					'type' => 'round',
					'id' => 'rounds.' . $argv['day_id']
				), $this->getUriFilter(NULL, TRUE))),
				'rounds' => !empty($argv['unhide'])
					? $this->subRenderRoundAndBreakList($argv['event_id'], $argv['day_id'])
					: 'Loading', // not empty
				'cr.master' => 'round', // show list after save
			);
		} else {
			if (!empty($argv['master'])) {
				$controlsArgv['cr.master'] = 'round'; // show list after save
			}
		}
		
		return $this->load_template()
			->parse('controls:round', $controlsArgv);
	}

	private function subRenderRoundAndBreakList($eventId, $dayId)
	{
		$rounds = $this->getRounds($dayId);
		$lrep = $this->lrep();
		$text = moon::shared('text');
		$tpl  = $this->load_template();
		$eventData = $lrep->instEventModel('_src_event')->getEventData($eventId);

		$lastStartedRoundNr = -1;
		foreach ($rounds as $k => $round) {
			if ($round['is_hidden'] == 0) {
				$lastStartedRoundNr = $k;
			}
		}

		$moveDays = array();
		$moveDayCandidates = array_reverse($lrep->instEventModel('_src_event')->getDaysData($eventId), true);
		$moveDayName = preg_replace('~[a-z]~i', '', $moveDayCandidates[$dayId]['name']);
		foreach ($moveDayCandidates as $moveDayCandidate) {
			if ($moveDayCandidate['id'] == $dayId || strpos($moveDayCandidate['name'], $moveDayName) === 0) {
				break;
			}
			$moveDays[] = $moveDayCandidate;
		}
		$moveDays = array_reverse($moveDays);
		$moveDays = array_slice($moveDays, 0, 1);

		$tRounds = '';
		// var_dump($lastStartedRoundNr);
		foreach ($rounds as $k => $round) {
			$roundArgv = array(
				'round' => $round['round'],
				'small_blind' => $round['small_blind'],
				'big_blind' => $round['big_blind'],
				'limit_not_blind' => $round['variety'] == 'limits-round',
				'ante' => $round['ante'],
				'duration' => $round['duration'],
				'description' => nl2br(htmlspecialchars($round['description'])),
				'custom' => ($round['description'] != ''),
				'has_break' => $round['break_id'] != null,
				'break_duration' => $round['break_duration'],
				'url.edit' => $this->linkas('event#edit', array(
					'event_id' => $eventId,
					'path' => $this->getUriPath($dayId),
					'type' => 'round',
					'id' => $round['id']
				), $this->getUriFilter(array('master' => 'round', 'display' => 'full'), true))
			);
			if ($round['is_hidden'] == 2) { // round not started
				$roundArgv += array(
					'started_on' => '',
					'moveDay' => '',
					'url.delete' => $this->linkas('event#delete', array(
						'event_id' => $eventId,
						'path' => $this->getUriPath($dayId),
						'type' => 'round',
						'id' => $round['id']
					), $this->getUriFilter(array('master' => 'round'), true)),
				);
				if (($k <= $lastStartedRoundNr + 1)) {
					$roundArgv['url.start'] = $this->linkas('event#save', array(
						'event_id' => $eventId,
						'path' => $this->getUriPath($dayId),
						'type' => 'round',
						'id' => 'start.' . $round['id']
					), $this->getUriFilter(array('master' => 'round'), true));
				}
				if (count($moveDays)) {
					$roundArgv['move'] = $lastStartedRoundNr === NULL
						? $k == 0
						: $k == $lastStartedRoundNr + 1;
					if ($roundArgv['move']) {
						foreach ($moveDays as $moveDay) {
							$roundArgv['moveDay'] .= $tpl->parse('controls:round.item.moveDay', array(
								'name' => htmlspecialchars($moveDay['name']),
								'url.move' => $this->linkas('event#save', array(
									'event_id' => $eventId,
									'path' => $this->getUriPath($dayId),
									'type' => 'round',
									'id' => 'move.' . $round['id'] . '.' . $dayId. '.' . $moveDay['id']
								), $this->getUriFilter(array('master' => 'round'), true))
							));
						}
					}
				}
			} else { // round started
				$roundArgv += array(
					'started_on' => $text->ago($round['created_on']),
				);
				if (($k == $lastStartedRoundNr)) {
					$roundArgv['url.stop'] = $this->linkas('event#save', array(
						'event_id' => $eventId,
						'path' => $this->getUriPath($dayId),
						'type' => 'round',
						'id' => 'stop.' . $round['id']
					), $this->getUriFilter(array('master' => 'round'), true));
					if ($round['break_id']) {
						if ($round['break_is_hidden'] == 2) { // break not started
							$roundArgv['url.start_break'] = $this->linkas('event#save', array(
								'event_id' => $eventId,
								'path' => $this->getUriPath($dayId),
								'type' => 'round',
								'id' => 'start.' . $round['break_id']
							), $this->getUriFilter(array('master' => 'round'), true));
						} else { // break started
							$roundArgv['url.stop_break'] = $this->linkas('event#save', array(
								'event_id' => $eventId,
								'path' => $this->getUriPath($dayId),
								'type' => 'round',
								'id' => 'stop.' . $round['break_id']
							), $this->getUriFilter(array('master' => 'round'), true));
							unset($roundArgv['url.stop']);
						}
					}
				}
				if ($roundArgv['started_on'] == '' || $round['created_on'] > time()) {
					$roundArgv['started_on'] = moon::locale()->gmdatef($round['created_on'] + $eventData['tzOffset'], 'Reporting') . ' ' . $eventData['tzName'];
				}
			}
			$tRounds .= $tpl->parse('controls:round.item', $roundArgv);
		}
		return $tpl->parse('controls:round.round_list', array(
			'rounds' => $tRounds
		));
	}

	private function saveRoundAndBreak($data)
	{
		if (NULL == ($prereq = $this->helperSaveCheckPrerequisites($data['day_id'], $data['round_id'], 'round'))) {
			return FALSE;
		}
		list (
			$location,
			$entry
		) = $prereq;

		$userId = intval(moon::user()->get_user_id());

		$rtf = $this->object('rtf');
		$rtf->setInstance($this->get_var('rtf') . ':4');
		list(, $description_compiled) = $rtf->parseText($entry['id'], $data['description']);

		$saveDataRound = $serData = array(
			'round' => $data['round'],
			'duration' => $data['duration'],
			'ante' => $data['ante'],
		);
		$saveDataRound['description'] = $data['description'];
		$serData['description'] = $description_compiled;
		if (!empty($data['limit_not_blind'])) {
			$saveDataRound['variety']     = $serData['variety']     = 'limits-round';
			$saveDataRound['small_blind'] = $serData['small_blind'] = $data['small_limit'];
			$saveDataRound['big_blind']   = $serData['big_blind']   = $data['big_limit'];
		} else {
			$saveDataRound['variety']     = $serData['variety']     = 'blinds-round';
			$saveDataRound['small_blind'] = $serData['small_blind'] = $data['small_blind'];
			$saveDataRound['big_blind']   = $serData['big_blind']   = $data['big_blind'];
		}
		$saveDataLog = array(
			'type' => 'round',
			'contents' => serialize($serData),
			'is_hidden' => $entry['id'] == NULL
				? 2
				: $entry['is_hidden']
		);
		$this->helperSaveAssignCommonLogAttrs($saveDataLog, $userId, $entry, $data, $location);
		
		if ($entry['id'] != NULL) { // update
			$this->db->update($saveDataRound, $this->table('tRounds'), array(
				'id' => $entry['id']
			));
			$this->db->update($saveDataLog, $this->table('Log'), array(
				'id' => $entry['id'],
				'type' => 'round'
			));
		} else { // create
			$this->db->insert($saveDataRound, $this->table('tRounds'));
			if (!($entry['id'] = $saveDataLog['id'] = $this->db->insert_id())) {
				return;
			}
			$this->db->insert($saveDataLog, $this->table('Log'));
		}

		$this->saveRoundAndBreakAttachBreak($entry['id'], $userId, $location, $data);

		return $entry['id'];
	}

	// sigh... hope it doesn't break too much
	private function saveRoundAndBreakAttachBreak($roundId, $userId, $location, $data)
	{
		$entry = $this->getEditableData($roundId, $location['event_id']);
		if (NULL == ($prereq = $this->helperSaveCheckPrerequisites($data['day_id'], $entry['break_id'], 'round'))) {
			return FALSE;
		}
		list (,	$breakEntry) = $prereq;

		if (!empty($data['has_break'])) { // has break
			// should be alsmost a clone of saveRoundAndBreak() saving bit
			$saveDataBreak = $serData = array(
				'round' => $data['round'],
				'duration' => $data['break_duration'],
				'variety' => 'break'
			);
			$saveDataLog = array(
				'type' => 'round',
				'contents' => serialize($serData),
				'is_hidden' => $breakEntry['id'] == NULL
					? 2
					: $breakEntry['is_hidden']
			);
			$this->helperSaveAssignCommonLogAttrs($saveDataLog, $userId, $breakEntry, $data, $location);

			if ($breakEntry['id'] != NULL) { // update
				$this->db->update($saveDataBreak, $this->table('tRounds'), array(
					'id' => $breakEntry['id']
				));
				$this->db->update($saveDataLog, $this->table('Log'), array(
					'id' => $breakEntry['id'],
					'type' => 'round'
				));
			} else { // create
				$this->db->insert($saveDataBreak, $this->table('tRounds'));
				if (!($breakEntry['id'] = $saveDataLog['id'] = $this->db->insert_id())) {
					return;
				}
				$this->db->insert($saveDataLog, $this->table('Log'));
			}
		} else { // no break
			if (null != $breakEntry['id']) { // delete
				$this->helperDeleteDbDelete($breakEntry['id'], 'round', 'tRounds');
			}
		}
	}

	private function saveStart($argv)
	{
		if (!$this->lrep()->instTools()->isAllowed('writeContent')) {
			return ;
		}
		$this->db->update(array(
			'is_hidden' => 0,
			'created_on' => time(),
			'updated_on' => time(),
		), $this->table('Log'), array(
			'id' => $argv['round_id'],
			'type' => 'round'
		));
	}

	private function saveStop($argv)
	{
		if (!$this->lrep()->instTools()->isAllowed('writeContent')) {
			return ;
		}
		$this->db->update(array(
			'is_hidden' => 2,
			'updated_on' => time(),
		), $this->table('Log'), array(
			'id' => $argv['round_id'],
			'type' => 'round'
		));
	}

	private function saveMove($argv)
	{
		if (!$this->lrep()->instTools()->isAllowed('writeContent')) {
			return ;
		}
		$sameEvent = $this->db->single_query_assoc('
			SELECT d1.event_id FROM ' . $this->table('Days') . ' d1
			INNER JOIN ' . $this->table('Days') . ' d2
				ON d1.event_id=d2.event_id
			WHERE d1.id=' . filter_var($argv['day_from_id'], FILTER_VALIDATE_INT) . '
				AND d2.id=' . filter_var($argv['day_to_id'], FILTER_VALIDATE_INT) . '
		');
		if (empty($sameEvent)) {
			return ;
		}
		$roundCandidates = array_reverse($this->getRounds($argv['day_from_id'])); // descending nr. order
		$rounds = array();
		foreach ($roundCandidates as $round) {
			$rounds[] = $round['id'];
			if ($round['break_id'])
				$rounds[] = $round['break_id'];
			if ($round['id'] == $argv['round_id']) {
				break;
			}
		}
		if (0 == count($rounds)) {
			return ;
		}
		$this->db->query('
			UPDATE ' . $this->table('Log') . '
			SET day_id=' . filter_var($argv['day_to_id'], FILTER_VALIDATE_INT) . ', updated_on=' . time() . '
			WHERE id IN(' . implode(',', $rounds) . ') AND type="round"
		');
	}

	private function delete($roundId)
	{
		if (NULL == ($prereq = $this->helperDeleteCheckPrerequisites($roundId, 'round'))) {
			return FALSE;
		}
		list ($location) = $prereq;
		$entry = $this->getEditableData($roundId, $location['event_id']);

		// break
		if (!empty($entry['break_id'])) {
			$this->helperDeleteDbDelete($entry['break_id'], 'round', 'tRounds');
		}

		$this->helperDeleteDbDelete($roundId, 'round', 'tRounds', TRUE);
	}
}
