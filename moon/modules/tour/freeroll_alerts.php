<?php
class freeroll_alerts extends moon_com {

	function onload() {

		$this->myTable = $this->table('SpecSubscriptions');
	}

	function events($event, $par) {
		switch ($event) {


			case 'save-subscription-rooms':
				$form = &$this->form();
				$form->names('room');
				$form->fill($_POST);
				$data = $form->get_values();
				$this->saveSubscriptionRooms($data);
				$this->redirect('freerolls_special#');
				break;

			default:
				/*if (isset ($par[1]) && $par[0] == 'subscribe') {
					$isAjax = isset($_GET['ajax']);
					$response = $this->renderSaveAlertsTournament($par[1], $isAjax);
					if ($isAjax) {
						echo str_replace(array('\t','\r','\n','\/','\"'), array('','','','/',"'"), json_encode($response));
						$this->forget(); moon_close(); exit;
					}
				}*/

		}
	}


	function properties() {
		return array();
	}


	function isSubscribed($id, $roomId, &$subscriptions) {
		return in_array($id, $subscriptions['tournaments'])	|| in_array($roomId, $subscriptions['rooms']) && !in_array(-$id, $subscriptions['tournaments']);
	}

	function main($argv = null) {
		$tpl = $this->load_template();
		$page = &moon::page();
		$user = &moon::user();
		$userId = $user->get_user_id();
		$locale = &moon::locale();
		if (!$userId) return '';

		if (!empty($_GET['far'])) {
			$this->renderSaveAlertsTournament(-$_GET['far'],false);
		}
		elseif (!empty($_GET['faa'])) {
			$this->renderSaveAlertsTournament($_GET['faa'],false);
		}

		//$page->css('/css/freerolls.css');
		//$page->js('/js/modules/freerolls.js');

		$subscriptions = $this->getUserSubscriptions($userId);

		$mainArgv = array(
			'list.freerolls' => '',
			'list.rooms' => '',
			'save_rooms_event' => $this->my('fullname') . '#save-subscription-rooms',
		);
		//$urlSelf = $page->uri_segments(0);
		//echo $urlSelf;

		$rooms = $this->getUserRooms();
		foreach ($rooms as $room) {
			$mainArgv['list.rooms'] .= $tpl->parse('alerts:room.item', array(
				'id' => $room['id'],
				'img' => img('rw', $room['id'], $room['logo']),
				'title' => htmlspecialchars($room['name']),
				'checked' => in_array($room['id'], $subscriptions['rooms'])
			));
		}

		$tournaments = array();
		if (0 != count($subscriptions['rooms']) || 0 != count($subscriptions['tournaments'])) {
			$tournaments = $this->db->array_query_assoc('
				SELECT t.id, t.date, t.name, r.alias
				FROM ' . $this->table('SpecTournaments') . ' t
				INNER JOIN ' . $this->table('Rooms') . ' r
					ON t.room_id=r.id
				WHERE t.hide=0 AND t.`date`>' . time() . ' AND (' . (
					0 != count($subscriptions['rooms'])
						? ' t.room_id IN (' . implode(',', $subscriptions['rooms']) . ')'
						: ''
				) . (0 != count($subscriptions['rooms']) && 0 != count($subscriptions['tournaments']) ? ' OR ' : '') . (
					0 != count($subscriptions['tournaments'])
						? ' t.id IN (' . implode(',', $subscriptions['tournaments']) . ')'
						: ''
				) . ')
				ORDER BY date
			');
			foreach ($tournaments as $k => $tournament) if (in_array(-$tournament['id'], $subscriptions['tournaments'])) unset($tournaments[$k]);
			$tournaments = array_values($tournaments);
		}
        //$tournaments = array();
		$pn = & moon :: shared('paginate');
		$currPg = empty($_GET['fapg']) ? 1 : intval($_GET['fapg']);
		$pn->set_curent_all_limit($currPg, count($tournaments), 5);
		$get = $_GET;
		if (isset($get['fapg'])) {
			unset($get['fapg']);
		}
		$pn->set_url($this->linkas('freerolls_special#', '', $get + array('fapg' => '{pg}')), $this->linkas('freerolls_special#', '', $get));
		$mainArgv['puslapiai'] = $pn->show_nav();
		$psl = $pn->get_info();

		list($tOffset, $gmt) = $locale->timezone((int)$user->get_user('timezone'));
		if ($currPg>1) {
			$get['fapg']=$currPg;
		}
		if (isset($get['far'])) {
			unset($get['far']);
		}
		$urlUnsub = $this->linkas('freerolls_special#', '', $get + array('far' => ''));
		for ($k=$psl['from']; $k>0 && $k<=$psl['to'];$k++) {
			$tournament = $tournaments[$k-1];
			$mainArgv['list.freerolls'] .= $tpl->parse('alerts:freeroll.item', array(
				'date' => $locale->gmdatef($tournament['date'] + $tOffset, 'freerollTime'),
				'title' => htmlspecialchars($tournament['name']),
				'url' => '/' . $tournament['alias'] . '/freerolls/' . $tournament['id'] . '.htm',
				'urlunsub' => $urlUnsub . $tournament['id']
			));
		}

		if (is_array($argv) && isset($argv['unparsed'])) return $mainArgv;
		return $tpl->parse('alerts:main', $mainArgv);
	}

	function renderSaveAlertsTournament($id, $isAjax) {
		$user = moon::user();
		$userId = $user->get_user_id();
		if (!$userId) {
			$page = moon::page();
			$page->page404();
		}
		$tpl = $this->load_template();

		$id = intval($id);
		$tournament = $this->getTournament(abs($id));
		if (empty($tournament)) {
			$page = moon::page();
			$page->page404();
		}
		$roomId = $tournament['room_id'];

		$subscriptions = $this->getUserSubscriptions($userId);
		$isSubscribed = $this->isSubscribed(abs($id), $roomId, $subscriptions);

		$isSubscribing = $id > 0;
		$id = abs($id);

		if ($isSubscribing && !$isSubscribed) {
			$isUnsubbedTournament = in_array(-$id, $subscriptions['tournaments']);
			$isSubbedRoom		 = in_array($roomId, $subscriptions['rooms']);
			if ($isUnsubbedTournament) {
				$tIndex = array_search(-$id, $subscriptions['tournaments']);
				unset ($subscriptions['tournaments'][$tIndex]);
			}
			if (!$isSubbedRoom) $subscriptions['tournaments'][] = $id;
		} elseif (!$isSubscribing && $isSubscribed) {
			$isSubbedTournament = in_array($id, $subscriptions['tournaments']);
			$isSubbedRoom	   = in_array($roomId, $subscriptions['rooms']);
			if ($isSubbedTournament) {
				$tIndex = array_search($id, $subscriptions['tournaments']);
				unset ($subscriptions['tournaments'][$tIndex]);
			}
			if ($isSubbedRoom) $subscriptions['tournaments'][] = -$id;
		}

		$this->saveSubscriptionTournaments($subscriptions['tournaments']);
		$this->getUserSubscriptions($userId, $subscriptions);

		if ($isAjax) {
			$r2['subscribed'] = $this->isSubscribed($id, $roomId, $subscriptions);
			$r2['sub_link'] = $this->linkas('#', 'subscribe.' . ($r2['subscribed'] ? -$id : $id));
			$r2['id'] = $id;
			$list = $this->renderAlertsSB(array('unparsed' => true));
			$r = array(
				'id' => abs($id),
				'link' => $tpl->parse('subscribe', $r2),
				'freerolls' => $list['list.freerolls']
			);
			return $r;
		}
	}

	function saveSubscriptionTournaments($data) {
		$user = &moon::user();
		$userId = $user->get_user_id();
		if (empty ($userId)) return;
		$validTournaments = $this->getTournamentsLight();
		$vMap = array();
		foreach ($validTournaments as $v) {
			$vMap[] = $v['id'];
			$vMap[] = -$v['id'];
		}
		$tournaments = array_intersect($vMap, $data);
		$tournaments = implode(',', $tournaments);
		$this->db->query('
			INSERT INTO ' . $this->table('SpecSubscriptions') . '
			(user_id, tournaments) VALUES(' . $userId . ', "' . $tournaments . '")
			ON DUPLICATE KEY
			UPDATE tournaments="' . $tournaments . '"
		');
	}

	function saveSubscriptionRooms($data) {
		$user = &moon::user();
		$userId = $user->get_user_id();
		if (empty ($userId)) return ;
		$dbData = $this->db->single_query_assoc('
			SELECT rooms,tournaments
			FROM ' . $this->table('SpecSubscriptions') . '
			WHERE user_id=' . $userId . '
		');
		if (!(array_key_exists('room', $data))) return;
		$data = is_array($data['room']) ? array_keys($data['room']) : array();
		$t = $a = $d = $r = array();
		if (!empty($dbData)) {
			$a = explode(',',$dbData['tournaments']);
			$r = explode(',',$dbData['rooms']);
			foreach ($a as $k => $v) {
				if ($v === '') {unset($a[$k]); continue;}
				if ($v<0) $d[$k] = -$v;
			}
		}

		$r = array_diff($r,$data);
		if (!empty($d) && !empty($r)) {
			$t = $this->db->array_query('SELECT id
				FROM ' . $this->table('SpecTournaments') . '
				WHERE room_id IN (' . implode(',', $r) . ')
					AND id IN (' . implode(',', $d) . ')
			');
		}

		foreach ($t as $v) if (($k = array_search($v[0], $d))!==FALSE) unset($a[$k]);

		$validRooms = $this->db->array_query_assoc('
			SELECT id
			FROM ' . $this->table('Rooms') . '
			WHERE is_hidden=0
			', 'id');

		$r = array_intersect(array_keys($validRooms), $data);
		$r = implode(',', $r);
		$t = implode(',', $a);
		$this->db->query('
			INSERT INTO ' . $this->table('SpecSubscriptions') . '
			(user_id, rooms, tournaments) VALUES(' . $userId . ', "' . $r . '", "' . $t . '")
			ON DUPLICATE KEY
			UPDATE rooms="' . $r . '", tournaments="' . $t . '"
		');
	}

	function getUserSubscriptions($userId, $setData = NULL) {
		static $cache = array();
		if (NULL !== $setData) {
			$cache[$userId] = $setData;
			return $setData;
		}
		if (isset ($cache[$userId])) {
			return $cache[$userId];
		}

		$data = $this->db->single_query_assoc('
			SELECT rooms,tournaments FROM ' . $this->table('SpecSubscriptions') . '
			WHERE user_id=' . intval($userId) . '
		');
		if (!empty($data)) {
			$data = array(
				'rooms' => explode(',', $data['rooms']),
				'tournaments' => explode(',', $data['tournaments'])
			);
			if ($data['rooms'][0] === '') unset($data['rooms'][0]);
			if ($data['tournaments'][0] === '') unset($data['tournaments'][0]);
		} else $data = array('rooms' => array(), 'tournaments' => array());
		$cache[$userId] = $data;
		return $cache[$userId];
	}

	function getUserRooms() {
		$user = &moon::user();
		$userSubscriptions = $this->getUserSubscriptions($user->get_user_id());
		// available rooms
		$locale = &moon::locale();
		$roomsT = $this->db->array_query('
			SELECT DISTINCT room_id,room_id
			FROM ' . $this->table('SpecTournaments') . '
			WHERE hide = 0 AND `date` >= ' . $locale->now() . '
		', TRUE);
		$roomIds = array_unique(array_merge(
			  array_keys($roomsT),
			  $userSubscriptions['rooms']));
		if (0 == count($roomIds)) return array();
		return $this->db->array_query_assoc('
			SELECT id,name,favicon logo
			FROM ' . $this->table('Rooms') . '
			WHERE is_hidden=0 AND id IN (' . implode(',', $roomIds) . ')
			ORDER BY name
			', 'id');
	}


	function getTournamentsLight() {
		return $this->db->array_query_assoc('SELECT id, room_id FROM ' . $this->table('SpecTournaments') . $this->sqlWhere());
	}

	function getTournament($id) {
		return $this->db->single_query_assoc('SELECT id, room_id FROM ' . $this->table('SpecTournaments') . $this->sqlWhere() . ' AND id=' . intval($id));
	}

	function sqlWhere() {
		if (isset ($this->tmpWhere)) return $this->tmpWhere;
		$locale = &moon::locale();
		$now = ceil($locale->now() / 100) * 100;
		$where = " WHERE hide=0 AND `date`>" . $now;
		$a = array();

		/*if (strlen($a['room'])) $where .= ' AND room_id=' . intval($a['room']);
		if ($a['team']) $where .= ' AND team_id=' . intval($a['team']);
		list($min, $max) = $this->getPrizeRange($a['prize']);
		if ($min) $where .= ' AND prizepool >= ' . $min;
		if ($max) $where .= ' AND prizepool <= ' . $max;
		if ($a['df']) {
			$where .= ' AND TO_DAYS(FROM_UNIXTIME(`date`))>=' . intval($a['df']);
			if (!$a['dt']) $a['dt'] = $a['df'];
		}
		if ($a['dt']) $where .= ' AND TO_DAYS(FROM_UNIXTIME(`date`))<=' . intval($a['dt']);*/
		return ($this->tmpWhere = $where);
	}




}
