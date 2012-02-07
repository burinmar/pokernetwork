<?php
class sorting extends moon_com {

	function onload() {
		$this->myTable = $this->table('Rooms');
		switch ($this->my('name')) {
			case 'sorting_linux': $this->sortF = 3;break;
			case 'sorting_mac': $this->sortF = 2;break;
			default: $this->sortF = 1;
		}
	}

	function events($event, $par) {
		switch ($event) {

			case 'sort' :
				if (isset ($_POST['rows'])) {
					$this->updateSortOrder($_POST['rows']);
				}
				$this->redirect('#');
				break;

			default :
				break;
		}
		$this->use_page('Common');
	}

	function main($vars) {
		$win=&moon::shared('admin');
		$win->active($this->my('fullname'));
		//if (in_array(_SITE_ID_, array('com', 'fr', 'it'))) {
			$win->subMenu();
		//}

		$page = &moon::page();
		$tpl = &$this->load_template();

		$page->js('/js/tablednd_0_5.js');

		$rooms = $this->getRoomsList();
		$m = array('items' => '');
		foreach ($rooms as $i=>$room) {
			$val = array();
			$val['id'] = $room['id'];
			$val['roomNr'] = $i+1;
			$val['roomTitle'] = $room['name'];
			$m['items'] .= $tpl->parse('items', $val);
		}


		$m['title'] = $win->current_info('title');
		$m['goSort'] = $this->my('fullname') . '#sort';


		$res = $tpl->parse('main', $m);
		$page->title($m['title']);
		return $res;
	}

	function getRoomsList() {
		switch ($this->my('name')) {
			case 'sorting_linux': $where = ' AND software_os & 4';break;
			case 'sorting_mac': $where = ' AND software_os & 2';break;
			default: $where = '';
		}
		$sql = 'SELECT `id`, `name`, `sort_1`
			FROM ' . $this->myTable . '
			WHERE is_hidden = 0 '.
			$where .'
			ORDER BY sort_'.$this->sortF.' ASC';
		return $this->db->array_query_assoc($sql);
	}


	function updateSortOrder($rows) {
		$rows = explode(';',$rows);
		$order = array();
		$when = '';
		$i = 1;
		$ids = array();
		foreach ($rows as $id) {
			$key = intval(substr($id, 3));
			if (!$key) continue;
			$ids[] = $key;
			$when .= 'WHEN id = ' . $key . ' THEN ' . $i++ . ' ';
		}
		if (count($ids)) {
	    	$sql = 'UPDATE ' . $this->myTable . '
				SET sort_'.$this->sortF.' =
					CASE
					' . $when . '
					END
				WHERE id IN (' . implode(', ', $ids) . ')';
			$this->db->query($sql);
			blame($this->my('fullname'), 'Updated', 'Changed order');
		}
	}

}
?>