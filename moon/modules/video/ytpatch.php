<?php
class ytpatch extends moon_com {

	function onload()
	{
		$this->nVideos = 'video2';
		$this->nCats = 'video2_categories';

		$this->oVideos = 'videos';
		$this->oCats = 'videos_playlists';
	}

	function import() {
		set_time_limit(600);
		ignore_user_abort(1);
		//$this->createTables();
		$this->importCats();
		$this->importVideos();
		$this->importComments();
		echo "DONE!\n";
	}


	function importCats() {
		$a = $this->db->array_query_assoc('SELECT * FROM ' . $this->oCats);
		$i = 0;
		$this->db->query('TRUNCATE TABLE ' . $this->nCats);
		$ids = array();
		foreach ($a as $v) {
			$i++;
			$ins = array();
			$ins['id'] = $i;
			$ins['hide'] = $v['is_deleted'] ? 2 : ($v['is_hidden'] ? 1 : 0);
			$ins['title'] = $v['name'];
			$ins['uri'] = $v['uri'];
			$ins['description'] = (string)$v['short_description'];
			$ins['sort_order'] = $v['sort_order'];
			$ins['tags'] = $v['filter_tags'];
			$this->db->insert($ins, $this->nCats);
			$ids[(string)$v['id']] = $i;
		}
		$this->cats = $ids;
		echo count($this->cats) . " categories imported\n";
	}

	function importVideos() {
		$r = $this->db->query('SELECT * FROM ' . $this->oVideos );
		$ids = array();
		$i = 0; $j = 0;
		$txt = & moon::shared('text');
		$now = time();
		while ($v = $this->db->fetch_row_assoc($r)) {
			$up = array();
			$up['hide'] = $v['is_deleted'] ? 2 : ($v['is_hidden'] ? 1 : 0);
			$up['title'] = $v['name'];
			$up['uri'] = $txt->make_uri($v['name']);
			$up['description'] = strlen($v['short_description']) > strlen($v['long_description']) ? $v['short_description'] : $v['long_description'];
			$up['tags'] = $v['tags'];
			$up['category'] = '';
			if ($v['playlist_ids']) {
				$b = explode(',', $v['playlist_ids']);
				$c = array();
				foreach ($b as $ii) {
					if (isset($this->cats[$ii])) {
						$c[] = $this->cats[$ii];
					}
				}
				$up['category'] = implode(',', $c);
			}
			$up['updated'] = $now;
			$up['comm_count'] = $v['comm_count'];
			$up['comm_last'] = $v['comm_last'];
			$up['views_count'] = $v['views_count'];
			$this->db->update($up, $this->nVideos, array('brightcove_id'=>(string)$v['id']));
			if ($this->db->affected_rows()) {
				$i++;
			}
			else {
				$j++;
			}
		}
		echo "$i videos updated, $j videos ignored.\n";
	}

	function importComments() {
		$this->db->query('TRUNCATE TABLE video2_comments');
		$r = $this->db->query('INSERT INTO video2_comments (
		SELECT c.id, v.id, c.user_id,c.user_ip,c.comment,c.created,c.spam
		FROM videos_comments c, ' . $this->nVideos . ' v
		WHERE c.obj_id=v.brightcove_id)');
		$i = $this->db->affected_rows();
		echo "$i comments imported.\n";
	}


	function createTables() {
		$sqlArr = array();
		/* Patikrinam, ar dar nebuvo patchinta */

		$m = $this->db->single_query("show tables like 'video2'");
		if (count($m)) {
			echo "Tables already exist.\n";
			return;
		}
			$sqlArr[] = "CREATE TABLE `video2` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `hide` TINYINT(1) NOT NULL DEFAULT '0',
  `duration` SMALLINT(6) NOT NULL DEFAULT '0',
  `created` INT(11) UNSIGNED NOT NULL COMMENT 'timestamp',
  `updated` INT(11) NOT NULL COMMENT 'timestamp',
  `master_updated` INT(11) NOT NULL COMMENT 'timestamp',
  `author_id` INT(11) NOT NULL,
  `youtube_video_id` CHAR(11) CHARACTER SET ASCII NOT NULL,
  `youtube_playlist_id` CHAR(16) DEFAULT NULL,
  `brightcove_id` BIGINT(20) NOT NULL DEFAULT '0',
  `flv_url` VARCHAR(255) DEFAULT NULL,
  `thumbnail_url` VARCHAR(255) DEFAULT NULL,
  `uri` VARCHAR(60) NOT NULL DEFAULT '',
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT,
  `tags` VARCHAR(255) DEFAULT NULL,
  `category` VARCHAR(50) DEFAULT NULL,
  `comm_count` INT(10) UNSIGNED NOT NULL DEFAULT '0',
  `comm_last` INT(10) UNSIGNED NOT NULL DEFAULT '0',
  `views_count` INT(11) UNSIGNED DEFAULT '0',
  PRIMARY KEY (`id`)
)";
			$sqlArr[] = "CREATE TABLE `video2_categories` (
  `id` SMALLINT(6) NOT NULL AUTO_INCREMENT,
  `hide` TINYINT(1) NOT NULL DEFAULT '0',
  `title` VARCHAR(128) NOT NULL,
  `uri` VARCHAR(60) NOT NULL DEFAULT '',
  `meta_description` VARCHAR(255) NOT NULL,
  `description` TEXT NOT NULL,
  `sort_order` TINYINT(1) NOT NULL DEFAULT '0',
  `tags` TEXT NOT NULL,
  PRIMARY KEY (`id`)
)";
			$sqlArr[] = "CREATE TABLE `video2_comments` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `obj_id` INT(10) UNSIGNED NOT NULL,
  `user_id` INT(11) NOT NULL,
  `user_ip` INT(10) UNSIGNED DEFAULT NULL,
  `comment` TEXT NOT NULL,
  `created` INT(10) UNSIGNED NOT NULL,
  `spam` TINYINT(1) UNSIGNED NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `obj_id` (`obj_id`),
  KEY `user_id` (`user_id`)
)";
			$sqlArr[] = "CREATE TABLE `video2_master` (
  `id` INT(11) NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT,
  `tags` VARCHAR(255) DEFAULT NULL,
  `prev_title` VARCHAR(255) NOT NULL,
  `prev_description` TEXT,
  `prev_tags` VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
)";

		$sqlArr[] = "insert into `sys_cron_tasks` ( `event`, `comment`, `schedule`, `priority`, `last_run`, `next_run`, `in_background`, `disabled`) values('video.video#import','Video import from adm.pokernews','*','15','1335432946','1335432947','0','0')";

		$sqlArr[] = "update `sys_cron_tasks` SET `disabled`=1, next_run=0 WHERE `event`='video.video_import#import-bc-videos-cron'";


		/* do patch */
		$s = '';
		foreach ($sqlArr as $k => $v) {
			$r = $this->db->query($v);
			$s .= "SQL#" . $k . ' - ' . ($r ? 'OK' : 'Error ' . mysql_error()) . ' rows:'.$this->db->affected_rows(). "\n";
		}
		echo $s, "Tables created\n";
	}



}
?>
