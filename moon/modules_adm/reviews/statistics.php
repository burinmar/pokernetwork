<?php
class statistics extends moon_com {

	function onload() {
		$reviewObj = $this->object('review');
		if (is_object($reviewObj) && $roomId = $reviewObj->id()) {
			$this->roomId = $roomId;
		}
	}
	
	function events($event,$par) {
		switch($event) {
			case 'details':
				if (isset($par[2])) {
					$this->roomId = intval($par[2]);
				}
				if (isset($par[0])) {
					$this->set_var('year', intval($par[0]));
				}
				if (isset($par[1])) {
					$this->set_var('month', intval($par[1]));
				}
				$this->set_var('details', TRUE);
				break;
			default:
				if (isset($par[0])) {
					$this->roomId = intval($par[0]);
				}
				
				break;
		}
		$this->use_page('Common');
	}
	
	function properties() {
		$vars = array();
		$vars['details'] = FALSE;
		$vars['room'] = FALSE;
		$vars['year'] = '2008';
		$vars['month'] = '';
		return $vars;
	}
	
	function main($vars) {
		$page = &moon::page();
		$loc = &moon::locale();
		$tpl = &$this->load_template();
		$win = &moon::shared('admin');
		$win->active($this->my('fullname'));
		$info = $tpl->explode_ini('info');
		$main = array();
		if (isset ($this->roomId) && $this->roomId) {
			$win->active($this->my('fullname').'Room');
			$main['submenu'] = $win->subMenu(array('*id*'=>$this->roomId));
			$main['goBack'] = $vars['details'] === TRUE ? $this->linkas('#',$this->roomId) : $this->linkas('reviews#');
		} else {
			$main['goBack'] = $vars['details'] === TRUE ? $this->linkas('#') : '';
			$main['submenu'] = '';
		}
		$output = '';
		if ($vars['details'] === TRUE) {
			$month = array();
			$month['submenu'] = $main['submenu'];
			$month['goBack'] = $main['goBack'];
			
			// sections
			$items = $this->getListSections($vars['year'], $vars['month']);
			$monthListItems = '';
			$uriCount = 0;
			$uriDownloadCount = 0;
			foreach($items as $item) {
				$uriCount += $item['uriCount'];
				$uriDownloadCount += $item['uriDownloadCount'];
				$item['pName'] = ($item['p'] != '') ? $item['p'] : '[main]';
				$monthListItems .= $tpl->parse('month_list_item', $item);
			}
			$month['monthListItems'] = $monthListItems;
			// campaigns
			$items = $this->getListCampaigns($vars['year'], $vars['month']);
			$monthCampaignItems = '';
			$uriCount = 0;
			$uriDownloadCount = 0;
			foreach($items as $item) {
				$uriCount += $item['uriCount'];
				$uriDownloadCount += $item['uriDownloadCount'];
				$item['pName'] = ($item['campaign'] != '') ? $item['campaign'] : '[main]';
				$monthCampaignItems .= $tpl->parse('month_list_item', $item);
			}
			$month['monthCampaignItems'] = $monthCampaignItems;
			$month['uriCountTotal'] = $uriCount;
			$month['uriDownloadCountTotal'] = $uriDownloadCount;
			// rooms
			$items = $this->getListRooms($vars['year'], $vars['month']);
			$roomItems = '';
			$daysHeader = '';
			foreach($items as $roomId => $day) {
				$days = '';
				$daysHeader = '';
				ksort($day);
				$totalUriCount = 0;
				$i = 1;
				foreach($day as $d) {
					$totalUriCount += $d;
					if ($d==0) $d='&nbsp;';
					$days .= $tpl->parse('month_room_td', array('dayUriCnt' => $d));
					$daysHeader .= $tpl->parse('month_room_th', array('dayNr' => $i++));

				}
				$roomData = array();
				$roomData['roomName'] = $this->getRoomName($roomId);
				$roomData['roomId'] = $roomId;
				$roomData['totalUriCount'] = $totalUriCount;
				$roomData['days'] = $days;
 				$roomItems .= $tpl->parse('month_room_item', $roomData);
				$days = '';
				
			}
			$month['isStats'] = (!empty($items)) ? TRUE : FALSE;
			$month['roomItems'] = $roomItems;
			$month['daysHeader'] = $daysHeader;
			// go prev, next
			
			$month['showPrev'] = TRUE;
			$month['showNext'] = TRUE;
			
			$month['year'] = $vars['year'];
			$month['month'] = $vars['month'];
			$month['monthName'] = $this->monthName($vars['month']);
			
			$yearPrev = $vars['year'];
			$yearNext = $vars['year'];
			$monthPrev = (int)$vars['month'] - 1;
			$monthNext = (int)$vars['month'] + 1;
			
			$month['yearPrev'] = $yearPrev;
			$month['yearNext'] = $yearNext;
			
			$month['monthPrev'] = $monthPrev;
			$month['monthNext'] = $monthNext;
			
			
			$month['monthPrevName'] = $this->monthName($monthPrev);
			$month['monthNextName'] = $this->monthName($monthNext);
			
			if ($vars['month'] == '1') {
				if (!$this->yearHasStats((int)$vars['year'] - 1)) {
					$month['showPrev'] = FALSE;
				} else {
					$month['yearPrev'] = (int)$vars['year'] - 1;
					$month['monthPrev'] = 12;
				}
			} elseif ($vars['month'] == '12') {
				if (!$this->yearHasStats((int)$vars['year'] + 1)) {
					$month['showNext'] = FALSE;
				} else {
					$month['yearNext'] = (int)$vars['year'] + 1;
					$month['monthNext'] = 1;
				}
			}
			$roomId = (isset($this->roomId)) ? '.' . $this->roomId : '';
			$month['goPrev'] = $this->linkas('#') . 'details/' . $month['yearPrev'] . '.' . $month['monthPrev'] . $roomId . '.htm';
			$month['goNext'] = $this->linkas('#') . 'details/' . $month['yearNext'] . '.' . $month['monthNext'] . $roomId . '.htm';
			$month['roomTitle'] = '';
			$month['roomId'] = '';
			$month['goRoom'] = '';
			if (isset($this->roomId)) {
				$month['roomTitle'] = $this->getRoomName($this->roomId);
				$month['roomId'] = $this->roomId . '.htm';
				$month['goRoom'] = $this->linkas('reviews#edit',$this->roomId);
			}
			$output = $tpl->parse('month', $month);
		} else {
			$items = $this->getList($vars['year']);
			$listItems = '';
			$yearBefore = '';
			foreach($items as $item) {
				//$item['year'] = $vars['year'];
				$item['roomId'] = (isset($this->roomId)) ? $this->roomId . '.' : '';
				$item['monthName'] = $this->monthName($item['month']);
				$item['class'] = ($item['year'] != $yearBefore) ? 'tborder' : '';
				$listItems .= $tpl->parse('list_item', $item);
				$yearBefore = $item['year'];
			}
			
			$main['isStats'] = (!empty($items)) ? TRUE : FALSE;
			$main['roomTitle'] = '';
			$main['goRoom'] = '';
			$main['listItems'] = $listItems;
			
			if (isset($this->roomId)) {
				$main['roomTitle'] = 'for "' . $this->getRoomName($this->roomId) . '"';
				$main['goRoom'] = $this->linkas('reviews#edit',$this->roomId);
			}
			
			$output = $tpl->parse('main', $main);
		}
		
		$title = $win->current_info('title');
		$page->title($title);
		return $output;
	}
	
	function getList($year) {
		$where = '';
		if (isset($this->roomId)) {
			$where = ' WHERE room_id = ' . $this->roomId;
		}
		$sql = 'SELECT sum(uri_count) as uriCount, sum(uri_download_count) as uriDownloadCount, MONTH(day) as month, YEAR(day) as year
			FROM ' . $this->table('Stats') .
			$where . '
			GROUP BY YEAR(day) DESC, MONTH(day) DESC';
		$result = $this->db->array_query_assoc($sql);
		return $result;
	}
	
	function getListSections($year, $month) {
		$where = '';
		if (isset($this->roomId)) {
			$where = ' AND room_id = ' . $this->roomId;
		}
		if (strlen($month) == 1)  {
			$month = '0' . $month;
		}
		$date = $year . '-' . $month;
		$sql = 'SELECT sum(uri_count) as uriCount, sum(uri_download_count) as uriDownloadCount, p
			FROM ' . $this->table('Stats') . '
			WHERE `day` LIKE \'' . $date . '%\'' . $where . '
			GROUP BY p ASC';
		$result = $this->db->array_query_assoc($sql);
		return $result;
	}
	
	function getListCampaigns($year, $month) {
		$where = '';
		if (isset($this->roomId)) {
			$where = ' AND room_id = ' . $this->roomId;
		}
		if (strlen($month) == 1)  {
			$month = '0' . $month;
		}
		$date = $year . '-' . $month;
		$sql = 'SELECT sum(uri_count) as uriCount, sum(uri_download_count) as uriDownloadCount, campaign
			FROM ' . $this->table('Stats') . '
			WHERE `day` LIKE \'' . $date . '%\'' . $where . '
			GROUP BY campaign ASC';
		$result = $this->db->array_query_assoc($sql);
		return $result;
	}
	
	function getListRooms($year, $month) {
		$where = '';
		if (isset($this->roomId)) {
			$where = ' AND room_id = ' . $this->roomId;
		}
		if (strlen($month) == 1)  {
			$month = '0' . $month;
		}
		$date = $year . '-' . $month;
		$sql = 'SELECT room_id as roomId, sum(uri_count + uri_download_count) as uriCount, DAY(day) as day
			FROM ' . $this->table('Stats') . '
			WHERE `day` LIKE \'' . $date . '%\'' . $where . '
			GROUP BY roomId, day DESC';
		$result = $this->db->array_query_assoc($sql);
		$days = array();
		foreach($result as $res) {
			$days[$res['roomId']][$res['day']] = $res['uriCount'];
		}
		$d = date('t', mktime(0,0,0,$month,1));
		foreach($days as $room => $day) {
			for ($i = 1; $i <= $d; $i++) {
				$days[$room][$i] = (isset($day[$i])) ? $day[$i] : 0;
			}
		}
		return $days;
	}
	
	function getRoomStatsSections($roomId, $year, $month) {
		if (strlen($month) == 1)  {
			$month = '0' . $month;
		}
		$date = $year . '-' . $month;
		$sql = 'SELECT p, sum(uri_count) as uriTotal, sum(uri_download_count) as uriDownloadTotal
			FROM ' . $this->table('Stats') . '
			WHERE 	`day` LIKE \'' . $date . '%\' AND
				room_id = ' . $roomId . '
			GROUP BY p DESC';
		$result = $this->db->array_query_assoc($sql);
		return $result;
	}
	
	function getRoomStatsMonth($roomId, $year) {
		$sql = 'SELECT MONTH(day) as month, p, sum(uri_count) as uriTotal, sum(uri_download_count) as uriDownloadTotal
			FROM ' . $this->table('Stats') . '
			WHERE 	`day` LIKE \'' . $year . '%\' AND
				room_id = ' . $roomId . '
			GROUP BY month';
		$result = $this->db->array_query_assoc($sql);
		return $result;
	}
	
	function getRoomName($id) {
		$sql = 'SELECT `name`
			FROM ' . $this->table('Rooms') . '
			WHERE id = ' . $id;
		$result = $this->db->single_query_assoc($sql);
		if (!empty($result)) {	
			return $result['name'];	
		} else {
			return '';
		}
	}
	
	function yearHasStats($year) {
		$sql = 'SELECT count(*) as cnt
			FROM ' . $this->table('Stats') . '
			WHERE `day` LIKE \'' . $year . '%\'';
		$result = $this->db->single_query_assoc($sql);
		return ($result['cnt'] > 0) ? TRUE : FALSE;
	}
	
	function monthName($monthNr) {
		return date('F', mktime(0,0,0,$monthNr,1));
	}
	
}
?>