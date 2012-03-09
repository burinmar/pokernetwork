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
	private $listPageBy = 100;

	/** 
	 * @todo decouple 'save-event' in html and php !! terrible legacy mix 
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
					'event_id' => getInteger($argv['event_id']),
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
					'event_id' => getInteger($argv['event_id']),
					'path' => $this->getUriPath($this->requestArgv('day_id'))
				), $this->getUriFilter(NULL, TRUE));
				moon_close();
				exit;
			case 'save-event': 
				$form = $this->form();
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
				} elseif (isset($_POST['action_import_new_plist'])) {
					$this->forget();
					header('content-type: text/plain; charset=utf-8');
					$data = $this->helperEventGetData(array(
						'event_id', 'day_id', 'new_players_list'
					));
					echo json_encode( // 404s itself
						  $this->renderPlayersSavePreviewWXML($data)
					);
					moon_close();
					exit;
				} elseif (isset($_POST['action_save_new_plist']) || (isset($_POST['action_preview_new_plist']))) {
					$data = $this->helperEventGetData(array(
						'event_id', 'day_id', 'new_players_list'
					));
					$data['new_players_list'] = str_replace("\r", '', $data['new_players_list']);
					$npList = array();
					$dpList = array();
					$rows = explode("\n", $data['new_players_list']);
					foreach ($rows as $row) {
						$row = preg_split("~\t|;|,~", $row);
						$newRow = array();
						foreach ($row as $cell) {
							$newRow[] = trim($cell);
						}
						if (strlen($newRow[0])) {
							if ($newRow[0][0] != '#') {
								$npList[] = $newRow;
							} else {
								$newRow[0] = substr($newRow[0], 1);
								$dpList[] = $newRow;
							}
						}
					}
					$data['new_players_list'] = $npList;
					$data['del_players_list'] = $dpList;
					if (is_array($add)) {
						$add['submaster'] = 'list';
					}
					if (isset($_POST['action_save_new_plist'])) {
						if (FALSE === $this->savePlayers($data)) {
							moon::page()->page404();
						}
					} else { // if isset action_preview_new_plist
						$this->forget();
						header('content-type: text/plain; charset=utf-8');
						if (FALSE !== ($preview = $this->renderPlayersSavePreview($data))) {
							echo json_encode(array(
								'preview' => $preview,
							));
						}
						moon_close();
						exit;
					}
				}
				$this->redirect('event#view', array(
					'event_id' => getInteger($argv['event_id']),
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
			case 'load-plist':
				$this->forget();
				if (FALSE !== ($data = $this->renderPlayersData($argv['event_id']))) {
					echo json_encode(array(
						'status' => 0,
						'data' => $data,
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
			return $this->renderLogTab($data, $argv);
		}
	}

	private function renderLogTab($data, $argv) // payouts
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
		$lrep = $this->lrep();

		$cUriPath = $this->getUriPath();
		$cUriPathZ = $this->getUriPath(0);
		$controlsArgv = array(
			'ce.unhide' => !empty($argv['unhide']),
			'cm.unhide' => !empty($argv['unhidem']),
			'ce.save_event' => $this->parent->my('fullname') . '#save-event',
			'cm.save_event' => $this->parent->my('fullname') . '#save-misc',
			'ce.event_id' => $argv['event_id'],
			'ce.day_id' => $argv['day_id'],
			'ce.sect_list_paging' => array(),
			'ce.list.page_by' => $this->listPageBy,
			'sponsor' => '',
			'status' => ''
		);

		if (!empty($argv['active_section']) && in_array($argv['active_section'], array('prizepool', 'winners', 'list'))) {
			$controlsArgv['ce.sect_' . $argv['active_section'] . '_active'] = TRUE;
		}
		
		foreach (array(
			'nopl'
		) as $cname) {
			$controlsArgv['ce.' . $cname] = isset($argv[$cname])
				? htmlspecialchars($argv[$cname])
				: '';
		}
		$controlsArgv['ce.url.ajax_load'] = htmlspecialchars_decode($this->linkas('event#load', array(
			'event_id' => $argv['event_id'],
			'path' => $cUriPathZ,
			'type' => 'event',
			'id' => 'all'
		), $this->getUriFilter(NULL, TRUE)));
		$controlsArgv['ce.url.ajax_list_load'] = htmlspecialchars_decode($this->linkas('event#load', array(
			'event_id' => $argv['event_id'],
			'path' => $cUriPathZ,
			'type' => 'event',
			'id' => 'plist'
		), $this->getUriFilter(NULL, TRUE)));

		$controlsArgv['ce.sc.places'] = '';
		$controlsArgv['ce.sc.winners'] = '';
		if (!empty($argv['unhide'])) {
			$controlsArgv['ce.sc.places'] = $this->renderSubcontrolPlaces($argv['event_id']);
			$controlsArgv['ce.sc.winners'] = $this->renderSubcontrolWinners($argv['event_id']);
		}
		$controlsArgv['ce.master'] = 'event';
		$controlsArgv['cm.master'] = 'misc';

		$controlsArgv['cm.players_left'] = $argv['pleft'];
		$controlsArgv['cm.players_total'] = $argv['ptotal'];
		$controlsArgv['cm.chipspool'] = $argv['cp'];
		$controlsArgv['cm.prizepool'] = $argv['ppool'];
		$controlsArgv['cm.fee'] = $argv['fee'];
		$controlsArgv['cm.buyin'] = $argv['buyin'];
		$controlsArgv['cm.rebuy'] = $argv['rebuy'];
		$controlsArgv['cm.addon'] = $argv['addon'];

		$playersPaging = $this->getPlayersPagingLabels($argv['event_id']);
		foreach ($playersPaging as $k => $playerPaging) {
			$controlsArgv['ce.sect_list_paging'][]= $tpl->parse('controls:event.sect_list_paging.item', array(
				'page' => $k,
				'name' => htmlspecialchars($playerPaging['name'])
			));
		};
		if (count($playersPaging) > 1) {
			$controlsArgv['ce.sect_list_paging'] = implode('', $controlsArgv['ce.sect_list_paging']);
		} else {
			$controlsArgv['ce.sect_list_paging'] = '';
		}
		$controlsArgv['ce.sect_list.evurl'] = $this->linkas('event#edit', array(
			'event_id' => $argv['event_id'],
			'path' => $cUriPath,
			'type' => 'profile',
			'id' => '-id-'
		), $this->getUriFilter(NULL, TRUE));
		$controlsArgv['ce.sponsors'] = json_encode($this->getSponsors());

		if (in_array($argv['tournament_id'], $this->get_var('wsopxml'))) {
			$controlsArgv['ce.special_import'] = true;
		}

		return $tpl->parse('controls:event', $controlsArgv);
	}

	// @todo get rid of this
	private function getSponsors()
	{
		return $this->lrep()->instEventModel('_src_event')->getSponsors();
	}

	private function getPlayersPagingLabels($eventId) 
	{
		$this->db->query('SET @ROW := -1');
		return $this->db->array_query_assoc('
			SELECT id, name FROM (
				SELECT @ROW:=@ROW+1 AS rk, p.id, p.name
				FROM ' . $this->table('Players') . ' p
				WHERE p.event_id=' . getInteger($eventId) . '
				ORDER BY name
			) i
			GROUP BY FLOOR(rk/' . $this->listPageBy . ')
		');
	}

	private function getPlayersData($eventId)
	{
		return $this->db->array_query_assoc('
			SELECT id , card, name, is_pnews, sponsor_id, status
			FROM ' . $this->table('Players') . ' 
			WHERE event_id=' . getInteger($eventId) . '
			ORDER BY name
		', 'id');
	}

	public function getPlayersDataSrcChips($eventId)
	{
		return $this->getPlayersData($eventId);
	}

	private function renderPlayersData($eventId)
	{
		if (NULL == ($prereq = $this->helperSaveCheckPrerequisitesLocationByEvent($eventId))) {
			return FALSE;
		}
		$this->db->query('SET @ROW := -1');
		$rPlayers = $this->db->query('
			SELECT @ROW:=@ROW+1 AS nr, p.id , p.card, p.name, p.is_pnews pn, p.sponsor_id sp, p.status st
			FROM ' . $this->table('Players') . ' p
			WHERE p.event_id=' . getInteger($eventId) . '
			ORDER BY name
		');
		$players = array();
		while ($player = $this->db->fetch_row_assoc($rPlayers)) {
			$players[] = array(
				intval($player['nr']),
				intval($player['id']),
				htmlspecialchars($player['card']),
				htmlspecialchars($player['name']),
				intval($player['pn']),
				intval($player['sp']),
				$player['st'],
			);
		}
		return $players;
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
			WHERE event_id=' . getInteger($eventId) . '
			ORDER BY place
		');
		$winners = $this->db->array_query_assoc('
			SELECT wl.player_id id, wl.place, wl.name' . 
				($extended ? ', p.sponsor_id, p.status, p.is_pnews, pp.uri uri' : '') . '
			FROM reporting_ng_winners_list wl ' . ($extended ? '
				LEFT JOIN ' . $this->table('Players') . ' p
					ON p.id=wl.player_id
				LEFT JOIN ' . $this->table('PlayersPoker') . ' pp
					ON pp.title=p.name AND pp.hidden=0
				' : '') . '
			WHERE wl.event_id=' . getInteger($eventId) . '
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

	private function getPlayerNames($eventId)
	{
		$names = array();
		$players = $this->db->array_query_assoc('
			SELECT id, name FROM ' . $this->table('Players') . '
			WHERE event_id=' . getInteger($eventId) . '
		');
		foreach ($players as $player) {
			$names[$player['id']] = $player['name'];
		}
		return $names;
	}

	private function renderPlayersSavePreviewWXML($data)
	{
		if (NULL == ($prereq = $this->helperSaveCheckPrerequisitesLocationByDay($data['day_id']))) {
			moon::page()->page404();
		}
		list ($location) = $prereq;
		if (!in_array($location['tournament_id'], $this->get_var('wsopxml'))) {
			moon::page()->page404();
		}

		$playerNames = array();
		foreach ($this->getPlayersData($location['event_id']) as $player) {
			$playerNames[$player['id']] = $player['name'];
		}
		$newData = array();
		$delData = array();
		$skpData = array();

		$ch = curl_init('http://wsop.com/data/xml/wsop2010/entrants.aspx' 
			. '?EventID=' . $this->object('livereporting_bluff')->bluffEventId($location['event_id']) 
			. '&day=' . $location['day_name']);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
		curl_setopt($ch, CURLOPT_TIMEOUT, 60*20);
		curl_setopt($ch, CURLOPT_FAILONERROR, 1);
		$gotData = curl_exec($ch);
		if (curl_errno($ch)) {
			curl_close($ch);
			moon::page()->page404();
		}
		curl_close($ch);

		try {
			$data = new SimpleXMLElement($gotData);
		} catch (Exception $e) {}

		if (!isset($data->chip_count)) {
			moon::page()->page404();
		}

		foreach ($data->chip_count as $row) {
			$xmlPlayer = array(
				'id'   => (int)$row->player['id'],
				'name' => trim((string)$row->player),
				'city' => trim((string)$row->city),
				'country' => trim((string)$row->country),
				'state'   => trim((string)$row->state),
				'amount'  => (int)$row->amount,
			);
			$tmp = explode(',', $xmlPlayer['name']);
			if (count($tmp) == 2) {
				$xmlPlayer['name'] = trim($tmp[1]) . ' ' . trim($tmp[0]);
			}

			$this->db->query('
				DELETE FROM ' . $this->table('PlayersBluff') . '
				WHERE event_id=' . intval($location['event_id']) . '
				  AND name="' . addslashes($xmlPlayer['name']) . '"
			');
			$this->db->insert(array(
					'event_id' => $location['event_id'],
					'bluff_id' => $xmlPlayer['id'],
					'name' => $xmlPlayer['name'],
					'city' => $xmlPlayer['city'],
					'country' => $xmlPlayer['country'],
					'state' => $xmlPlayer['state']
				), $this->table('PlayersBluff'));

			if (in_array($xmlPlayer['name'], $playerNames)) {
				$skpData[$xmlPlayer['id']] = $xmlPlayer;
				unset($playerNames[array_search($xmlPlayer['name'], $playerNames)]); // why not just use 'id'?
				continue;
			}

			$newData[$xmlPlayer['id']] = $xmlPlayer;
		}

		$omitDays = $this->lrep()->instEventModel('_src_event')->dayParallel($location['event_id'], $location['day_id']);
		if (0 != count($omitDays)) {
			$omitPlayers = $this->db->array_query_assoc('
				SELECT id FROM ' . $this->table('Players') . '
				WHERE event_id=' . getInteger($location['event_id']) . '
				  AND day_enter_id IN (' . implode(',', $omitDays) . ')
			');
			foreach ($omitPlayers as $omitPlayer) {
				if (isset($playerNames[$omitPlayer['id']])) {
					unset($playerNames[$omitPlayer['id']]);
				}
			}
		}
		foreach ($playerNames as $id => $name) {
			$delData[$id] = $name;
		}

		$textareaResult = array();
		$newPList = array();
		$delPList = array();
		$opList = array();
		foreach ($delData as $row) {
			$delPList[] = array($row);
			$textareaResult[] = '#' . $row;
		}
		foreach ($newData as $row) {
			$newPList[] = array($row['name']);
			$textareaResult[] = $row['name'] . '; ; ; ' . $row['amount'];
		}
		foreach ($skpData as $row) {
			$newPList[] = array($row['name']);
		}

		$return = array(
			'preview' => $this->renderPlayersSavePreview(array(
				'event_id' => $location['event_id'],
				'new_players_list' => $newPList,
				'del_players_list' => $delPList
			)),
			'data' => implode("\n", $textareaResult)
		);
		return $return;
	}

	private function renderPlayersSavePreview($data)
	{
		if (NULL == ($prereq = $this->helperSaveCheckPrerequisitesLocationByEvent($data['event_id']))) {
			return FALSE;
		}
		list ($location) = $prereq;

		$tpl = $this->load_template();
		$previewArgv = array(
			'cc.plist' => ''
		);

		$players = $this->getPlayersData($location['event_id']);
		$playerNames = array();
		$sponsors = array();
		foreach ($players as $player) {
			$playerNames[$player['id']] = strtolower($player['name']);
		}

		$rSponsors = $this->getSponsors();
		foreach ($rSponsors as $rSponsor) {
			$sponsors[strtolower($rSponsor['name'])] = $rSponsor['id'];
		}

		$mentionedPlayers = array();

		foreach ($data['del_players_list'] as $row) {
			if (!in_array(strtolower($row[0]), $playerNames)) {
				continue;
			}
			$player = $players[array_search(strtolower($row[0]), $playerNames)];
			$previewArgv['cc.plist'] .= $tpl->parse('controls:plist.save_preview.item', array(
				'is_del_player' => 1,
				'id'   => htmlspecialchars($player['card']),
				'name' => htmlspecialchars($player['name']),
				'sponsor' => isset($rSponsors[$player['sponsor_id']])
					? htmlspecialchars($rSponsors[$player['sponsor_id']]['name'])
					: '',
			));
		}
		foreach ($data['new_players_list'] as $row) {
			if (in_array(strtolower($row[0]), $mentionedPlayers)) {
				continue;
			}
			$mentionedPlayers[] = strtolower($row[0]);
			$player = array();
			if (in_array(strtolower($row[0]), $playerNames)) {
				$player = $players[array_search(strtolower($row[0]), $playerNames)];
			} else {
				$player['id'] = NULL;
				$player['name'] = $row[0];
			}
			if (isset($row[1])) {
				$player['card'] = $row[1];
			} elseif(!isset($player['card'])) {
				$player['card'] = '';
			}
			if (!empty($row[2])) {
				$row[2] = strtolower($row[2]);
				if (isset($sponsors[$row[2]])) {
					$player['sponsor_id'] = $sponsors[$row[2]];
				} else {
					$player['sponsor_id'] = NULL;
				}
			} elseif (!isset($player['sponsor_id'])) {
				$player['sponsor_id'] = NULL;
			}

			$previewArgv['cc.plist'] .= $tpl->parse('controls:plist.save_preview.item', array(
				'is_new_player' => !$player['id'],
				'id'   => htmlspecialchars($player['card']),
				'name' => htmlspecialchars($player['name']),
				'sponsor' => isset($rSponsors[$player['sponsor_id']])
					? htmlspecialchars($rSponsors[$player['sponsor_id']]['name'])
					: '',
			));
		}

		return $tpl->parse('controls:plist.save_preview', $previewArgv);
	}

	private function saveBumpEvent($eventId)
	{
		$this->db->update(array(
			'updated_on' => time(),
		), $this->table('Events'), array(
			'id' => $eventId
		));
	}

	private function savePlayers($data)
	{
		if (NULL == ($prereq = $this->helperSaveCheckPrerequisitesLocationByDay($data['day_id']))) {
			return FALSE;
		}
		list ($location) = $prereq;
		$lrep = $this->lrep();
		$chipsObj = $this->instEventChips();
		$profileObj = $this->instEventProfile();

		$players = $this->getPlayersData($location['event_id']);
		$playerNames = array();
		$sponsors = array();
		foreach ($players as $player) {
			$playerNames[$player['id']] = strtolower($player['name']);
		}

		$rSponsors = $this->getSponsors();
		foreach ($rSponsors as $rSponsor) {
			$sponsors[strtolower($rSponsor['name'])] = $rSponsor['id'];
		}

		$mentionedPlayers = array(); // players, mentioned several times in _this_ import

		// @todo should probably just pass control to deleteProfileFromSubEvent, without checking
		// @todo just event_id and player name
		foreach ($data['del_players_list'] as $row) {
			if (!in_array(strtolower($row[0]), $playerNames)) {
				continue;
			}
			$player = $players[array_search(strtolower($row[0]), $playerNames)];
			$profileObj->deleteProfileFromSubEvent($player['id']);
		}

		foreach ($data['new_players_list'] as $row) {
			if (in_array(strtolower($row[0]), $mentionedPlayers)) {
				continue;
			}
			$mentionedPlayers[] = strtolower($row[0]);
			$saveData = array(
				'day_enter_id' => $location['day_id'] // unroll
			);
			if (isset($row[1])) {
				$saveData['card'] = $row[1];
			}
			if (!empty($row[2])) {
				$row[2] = strtolower($row[2]);
				if (isset($sponsors[$row[2]])) {
					$saveData['sponsor_id'] = $sponsors[$row[2]];
				} else {
					$saveData['sponsor_id'] = NULL;
				}
			}
			if (in_array(strtolower($row[0]), $playerNames)) {
				$player = $players[array_search(strtolower($row[0]), $playerNames)];
				$saveData['updated_on'] = time();
				$this->db->update(
					$saveData,
					$this->table('Players'),
					array(
						'id' => $player['id']
					)
				);
				$chipsObj->saveSingleInitialSrcEvent(array(
					'day_id' => $location['day_id'],
					'event_id' => $location['event_id'],
					'player_id' => $player['id'],
					'chips' => isset($row[3])
						? intval($row[3])
						: NULL,
					'existing_player' => true
				));
			} else {
				$saveData['created_on'] = time();
				$saveData['name'] = $row[0];
				$saveData['tournament_id'] = $location['tournament_id'];
				$saveData['event_id'] = $location['event_id'];
				$this->db->insert(
					$saveData,
					$this->table('Players')
				);
				$iId = $this->db->insert_id();
				$chipsObj->saveSingleInitialSrcEvent(array(
					'day_id' => $location['day_id'],
					'event_id' => $location['event_id'],
					'player_id' => $iId,
					'chips' => isset($row[3])
						? intval($row[3])
						: NULL
				));
				$profileObj->notifyPlayerSaved($iId, $saveData['name']);
			}
		}

		/* $totalPlayers = $this->db->single_query_assoc('
			SELECT COUNT(id) cid FROM ' . $this->table('Players') . '
			WHERE event_id=' . $location['event_id'] . '
		');
		$this->db->query('
			UPDATE ' . $this->table('Events') . '
			SET players_left=' . getInteger($totalPlayers['cid']) . ', players_total=' . getInteger($totalPlayers['cid']) . '
			WHERE id=' . getInteger($data['event_id']) . ' AND players_left=0'
		); */
	}

	public function savePlayersSrcChips($data)
	{
		return $this->savePlayers($data);
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
		
		//$placesNoPlayer = array();
		$placePlayer = array();
		$placePayout = array();
		$players = $this->getPlayerNames($data['event_id']);

		foreach ($data['winner_list'] as $place => $name) {
			$evPayout = NULL;
			if ($name != NULL) {
				$evPayout = array(
					'name' => $name
				);
			}
			if ($name == NULL || !in_array($name, $players)) {
				//$placesNoPlayer[] = $place + 1;
			} else {
				$placePlayer[$place + 1] = array_search($name, $players);
				$evPayout['id'] = $placePlayer[$place + 1];
			}
			if ($evPayout) {
				$placePayout[$place + 1] = $evPayout;
			}
		}
		
		/* if (!empty($placePlayer)) {
			$playersLeft = min(array_keys($placePlayer));
			$playersLeft--;
			if ($playersLeft <= 0) {
				$playersLeft = 1;
			}
			// seems ok but not wanted
			$this->db->query('
				UPDATE ' . $this->table('Events') . '
				SET players_left=' . getInteger($playersLeft) . '
				WHERE id=' . getInteger($data['event_id'])
			);
		} */

		$this->db->query('DELETE FROM reporting_ng_winners_list WHERE event_id=' . $location['event_id']);
		foreach ($placePayout as $place => $payout) {
			$this->db->insert(array(
				'tournament_id' => $location['tournament_id'],
				'event_id' => $location['event_id'],
				'place' => $place,
				'name' => $payout['name'],
				'player_id' => isset($payout['id'])
					? $payout['id']
					: NULL,
				'created_on' => time(),
				'updated_on' => time(),
			), 'reporting_ng_winners_list');
		}
		
		// players left updated, winner list updated
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

		$deleteIds = array_keys($data['delete']);
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
	}

	// not a notify in fact, but a plain "save"
	private function saveEventStateSetReopened($location)
	{
		$this->db->query('
			UPDATE ' . $this->table('Events') . '
			SET state=1, updated_on=' . time() . '
			WHERE id=' . intval($location['event_id']) . ' AND state=2
		');
		
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
	}
}