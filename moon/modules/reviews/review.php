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


	/*********************************************************************/

	function main($vars)
	{
		$tpl = & $this->load_template();

		$reviewObj = & $this->object('review');
		$roomId = $reviewObj->id();
		$room = $reviewObj->getRoom();

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
		$uris = array(1=>'','bonus','installation','tournaments');
        $aTabs = $tpl->parse_array('tabItems');
		$m['tabs'] = '';
        $on = FALSE;
		foreach ($aTabs as $k=>$v) {
			$ur = $uris[$k] ? $uris[$k] . '.html' : '';
			$c = $ur === $view ? ' class="current"' : '';
            if ($c != '') {
            	$on = $uris[$k];
				$rwPage = $k;
            }
			elseif (!isset($reviews[$k])) {
				continue;
			}
			$uri = $roomURI . $ur;
            $m['tabs'] .= $tpl->parse('tabs', array($uri, $v, $c));
		}
		if ($on === FALSE) {
			$page = $page->page404();
		}

		//review
		$page->title($room['name']);
		if (!empty($reviews[$rwPage])) {
			$rw = $reviews[$rwPage];
			$article = $rw['content_html'];
			$txt = & moon :: shared('text');
        	$article = $txt->check_timer($article);
			$article = $reviewObj->insertCodes($article);
			$page->meta('description', $rw['meta_description']);
			if ($rw['meta_title']) {
				$page->title($rw['meta_title']);
			}
		}
		else {
			$article = '';
		}

		$n = array('review'=>$article,'roomName'=>$m['roomName']);
		switch ($on) {
			case 'bonus':
				/* dabar bonuses */
				$bonuses = (int) $room['bonuses'];
				$n['bonuses0'] = $n['bonuses1'] = '';
				$bItems = $tpl->parse_array('bonuses_names');
				$a = array();
				$kiek = ceil(count($bItems) / 2);
				$col = - 1;
				foreach ($bItems as $k => $v) {
					$k--;
					$a['name'] = htmlspecialchars($v);
					$a['class:even'] = $k % 2 ? ' class="even"' : '';
					$bt = 1 << $k;
					$a['yes'] = $bt & $bonuses ? 1 : 0;
					if ($k % $kiek == 0) {
						$col++;
					}
					$n['bonuses' . $col] .= $tpl->parse('bonuses', $a);
				}
				$m['tabContent'] = $tpl->parse('viewBonus', $n);
				break;
			case 'installation':
				$m['tabContent'] = $tpl->parse('viewInstallation', $n);
				break;
			case 'tournaments':
				$m['tabContent'] = $tpl->parse('viewTournaments', $n);
				break;
			default:
				$n += array('promotions'=>'', 'deposits'=>'', 'screenshots'=>'', 'intro'=>'');
                // atskiriam pirma skyreli
				if (strlen($article)<800) {
					$n['intro'] = $article;
                    $n['review'] = '';
				}
				elseif ($pos = strpos($article, '</p>', 800)) {
					if (($posU = strpos($article, '<ul')) && $posU<$pos) {
						$n['intro'] = substr($article,0,$posU);
                    	$n['review'] = substr($article,$posU);
					}
					else {
						$n['intro'] = substr($article,0,$pos+4);
                    	$n['review'] = substr($article,$pos+4);
					}
				}
				elseif ($pos = strpos($article, '<h3', 800)) {
					$n['intro'] = substr($article,0,$pos);
                    $n['review'] = substr($article,$pos);
				}

				// promotions
				$dat = $this->getPromotions($roomId);
				foreach ($dat as $d) {
					$d['url'] = htmlspecialchars($d['url']);
					$d['title'] = htmlspecialchars($d['title']);
					$n['promotions'] .= $tpl->parse('promotions', $d);
				}
				// deposits
		        $dat = $this->getDeposits($roomId);
				$navi = & moon :: shared('sitemap');
				$isDepositReviews = $navi->getLink('deposits');
				foreach ($dat as $d) {
					if ($d['img']) {
						$d['img'] = img('deposit',$d['id'],$d['img']);
						if ($isDepositReviews && $d['has_review'] && $d['uri']) {
							$d['url'] = $this->linkas('deposits#' . $d['uri']);
						}
						$d['name'] = htmlspecialchars($d['name']);
						$n['deposits'] .= $tpl->parse('deposits', $d);
					}
				}
				//screenshots
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
					$n['screenshots'] = $tpl->parse('screenshots',$a);
				}

				//
				$m['tabContent'] = $tpl->parse('viewSummary', $n);
		}
		$res = $tpl->parse('main', $m);
		return $res;

		// jira: PN-2755
		if (($roomId == '53' || $roomId == '147') && !in_array(geo_my_country(), array('us'))) {
			$bgURL = $m['downloadURL'];
			$res = '<script type="text/javascript">var bgURL = "'.$bgURL.'";</script>' . $res;
			$page->css('<style type="text/css">/*<![CDATA*/ body {background: #2a0c01} #bg {background: url(\'/i/100billion_wallpaper.jpg\') no-repeat fixed top} html {cursor: pointer} #bodyBlock {cursor: default} /*]>*/</style>');
		}

		
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
}
?>