<?php


class rooms_box extends moon_com {


	function main($vars) {
		$page = & moon :: page();
		$tpl = & $this->load_template();
		$result = '';
		$oReview = $this->object('review');
		if (count($rooms = $this->getTopRooms())) {
			$hot = rand(0, count($rooms));
			$m = array('items' => '', 'items1' => '');
			foreach ($rooms as $i => $room) {
				$room['img'] = ($room['favicon'] != '') ? img('rw', $room['id'], $room['favicon'] . ''):'';
				$room['roomLink'] = '/' . $room['alias'] . '/';
				$room['roomName'] = htmlspecialchars($room['name']);
				//$room['roomDownloadLink'] = '/' . $room['alias'] . '/download/?BN=roomsBox';
				$er = $room['editors_rating'];
				$room['editors_rating'] = number_format($room['editors_rating'] / 10, 1);

				$ratings = $oReview->ratings($room['ratings']);
				$room['ratings'] = '';
				foreach ($ratings as $v) {
					list($a['rate'], $a['name']) = $v;
					$a['rate%'] = floor($a['rate'] * 10);
					$room['ratings'] .= $tpl->parse('ratings', $a);
				}
				if ($i) {
					$room['class1'] = '';
					$room['class2'] = ' hide';
				}
				else {
					$room['class1'] = ' active';
					$room['class2'] = '';
				}


				$m['items'] .= $tpl->parse('items', $room);

			}
			$m['url.rooms'] = moon :: shared('sitemap')->getLink('rooms');
			$result = $tpl->parse('main', $m);
		}
		return $result;
	}


	function getTopRooms() {
		$sql = 'SELECT `id`, `name`, r.`alias`, `favicon`, `editors_rating`,`ratings`
			FROM ' . $this->table('Rooms') . ' r, ' . $this->table('Trackers') . " t
			WHERE is_hidden = 0 AND r.id=t.parent_id AND t.alias=''".'
			ORDER BY editors_rating DESC, sort_1 ASC
			LIMIT 10
			';
		$result = $this->db->array_query_assoc($sql);
		return $result;
	}


}

?>