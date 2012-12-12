<?php

class upgrade extends moon_com
{
	function events()
	{
		header('content-type: text/plain');
		set_time_limit(600);
		// $this->upgradeChipsPlayers();
		moon_close();
		exit;
	}

	private function upgradeChipsPlayers()
	{
		$this->db->query('
			DELETE FROM `reporting_ng_chips` WHERE chips IS NULL
		');
		echo $this->db->error()
			? 'Error'
			: 'Hokkay';
		echo "\n";

		$this->db->query('
			ALTER TABLE `reporting_ng_chips` 
			CHANGE `chips` `chips` INT(11) NOT NULL
		');
		echo $this->db->error()
			? 'Error'
			: 'Hokkay';
		echo "\n";

		$this->db->query('
		DELETE FROM reporting_ng_players WHERE id NOT IN (
			SELECT DISTINCT player_id FROM reporting_ng_chips
		)
		');
		echo $this->db->error()
			? 'Error'
			: 'Hokkay';
		echo "\n";

		$this->db->query('
			ALTER TABLE `reporting_ng_players` 
			ADD COLUMN `is_hidden` TINYINT(1) UNSIGNED NOT NULL AFTER `country_id`
		');
		echo $this->db->error()
			? 'Error'
			: 'Hokkay';
		echo "\n";

		$dupes = $this->db->array_query_assoc('
			SELECT GROUP_CONCAT(id ORDER BY gr.created_on DESC) ids FROM (
				SELECT p.id,p.name, p.event_id, MAX(c.created_on) created_on FROM (
					SELECT `event_id`, `name` FROM reporting_ng_players 
					GROUP BY `event_id`, `name`
					HAVING COUNT(*) > 1
				) dup 
				INNER JOIN reporting_ng_players p
					ON p.name=dup.name AND p.event_id=dup.event_id
				LEFT JOIN reporting_ng_chips c
					ON c.player_id=p.id
				GROUP BY p.id
				ORDER BY p.name, MAX(c.created_on)
			) gr
			GROUP BY `event_id`, `name`
			ORDER BY `name`
		');
		foreach ($dupes as $dupe) {
			$ids = explode(',', $dupe['ids']);
			$highlander = array_shift($ids);
			foreach ($ids as $leftover) {
				$this->db->update(array(
					'player_id' => $highlander
				), 'reporting_ng_chips', array(
					'player_id' => $leftover
				));
				$this->db->query('
					DELETE FROM reporting_ng_players
					WHERE id=' . intval($leftover) . '
				');
			}
		}
		$this->db->query('
			ALTER TABLE `reporting_ng_players` DROP KEY `ten`, ADD UNIQUE `ten` (`event_id`, `name`)
		');
		echo $this->db->error()
			? 'Error'
			: 'Hokkay';
		echo "\n";

		$dupes = $this->db->array_query_assoc('
			SELECT GROUP_CONCAT(id ORDER BY id DESC) ids FROM (
				SELECT c.id, c.player_id, c.created_on FROM (
					SELECT id, player_id, created_on FROM reporting_ng_chips 
					GROUP BY player_id, created_on
					HAVING COUNT(*) > 1
				) dup
				INNER JOIN reporting_ng_chips c
					ON c.created_on=dup.created_on AND c.player_id=dup.player_id
			) gr
				GROUP BY `player_id`, `created_on`
		');
		foreach ($dupes as $dupe) {
			$ids = explode(',', $dupe['ids']);
			$highlander = array_shift($ids);
			foreach ($ids as $leftover) {
				$this->db->query('
					DELETE FROM reporting_ng_chips
					WHERE id=' . intval($leftover) . '
				');
			}
		}
		$this->db->query('
			ALTER TABLE `reporting_ng_chips` DROP KEY `list_latest_join`, ADD UNIQUE `list_latest_join` (`player_id`, `created_on`)
		');
		echo $this->db->error()
			? 'Error'
			: 'Hokkay';
		echo "\n";

		$this->db->query('
		DELETE FROM reporting_ng_players WHERE id NOT IN (
			SELECT DISTINCT player_id FROM reporting_ng_chips
		)
		');
		echo $this->db->error()
			? 'Error'
			: 'Hokkay';
		echo "\n";

		$this->db->query('
		ALTER TABLE `reporting_ng_chips` DROP COLUMN `is_full_import`
		');
		echo $this->db->error()
			? 'Error'
			: 'Hokkay';
		echo "\n";

		$this->db->query('
		ALTER TABLE `reporting_ng_players` DROP COLUMN `position`, DROP COLUMN `place`
		');
		echo $this->db->error()
			? 'Error'
			: 'Hokkay';
		echo "\n";
	}
}