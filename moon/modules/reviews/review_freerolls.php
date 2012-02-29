<?php

class review_freerolls extends moon_com {


	function events($event, $par)
	{
		if ($event === 'xml' ) {
			$this->forget();
			$this->xml($par[0]);
			moon_close();
			exit;
		}
		if (isset($_GET['upcoming'])) {
			$par[0] = -1;
		}
		if (!empty($par[0])) {
			//$this->htmlLandingPage((int)$par[0]);
			$this->set_var('id', $par[0]);
		}
		else {
			$page = & moon :: page();
			$url = $this->linkas('tour.freerolls_special#');
			$page->redirect($url,301);
			//$page->page404();
		}
		$this->use_page('Freeroll');
	}

	function main($vars) {
		moon::shared('sitemap')->on('freerolls-special');
		return $this->htmlLandingPage((int)$vars['id']);
	}






	function xml($roomId) {
	    $xmlWriter = new moon_xml_write;
		$xmlWriter->encoding('utf-8');
		$xmlWriter->open_xml();
		$xmlWriter->start_node('freerolls');

		// feed items
		$locale = &moon::locale();
		$now = ceil($locale->now() / 100) * 100;
		$items = $this->db->array_query_assoc(
			'SELECT *
			FROM '.$this->table('SpecTournaments')."
			WHERE hide=0 AND room_id = ".$roomId." AND `date`>" . $now.'
			ORDER BY `date` ASC, prizepool DESC LIMIT 10'
			);

		$page = &moon::page();
		$homeURL = rtrim($page->home_url(), '\/');
		$link = $homeURL . $this->linkas('tour.freerolls_special#', '', 'room='.$roomId) . '#i';
		foreach($items as $v) {
			$tzData = $locale->timezone($v['timezone']);
			//$r['prizePool'] = $v['prizepool'] ? $this->currency($v['prizepool'], $room['currency']) : '';
			$xmlWriter->start_node('item');
			$xmlWriter->node('title', '', $v['name']);
			$xmlWriter->node('url', '', $link . $v['id']);
			$xmlWriter->node('date', '', gmdate('c',$v['date']));
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
		$locale = & moon :: locale();
		if ($id === -1) {
			// upcomming
			$tour = array('room_id' => -1);
		}
		else {
			$masterField = 'com' == _SITE_ID_ ? 'id' : 'master_id';
			$sql = "SELECT id, name, body_html, room_id, date, qualification_from, qualification_to, hide, exclusive, $masterField as master_id, tags, skin
					FROM ".$this->table('SpecTournaments')."
					WHERE ".(isset($_GET['master']) ? $masterField : 'id')." = ".$id;
			$tour = $this->db->single_query_assoc($sql);
		}
		$oRoom = $this->object('review');
		$roomID = $oRoom->id();


		if (empty($tour)) {
			$page = & moon :: page();
			$page->page404();
		}
		$now = $locale->now;
		//$roomID = intval($tour['room_id']);
		if ($roomID != (int) $tour['room_id'] || $tour['hide'] || $now > $tour['date']) {
			//turnyras pasibaiges, redirektinam i artimiausia
			$sql = "SELECT id FROM ".$this->table('SpecTournaments')."
				WHERE hide = 0 AND room_id = ".$roomID." AND `date`> ".$now."
				ORDER BY `date` ASC, prizepool DESC
				LIMIT 1";
			$tour = $this->db->single_query($sql);
			$page = & moon :: page();
			if (isset($tour[0])) {
				//$url = $this->linkas('#', $tour[0]);
				$url = '/' . $page->uri_segments(1) .'/freerolls/' . $tour[0] . '.htm';
			}
            else {
            	$url = $this->linkas('tour.freerolls_special#');
            }
			$page->redirect($url,301);
		}

		/* ROOM */
		$room = $this->db->single_query_assoc('
			SELECT id, name, alias, logo_big as logo, us_friendly, marketing_code, bonus_code, software_os, bonus_text, currency
			FROM ' . $this->table('Rooms') . '
			WHERE id=' . $roomID . ' AND is_hidden = 0'
			);
		if (empty($room)) {
			$page = & moon :: page();
			$page->page404();
		}
		$tpl = & $this->load_template();

		moon::shared('sitemap')->breadcrumb(array(''=>$tour['name']));

		//$srcLogo = $this->get_dir('srcLogo');

		$trackerURI = empty($_GET['t']) ? '' : $_GET['t'];
		if ($trackerURI !== '') {
			$tracker = $this->db->single_query('
			SELECT bonus_code
			FROM ' . $this->table('Trackers') . '
			WHERE parent_id=' . $roomID . " AND alias = '" . $this->db->escape($trackerURI) . "'"
			);
			if (isset($tracker[0])) {
				list($room['bonus_code'], $room['marketing_code']) = explode('|', $tracker[0] . '|');
				$trackerURI .= '.htm';
			}
			else {
				$trackerURI = '';
			}
		}

		$m = array();
		$m['name'] = htmlspecialchars($tour['name']);
		$m['text'] = $tour['body_html'];
		$m['pageID'] = preg_replace("/[^a-z0-9]/",'',strtolower($room['name']));
		if ($m['pageID'] != '' && is_numeric($m['pageID']{0})) {
			$m['pageID'] = 'x' . $m['pageID'];
		}
		$m['pageID'] .= ' site-' ._SITE_ID_;
		if (_SITE_ID_ == 'si' && strpos($tour['tags'], 'ŠOUPoker') !== FALSE) {
			$m['pageID'] .= ' soupoker';
		}
		else {
			$m['pageID'] .= ' master-id-' . $tour['master_id'];
		}
		if ($tour['skin']) {
			$m['pageID'] .= ' ' . $tour['skin'];
		}
		$m['roomName'] = htmlspecialchars($room['name']);
		$m['center'] = $room['marketing_code'] || $room['bonus_code'] ? '' : ' center';
		if ($room['marketing_code']) {
			$m['marketing_code'] = htmlspecialchars($room['marketing_code']);
		}
		else {
			$m['bonus_code'] = htmlspecialchars($room['bonus_code']);
		}
		$m['roomDownloadLink'] = '/' . $room['alias'] . '/' . 'download/'.$trackerURI.'?BN=TourLanding';
		$m['usFriendly'] = 'com' == _SITE_ID_ && $room['us_friendly'];
		//$m['roomLogo'] = $room['logo'] ? $srcLogo . $room['logo'] : '';
		$m['roomLogo'] = $room['logo'] ? img('rw',$room['id'], $room['logo'].'?2') : '';
		$m['roomLink'] = '/' . $room['alias'] . '/';

		$os = array(1=>'Windows', 2=>'Mac OS', 4=>'Linux', 8=>'Instant Play');
		$osT = array();
		$supportedOs = (int) $room['software_os'];
		foreach ($os as $k=>$v) {
			if ($supportedOs & $k) {
				$osT[] = $v;
			}
		}
		$m['osText'] = implode(', ', $osT);

		$user = & moon :: user();
		list($tOffset, $gmt,, $tzName) = $locale->timezone( (int)$user->get_user('timezone') );
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
			$m['qualificationFrom'] ='';
		}
		$m['exclusive'] = $tour['exclusive'];

		/* Upcoming */
		$sql = "
				SELECT id, name, body_html, date, qualification_points, qualification_from, qualification_to, prizepool
				FROM ".$this->table('SpecTournaments')."
				WHERE room_id = ".$roomID." AND `date`> ".$now." AND hide = 0
				ORDER BY `date` ASC, prizepool DESC
				LIMIT 6";
		$tours = array();//$this->db->array_query_assoc($sql);
		$m['upcoming'] = '';
		$i = 0;
		foreach ($tours as $d) {
			if ($d['id'] != $id && $i++ < 5) {
				$d['classOdd'] = $i % 2 ? ' class="odd"' : '';
				$d['name'] = htmlspecialchars($d['name']);
				$d['bonus'] = htmlspecialchars($room['bonus_text']);
				$d['prizepool'] = $d['prizepool'] ? $this->currency($d['prizepool'], $room['currency']) : '';
				$startTime = $d['date'] + $tOffset;
				$d['gmt'] = $gmt;
				$d['tourDate'] = $locale->gmdatef($startTime, '%{m.Sau} %D %H:%I');
				$date = $d['qualification_from'];
				if ($date !== '0000-00-00 00:00:00') {
					$time = strtotime($d['qualification_from'] . ' +0000') + $tOffset;
					$d['qualificationFrom'] = $locale->gmdatef($time, '%{m.Sau} %D %H:%I');
					if ($d['qualification_to'] !== '0000-00-00 00:00:00') {
						$time = strtotime($d['qualification_to'] . ' +0000') + $tOffset;
						$d['qualificationTo'] = $locale->gmdatef($time, '%{m.Sau} %D %H:%I');
					}
					else {
						$d['qualificationTo'] = $d['tourDate'];
					}
				}
				else {
					$d['qualificationTo'] = 'n/a ';
					$d['qualificationFrom'] ='';
				}
				if ($d['qualification_points'] < 0) {
					$d['qualification_points'] = '-';
				}
				$m['upcoming'] .= $tpl->parse('upcoming', $d);
			}
		}

		$ini = & moon :: moon_ini();
		$m['googleID'] = is_dev() ? '' : $ini->get('other', 'googleStatsID');
		$m['lang'] = $locale->language();
		$m['rtl'] = $locale->language() === 'he' ? ' dir="rtl"' : '';
		$m['cssMod'] = !is_dev() && file_exists('css/landing.css') ? filemtime('css/landing.css') : '0';
		return $tpl->parse('main', $m);
		echo $tpl->parse('LandingPage', $m);
		moon_close();
		exit;
	}

	function currency($num, $currency)
	{
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