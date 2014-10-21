<?php

class promos_transfer extends moon_com
{
	function onload()
	{
		$this->adb = moon::db('database-adm');
		$this->db->exceptions(true);
		$this->adb->exceptions(true);
	}
	function events($event, $argv)
	{
		$this->import();
		moon_close();exit;
	}

	private function import()
	{
		$create = $this->db->single_query('SHOW CREATE TABLE promos');
		if (stripos($create[1], 'AUTO_INCREMENT') === false)
			return ;

		$this->db->query('
			ALTER TABLE `promos`
				CHANGE `id` `id` INT(11) NOT NULL,
				ADD COLUMN `base_version` INT DEFAULT 1 NOT NULL AFTER updated_on,
				DROP PRIMARY KEY
		');
		$this->db->query('
			ALTER TABLE `promos_events`
				CHANGE `id` `id` INT(11) NOT NULL,
				ADD COLUMN `base_version` INT DEFAULT 1 NOT NULL AFTER updated_on,
				DROP PRIMARY KEY
		');
		$this->db->query('
			ALTER TABLE `promos_pages`
				CHANGE `id` `id` INT(11) NOT NULL,
				ADD COLUMN `base_version` INT DEFAULT 1 NOT NULL AFTER updated_on,
				DROP PRIMARY KEY
		');
		if (_SITE_ID_ == 'com') {
			$this->db->query('
				ALTER TABLE `promos`
					ADD COLUMN `remote_id` INT(11) UNSIGNED   NOT NULL DEFAULT 0 AFTER `weight` ,
					ADD COLUMN `remote_updated_on` TIMESTAMP   NOT NULL DEFAULT "0000-00-00 00:00:00" AFTER `remote_id`');
			$this->db->query('
				ALTER TABLE `promos_events`
					ADD COLUMN `remote_id` INT(10) UNSIGNED   NOT NULL DEFAULT 0 AFTER `updated_on` ,
					ADD COLUMN `remote_updated_on` TIMESTAMP   NOT NULL DEFAULT "0000-00-00 00:00:00" AFTER `remote_id`
			');
			$this->db->query('
				ALTER TABLE `promos_pages`
					ADD COLUMN `remote_id` INT(10) UNSIGNED   NOT NULL DEFAULT 0 AFTER `updated_on` ,
					ADD COLUMN `remote_updated_on` TIMESTAMP   NOT NULL DEFAULT "0000-00-00 00:00:00" AFTER `remote_id`
			');
			$this->db->query('
				CREATE TABLE IF NOT EXISTS `promos_push`(
					`id` INT(11) UNSIGNED NOT NULL  AUTO_INCREMENT ,
					`title` VARCHAR(255) COLLATE utf8_general_ci NOT NULL  DEFAULT "" ,
					`title_prev` VARCHAR(255) COLLATE utf8_general_ci NOT NULL  DEFAULT "" ,
					`menu_title` VARCHAR(255) COLLATE utf8_general_ci NOT NULL  ,
					`menu_title_prev` VARCHAR(255) COLLATE utf8_general_ci NOT NULL  ,
					`prize` VARCHAR(255) COLLATE utf8_general_ci NOT NULL  ,
					`prize_prev` VARCHAR(255) COLLATE utf8_general_ci NOT NULL  ,
					`descr_intro` TEXT COLLATE utf8_general_ci NOT NULL  ,
					`descr_intro_prev` TEXT COLLATE utf8_general_ci NOT NULL  ,
					`descr_meta` VARCHAR(255) COLLATE utf8_general_ci NOT NULL  DEFAULT "" ,
					`descr_meta_prev` VARCHAR(255) COLLATE utf8_general_ci NOT NULL  DEFAULT "" ,
					`descr_steps` TEXT COLLATE utf8_general_ci NOT NULL  ,
					`descr_steps_prev` TEXT COLLATE utf8_general_ci NOT NULL  ,
					`descr_prize` TEXT COLLATE utf8_general_ci NOT NULL  ,
					`descr_prize_prev` TEXT COLLATE utf8_general_ci NOT NULL  ,
					`descr_qualify` TEXT COLLATE utf8_general_ci NOT NULL  ,
					`descr_qualify_prev` TEXT COLLATE utf8_general_ci NOT NULL  ,
					`terms_conditions` TEXT COLLATE utf8_general_ci NOT NULL  ,
					`terms_conditions_prev` TEXT COLLATE utf8_general_ci NOT NULL  ,
					`lb_columns` VARCHAR(80) COLLATE utf8_general_ci NOT NULL  ,
					`lb_columns_prev` VARCHAR(50) COLLATE utf8_general_ci NOT NULL  ,
					`lb_descr` TEXT COLLATE utf8_general_ci NOT NULL  ,
					`lb_descr_prev` TEXT COLLATE utf8_general_ci NOT NULL  ,
					PRIMARY KEY (`id`)
				) ENGINE=INNODB DEFAULT CHARSET="utf8" COLLATE="utf8_general_ci"
			');
			$this->db->query('
				CREATE TABLE IF NOT EXISTS `promos_events_push`(
					`id` INT(11) UNSIGNED NOT NULL  AUTO_INCREMENT ,
					`title` VARCHAR(255) COLLATE utf8_general_ci NOT NULL  DEFAULT "" ,
					`title_prev` VARCHAR(255) COLLATE utf8_general_ci NOT NULL  DEFAULT "" ,
					`results_columns` VARCHAR(50) COLLATE utf8_general_ci NOT NULL  DEFAULT "" ,
					`results_columns_prev` VARCHAR(50) COLLATE utf8_general_ci NOT NULL  DEFAULT "" ,
					PRIMARY KEY (`id`)
				) ENGINE=INNODB DEFAULT CHARSET="utf8" COLLATE="utf8_general_ci"
			');
			$this->db->query('
				CREATE TABLE IF NOT EXISTS `promos_pages_push`(
					`id` INT(11) UNSIGNED NOT NULL  AUTO_INCREMENT ,
					`title` VARCHAR(255) COLLATE utf8_general_ci NOT NULL  DEFAULT "" ,
					`title_prev` VARCHAR(255) COLLATE utf8_general_ci NOT NULL  DEFAULT "" ,
					`description` TEXT COLLATE utf8_general_ci NOT NULL  ,
					`description_prev` TEXT COLLATE utf8_general_ci NOT NULL  ,
					`meta_kwd` VARCHAR(120) COLLATE utf8_general_ci NOT NULL  DEFAULT "" ,
					`meta_kwd_prev` VARCHAR(120) COLLATE utf8_general_ci NOT NULL  DEFAULT "" ,
					`meta_descr` VARCHAR(255) COLLATE utf8_general_ci NOT NULL  DEFAULT "" ,
					`meta_descr_prev` VARCHAR(255) COLLATE utf8_general_ci NOT NULL  DEFAULT "" ,
					PRIMARY KEY (`id`)
				) ENGINE=INNODB DEFAULT CHARSET="utf8" COLLATE="utf8_general_ci"
			');
		}

		$this->db->query('begin');
		$this->adb->query('begin');

		$this->db->query('DELETE FROM promos_events WHERE promo_id NOT IN (SELECT id FROM promos)');
		$this->db->query('DELETE FROM promos_pages  WHERE promo_id NOT IN (SELECT id FROM promos)');
		$this->db->query('DELETE FROM promos_push WHERE id NOT IN (SELECT id FROM promos)');
		$this->db->query('DELETE FROM promos_events_push WHERE id NOT IN (SELECT id FROM promos_events)');
		$this->db->query('DELETE FROM promos_pages_push WHERE id NOT IN (SELECT id FROM promos_pages)');

		try {
			$promo_room_map = $this->importLocalRooms();
			$this->importPromos($promo_room_map);
			if (_SITE_ID_ != 'com')
				$this->translateSyncedPromos($promo_room_map);

			$this->db->query('UPDATE promos SET id=-id, updated_on=updated_on WHERE id<0');
			$this->db->query('UPDATE promos_pages SET id=-id, updated_on=updated_on WHERE id<0');
			$this->db->query('UPDATE promos_events SET id=-id, updated_on=updated_on WHERE id<0');

			foreach (['promos', 'promos_events', 'promos_pages'] as $tbl) {
				$repeats = $this->db->array_query_assoc('
					SELECT id, count(*) FROM ' . $tbl . ' GROUP BY id HAVING COUNT(*)>1
				');
				if (count($repeats))
					throw new Exception('Repeats in ' . $tbl . ': ' . json_encode($repeats));
			}

			$this->db->query('commit');
			$this->adb->query('commit');
		} catch (Exception $ex) {
			$this->db->query('rollback');
			$this->adb->query('rollback');
			echo $ex->getMessage();
		} finally {
			$this->db->query('
				ALTER TABLE `promos`
					CHANGE `id` `id` INT(11) UNSIGNED NOT NULL,
					ADD PRIMARY KEY (`id`)
			');
			$this->db->query('
				ALTER TABLE `promos_events`
					CHANGE `id` `id` INT(11) UNSIGNED NOT NULL,
					ADD PRIMARY KEY (`id`)
			');
			$this->db->query('
				ALTER TABLE `promos_pages`
					CHANGE `id` `id` INT(11) UNSIGNED NOT NULL,
					ADD PRIMARY KEY (`id`)
			');
			// $this->db->query('ALTER TABLE `promos` DROP COLUMN `remote_id`');
			// $this->db->query('ALTER TABLE `promos_events` DROP COLUMN `remote_id`');
			// $this->db->query('ALTER TABLE `promos_pages` DROP COLUMN `remote_id`');
		}

		echo 'Done';

		moon_close();exit;
	}

	private function importLocalRooms()
	{
		// local_id => new_global_id
		$promo_room_map = [];

		$local_rooms = $this->db->array_query_assoc('
			SELECT * FROM promos_rooms
		');
		foreach ($local_rooms as $room) {
			$promo_room_map[$room['id']] = $this->importLocalRoom($room);
		}

		return $promo_room_map;
	}
	private function importLocalRoom($room)
	{
		$room_ins = $room;
		unset($room_ins['id']);
		// image
		return $this->adb->insert($room_ins, 'promos_rooms', 'id');
	}

	private function importPromos($promo_room_map)
	{
		$promo_map = [];
		$local_promos = $this->db->array_query_assoc('
			SELECT *, UNIX_TIMESTAMP(updated_on) updated_on FROM promos
			WHERE ' . (_SITE_ID_ == 'com' ? '1' : 'remote_id=0') . '
		');
		foreach ($local_promos as $promo) {
			$promo_map[$promo['id']] = $this->importPromo($promo, $promo_room_map);
		}

		return $promo_map;
	}
	private function importPromo($promo, $promo_room_map)
	{
		$promo_old_id = $promo['id'];

		// promo itself
		unset(
			$promo['menu_title'],  $promo['descr_intro'],   $promo['descr_meta'],
			$promo['descr_prize'], $promo['descr_qualify'], $promo['terms_conditions'],
			$promo['lb_descr'],    $promo['remote_id'],     $promo['remote_updated_on'],
			$promo['descr_steps'], $promo['descr_steps_images'], $promo['updated_on'],
			$promo['base_version'],$promo['is_live_league']
		);
		if (_SITE_ID_ != 'com') unset($promo['id']);
		$this->promoTranslateRoom($promo, $promo_room_map);
		$promo['version'] = 1;
		$promo_new_id = $this->adb->insert($promo, 'promos', 'id');
		$this->db->update(['id' => -$promo_new_id, 'room_id' => $promo['room_id'], 'base_version' => $promo['version'], 'updated_on' => ['updated_on']], 'promos', ['id' => $promo_old_id]);
		unset($promo['id']);

		// events
		$promo_events = $this->db->array_query_assoc('
			SELECT *, UNIX_TIMESTAMP(updated_on) updated_on FROM promos_events
			WHERE promo_id="' . $this->db->escape($promo_old_id) . '"
		');
		foreach ($promo_events as $promo_event) {
			$promo_event['promo_id'] = $promo_new_id;
			$promo_event['version'] = 1;
			$promo_event_old_id = $promo_event['id'];
			unset($promo_event['remote_id'], $promo_event['remote_updated_on'], $promo_event['updated_on'], $promo_event['base_version']);
			if (_SITE_ID_ != 'com') unset($promo_event['id']);
			$promo_event_new_id = $this->adb->insert($promo_event, 'promos_events', 'id');
			$this->db->update(['id' => -$promo_event_new_id, 'promo_id' => $promo_new_id, 'base_version' => $promo_event['version'], 'updated_on' => ['updated_on']], 'promos_events', ['id' => $promo_event_old_id, 'promo_id' => $promo_old_id]);
		}

		// pages
		$promo_pages = $this->db->array_query_assoc('
			SELECT *, UNIX_TIMESTAMP(updated_on) updated_on FROM promos_pages
			WHERE promo_id="' . $this->db->escape($promo_old_id) . '"
		');
		foreach ($promo_pages as $promo_page) {
			$promo_page['promo_id'] = $promo_new_id;
			$promo_page['version'] = 1;
			$promo_page_old_id = $promo_page['id'];
			unset($promo_page['remote_id'], $promo_page['remote_updated_on'], $promo_page['updated_on'], $promo_page['base_version'], $promo_page['description'], $promo_page['meta_kwd'], $promo_page['meta_descr']);
			if (_SITE_ID_ != 'com') unset($promo_page['id']);
			$promo_page_new_id = $this->adb->insert($promo_page, 'promos_pages', 'id');
			$this->db->update(['id' => -$promo_page_new_id, 'promo_id' => $promo_new_id, 'base_version' => $promo_page['version'], 'updated_on' => ['updated_on']], 'promos_pages', ['id' => $promo_page_old_id, 'promo_id' => $promo_old_id]);
		}

		return $promo_new_id;
	}

	private function translateSyncedPromos($promo_room_map)
	{
		$synced_promos = $this->db->array_query_assoc('SELECT id, room_id, remote_id FROM promos WHERE remote_id!=0');
		foreach ($synced_promos as $promo) {
			$this->promoTranslateRoom($promo, $promo_room_map);
			$this->db->update([
				'room_id' => $promo['room_id'],
				'updated_on' => ['updated_on']
			], 'promos', [
				'id' => $promo['id']
			]);
		}

		$this->db->query('UPDATE promos SET id=remote_id, base_version=1, updated_on=updated_on WHERE remote_id!=0');
		$this->db->query('UPDATE promos_pages SET id=remote_id, base_version=1, updated_on=updated_on WHERE remote_id!=0');
		$this->db->query('UPDATE promos_events SET id=remote_id, base_version=1, updated_on=updated_on WHERE remote_id!=0');

		if (count($synced_promos)) {
			$map_sql = [];
			foreach ($synced_promos as $promo) {
				$map_sql[] = 'WHEN ' . intval($promo['id']) . ' THEN ' . intval($promo['remote_id']);
			}
			$this->db->query('
				UPDATE promos_pages SET promo_id=(CASE promo_id ' . implode(' ', $map_sql) . ' ELSE 0 END), updated_on=updated_on WHERE remote_id!=0
			');
			$this->db->query('
				UPDATE promos_events SET promo_id=(CASE promo_id ' . implode(' ', $map_sql) . ' ELSE 0 END), updated_on=updated_on WHERE remote_id!=0
			');
		}
	}

	private function promoTranslateRoom(&$promo, $promo_room_map)
	{
		if ('' != $promo['room_id']) {
			$promo['room_id'] = explode(',', $promo['room_id']);
			foreach ($promo['room_id'] as $k => $room_id)
				if ($room_id < 0 && isset($promo_room_map[-$room_id]))
					$promo['room_id'][$k] = -$promo_room_map[-$room_id];
			$promo['room_id'] = implode(',', $promo['room_id']);
		}
	}
}
