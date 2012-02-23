<?php
/**
 * @package livereporting
 */
/**
 * @package livereporting
 */
class livereporting_bluff extends moon_com
{
	public function process($argv)
	{
		if (!in_array($argv['dist'], array('xml', 'json'))) {
			return;
		}
		// 'src' == 'bluff' from or
		if (isset($_GET['stars'])) {
			$argv['src'] = 'stars';
		} elseif (!isset($argv['src'])) {
			$argv['src'] = 'std';
		}
		
		if (!isset($_GET['nocache']) && !isset($argv['nocache'])) {
			$cacheFn = 'tmp/cache/lrep.bluff.' . urlencode($argv['key']) . '.' . 
				intval($argv['event_id']) . '.' . urlencode($argv['day_id']) . '.' . 
				$argv['src'] . '.' . $argv['dist'];
			if (file_exists($cacheFn)) {
				$output = file_get_contents($cacheFn);
				preg_match('~<timestamp>(.+?)</timestamp>~', $output, $tzts);
				if (isset($tzts[1])) {
					$tzts = $tzts[1];
					@list ($tz, $ts) = explode(':', $tzts);
					if (intval($ts) > (time() - 1 * 60)) {
						return str_replace('<timestamp>' . $tzts . '</timestamp>', '<timestamp>' . $this->fancyDate(time(), $tz) . '</timestamp>', $output);
					}
				}
  				@unlink($cacheFn);
			}
		}

		switch ($argv['key']) {
			case 'gallery':
				$output = $this->exGalleryPub($argv);
				break;

			case 'days':
				// always last
				$output = $this->exDays($argv);
				break;

			case 'chipcounts':
				// depends on day or all days
				$output = $this->exChipcounts($argv);
				break;

			case 'payouts':
				// always last
				$output = $this->exPayouts($argv);
				break;

			case 'topchips':
				// depends on day or last
				$output = $this->exTopchips($argv);
				break;

			case 'topupdates':
				// depends on day or last
				$output = $this->exTopupdates($argv);
				break;

			case 'updates':
				// depends on day or all days
				$output = $this->exUpdates($argv);
				break;

			case 'betting':
				// depends on day or all days
				$output = $this->exBetting($argv);
				break;

			case 'shoutbox':
				// depends on event
				$output = $this->exShoutBoxPub($argv['event_id']);
				break;

			case 'playersleft':
				// always last
				$output = $this->exPlayersleft($argv);
				break;

			case 'tournament-winners':
				$output = $this->exGroupedTournamentWinners($argv);
				break;

			default:
				return ;
		}
		
		if (!isset($_GET['nocache']) && !isset($argv['nocache'])) {
			file_put_contents($cacheFn, $output);
			touch($cacheFn, time());
		}
		preg_match('~<timestamp>(.+?)</timestamp>~', $output, $tzts);
		if (isset($tzts[1])) {
			$tzts = $tzts[1];
			@list ($tz, $ts) = explode(':', $tzts);
			$output = str_replace('<timestamp>' . $tzts . '</timestamp>', '<timestamp>' . $this->fancyDate(time(), $tz) . '</timestamp>', $output);
		}
		return $output;
	}

	private function getDayData($eventId)
	{
		$dayData = $this->db->array_query_assoc('
			SELECT id, name, day_date, state FROM ' . $this->table('Days') . '
			WHERE event_id=' . intval($eventId) . '
			    AND is_live>=0
		', 'id');
		usort($dayData, array($this, 'daysSortCmp'));
		$dayData_ = array();
		foreach ($dayData as $k => $v) {
			$dayData_[$v['id']] = $v;
		}
		return $dayData_;
	}

	private function exShoutBoxPub($eventId)
	{
		$event = $this->db->single_query_assoc('
			SELECT e.id, e.name
			FROM ' . $this->table('Events') . ' e
			INNER JOIN  ' . $this->table('Days') . ' d
				ON d.event_id=e.id
			WHERE e.id=' . intval($eventId) . '
				AND e.is_live=1
				AND d.is_live=1
			GROUP BY e.id
		');
		if (empty ($event)) {
			return ;
		}
		$obj = $this->object('shoutbox');
		$obj->parentID = intval($eventId);
		$shouts = $obj->getList(' LIMIT 5');
		$uID = array();
		foreach ($shouts as $shout) {
			$uID[] = $shout['user_id'];
		}
		$users = $obj->users($uID);

		Header('Content-Type: application/xml');
		$xml=new moon_xml_write;
		$xml->encoding('utf-8');
		$xml->open_xml();
		$xml->start_node('shouts');

		$xml->start_node('info');
		$xml->node('event', '', $event['name']);
		$xml->end_node('info');

		$locale = &moon::locale();
		$lrepObj = $this->object('livereporting');
		foreach ($shouts as $shout) {
			$xml->start_node('shout');
			$xml->node('id', '', $shout['id']);
			$xml->node('created', '', $lrepObj->instTools()->helperFancyDate($shout['created'], $shout['created'], $locale));
			$xml->node('user', '', (isset ($users[$shout['user_id']]))
				  ? $users[$shout['user_id']]['nick']
				  : '?');
			$xml->node('text', '', $shout['content']);
			$xml->end_node('shout');
		}

		$xml->end_node('shouts');
		echo $xml->close_xml();
		moon_close();
		exit;
	}

	private function exGalleryPub($argv)
	{
		$evtData = $this->db->single_query_assoc('
			SELECT t.id tid, t.name tname, t.timezone, t.currency, e.name ename, e.*
			FROM ' . $this->table('Tournaments') . ' t
			INNER JOIN ' . $this->table('Events') . ' e
				ON t.id=e.tournament_id
			WHERE e.id="' . intval($argv['event_id']) . '" and e.is_live=1
		');
		if (empty ($evtData)) {
			moon::page()->page404();
		}
		list($tzOffset) = moon::locale()->timezone($evtData['timezone']);

		if ('xml' == $argv['dist']) {
			$xml=new moon_xml_write;
			$xml->encoding('utf-8');
			$xml->open_xml();
			$xml->start_node('photos');
			$xml->node('timestamp','', $tzOffset . ':' . time());
			$this->eventInfoChunk($xml, $argv, $evtData, TRUE);
		} elseif ('json' == $argv['dist']) {
			$data = array(
				'eventInfo' => $this->eventInfoChunkJson($argv, $evtData, TRUE),
				'data' => array()
			);
		}

		$readUrl = $this->get_var('ipnReadBase');

		$photos = $this->object('livereporting_event')->instEventPhotos()->getPhotosSrcBluff($argv['event_id'], $argv['day_id'], 'LIMIT 500');
		foreach ($photos as $photo) {
			$photo['image2_src'] = $photo['image_src'];
			$photo['image2_src'][strlen($photo['image2_src']) - 15] = 'm';
			if ('xml' == $argv['dist']) {
				$xml->start_node('photo', array('id' => $photo['id']));
				$xml->node('url_small', '', $readUrl . $photo['image_src']);
				$xml->node('url_big',   '', $readUrl . $photo['image2_src']);
				$xml->node('description', '', $photo['image_alt']);
				$xml->end_node('photo');
			} elseif ('json' == $argv['dist']) {
				$data['data'][] = array(
					'id' => (int)$photo['id'],
					'urlSmall' => $readUrl . $photo['image_src'],
					'urlBig'   => $readUrl . $photo['image2_src'],
					'text' => $photo['image_alt']
				);
			}
		}

		if ('xml' == $argv['dist']) {
			$xml->end_node('photos');
			return $xml->close_xml();
		} elseif ('json' == $argv['dist']) {
			return json_encode($data);
		}
	}

	private function daysSortCmp($a, $b)
	{
		return strnatcasecmp($a['name'], $b['name']);
	}

	private function exChipcounts($argv)
	{
		$evt = $this->object('livereporting_event');
		$lrepTools = $this->object('livereporting')->instTools();
		$evtData = $this->db->single_query_assoc('
			SELECT t.id tid, t.name tname, t.timezone, t.currency, e.name ename, e.*
			FROM ' . $this->table('Tournaments') . ' t
			INNER JOIN ' . $this->table('Events') . ' e
				ON t.id=e.tournament_id
			WHERE e.id="' . intval($argv['event_id']) . '" and e.is_live=1
		');
		if (empty ($evtData)) {
			$page = &moon::page();
			$page->page404();
		}
		$dayData = $this->getDayData($argv['event_id']);

		$locale = &moon::locale();
		list($tzOffset) = $locale->timezone($evtData['timezone']);

		$bluffPlayers = $this->db->array_query_assoc('
			SELECT name, city, state, country FROM ' . $this->table('PlayersBluff') . '
			WHERE event_id=' . intval($argv['event_id']) . '
		', 'name');

		$xml=new moon_xml_write;
		$xml->encoding('utf-8');
		$xml->open_xml();
		$xml->start_node('div', array(
			'class' => 'live_updates'
		));

		$chips = array();

		$qDays = intval($argv['day_id']) == 0
			? array_keys($dayData)
			: array($argv['day_id']);

		$fDays = array();
		$oneFwd = false;
		foreach ($qDays as $day) {
			if ($dayData[$day]['state'] == 0 && $oneFwd) {
				continue;
			}
			if ($dayData[$day]['state'] == 0) {
				$oneFwd = true;
			}
			$fDays[] = $day;
		}
		$qDays = $fDays;
		$qDays = array_reverse($qDays);

		$chips = array();

		foreach ($qDays as $day) {
			$chipsC = $this->object('livereporting')->instEventModel('_src_bluff')->getLastChips($argv['event_id'], $day);
			foreach ($chipsC as $chip) {
				if ($chip['chips'] == '0' && $chip['day_id'] != $day) {
					continue ;
				}
				$chips[] = array(
					'chips' => $chip['chips'],
					'chips_change' => $chip['chipsc'],
					'created_on' => $chip['created_on'],
					'id' => $chip['id'],
					'user_id' => $chip['id'],
					'user_name' => $chip['uname'],
					'day_id' => $day,
					'status'  => isset($chip['status'])  ? $chip['status'] : '',
					'sponsor' => isset($chip['sponsor']) ? $chip['sponsor'] : ''
				);
			}

		}

		$updatedAt = 0;
		foreach ($chips as $chip) {
			$updatedAt = max($updatedAt, $chip['created_on']);
		}
		if (0 == $updatedAt) {
			$updatedAt = floor(time() / 300) * 300;
		}

		$xml->node('timestamp','', $tzOffset . ':' . time());
		$xml->node('modified','',  $this->fancyDate($updatedAt, $tzOffset));
		$this->eventInfoChunk($xml, $argv, $evtData);

		$wasDay=false;
		foreach ($chips as $chip) {
			$chip['day_id']=intval($chip['day_id']);
			if ($wasDay !== $chip['day_id']) {
				if (!isset($dayData[$chip['day_id']])) {
					continue;
				}
				if ($wasDay!==false) {
					$xml->end_node('event_day');
				}
				$wasDay = $chip['day_id'];
				$xml->start_node('event_day', array(
					'id' => $wasDay
				));
				$day = $dayData[$chip['day_id']];
				$xml->node('event_day_title', array(
						'name' => $day['name']),
					($day['name']
						? 'Day '.$day['name']
						: 'Pre-event')
					);
				$xml->node('event_day_datetime', '', $this->fancyDate($day['day_date'], $tzOffset));
				$xml->node('event_name', array(
						'id' => $evtData['bluff_id']
					), $evtData['ename']);
				$xml->node('tournament_name', array(
						'id' => $evtData['tid']
					), $evtData['tname']);
			}

			$xml->start_node('chip_count', array('id' => $chip['id']));
			$xml->node('amount', '', $chip['chips']);
			$xml->node('amount_change', '',$chip['chips_change']);
			$xml->node('created_at', '', $this->fancyDate($chip['created_on'], $tzOffset));
			$bluff = $this->getBluff($argv['event_id'], $chip['user_name']);
			$chip['bluff_id'] = $bluff['id'];
			$xml->node('player', array(
					'id'=> intval($chip['bluff_id'])
						? intval($chip['bluff_id'])
						: -1 * intval($chip['user_id'])
				),
				$chip['user_name']
			);
			$chip['player_sponsor'] = $lrepTools->helperPlayerStatus($chip['status'], $chip['sponsor']);
			if (!empty($chip['player_sponsor'])) {
				$xml->node('player_sponsor', '', $chip['player_sponsor']);
			}
			if (isset($bluffPlayers[$chip['user_name']])) {
				$xml->node('city', '', $bluffPlayers[$chip['user_name']]['city']);
				$xml->node('state', '', $bluffPlayers[$chip['user_name']]['state']);
				$xml->node('country', '', $bluffPlayers[$chip['user_name']]['country']);
			} else {
				$xml->node('city', '', '');
				$xml->node('state', '', '');
				$xml->node('country', '', '');
			}
			
			$xml->end_node('chip_count');
		}
		if ($wasDay!==false) {
			$xml->end_node('event_day');
		}

		$xml->end_node('div');
		return $xml->close_xml();
	}

	private function getBluff($eventId, $name)
	{
		if (!empty($name)) {
			$bluff = $this->db->single_query_assoc('
				SELECT bluff_id id, city, state, country FROM ' . $this->table('PlayersBluff') . '
				WHERE event_id=' . intval($eventId) . ' AND name="' . addslashes($name) . '"
			');
		}
		if (empty($bluff)) {
			$bluff = array(
				'id' => '', 'city' => '', 'state' => '', 'country' => ''
			);
		}
		return $bluff;
	}

	private function exTopchips($argv)
	{
		$evtData = $this->db->single_query_assoc('
			SELECT t.id tid, t.name tname, t.timezone, t.currency, e.name ename, e.*
			FROM ' . $this->table('Tournaments') . ' t
			INNER JOIN ' . $this->table('Events') . ' e
				ON t.id=e.tournament_id
			WHERE e.id="' . intval($argv['event_id']) . '" and e.is_live=1
		');
		if (empty ($evtData)) {
			$page = &moon::page();
			$page->page404();
		}
		/* $dayData = $this->getDayData($argv['event_id']); */
		$locale = &moon::locale();
		list($tzOffset) = $locale->timezone($evtData['timezone']);
		$lrepTools = $this->object('livereporting')->instTools();

		$xml=new moon_xml_write;
		$xml->encoding('utf-8');
		$xml->open_xml();
		$xml->start_node('div', array(
			'class' => 'top_chipcounts'
		));

		$bluffPlayers = $this->db->array_query_assoc('
			SELECT name, city, state, country FROM ' . $this->table('PlayersBluff') . '
			WHERE event_id=' . intval($argv['event_id']) . '
		', 'name');

		$chips = $this->object('livereporting')->instEventModel('_src_bluff')->getLastChips($argv['event_id'], $argv['day_id']);

		$chips = array_slice($chips, 0, 10);

		$updatedAt = 0;
		foreach ($chips as $chip) {
			$updatedAt = max($updatedAt, $chip['created_on']);
		}
		if (0 == $updatedAt) {
			$updatedAt = floor(time() / 300) * 300;
		}

		$xml->node('timestamp','', $tzOffset . ':' . time());
		$xml->node('modified','',  $this->fancyDate($updatedAt, $tzOffset));
		$this->eventInfoChunk($xml, $argv, $evtData);

		$i = 1;
		foreach ($chips as $chip) {
			if (0 == intval($chip['chips'])) {
				continue;
			}
			$bluff = $this->getBluff($argv['event_id'], $chip['uname']);
			$chip['bluff_id'] = $bluff['id'];
			$xml->start_node('chip_count', array('place' => $i++));
			$xml->node('player', array(
					'id'=> intval($chip['bluff_id'])
						? intval($chip['bluff_id'])
						: -1 * intval($chip['id'])
				),
				$chip['uname']
			);
			$chip['player_sponsor'] = $lrepTools->helperPlayerStatus(
				isset($chip['status']) ? $chip['status'] : '', 
				isset($chip['sponsor']) ? $chip['sponsor'] : '');
			if (!empty($chip['player_sponsor'])) {
				$xml->node('player_sponsor', '', $chip['player_sponsor']);
			}
			$xml->node('amount', '', $chip['chips']);
			$xml->node('created_at', '', $this->fancyDate($chip['created_on'], $tzOffset));

			if (isset($bluffPlayers[$chip['uname']])) {
				$xml->node('city', '', $bluffPlayers[$chip['uname']]['city']);
				$xml->node('state', '', $bluffPlayers[$chip['uname']]['state']);
				$xml->node('country', '', $bluffPlayers[$chip['uname']]['country']);
			} else {
				$xml->node('city', '', '');
				$xml->node('state', '', '');
				$xml->node('country', '', '');
			}

			$xml->end_node('chip_count');
		}

		$xml->end_node('div');
		return $xml->close_xml();
	}

	private function exPlayersleft($argv)
	{
		$evt = $this->object('livereporting_event');
		$evtData = $this->db->single_query_assoc('
			SELECT t.id tid, t.name tname, t.timezone, t.currency, e.name ename, e.*
			FROM ' . $this->table('Tournaments') . ' t
			INNER JOIN ' . $this->table('Events') . ' e
				ON t.id=e.tournament_id
			WHERE e.id="' . intval($argv['event_id']) . '" and e.is_live=1
		');
		if (empty ($evtData)) {
			$page = &moon::page();
			$page->page404();
		}
		$locale = &moon::locale();
		list($tzOffset) = $locale->timezone($evtData['timezone']);

		$playersLeft = '';
		if (intval($evtData['players_left']) == 0) {
			if (intval($evtData['players_total']) != 0) {
				$playersLeft = intval($evtData['players_total']);
			}
		} else {
			$playersLeft = intval($evtData['players_left']);
		}

		$xml=new moon_xml_write;
		$xml->encoding('utf-8');
		$xml->open_xml();
		$xml->start_node('players', array(
			'event_id' => $evtData['bluff_id'],
			'left' => $playersLeft
		));

		$xml->node('timestamp','', $tzOffset . ':' . time());
		$this->eventInfoChunk($xml, $argv, $evtData);

		$xml->end_node('players');
		return $xml->close_xml();
	}

	private function eventInfoChunk(&$xml, &$argv, &$evtData, $short = FALSE) 
	{
		$xml->start_node('event_info');
		$xml->node('tournament_name', array(
			'id' => $evtData['tid']
		), $evtData['tname']);
		$xml->node('event_name', array(
			'id' => $evtData['id']
		), $evtData['ename']);
		$xml->node('event_shortname', array(
			'id' => $evtData['id']
		), '');

		$dayId = $argv['day_id'];
		if ($dayId == 0) {
			$dayId = $this->object('livereporting')->instEventModel('_src_bluff')
				->getDaysDefaultId($argv['event_id']);
		}
		$day = $this->db->single_query_assoc('
			SELECT id, name, day_date FROM ' . $this->table('Days') . '
			WHERE id=' . intval($dayId) . '
			    AND is_live>=0
		');
		$xml->start_node('day', array(
			'id' => $dayId
		));
		$xml->node('name', array(
				'short' => $day['name']
			), 'Day ' . $day['name']);
		list($tzOffset) = moon::locale()->timezone($evtData['timezone']);
		$xml->node('date', array(), gmdate('Y-m-d', $day['day_date'] + $tzOffset));
		$xml->end_node('day');

		if (!$short) {
			$xml->node('buyin',     null, $evtData['buyin']);
			$xml->node('prizepool', null, $evtData['prizepool']);
			$xml->node('entries', null, $evtData['players_total']);
			$xml->node('remaining_players', null, $evtData['players_left']);

			$playersLeft = 0;
			if (intval($evtData['players_left']) == 0) {
				if (intval($evtData['players_total']) != 0) {
					$playersLeft = intval($evtData['players_total']);
				}
			} else {
				$playersLeft = intval($evtData['players_left']);
			}
			if ($playersLeft != 0 && intval($evtData['chipspool']) != 0) {
				$avgStack = round($evtData['chipspool'] / $playersLeft, 2);
			} else {
				$avgStack = '';
			}
			$xml->node('avg_stack', null, $avgStack);

			$round = $this->db->single_query_assoc('
				SELECT r.* FROM ' . $this->table('Log') . ' l
				INNER JOIN ' . $this->table('tRounds') . ' r
					ON r.id=l.id
				WHERE l.event_id=' . intval($argv['event_id']) . ' AND l.day_id=' . $dayId . ' AND l.type="round" AND l.is_hidden=0
				ORDER BY r.round DESC
				LIMIT 1
			');
			if ($round == null) {
				$xml->node('level', null, '');
				$xml->node('blinds', null, '');
				$xml->node('ante', null, '');
			} else {
				$xml->node('level', null, $round['round']);
				$xml->node('blinds', null, $round['small_blind'] . '/' . $round['big_blind']);
				$xml->node('ante', null, $round['ante']);
			}
		}
		$xml->end_node('event_info');
	}
	
	private function eventInfoChunkJson($argv, $evtData, $short = FALSE)
	{
		$dayId = $argv['day_id'];
		if ($dayId == 0) {
			$dayId = $this->object('livereporting')->instEventModel('_src_bluff')
				->getDaysDefaultId($argv['event_id']);
		}
		$day = $this->db->single_query_assoc('
			SELECT id, name, day_date FROM ' . $this->table('Days') . '
			WHERE id=' . intval($dayId) . '
			    AND is_live>=0
		');
		list($tzOffset) = moon::locale()->timezone($evtData['timezone']);
		
		$data = array(
			'tournament' => array(
				'id' => (int)$evtData['tid'],
				'name' => $evtData['tname']
			),
			'event' => array(
				'id' => (int)$evtData['id'],
				'name' => $evtData['ename']
			),
			'day' => array(
				'id' => $dayId,
				'name' => 'Day ' . $day['name'],
				'date' => gmdate('Y-m-d', $day['day_date'] + $tzOffset)
			)
		);
		return $data;

		/*if (!$short) {
			$xml->node('buyin',     null, $evtData['buyin']);
			$xml->node('prizepool', null, $evtData['prizepool']);
			$xml->node('entries', null, $evtData['players_total']);
			$xml->node('remaining_players', null, $evtData['players_left']);

			$playersLeft = 0;
			if (intval($evtData['players_left']) == 0) {
				if (intval($evtData['players_total']) != 0) {
					$playersLeft = intval($evtData['players_total']);
				}
			} else {
				$playersLeft = intval($evtData['players_left']);
			}
			if ($playersLeft != 0 && intval($evtData['chipspool']) != 0) {
				$avgStack = round($evtData['chipspool'] / $playersLeft, 2);
			} else {
				$avgStack = '';
			}
			$xml->node('avg_stack', null, $avgStack);

			$round = $this->db->single_query_assoc('
				SELECT r.* FROM ' . $this->table('Log') . ' l
				INNER JOIN ' . $this->table('tRounds') . ' r
					ON r.id=l.id
				WHERE l.event_id=' . intval($argv['event_id']) . ' AND l.day_id=' . $dayId . ' AND l.type="round" AND l.is_hidden=0
				ORDER BY r.round DESC
				LIMIT 1
			');
			if ($round == null) {
				$xml->node('level', null, '');
				$xml->node('blinds', null, '');
				$xml->node('ante', null, '');
			} else {
				$xml->node('level', null, $round['round']);
				$xml->node('blinds', null, $round['small_blind'] . '/' . $round['big_blind']);
				$xml->node('ante', null, $round['ante']);
			}
		}*/
	}

	private function exBetting($argv)
	{
		$evt = $this->object('livereporting_event');
		$evtData = $this->db->single_query_assoc('
			SELECT t.id tid, t.name tname, t.timezone, t.currency, e.name ename, e.*
			FROM ' . $this->table('Tournaments') . ' t
			INNER JOIN ' . $this->table('Events') . ' e
				ON t.id=e.tournament_id
			WHERE e.id="' . intval($argv['event_id']) . '" and e.is_live=1
		');
		if (empty ($evtData)) {
			$page = &moon::page();
			$page->page404();
		}
		$dayData = $this->getDayData($argv['event_id']);
		$locale = &moon::locale();
		list($tzOffset) = $locale->timezone($evtData['timezone']);

		$xml=new moon_xml_write;
		$xml->encoding('utf-8');
		$xml->open_xml();
		$xml->start_node('betting', array(
			'event_id' => $evtData['bluff_id']
		));

		$xml->node('timestamp','', $tzOffset . ':' . time());

		$this->eventInfoChunk($xml, $argv, $evtData);

		$rounds = $this->db->array_query_assoc('
			SELECT l.created_on, l.day_id, r.* FROM ' . $this->table('Log') . ' l
			INNER JOIN ' . $this->table('tRounds') . ' r
				ON r.id=l.id
			INNER JOIN ' . $this->table('Days') . ' d
				ON d.id=l.day_id
			WHERE l.event_id=' . intval($argv['event_id']) .
				(intval($argv['day_id'])
					? ' AND l.day_id=' . intval($argv['day_id'])
					: '') . '
				AND l.type="round"
				AND l.is_hidden=0
				AND d.is_live=1
			ORDER BY d.id DESC, r.round DESC
		');

		$wasDay=false;
		foreach ($rounds as $round) {
			$round['day_id']=intval($round['day_id']);
			if ($wasDay !== $round['day_id']) {
				if (!isset($dayData[$round['day_id']])) {
					continue;
				}
				if ($wasDay!==false) {
					$xml->end_node('day');
				}
				$wasDay = $round['day_id'];
				$xml->start_node('day', array(
					'id' => $wasDay
				));
				$day = $dayData[$round['day_id']];
				$xml->node('day_title', array(
						'name' => $day['name']),
					($day['name']
						? 'Day '.$day['name']
						: 'Pre-event')
					);
				$xml->node('day_datetime', '', $this->fancyDate($day['day_date'], $tzOffset));
			}
			$xml->start_node('round', array(
					'round' => intval($round['round']),
					'small_blind' => intval($round['small_blind']),
					'big_blind' => intval($round['big_blind']),
					'ante' => intval($round['ante']),
					'duration' => intval($round['duration']),
				));
			$xml->node('datetime', '', $this->fancyDate($round['created_on'], $tzOffset));
			$xml->node('description', '', $round['description']);
			$xml->end_node('round');
		}
		if ($wasDay!==false) {
			$xml->end_node('day');
		}

		$xml->end_node('betting');
		return $xml->close_xml();
	}

	private function sortCreatedCmp(&$a, &$b)
	{
		if ($a['created_on'] == $b['created_on']) {
			return 0;
		}
		return $a['created_on'] < $b['created_on'] ? 1 : -1;
	}
	
	private function exTopupdates($argv)
	{
		$evt = $this->object('livereporting_event');
		$evtData = $this->db->single_query_assoc('
			SELECT t.id tid, t.name tname, t.timezone, t.currency, e.name ename, e.*
			FROM ' . $this->table('Tournaments') . ' t
			INNER JOIN ' . $this->table('Events') . ' e
				ON t.id=e.tournament_id
			WHERE e.id="' . intval($argv['event_id']) . '" and e.is_live=1
		');
		if (empty ($evtData)) {
			$page = &moon::page();
			$page->page404();
		}
		$locale = &moon::locale();
		list($tzOffset) = $locale->timezone($evtData['timezone']);

		$xml=new moon_xml_write;
		$xml->encoding('utf-8');
		$xml->open_xml();
		$xml->start_node('div', array(
			'class' => 'top_updates'
		));

		$limit = $argv['src'] == 'stars'
			? 25
			: 5;
		$posts = $this->db->array_query_assoc('
			SELECT l.type, l.created_on, l.updated_on, l.day_id, p.id, p.title, p.contents, p.image_src, p.image_alt, p.image_misc
			FROM ' . $this->table('Log') . ' l
			INNER JOIN ' . $this->table('Days') . ' d
				ON l.day_id=d.id
			INNER JOIN ' . $this->table('tPosts') . ' p
				ON l.id=p.id
			WHERE l.type="post"
				AND l.event_id=' . intval($argv['event_id']) .
				(intval($argv['day_id'])
					? ' AND l.day_id=' . intval($argv['day_id'])
					: '') . '
				AND l.is_hidden=0
				AND d.is_live=1
				AND p.is_exportable=1
			ORDER BY l.created_on DESC
			LIMIT ' . $limit
		);
		if (!in_array(@$argv['src'], array('stars', 'bluff'))) {
			$cPosts = array(); // do not show in pnapp
		} else {
			$cPosts = $this->db->array_query_assoc('
				SELECT l.type, l.created_on, l.updated_on, l.day_id, p.id, p.title, p.contents, p.image_src, p.image_alt, p.image_misc, p.chips
				FROM ' . $this->table('Log') . ' l
				INNER JOIN ' . $this->table('Days') . ' d
					ON l.day_id=d.id
				INNER JOIN ' . $this->table('tChips') . ' p
					ON l.id=p.id
				WHERE l.type="chips"
					AND l.event_id=' . intval($argv['event_id']) .
					(intval($argv['day_id'])
						? ' AND l.day_id=' . intval($argv['day_id'])
						: '') . '
					AND l.is_hidden=0
					AND d.is_live=1
					AND p.is_exportable=1
				ORDER BY l.created_on DESC
				LIMIT ' . $limit
			);
		}
		$rPosts = $this->db->array_query_assoc('
			SELECT l.type, l.created_on, l.updated_on, l.day_id, p.id, p.round, p.limit_not_blind, p.small_blind, p.big_blind, p.ante, p.description
			FROM ' . $this->table('Log') . ' l
			INNER JOIN ' . $this->table('Days') . ' d
				ON l.day_id=d.id
			INNER JOIN ' . $this->table('tRounds') . ' p
				ON l.id=p.id
			WHERE l.type="round"
				AND l.event_id=' . intval($argv['event_id']) .
				(intval($argv['day_id'])
					? ' AND l.day_id=' . intval($argv['day_id'])
					: '') . '
				AND l.is_hidden=0
				AND d.is_live=1
			ORDER BY l.created_on DESC
			LIMIT ' . $limit
		);
		foreach ($cPosts as $post) {
			$posts[] = $post;
		}
		foreach ($rPosts as $post) {
			$posts[] = $post;
		}
		usort($posts, array($this, 'sortCreatedCmp'));
		$posts = array_slice($posts, 0, $limit);

		$updatedAt = 0;
		foreach ($posts as $post) {
			$updatedAt = max($updatedAt, $post['updated_on']
				? $post['updated_on']
				: $post['created_on'] );
		}
		if (0 == $updatedAt) {
			$updatedAt = floor(time() / 300) * 300;
		}

		$xml->node('timestamp','', $tzOffset . ':' . time());
		$xml->node('modified','',  $this->fancyDate($updatedAt, $tzOffset));
		$this->eventInfoChunk($xml, $argv, $evtData);

		foreach ($posts as $post) {
			$this->exUpdatesHelperPost($xml, $post, $tzOffset, $argv);
		}

		$xml->end_node('div');
		return $xml->close_xml();
	}

	private function exUpdates($argv)
	{
		$evt = $this->object('livereporting_event');
		$evtData = $this->db->single_query_assoc('
			SELECT t.id tid, t.name tname, t.timezone, t.currency, e.name ename, e.*
			FROM ' . $this->table('Tournaments') . ' t
			INNER JOIN ' . $this->table('Events') . ' e
				ON t.id=e.tournament_id
			WHERE e.id="' . intval($argv['event_id']) . '" and e.is_live=1
		');
		if (empty ($evtData)) {
			$page = &moon::page();
			$page->page404();
		}
		$dayData = $this->getDayData($argv['event_id']);
		$locale = &moon::locale();
		list($tzOffset) = $locale->timezone($evtData['timezone']);

		$xml=new moon_xml_write;
		$xml->encoding('utf-8');
		$xml->open_xml();
		$xml->start_node('div', array(
			'class' => 'live_updates'
		));

		$posts = $this->db->array_query_assoc('
			SELECT l.type, l.created_on, l.updated_on, l.day_id, p.id, p.title, p.contents, p.image_src, p.image_alt, p.image_misc
			FROM ' . $this->table('Log') . ' l
			INNER JOIN ' . $this->table('tPosts') . ' p
				ON l.id=p.id
			INNER JOIN ' . $this->table('Days') . ' d
				ON l.day_id=d.id
			WHERE l.type="post"
				AND l.event_id=' . intval($argv['event_id']) .
				(intval($argv['day_id'])
					? ' AND l.day_id=' . intval($argv['day_id'])
					: '') . '
				AND l.is_hidden=0
				AND d.is_live=1
				AND p.is_exportable=1
			ORDER BY l.day_id DESC, l.created_on DESC
		');
		if (!in_array(@$argv['src'], array('stars', 'bluff'))) {
			$cPosts = array(); // do not show in pnapp
		} else {
			$cPosts = $this->db->array_query_assoc('
				SELECT l.type, l.created_on, l.updated_on, l.day_id, p.id, p.title, p.contents, p.image_src, p.image_alt, p.image_misc, p.chips
				FROM ' . $this->table('Log') . ' l
				INNER JOIN ' . $this->table('Days') . ' d
					ON l.day_id=d.id
				INNER JOIN ' . $this->table('tChips') . ' p
					ON l.id=p.id
				WHERE l.type="chips"
					AND l.event_id=' . intval($argv['event_id']) .
					(intval($argv['day_id'])
						? ' AND l.day_id=' . intval($argv['day_id'])
						: '') . '
					AND l.is_hidden=0
					AND d.is_live=1
					AND p.is_exportable=1
				ORDER BY l.day_id DESC, l.created_on DESC
			');
		}
		$rPosts = $this->db->array_query_assoc('
			SELECT l.type, l.created_on, l.updated_on, l.day_id, p.id, p.round, p.limit_not_blind, p.small_blind, p.big_blind, p.ante, p.description
			FROM ' . $this->table('Log') . ' l
			INNER JOIN ' . $this->table('Days') . ' d
				ON l.day_id=d.id
			INNER JOIN ' . $this->table('tRounds') . ' p
				ON l.id=p.id
			WHERE l.type="round"
				AND l.event_id=' . intval($argv['event_id']) .
				(intval($argv['day_id'])
					? ' AND l.day_id=' . intval($argv['day_id'])
					: '') . '
				AND l.is_hidden=0
				AND d.is_live=1
			ORDER BY l.created_on DESC
			LIMIT 5
		');
		foreach ($cPosts as $post) {
			$posts[] = $post;
		}
		foreach ($rPosts as $post) {
			$posts[] = $post;
		}
		usort($posts, array($this, 'sortCreatedCmp'));
		
		$updatedAt = 0;
		foreach ($posts as $post) {
			$updatedAt = max($updatedAt, $post['updated_on']
				? $post['updated_on']
				: $post['created_on'] );
		}
		if (0 == $updatedAt) {
			$updatedAt = floor(time() / 300) * 300;
		}

		$xml->node('timestamp','', $tzOffset . ':' . time());
		$xml->node('modified','',  $this->fancyDate($updatedAt, $tzOffset));

		$this->eventInfoChunk($xml, $argv, $evtData);
		
		if (@$argv['src'] == 'bluff') {
			$emptyDays = $this->db->array_query_assoc('
				SELECT id FROM ' . $this->table('Days') . '
				WHERE event_id=' . intval($argv['event_id']) .
				(intval($argv['day_id'])
					? ' AND id=' . intval($argv['day_id'])
					: '') . '
					AND is_live>=0
			', 'id');
			foreach ($posts as $post) {
				unset($emptyDays[intval($post['day_id'])]);
			}
			foreach ($emptyDays as $day_) {
				$posts[] = array(
					'id' => 0,
					'type' => 'dummy',
					'day_id' => $day_['id'],
					'created_on' => time(),
					'updated_on' => time(),
					'title' => 'Day not started yet',
					'contents' => 'Live updates will be provided once this day begins. Consult our events schedule for complete details.'
				);
			}
		}

		$wasDay=false;
		foreach ($posts as $post) {
			$post['day_id']=intval($post['day_id']);
			if ($wasDay !== $post['day_id']) {
				if (!isset($dayData[$post['day_id']])) {
					continue;
				}
				if ($wasDay!==false) {
					$xml->end_node('event_day');
				}
				$wasDay = $post['day_id'];
				$xml->start_node('event_day', array(
					'id' => $wasDay
				));
				$day = $dayData[$post['day_id']];
				$xml->node('event_day_title', array(
						'name' => $day['name']),
					($day['name']
						? 'Day '.$day['name']
						: 'Pre-event')
					);
				$xml->node('event_day_datetime', '', $this->fancyDate($day['day_date'], $tzOffset));
				$xml->node('event_name', array(
						'id' => $evtData['bluff_id']
					), $evtData['ename']);
				$xml->node('tournament_name', array(
						'id' => $evtData['tid']
					), $evtData['tname']);
			}
			$this->exUpdatesHelperPost($xml, $post, $tzOffset, $argv);
		}
		if ($wasDay!==false) {
			$xml->end_node('event_day');
		}

		$xml->end_node('div');
		return $xml->close_xml();
	}

	private function exUpdatesHelperPost(&$xml, $post, $tzOffset, &$argv)
	{
		if (in_array($post['type'], array('chips', 'post'))) {
			$rtf = $this->object('rtf');
			$rtf->setInstance($this->get_var('rtf') . '-bluff');
			list(, $post['contents']) = $rtf->parseText($post['id'], $post['contents']);
			preg_match_all('/\[((10|[2-9AKQJtx]{1})(s|c|h|d|x)([,|\s]*))+\]/i', $post['contents'], $m);
			foreach($m[0] as $k => $v) {
				$t = preg_replace('/(10|[2-9AKQJtx]{1})(s|c|h|d|x)/i', '{$1$2}', $v);
				$t = str_replace(array("[", "]", ",", " "), "", $t);
				$post['contents'] = str_replace($v, $t, $post['contents']);
			}
			$post['contents'] = str_replace(
				array(
					chr(226).chr(128).chr(156),
					chr(226).chr(128).chr(157),
					chr(226).chr(128).chr(152),
					chr(226).chr(128).chr(153)
				), array('"', '"', "'", "'"),
				$post['contents']);
			$post['contents'] = preg_replace('~\[([0-9jkqatx]{1,2}[dcshx])\]~i', '{${1}}', $post['contents']);
			$post['contents'] = preg_replace('/Ladbrokes.com player|Ladbrokes player|Ladbrokes online qualifier|Ladbrokes/is','',$post['contents']);

			if (!empty($post['chips'])) {
				$chips = array();
				$rChips = explode("\n", $post['chips']);
				foreach($rChips as $rChip) {
					if ('' == $rChip) {
						continue;
					}
					$rChip = explode(",", $rChip);
					$nr = intval($rChip[0]);
					if ($nr == 0) {
						continue;
					}
					$chips[$nr] = array(
						'chips' => number_format(intval($rChip[1])),
						'chipsc' => $rChip[2] !== ''
							? number_format(intval($rChip[2]))
							: NULL,
						'pos' => isset($rChip[3])
							? intval($rChip[3])
							: 0
					);
				}
				if (count($chips) > 0) {
					$players = $this->db->array_query_assoc('
						SELECT p.id, p.name
						FROM ' . $this->table('Players') . ' p
						WHERE p.id IN (' . implode(',', array_keys($chips)) . ')
					');
					foreach ($players as $player) {
						$chips[intval($player['id'])] += array(
							'id'      => $player['id'],
							'uname'   => $player['name'],
						);
					}
					$post['contents'] .= '<table class="chips">';
					$kChip = 1;
					foreach ($chips as $chip) {
						$post['contents'] .= '<tr class="' . ($kChip % 2 ? 'PNhirow' : 'PNlorow') . '"><td class="name">' . (isset($chip['uname']) ? htmlspecialchars($chip['uname']) : '-') . '</td>';
						$post['contents'] .= '<td class="c">' . htmlspecialchars($chip['chips']) . '</td>';
						$post['contents'] .= '<td class="cc">' . ($chip['chipsc'] !== NULL
						    ? htmlspecialchars($chip['chipsc']) . ' <img src="http://www.pokernews.com/img/live_poker/delta_' . ($chip['chipsc'] > 0 ? 'pos' : 'neg') .'.png" width="7" height="8" />'
						    : '') . '</td></tr>';
						$kChip++;
					}
					$post['contents'] .= '</table>';
				}
			}
		} elseif ('round' == $post['type']) {
			$post['title'] = 'Level ' . $post['round'] . ' started';
			$post['contents'] = '
				<table width=100% cellpadding=6 cellspacing=0 border=0 bgcolor="#701112"><tr><td align=center><table cellpadding=2 cellspacing=0 border=0><tr><td><font size=5 color="#ffffff"><b>
				Level:
				</td><td><font size=5 color="#ffffff">
				' . $post['round'] . '
				</td><td> </td><td><font size=5 color="#ffffff"><b>
				Blinds:
				</td><td><font size=5 color="#ffffff">
				' . $post['small_blind'] . '/' . $post['big_blind'] . '
				</td><td> </td><td><font size=5 color="#ffffff"><b>
				Ante:
				</td><td><font size=5 color="#ffffff">
				' . $post['ante'] . '
				</td></tr></table></td></tr></table>';
		}
		
		$xml->start_node('live_update', array(
			'id'=>$post['id']
		));

		$xml->node('timestamp', '', $this->fancyDate($post['created_on'], $tzOffset));
		if ($argv['src'] == 'stars') {
			$xml->node('timestamp_fmtd', '', $this->fancyDateFancierPST($post['created_on']));
		}

		$xml->node('title','', $post['title']);
		if (!empty($post['image_src'])) {
			if (!empty($argv['src']) && in_array($argv['src'], array('bluff', 'stars'))) {
				$dims = explode(',', $post['image_misc']);
				$orientation = 'right';
				$w = null; $h = null;
				if (3 == count($dims)) {
					$w = $dims[1];
					$h = $dims[2];
					$orientation = ($w > $h) 
						? 'left'
						: 'right';
				}
				$img = '<p><img src="' . $this->get_var('ipnReadBase') . $post['image_src'] . '" style="' . ($w && $h ? 'height:' . $h . 'px;width:' . $w . 'px;' : '') . 'margin:3px 10px;border:3px solid;" class="' . $orientation . ' caption" title="' . htmlspecialchars($post['image_alt']) . '" alt="' . htmlspecialchars($post['image_alt']) . '" />';
				$post['contents'] = 
					$img . $post['contents'];
			} else {
				$xml->node_nl('img', array(
					'src' => $this->get_var('ipnReadBase') . $post['image_src']
				), $post['image_alt']);
			}
		}
		$xml->node_nl('body','', $post['contents']);
		$xml->node('updated_at', '', $this->fancyDate($post['updated_on']
					? $post['updated_on']
					: $post['created_on'], $tzOffset));
		$xml->end_node('live_update');
	}
	
	private function exPayouts($argv)
	{
		$evt = $this->object('livereporting_event');
		$evtData = $this->db->single_query_assoc('
			SELECT t.id tid, t.name tname, t.timezone, t.currency, e.name ename, e.*
			FROM ' . $this->table('Tournaments') . ' t
			INNER JOIN ' . $this->table('Events') . ' e
				ON t.id=e.tournament_id
			WHERE e.id="' . intval($argv['event_id']) . '" and e.is_live=1
		');
		if (empty ($evtData)) {
			$page = &moon::page();
			$page->page404();
		}
		$locale = &moon::locale();
		list($tzOffset) = $locale->timezone($evtData['timezone']);

		$xml=new moon_xml_write;
		$xml->encoding('utf-8');
		$xml->open_xml();
		$xml->start_node('div', array(
			'class' => 'payouts'
		));

		$xml->node('timestamp','', $tzOffset . ':' . time());
		$this->eventInfoChunk($xml, $argv, $evtData);
		$xml->node('currency', array(),
			$evtData['currency']
		);

		$evtEvt = $this->object('livereporting_event')->instEventEvent();
		$payouts = $evtEvt->getPayoutsSrcBluff($argv['event_id']);

		$sponsorIds = array();
		foreach ($payouts as $k => $row) {
			if (!empty($row['player.sponsor_id'])) {
				$sponsorIds[] = $row['player.sponsor_id'];
			}
		}
		$sponsors = $this->object('livereporting')->instEventModel('_src_bluff')->getSponsorsById($sponsorIds);
		foreach ($payouts as $k => $row) {
			if (isset($sponsors[$row['player.sponsor_id']])) {
				$payouts[$k]['player.sponsor'] = $sponsors[$row['player.sponsor_id']]['name'];
			} else {
				$payouts[$k]['player.sponsor'] = '';
			}
		}

		$lrepTools = $this->object('livereporting')->instTools();
		foreach ($payouts as $payout) {
			$xml->start_node('payout', array(
				'place' => $payout['nr']
			));
			$bluff = $this->getBluff($argv['event_id'], $payout['player.name']);
			$payout['bluff_id'] = $bluff['id'];
			$xml->node('player', array(
					'id' => intval($payout['bluff_id'])
						? intval($payout['bluff_id'])
						: -1 * intval($payout['player.id'])
				), $payout['player.name']);
			$xml->node('prize', array(),
				number_format($payout['sum'], 0));
			$payout['player.sponsor'] = $lrepTools->helperPlayerStatus($payout['player.status'], $payout['player.sponsor']);
			if (!empty($payout['player.sponsor'])) {
				$xml->node('player_sponsor', '', $payout['player.sponsor']);
			}

			$xml->end_node('payout');
		}

		$xml->end_node('div');
		return $xml->close_xml();
	}

	private function exDays($argv)
	{
		$evt = $this->object('livereporting_event');
		$evtData = $this->db->single_query_assoc('
			SELECT t.id tid, t.name tname, t.timezone, t.currency, e.name ename, e.*
			FROM ' . $this->table('Tournaments') . ' t
			INNER JOIN ' . $this->table('Events') . ' e
				ON t.id=e.tournament_id
			WHERE e.id="' . intval($argv['event_id']) . '" and e.is_live=1
		');
		if (empty ($evtData)) {
			$page = &moon::page();
			$page->page404();
		}
		$locale = &moon::locale();
		list($tzOffset) = $locale->timezone($evtData['timezone']);

		$xml=new moon_xml_write;
		$xml->encoding('utf-8');
		$xml->open_xml();
		$xml->start_node('days', array(
			'event_id' => $evtData['bluff_id']
		));

		$xml->node('timestamp','', $tzOffset . ':' . time());
		$this->eventInfoChunk($xml, $argv, $evtData);

		$days = $this->db->array_query_assoc('
			SELECT id, name, day_date FROM ' . $this->table('Days') . '
			WHERE event_id=' . intval($argv['event_id']) . '
			    AND is_live>=0
			ORDER BY name
		');
		$k = 1;
		foreach ($days as $day) {
			$xml->node('day', array(
					'id' => $day['id'],
					'order_no' => $k++,
					'name' => $day['name'],
					'date' => gmdate('m/d/Y', $day['day_date']/* + $tzOffset */) // w/$tz local, wo/$tz gmt
				),
				$day['name']
					? 'Day ' . $day['name']
					: 'Pre-event'
			);
		}

		$xml->end_node('days');
		return $xml->close_xml();
	}

	private function exGroupedTournamentWinners($argv)
	{
		// $evt = $this->object('livereporting_event');
		$trnData = $this->db->single_query_assoc('
			SELECT t.id tid, t.name tname, t.timezone, t.currency
			FROM ' . $this->table('Tournaments') . ' t
			WHERE t.id="' . intval($argv['tournament_id']) . '" and t.is_live=1
		');
		if (empty ($trnData)) {
			$page = &moon::page();
			$page->page404();
		}
		$locale = &moon::locale();
		list($tzOffset) = $locale->timezone($trnData['timezone']);

		$xml=new moon_xml_write;
		$xml->encoding('utf-8');
		$xml->open_xml();
		$xml->start_node('div', array(
			'class' => 'tournament-winners'
		));

		$xml->node('timestamp','', $tzOffset . ':' . time());
		$xml->start_node('event_info');
		$xml->node('tournament_name', array(
			'id' => $trnData['tid']
		), $trnData['tname']);
		$xml->node('currency', array(),
			$trnData['currency']
		);
		$xml->end_node('event_info');

		$lrep = $this->object('livereporting');
		$lrepTools = $lrep->instTools();
		$lrepTournament = $lrep->instTournamentModel('_src_bluff');
		$events = $lrepTournament->getEvents($argv['tournament_id']);
		foreach ($events as $event) {
			$xml->start_node('event', array(
				'id' => $event['id'],
				'bluff_id' => $event['bluff_id'],
			));
			$xml->node('name', '', $event['name']);
			$xml->node('prize', '', $event['prize']
					? number_format($event['prize'])
					: '');
			$xml->node('duration', '', 
				$lrepTools->helperFancyDate($event['from_date'] + $tzOffset, $event['to_date'] == NULL ? NULL : $event['to_date'] + $tzOffset, $locale)
			);
			$xml->start_node('winner');
				$xml->node('name', '', $event['winner']);
				$xml->node('hand', '', $event['winning_hand']);
				$xml->node('uri', '', $event['winner_uri']);
				$xml->node('image', '', $event['winner_img']
					? img('player',  $event['winner_id'] . '-' . $event['winner_img'])
					: '');
			$xml->end_node('winner');
			$xml->start_node('runner_up');
				$xml->node('name', '', $event['runner_up']);
				$xml->node('hand', '', $event['losing_hand']);
				$xml->node('uri', '', $event['runner_up_uri']);
			$xml->end_node('runner_up');
			$xml->end_node('event');
		}

		$xml->end_node('div');
		return $xml->close_xml();
	}

	private function fancyDate($time, $tzOffset)
	{
		$tzz = round($tzOffset/3600);
		$tz = ($tzz<0 ? '-':'+') . (abs($tzz)<10 ? '0':'') . abs($tzz).'00';
		return gmdate('Y-m-d H:i:s', $time + $tzOffset) . ' ' . $tz;
	}

	private function fancyDateFancierPST($time)
	{
		$text = moon::shared('text');
		$createdOn = $text->ago($time);
		if (!($createdOn == '' || $time > time())) {
			return $createdOn;
		}
		
		$tzOffset = -8*3600;
		$tzz = round($tzOffset/3600);
		$tz = ($tzz<0 ? '-':'+') . (abs($tzz)<10 ? '0':'') . abs($tzz).'00';
		return moon::locale()->gmdatef($time + $tzOffset, 'Reporting') . ' PST';
	}
	
	public function bluffEventId($id, $rev = false) 
	{
		if ($rev == FALSE) {
			$row = $this->db->single_query_assoc('
				SELECT bluff_id FROM ' . $this->table('Events') . '
				WHERE id=' . intval($id) . '
				LIMIT 1
			');
			return isset($row['bluff_id'])
				? $row['bluff_id']
				: null;
		} else {
			$row = $this->db->single_query_assoc('
				SELECT id FROM ' . $this->table('Events') . '
				WHERE bluff_id=' . intval($id) . '
				LIMIT 1
			');
			return isset($row['id'])
				? $row['id']
				: null;
		}
	}
}