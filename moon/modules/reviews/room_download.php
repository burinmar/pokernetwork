<?php

class room_download extends moon_com {

	function renderDownloadIframe($roomID, $alias, $uri) {
		$page = & moon :: page();
		$oReview = & $this->object('review');
		$oReview->id($roomID);
		$roomData = $oReview->getRoom();
		list($url, $inFrame, $bonusCode) = $this->getRedirectUrl($roomID, $alias, $uri);
		$param = isset ($_GET['p']) ? trim(substr($_GET['p'], 0, 40)):'';
		$this->updateStats($roomID, $alias, $param, $uri);
		$page->redirect($url);
	}

	function updateStats($roomId, $alias, $p, $uri = 'ext') {
		$roomID = intval($roomId);
		$day = date('Y-m-d');
		$myTable = $this->table('Stats');
		$alias = trim(substr($alias, 0, 40));
		// check if record exists
		$result = $this->db->single_query('
			SELECT room_id
			FROM ' . $this->table('Stats') . "
			WHERE 	room_id = $roomID AND
				day = '$day' AND
				campaign = '" . $this->db->escape($alias) . "' AND
				p = '" . $this->db->escape($p) . "'
			");
		$field = ($uri == 'ext') ? 'uri_count':'uri_download_count';
		if (count($result)) {
			// update
			$this->db->query("UPDATE  $myTable
				SET  $field  = $field  + 1
				WHERE	room_id = $roomID  AND
					day = '$day' AND
					campaign = '" . $this->db->escape($alias) . "' AND
					p = '" . $this->db->escape($p) . "'
				");
		}
		else {
			// insert
			$this->db->insert(array('room_id' => $roomID, 'campaign' => $alias, 'day' => $day, $field => 1, 'p' => $p), $myTable);
		}
	}

	function getRedirectUrl($roomId, $alias, $uri = 'ext') {
		$isDownload = $uri == 'download' ? TRUE:FALSE;
		if (_SITE_ID_ === 'fr' && 'fr' == geo_my_country()) {
			//nelicenzijuoti saitai neturi veikti prancuzui
			$is = $this->db->single_query('
				SELECT us_friendly
				FROM ' . $this->table('Rooms') . '
				WHERE id = ' . intval($roomId) . '
				');
			if (empty ($is[0])) {
				// neturi licenzijos
				return array('/salles-poker-licence-france/', 0);
			}
		}
		$field = ($uri == 'ext') ? 'uri':'uri_download';
		$result = $this->db->single_query_assoc('
			SELECT uri, uri_download, iframe, bonus_code
			FROM ' . $this->table('Trackers') . '
			WHERE parent_id = ' . intval($roomId) . "
				AND (alias='' OR alias = '" . $this->db->escape($alias) . "')
			ORDER BY alias DESC
			LIMIT 1
		");
		if (empty ($result[$field]) AND $field == 'uri_download') {
			$field = 'uri';
		}
		// Gal tai reviewso additional page trackeris?
		if (isset ($_GET['rwPage']) && $pID = intval($_GET['rwPage'])) {
			$a = $this->db->single_query('
				SELECT bonus_code, tracker_url FROM ' . $this->table('Pages') . '
				WHERE id=' . $pID . '
			');
			if (isset ($a[0])) {
				$result['bonus_code'] = $a[0];
				if ($a[1]) {
					$result[$field] = $a[1];
				}
			}
		}
		return array($result[$field], $result['iframe'], $result['bonus_code']);
	}

}

?>