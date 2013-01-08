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
class livereporting_event_event extends livereporting_event_pylon
{
	/** 
	 * @todo decouple 'save-event' in html and php !! terrible legacy mix (partially done)
	 */
	protected function synthEvent($event, $argv)
	{
		switch ($event) {
			case 'save-misc': // "Actions for this event: Miscellaneous"
				$data = $this->helperEventGetData(array(
					'event_id', 'prizepool', 'chipspool', 'players_total', 'players_left', 'buyin', 'fee', 'rebuy', 'addon'
				));
				if (FALSE === $this->saveMisc($data)) {
					moon::page()->page404();
				}
				$this->redirect('event#view', array(
					'event_id' => filter_var($argv['event_id'], FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE),
					'path' => $this->getUriPath()
				), $this->getUriFilter((!empty($_POST['master'])
						? array('master' => 'misc')
						: NULL
				), TRUE));
				moon_close();
				exit;
			case 'save-sbplayers': // "Players left"
				$data = $this->helperEventGetData(array(
					'players_total', 'players_left'
				));				
				$data['event_id'] = $this->requestArgv('event_id');
				if (FALSE !== $this->savePlayersCnt($data)) {
					echo json_encode(array('status'=>0));
				}
				moon_close();
				exit;
			case 'save-complete': // see days for code ident
			case 'save-resume':
				if (FALSE === $this->saveEventStateChanged(array(
					'tournament_id' => $argv['tournament_id'],
					'event_id' => $argv['event_id'],
					'state' => $argv['uri']['argv'][2]
				))) {
					moon::page()->page404();
				}
				$this->redirect('event#view', array(
					'event_id' => filter_var($argv['event_id'], FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE),
					'path' => $this->getUriPath($this->requestArgv('day_id'))
				), $this->getUriFilter(NULL, TRUE));
				moon_close();
				exit;
			case 'save-event': 
				$add = (!empty($_POST['master'])
						? array('master' => 'event')
						: NULL
				);
				if (isset($_POST['action_save_prizepool'])) {
					$data = $this->helperEventGetData(array(
						'event_id', 'place', 'prize', 'new_place', 'new_prize'
					));
					if (FALSE === $this->savePayouts($data)) {
						moon::page()->page404();
					}
					if (is_array($add)) {
						$add['submaster'] = 'prizepool';
					}
				} elseif(isset($_POST['action_delete_prizepool'])) {
					$data = $this->helperEventGetData(array(
						'event_id', 'delete'
					));
					if (FALSE === $this->deletePayouts($data)) {
						moon::page()->page404();
					}
					if (is_array($add)) {
						$add['submaster'] = 'prizepool';
					}
				} elseif (isset($_POST['action_save_winners'])) {
					$data = $this->helperEventGetData(array(
						'event_id', 'winner_list'
					));
					if (isset($_POST['winner_list'])) {
						$data['winner_list'] = $_POST['winner_list'];
					}
					$winners = array();
					$data['winner_list'] = explode("\n", $data['winner_list']);
					foreach ($data['winner_list'] as $k => $row) {
						$row = trim($row);
						if ($row == '-' || $row == '') {
							$row = NULL;
						}
						$winners[$k] = $row;
					}
					$data['winner_list'] = $winners;
					if (FALSE === $this->savePlaces($data)) {
						moon::page()->page404();
					}
					if (is_array($add)) {
						$add['submaster'] = 'winners';
					}
				}
				$this->redirect('event#view', array(
					'event_id' => filter_var($argv['event_id'], FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE),
					'path' => $this->getUriPath()
				), $this->getUriFilter($add, TRUE));
				moon_close();
				exit;
			case 'load-all':
				$this->forget();
				if (FALSE !== ($places = $this->renderSubcontrolPlaces($argv['event_id']))
				 && FALSE !== ($winners = $this->renderSubcontrolWinners($argv['event_id']))) {
					echo json_encode(array(
						'status' => 0,
						'pdata' => $places,
						'wdata' => $winners
					));
				}
				moon_close();
				exit;
			default:
				moon::page()->page404();
		}
	}

	protected function render($data, $argv)
	{
		if ($argv['variation'] == 'logControl') {
			return $this->renderControl(array_merge($data, array(
				'unhide'  => (!empty($_GET['master']) && $_GET['master'] == 'event'),
				'unhidem' => (!empty($_GET['master']) && $_GET['master'] == 'misc'),
				'active_section'  => !empty($_GET['submaster'])
					? $_GET['submaster']
					: NULL,
			)));
		} elseif ($argv['variation'] == 'logTab') {
			return $this->renderLogTab($data);
		}
	}

	private function renderLogTab($data) // payouts
	{
		$tpl = $this->load_template();
		$path = $this->getUriPath();
		$lrep = $this->lrep();
		$page = moon::page();
		$eventInfo = $lrep->instEventModel('_src_event')->getEventData($this->requestArgv('event_id'));
		$showProfileLinks = $page->get_global('adminView') && $lrep->instTools()->isAllowed('writeContent');
		$playersPokerUrl = $this->linkas('players.poker#');

		$payouts = $this->getPayouts($data['event_id'], TRUE);
		$sponsorIds = array();
		foreach ($payouts as $row) {
			if (!empty($row['player.sponsor_id'])) {
				$sponsorIds[] = $row['player.sponsor_id'];
			}
		}
		$sponsors = $lrep->instEventModel('_src_event')->getSponsorsById($sponsorIds);

		$mainArgv = array(
			'entries' => '',
			'currency' => $eventInfo['currency']
		);

		foreach ($payouts as $k => $payout) {
			$evArgv = array(
				'evenodd' => $k % 2
					? 'even'
					: 'odd',
				'place' => $payout['nr'],
				'url'   => $showProfileLinks && $payout['player.id']
					? $this->linkas('event#edit', array(
							'event_id' => $data['event_id'],
							'path' => $path,
							'type' => 'profile',
							'id' => $payout['player.id']
						), $this->getUriFilter(NULL, TRUE))
					: (!empty($payout['player.uri']) && $payout['player.id']
						? $playersPokerUrl . $payout['player.uri'] . '/'
						: ''),
				'wins' => number_format($payout['sum']),
				'name' => htmlspecialchars($payout['player.name']),
			);
			if ($payout['player.id'] != NULL && isset($sponsors[$payout['player.sponsor_id']])) {
				$sponsor = $sponsors[$payout['player.sponsor_id']];
				$evArgv += array(
					'sponsor' => $lrep->instTools()->helperPlayerStatus(!empty($payout['player.status'])
							? $payout['player.status']
							: '',
						$sponsor['name']),
					'sponsorimg' => !empty($sponsor['favicon'])
						? ($sponsor['id'] > 0 
							? img('rw', $sponsor['id'], $sponsor['favicon'])
							: $sponsor['favicon'])
						: null,
				);
				if ($sponsor['is_hidden'] == '0' && !empty($sponsor['alias'])) {
					$evArgv['sponsorurl'] = '/' . $sponsor['alias'] . '/';
				}
			}
			$mainArgv['entries'] .= $tpl->parse('logTab:entries.item', $evArgv);
		}		

		return $tpl->parse('logTab:main', $mainArgv);
	}

	function renderControl($argv)
	{
		$tpl= $this->load_template();

		$controlsArgv = array(
			'ce.unhide' => !empty($argv['unhide']),
			'cm.unhide' => !empty($argv['unhidem']),
			'ce.save_event' => $this->parent->my('fullname') . '#save-event',
			'cm.save_event' => $this->parent->my('fullname') . '#save-misc',
			'ce.event_id' => $argv['event_id'],
			'ce.day_id' => $argv['day_id'],
		);

		if (!empty($argv['active_section']) && in_array($argv['active_section'], array('prizepool', 'winners'))) {
			$controlsArgv['ce.sect_' . $argv['active_section'] . '_active'] = TRUE;
		}
		
		$controlsArgv['ce.url.ajax_load'] = htmlspecialchars_decode($this->linkas('event#load', array(
			'event_id' => $argv['event_id'],
			'path' => $this->getUriPath(0),
			'type' => 'event',
			'id' => 'all'
		), $this->getUriFilter(NULL, TRUE)));

		$controlsArgv['ce.sc.places'] = '';
		$controlsArgv['ce.sc.winners'] = '';
		if (!empty($argv['unhide'])) {
			$controlsArgv['ce.sc.places'] = $this->renderSubcontrolPlaces($argv['event_id']);
			$controlsArgv['ce.sc.winners'] = $this->renderSubcontrolWinners($argv['event_id']);
		}
		$controlsArgv['ce.master'] = 'event';
		$controlsArgv['cm.master'] = 'misc';

		$eventInfo = $this->lrep()->instEventModel('_src_event')->getEventData($argv['event_id']);
		$controlsArgv['cm.players_left'] = $eventInfo['pleft'];
		$controlsArgv['cm.players_total'] = $eventInfo['ptotal'];
		$controlsArgv['cm.chipspool'] = $eventInfo['cp'];
		$controlsArgv['cm.prizepool'] = $eventInfo['ppool'];
		$controlsArgv['cm.fee'] = $eventInfo['fee'];
		$controlsArgv['cm.buyin'] = $eventInfo['buyin'];
		$controlsArgv['cm.rebuy'] = $eventInfo['rebuy'];
		$controlsArgv['cm.addon'] = $eventInfo['addon'];

		return $tpl->parse('controls:event', $controlsArgv);
	}

	private function renderSubcontrolPlaces($eventId)
	{
		if (NULL == ($prereq = $this->helperSaveCheckPrerequisitesLocationByEvent($eventId))) {
			return FALSE;
		}

		$lrep = $this->lrep();
		$eventInfo = $lrep->instEventModel('_src_event')->getEventData($this->requestArgv('event_id'));
		$tpl = $this->load_template();

		$subcontrolArgv = array(
			'place_list' => '',
			'rq_we_pl_newplace' => json_encode($tpl->parse('controls:event.place_list.newitem', array())),
		);
		$payouts = $this->getPayouts($eventId);

		$tpl->save_parsed('controls:event.place_list.item', array(
			'prize' => $lrep->instTools()->helperCurrencyWrite('{prize}' ,$eventInfo['currency'])
		));
		foreach ($payouts as $payout) {
			$subcontrolArgv['place_list'] .= $tpl->parse('controls:event.place_list.item', array(
				'id' => $payout['id'],
				'place' => $payout['nr'],
				'placev' => $payout['nr'] . (isset($payout['nre'])
					? '-' . $payout['nre']
					: ''),
				'prizev' => $payout['sum'],
				'prize' => number_format($payout['sum']),
				'repeated' => $payout['repeated'],
				'name' => $payout['player.name']
					? htmlspecialchars($payout['player.name'])
					: '-'
			));
		}

		return $tpl->parse('controls:event.place_list', $subcontrolArgv);
	}

	private function renderSubcontrolWinners($eventId)
	{
		if (NULL == ($prereq = $this->helperSaveCheckPrerequisitesLocationByEvent($eventId))) {
			return FALSE;
		}
		
		$tpl = $this->load_template();

		$payouts = $this->getPayouts($eventId);
		$subcontrolArgv = array(
			'winners' => '',
			'rows' => '',
			'height' => round(count($payouts) * 15.1)
		);

		foreach ($payouts as $payout) {
			$subcontrolArgv['rows'] .= $tpl->parse('controls:event.winner_list.row', array(
				'nr' => $payout['nr']
			));
			$subcontrolArgv['winners'] .= !empty($payout['player.name'])
				? htmlspecialchars($payout['player.name']) . "\n"
				: "-\n";
		}
		$subcontrolArgv['winners'] = trim($subcontrolArgv['winners']);

		return $tpl->parse('controls:event.winner_list', $subcontrolArgv);
	}

	private function getPayouts($eventId, $extended = FALSE)
	{
		$placesSeq = $this->db->array_query_assoc('
			SELECT * FROM ' . $this->table('Payouts') . '
			WHERE event_id=' . filter_var($eventId, FILTER_VALIDATE_INT) . '
			ORDER BY place
		');
		$winners = $this->db->array_query_assoc('
			SELECT p.id, p.place, p.name' . 
				($extended ? ', p.sponsor_id, p.status, p.is_pnews, pp.uri uri' : '') . '
			FROM ' . $this->table('Players') . ' p ' . ($extended ? '
				LEFT JOIN ' . $this->table('PlayersPoker') . ' pp
					ON pp.id=p.pp_id AND pp.hidden=0
				' : '') . '
			WHERE p.event_id=' . intval($eventId) . '
			  AND p.is_hidden=0
			  AND p.place IS NOT NULL
		', 'place');

		$payouts = array();
		foreach ($placesSeq as $placeSeq) {
			if (!empty($placeSeq['place_to'])) {
				$range = range($placeSeq['place'], $placeSeq['place_to']);
			} else {
				$range = array((int)$placeSeq['place']);
			}
			$repeated = false;
			foreach ($range as $place) {
				$payout = array(
					'id' => $placeSeq['id'],
					'nr' => $place,
					'repeated' => $repeated,
					'sum' => $placeSeq['prize'],
					'player.name' => NULL,
					'player.id' => NULL,
					'player.sponsor_id' => NULL,
					'player.status' => NULL,
					'player.is_pnews' => NULL,
					'player.uri' => NULL,
					'nre' => $placeSeq['place_to']
						? max($range)
						: NULL
				);
				if (isset($winners[$place])) {
					$payout['player.id'] = $winners[$place]['id'];
					$payout['player.name'] = $winners[$place]['name'];
				}
				if (isset($winners[$place]) && $extended) {
					$payout['player.sponsor_id'] = $winners[$place]['sponsor_id'];
					$payout['player.status']     = $winners[$place]['status'];
					$payout['player.is_pnews']   = $winners[$place]['is_pnews'];
					$payout['player.uri']        = $winners[$place]['uri'];
				}
				$payouts[] = $payout;
				$repeated = true;
			}
		}

		return $payouts;
	}
	
	// used from _bluff.exPayouts()
	public function getPayoutsSrcBluff($eventId)
	{
		return $this->getPayouts($eventId, TRUE);
	}

	// used in players/poker.php function getLastTourWinners()
	public function getPayoutsForPokerPlayers($eventId)
	{
		return $this->getPayouts($eventId, TRUE);
	}

	// used in mobile app
	public function getPayoutsForMobileapp($eventId)
	{
		return $this->getPayouts($eventId, FALSE);
	}

	private function saveBumpEvent($eventId)
	{
		$this->db->update(array(
			'updated_on' => time(),
		), $this->table('Events'), array(
			'id' => $eventId
		));
	}

	private function saveMisc($data)
	{
		if (NULL == ($prereq = $this->helperSaveCheckPrerequisitesLocationByEvent($data['event_id']))) {
			return FALSE;
		}

		$this->db->update(array(
			'prizepool' => $data['prizepool'],
			'chipspool' => $data['chipspool'],
			'players_total' => $data['players_total'],
			'players_left'  => $data['players_left'],
			'fee'  => $data['fee'],
			'buyin'  => $data['buyin'],
			'rebuy'  => $data['rebuy'],
			'addon'  => $data['addon'],
		), $this->table('Events'), array(
			'id' => $data['event_id']
		));

		$this->saveBumpEvent($data['event_id']);
	}

	private function savePlayersCnt($data)
	{
		if (NULL == ($prereq = $this->helperSaveCheckPrerequisitesLocationByEvent($data['event_id']))) {
			return FALSE;
		}
		$this->db->update(array(
			'players_total' => $data['players_total'],
			'players_left'  => $data['players_left'],
		), $this->table('Events'), array(
			'id' => $data['event_id']
		));

		$this->saveBumpEvent($data['event_id']);
	}

	private function savePlaces($data)
	{
		if (NULL == ($prereq = $this->helperSaveCheckPrerequisitesLocationByEvent($data['event_id']))) {
			return FALSE;
		}
		list (
			$location
		) = $prereq;
		$lrep = $this->lrep();
		$tools = $lrep->instTools();
		$evProfileObj = $this->instEventProfile();
		$evModel = $lrep->instEventModel('_src_event');
		
		$names = array();
		foreach ($data['winner_list'] as $playerName) {
			$names[] = $tools->helperNormalizeName($playerName);
		}

		$this->db->query('
			UPDATE ' . $this->table('Players') . '
			SET place=NULL, updated_on=' . time() . '
			WHERE event_id=' . $location['event_id'] . ' AND place IS NOT NULL
		');

		$k = 0;
		$players = $evModel->getPlayersByPlayerName($data['event_id'], $names);
		foreach ($data['winner_list'] as $playerName) {
			$playerId = $players[$k];
			$k++;

			if (null === $playerId && null != $playerName) {
				$playerId = $evProfileObj->savePlayerSrcChips($location, $playerName);
				if (null == $playerId)
					continue;
			}
			if (null === $playerId) 
				continue;

			$this->db->update(array(
				'place' => $k,
				'updated_on' => time(),
			), $this->table('Players'), array(
				'id' => $playerId
			));
		}
		
		$this->saveBumpEvent($data['event_id']);
	}

	private function savePayouts($data)
	{
		if (NULL == ($prereq = $this->helperSaveCheckPrerequisitesLocationByEvent($data['event_id']))) {
			return FALSE;
		}
		list ($location) = $prereq;

		$usedUpPlaces = array();
		if (is_array($data['place'])) {
			foreach ($data['place'] as $id => $place) {
				$prize = $data['prize'][$id];
				$place = explode('-', $place);
				$place[0] = (int)$place[0];
				$place[1] = isset($place[1])
					? (int)$place[1]
					: NULL;
				$this->db->update(
					array(
						'place'    => $place[0],
						'place_to' => $place[1],
						'prize'    => $prize,
						'updated_on' => time()
					), $this->table('Payouts'), array(
						'id' => $id
					)
				);
				if (NULL != $place[1] && $place[0] < $place[1]) {
					for ($i = $place[0]; $i <= $place[1]; $i++) {
						$usedUpPlaces[] = $i;
					}
				} else {
					$usedUpPlaces[] = $place[0];
				}
			}
		}

		foreach ($data['new_place'] as $id => $place) {
			if (empty($place)) {
				continue;
			}
			$prize = $data['new_prize'][$id];
			$place = explode('-', $place);
			$place[0] = (int)$place[0];
			$place[1] = isset($place[1])
				? (int)$place[1]
				: NULL;
			if (NULL != $place[1] && $place[0] < $place[1]) {
				for ($i = $place[0]; $i <= $place[1]; $i++) {
					if (in_array($i, $usedUpPlaces)) {
						continue(2);
					}
				}
			} else {
				if (in_array($place[0], $usedUpPlaces)) {
					continue;
				}
			}
			if (NULL != $place[1] && $place[0] < $place[1]) {
				for ($i = $place[0]; $i <= $place[1]; $i++) {
					$usedUpPlaces[] = $i;
				}
			} else {
				$usedUpPlaces[] = $place[0];
			}
			$this->db->insert(
				array(
					'tournament_id' => $location['tournament_id'],
					'event_id' => $location['event_id'],
					'place'    => $place[0],
					'place_to' => $place[1],
					'prize'    => $prize,
					'created_on' => time()
				), $this->table('Payouts')
			);
		}
	}

	private function deletePayouts($data)
	{
		if (!$this->lrep()->instTools()->isAllowed('writeContent')) {
			return FALSE;
		}

		$deleteIds = is_array($data['delete'])
			? array_keys($data['delete'])
			: array();
		foreach ($deleteIds as $k => $id) {
			$deleteIds[$k] = intval($id);
		}
		if (0 == count($deleteIds)) {
			return FALSE;
		}
		$this->db->query('
			DELETE FROM ' . $this->table('Payouts') . '
			WHERE id IN (' . implode(',', $deleteIds) . ')
		');
	}

	private function saveEventStateChanged($argv)
	{
		$lrep = $this->lrep();
		if (!$lrep->instTools()->isAllowed('writeContent')) {
			return FALSE;
		}
		
		switch ($argv['state']) {
			case 'complete':
				$this->saveEventStateSetClosed($argv);
				break;
			case 'resume':
				$this->saveEventStateSetReopened($argv);
				break;
		}
	}

	// not a notify in fact, but a plain "save"
	private function saveEventStateSetClosed($location)
	{
		$this->db->query('
			UPDATE ' . $this->table('Events') . '
			SET state=2, updated_on=' . time() . '
			WHERE id=' . intval($location['event_id']) . ' AND state=1
		');
		$this->lrep()->altLog($location['tournament_id'], $location['event_id'], 0, 'update', 'events', $location['event_id'], 'evClosed:state=2');

		// if all events completed, then skip
		$check = $this->db->single_query_assoc('
			SELECT id FROM ' . $this->table('Events') . '
			WHERE tournament_id=' . intval($location['tournament_id']) . ' AND is_live=1
				AND state!=2
			LIMIT 1
		');
		if (!empty($check)) {
			return ;
		}

		$this->db->query('
			UPDATE ' . $this->table('Tournaments') . '
			SET state=2, updated_on=' . time() . '
			WHERE id=' . intval($location['tournament_id']) . '
		');
		$this->lrep()->altLog($location['tournament_id'], 0, 0, 'update', 'tournaments', $location['tournament_id'], 'evClosed:state=2');
	}

	// not a notify in fact, but a plain "save"
	private function saveEventStateSetReopened($location)
	{
		$this->db->query('
			UPDATE ' . $this->table('Events') . '
			SET state=1, updated_on=' . time() . '
			WHERE id=' . intval($location['event_id']) . ' AND state=2
		');
		$this->lrep()->altLog($location['tournament_id'], $location['event_id'], 0, 'update', 'events', $location['event_id'], 'evReopened:state=1');
		
		// if at least one event is started
		$check = $this->db->single_query_assoc('
			SELECT id FROM ' . $this->table('Events') . '
			WHERE tournament_id=' . intval($location['tournament_id']) . ' AND is_live=1
				AND state=1
			LIMIT 1
		');
		if (empty($check)) {
			return ;
		}

		$this->db->query('
			UPDATE ' . $this->table('Tournaments') . '
			SET state=1, updated_on=' . time() . '
			WHERE id=' . intval($location['tournament_id']) . '
		');
		$this->lrep()->altLog($location['tournament_id'], 0, 0, 'update', 'tournaments', $location['tournament_id'], 'evReopened:state=1');
	}
}
