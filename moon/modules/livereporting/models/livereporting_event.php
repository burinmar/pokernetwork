<?php
/**
 * @package livereporting
 */
/**
 */
require_once 'livereporting_model_pylon.php';
/**
 * Event data model: per-event information.
 *
 * Data retrieval, when it is used out of the scope of a more fine domain, should be placed here.
 * E.g.: 
 * - common (does not belong to any sub component): days list, current day, post list and count, etc.
 * - used by post comp. (other sub component): last round.
 *
 * All methods should be protected, and overridden in ancestor classes. This helps in filtering out numerous 
 * data accesses, and check if requesters still receive what they expect to, after changes are made.
 * @package livereporting
 * @subpackage models
 * @todo maybe put cross-linked method from livereporting_event_* in here
 */
class livereporting_model_event extends livereporting_model_pylon
{
	private $defaultDayCache = array();
	/**
	 * Returns id of the current `default` day of the event.
	 *
	 * If no days are started, the first public day is selected.
	 * Otherwise, the last started day is selected.
	 *
	 * Cached.
	 * @param int $eventId 
	 * @return mixed Integer or null
	 */
	protected function getDaysDefaultId($eventId)
	{
		if (isset($this->defaultDayCache[$eventId])) {
			return $this->defaultDayCache[$eventId];
		} elseif (NULL == $eventId) {
			return ;
		}
		$data = $this->db->single_query_assoc('
			SELECT id FROM ' . $this->table('Days') . '
			WHERE event_id=' . filter_var($eventId, FILTER_VALIDATE_INT) . '
			    AND is_live=1
			    AND state!=0
			ORDER BY is_empty, (0+name) DESC, name DESC
			LIMIT 1
		');
		if (empty($data['id'])) { // when no day is started (almost never)
			$data = $this->db->single_query_assoc('
				SELECT id FROM ' . $this->table('Days') . '
				WHERE event_id=' . filter_var($eventId, FILTER_VALIDATE_INT) . '
				    AND is_live=1
				ORDER BY is_empty, (0+name) ASC, name ASC
				LIMIT 1
			');
		}
		if (empty($data['id'])) {
			$data['id'] = NULL;
		}
		$this->defaultDayCache[$eventId] = $data['id'];
		return $this->defaultDayCache[$eventId];
	}
	
	/**
	 * Resets getDaysDefaultId() cache in the rare cases it is required
	 * @param int $eventId 
	 */
	protected function unsetDefaultDayCache($eventId)
	{
		unset($this->defaultDayCache[$eventId]);
	}

	protected function getDayData($eventId, $name)
	{
		$data = $this->db->single_query_assoc('
			SELECT id,is_live FROM ' . $this->table('Days') . '
			WHERE event_id=' . filter_var($eventId, FILTER_VALIDATE_INT) . '
			    AND name="' . addslashes(str_replace('day', '', $name)) . '"
			    AND is_live>=0
			');
		if (empty($data)) {
			return NULL;
		}
		return $data;
	}
	
	/**
	 * @todo supress arg errors
	 */
	protected function countUpdates($eventId, $dayId, $since)
	{
		$eventId = filter_var($eventId, FILTER_VALIDATE_INT);
		$dayId   = filter_var($dayId, FILTER_VALIDATE_INT);
		$since   = filter_var($since, FILTER_VALIDATE_INT);
		$updates = $this->db->single_query_assoc('
			SELECT COUNT(id) cnt FROM ' . $this->table('Log') . '
			WHERE ' . ($dayId ? 'day_id=' . $dayId : 'event_id=' . $eventId)  . '
				AND (created_on>' . $since . ' OR updated_on>' . $since . ') 
				AND created_on<' . (ceil(time() / 60) * 60) . '
				AND is_hidden=0
		');
		return $updates['cnt'];
	}

	/**
	 * @todo should check is_live in query (SELECT t.name tname ..), but since uri cache is invalidated quicky, skip that
	 */
	protected function getEventData($eventId)
	{
		static $eventsData = array();
		if (isset($eventsData[$eventId])) {
			return $eventsData[$eventId];
		}

		$eventsData[$eventId] = $this->db->single_query_assoc('
			SELECT t.name tname, t.timezone, t.currency, t.ad_rooms, t.tour, e.name ename, e.is_live, t.geolocation,
			       e.players_left pleft, e.players_total ptotal, e.prizepool ppool, e.chipspool cp, t.state tstate, e.state, 
			       t.is_syncable&e.is_syncable synced, t.sync_id sync_origin, t.autopublish, e.buyin, e.fee, e.rebuy, e.addon,
			       t.show_wsop_eod and e.bluff_id IS NOT NULL show_wsop_eod
			FROM ' . $this->table('Tournaments') . ' t
			INNER JOIN ' . $this->table('Events') . ' e
				ON t.id=e.tournament_id
			WHERE e.id=' . filter_var($eventId, FILTER_VALIDATE_INT) . '
			HAVING e.is_live!=-1
		');
		if (0 == count($eventsData[$eventId])) {
			return NULL;
		}

		$eventsData[$eventId]['sync_origin'] = explode(':', $eventsData[$eventId]['sync_origin']);
		$eventsData[$eventId]['sync_origin'] = $eventsData[$eventId]['sync_origin'][0];
		list($eventsData[$eventId]['tzOffset'], $eventsData[$eventId]['tzName']) = moon::locale()->timezone($eventsData[$eventId]['timezone']);

		return $eventsData[$eventId];
	}

	private function getAvailableFilterTypes()
	{
		return array(
			'posts' => 'post',
			'chips' => 'chips',
			'photos'=> 'photos'
		);
	}
	
	private function getLogEntriesSqlWhere($eventId, $dayId, $filter)
	{
		$where = array();

		if (!empty($dayId)) {
			$where[] = 'day_id=' . filter_var($dayId, FILTER_VALIDATE_INT);
		} else {
			$where[] = 'event_id=' . filter_var($eventId, FILTER_VALIDATE_INT);
		}
		if (!empty($filter['showHidden'])) {
			$where[] = 'is_hidden!=2';
		} else {
			$where[] = 'is_hidden=0';
			$where[] = 'created_on<' . (ceil(time() / 60) * 60);
		}
		if (!empty($filter['show'])) {
			$typeFilterData = $this->getAvailableFilterTypes();
			$typeFilter = array();
			$typeCandidates = explode(' ', $filter['show']);
			foreach ($typeCandidates as $candidate) {
				if (isset($typeFilterData[$candidate])) {
					$typeFilter[] = "'" . $typeFilterData[$candidate] . "'";
				}
			}
			if (0 != count($typeFilter)) {
				$where[] = 'type IN (' . implode(',', $typeFilter) . ')';
			}
		}
		if (!empty($filter['newerThan'])) {
			$where[] = 'created_on>' . filter_var($filter['newerThan'], FILTER_VALIDATE_INT) . '';
		}
		return $where;
	}

	protected function getLogEntries($eventId, $dayId, $filter = NULL, $limit = NULL)
	{
		$where = $this->getLogEntriesSqlWhere($eventId, $dayId, $filter);
		$entries = $this->db->array_query_assoc('
			SELECT id, event_id, type, is_hidden, created_on, author_id, contents, sync_id IS NOT NULL synced
			FROM ' . $this->table('Log') . ' l 
			WHERE ' . implode(' AND ', $where) . '
			ORDER BY created_on ' . (!empty($filter['rsort']) ? '' : 'DESC ') .
			($limit != NULL
				? $limit
				: '')
		);
		if (0 == count($entries)) {
			return array();
		}
		$this->helperLogEntriesAttachAuthors($entries);
		return $entries;
	}

	private function helperLogEntriesAttachAuthors(&$entries)
	{
		$authorIds = array();
		foreach ($entries as $entry) {
			if ($entry['author_id'] != 0) {
				$authorIds[$entry['id']] = $entry['author_id'];
			}
		}
		$authors = array();
		if (0 !== count($authorIds)) {
			$authors = array(); // no users table
		}
		foreach ($entries as $k => $entry) {
			if (isset($authors[$entry['author_id']])) {
				$entries[$k]['author_name'] = $authors[$entry['author_id']];
			} else {
				$entries[$k]['author_name'] = NULL;
			}
		}
	}

	protected function getLogEntriesCount($eventId, $dayId, $filter = NULL)
	{
		$where = $this->getLogEntriesSqlWhere($eventId, $dayId, $filter);

		$count = $this->db->single_query_assoc('
		SELECT COUNT(id) cid FROM ' . $this->table('Log') . '
		WHERE ' . implode(' AND ', $where));

		return $count['cid'];
	}

	protected function getLogEntry($eventId, $type, $id, $showHidden = FALSE)
	{
		$isHiddenSql = $showHidden
			? '1'
			: 'is_hidden=0';
		$entry = $this->db->single_query_assoc('
			SELECT l.*, "" author_name
			FROM ' . $this->table('Log') . ' l
			WHERE l.id=' . filter_var($id, FILTER_VALIDATE_INT) . '
				AND l.type="' . addslashes($type) . '"
				AND l.event_id=' . filter_var($eventId, FILTER_VALIDATE_INT) . '
				AND ' . $isHiddenSql
		);
		if (empty($entry)) {
			return NULL;
		}
		return $entry;
	}

	protected function getKeyHandEntries($eventId)
	{
		$entries = $this->db->array_query_assoc('
			SELECT l.id, l.event_id, l.day_id, l.type, l.created_on, l.author_id, COALESCE(p.title, c.title) title
			FROM ' . $this->table('Log') . ' l 
			LEFT JOIN ' . $this->table('tPosts') . ' p
				ON p.is_keyhand=1 AND p.id=l.id AND l.type="post"
			LEFT JOIN ' . $this->table('tChips') . ' c
				ON c.is_keyhand=1 AND c.id=l.id AND l.type="chips"
			WHERE l.event_id=' . filter_var($eventId, FILTER_VALIDATE_INT) . ' AND l.is_hidden=0
				AND COALESCE(p.id, c.id) IS NOT NULL 
			ORDER BY l.created_on DESC 
			LIMIT 20'
		);
		if (0 == count($entries)) {
			return array();
		}
		$this->helperLogEntriesAttachAuthors($entries);
		return $entries;
	} 
	
	private function daysSortCmp($a, $b)
	{
		return strnatcasecmp($a['name'], $b['name']);
	}

	protected function getDaysData($eventId)
	{
		static $daysData = array();
		if (isset($daysData[$eventId])) {
			return $daysData[$eventId];
		}
		$days = $this->db->array_query_assoc('
			SELECT id, name, is_live, is_empty, state, merge_name FROM ' . $this->table('Days') . '
			WHERE event_id=' . filter_var($eventId, FILTER_VALIDATE_INT) . '
			    AND is_live>=0
			ORDER BY name 
		');
		usort($days, array($this, 'daysSortCmp'));
		foreach ($days as $row) {
			$daysData[$eventId][$row['id']] = $row;
		}
		return $daysData[$eventId];
	}
	
	private function getLiveEventsSort($a, $b)
	{
		if ($a['from_date'] == $b['from_date']) {
			return $a['id'] < $b['id'] ? 1 : -1;
		}
		return ($a['from_date'] < $b['from_date']) ? 1 : -1;
	}

	/**
	 * @todo cache: if caching, drop from_date and sort within the sql query
	 */
	protected function getLiveEvents($tournamentId, $includeEmpty = FALSE)
	{
		$evs = $this->db->array_query_assoc('
			SELECT e.id, e.name, e.from_date, MIN(d.is_empty) is_empty
			FROM ' . $this->table('Events') . ' e
			INNER JOIN  ' . $this->table('Days') . ' d
				ON d.event_id=e.id
			WHERE e.tournament_id=' . filter_var($tournamentId, FILTER_VALIDATE_INT) . '
				AND e.is_live=1
				AND d.is_live=1
			GROUP BY e.id ' . ($includeEmpty 
			    ? ''
			    : 'HAVING is_empty=0') . '
			ORDER BY e.id DESC
		');
		usort($evs, array($this, 'getLiveEventsSort'));
		foreach ($evs as $k => $v) {
			unset($evs[$k]['from_date']);
		}
		
		return $evs;
	}
	
	/**
	 * @todo cache, maybe
	 */
	protected function getLastRound($eventId, $dayId)
	{
		// $daysData = $this->getDaysData($eventId);
		// $omitDays = $this->dayParallel($daysData, $dayId);
		$roundId = $this->db->single_query_assoc('
			SELECT r.id mid 
			FROM reporting_ng_log l
			INNER JOIN reporting_ng_sub_rounds r
				ON r.id=l.id AND l.type="round"
			WHERE l.event_id=' . intval($eventId) . '
			  AND l.day_id=' . intval($dayId) . '
			  AND l.is_hidden!=2
			  AND r.variety IN("limits-round", "blinds-round")
			ORDER BY l.day_id=' . intval($dayId) /* may be 0, but ok */ . ' DESC, l.created_on DESC
			LIMIT 1
		');
		//	WHERE l.event_id=' . intval($eventId) .
		//	(0 != count($omitDays)
		//		? ' AND l.day_id NOT IN(' . implode(',', $omitDays) . ')' : '')  .'
		if (!isset($roundId['mid']))
			return null;

		return $this->getRoundData($roundId['mid']);
	}
	
	protected function getCurrentRound($eventId, $dayId)
	{
		return $this->getRound($eventId, $dayId, time());;
	}
	
	protected function getRound($eventId, $dayId, $timestamp)
	{
		// $daysData = $this->getDaysData($eventId);
		// $omitDays = $this->dayParallel($daysData, $dayId);
		$id = $this->db->single_query_assoc('
			SELECT l.id FROM reporting_ng_log l
			INNER JOIN reporting_ng_sub_rounds r
				ON r.id=l.id AND l.type="round" 
			WHERE l.event_id=' . intval($eventId) . '
			  AND l.day_id=' . intval($dayId) . '
			  AND l.is_hidden!=2 AND l.created_on<' . intval($timestamp) . '
			  AND r.variety IN("limits-round", "blinds-round")
			ORDER BY l.created_on DESC
			LIMIT 1
		');
		//	WHERE event_id=' . intval($eventId) . 
		//	(0 != count($omitDays)
		//		? ' AND day_id NOT IN(' . implode(',', $omitDays) . ')' : '')  .'
		if (!isset($id['id']))
			return null;

		if (null == ($round = $this->getRoundData($id['id'])))
			return null;

		return array_intersect_key($round, array_flip(array('id', 'round', 'small_blind', 'big_blind', 'ante')));
	}

	private function getRoundData($id)
	{
		$round = $this->db->single_query_assoc('
			SELECT id, round, duration, small_blind, big_blind, variety, ante
			FROM reporting_ng_sub_rounds
			WHERE id=' . intval($id) . '
		');

		if (0 == count($round)) {
			return null;
		}

		return $round;
	}

	protected function getWinner($eventId)
	{
		$winner = $this->db->single_query_assoc('
			SELECT w.winning_hand, w.losing_hand, w.winner, w.prize, pw.id,  pw.uri winner_uri, pw.img winner_img
			FROM ' . $this->table('Winners') . ' w
			LEFT JOIN ' . $this->table('PlayersPoker') . ' pw
				ON pw.title=w.winner AND pw.hidden=0
			WHERE w.event_id=' . filter_var($eventId, FILTER_VALIDATE_INT) . '
				AND w.winner!=""
			LIMIT 1
		');
		
		if (empty($winner)) {
			return NULL;
		}
		
		return $winner;	
	}
	
	protected function getLastPhotos($eventId)
	{
		return $this->db->array_query_assoc('
			SELECT image_src, image_alt
			FROM ' . $this->table('Photos') . '
			WHERE event_id=' . filter_var($eventId, FILTER_VALIDATE_INT) . ' AND is_hidden=0
			ORDER BY created_on DESC
			LIMIT 9');
	}
	
	protected function getMobileappPhotos($dayId)
	{
		return $this->db->array_query_assoc('
			SELECT id, image_src, image_alt title
			FROM ' . $this->table('Photos') . '
			WHERE day_id=' . filter_var($dayId, FILTER_VALIDATE_INT) . ' AND is_hidden=0
			ORDER BY created_on DESC
			LIMIT 30');
	}

	/**
	 * Returns previous days, including self, but excluding parallel and future days
	 * Input days must be ordered
	 */
	private function dayUnroll($daysData, $dayId)
	{
		$nameHash = array();
		foreach ($daysData as $day)
			$nameHash[$day['name']] = $day['id'];

		$revHash = array();
		foreach ($daysData as $day) {
			if (!isset($nameHash[$day['merge_name']]))
				continue;
			$revHash[$nameHash[$day['merge_name']]][] = $day['id'];
		}

		$daysPrev = array();
		$descendPool = array($dayId);
		while (count($descendPool) > 0) {
			$newDescendPool = array();
			foreach ($descendPool as $id) {
				$daysPrev[] = $id;
				if (isset($revHash[$id]))
					foreach ($revHash[$id] as $subId)
						$newDescendPool[] = $subId;
			}
			$descendPool = $newDescendPool;
		}

		return $daysPrev;
	}

	protected function dayParallelIface($eventId, $dayId)
	{
		$daysData = $this->getDaysData($eventId);
		if (0 == count($daysData)) {
			return array();
		}
		return $this->dayParallel($daysData, $dayId);
	}

	private function getFauxRoomSponsors()
	{
		return array(
			-1 => array(
				'id' => -1,
				'alias' => '',
				'name'  => 'DeepStack Team Pro',
				'favicon' => '/img/live_poker/deepstacks-icon.png',
				'is_hidden' => 0,
			),
			-2 => array(
				'id' => -2,
				'alias' => '',
				'name'  => 'Onnit',
				'favicon' => '/img/live_poker/nn-icon.png',
				'is_hidden' => 0,
			),
		);
	}
	
	protected function getSponsorsById($ids)
	{
		$ids = array_unique($ids);
		foreach ($ids as $key => $id) {
			$ids[$key] = intval($id);
		}
		if (0 == count($ids)) {
			$return = array();
		} else {			
			$return = $this->db->array_query_assoc('
				SELECT id, alias, name, favicon, is_hidden FROM ' . $this->table('Rooms') . '
				WHERE id IN (' . implode(',', $ids) . ')
			', 'id');
		}
		$return += $this->getFauxRoomSponsors();

		return $return;
	}
	
	protected function getSponsors()
	{
		$return = $this->db->array_query_assoc('
			SELECT id, name, is_hidden
			FROM ' . $this->table('Rooms') . '
			ORDER BY name
		', 'id');
		$return += $this->getFauxRoomSponsors();

		return $return;
	}
	
	// @todo name fields properly
	const chipsTiny = 1;
	protected function getLastChips($eventId, $dayId, $modifier = 0)
	{
		$daysData = $this->getDaysData($eventId);
		if (0 == count($daysData)) {
			return array();
		}

		$daysToQuery = !empty($dayId)
			? $this->dayUnroll($daysData, $dayId)
			: array_keys($daysData);

		$tiny = $modifier == self::chipsTiny;

		$outerWhere = array('true', /* 'p.event_id=' . intval($eventId); !! implied */);
		$innerWhere = array('true');
		$outerOrder = array(
			'chips'  => 'ce.chips DESC', 
			'date'   => 'ce.created_on DESC', 
			'player' => 'ce.player_id'
		);
		$outerLimit = '';
		if ($tiny) {
			unset($outerOrder['date']);
			$innerWhere[] = 'c.chips!=0';
			$outerLimit = ' LIMIT 10';
		} else if ($dayId) {
			$outerWhere[] = '(ce.day_id=' . intval($dayId) . ' OR ce.chips!=0)';
		}

		$sql = '
		SELECT p.id, ce.chips, p.name uname, p.sponsor_id' . (!$tiny ? ', ce.created_on, ce.day_id, ce.chips_change chipsc, p.pp_id, p.status, p.is_pnews, p.country_id' : '') . ' 
		FROM (
			SELECT c.chips, c.player_id' . (!$tiny ? ', c.created_on, c.day_id, c.chips_change' : '' ) . ' FROM (
				SELECT player_id, MAX(created_on) created_on FROM reporting_ng_chips
				WHERE day_id IN (' . implode(',', $daysToQuery) . ') AND is_hidden=0
				GROUP BY player_id ORDER BY NULL
			) x
			INNER JOIN reporting_ng_chips c
				ON c.player_id=x.player_id AND c.created_on=x.created_on
			WHERE ' . implode(' AND ', $innerWhere) . '
		) ce
		INNER JOIN ' . $this->table('Players') . ' p
			ON p.id=ce.player_id AND p.is_hidden=0
		WHERE ' . implode(' AND ', $outerWhere) . '
		ORDER BY ' . implode(', ', $outerOrder) .
		$outerLimit;

		$chips = $this->db->array_query_assoc($sql);

		$sponsorIds = array();
		foreach ($chips as $k => $row) {
			if (!empty($row['sponsor_id'])) {
				$sponsorIds[] = $row['sponsor_id'];
			}
		}
		
		$sponsors = $this->getSponsorsById($sponsorIds);
		foreach ($chips as $k => $row) {
			if (isset($sponsors[$row['sponsor_id']])) {
				$chips[$k] += array(
					'sponsorimg' => $sponsors[$row['sponsor_id']]['favicon'],
					'sponsorurl' => $sponsors[$row['sponsor_id']]['alias'],
					'sponsor'    => $sponsors[$row['sponsor_id']]['name'],
				);
			} else {
				$chips[$k] += array(
					'sponsorimg' => NULL,
					'sponsorurl' => NULL,
					'sponsor' => NULL,
				);
			}
		}

		return $chips;
	}

	// "retains" players array keys
	// redirects to getPreviousChipsByPlayerId with some processing
	protected function getPreviousChipsByPlayerName($eventId, $playerKNames, $datetime)
	{
		if (0 == count($playerKNames))
			return array();

		$names = array();
		foreach ($playerKNames as $name)
			$names[] = '"' . $this->db->escape($name) . '"';
		$players = $this->db->array_query_assoc('
			SELECT id, FIELD(name, ' . implode(',', $names) . ') nr 
			FROM ' . $this->table('Players') . '
			WHERE event_id=' . intval($eventId) . ' 
			  AND name IN(' . implode(',', $names) . ')
		', 'id');

		$previousChips = $this->getPreviousChipsByPlayerId($eventId, array_keys($players), $datetime);

		$result = array();
		foreach ($playerKNames as $k => $name)
			$result[$k] = null;
		foreach ($players as $player) {
			$nr = intval($player['nr']) - 1;
			if (!array_key_exists($nr, $result))
				continue; // should not be possible at all, in theory
			$playerId = $player['id'];
			if (!isset($previousChips[$playerId])) 
				continue; // should not be possible, unless db altered in between queries or bug in code
			$result[$nr] = $previousChips[$playerId];
		}

		return $result;
	}

	// analogous to getLastChips, but matched by player ids instead of day, 
	// and limited by datetime instead of plain last within the day
	// return hidden players, players with no chips
	protected function getPreviousChipsByPlayerId($eventId, $playerIds, $datetime)
	{
		// $includeIntermediate = no chips change / chips change since last major update
		// $includeIntermediate == TRUE ? '' : 'is_full_import DESC,
		$daysData = $this->getDaysData($eventId);
		if (0 == count($daysData))
			return array();
		$daysToQuery = array_keys($daysData);

		if (0 == count($playerIds))
			return array();
		foreach ($playerIds as $k => $id)
			$playerIds[$k] = intval($id);

		$sql = '
		SELECT p.id, p.name, ce.chips
		FROM ' . $this->table('Players') . ' p
		LEFT JOIN (
			SELECT c.chips, c.player_id FROM (
				SELECT player_id, MAX(created_on) created_on FROM ' . $this->table('Chips') . '
				WHERE day_id IN (' . implode(',', $daysToQuery) . ') AND is_hidden=0
				  AND created_on<' . intval($datetime) . '
				  AND player_id IN (' . implode(',', $playerIds) . ')
				GROUP BY player_id ORDER BY NULL
			) x
			INNER JOIN ' . $this->table('Chips') . ' c
				ON c.player_id=x.player_id AND c.created_on=x.created_on
		) ce
			ON p.id=ce.player_id
		WHERE p.event_id=' . intval($eventId) . '
			AND p.id IN (' . implode(',', $playerIds) . ')';
		// p.event_id almost implied

		return $this->db->array_query_assoc($sql, 'id');
	}

	// analogous to getLastChips with tiny on, but without limit=10,
	// limited by datetime instead of plain last within the day
	// return hidden players, do not return busted players at all (even todays)
	protected function getPreviousChipsByDayDT($eventId, $dayId, $datetime)
	{
		$daysData = $this->getDaysData($eventId);
		if (0 == count($daysData)) {
			return array();
		}

		$daysToQuery = $this->dayUnroll($daysData, $dayId);

		$sql = '
		SELECT p.id, p.name, ce.chips
		FROM ' . $this->table('Players') . ' p
		INNER JOIN (
			SELECT c.chips, c.player_id FROM (
				SELECT player_id, MAX(created_on) created_on FROM ' . $this->table('Chips') . '
				WHERE day_id IN (' . implode(',', $daysToQuery) . ') AND is_hidden=0
				  AND created_on<' . intval($datetime) . '
				GROUP BY player_id ORDER BY NULL
			) x
			INNER JOIN ' . $this->table('Chips') . ' c
				ON c.player_id=x.player_id AND c.created_on=x.created_on
			WHERE c.chips!=0
		) ce
			ON p.id=ce.player_id';
		$chips = $this->db->array_query_assoc($sql);

		return $chips;
	}	

	protected function getChipsHistory($playerId, $eventId)
	{
		$daysData = $this->getDaysData($eventId);
		if (0 == count($daysData))
			return array();
		$daysToQuery = array_keys($daysData);

		return $this->db->array_query_assoc('
			SELECT id, chips, chips_change, import_id, created_on, day_id
			FROM ' . $this->table('Chips') . '
			WHERE player_id=' . intval($playerId) . '
				AND day_id IN (' . implode(',', $daysToQuery) . ')
			ORDER BY created_on
		');
	}	

	protected function getDayChipsTimeCap($eventId, $dayId)
	{
		$daysData = $this->getDaysData($eventId);

		$daysToQuery = array_diff(
			array_keys($daysData), 
			$this->dayUnroll($daysData, $dayId)
		);
		if (0 == count($daysToQuery)) {
			return time();
		}

		// forward days
		$capTop = $this->db->single_query_assoc('
			SELECT MIN(created_on) `cap` FROM ' . $this->table('Chips') . '
			WHERE day_id IN(' . implode(',', $daysToQuery) . ')
		');
		$cap = empty($capTop['cap'])
			? time()
			: $capTop['cap'] - 120;

		$capBottom = $this->db->single_query_assoc('
			SELECT MAX(created_on) `cap` FROM ' . $this->table('Chips') . '
			WHERE day_id=' . intval($dayId) . '
		');
		if (!empty($capBottom['cap']))
			$cap = max($cap, $capBottom['cap'] + 1);

		return $cap;
	}
	
	/**
	 * @todo maybe if there is winner, abort before checking min(place)
	 */
	protected function getPayoutBundle($eventId)
	{
		$return = array(
			'has_payouts' => FALSE,
			'next_payout' => NULL
		);
		
		$hasPayouts = $this->db->single_query_assoc('
			SELECT id FROM ' . $this->table('Payouts') . '
			WHERE event_id=' . intval($eventId) . '
			LIMIT 1');
		if (!empty($hasPayouts)) {
			$return['has_payouts'] = true;
		}
		
		$place = $this->db->single_query_assoc('
			SELECT MIN(place)-1 place FROM ' . $this->table('Players') . '
			WHERE event_id=' . intval($eventId) . '
		');
		if (0 == count($place) || $place['place'] == 0) {
			return $return;
		}
		
		$payout = $this->db->single_query_assoc('
			SELECT prize FROM ' . $this->table('Payouts') . '
			WHERE  event_id=' . intval($eventId) . '
				AND ((place=' . $place['place'] . ' AND place_to IS NULL)
				  OR (place<=' . $place['place'] . ' AND place_to>=' . $place['place'] . ')
				)
		');
		if (0 == count($payout)) {
			return $return;
		}
		
		$return['next_payout'] = $place + $payout;
		
		return $return;
	}

	// "retains" players array keys
	public function getPlayersByPlayerName($eventId, $playerKNames)
	{
		if (0 == count($playerKNames))
			return array();

		$names = array();
		foreach ($playerKNames as $name)
			$names[] = '"' . $this->db->escape($name) . '"';
		$players = $this->db->array_query_assoc('
			SELECT id, FIELD(name, ' . implode(',', $names) . ') nr 
			FROM ' . $this->table('Players') . '
			WHERE event_id=' . intval($eventId) . ' 
			  AND name IN(' . implode(',', $names) . ')
		');

		$result = array();
		foreach ($playerKNames as $k => $name)
			$result[$k] = null;
		foreach ($players as $player) {
			$nr = intval($player['nr']) - 1;
			if (!array_key_exists($nr, $result))
				continue; // should not be possible at all, in theory
			$result[$nr] = $player['id'];
		}

		return $result;
	}

	protected function getTournamentSyncOrigin($eventId)
	{
		$syncData = $this->db->single_query_assoc('
			SELECT t.sync_id tid
			FROM ' . $this->table('Events') . ' e
			INNER JOIN ' . $this->table('Tournaments') . ' t
				ON t.id=e.tournament_id
			WHERE e.id=' . intval($eventId) . '
		');
		$syncData = explode(':', $syncData['tid']);
		if (2 == count($syncData)) {
			$syncData[1] = intval($syncData[1]);
			return $syncData;
		}
		
		return NULL;
	}
	
	protected function getLogEntryRedirectable($id, $type)
	{
		$entry = $this->db->single_query_assoc('
			SELECT id, type, day_id, event_id, tournament_id, created_on, is_hidden
			FROM ' . $this->table('Log') . '
			WHERE id=' . intval($id) . ' AND type="' . $this->db->escape($type) . '"
		');
		if (empty($entry)) {
			return NULL;
		}
		return $entry;
	}
	
	protected function getSuccessivePostsCount($entry, $redirectToDayAll, $filterShow, $viewHidden)
	{
		$successiveCntWhere = array();
		
		if ($redirectToDayAll) {
			$successiveCntWhere[] = 'l.event_id=' . intval($entry['event_id']);
		} else {
			$successiveCntWhere[] = 'l.day_id=' . intval($entry['day_id']);
		}

		if (!empty($filterShow)) {
			$typeFilterData = $this->getAvailableFilterTypes();
			$typeFilter = array();
			$typeCandidates = explode(' ', $filterShow);
			foreach ($typeCandidates as $candidate) {
				if (isset($typeFilterData[$candidate])) {
					$typeFilter[] = "'" . $typeFilterData[$candidate] . "'";
				}
			}
			if (0 != count($typeFilter)) {
				$successiveCntWhere[] = 'l.type IN (' . implode(',', $typeFilter) . ')';
			}
		}

		if (!$viewHidden) {
			$successiveCntWhere[] = 'l.is_hidden=0';
		} else {
			$successiveCntWhere[] = 'l.is_hidden!=2';
		}

		$successiveCntWhere[] = 'created_on>' . $entry['created_on'];

		$before = $this->db->single_query_assoc('
			SELECT COUNT(l.id) cid
			FROM ' . $this->table('Log') . ' l
			WHERE ' . implode(' AND ', $successiveCntWhere));
		$before = $before['cid'];
		
		return $before;
	}
	
	protected function updateStatesOnLogEntryAdded($tournamentId, $eventId, $dayId)
	{
		$this->db->query('
			UPDATE ' . $this->table('Tournaments') . '
			SET state=1, updated_on=' . time() . '
			WHERE id=' . intval($tournamentId) . ' AND state=0
		');
		if ($this->db->affected_rows())
			$this->object('livereporting')->altLog($tournamentId, 0, 0, 'update', 'tournaments', $tournamentId, 'entryAdded:state=1');
		$this->db->query('
			UPDATE ' . $this->table('Events') . '
			SET state=1, updated_on=' . time() . '
			WHERE id=' . intval($eventId) . ' AND state=0
		');
		if ($this->db->affected_rows())
			$this->object('livereporting')->altLog($tournamentId, $eventId, 0, 'update', 'events', $eventId, 'entryAdded:state=1');
		// not changing day state, just emptyness (or else should insert entry to log)
		$this->db->query('
			UPDATE ' . $this->table('Days') . '
			SET is_empty=0, updated_on=' . time() . '
			WHERE id=' . intval($dayId) . ' AND is_empty=1
		');
	}
	
	protected function updateStatesOnLogEntryRemoved($tournamentId, $eventId, $dayId)
	{
		// if at least one post in day
		$check = $this->db->single_query_assoc('
			SELECT l.id FROM ' . $this->table('Log') . ' l
			WHERE l.tournament_id=' . intval($tournamentId) . ' AND l.event_id=' . intval($eventId) . ' 
				AND l.day_id=' . intval($dayId) . '
				AND l.is_hidden=0 AND l.type IN ("post", "tweet", "photos", "chips")
			LIMIT 1
		');
		if (!empty($check)) {
			return ;
		}
		$this->db->query('
			UPDATE ' . $this->table('Days') . '
			SET is_empty=1, updated_on=' . time() . '
			WHERE id=' . intval($dayId) . ' AND is_empty=0
		');
		$this->unsetDefaultDayCache($eventId);

		// if at least one post in event
		$check = $this->db->single_query_assoc('
			SELECT l.id FROM ' . $this->table('Log') . ' l
			WHERE l.tournament_id=' . intval($tournamentId) . ' AND l.event_id=' . intval($eventId) . '
				AND l.is_hidden=0 AND l.type IN ("post", "tweet", "photos", "chips")
			LIMIT 1
		');
		if (!empty($check)) {
			return ;
		}
		$this->db->query('
			UPDATE ' . $this->table('Events') . '
			SET state=0, updated_on=' . time() . '
			WHERE id=' . intval($eventId) . ' AND state=1
		');
		if ($this->db->affected_rows())
			$this->object('livereporting')->altLog($tournamentId, $eventId, 0, 'update', 'events', $eventId, 'entryRemoved:state=0');

		// if all events scheduled (in tournament)
		$check = $this->db->single_query_assoc('
			SELECT e.id FROM ' . $this->table('Events') . ' e
			WHERE e.tournament_id=' . intval($tournamentId) . '
				AND e.state!=0 AND e.is_live=1
			LIMIT 1
		');
		if (!empty($check)) {
			return ;
		}
		$this->db->query('
			UPDATE ' . $this->table('Tournaments') . '
			SET state=0, updated_on=' . time() . '
			WHERE id=' . intval($tournamentId) . ' AND state!=0
		');
		if ($this->db->affected_rows())
			$this->object('livereporting')->altLog($tournamentId, 0, 0, 'update', 'tournaments', $tournamentId, 'entryRemoved:state=0');
	}
}

/**
 * livereporting_model_event methods, accessed from ../livereporting_index component
 * @package livereporting
 * @subpackage models
 */
class livereporting_model_event_src_index extends livereporting_model_event
{
	function countUpdates($eventId, $dayId, $since)
		{ return parent::countUpdates($eventId, $dayId, $since); }
	function getDaysDefaultId($eventId)
		{ return parent::getDaysDefaultId($eventId); }
	function getDayData($eventId, $name)
		{ return parent::getDayData($eventId, $name); }
}

/**
 * livereporting_model_event methods, accessed from ../livereporting_event component
 * and some of it's subcomponents (chips, days, etc.)
 * @package livereporting
 * @subpackage models
 * @todo maybe split access from livereporting_event_* to other classes
 */
class livereporting_model_event_src_event extends livereporting_model_event
{
	function getDaysDefaultId($eventId)
		{ return parent::getDaysDefaultId($eventId); }
	function getDayData($eventId, $name)
		{ return parent::getDayData($eventId, $name); }
	function getEventData($eventId)
		{ return parent::getEventData($eventId); }
	function unsetDefaultDayCache($eventId)
		{ return parent::unsetDefaultDayCache($eventId); }
	function getLogEntries($eventId, $dayId, $filter = NULL, $limit = NULL)
		{ return parent::getLogEntries($eventId, $dayId, $filter, $limit); }
	function getLogEntriesCount($eventId, $dayId, $filter = NULL)
		{ return parent::getLogEntriesCount($eventId, $dayId, $filter); }
	function getLogEntry($eventId, $type, $id, $showHidden = FALSE)
		{ return parent::getLogEntry($eventId, $type, $id, $showHidden); }
	function getDaysData($eventId)
		{ return parent::getDaysData($eventId); }
	function getLiveEvents($tournamentId, $includeEmpty = FALSE)
		{ return parent::getLiveEvents($tournamentId, $includeEmpty); }
	function getLastRound($eventId, $dayId)
		{ return parent::getLastRound($eventId, $dayId); }
	function getWinner($eventId)
		{ return parent::getWinner($eventId); }
	function getLastPhotos($eventId)
		{ return parent::getLastPhotos($eventId); }
	function getLastTinyChips($eventId, $dayId)
		{ return parent::getLastChips($eventId, $dayId, self::chipsTiny); }
	function getLastTodayChips($eventId, $dayId)
		{ return parent::getLastChips($eventId, $dayId); }
	function getPayoutBundle($eventId)
		{ return parent::getPayoutBundle($eventId); }
	function getTournamentSyncOrigin($eventId)
		{ return parent::getTournamentSyncOrigin($eventId); }
	function getLogEntryRedirectable($id, $type)
		{ return parent::getLogEntryRedirectable($id, $type); }
	function getSuccessivePostsCount($entry, $redirectToDayAll, $filterShow, $viewHidden)
		{ return parent::getSuccessivePostsCount($entry, $redirectToDayAll, $filterShow, $viewHidden); }
	function updateStatesOnLogEntryAdded($tournamentId, $eventId, $dayId)
		{ return parent::updateStatesOnLogEntryAdded($tournamentId, $eventId, $dayId); }
	function updateStatesOnLogEntryRemoved($tournamentId, $eventId, $dayId)
		{ return parent::updateStatesOnLogEntryRemoved($tournamentId, $eventId, $dayId); }
	function dayParallel($eventId, $dayId)
		{ return parent::dayParallelIface($eventId, $dayId); }
	function getSponsorsById($ids)
		{ return parent::getSponsorsById($ids); }
	function getSponsors()
		{ return parent::getSponsors(); }
	function getKeyHandEntries($eventId)
		{ return parent::getKeyHandEntries($eventId); }
}

/**
 * livereporting_model_event methods, accessed from ../livereporting_event_post components
 * @package livereporting
 * @subpackage models
 */
class livereporting_model_event_src_event_post extends livereporting_model_event
{
	function getCurrentRound($eventId, $dayId)
		{ return parent::getCurrentRound($eventId, $dayId); }
	function getRound($eventId, $dayId, $timestamp)
		{ return parent::getRound($eventId, $dayId, $timestamp); }
}

/**
 * livereporting_model_event methods, accessed from ../livereporting_event_chips components
 * @package livereporting
 * @subpackage models
 */
class livereporting_model_event_src_event_chips extends livereporting_model_event
{
	function getPreviousChipsByPlayerId($eventId, $playerIds, $datetime)
		{ return parent::getPreviousChipsByPlayerId($eventId, $playerIds, $datetime); }
	function getPreviousChipsByPlayerName($eventId, $playerKNames, $datetime)
		{ return parent::getPreviousChipsByPlayerName($eventId, $playerKNames, $datetime); }
	function getPreviousChipsByDayDT($eventId, $dayId, $datetime)
		{ return parent::getPreviousChipsByDayDT($eventId, $dayId, $datetime); }
	function getDayChipsTimeCap($eventId, $dayId) 
		{ return parent::getDayChipsTimeCap($eventId, $dayId); }
	function getCurrentRound($eventId, $dayId)
		{ return parent::getCurrentRound($eventId, $dayId); }
	function getRound($eventId, $dayId, $timestamp)
		{ return parent::getRound($eventId, $dayId, $timestamp); }
}

/**
 * livereporting_model_event methods, accessed from ../livereporting_event_profile component
 * @package livereporting
 * @subpackage models
 */
class livereporting_model_event_src_event_profile extends livereporting_model_event
{
	function getSponsors()
		{ return parent::getSponsors(); }
	function getChipsHistory($playerId, $eventId)
		{ return parent::getChipsHistory($playerId, $eventId); }
}

/**
 * livereporting_model_event methods, accessed from ../livereporting_bluff component
 * @package livereporting
 * @subpackage models
 */
class livereporting_model_event_src_bluff extends livereporting_model_event
{
	function getDaysDefaultId($eventId)
		{ return parent::getDaysDefaultId($eventId); }
	function getChips($eventId, $dayId)
		{ return parent::getLastChips($eventId, $dayId); }
	function getSponsorsById($ids)
		{ return parent::getSponsorsById($ids); }
}

/**
 * livereporting_model_event methods, accessed from ../livereporting_shoutbox component
 * @package livereporting
 * @subpackage models
 */
class livereporting_model_event_src_shoutbox extends livereporting_model_event
{
	function getEventData($eventId, $more = false)
		{ return parent::getEventData($eventId, $more); }
}

/**
 * livereporting_model_event methods, accessed from ../../tags/* components
 * @package livereporting
 * @subpackage models
 */
class livereporting_model_event_src_tags extends livereporting_model_event
{
	function getLiveReportingEntries($ids) 
	{
		if (0 == count($ids)) {
			return array();
		}
		$where = array();
		foreach ($ids as $id) {
			$where[] = '(l.id=' . intval($id['id']) . ' AND l.type="' . addslashes($id['type']) . '")';
		}

		$entries = array();
		$entriesR = $this->db->query('
			SELECT l.id, l.type, l.created_on, l.event_id, l.tournament_id, l.contents
			FROM reporting_ng_log l
			INNER JOIN reporting_ng_days d
				ON d.id=l.day_id
			WHERE l.is_hidden=0 AND (' . implode(' OR ', $where) . ')
			  AND d.is_live=1
		');
		$eBase = $this->parent->instHierarchyModel()->getEventsUris();

		while ($entry = $this->db->fetch_row_assoc($entriesR)) {
			$entry['contents'] = unserialize($entry['contents']);
			if (!is_array($entry['contents'])) {
				continue;
			}
			if (!isset($eBase[$entry['event_id']])) {
				continue;
			}
			$entry_ = array(
				'id'      => $entry['id'],
				'type'    => $entry['type'],
				'title'   => $entry['contents']['title'],
				'contents'=> $entry['contents']['contents'],
				'date'    => $entry['created_on'],
				'url' => $this->parent->makeUri('event#view', array(
					'event_id' => $entry['event_id'],
					'type' => $entry['type'],
					'id' => $entry['id'],
				)),
				'img' => isset($entry['contents']['i_src'])
					? $this->get_var('ipnReadBase') . $entry['contents']['i_src']
					: NULL,
			);
			if (!$entry_['img']) {
				$entry_['img'] = $this->helperLiveReportingTourLogo($entry['tournament_id']);
			}
			$entries[] = $entry_;
		}

		return $entries;
	}

	private function helperLiveReportingTourLogo($tId)
	{
		static $map = null;
		if (!$map) {
			$map = $this->db->array_query_assoc('
				SELECT id, tour
				FROM reporting_ng_tournaments
			', 'id');

		}
		$pt = poker_tours();
		if (isset($pt[$map[$tId]['tour']])) {
			return $pt[$map[$tId]['tour']]['img1'];
		}
	}
}

/**
 * livereporting_model_event methods, accessed from other.mobileapp
 * @package livereporting
 * @subpackage models
 */
class livereporting_model_event_src_mobileapp extends livereporting_model_event
{
	function getPhotos($dayId)
		{ return parent::getMobileappPhotos($dayId); }
	function getEventData($eventId)
		{ return parent::getEventData($eventId); }
	function getLogEntries($eventId, $dayId, $filter = NULL, $limit = 5)
		{ return parent::getLogEntries($eventId, $dayId, $filter, 'LIMIT ' . intval($limit)); }
	function getChips($eventId, $dayId)
		{ return parent::getLastChips($eventId, $dayId); }
	function getSponsorsById($ids)
		{ return parent::getSponsorsById($ids); }

	function getDayEvent($dayId)
	{
		$data = $this->db->single_query_assoc('
			SELECT event_id FROM ' . $this->table('Days') . '
			WHERE id=' . filter_var($dayId, FILTER_VALIDATE_INT) . '
			    AND is_live>=0
		');
		if (empty($data)) {
			return NULL;
		}
		return $data['event_id'];
	}
}
