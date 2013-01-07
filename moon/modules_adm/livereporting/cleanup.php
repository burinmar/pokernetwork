<?php
set_time_limit(0);
class cleanup extends moon_com
{
	function events($event)
	{
		$this->use_page('Common');
		switch ($event) {
			/* case 'cleanup' :
				ob_start();
				$this->doCleanup();
				$page = & moon :: page();
				$page->set_local('cron', ob_get_contents());
				return; */

			case 'batch1':
				require_once 'sandbox.php';
				ob_start();
				$this->doBatch1();
				moon::page()->set_local('cron', ob_get_contents());
				return;

			case 'players-poker-rel':
				ob_start();
				$this->doCalcPPId();
				moon::page()->set_local('cron', ob_get_contents());
				return;
			
			/*case 'winners-list-to-players-places':
				$this->winnersListToPlayersPlaces();
				return ;*/

			case 'days-format-upgrade':
				$this->daysFormatUpgrade();
				return;

			/*case 'wsop-bluff-profile':
				ob_start();
				$days = isset($_GET['days'])
					? max(1, intval($_GET['days']))
					: 2;
				$this->doUpdateBluffCountry($days);
				moon::page()->set_local('cron', ob_get_contents());
				return;*/
			
			default:
				break;
		}
	}

	function doBatch1()
	{
		$this->clearTmpFiles();
		$this->releaseCursedPlayers();

		// $this->oldChipsPurge();

		echo "done";
	}

	private function clearTmpFiles()
	{
		$fs = new FS_CMD;

		echo "Tmp files:\n";
		$files = $fs->cd('tmp/cache/')->ls('lrep.bluff.*');
		foreach ($files as $file) {
			if (filemtime('tmp/cache/' . $file) < time() - 3600) {
				echo '-' . $file . "\n";
				unlink('tmp/cache/' . $file);
			}
		}
		if (count($files)) {
			livereporting_adm_alt_log(0, 0, 0, 'delete', 'other', '0', count($files) . ' tmp:lrep.bluff.*', -1);
		}

		echo "Lrep failed files:\n";
		$files = $fs->cd('tmp/')->ls('lrep*');
		foreach ($files as $file) {
			if (filemtime('tmp/' . $file) < time() - 24*3600) {
				echo '-' . $file . "\n";
				unlink('tmp/' . $file);
			}
		}
		if (count($files)) {
			livereporting_adm_alt_log(0, 0, 0, 'delete', 'other', '0', count($files) . ' tmp:failed:lrep*', -1);
		}
	}

	private function releaseCursedPlayers()
	{
		// delete players with no chips or winning place number
		$playersIds = array_keys($this->db->array_query_assoc('
			SELECT p.id, IFNULL(MAX(c.id),0) has_chips
			FROM reporting_ng_players p
			LEFT JOIN reporting_ng_chips c
				ON c.player_id=p.id
			WHERE p.place IS NULL
			  AND GREATEST(p.created_on, IFNULL(p.updated_on, 0)) BETWEEN UNIX_TIMESTAMP()-30*24*3600 AND UNIX_TIMESTAMP()-3*24*3600
			GROUP BY p.id
			HAVING has_chips=0
		', 'id'));
		if (0 == count($playersIds))
			return ;
		$this->db->query('
			DELETE FROM reporting_ng_players WHERE id IN (' . implode(',', $playersIds) . ')
		');
		echo 'Deleted ' . $this->db->affected_rows() . ' unused players. ';
	}

	/* private function winnersListToPlayersPlaces()
	{
		$events = $this->db->array_query_assoc('
			SELECT id FROM reporting_ng_events
			WHERE id NOT IN (
				SELECT event_id FROM (
					SELECT event_id, MAX(place) mpl FROM reporting_ng_players
					GROUP BY event_id
					HAVING mpl IS NOT NULL
				) `unprocessed_events`
			)
			GROUP BY id
		');
		foreach ($events as $event) {
			$eventId = $event['id'];
			$winners = $this->db->array_query_assoc('
				SELECT name, place, event_id, tournament_id FROM reporting_ng_winners_list
				WHERE event_id=' . $eventId . '
			');

			$names = array();
			foreach ($winners as $winner)
				$names[] = '"' . $this->db->escape($winner['name']) . '"';
			if (0 == count($names))
				continue;
			$eventPlayers = $this->db->array_query_assoc('
				SELECT id, FIELD(name, ' . implode(',', $names) . ') nr 
				FROM reporting_ng_players
				WHERE event_id=' . intval($eventId) . ' 
				  AND name IN(' . implode(',', $names) . ')
			');
			$result = array();
			foreach ($names as $k => $name)
				$result[$k] = null;
			foreach ($eventPlayers as $player) {
				$nr = intval($player['nr']) - 1;
				if (!array_key_exists($nr, $result))
					continue; // should not be possible at all, in theory
				$result[$nr] = $player['id'];
			}
			$eventPlayers = $result;

			$time = time();
			foreach ($winners as $k => $player) {
				$playerId = $eventPlayers[$k];

				if (null === $playerId && null != $player['name'] && 'Error' != $player['name']) {
					$playerId = $this->savePlayer($player['tournament_id'], $player['event_id'], $player['name']);
					if (null == $playerId)
						continue;
				}
				if (null === $playerId) 
					continue;

				$this->db->update(array(
					'place' => $player['place'],
					'updated_on' => $time + $player['event_id'],
				), 'reporting_ng_players', array(
					'id' => $playerId
				));
			}
		}
	}

	private function savePlayer($tournamentId, $eventId, $playerName)
	{
		$this->db->insert(array(
			'tournament_id' => $tournamentId,
			'event_id' => $eventId,
			'name' => $playerName,
			'created_on' => time(),
		), 'reporting_ng_players');
		$playerId = $this->db->insert_id();
		if (!$playerId) {
			return ; // can't distinguish between dupe error (which is ok) and others (which are not)
		}
		return $playerId;
	} */

	private function daysFormatUpgrade()
	{
		header('content-type: text/plain');
		$events = $this->db->array_query_assoc('
			SELECT DISTINCT event_id FROM reporting_ng_days
		');
		$upd = 0;
		foreach ($events as $event) {
			$days = $this->db->array_query_assoc('
				SELECT id, name FROM reporting_ng_days
				WHERE event_id=' . $event['event_id'] . '
				  AND is_live!=-1
			');
			usort($days, array($this, 'daysSortCmp'));

			$mush = $this->preprocessDays($days);
			$pdays = $this->assignMerges($mush);

			if (count($days) != count($pdays)) {
				print_R($days);
				print_R($pdays);
				echo '~';
				echo "\n\n";
			}

			foreach ($pdays as $day) {
				$this->db->update(array(
					'merge_name' => $day['merge_name']
				), 'reporting_ng_days', array(
					'id' => $day['id']
				));
				$upd += $this->db->affected_rows();
			}

		}

		echo $upd;

		moon_close();exit;
	}

	private function daysSortCmp($a, $b)
	{
		return strnatcasecmp($a['name'], $b['name']);
	}

	// loads of assumptions within
	private function preprocessDays($days)
	{
		$mush = array(
			'linear' => array(),
			'tree' => array(),
			'lost' => array(),
		);
		$return = array();

		foreach (array_reverse($days, true) as $k => $day) {
			$pday = $this->helperDayParse($day['name']);
			if (!isset($pday[2])) {
				$mush['lost'][] = $day;
				unset($days[$k]);
				continue;
			}
			if ('' !== $pday[2])
				break;
			array_unshift($mush['linear'], $day);
			unset($days[$k]);
		}

		foreach ($days as $day) {
			$pday = $this->helperDayParse($day['name']);
			$mush['tree'][$pday[1]][] = $day;
		}

		return $mush;
	}

	// // $bigDayCurrent = "number" of the milestone day (1a => 1, 2 => 2, null => 'all')
	private function helperDayParse($name)
	{
		// $name = str_ireplace('Day ', '', $name);
		preg_match('~^([0-9]+)([a-z]*)~i', $name, $bigDay);
		return $bigDay;
	}

	private function assignMerges($mush)
	{
		$days = array();

		// linear days: merge_name=next(day)
		foreach ($mush['linear'] as $k => $day) {
			$day['merge_name'] = isset($mush['linear'][$k+1])
				? $mush['linear'][$k+1]['name']
				: null;
			$days[] = $day;
		}

		if (!count($mush['linear']) || !count($mush['tree']))
			return $days;

		// last tree level merges into first linear day
		$lastTreeLevel = end($mush['tree']);
		foreach ($lastTreeLevel as $day) {
			$day['merge_name'] = $mush['linear'][0]['name'];
			$days[] = $day;
		}

		// disregard single "0" day
		if (isset($mush['tree']['0']) && count($mush['tree']['0'])) {
			$mush['tree']['0'][0]['merge_name'] = null;
			$days[] = $mush['tree']['0'][0];
			unset($mush['tree']['0']);
		}

		// if was 1 tree level, already done
		if (count($mush['tree']) == 1)
			return $days;

		// if not 2 tree levels ("1" and "2"), we don't know of that setup
		if (count($mush['tree']) != 2 || !isset($mush['tree']['1']) || !isset($mush['tree']['2']))
			return $days;

		$lvl2Days = array();
		foreach ($mush['tree']['2'] as $day)
			$lvl2Days[strtolower($day['name'])] = $day['name'];
		$lvl1Days = $mush['tree']['1'];
		foreach ($lvl1Days as $day) {
			switch (count($lvl1Days)) {
			case 4:
				switch (strtolower($day['name'])) {
				case '1a':
				case '1c':
					if (isset($lvl2Days['2a']))
						$day['merge_name'] = $lvl2Days['2a'];
					break;
				case '1b':
				case '1d':
					if (isset($lvl2Days['2b']))
						$day['merge_name'] = $lvl2Days['2b'];
					break;
				}
				break;
			case 3:
				switch (strtolower($day['name'])) {
				case '1a':
					if (isset($lvl2Days['2ab']))
						$day['merge_name'] = $lvl2Days['2ab'];
					if (isset($lvl2Days['2ac']))
						$day['merge_name'] = $lvl2Days['2ac'];
					if (isset($lvl2Days['2a']))
						$day['merge_name'] = $lvl2Days['2a'];
					break;
				case '1b':
					if (isset($lvl2Days['2ab']))
						$day['merge_name'] = $lvl2Days['2ab'];
					if (isset($lvl2Days['2bc']))
						$day['merge_name'] = $lvl2Days['2bc'];
					if (isset($lvl2Days['2b']))
						$day['merge_name'] = $lvl2Days['2b'];
					break;
				case '1c':
					if (isset($lvl2Days['2ac']))
						$day['merge_name'] = $lvl2Days['2ac'];
					if (isset($lvl2Days['2bc']))
						$day['merge_name'] = $lvl2Days['2bc'];
					if (isset($lvl2Days['2c']))
						$day['merge_name'] = $lvl2Days['2c'];
					break;
				}
				break;
			case 2:
				switch (strtolower($day['name'])) {
				case '1a':
					if (isset($lvl2Days['2a']))
						$day['merge_name'] = $lvl2Days['2a'];
					break;
				case '1b':
					if (isset($lvl2Days['2b']))
						$day['merge_name'] = $lvl2Days['2b'];
					break;
				}
				break;
			}
			if (isset($day['merge_name']))
				$days[] = $day;
		}

		return $days;
	}

	// purge redundant (invisible for regular user) chip counts
	private function oldChipsPurge()
	{
		try {
			$this->db->exceptions(true);
			$days = $this->db->array_query_assoc('SELECT id FROM reporting_ng_days WHERE state=2 AND day_date>' . (time() - 3*24*3600));
			foreach ($days as $k => $day) {
				$days[$k] = $day['id'];
			}
			natsort($days);
			if (0 == count($days))
				throw new Exception('No days');

			$this->db->query('
				CREATE TEMPORARY TABLE `_delchips_` (
				  `id` int(10) unsigned NOT NULL,
				  PRIMARY KEY (`id`)
				)
			');
			$this->db->query('
				INSERT INTO `_delchips_` (id)
				SELECT id FROM reporting_ng_chips
				WHERE day_id IN (' . implode(',', $days) . ')
				  AND is_hidden=0
			');

			$total = $this->db->single_query_assoc('SELECT COUNT(*)  cnt FROM `_delchips_`');
			$total = $total['cnt'];

			// selecting first player chip (implied per-event) and last player chips (per-day)
			$rdel = $this->db->array_query_assoc('
				SELECT c.id FROM (
					(
						SELECT player_id, MAX(created_on) created_on
						FROM reporting_ng_chips
						WHERE day_id IN (' . implode(',', $days) . ') 
						  AND is_hidden=0
						GROUP BY player_id, day_id
					)
						UNION
					(
						SELECT player_id, MIN(created_on) created_on
						FROM reporting_ng_chips
						WHERE day_id IN (' . implode(',', $days) . ') 
						  AND is_hidden=0
						GROUP BY player_id
					)
				) `x`
				INNER JOIN reporting_ng_chips c ON c.player_id=x.player_id AND c.created_on=x.created_on
			', 'id');
			$dids = array_keys($rdel);
			if (0 == count($dids))
				throw new Exception('Strange conditions, abort');

			// delete "meaningful" chips from temporary table
			$delete = $this->db->query('
				DELETE FROM _delchips_ WHERE id IN (' . implode(',', $dids) . ')
			');
			// stop on db error, otherwise all chips are deleted for selected days
			if (!$delete) // [1]
				throw new Exception('Scary');

			$extras = $this->db->single_query_assoc('SELECT COUNT(*) cnt FROM `_delchips_`');
			$extras = $extras['cnt'];
			echo 'Total: ' . $total . '; extras: ' . $extras . '; ';

			if ($total == $extras)
				throw new Exception('Sanity check failed'); // looks like the delete fail detection [1] slipped anyway

			// // delete what's left, where import_id is null
			$extras = $this->db->array_query_assoc('SELECT id FROM `_delchips_`', 'id');
			$extras = array_keys($extras);
			if (0 == count($extras))
				throw new Exception('Nothing ot do');;

			$dextras = $this->db->array_query_assoc('
				SELECT id FROM `reporting_ng_chips`
				WHERE id IN (' . implode(',', $extras) . ')
				  AND import_id IS NULL
			', 'id');
			$dextras = array_keys($dextras);
			if (0 == count($dextras))
				throw new Exception('Nothing to do'); ;

			echo 'deletable extras: ' . count($dextras) . ";\n";

			// $this->db->query('DELETE FROM reporting_ng_chips WHERE id IN (' . implode(',', $dextras) . ')');
			// $deleted = $this->db->affected_rows();
			// livereporting_adm_alt_log(0, 0, 0, 'delete', 'other', '0', 'cnt:' . $deleted, -1);
			// echo 'deleted: ' . $deleted . ";\n";
		} catch (Exception $e) {
			echo $e->getMessage() . ".\n";
		}
		$this->db->exceptions(false);
	}

	/*private function doUpdateBluffCountry($numDays)
	{
		$this->db->query('
			UPDATE reporting_ng_players p
			INNER JOIN (
				SELECT pb.name, pb.event_id, pb.country FROM reporting_ng_players_bluff pb
				INNER JOIN (
					SELECT DISTINCT event_id FROM reporting_ng_days WHERE GREATEST(created_on,updated_on)>(UNIX_TIMESTAMP()-' . intval($numDays) . '*86400)
				) d ON d.event_id=pb.event_id
			) pb
				ON p.event_id=pb.event_id AND p.name=pb.name
			SET p.country_id=pb.country
		');
		echo 'Updated: ' . $this->db->affected_rows();
	}*/

	/* if uncommenting apply alt_log()
	function doCleanup()
	{
		$delete = isset($_GET['delete'])
			?1:0;
		$cnt = $this->db->query('
			' . ($delete ? 'DELETE' : 'SELECT COUNT(*) cnt') . ' FROM reporting_ng_events WHERE tournament_id NOT IN (
				SELECT id FROM reporting_ng_tournaments
			)
		');
		if (!$delete) {
			$cnt = $this->db->fetch_row_assoc($cnt);
		}
		echo 'Lost events: ' . ($delete ? $this->db->affected_rows() : $cnt['cnt']) . "\n";

		$cnt = $this->db->query('
			' . ($delete ? 'DELETE' : 'SELECT COUNT(*) cnt') . ' FROM reporting_ng_days WHERE event_id NOT IN (
				SELECT id FROM reporting_ng_events
			)
		');
		if (!$delete) {
			$cnt = $this->db->fetch_row_assoc($cnt);
		}
		echo 'Lost days: ' . ($delete ? $this->db->affected_rows() : $cnt['cnt']) . "\n";

		$cnt = $this->db->query('
			' . ($delete ? 'DELETE' : 'SELECT COUNT(*) cnt') . ' FROM reporting_ng_log WHERE day_id NOT IN (
				SELECT id FROM reporting_ng_days
			)
		');
		if (!$delete) {
			$cnt = $this->db->fetch_row_assoc($cnt);
		}
		echo 'Lost logs: ' . ($delete ? $this->db->affected_rows() : $cnt['cnt']) . "\n";

		foreach (array(
			'chips' => 'chips',
			'day' => 'days',
			'photos' => 'photos',
			'post' => 'posts',
			'round' => 'rounds') as $typeK => $typeV) {
			$cnt = $this->db->query('
				' . ($delete ? 'DELETE' : 'SELECT COUNT(*) cnt') . ' FROM reporting_ng_sub_' . $typeV . ' WHERE id NOT IN (
					SELECT id FROM reporting_ng_log WHERE TYPE="' . $typeK . '"
				)
			');
			if (!$delete) {
				$cnt = $this->db->fetch_row_assoc($cnt);
			}
			echo 'Lost ' . $typeV . ': ' . ($delete ? $this->db->affected_rows() : $cnt['cnt']) . "\n";
		}
		
		// @todo tags, ext

		echo 'done';
	}*/

	private function updatePlayerPPRels($id, $name)
	{
		static $cachedId = array();
		static $cachedStored = array();
		if (!isset($cachedStored[$name])) {
			$player = $this->db->single_query_assoc('
				SELECT id FROM `players_poker`
				WHERE title="' . $this->db->escape($name) . '"
					OR find_in_set("' . $this->db->escape($name) . '", alternative_names)
			');
			$cachedId[$name] = !empty($player['id'])
				? $player['id']
				: NULL;
			$cachedStored[$name] = true;
		}
		$this->db->update(array(
			'pp_id' => $cachedId[$name]
		), 'reporting_ng_players', array(
			'id' => $id
		));
		if ($this->db->affected_rows()) {
			return true;
		}
	}

	private function doCalcPPId()
	{
		$cnt = 0;
		$players = $this->db->query('
			SELECT id, name FROM `reporting_ng_players`
			ORDER BY id DESC
		');
		while ($player = $this->db->fetch_row_assoc($players)) {
			$res = $this->updatePlayerPPRels($player['id'], $player['name']);
			if ($res) {
				$cnt++;
			}
		}
		echo 'updated ' . $cnt . ' relations';
	}
}


