<?php

class review_freerolls extends moon_com {

	function events($event, $par) {
		if ($event === 'xml') {
			$this->forget();
			$this->xml($par[0]);
			moon_close();
			exit;
		}
		if (isset ($_GET['upcoming'])) {
			$par[0] = - 1;
		}
		if (!empty ($par[0])) {
			//$this->htmlLandingPage((int)$par[0]);
			$this->set_var('id', $par[0]);
		}
		else {
			$page = & moon :: page();
			$url = $this->linkas('tour.freerolls_special#');
			$page->redirect($url, 301);
			//$page->page404();
		}
		$this->use_page('Freeroll');
	}

	function main($vars) {
		moon :: shared('sitemap')->on('freerolls-special');
		return $this->htmlLandingPage((int) $vars['id']);
	}

	function xml($roomId) {
		$xmlWriter = new moon_xml_write;
		$xmlWriter->encoding('utf-8');
		$xmlWriter->open_xml();
		$xmlWriter->start_node('freerolls');
		// feed items
		$locale = & moon :: locale();
		$now = ceil($locale->now() / 100) * 100;
		$items = $this->db->array_query_assoc('SELECT *
			FROM ' . $this->table('SpecTournaments') . "
			WHERE hide=0 AND room_id = " . $roomId . " AND `date`>" . $now . '
			ORDER BY `date` ASC, prizepool DESC LIMIT 10');
		$page = & moon :: page();
		$homeURL = rtrim($page->home_url(), '\/');
		$link = $homeURL . $this->linkas('tour.freerolls_special#', '', 'room=' . $roomId) . '#i';
		foreach ($items as $v) {
			$tzData = $locale->timezone($v['timezone']);
			//$r['prizePool'] = $v['prizepool'] ? $this->currency($v['prizepool'], $room['currency']) : '';
			$xmlWriter->start_node('item');
			$xmlWriter->node('title', '', $v['name']);
			$xmlWriter->node('url', '', $link . $v['id']);
			$xmlWriter->node('date', '', gmdate('c', $v['date']));
			$xmlWriter->node('prizepool', '', $v['prizepool']);
			$xmlWriter->end_node('item');
		}
		$xmlWriter->end_node('freerolls');
		$content = $xmlWriter->close_xml();
		//outputinam kontenta
		header('Content-type: text/xml; charset=utf-8');
		echo $content;
	}

	function htmlLandingPage($id) {
		$page = & moon :: page();
		$page->css('/css/article.css');
		$page->css('/css/landing.css');
		$locale = & moon :: locale();
		if ($id === - 1) {
			// upcomming
			$tour = array('room_id' => - 1);
		}
		else {
			$sql = "SELECT id, name, body_html, room_id, date, qualification_from, qualification_to, hide, skin
					FROM " . $this->table('SpecTournaments') . "
					WHERE " . (isset ($_GET['master']) ? $masterField:'id') . " = " . $id;
			$tour = $this->db->single_query_assoc($sql);
		}
		$oRoom = $this->object('review');
		$roomID = $oRoom->id();
		if (empty ($tour)) {
			$page = & moon :: page();
			$page->page404();
		}
		$now = $locale->now;
		if ($roomID != (int) $tour['room_id'] || $tour['hide'] || $now > $tour['date']) {
			//turnyras pasibaiges, redirektinam i artimiausia
			$sql = "SELECT id FROM " . $this->table('SpecTournaments') . "
				WHERE hide = 0 AND room_id = " . $roomID . " AND `date`> " . $now . "
				ORDER BY `date` ASC, prizepool DESC
				LIMIT 1";
			$tour = $this->db->single_query($sql);
			$page = & moon :: page();
			if (isset ($tour[0])) {
				//$url = $this->linkas('#', $tour[0]);
				$url = '/' . $page->uri_segments(1) . '/freerolls/' . $tour[0] . '.htm';
			}
			else {
				$url = $this->linkas('tour.freerolls_special#');
			}
			$page->redirect($url, 301);
		}

		/* ROOM */
		$room = $this->db->single_query_assoc('
			SELECT id, name, r.alias, logo_big as logo, t.bonus_code
			FROM ' . $this->table('Rooms') . ' r, ' . $this->table('Trackers') . ' t
			WHERE r.id=' . $roomID . " AND is_hidden = 0 AND r.id=t.parent_id AND t.alias=''");
		if (empty ($room)) {
			$page = & moon :: page();
			$page->page404();
		}
		$tpl = & $this->load_template();
		moon :: shared('sitemap')->breadcrumb(array('' => $tour['name']));
		$trackerURI = empty ($_GET['t']) ? '':$_GET['t'];
		if ($trackerURI !== '') {
			$tracker = $this->db->single_query('
			SELECT bonus_code
			FROM ' . $this->table('Trackers') . '
			WHERE parent_id=' . $roomID . " AND alias = '" . $this->db->escape($trackerURI) . "'");
			if (isset ($tracker[0])) {
				list($room['bonus_code'], $room['marketing_code']) = explode('|', $tracker[0] . '|');
				$trackerURI .= '.htm';
			}
			else {
				$trackerURI = '';
			}
		}
		$m = array();
		$page->title($tour['name']);
		$m['name'] = htmlspecialchars($tour['name']);
		$m['text'] = $tour['body_html'];
		$m['pageID'] = preg_replace("/[^a-z0-9]/", '', strtolower($room['name']));
		if ($m['pageID'] != '' && is_numeric($m['pageID'] { 0 })) {
			$m['pageID'] = 'x' . $m['pageID'];
		}
		if ($tour['skin']) {
			$m['pageID'] .= ' ' . $tour['skin'];
		}
		$m['roomName'] = htmlspecialchars($room['name']);
		list($bCode, $mCode) = explode('|', $room['bonus_code'] . '|');
		if ($mCode) {
			$m['marketing_code'] = htmlspecialchars($mCode);
		}
		$m['bonus_code'] = htmlspecialchars($bCode);
		$m['roomDownloadLink'] = '/' . $room['alias'] . '/' . 'download/' . $trackerURI . '?BN=TourLanding';
		$m['roomLogo'] = $room['logo'] ? img('rw', $room['id'], $room['logo'] . '?2'):'';
		$m['roomLink'] = '/' . $room['alias'] . '/';

		/*$os = array(1=>'Windows', 2=>'Mac OS', 4=>'Linux', 8=>'Instant Play');
		$osT = array();
		$supportedOs = (int) $room['software_os'];
		foreach ($os as $k=>$v) {
		if ($supportedOs & $k) {
		$osT[] = $v;
		}
		}
		$m['osText'] = implode(', ', $osT);*/
		$user = & moon :: user();
		list($tOffset, $gmt,, $tzName) = $locale->timezone((int) $user->get_user('timezone'));
		$m['gmt'] = $gmt;
		$startTime = $tour['date'] + $tOffset;
		$m['tourDate'] = $locale->gmdatef($startTime, '%{m.Sau} %D %H:%I');
		$date = $tour['qualification_from'];
		if ($date !== '0000-00-00 00:00:00') {
			$time = strtotime($tour['qualification_from'] . ' +0000') + $tOffset;
			$m['qualificationFrom'] = $locale->gmdatef($time, '%{m.Sau} %D %H:%I');
			if ($tour['qualification_to'] !== '0000-00-00 00:00:00') {
				$time = strtotime($tour['qualification_to'] . ' +0000') + $tOffset;
				$m['qualificationTo'] = $locale->gmdatef($time, '%{m.Sau} %D %H:%I');
			}
			else {
				$m['qualificationTo'] = $m['tourDate'];
			}
		}
		else {
			$m['qualificationTo'] = 'n/a ';
			$m['qualificationFrom'] = '';
		}
		return $tpl->parse('main', $m);
	}

	function currency($num, $currency) {
		$codes = array('USD' => '$', 'EUR' => '&euro;', 'GBP' => '&pound;');
		if (isset ($codes[$currency])) {
			return $codes[$currency] . '' . $num;
		}
		else {
			return $num . ' ' . $currency;
		}
	}

}

?>