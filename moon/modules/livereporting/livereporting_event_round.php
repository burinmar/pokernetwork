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
					'round_id', 'round', 'day_id', 'duration', 'format', 'limit_not_blind', 'small_blind', 'big_blind', 'small_limit', 'big_limit', 'ante', 'description', 'datetime_options'
				));
				
				$roundId = $this->save($data);
				$this->redirectAfterSave($roundId, 'round', array(
					'add' => (!empty($_POST['master'])
						? array('master' => 'round')
						: NULL
					),
					'noanchor' => !empty($_POST['master'])
				));
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
					'data' => $this->renderSubcontrol($argv['event_id'], $argv['uri']['argv'][2])
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
			return $this->renderControl(array_merge($data, array(
				'unhide' => (!empty($_GET['master']) && $_GET['master'] == 'round')
			)));
		}
		
		$lrep = $this->lrep();
		$page = moon::page();
		$tpl = $this->load_template();
		
		$rArgv = $this->helperRenderCommonArgv($data, $argv, $tpl);

		$rArgv += array(
			'round' => $data['contents']['round'],
			'ante' => $data['contents']['ante'],
			'custom' => !empty($data['contents']['desciption']),
			'description' => $data['contents']['desciption'],
			'limit_not_blind' => !empty($data['contents']['limit_not_blind']),
			'small_blind' => @$data['contents']['small_blind'],
			'big_blind' => @$data['contents']['big_blind'],
		);

		if ($argv['variation'] == 'logEntry') {
			$rArgv += array(
				'is_hidden'  => $data['is_hidden']
			);
			if (!empty($rArgv['show_controls'])) {
				unset($rArgv['url.delete']);
				$eventInfo = $lrep->instEventModel('_src_event')->getEventData($data['event_id']);
				$rArgv += array(
					'show_fullcontrols' => $eventInfo['synced'] == '0',
					'url.stop' => $this->linkas('event#save', array(
						'event_id' => $data['event_id'],
						'path' => $this->getUriPath(),
						'type' => 'round',
						'id' => 'stop.' . $data['id']
					), $this->getUriFilter(array('master' => 'round'), true)),
				);
			}
			return $tpl->parse('logEntry:round', $rArgv);
		} elseif ($argv['variation'] == 'individual') {
			if ($argv['action'] == 'view') {
				$page->page404();
			}
			if ($rArgv['show_controls']) {
				$entry = $this->getEditableData($data['id'], $data['event_id']);
				$rArgv['control'] = $this->renderControl(array(
					'round_id' => $entry['id'],
					'keep_old_dt' => true,
					'day_id'  => $entry['day_id'],
					'event_id'  => $entry['event_id'],
					'post_id' => $entry['id'],
					'created_on' => $entry['created_on'],
					'tzName' => $data['tzName'],
					'tzOffset' => $data['tzOffset'],
					'unhide' => true,
					'master' => (isset($_GET['master']) && $_GET['master'] == 'round'),
					'show_single_entry' => 'true',
					'round' => $entry['round'],
					'big_blind' => $entry['big_blind'],
					'small_blind' => $entry['small_blind'],
					'limit_not_blind' => $entry['limit_not_blind'],
					'ante' => $entry['ante'],
					'description' => $entry['description'],
					'duration' => $entry['duration'],
				));
			}
			return $tpl->parse('entry:round', $rArgv);
		}
	}

	private function getEditableData($id, $eventId)
	{
		$entry = $this->db->single_query_assoc('
			SELECT l.tournament_id, l.event_id, l.day_id, l.created_on, l.updated_on, l.is_hidden, d.*
			FROM ' . $this->table('Log') . ' l
			INNER JOIN ' . $this->table('tRounds') . ' d
			ON l.id=d.id
			WHERE l.id=' . filter_var($id, FILTER_VALIDATE_INT) . ' AND l.type="round"
				AND l.event_id=' . filter_var($eventId, FILTER_VALIDATE_INT));
		if (empty($entry)) {
			return NULL;
		}
		return $entry;
	}
	
	// in ascending(!) order
	private function getRounds($dayId)
	{
		return $this->db->array_query_assoc('
			SELECT l.is_hidden, l.created_on, r.* FROM ' . $this->table('Log') . ' l
			INNER JOIN ' . $this->table('tRounds') . ' r
				ON r.id=l.id
			WHERE l.day_id=' . intval($dayId) . ' AND l.type="round"
			ORDER BY r.round ASC
		');
	}

	private function renderControl($argv)
	{
		if (empty($argv['day_id'])) {
			return ;
		}
		$lrep = $this->lrep();

		$controlsArgv = array(
			'cr.save_event' => $this->parent->my('fullname') . '#save-round',
			'cr.round_id' => isset($argv['round_id'])
				? intval($argv['round_id'])
				: '',
			'cr.day_id' => $argv['day_id'],
			'cr.unhide' => !empty($argv['unhide']),
		);
		foreach (array('round', 'duration', 'small_blind', 'big_blind', 'limit_not_blind', 'ante', 'description') as $cname) {
			$controlsArgv['cr.' . $cname] = isset($argv[$cname])
				? htmlspecialchars($argv[$cname])
				: '';
		}
		
		if (empty($argv['show_single_entry'])) { // list control
			$controlsArgv += array(
				'cr.url.ajax_rounds' => htmlspecialchars_decode($this->linkas('event#load', array(
					'event_id' => $argv['event_id'],
					'path' => $this->getUriPath(0),
					'type' => 'round',
					'id' => $argv['day_id']
				), $this->getUriFilter(NULL, TRUE))),
				'rounds' => !empty($argv['unhide'])
					? $this->renderSubcontrol($argv['event_id'], $argv['day_id'])
					: '',
				'cr.master' => 'round', // show list after save
			);
		} else {
			list(
				$controlsArgv['cr.datetime_options'],
				$controlsArgv['cr.custom_datetime'],
				$controlsArgv['cr.custom_tz']
			) = $this->helperRenderControlDatetime($argv, $lrep);
			$controlsArgv['cr.show_single_entry'] = true;
			if (!empty($argv['master'])) {
				$controlsArgv['cr.master'] = 'round'; // show list after save
			}
		}
		
		return $this->load_template()
			->parse('controls:round', $controlsArgv);
	}

	private function renderSubcontrol($eventId, $dayId)
	{
		$rounds = $this->getRounds($dayId);
		$lrep = $this->lrep();
		$text = moon::shared('text');
		$tpl  = $this->load_template();
		$eventData = $lrep->instEventModel('_src_event')->getEventData($eventId);
		$lastStartedRound = NULL;
		foreach ($rounds as $k => $round) {
			if ($round['is_hidden'] == 0) {
				$lastStartedRound = $k;
			}
		}

		$moveDayCandidates = array_reverse($lrep->instEventModel('_src_event')->getDaysData($eventId), true);
		$moveDayName = preg_replace('~[a-z]~i', '', $moveDayCandidates[$dayId]['name']);
		$moveDays = array();
		foreach ($moveDayCandidates as $moveDayCandidate) {
			if ($moveDayCandidate['id'] == $dayId || strpos($moveDayCandidate['name'], $moveDayName) === 0) {
				break;
			}
			$moveDays[] = $moveDayCandidate;
		}
		$moveDays = array_reverse($moveDays);

		$tRounds = '';
		foreach ($rounds as $k => $round) {
			$rounderArgv = array(
				'round' => $round['round'],
				'small_blind' => $round['small_blind'],
				'big_blind' => $round['big_blind'],
				'limit_not_blind' => $round['limit_not_blind'],
				'ante' => $round['ante'],
				'duration' => $round['duration'],
				'description' => nl2br(htmlspecialchars($round['description'])),
				'custom' => ($round['description'] != ''),
				'url.delete' => $this->linkas('event#delete', array(
					'event_id' => $eventId,
					'path' => $this->getUriPath($dayId),
					'type' => 'round',
					'id' => $round['id']
				), $this->getUriFilter(array('master' => 'round'), true)),
				'url.edit' => $this->linkas('event#edit', array(
					'event_id' => $eventId,
					'path' => $this->getUriPath($dayId),
					'type' => 'round',
					'id' => $round['id']
				), $this->getUriFilter(array('master' => 'round'), true))
			);
			if ($round['is_hidden'] == 2) {
				$rounderArgv += array(
					'started_on' => '',
					'url.start' => $this->linkas('event#save', array(
						'event_id' => $eventId,
						'path' => $this->getUriPath($dayId),
						'type' => 'round',
						'id' => 'start.' . $round['id']
					), $this->getUriFilter(array('master' => 'round'), true)),
					'url.stop' => '',
					'start' => ($k <= $lastStartedRound + 1),
					'stop' => false,
					'move' => false,
					'moveDay' => '',
				);
				if (count($moveDays)) {
					$rounderArgv['move'] = $lastStartedRound === NULL
						? $k == 0
						: $k == $lastStartedRound + 1;
					if ($rounderArgv['move']) {
						foreach ($moveDays as $moveDay) {
							$rounderArgv['moveDay'] .= $tpl->parse('controls:round.item.moveDay', array(
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
			} else {
				$rounderArgv += array(
					'started_on' => $text->ago($round['created_on']),
					'url.start' => '',
					'url.stop' => $this->linkas('event#save', array(
						'event_id' => $eventId,
						'path' => $this->getUriPath($dayId),
						'type' => 'round',
						'id' => 'stop.' . $round['id']
					), $this->getUriFilter(array('master' => 'round'), true)),
					'start' => false,
					'stop' => true,
					'move' => false,
					'moveDay' => '',
				);
				if ($rounderArgv['started_on'] == '' || $round['created_on'] > time()) {
					$rounderArgv['started_on'] = moon::locale()->gmdatef($round['created_on'] + $eventData['tzOffset'], 'Reporting') . ' ' . $eventData['tzName'];
				}
			}
			$tRounds .= $tpl->parse('controls:round.item', $rounderArgv);
		}
		return $tpl->parse('controls:round.round_list', array(
			'rounds' => $tRounds
		));
	}

	private function save($data)
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
		list(,$description_compiled) = $rtf->parseText($entry['id'], $data['description']);

		$saveDataRound = array(
			'round' => $data['round'],
			'duration' => $data['duration'],
			'limit_not_blind' => !empty($data['limit_not_blind']),
			'ante' => $data['ante'],
			'description' => $data['description']
		);
		$serData = array(
			'round' => $data['round'],
			'duration' => $data['duration'],
			'limit_not_blind' => !empty($data['limit_not_blind']),
			'ante' => $data['ante'],
			'desciption' => $description_compiled 
		);
		if (!empty($data['limit_not_blind'])) {
			$serData['small_blind'] = $data['small_limit'];
			$serData['big_blind'] = $data['big_limit'];
			$saveDataRound['small_blind'] = $data['small_limit'];
			$saveDataRound['big_blind'] = $data['big_limit'];
		} else {
			$serData['small_blind'] = $data['small_blind'];
			$serData['big_blind'] = $data['big_blind'];
			$saveDataRound['small_blind'] = $data['small_blind'];
			$saveDataRound['big_blind'] = $data['big_blind'];
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
			// for sync: unhide hidden rounds on save
			$this->db->update(array(
				'is_hidden' => 0
			), $this->table('Log'), array(
				'id' => $entry['id'],
				'type' => 'round',
				'is_hidden' => 1
			));
		} else { // create
			$this->db->insert($saveDataRound, $this->table('tRounds'));
			if (!($entry['id'] = $saveDataLog['id'] = $this->db->insert_id())) {
				return;
			}
			$this->db->insert($saveDataLog, $this->table('Log'));
		}

		return $entry['id'];
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
		
		$this->helperDeleteDbDelete($roundId, 'round', 'tRounds', TRUE);
	}
}