<?php


class roomtrackers extends moon_com {


	function events($event, $par) {
		switch ($event) {

			case 'save' :
				$parentID = $_POST['room_id'];
				$this->save();
				$this->redirect('#', $parentID);
				break;

			default :
				if (isset ($_POST['tracker_id'])) {
					$this->set_var('trackerID', intval($_POST['tracker_id']));
				}
				$this->set_var('parentID', (isset ($par[0]) ? intval($par[0]) : 0));
		}
		$this->use_page('Common');
	}


	function properties() {
		return array('parentID' => 0, 'trackerID' => 0);
	}


	function main($vars) {
		$page = & moon :: page();
		$tpl = & $this->load_template();
		$win = & moon :: shared('admin');
		$win->active($this->my('fullname'));
		$info = $tpl->parse_array('info');

		/**/
		$parentID = intval($vars['parentID']);
		$this->roomId = $parentID;
		$this->trackerId = (int) $vars['trackerID'];
		$roomName = $this->getRoomName($this->roomId);
		if ($roomName === FALSE) {
			$page->alert($info['404']);
			$this->redirect('reviews#');
		}
		$win->subMenu(array('*id*' => $parentID));

		/**/
		$m = array(
			'pageTitle' => '',
			'title' => $this->getRoomName($this->roomId),
			'room_id' => $this->roomId,
			'tracker_id' => $this->trackerId,
			'_event_' => $this->my('fullname') . '#save',
			'refresh' => $page->refresh_field(),
			'rows' => ''
			);
		$page->title($m['title'] . ': ' . $page->title());
		$m['pageTitle'] = htmlspecialchars($page->title());
		$m['goBack'] = $this->linkas('#', $this->roomId);

		/* Filtras */
		$trackers = $this->getTrackers();


		/* Items */
		$items = $this->getItems();
		$dat = $this->getTrackers();
		$i = 0;
		foreach ($dat as $k => $d) {
			$starsRoomId = array(53,147);
			$row = array('id' => $k, 'title' => $d, 'show_marketing_code' => (in_array($this->roomId , $starsRoomId)) ? 'marketing code' : '', 'style' => "td" . (($i++% 2) + 1) . 'p', 'no' => $i);
			if (isset ($items[$k])) {
				$bonusCode = $items[$k]['bonus_code'];
				$codes = explode('|', $bonusCode);
				$row['bonus_code'] = htmlspecialchars($codes[0]);
				$row['marketing_code'] = isset ($codes[1]) ? htmlspecialchars($codes[1]) : '';
				$row['uri'] = htmlspecialchars($items[$k]['uri']);
				$row['urid'] = htmlspecialchars($items[$k]['uri_download']);
				$row['iframe'] = $items[$k]['iframe'] == '1' ? '1" checked="checked' : '1';
			}
			else {
				$row['uri'] = $row['urid'] = $row['bonus_code'] = $row['marketing_code'] = '';
				$row['iframe'] = '1" checked="checked';
			}
			$m['rows'] .= $tpl->parse('uri_row', $row);
		}
		$res = $tpl->parse('main', $m);
		$this->save_vars(array('trackerID' => $this->trackerId));
		return $res;
	}


	//***************************************
	//           --- DB AND OTHER ---
	//***************************************
	function save() {
		$page = & moon :: page();
		if ($page->was_refresh()) {
			return TRUE;
		}

		/* */
		$roomID = intval($_POST['room_id']);
		if (!$roomID) {
			$page = & moon :: page();
			$page->alert('System error: no roomID!');
			return FALSE;
		}

		/* */
		$url = is_array($_POST['uri']) ? $_POST['uri'] : array();
		$urlDownload = is_array($_POST['uri_download']) ? $_POST['uri_download'] : array();
		$iframe = is_array($_POST['iframe']) ? $_POST['iframe'] : array();
		$bonusCode = is_array($_POST['bonus_code']) ? $_POST['bonus_code'] : array();
		$marketingCode = isset ($_POST['marketing_code']) && is_array($_POST['marketing_code']) ? $_POST['marketing_code'] : array();

		/* */
		$ins = array('parent_id' => $roomID);
		$sites = array();
		foreach ($url as $id => $value) {
			$marCode = isset ($marketingCode[$id]) ? trim($marketingCode[$id]) : '';
			$bonCode = isset ($bonusCode[$id]) ? trim($bonusCode[$id]) : '';
			$ins['alias'] = $id ? $id : '';
			$ins['uri'] = trim($value);
			$ins['uri_download'] = isset ($urlDownload[$id]) ? trim($urlDownload[$id]) : '';
			$ins['bonus_code'] = ($marCode != '') ? $bonCode . '|' . $marCode : $bonCode;
			$ins['iframe'] = empty ($iframe[$id]) ? 0 : 1;
			$this->db->replace($ins, $this->table('Trackers'));
		}
		blame($this->my('fullname'), 'Updated', 'room:' . $roomID );
	}


	function getRoomName($id) {
		$sql = '
		SELECT name
		FROM ' . $this->table('Rooms') . '
		WHERE id = ' . intval($id);
		$r = $this->db->single_query($sql);
		return isset ($r[0]) ? $r[0] : FALSE;
	}

	function getTrackers() {
		return array(''=>'Default');
		/*$sql = '
		SELECT id, name
		FROM ' . $this->table('TrackersNew') . '
		WHERE is_deleted = 0';
		return $this->db->array_query($sql, TRUE);*/
	}

	function getItems() {
		$sql = '
		SELECT *
		FROM ' . $this->table('Trackers') . '
		WHERE parent_id=' . intval($this->roomId) ;
		return $this->db->array_query_assoc($sql, 'alias');
	}


}

?>