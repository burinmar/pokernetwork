<?php

class homepage_rooms extends moon_com {


	function main($vars)
	{

		$tpl = & $this->load_template();
		$result = '';

		if (count($rooms = $this->getTopRooms())) {
			$hasFree50 = in_array(_SITE_ID_, array('bg','china','tw','il','tr','ee','it','pt', 'es', 'lt', 'ro', 'hu')) ? FALSE : TRUE;
			$partyID = 39;
			$m = array('items' => '');
			foreach ($rooms as $i=>$room) {
				$room['nr'] = $i+1;
				$room['logo'] = ($room['logo'] != '') ? img('rw', $room['id'],$room['logo'] . '') : '';
				$room['roomLink'] = '/' . $room['alias'] . '/';
				$room['roomName'] = $room['name'];
				$room['roomDownloadLink'] = '/' . $room['alias'] . '/download/?BN=homepageRooms';
				//$room['intro_text'] = htmlspecialchars($room['intro_text']);
				$room['bonus'] = $this->currency($room['bonus_int'], $room['currency']);
				if (($hasFree50 && $room['id'] == $partyID)) {
					$room['free'] = 1;
				}
				// PN-2101 7Win Poker
				elseif ($room['id'] == 159) {
					$room['bonus'] = '33%';
				}
				$m['items'] .= $tpl->parse('items', $room);
			}
            $nav = & moon :: shared('sitemap');
			$url = $nav->getLink('rooms');
			$m['url.rooms'] = $url;
			$result = $tpl->parse('main', $m);
		}
		return $result;
	}


	function getTopRooms()
	{
		$sql = 'SELECT `id`, `name`, `alias`, `favicon` as logo, bonus_int, currency
			FROM ' . $this->table('Rooms') . '
			WHERE is_hidden = 0
			ORDER BY sort_1 ASC
			LIMIT 10
			';
		$result = $this->db->array_query_assoc($sql);
		return $result;
	}

	function currency($num, $currency) {
		$codes = array('USD' => '$', 'EUR' => '&euro;', 'GBP' => '&pound;');
		if (isset ($codes[$currency])) {
			return $codes[$currency] . '' . $num;
		}
		else {
			return $num . '&nbsp;' . $currency;
		}
	}


}

?>