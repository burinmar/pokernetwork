<?php
/**
 * @package livereporting
 */
/**
 */
require_once 'livereporting_model_pylon.php';
/**
 * Tour-related data
 *
 * All methods should be protected, and overridden in ancestor classes. This helps in filtering out numerous 
 * data accesses, and check if requesters still receive what they expect to, after changes are made.
 * @package livereporting
 * @subpackage models
 */
class livereporting_model_tour extends livereporting_model_pylon
{
	/**
	 * Return visible tour metadata
	 * @param <int> $id
	 * @return mixed
	 */
	protected function getTour($id)
	{
		$result = $this->db->single_query_assoc('
			SELECT meta_title,meta_keywords,meta_description,about_html,news_tag
			FROM ' . $this->table('Tours') . '
			WHERE id=' . getInteger($id) . ' AND is_hidden=0
		');
		return !empty($result)
			? $result
			: NULL;
	}
	
	/**
	 * Return visible tours data
	 * @return array
	 */
	protected function getTours()
	{
		$tours = poker_tours();
		$result = $this->db->array_query_assoc('
			SELECT id
			FROM ' . $this->table('Tours') . '
			WHERE is_hidden = 0
		');
		$items = array();
		foreach($result as $r) {
			$items[$r['id']] = $r;
		}
		return array_intersect_assoc($tours, $items);
	}	
	
	/**
	 * Get tour tournaments, with winners of main events, etc.
	 * @param <int> $tourId
	 * @return mixed union of 2 arrays
	 */
	protected function getTournamentsSummary($tourId)
	{
		$tournaments = $this->db->array_query_assoc('
			SELECT t.id, e.id event_id, t.from_date, t.name, t.place, t.address, t.state, t.currency, t.duration, w.winner, w.winning_hand, w.runner_up, w.losing_hand, w.prize
			FROM (
				SELECT id, tournament_id
				FROM ' . $this->table('Events') . '
				ORDER BY is_main DESC, from_date DESC
			) e
			INNER JOIN ' . $this->table('Tournaments') . ' t
				ON t.id=e.tournament_id
			LEFT JOIN ' . $this->table('Winners') . ' w
				ON w.event_id=e.id
			WHERE t.is_live=1 AND t.tour=' . getInteger($tourId) . '
			GROUP BY t.id
			ORDER BY from_date DESC'
		);
		
		// get all players mentioned
		$playersNameList = array();
		foreach ($tournaments as $k => $tournament) {
			if (!empty($tournament['winner'])) {
				$playersNameList[] = $this->db->escape($tournament['winner']);
			}
			if (!empty($tournament['runner_up'])) {
				$playersNameList[] = $this->db->escape($tournament['runner_up']);
			}
		}
		
		return array(
			$tournaments, 
			$this->getPokerPlayersByName($playersNameList)
		);
	}
	
	/**
	 * @param array $players 
	 * @return mixed null or array
	 */
	private function getPokerPlayersByName($players = array())
	{
		if (0 == count($players)) {
			return ;
		}
		$sql = '
			SELECT id, title, uri, img
			FROM ' . $this->table('PlayersPoker') . '
			WHERE title IN ("' . implode('","', $players) . '") 
				AND hidden = 0';
		$result = $this->db->array_query_assoc($sql);
		
		$items = array();
		foreach ($result as $row) {
			$items[$row['title']] = $row;
		}
		return $items;
	}
}

/**
 * livereporting_model_tour methods, accessed from ../livereporting_tour component
 * @package livereporting
 * @subpackage models
 */
class livereporting_model_tour_src_tour extends livereporting_model_tour
{
	function getTour($id)
		{ return parent::getTour($id); }
	function getTours()
		{ return parent::getTours(); }
	function getTournamentsSummary($tourId)
		{ return parent::getTournamentsSummary($tourId); }
}