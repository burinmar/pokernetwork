<?php
class review extends moon_com {
	var $teamID;
	var $teamData;
	var $usersInfo;
	var $iMember;
	var $pageInfo;

	//constructor (dalis komponento naudojama ne tik kaip komponentas)
	function parse_request(){
		$this->roomData = null;
		$this->roomID = 0;
		$p = & moon :: page();
		$d = $p->uri_segments();
		$roomID = isset ($GLOBALS['review.roomID']) ? (int) $GLOBALS['review.roomID'] : 0;
		if ($roomID) {
			$this->roomID = $roomID;
			$this->getRoom();
			if ($this->roomID) {
				if ($this->roomData['is_hidden']) {
					$navi = & moon :: shared('sitemap');
					$p->redirect($navi->getLink('rooms'), 301);
				}
				//galima adresa panagrineti
				//gal tai customPage?
				$pageID = isset ($GLOBALS['review.roomPageID']) ? (int) $GLOBALS['review.roomPageID'] : 0;
				if ($pageID) {
					$p->call_event($this->my('module') . '.review_page#', $pageID);
					return;
				}
				if (!isset ($d[2]))
					$p->redirect($d[0] . '/');
				if ($d[2] === '') {
					$p->set_local('reviews.tab', 'index');
					$this->use_page('Index');
				}
				else {
					switch ($d[2]) {
						case 'download' :
						case 'ext' :
							$alias = (isset ($d[3])) ? str_replace('.htm', '', $d[3]) : '';
							//download arba ext
							$uri = $d[2];
							if (is_object($obj = & $this->object('room_download'))) {
								$obj->renderDownloadIframe($roomID, $alias, $uri);
								// jeigu ateinam cia, vadinasi us ir nepalaiko
								$p->call_event($this->my('module') . '.room_download#');
							}
							else {
								$p->page404();
							}
							break;


						default :
                            $this->use_page('Index');
                            $this->set_var('view', $d[2]);
							/*if (!isset ($d[3]))
								$d[3] = '';
							if (!$p->call_event($this->my('module') . '.' . $d[2] . '#' . $d[3], $p->requested_event('param'))) {
								$p->page404();return;
							}*/
					}
					//if (!isset($d[3])) $p->redirect($d[0].'/');
				}
			}
		}
	}




	//***************************************
	//           --- IMA KITI KOMPONENTAI ---
	//***************************************
	// grupes ID
	function id($roomID = false) {
		if ($roomID !== false) {
			$roomID = intval($roomID);
			if ($roomID !== $this->roomID)
				$this->roomData = null;
			$this->roomID = $roomID;
		}
		return $this->roomID;
	}

	// roomso informacija
	function getRoom($fld = "") {
		if (empty($this->roomID)) {
			return array();
		}
		if (is_null($this->roomData)) {
			$q = "SELECT * FROM " . $this->table('Rooms') . " WHERE id=" . $this->roomID;
			$data = $this->db->single_query_assoc($q);
			if (!count($data)) {
				$this->roomID = 0;
			}
			$this->roomData = $data;
		}
		$res = $this->roomData;
		if ($fld) {
			$res = (isset ($this->roomData[$fld]) ? $this->roomData[$fld] : "");
		}
		return $res;
	}

	function insertCodes($s) {
		if (count($d = $this->getRoom())) {
			$s = str_replace(
				array('{bonus_code}', '{marketing_code}'),
				array($d['bonus_code'], $d['marketing_code']),
				$s
			);
		}
		return $s;
	}

	function ratings($ratings) {
		$r = array();
		$r[1] = array(0, 'Bonuses and Promotions');
		$r[2] = array(0, 'Player Traffic');
		$r[3] = array(0, 'Limits and Rake');
		$r[4] = array(0, 'Software');
		$r[5] = array(0, 'Customer Support');
		for ($i=1;$i<=5;$i++) {
			$rate = substr($ratings,-3);
			$ratings = substr($ratings,0,-3);
			$r[$i][0] = number_format($rate/10, 1);
		}
		return $r;
	}


	/*********************************************************************/

	function main($vars)
	{
		$tpl = & $this->load_template();

		$oReview = & $this->object('review');
		$roomId = $oReview->id();
		$room = $oReview->getRoom();

		$page = &moon::page();
		$page->css('/css/article.css');
		//$page->set_local('banner.roomID', $roomId);
		$page->set_local('nobanners', 1);
		$navi = & moon :: shared('sitemap');
		$navi->on('rooms');
		$navi->breadcrumb(array('' => $room['name']));
		$supportedOs = (int) $room['software_os'];

		$trackerURI = empty($_GET['t']) ? '' : $_GET['t'];
		if ($trackerURI !== '') {
			$tracker = $this->db->single_query('
			SELECT bonus_code
			FROM ' . $this->table('Trackers') . '
			WHERE parent_id=' . $roomId . " AND alias = '" . $this->db->escape($trackerURI) . "'"
			);
			if (isset($tracker[0])) {
				list($room['bonus_code'], $room['marketing_code']) = explode('|', $tracker[0] . '|');
				$trackerURI .= '.htm';
			}
			else {
				$trackerURI = '';
			}
		}

		$m = array(
			'roomName' => htmlspecialchars($room['name']),
			'url' => htmlspecialchars($room['url']),
			'email' => htmlspecialchars($room['email']),
			'established' => htmlspecialchars($room['established']),
			'auditor' => htmlspecialchars($room['auditor']),
			'network' => htmlspecialchars($room['network']),
			'games' => '',
			'promotions' => '',
			'deposits' => '',

			'alias' => htmlspecialchars($room['alias']),
			'name' => htmlspecialchars($room['name']),
			'track' => '?BN=Review',
			'visitURL'=> '/' . $room['alias'] . '/ext/'.$trackerURI.'?BN=Review',
			'downloadURL'=> '/' . $room['alias'] . '/download/'.$trackerURI.'?BN=Review',

			//os
			'isWindows' =>	$supportedOs & 1 ?  TRUE : FALSE,
			'isApple' => 	$supportedOs & 2 ?  TRUE : FALSE,
			'isLinux' => 	$supportedOs & 4 ?  TRUE : FALSE,
			'isOs' => $supportedOs,

			//'intro_text' => htmlspecialchars($room['intro_text']),
			'bonus_text' => htmlspecialchars($room['bonus_text']),
			'bonusCode' => htmlspecialchars($room['bonus_code']),
			'marketingCode' => htmlspecialchars($room['marketing_code']),
		);

		$roomURI = '/' . $room['alias'] . '/';

	   /*	$m['url.os.win'] = $navi->getLink('rooms');
        ($m['url.os.linux'] = $navi->getLink('rooms-linux')) || ($m['url.os.linux'] = $m['url.os.win']);
        ($m['url.os.mac'] = $navi->getLink('rooms-mac')) || ($m['url.os.mac'] = $m['url.os.win']);*/
		$m['url.www'] = strpos($m['url'],'http://') === 0 ? substr($m['url'],7) : $m['url'];
        $os = array(1=>'Windows', 2=>'Mac OS X', 4=>'Linux', 8=>'Instant Play');
		$osT = array();
		foreach ($os as $k=>$v) {
			if ($supportedOs & $k) {
				$osT[] = $v;
			}
		}
		$m['osText'] = implode(', ', $osT);

		if ($room['logo_big'] != '') {
			$m['logoBigSrc'] = img('rw',$room['id'], $room['logo_big'].'?3');
		}
        elseif ($room['logo'] != '') {
			$m['logoBigSrc'] = img('rw',$room['id'], $room['logo'].'?2');
		}


        /**/
		$view = isset($vars['view']) ? $vars['view'] : '';
		/* Tabs menu*/
		$reviews = $this->getReviews($roomId);
		$on = '';
        $rwPage = 0;

		//review
		$page->title($room['name']);
		if (!empty($reviews[$rwPage])) {
			$rw = $reviews[$rwPage];
			$article = $rw['content_html'];
			$txt = & moon :: shared('text');
        	$article = $txt->check_timer($article);
			$article = $oReview->insertCodes($article);
			$page->meta('description', $rw['meta_description']);
			if ($rw['meta_title']) {
				$page->title($rw['meta_title']);
			}
		}
		else {
			$article = '';
		}

		$m += array('promotions'=>'', 'deposits'=>'', 'screenshots'=>'', 'intro'=>'');

		// atskiriam pirma skyreli
		if (strlen($article)<800) {
			$m['intro'] = $article;
                  $m['review'] = '';
		}
		elseif ($pos = strpos($article, '<h2', 400)) {
			$m['intro'] = substr($article,0,$pos);
                  $m['review'] = substr($article,$pos);
		}
		elseif ($pos = strpos($article, '</p>', 800)) {
			if (($posU = strpos($article, '<ul')) && $posU<$pos) {
				$m['intro'] = substr($article,0,$posU);
                  	$m['review'] = substr($article,$posU);
			}
			else {
				$m['intro'] = substr($article,0,$pos+4);
                  	$m['review'] = substr($article,$pos+4);
			}
		}

		/* Ratings*/
		$m['editors_rating'] = number_format($room['editors_rating'] / 10, 1);
		$ratings = $this->ratings($room['ratings']);
		$m['ratings'] = '';
		foreach ($ratings as $v) {
			list($a['rate'], $a['name']) = $v;
			$a['rate%'] = floor($a['rate'] * 10);
			$m['ratings'] .= $tpl->parse('ratings', $a);
		}
		$m['editors_rating%'] = $room['editors_rating'];

		/* promotions */
		$dat = $this->getPromotions($roomId);
		foreach ($dat as $d) {
			$d['url'] = htmlspecialchars($d['url']);
			$d['title'] = htmlspecialchars($d['title']);
			$m['promotions'] .= $tpl->parse('promotions', $d);
		}

		/* deposits */
        $dat = $this->getDeposits($roomId);
		//$navi = & moon :: shared('sitemap');
		//$isDepositReviews = $navi->getLink('deposits');
		$isDepositReviews = FALSE;
		foreach ($dat as $d) {
			if ($d['img']) {
				$d['img'] = img('deposit',$d['id'],$d['img']);
				if ($isDepositReviews && $d['has_review'] && $d['uri']) {
					$d['url'] = $this->linkas('deposits#' . $d['uri']);
				}
				$d['name'] = htmlspecialchars($d['name']);
				$m['deposits'] .= $tpl->parse('deposits', $d);
			}
		}

		/*screenshots*/
		        $dat = $this->getScreenshots($roomId);
				if (count($dat)) {
					$p = &moon::page();
					$p->js('/js/jquery/lightbox-0.5.js');
					$p->css('/js/jquery/lightbox-0.5.css');
					$a = array('img-items' => '');
					foreach ($dat as $d) {
						$d['alt'] = htmlspecialchars($d['alt']);
						$d['imgOrig'] = img('rw-gallery', $d['id'], $d['img'].'?o');
						$d['img'] = img('rw-gallery', $d['id'], $d['img']);
						$a['img-items'] .= $tpl->parse('img-items', $d);
					}
					$m['screenshots'] = $tpl->parse('screenshots',$a);
				}


		/*pages */
		$m['pages']='';
		$pages = $this->getRoomPages($roomId);
		foreach ($pages as $d) {
			if ($d['is_link']) {
				$d['url'] = htmlspecialchars($d['alias']);
			}
			else {
            	$d['url'] = '/' . htmlspecialchars($d['alias']) . '.htm';
			}
			$d['title'] = htmlspecialchars($d['title']);
			$m['pages'] .= $tpl->parse('pages', $d);
		}

		// Special freerols
		$m['freerolls'] = '';
		$locale = & moon :: locale();
		$user = & moon :: user();
		list($tOffset, $gmt,, $tzName) = $locale->timezone( (int)$user->get_user('timezone') );
		$rec = $this->getSpecialFreerolls($roomId);
		$r = array();
		$m['url.moreFreerolls'] = $this->linkas('tour.freerolls_special#');
		foreach ($rec as $i=>$v) {
			//$r['url.full'] = $this->linkas('#', $v['id']);
			$r['name'] = htmlspecialchars($v['name']);
			$r['url.full'] = '/' . $room['alias'] . '/freerolls/' .$v['id'] . '.htm';
			$r['prizePool'] = $v['prizepool'] ? $this->currency($v['prizepool'], $room['currency']) : '';
			if ($v['qualification_points'] < 0) {
				$r['pointsReq'] = '-';
			}
			else {
				$r['pointsReq'] = $v['qualification_points'];
			}
            $startTime = $v['date'] + $tOffset;
			$r['tourDate'] = $locale->gmdatef($startTime, 'freerollTime');
            $date = $v['qualification_from'];
            if ($date !== '0000-00-00 00:00:00') {
				$time = strtotime($v['qualification_from'] . ' +0000') + $tOffset;
				$r['qualificationFrom'] = $locale->gmdatef($time, 'freerollTime');
				if ($v['qualification_to'] !== '0000-00-00 00:00:00') {
					$time = strtotime($v['qualification_to'] . ' +0000') + $tOffset;
					$r['qualificationTo'] = $locale->gmdatef($time, 'freerollTime');
				}
				else {
					$r['qualificationTo'] = $r['tourDate'];
				}
			}
			else {
				$r['qualificationTo'] = 'n/a ';
				$r['qualificationFrom'] ='';
			}


			$m['freerolls'] .= $tpl->parse('freerolls', $r);
		}


		$res = $tpl->parse('main', $m);
		return $res;
	}


	function getPromotions($roomId)
	{
		return $this->db->array_query_assoc('
			SELECT title,url
			FROM ' . $this->table('PromoList') . '
			WHERE room_id = ' . $roomId . ' AND hide=0
				AND (ISNULL(active_from) OR CURDATE()>= active_from)
				AND (ISNULL(active_to) || CURDATE()<= active_to)
			ORDER BY ord_no
		');
	}


    function getScreenshots($roomId)
	{
		$m = $this->db->array_query_assoc('
			SELECT *
			FROM ' . $this->table('RoomsGallery') . '
			WHERE room_id = ' . $roomId . '
			ORDER BY updated DESC LIMIT 3
		');
		if (count($m)<3) $m = array();
		return $m;
	}


	function getReviews($roomID)
	{
		$sql='
		SELECT meta_title, meta_description, content_html, page_id
		FROM '.$this->table('Reviews') . '
		WHERE room_id=' . (int)$roomID;
		return $this->db->array_query_assoc($sql, 'page_id');
	}

	function getDeposits($roomId)
	{
		return $this->db->array_query_assoc('
			SELECT `id`, `img`, `name`,`has_review`,`uri`, `tracker_url`
			FROM ' . $this->table('Deposits') . ' as d
				INNER JOIN ' . $this->table('DepositsRooms') . ' as dr
				ON d.id = dr.deposit_id
			WHERE dr.room_id = ' . $roomId . ' AND hide = 0
			ORDER BY d.sort_order
		');
	}

	function getSpecialFreerolls($roomId)
	{
		$locale = &moon::locale();
		$now = ceil($locale->now() / 100) * 100;
		$sql = "SELECT * FROM ".$this->table('SpecTournaments')."
				WHERE hide = 0 AND room_id = ".$roomId." AND `date`> ".$now."
				ORDER BY `date` ASC, prizepool DESC
				LIMIT 3";
		return $this->db->array_query_assoc($sql);
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


	function getReview($roomId)
	{
		$m = $this->db->single_query('
			SELECT content_html FROM ' . $this->table('Reviews') . '
			WHERE room_id = ' . intval($roomId)
		);
		return (empty($m[0]) ? '' : $m[0]);
	}

	function getRoomPages($roomId)
	{
		return $this->db->array_query_assoc('
			SELECT id,title,uri as alias,is_link
			FROM ' . $this->table('Pages') . '
			WHERE 	room_id = ' . $roomId . ' AND hide = 0 AND is_link<2
			ORDER BY sort ASC
		');
	}
}
?>