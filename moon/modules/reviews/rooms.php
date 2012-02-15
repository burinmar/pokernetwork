<?php


class rooms extends moon_com {


	function events($event, $par) {
		$this->use_page('RoomsList');
		$p = & moon :: page();
        if ($p->requested_event('REST')) {
        	$p->page404();
        }
		if ($event != '') {
			$par[0] = $event;
		}
		if (!isset($par[0])) {
			$par[0] = '';
		}
		$d = $p->uri_segments();
		switch ($par[0]) {

			case 'mac' :
				$show = 'mac';
				break;

			case 'linux' :
				$show = 'linux';
				break;

			case 'www' :
				$show = 'www';
				break;

            case 'all-rooms' :
				$show = 'all';
				break;

			default :
				$p->page404();
		}
		$this->set_var('show', $show);
	}


	function main($vars)
	{
		return $this->viewList($vars);
	}



	function viewList($vars)
	{
		$show = $vars['show'];
		$t = & $this->load_template();
		//return $t->parse('tmp');
		$page = & moon :: page();
		$m = array('items2' => '', 'intro'=>'', 'tab-item'=>'');

		$result = '';

		$navi = & moon::shared('sitemap');
		if (count($pageInfo = $navi->getPage())) {
			$m['intro'] = $pageInfo['content_html'];
			if (strpos($m['intro'],'<p>{...}</p>')) {
				list($m['intro'],$m['intro2']) = explode('<p>{...}</p>',$m['intro'],2);
			}
		}
        $title = $navi->getTitle();
        $onTab = $navi->on();
		//tabs
		$tabs = $t->parse_array('tabs');
		$a = array();

		foreach ($tabs as $k=>$v) {
			if ($a['url'] = $navi->getLink($k)) {
				$a['class-on'] = $onTab == $k ? ' class="on"' : '';
				$a['name'] = ('' == $v) ? $navi->getTitle($k) : $v;
				$a['name'] = htmlspecialchars($a['name']);
				$m['tab-item'] .= $t->parse('tab-item',$a);
			}
		}

		$m['str_header'] = htmlspecialchars($title);
		$headerTitle = $title;

        //promotions
		$aPromo = $this->getPromotions();
		$promo = array();
		foreach ($aPromo as $v) {
			$rID = $v['room_id'];
			if (!isset($promo[$rID])) {
				$promo[$rID] = array(0,'');
			}
			elseif ($promo[$rID][0]>2) {
				continue;
			}
			$promo[$rID][0]++;
			$promo[$rID][1] .= $t->parse('promo',array($v['title']));
		}



		/* rooms */
		$rooms = $this->getRooms($show);
		$items = '';
		//$srcLogo = $this->get_dir('srcLogo');
		$m['showFriendly'] = FALSE;
		$oReview = $this->object('review');

		$os = array(1=>'Win', 2=>'Mac OS', 4=>'Linux', 8=>'Instant Play');
		foreach ($rooms as $i => $d) {
			$d['class-odd'] = $i % 2 ? '' : ' class="odd"';
			if ($d['logo']) {
				$d['logo'] = img('rw',$d['id'], $d['logo'].'?2');
			}
			$supportedOs = (int) $d['software_os'];
			foreach ($os as $k=>$v) {
				if ($supportedOs & $k) {
					$d['osAlt' . $k] = $v;
				}
			}
			$d['roomLink'] = '/' . $d['alias'] . '/';
			$d['roomName'] = $d['name'];
			$d['intro_text'] = htmlspecialchars($d['bonus_text']);
			//$d['intro_text'] = preg_replace('/[€$][0-9]{1,}([,\.\s]{1}[0-9]+)*(k[\s]+)*/u', '<strong>$0</strong>', $d['intro_text']);
			//$d['intro_text'] = preg_replace('/[0-9]{2,}%/', '<strong>$0</strong>', $d['intro_text']);
			$d['promo'] = isset($promo[$d['id']]) ? $promo[$d['id']][1] : '';
			$d['roomID'] = $d['id'];
			$ratings = $oReview->ratings($d['ratings']);
			$d['ratings'] = '';
			foreach ($ratings as $v) {
				list($a['rate'], $a['name']) = $v;
				$a['rate%'] = floor($a['rate'] * 10);
				$d['ratings'] .= $t->parse('ratings', $a);
			}
			$d['editors_rating%'] = $d['editors_rating'];
			$d['editors_rating'] = number_format($d['editors_rating'] / 10,1);

			$d['roomDownloadLink'] = '/' . $d['alias'] . '/download/';


			$m['items2'] .= $t->parse('items2', $d);
		}
 		$result = $t->parse('viewList', $m);
		return $result;
	}


	//***************************************
	//           --- DB AND OTHER ---
	//***************************************
	function getRooms($pg)
	{
		//$myCountry = geo_my_country();

        switch ($pg) {

		   case 'mac' :
				$order = 'sort_2 ASC';
				$where = ' AND software_os & 2';
				$byEditorRating = FALSE;
				break;

			case 'linux' :
				$order = 'sort_3 ASC';
				$where = ' AND software_os & 4';
				$byEditorRating = FALSE;
				break;

			/*case 'www' :
				$order = 'sort_1 ASC';
				$where = ' AND software_os & 8';
				$byEditorRating = TRUE;
				break;

			*/

			default :
				$order = 'sort_1 ASC';
				$where = '';
		}

		$sql = '
			SELECT id, name, alias, logo, bonus_code, marketing_code, software_os,bonus_text,editors_rating,ratings
			FROM ' . $this->table('Rooms') . '
			WHERE is_hidden = 0' . $where . '
			ORDER BY ' . $order;
		$result = $this->db->array_query_assoc($sql);
		//rusiuojam pagal editoriaus reitinga
		if (!empty($byEditorRating)) {
            //rusiavimo funkcija
	        function cmp_rooms($va, $vb)
			{
				$a = $b = 0;
				while ($va['editors_rating']>0) {
					$a += $va['editors_rating'] % 10;
					$va['editors_rating'] = floor($va['editors_rating'] / 10);
				}
                while ($vb['editors_rating']>0) {
					$b += $vb['editors_rating'] % 10;
					$vb['editors_rating'] = floor($vb['editors_rating'] / 10);
				}
				/*if ($a == $b) {
					$a = $va['vote_overal'];
					$b = $vb['vote_overal'];
				}*/
			    return ($a>$b ? -1 : 1);
			}

			usort($result, 'cmp_rooms');
		}
		return $result;
	}



	function getPromotions()
	{
		return $this->db->array_query_assoc('
			SELECT room_id,title
			FROM ' . $this->table('PromoList') . '
			WHERE hide=0
				AND (ISNULL(active_from) OR CURDATE()>= active_from)
				AND (ISNULL(active_to) || CURDATE()<= active_to)
			ORDER BY ord_no
		');
	}




}

?>