<?php
class freerolls_special extends moon_com {

	function onload() {
		$this->formFilter = & $this->form();
		$this->formFilter->names('game', 'prize', 'room');
		$this->formFilter->fill($_GET);
		$this->myTable = $this->table('SpecTournaments');
	}

	function events($event, $par) {
		switch ($event) {

			default:
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
			$page->insert_html($this->object('freeroll_alerts')->main(), 'column');
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
			'classIsOn' => count($addGet) ? ' filter-on' : '',
			'!action' => $this->linkas('#')
		);
		$filterHTML = $tpl->parse('filter', $fForm);

		$m = array(
			'filter' => $filterHTML,
			'items' => '',
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
			//$page->css('/css/freerolls.css');
			//$page->js('/js/freerolls.js');
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
					$addGet[$r2['subscribed'] ? 'far' : 'faa'] = $v['id'];
					$r2['sub_link'] = $this->linkas('#','', $addGet);
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

	function getUserSubscriptions($userId) {
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
		return $data;
	}

	function getTournaments($limit = '') {
		return $this->db->array_query_assoc('SELECT * FROM ' . $this->myTable . $this->sqlWhere() . ' ORDER BY `date` asc, prizepool desc ' . $limit);
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
		list($min, $max) = $this->getPrizeRange($a['prize']);
		if ($min) $where .= ' AND prizepool >= ' . $min;
		if ($max) $where .= ' AND prizepool <= ' . $max;
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
		$useCache = !isset($_GET['all']) ? TRUE : FALSE;
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
