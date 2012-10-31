<?php
/**
 * @package livereporting
 */
/**
 */
require_once 'livereporting_model_pylon.php';
/**
 * Tournament-related data
 *
 * All methods should be protected, and overridden in ancestor classes. This helps in filtering out numerous 
 * data accesses, and check if requesters still receive what they expect to, after changes are made.
 * @package livereporting
 * @subpackage models
 */
class livereporting_model_tournament extends livereporting_model_pylon
{
	protected function getTournament($id)
	{
		$data = $this->db->single_query_assoc('
			SELECT name, logo_mid logo, skin, tour, currency, intro, timezone,
				sync_id sync_origin, ad_rooms, autopublish
			FROM ' . $this->table('Tournaments') . '
			WHERE id=' . filter_var($id, FILTER_VALIDATE_INT) . '
		');
		if (0 == count($data)) {
			return NULL;
		}
		$data['sync_origin'] = explode(':', $data['sync_origin']);
		$data['sync_origin'] = $data['sync_origin'][0];
		return $data;
	}

	protected function getScheduledAndRunningEvents($tournamentId)
	{
		return $this->db->array_query_assoc('
			SELECT e.id, e.name, e.from_date, e.to_date, e.state
			FROM ' . $this->table('Events') . ' e
			INNER JOIN  ' . $this->table('Days') . ' d
				ON d.event_id=e.id
			WHERE e.tournament_id=' . filter_var($tournamentId, FILTER_VALIDATE_INT) . '
				AND e.is_live=1
				AND (e.state=0 OR e.state=1)
				AND d.is_live=1
			GROUP BY e.id
			ORDER BY e.state DESC, e.from_date, e.id
		');
	}

	// also see getEventsWithBluff
	protected function getFinishedEvents($tournamentId)
	{
		return $this->db->array_query_assoc('
			SELECT e.id, e.name, e.from_date, e.to_date, MIN(d.is_empty) is_empty, w.winning_hand, w.losing_hand,
				w.winner, w.runner_up, w.prize, pw.id winner_id, pw.uri winner_uri, pl.uri runner_up_uri,
				pw.img winner_img
			FROM ' . $this->table('Events') . ' e
			INNER JOIN  ' . $this->table('Days') . ' d
				ON d.event_id=e.id
			LEFT JOIN ' . $this->table('Winners') . ' w
				ON e.id = w.event_id AND w.winner!=""
			LEFT JOIN ' . $this->table('PlayersPoker') . ' pw
				ON pw.title=w.winner AND pw.hidden=0
			LEFT JOIN ' . $this->table('PlayersPoker') . ' pl
				ON pl.title=w.runner_up AND pl.hidden=0
			WHERE e.tournament_id=' . filter_var($tournamentId, FILTER_VALIDATE_INT) . '
				AND e.is_live=1
				AND e.state=2
				AND d.is_live=1
			GROUP BY e.id
			ORDER BY e.from_date DESC, e.id DESC
		');
	}

	// also see getFinishedEvents
	protected function getEventsWithBluff($tournamentId)
	{
		return $this->db->array_query_assoc('
			SELECT e.id, e.bluff_id, e.name, e.from_date, e.to_date, MIN(d.is_empty) is_empty, w.winning_hand, w.losing_hand,
				w.winner, w.runner_up, w.prize, pw.id winner_id, pw.uri winner_uri, pl.uri runner_up_uri,
				pw.img winner_img
			FROM ' . $this->table('Events') . ' e
			INNER JOIN  ' . $this->table('Days') . ' d
				ON d.event_id=e.id
			LEFT JOIN ' . $this->table('Winners') . ' w
				ON e.id = w.event_id AND w.winner!=""
			LEFT JOIN ' . $this->table('PlayersPoker') . ' pw
				ON pw.title=w.winner AND pw.hidden=0
			LEFT JOIN ' . $this->table('PlayersPoker') . ' pl
				ON pl.title=w.runner_up AND pl.hidden=0
			WHERE e.tournament_id=' . filter_var($tournamentId, FILTER_VALIDATE_INT) . '
				AND e.is_live=1
				AND d.is_live=1
				AND e.bluff_id IS NOT NULL
			GROUP BY e.id
			ORDER BY e.from_date DESC, e.id DESC
		');
	}

	protected function getArchivedTournamentsCount()
	{
		$cnt = $this->db->single_query_assoc('
			SELECT COUNT(x.id) cid FROM (
				SELECT t.id id
				FROM ' . $this->table('Tournaments') . ' t
				INNER JOIN ' . $this->table('Events') . ' e
					ON e.tournament_id=t.id
				INNER JOIN  ' . $this->table('Days') . ' d
					ON d.event_id=e.id
				WHERE t.state=2 AND t.is_live=1
					AND e.is_live=1
					AND d.is_live=1
				GROUP BY t.id
			) x
		');
		return $cnt['cid'];
	}

	/**
	 * Returns tournament/event list for archive
	 * @param <string> $limit
	 * @return <array>
	 */
	protected function getArchivedTournaments($limit)
	{
		$tree = $this->db->array_query_assoc('
			SELECT t.id, t.name, t.logo_small logo, t.skin, t.tour
			FROM ' . $this->table('Tournaments') . ' t
			INNER JOIN ' . $this->table('Events') . ' e
				ON e.tournament_id=t.id
			INNER JOIN  ' . $this->table('Days') . ' d
				ON d.event_id=e.id
			WHERE t.state=2 AND t.is_live=1
				AND e.is_live=1
				AND d.is_live=1
			GROUP BY t.id
			ORDER BY t.from_date DESC ' . $limit
		, 'id');
		if (0 == count($tree)) {
			return $tree;
		}
		foreach ($tree as $k => $tournament) {
			$tree[$k]['events'] = array();
		}

		$events = $this->db->array_query_assoc('
			SELECT e.id, e.tournament_id tid, e.name, MIN(d.is_empty) is_empty
			FROM ' . $this->table('Events') . ' e
			INNER JOIN  ' . $this->table('Days') . ' d
				ON d.event_id=e.id
			WHERE e.tournament_id IN (' . implode(',', array_keys($tree)) . ')
				AND e.is_live=1
				AND d.is_live=1
			GROUP BY e.id
			ORDER BY e.from_date DESC'
			, 'id');
		foreach ($events as $event) {
			$tree[$event['tid']]['events'][] = $event;
		}
		return $tree;
	}

	/**
	 * Get the currently running tournaments, by default including the running events and post snippets. 
	 * 
	 * @todo kill this with fire, review
	 * @param <bool> $fetchRuningEvents Include the running events info
	 * @param <bool> $onEmptyUseLast If set to false, will only return the tournaments which are actually running. Else, the last completed tournament will also be considered.
	 * @param <bool> $onEmptyEventUseLast If set to false, will only return events which are actually running. Else, the last completed event will also be used for otherwise empty tournaments
	 * @param <bool> $fetchPostSnippets Return post snippets. Depends on $fetchRuningEvents=true
	 * @return <array> 
	 */
	protected function getRunningTournaments($fetchRuningEvents = TRUE, $onEmptyUseLast = TRUE, $onEmptyEventUseLast = TRUE, $fetchPostSnippets = TRUE)
	{
		$tours = $this->db->array_query_assoc('
			SELECT id, sync_id, name, logo_idx, logo_small, logo_bgcolor, logo_is_dark, logo_big_bg bg, skin, tour, 1 livecov, timezone
			FROM ' . $this->table('Tournaments') . '
			WHERE is_live=1 AND state=1
			ORDER BY priority DESC, from_date DESC'
		, 'id');
		if (count($tours) == 0) {
			if (!$onEmptyUseLast) {
				return array();
			}
			$tours = $this->db->array_query_assoc('
				SELECT id, name, logo_idx, logo_small, logo_bgcolor, logo_is_dark, logo_big_bg bg, skin, tour, 0 livecov, timezone
				FROM ' . $this->table('Tournaments') . '
				WHERE is_live=1 AND state=2
				ORDER BY from_date DESC, id DESC
				LIMIT 1'
			, 'id');
		}
		foreach ($tours as $k => $tournament) {
			$tours[$k]['running_events'] = array();
		}
		if ($fetchRuningEvents) {
			$runningEvents = $this->helperRunningTournamentEx(array_keys($tours), $onEmptyEventUseLast, !$fetchPostSnippets);
			foreach ($runningEvents as $event) {
				if (count($tours[$event['tid']]['running_events']) < 8) {
					$tours[$event['tid']]['running_events'][] = $event;
				}
			}
			if (!$onEmptyEventUseLast) {
				foreach ($tours as $k => $tournament) {
					if (0 == count($tours[$k]['running_events'])) {
						unset($tours[$k]);
					}
				}
			}
		}
		return $tours;
	}

	/**
	 * Events and post snippets for getRunningTournaments()
	 * @param <array> $tournamentIds
	 * @param <bool> $skipPost
	 * @return <array>
	 */
	private function helperRunningTournamentEx($tournamentIds, $onEmptyEventUseLast, $skipPost = false)
	{
		if (empty($tournamentIds)) {
			return array();
		}
		// @warn limit is no good when several tournaments are running
		$events = $this->db->array_query_assoc('
			SELECT e.id, e.tournament_id tid, e.name, e.players_left, e.players_total, e.chipspool
			FROM ' . $this->table('Events') . ' e
			INNER JOIN  ' . $this->table('Days') . ' d
				ON d.event_id=e.id
			WHERE e.tournament_id IN (' . implode(',', $tournamentIds) . ')
				AND e.state=1
				AND e.is_live=1
				AND d.is_live=1
			GROUP BY e.id
			ORDER BY e.from_date DESC, e.id DESC' .
			(count($tournamentIds) == 1
				? ' LIMIT 8'
				: ''
			)
			, 'id');

		$emptyTournaments = array_flip($tournamentIds);
		foreach ($events as $event) {
			if (isset($emptyTournaments[$event['tid']])) {
				unset ($emptyTournaments[$event['tid']]);
			}
		}
		$emptyTournaments = array_flip($emptyTournaments);
		if (0 != count($emptyTournaments) && $onEmptyEventUseLast) {
			$mEvents = $this->db->array_query_assoc('
				SELECT * FROM (
					SELECT e.id, e.tournament_id tid, e.name, e.players_left, e.players_total, e.chipspool
					FROM ' . $this->table('Events') . ' e
					INNER JOIN  ' . $this->table('Days') . ' d
						ON d.event_id=e.id
					WHERE e.tournament_id IN (' . implode(',', $emptyTournaments) . ')
						AND e.state=2
						AND e.is_live=1
						AND d.is_live=1
					GROUP BY e.id
					ORDER BY e.is_main DESC, e.from_date DESC
				) x
				GROUP BY x.tid
			', 'id');
			foreach ($mEvents as $id => $mEvent) {
				$events[$id] = $mEvent;
			}
		}

		foreach ($events as $k => $event) {
			$events[$k]['dname'] = NULL;
			$events[$k]['post'] = NULL;
		}

		if (0 == count($events) || $skipPost) {
			return $events;
		}

		$posts = $this->db->array_query_assoc('
			SELECT l.id, l.type, l.event_id, l.created_on, l.contents, d.name dname, d.state dstate FROM (
				SELECT event_id, MAX(created_on) mincreated
				FROM  ' . $this->table('Log') . '
				WHERE event_id IN (' . implode(',', array_keys($events)) . ') 
					AND is_hidden=0
					AND type IN ("post","photos","chips")
					AND created_on<' . (ceil(time() / 60) * 60) . '
				GROUP BY event_id
			) l2
			INNER JOIN ' . $this->table('Log') . ' l
				ON l.event_id=l2.event_id
				AND l.created_on=l2.mincreated
			INNER JOIN ' . $this->table('Days') . ' d
				ON l.day_id=d.id
		');
		foreach ($posts as $post) {
			$events[$post['event_id']]['dname'] = $post['dname'];
			$events[$post['event_id']]['dstate'] = $post['dstate'];
			unset ($post['dname']);
			unset ($post['dstate']);
			$events[$post['event_id']]['post'] = $post;
		}
		return $events;
	}

	/**
	 * Returns some basic info about upcoming tournaments. 
	 * 
	 * Limited to 4 items.
	 * @return <array> 
	 */
	protected function getUpcomingTournaments($limit)
	{
		return $this->db->array_query_assoc('
			SELECT id, name, logo_mid logo, skin, tour, from_date, timezone
			FROM ' . $this->table('Tournaments') . '
			WHERE state=0 AND is_live=1 AND from_date>' . (round(time() / 60)*60 - 3600*24)  . '
			ORDER BY from_date
			LIMIT ' . intval($limit)
		, 'id');
	}

	protected function getUpcomingTournamentsByNearestEvent($limit)
	{
		return $this->db->array_query_assoc('
			SELECT id, name, logo_small logo, skin, tour, e.from_date, timezone
			FROM ' . $this->table('Tournaments') . ' t
			INNER JOIN (
				SELECT tournament_id, MIN(from_date) from_date
				FROM ' . $this->table('Events') . '
				WHERE state=0 AND is_live=1
				GROUP BY tournament_id
			) e ON e.tournament_id = t.id
			WHERE state=0 AND is_live=1 AND e.from_date>' . (round(time() / 60)*60 - 3600*24)  . '
			ORDER BY from_date
			LIMIT ' . intval($limit)
		, 'id');
	}

	/**
	 * Returns upcoming events of the specified tournaments
	 * @param <array> $tournamentIds Torunament ids
	 * @return <array>
	 */
	protected function helperGetUpcomingEvents($tournamentIds)
	{
		if (0 == count($tournamentIds)) {
			return array();
		}
		return $this->db->array_query_assoc('
			SELECT e.id, e.tournament_id tid, e.name, e.from_date, e.to_date
			FROM ' . $this->table('Events') . ' e
			INNER JOIN  ' . $this->table('Days') . ' d
				ON d.event_id=e.id
			WHERE e.tournament_id IN (' . implode(',', $tournamentIds) . ') AND e.state=0 AND e.is_live=1
			GROUP BY e.id
			ORDER BY e.from_date, e.id
		');
	}

	protected function getTournamentsEventsSyncTree()
	{
		$tournaments = $this->db->array_query_assoc('
			SELECT t.id, t.name, t.from_date, t.is_live, t.state, t.timezone, t.duration
			FROM ' . $this->table('Tournaments') . ' t
			WHERE t.is_live!=-1 AND t.state IN (0,1)
			ORDER BY t.from_date DESC, t.id DESC
			LIMIT 50
		', 'id');
		if (0 == count($tournaments)) {
			return array();
		}

		$tIds = array_keys($tournaments);
		foreach ($tournaments as $k => $tournament) {
			$tournaments[$k]['events'] = array();
		}
		
		$events = $this->db->array_query_assoc('
			SELECT e.id, e.tournament_id, e.name, e.is_main
			FROM ' . $this->table('Events') . ' e
			WHERE e.is_live!=-1 AND e.tournament_id IN(' . implode(',', $tIds) . ')
			ORDER BY e.from_date DESC, e.id DESC
		');
		foreach ($events as $k => $event) {
			$tournaments[$event['tournament_id']]['events'][] = $event;
		}
		
		return $tournaments;
	}
	
	protected function getExportWinners()
	{
		return $this->db->array_query_assoc('
			SELECT t.name tournament_name, e.name event_name, w.winner, w.runner_up, w.winning_hand, w.losing_hand, w.prize
			FROM ' . $this->table('Winners') . ' w
			INNER JOIN ' . $this->table('Events') . ' e
				ON e.id=w.event_id
			INNER JOIN ' . $this->table('Tournaments') . ' t
				ON t.id=w.tournament_id
		');
	}
	
	protected function getExportEvents($tournamentId)
	{
		return $this->db->array_query_assoc('
			SELECT e.id, e.name, e.from_date, e.to_date
			FROM ' . $this->table('Events') . ' e
			INNER JOIN  ' . $this->table('Days') . ' d
				ON d.event_id=e.id
			WHERE e.tournament_id=' . filter_var($tournamentId, FILTER_VALIDATE_INT) . '
				AND e.is_live=1
				AND d.is_live=1
			GROUP BY e.id
			ORDER BY e.from_date DESC
		');
	}
	
	protected function getRunningMobileappTree()
	{
		$tournaments = $this->db->array_query_assoc('
			SELECT id, name title, "" img, 1 live, timezone, logo_mobile_1 img1, logo_mobile_2 img2
			FROM ' . $this->table('Tournaments') . '
			WHERE is_live=1 AND state=1
			ORDER BY priority DESC, from_date DESC'
		, 'id');
		$tournaments += $this->db->array_query_assoc('
			SELECT id, name title, "" img, 0 live, timezone, logo_mobile_1 img1, logo_mobile_2 img2
			FROM ' . $this->table('Tournaments') . '
			WHERE is_live=1 AND state=2
			ORDER BY from_date DESC
			LIMIT 1'
		, 'id');

		$pathM1 = $this->get_dir('web:LogosM1');
		$pathM2 = $this->get_dir('web:LogosM2');
		foreach ($tournaments as $key => $_) {
			$tournaments[$key]['events'] = array();
			if ('' != $tournaments[$key]['img1'])
				$tournaments[$key]['img1'] = $pathM1 . $tournaments[$key]['img1'];
			if ('' != $tournaments[$key]['img2'])
				$tournaments[$key]['img2'] = $pathM2 . $tournaments[$key]['img2'];

		}
		$events = $this->getRunningMobileappTreeEvents(array_keys($tournaments));
		foreach ($events as $_ => $event) {
			$_ = $event['tid'];
			unset($event['tid']);
			$tournaments[$_]['events'][] = $event;
		}

		return $tournaments;
	}

	private function getRunningMobileappTreeEvents($tournamentIds)
	{
		if (empty($tournamentIds)) {
			return array();
		}
		$events = $this->db->array_query_assoc('
			SELECT e.tournament_id tid, e.id, e.name title, e.state=1 live, e.state=0 upcomming, e.from_date, e.to_date, "" date
			FROM ' . $this->table('Events') . ' e
			INNER JOIN  ' . $this->table('Days') . ' d
				ON d.event_id=e.id
			WHERE e.tournament_id IN (' . implode(',', $tournamentIds) . ')
				AND e.is_live=1
				AND d.is_live=1
			GROUP BY e.id
			ORDER BY e.from_date DESC, e.id DESC',
		'id');

		foreach ($events as $key => $_) {
			$events[$key]['days'] = array();
		}
		$days = $this->getRunningMobileappTreeDays(array_keys($events));
		foreach ($days as $_ => $day) {
			$_ = $day['eid'];
			unset($day['eid']);
			$events[$_]['days'][] = $day;
		}

		return $events;
	}

	private function getRunningMobileappTreeDays($eventIds)
	{
		if (empty($eventIds)) {
			return array();
		}
		$days = $this->db->array_query_assoc('
			SELECT event_id eid, id, name title, day_date `date`, state=1 live, state=0 upcomming, is_empty=0 has_posts
			FROM ' . $this->table('Days') . '
			WHERE event_id IN (' . implode(',', $eventIds) . ')
				AND is_live=1
			ORDER BY day_date DESC, id DESC',
		'id');

		return $days;
	}
}

/**
 * livereporting_model_tournament methods, accessed from ../livereporting_index component
 * @package livereporting
 * @subpackage models
 */
class livereporting_model_tournament_src_index extends livereporting_model_tournament {
	function getRunningTournaments($fetchRuningEvents = TRUE, $onEmptyUseLast = TRUE, $onEmptyEventUseLast = TRUE, $fetchPostSnippets = TRUE)
		{ return parent::getRunningTournaments($fetchRuningEvents, $onEmptyUseLast, $onEmptyEventUseLast, $fetchPostSnippets); }

	function getUpcomingTournaments($limit = 4) 
		{ return parent::getUpcomingTournaments($limit); }
		
	function getUpcomingTournamentsByNearestEvent($limit = 4) 
		{ return parent::getUpcomingTournamentsByNearestEvent($limit); }
		
	function helperGetUpcomingEvents($tournamentIds) 
		{ return parent::helperGetUpcomingEvents($tournamentIds); }
		
	function getTournament($id)
		{ return parent::getTournament($id); }

	function getArchivedTournamentsCount()
		{ return parent::getArchivedTournamentsCount(); }

	function getArchivedTournaments($limit)
		{ return parent::getArchivedTournaments($limit); }
		
	function getTournamentsEventsSyncTree()
		{ return parent::getTournamentsEventsSyncTree(); }
	
	function getExportWinners()
		{ return parent::getExportWinners(); }
	
	function getExportEvents($tournamentId)
		{ return parent::getExportEvents($tournamentId); }
}

/**
 * livereporting_model_tournament methods, accessed from ../livereporting_tour component
 * @package livereporting
 * @subpackage models
 */
class livereporting_model_tournament_src_tour extends livereporting_model_tournament {
	function getRunningTournaments($fetchRuningEvents = TRUE, $onEmptyUseLast = TRUE, $onEmptyEventUseLast = TRUE, $fetchPostSnippets = TRUE)
		{ return parent::getRunningTournaments($fetchRuningEvents, $onEmptyUseLast, $onEmptyEventUseLast, $fetchPostSnippets); }
}

/**
 * livereporting_model_tournament methods, accessed from ../livereporting_tournament component
 * @package livereporting
 * @subpackage models
 */
class livereporting_model_tournament_src_tournament extends livereporting_model_tournament
{
	function getTournament($id)
		{ return parent::getTournament($id); }

	function getScheduledAndRunningEvents($tournamentId)
		{ return parent::getScheduledAndRunningEvents($tournamentId); }

	function getFinishedEvents($tournamentId)
		{ return parent::getFinishedEvents($tournamentId); }
}

/**
 * livereporting_model_tournament methods, accessed from ../livereporting_bluff component
 * @package livereporting
 * @subpackage models
 */
class livereporting_model_tournament_src_bluff extends livereporting_model_tournament
{
	function getTournamentBluffableEvents($tournamentId)
		{ return parent::getEventsWithBluff($tournamentId); }
}

/**
 * livereporting_model_tournament methods, accessed from other.mobileapp
 * @package livereporting
 * @subpackage models
 */
class livereporting_model_tournament_src_mobileapp extends livereporting_model_tournament
{
	function getTree()
		{ return parent::getRunningMobileappTree(); }
}
