<?php
class freerolls_special extends moon_com {

	function onload() {
		$this->formFilter = & $this->form();
		$this->formFilter->names('game', 'prize', 'room','team','df','dt');
		$this->formFilter->fill($_GET);
		$this->myTable = $this->table('SpecTournaments');
	}

	function events($event, $par) {
		switch ($event) {
			case 'sync-activate':
				//aktyvuojam spec. freeroll nusiurbima is com
				cronTask('tour.sync#');
				$page = & moon :: page();
				$page->set_local('transporter', 'ok');
				break;

			case 'sync-export':
				//eksportuojam special freerolus (praso transporteris)
				$specTourList = array();
				if(isset($par['timestamp'])){
					$timestamp = intval($par['timestamp']);
					$specTourList = $this->db->array_query_assoc("
						SELECT *  FROM ".$this->myTable."
						WHERE `updated` > ".$timestamp." AND hide=0 AND date>" . time()
						);
				}
				$page = & moon :: page();
				$page->set_local('transporter', $specTourList);
				break;

			case 'save-subscription-rooms':
				$form = &$this->form();
				$form->names('room');
				$form->fill($_POST);
				$data = $form->get_values();
				$this->saveSubscriptionRooms($data);
				$this->redirect('#');
				break;

			default:
				if (isset ($par[1]) && $par[0] == 'subscribe') {
					$isAjax = isset($_GET['ajax']);
					$response = $this->renderSaveAlertsTournament($par[1], $isAjax);
					if ($isAjax) {
						echo str_replace(array('\t','\r','\n','\/','\"'), array('','','','/',"'"), json_encode($response));
						$this->forget(); moon_close(); exit;
					}
				}
				$page = & moon :: page();
				if ($page->uri_segments(2) === 'rss.xml' ) {
					$this->forget();
					$this->xmlRss();
					$this->forget(); moon_close(); exit;
				}
				if (isset ($par[0]) && is_numeric($par[0])) $this->set_var('currPage', $par[0]);
				$this->use_page('Tournaments');
		}
	}


	function properties() {
		return array('currPage' => '1');
	}

	function main($vars) {
		$page = & moon :: page();
		$locale = & moon :: locale();
		$tpl = & $this->load_template();
		$user = moon::user();

		/* special_freeroll_alerts */
		$u = &moon::user();
		if ($u->get_user_id()) {
			$page->insert_html($this->renderAlertsSB(), 'column');
		}


		/* filter */
		$fPrize = $this->getPrizeRange();
		$rooms = $this->getRooms();
		$tmp = $this->getRoomsRange();
		$fRooms = array();
		foreach ($tmp as $id) if (isset($rooms[$id])) $fRooms[$id] = $rooms[$id]['name'];

		$filter = & $this->formFilter;

		//puslapiavimui per get kad persiduotu papildomi filtro parametrai
		$addGet = $filter->get_values();
		foreach ($addGet as $k => $v) if (!$v) unset ($addGet[$k]);

		$fForm = array(
			'prizes' => $filter->options('prize', $fPrize),
			'rooms' => $filter->options('room', $fRooms),
			'df' => intval($filter->get('df')),
			'dt' => intval($filter->get('dt')),
			'classIsOn' => count($addGet) ? ' filter-on' : '',
			'!action' => $this->linkas('#')
		);
		$fForm['interval'] = '';
		if ($fForm['df']) $fForm['interval'] = $locale->datef($locale->from_days($fForm['df']), '%d %{m.Sau} %Y');
		if ($fForm['dt']) $fForm['interval'] .= ' - ' . $locale->datef($locale->from_days($fForm['dt']), '%d %{m.Sau} %Y');
		$filterHTML = $tpl->parse('filter', $fForm);

		$m = array(
			'filter' => $filterHTML,
			'items' => '',
			'monthNames' => '"'.implode('", "', $locale->months_names() ) .'"',
			'weekNames' => '"'.implode('", "', $locale->get_array('w.Pirm') ) .'"',
			'siteID' => (_SITE_ID_ == 'uk' || _SITE_ID_ == 'il') ? _SITE_ID_ : '',
			//'calendarUrl' => $this->linkas('tournaments_calendar#'),
			'tab-item' => ''
		);

		$m['goRss'] = $this->linkas('sys.rss', '', array(
			'custom' => 'freerolls:*'
		));
		$page->head_link(substr( $this->linkas('#', 'rss.xml'), 0, -4), 'rss', $page->title());

		//$m['commonHeader'] = $this->common_header($m['goRss']);

		$sitemap = & moon :: shared('sitemap');
		$pageInfo = $sitemap->getPage();
		$m['pageTitle'] = $sitemap->getTitle();
		$m['pageIntro'] = $pageInfo['content_html'];

		$userId = $user->get_user_id();
		if ($userId) {
			$subscriptions = $this->getUserSubscriptions($userId);
			$page->css('/css/freerolls.css');
			$page->js('/js/freerolls.js');
		}

		if ($count = $this->countTournaments()) {
			//puslapiavimas
			$pn = & moon :: shared('paginate');
			$pn->set_curent_all_limit($vars['currPage'], $count, 20);
			$pn->set_url($this->linkas('#', '{pg}', $addGet), $this->linkas('#', '', $addGet));
			$pnInfo = $pn->get_info();

			$user = & moon :: user();
			list($tOffset, $gmt,, $tzName) = $locale->timezone( (int)$user->get_user('timezone') );
			$rec = $this->getTournaments($pnInfo['sqllimit']);

			$now = $locale->now();
			//$src = $this->get_var('srcRooms');
			$txt = & moon :: shared('text');

			$spotlights = $this->getSpotlights();

			$c = 0;
			foreach ($rec as $i => $v) {
				$r['c'] = ++$c;
				$r['classColor'] = $i ? '' : ' first';
				$r['id'] = $v['id'];
				$r['name'] = htmlspecialchars($v['name']);
				$r['prizePool'] = $v['prizepool'] ? $v['prizepool'] : '';
				if ($v['qualification_points'] < 0) $r['pointsReq'] = '-';
				else $r['pointsReq'] = $v['qualification_points'];
				$startTime = $v['date'] + $tOffset;
				$r['tourDate'] = $locale->gmdatef($startTime, 'freerollTime');
				$r['startsIn'] = $txt->ago($v['date'], TRUE, FALSE);
				$r['qStarted'] = 0;
				//$r['bonus'] = $v['bonus'];
				$addQSd = FALSE;
				if ($v['qualification_from'] !== '0000-00-00 00:00:00') {
					$time = strtotime($v['qualification_from'] . ' +0000');
					if ($time < $now) $r['qStarted'] = 1; else $addQSd = TRUE;
					$r['qualificationFrom'] = $locale->gmdatef($time + $tOffset, 'freerollTime');
					if ($v['qualification_to'] !== '0000-00-00 00:00:00') {
						$time = strtotime($v['qualification_to'] . ' +0000');
						if ($time < $now) {
							$r['qStarted'] = 0;
							$addQSd = FALSE;
						}
						$r['qualificationTo'] = $locale->gmdatef($time + $tOffset, 'freerollTime');
					} else {
						$r['qualificationTo'] = $r['tourDate'];
					}
				}
				else {
					$r['qualificationTo'] = 'n/a ';
					$r['qualificationFrom'] ='';
				}
				$r['password'] = htmlspecialchars($v['password']);
				if ($v['password_from']>86400 && $v['password_from']>$now) {
					$r['passFrom'] =$locale->gmdatef($v['password_from'] + $tOffset, 'freerollTime');
				}

				$r['addNotes'] = $v['body_html'];
				if (isset($rooms[$v['room_id']])) {
					$room = $rooms[$v['room_id']];
					$r['room'] = $room['name'];
					$r['bonus'] = $room['bonus_text'];
					list($room['bonus_code'], $room['marketing_code']) = explode('|', $room['bonus_code'] . '|');
					$r['bonusCode'] = htmlspecialchars($room['bonus_code']);
					$r['marketingCode'] = htmlspecialchars($room['marketing_code']);
					$r['prizePool'] = $v['prizepool'] ? $this->currency($v['prizepool'], $room['currency']) : '';
					$r['goRoom'] = '/' . $room['alias'] . '/ext/';
					$r['url.freeroll'] = '/' . $room['alias'] . '/freerolls/' . $v['id'] . '.htm';
					//if ($room['logo']) $r['logo'] = $src . $room['logo'];
					if ($room['logo']) $r['logo'] = img('rw', $room['id'], $room['logo'].'?2');
				}
				else {
					$r['goRoom'] = $r['bonus'] = $r['bonusCode'] = $r['marketingCode'] = '';
					$r['room'] = $v['room_id'];
					$r['url.freeroll'] = '';
				}
				$r['spotlight'] = '';
				if (isset($spotlights[$v['spotlight_id']])) {
					$r['spotlight'] = '/w/spotlight/' . $spotlights[$v['spotlight_id']]['img'];
				}

				$r['subscribe'] = !empty($userId);
				if ($r['subscribe']) {
					$r2['subscribed'] = $this->isSubscribed($v['id'], $v['room_id'], $subscriptions);
					$r2['sub_link'] = $this->linkas('#', 'subscribe.' . ($r2['subscribed'] ? -$v['id'] : $v['id']));
					$r2['id'] = $v['id'];
					$r['subscribe'] = $tpl->parse('subscribe', $r2);
				}


				$m['items'] .= $tpl->parse('items', $r);
			}
			$m['gmt'] = $gmt;
			$m['tzName'] = $tzName;
			$m['paging'] = $pn->show_nav();
		}
		$res = $tpl->parse('main', $m);
		return $res;
	}

	function isSubscribed($id, $roomId, &$subscriptions) {
		return in_array($id, $subscriptions['tournaments'])	|| in_array($roomId, $subscriptions['rooms']) && !in_array(-$id, $subscriptions['tournaments']);
	}

	function renderAlertsSB($argv = null) {
		$tpl = $this->load_template();
		$page = &moon::page();
		$user = &moon::user();
		$userId = $user->get_user_id();
		$locale = &moon::locale();
		if (!$userId) return '';

		$page->css('/css/freerolls.css');
		$page->js('/js/freerolls.js');

		$subscriptions = $this->getUserSubscriptions($userId);

		$mainArgv = array(
			'list.freerolls' => '',
			'list.rooms' => '',
			'save_rooms_event' => $this->my('fullname') . '#save-subscription-rooms',
		);

		$rooms = $this->getUserRooms();
		$eventOdd = 0;
		//$srcLogos = $this->get_var('srcRooms');
		foreach ($rooms as $room) {
			$mainArgv['list.rooms'] .= $tpl->parse('alerts:room.item', array(
				'id' => $room['id'],
				'even_odd' => $eventOdd % 2	? 'odd'	: 'even',
				//'img' => $srcLogos . $room['logo'],
				'img' => img('rw', $room['id'], $room['logo']),
				'title' => htmlspecialchars($room['name']),
				'checked' => in_array($room['id'], $subscriptions['rooms'])
			));
			$eventOdd ++;
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
		}

		$k = 0;
		$pageBy = 7;
		list($tOffset, $gmt) = $locale->timezone((int)$user->get_user('timezone'));
		foreach ($tournaments as $tournament) {
			$pg = floor($k / $pageBy) + 1;
			$mainArgv['list.freerolls'] .= $tpl->parse('alerts:freeroll.item', array(
				'pg' => $pg,
				'a11y' => $pg>1 ? TRUE : FALSE,
				'date' => $locale->gmdatef($tournament['date'] + $tOffset, 'freerollTime') /* . ' ' . $gmt */,
				'title' => htmlspecialchars($tournament['name']),
				'url' => '/' . $tournament['alias'] . '/ext/',
				'urlunsub' => $this->linkas('#', 'subscribe.' . -$tournament['id'])
			));
			$k++;
		}
		$c=count($tournaments);
		$n=$mainArgv['pCnt'] = ceil($c / $pageBy);
		$mainArgv['hPg'] = $n<2;
		$mainArgv['hI'] = $c>0;

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
		$roomIds = array_unique(array_merge(
			  $this->getRoomsRange(),
			  $userSubscriptions['rooms']));
		if (0 == count($roomIds)) return array();
		return $this->db->array_query_assoc('
			SELECT id,name,favicon logo
			FROM ' . $this->table('Rooms') . '
			WHERE is_hidden=0 AND id IN (' . implode(',', $roomIds) . ')
			ORDER BY name
			', 'id');
	}

	function getTournaments($limit = '') {
		return $this->db->array_query_assoc('SELECT * FROM ' . $this->myTable . $this->sqlWhere() . ' ORDER BY `date` asc, prizepool desc ' . $limit);
	}

	function getTournamentsLight() {
		return $this->db->array_query_assoc('SELECT id, room_id FROM ' . $this->myTable . $this->sqlWhere());
	}

	function getTournament($id) {
		return $this->db->single_query_assoc('SELECT id, room_id FROM ' . $this->myTable . $this->sqlWhere() . ' AND id=' . intval($id));
	}

	function countTournaments() {
		$result = $this->db->single_query('SELECT count(*) FROM ' . $this->myTable . $this->sqlWhere());
		return (isset ($result[0]) ? $result[0] : 0);
	}

	function sqlWhere() {
		if (isset ($this->tmpWhere)) return $this->tmpWhere;
		$locale = &moon::locale();
		$now = ceil($locale->now() / 100) * 100;
		$where = " WHERE hide=0 AND `date`>" . $now;
		$a = $this->formFilter->get_values();

		if (strlen($a['room'])) $where .= ' AND room_id=' . intval($a['room']);
		if ($a['team']) $where .= ' AND team_id=' . intval($a['team']);
		list($min, $max) = $this->getPrizeRange($a['prize']);
		if ($min) $where .= ' AND prizepool >= ' . $min;
		if ($max) $where .= ' AND prizepool <= ' . $max;
		if ($a['df']) {
			$where .= ' AND TO_DAYS(FROM_UNIXTIME(`date`))>=' . intval($a['df']);
			if (!$a['dt']) $a['dt'] = $a['df'];
		}
		if ($a['dt']) $where .= ' AND TO_DAYS(FROM_UNIXTIME(`date`))<=' . intval($a['dt']);
		return ($this->tmpWhere = $where);
	}

	function getPrizeRange($id = false) {
		$bi = array();
		$bi[] = array(0, 0, '');
		$bi[] = array(0, 500, '$0-$500');
		$bi[] = array(501, 1000, '$501-$1000');
		$bi[] = array(1001, 2000, '$1001-$2000');
		$bi[] = array(2001, 0, '>$2000');
		if ($id === false) {
			$m = array();
			foreach ($bi as $k => $v) $m[$k] = $v[2];
			return $m;
		}
		if (!isset ($bi[$id])) $id = 0;
		return $bi[$id];
	}

	// additionally used from sys.rss
	function getRooms() {
		$a = $this->db->array_query_assoc('
			SELECT id,name,r.alias,logo,bonus_text,currency, bonus_code
			FROM ' . $this->table('Rooms') . ' r, ' . $this->table('Trackers') . " t
			WHERE is_hidden = 0 AND r.id=t.parent_id AND t.alias=''"
			);
		$m = array();
		foreach ($a as $v) $m[$v['id']] = $v;
		return $m;
	}

	function getSpotlights() {
		$sql = 'SELECT id, title, img FROM spotlight WHERE is_hidden=0';
		return $this->db->array_query_assoc($sql . ' ORDER BY title', 'id');
	}


	function getRoomsRange() {
		$locale = &moon::locale();
		$a = $this->db->array_query('
			SELECT DISTINCT room_id
			FROM ' . $this->myTable . '
			WHERE hide = 0 AND `date` >= ' . $locale->now() . '
		');
		$m = array();
		foreach ($a as $v) $m[] = $v[0];
		return $m;
	}

	function currency($num, $currency) {
		$codes = array('USD' => '$', 'EUR' => '&euro;', 'GBP' => '&pound;');
		if (isset ($codes[$currency])) return $codes[$currency] . '' . $num;
		else return $num . ' ' . $currency;
	}

	// rss sugeneruoja
	function xmlRss() {
		$page = &moon::page();
		$homeURL = rtrim($page->home_url(), '\/');

		$xmlWriter = new moon_xml_write;
		$xmlWriter->encoding('utf-8');
		$xmlWriter->open_xml();
		$xmlWriter->start_node('freerolls');

		$xml = & moon::shared('rss');
		$useCache = empty($_GET['team']) && !isset($_GET['all']) ? TRUE : FALSE;
		$content = $xml->feed($homeURL . substr( $this->linkas('#', 'rss.xml'), 0, -4), 'rss', $useCache);
		if ($content === FALSE || isset($_GET['xml'])) {
			$t = & $this->load_template();
			$locale = & moon::locale();
			$sitemap = & moon :: shared('sitemap');
			$pageInfo = $sitemap->getPage();
			$pageTitle = $sitemap->getTitle();
			$pageIntro = $pageInfo['content_html'];
			// feed info
			$xml->info(
				array(
					'title' => $pageTitle,
					'description' => $pageIntro,
					'url:page' => $homeURL . $this->linkas('#'),
					'author' => 'PokerNews.com'
				)
			);
			// feed items
			$limit = isset($_GET['all']) ? '' : ' LIMIT 20';
			$items = $this->db->array_query_assoc('
				SELECT *
				FROM '.$this->myTable.$this->sqlWhere().'
				ORDER BY created DESC' . $limit
			);

			$rooms = $this->getRooms();
			$link = $homeURL . $this->linkas('#') . '#i';
			$tOffset = 0;
			foreach($items as $v) {
				$tzData = $locale->timezone($v['timezone']);
				$r['name'] = htmlspecialchars($v['name']);
				$r['prizePool'] = $v['prizepool'] ? $v['prizepool'] : '';
				if ($v['qualification_points'] < 0) $r['pointsReq'] = '-';
				else $r['pointsReq'] = $v['qualification_points'];
				$startTime = $v['date'] + $tOffset;
				$r['tourDate'] = $locale->gmdatef($startTime, 'freerollTime');
				//$r['bonus'] = $v['bonus'];
				$date = $v['qualification_from'];
				if ($date !== '0000-00-00 00:00:00') {
					$time = strtotime($v['qualification_from'] . ' +0000') + $tOffset;
					$r['qualificationFrom'] = $locale->gmdatef($time, 'freerollTime');
					if ($v['qualification_to'] !== '0000-00-00 00:00:00') {
						$time = strtotime($v['qualification_to'] . ' +0000') + $tOffset;
						$r['qualificationTo'] = $locale->gmdatef($time, 'freerollTime');
					}
					else $r['qualificationTo'] = $r['tourDate'];
				}
				else {
					$r['qualificationTo'] = 'n/a ';
					$r['qualificationFrom'] ='';
				}

				$r['addNotes'] = $v['body_html'];
				if (isset($rooms[$v['room_id']])) {
					$room = $rooms[$v['room_id']];
					$r['room'] = $room['name'];
					$r['bonus'] = $room['bonus_text'];
					$r['prizePool'] = $v['prizepool'] ? $this->currency($v['prizepool'], $room['currency']) : '';
					//$r['minDeposit'] = $this->currency($room['min_deposit'], $room['currency']);
					$r['goRoom'] = '/' . $room['alias'] . '/ext/';
				}
				$html = $t->parse('rss-item',$r);

				$xmlWriter->start_node('item');
				$xmlWriter->node('title', '', $v['name']);
				$xmlWriter->node('url', '', $link . $v['id']);
				$xmlWriter->node('description', '', $html);
				$xmlWriter->node('date', '', $r['tourDate']);
				$xmlWriter->node('timezone', '', $tzData[1]);
				if (isset($rooms[$v['room_id']])) {
					$xmlWriter->start_node('room');
					$xmlWriter->node('title', '', $room['name']);
					$xmlWriter->node('logo', '', img('rw', $room['id'], $room['logo'].'?2'));
					$xmlWriter->node('review', '', $homeURL . '/' . $room['alias'] . '/');
					$xmlWriter->node('download', '', $homeURL . '/' . $room['alias'] . '/download/');
					$xmlWriter->end_node('room');
				}
				$xmlWriter->end_node('item');

				$xml->item(
					array(
						'title' => $v['name'],
						'url' => $link . $v['id'],
						'created' => $v['created'],
						'updated' => $v['updated'],
						'summary:html' => $html,
						'category' => isset($rooms[$v['room_id']])
							? $room['name']
							: '',
					)
				);
			}
			$xmlWriter->end_node('freerolls');

			// gets content
			if (isset($_GET['xml'])) $content = $xmlWriter->close_xml();
			else $content = $xml->content();
		}

		//outputinam kontenta
		$xml->header();
		echo $content;
	}


}
