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
			
			case 'wsop-bluff-profile':
				ob_start();
				$days = isset($_GET['days'])
					? max(1, intval($_GET['days']))
					: 2;
				$this->doUpdateBluffCountry($days);
				moon::page()->set_local('cron', ob_get_contents());
				return;
			
			default:
				break;
		}
	}

	function doBatch1()
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

		// $this->oldChipsPurge();

		echo "done";
	}

	// purge redundant (invisible for regular user) chip counts
	function oldChipsPurge()
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

	private function doUpdateBluffCountry($numDays)
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
	}

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


