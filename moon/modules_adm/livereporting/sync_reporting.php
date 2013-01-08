<?php

set_time_limit(3600);

class sync_reporting extends moon_com
{
	function events($event)
	{
		$this->use_page('Common');
		switch ($event) {
			case 'imgsrv-import':
				ob_start();
				$this->doImgsrvImport();
				$page = & moon :: page();
				$page->set_local('cron', ob_get_contents());
				return;

			case 'sync_v2';
				ob_start();
				$sync = $this->object('sync_reporting_v2');
				$sync->syncAll();
				$sync->cleanupFailedSyncs();
				if (isset($_GET['debug'])) {
					moon_close();
					exit;
				}
				moon::page()->set_local('cron', ob_get_contents());
				return;

			case 'imgsrv-local-reimport':
				$this->doImgsrvLocalReimport();
				return;

			default:
				break;
		}
	}

	private function doImgsrvImport()
	{
		$tournaments = $this->db->array_query_assoc('
			SELECT id FROM reporting_ng_tournaments
			WHERE state=1
		', 'id');
		$tournaments = array_keys($tournaments);
		if (0 == count($tournaments)) {
			echo 'No tournament to fetch';
			return ;
		}
		$data = serialize(array(
			'paths' => $tournaments
		));
		$fn = tempnam("tmp", "lrep");
		$fp = fopen($fn, 'wb');
		fwrite($fp, $data);
		fclose($fp);
		
		$ch = curl_init(is_dev()
			? 'http://imgsrv.pokernews.dev/import.php'
			: 'http://imgsrv.pokernews.com/import.php');
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
		curl_setopt($ch, CURLOPT_TIMEOUT, 60*20);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, array(
			'key' => 'larry-the-cow',
			'src_id' => _SITE_ID_,
			'operation' => 'fetch-fresh-approved',
			'data' => '@' . $fn
		));
		curl_setopt($ch, CURLOPT_FAILONERROR, 1);

		$gotData = curl_exec($ch);
		unlink($fn);
		$oldData = $gotData;
		$gotData = @unserialize($gotData);
		if (!isset ($gotData['images'])) {
			echo 'No data';
			return ;
		}

		$images = $gotData['images'];
		$deleted = $gotData['deleted'];

		$imported = 0;
		$updated = 0;
		foreach ($images as $image) {
			$path = explode('/', $image['uri']);
			$exists = $this->db->single_query_assoc('
				SELECT id, import_id FROM ' . $this->table('Photos') . '
				WHERE day_id=' . intval($path[3]) . ' AND image_src="' . $this->db->escape($image['image_src']) . '"
				LIMIT 1
			');
			if (!empty($exists['import_id'])) {
				continue;
			}
			$iId = 0;
			if (!empty($exists)) {
				$this->db->update(array(
					'image_misc' => $image['misc'],
					'image_alt' => (string)$image['description'],
				), $this->table('Photos'), array(
					'id' => $exists['id']
				));
				if ($this->db->affected_rows()) {
					$updated++;
				}
				$iId = $exists['id'];
				$this->db->query('
					DELETE FROM ' . $this->table('Tags') . '
					WHERE id=' . $iId . ' AND type="photo"
				');
			} else {
				$imageData = array(
					'import_id' => NULL,
					'day_id' => $path[3],
					'event_id' => $path[2],
					'image_misc' => $image['misc'],
					'image_src' => $image['image_src'],
					'image_alt' => (string)$image['description'],
					'created_on' => time()
				);
				$this->db->insert($imageData, $this->table('Photos'));
				$iId = $this->db->insert_id();
				$imported++;
			}
			foreach ($image['tags'] as $tag) {
				$this->db->insert(array(
					'id' => $iId,
					'tag' => $tag,
					'type' => "photo",
					'day_id' => $path[3],
					'event_id' => $path[2],
					'tournament_id' => $path[1],
				), $this->table('Tags'));
			}
		}
		echo 'Imported ' . $imported . " images\n";

		$deletedCnt = 0;
		foreach ($deleted as $image) {
			$path = explode('/', $image['uri']);
			$exists = $this->db->single_query_assoc('
				SELECT id, import_id FROM ' . $this->table('Photos') . '
				WHERE day_id=' . intval($path[3]) . ' AND image_src="' . $this->db->escape($image['image_src']) . '"
				LIMIT 1
			');
			if (!empty($exists['import_id'])) {
				continue;
			}
			if (!empty($exists)) {
				$iId = $exists['id'];
				$this->db->query('
					DELETE FROM ' . $this->table('Photos') . '
					WHERE id=' . $iId . '
				');
				$this->db->query('
					DELETE FROM ' . $this->table('Tags') . '
					WHERE id=' . $iId . ' AND type="photo"
				');
				$deletedCnt++;
			}
		}
		echo 'Deleted ' . $deletedCnt . " images\n";

		if ($updated) {
			livereporting_adm_alt_log(0, 0, 0, 'update', 'photos', 0, $updated . ' +tags{} @imsrv', -1);
		}
		if ($imported) {
			livereporting_adm_alt_log(0, 0, 0, 'insert', 'photos', 0, $imported . ' +tags{} @imsrv', -1);
		}
		if ($deletedCnt) {
			livereporting_adm_alt_log(0, 0, 0, 'delete', 'photos', 0, $deletedCnt . ' +tags{} @imsrv', -1);
		}
	}

	private function doImgsrvLocalReimport()
	{
		$fn = tempnam("tmp", "lrep");
		$fp = fopen($fn, 'wb');
		fwrite($fp, 'Required dummy');
		fclose($fp);

		$ch = curl_init(is_dev()
			? 'http://imgsrv.pokernews.dev/import.php'
			: 'http://imgsrv.pokernews.com/import.php');
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
		curl_setopt($ch, CURLOPT_TIMEOUT, 60*20);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, array(
			'key' => 'larry-the-cow',
			'src_id' => _SITE_ID_,
			'operation' => 'fetch-all-local',
			'data' => '@' . $fn
		));
		curl_setopt($ch, CURLOPT_FAILONERROR, 1);

		$gotData = curl_exec($ch);
		unlink($fn);
		$oldData = $gotData;
		$gotData = @unserialize($gotData);
		if (!isset ($gotData['images']) || !is_array($gotData['images'])) {
			echo 'No data';
			return ;
		}

		$insertedDescrs = 0;
		$updatedDescrs = 0;
		$replacedTags = 0;
		foreach ($gotData['images'] as $image) {
			$path = explode('/', $image['uri']);
			$imageIds = array_keys($this->db->array_query_assoc('
				SELECT id FROM ' . $this->table('Photos') . '
				WHERE event_id="' . intval($path[2]) . '" AND image_misc LIKE "' . intval($image['id']) . ',%"
			', 'id'));
			if (0 == count($imageIds)) {
				$imageData = array(
					'import_id' => null,
					'day_id' => $path[3],
					'event_id' => $path[2],
					'image_misc' => $image['id'] . ',' . $image['misc'],
					'image_src' => $image['image_src'],
					'image_alt' => (string)$image['d'],
					'created_on' => time()
				);
				$this->db->insert($imageData, $this->table('Photos'));
				$imageIds[] = $this->db->insert_id();
				$insertedDescrs++;
			} else {
				foreach ($imageIds as $imageId) {
					$this->db->update(array(
						'image_misc' => $image['id'] . ',' . $image['misc'],
						'image_alt' => (string)$image['d'],
					), $this->table('Photos'), array(
						'id' => $imageId
					));
					$updatedDescrs += $this->db->affected_rows();
				}
				$this->db->query('
					DELETE FROM ' . $this->table('Tags') . '
					WHERE id IN(' . implode(',', $imageIds) . ') AND type="photo"
				');
			}
			foreach (explode(',', $image['tags']) as $tag) {
				foreach ($imageIds as $imageId) {
					$this->db->insert(array(
						'id' => $imageId,
						'tag' => trim($tag),
						'type' => "photo",
						'day_id' => $path[3],
						'event_id' => $path[2],
						'tournament_id' => $path[1],
					), $this->table('Tags'));
					$replacedTags++;
				}
			}			
		}

		echo 'ok';

		echo 'Inserted ' . $insertedDescrs . ' images' . "\n";
		echo 'Updated ' . $updatedDescrs . ' images' . "\n";
		echo 'Replaces ' . $replacedTags . ' tags' . "\n";
	}
}
