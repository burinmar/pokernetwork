<?php
/**
 * @package livereporting
 */
/**
 */
require_once 'livereporting_model_pylon.php';
/**
 * Provides tournament/event uri data
 * @package livereporting
 * @subpackage models
 */
class livereporting_model_hierarchy extends livereporting_model_pylon
{
	/**
	 * [id, alias] list of available tournaments
	 * 
	 * Stored using memcache for an hour
	 * @return array
	 */
	function getTorunamentsUris()
	{
		static $data = NULL;
		if ($data === NULL) {
			$cache = moon::cache('memcache');
			if (($data = $cache->get('reporting.tourns_uris')) === FALSE) {
				// live,id index
				$data = array();
				$rData = $this->db->query('
					SELECT id, alias FROM ' . $this->table('Tournaments') . '
					WHERE is_live=1
				');
				while ($row = $this->db->fetch_row_assoc($rData)) {
					if (!empty($row['alias'])) {
						$data[$row['id']] = $row['alias'];
					}
				}
				krsort($data);
				if (0 != count($data)) {
					$cache->save('reporting.tourns_uris', $data, 60);
				}
			}
		}
		return $data;
	}

	/**
	 * [id, tournament_id, alias] list of available events
	 * 
	 * Stored using memcache for an hour
	 * @return array
	 */
	function getEventsUris()
	{
		static $data = NULL;
		if ($data === NULL) {
			$cache = moon::cache('memcache');
			if (($data = $cache->get('reporting.events_uris')) === FALSE) {
				// live,id index
				$rData = $this->db->query('
					SELECT id, tournament_id, alias FROM ' . $this->table('Events') . '
					WHERE is_live=1 
				');
				$data = array();
				while ($row = $this->db->fetch_row_assoc($rData)) {
					if (NULL == $row['alias'] || '' == $row['alias']) {
						continue;
					}
					$data[$row['id']] = array(
						$row['tournament_id'],
						$row['alias']
					);
				}
				krsort($data);
				if (0 != count($data)) {
					$cache->save('reporting.events_uris', $data, 60);
				}
			}
		}

		return $data;
	}
}