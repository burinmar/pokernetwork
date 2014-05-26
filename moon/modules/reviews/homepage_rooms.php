<?php

class homepage_rooms extends moon_com {

	function events($event) {
		$page = & moon :: page();
		$this->forget();
		if ($event == 'js') {
			$this->viewJavaScript();
		}
		$page->page404();
	}

	function main($vars) {
		$tpl = & $this->load_template();
		$result = '';
		$oReview = $this->object('review');
		if (count($rooms = $this->getTopRooms())) {
			$page = & moon :: page();
			$page->js($page->home_url() . 'homepage-rooms/js.php'. (in_array(geo_my_country(), array('au', 'nz')) ? '?g=au' : ''));
			$m = array('items' => '', 'intro' => '');

			/*promotions*/
			$aPromo = $this->getPromotions();
			$promo = array();
			foreach ($aPromo as $v) {
				$rID = $v['room_id'];
				if (!isset ($promo[$rID])) {
					$promo[$rID] = array(0, '');
				}
				elseif ($promo[$rID][0] > 2) {
					continue;
				}
				$promo[$rID][0]++;
				$promo[$rID][1] .= $tpl->parse('promo', array($v['title']));
			}

			/* top list */
			foreach ($rooms as $i => $room) {
				$room['img'] = ($room['favicon'] != '') ? img('rw', $room['id'], $room['favicon'] . ''):'';
				$room['roomLink'] = '/' . $room['alias'] . '/';
				$room['roomName'] = htmlspecialchars($room['name']);
				$room['roomDownloadLink'] = '/' . $room['alias'] . '/download/?BN=homepageRooms';
				$er = $room['editors_rating'];
				$room['editors_rating'] = number_format($room['editors_rating'] / 10, 1);
				$m['items'] .= $tpl->parse('items', $room);
				if (!$i) {
					$ratings = $oReview->ratings($room['ratings']);
					$room['ratings'] = '';
					foreach ($ratings as $v) {
						list($a['rate'], $a['name']) = $v;
						$a['rate%'] = floor($a['rate'] * 10);
						$room['ratings'] .= $tpl->parse('ratings', $a);
					}
					$room['editors_rating%'] = $er;
					$room['logo'] = ($room['logo'] != '') ? img('rw', $room['id'], $room['logo'] . '?2'):'';
					$room['promo'] = isset ($promo[$room['id']]) ? $promo[$room['id']][1]:'';
					$m['intro'] = $tpl->parse('intro', $room);
				}
			}
			$m['url.rooms'] = moon :: shared('sitemap')->getLink('rooms');
			$result = $tpl->parse('main', $m);
		}
		return $result;
	}

	function viewJavaScript() {
		header('Content-type: text/javascript; charset=UTF-8');
		if (!is_dev()) {
			header('Expires: ' . gmdate('r', time() + 600), TRUE);
			header('Cache-Control: max-age=' . 600, TRUE);
			header('Pragma: public', TRUE);
		}
		$result = '';
		$tpl = & $this->load_template();
		$oReview = $this->object('review');
		if (count($rooms = $this->getTopRooms())) {
			$m = array('items' => '', 'intro' => '');

			/*promotions*/
			$aPromo = $this->getPromotions();
			$promo = array();
			foreach ($aPromo as $v) {
				$rID = $v['room_id'];
				if (!isset ($promo[$rID])) {
					$promo[$rID] = array(0, '');
				}
				elseif ($promo[$rID][0] > 2) {
					continue;
				}
				$promo[$rID][0]++;
				$promo[$rID][1] .= $tpl->parse('promo', array($v['title']));
			}

			/* top list */
			$m['jsRooms'] = $m['jsImages'] = array();
			foreach ($rooms as $i => $room) {
				$room['img'] = ($room['favicon'] != '') ? img('rw', $room['id'], $room['favicon'] . ''):'';
				$room['roomLink'] = '/' . $room['alias'] . '/';
				$room['roomName'] = htmlspecialchars($room['name']);
				$room['roomDownloadLink'] = '/' . $room['alias'] . '/download/?BN=homepageRooms';
				$er = $room['editors_rating'];
				$room['editors_rating'] = number_format($room['editors_rating'] / 10, 1);
				$ratings = $oReview->ratings($room['ratings']);
				$room['ratings'] = '';
				foreach ($ratings as $v) {
					list($a['rate'], $a['name']) = $v;
					$a['rate%'] = floor($a['rate'] * 10);
					$room['ratings'] .= $tpl->parse('ratings', $a);
				}
				$room['editors_rating%'] = $er;
				$room['logo'] = ($room['logo'] != '') ? img('rw', $room['id'], $room['logo'] . '?2'):'';
				$room['promo'] = isset ($promo[$room['id']]) ? $promo[$room['id']][1]:'';
				$html = $tpl->parse('intro', $room);
				$html = preg_replace('~[\s\t\n\r]{2,}~', ' ', $html);
				$m['jsRooms'][] = '"' . $room['id'] . '" : "' . $tpl->ready_js($html) . '"';
				if ($room['logo']) {
					$m['jsImages'][] = '(new Image()).src="' . $room['logo'] . '"';
				}
			}
			$m['jsRooms'] = implode(",\n", $m['jsRooms']);
			$m['jsImages'] = implode(",\n", $m['jsImages']);
			$result = $tpl->parse('javascript', $m);
		}
		echo $result;
		moon_close();
		exit;
	}

	//***************************************
	//           --- DB AND OTHER ---
	//***************************************
	function getTopRooms() {
		$and = in_array(geo_my_country(), array('au', 'nz')) ? ' AND id<>217' : '';
		$sql = 'SELECT `id`, `name`, r.`alias`, `favicon`, `logo`, `editors_rating`,`ratings`
			FROM ' . $this->table('Rooms') . ' r, ' . $this->table('Trackers') . " t
			WHERE is_hidden = 0 AND r.id=t.parent_id AND t.alias=''".$and.'
			ORDER BY sort_1 ASC, editors_rating DESC
			LIMIT 10
			';
		$result = $this->db->array_query_assoc($sql);
		return $result;
	}

	function getPromotions() {
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