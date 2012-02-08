<?php

class promo extends moon_com {
	var $form, $formFilter;

	function onload() {
		//form of item
		$this->form = & $this->form();
		$this->form->names('id', 'room_id', 'title', 'url', 'active_from', 'active_to', 'hide', 'master_id', 'master_updated', 'updated');
		$this->form->fill();
		//main table
		$this->myTable = $this->table('PromoList');
	}

	function events($event, $par) {
		if (isset ($_POST['room_id'])) {
			$roomId = (int) $_POST['room_id'];
		}
		elseif (isset ($par[0])) {
			$roomId = (int) $par[0];
		}
		else {
			$p = & moon :: page();
			$p->page404();
		}
		$this->set_var('roomId', $roomId);
		switch ($event) {

			case 'edit':
				$id = isset ($par[1]) ? intval($par[1]):0;
				if ($id) {
					if (count($values = $this->getItem($id))) {
						$this->form->fill($values);
					}
					else {
						$this->set_var('error', '404');
					}
				}
				$this->set_var('view', 'form');
				break;

			case 'save':
				if ($id = $this->saveItem()) {
					$this->redirect('#', $roomId);
				}
				else {
					$this->set_var('view', 'form');
				}
				break;

			case 'delete':
				if (isset ($_POST['it']))
					$this->deleteItem($_POST['it']);
				$this->redirect('#', $roomId);
				break;

			case 'sort':
				if (isset ($_POST['rows'])) {
					$this->updateSortOrder($_POST['rows']);
				}
				$this->redirect('#', $roomId);
				break;

			default:
		}
		$this->use_page('Common');
	}

	function properties() {
		return array('view' => 'list');
	}

	function main($vars) {
		$p = & moon :: page();
		$t = & $this->load_template();
		$info = $t->parse_array('info');
		$win = & moon :: shared('admin');
		$win->active($this->my('fullname'));
		$roomId = $vars['roomId'];
		$roomName = $this->getRoomName($roomId);
		$submenu = $win->subMenu(array('*id*' => $roomId));
		$a = array();
		//******* FORM **********
		$err = (isset ($vars['error'])) ? $vars['error']:0;
		$f = $this->form;
		$title = $f->get('id') ? $info['titleEdit']:$info['titleNew'];
		$m = array('error' => $err ? $info['error' . $err]:'', 'event' => $this->my('fullname') . '#save', 'refresh' => $p->refresh_field(), 'id' => ($id = $f->get('id')), 'goBack' => $this->linkas('#', $roomId), 'pageTitle' => $win->current_info('title'), 'formTitle' => htmlspecialchars($title), 'roomName' => htmlspecialchars($roomName), 'room_id' => $roomId, 'goToRooms' => $this->linkas('reviews#'), 'submenu' => $submenu, 'hide' => $f->checked('hide', 1)) + $f->html_values();
		$m['class-hide'] = $err || $id ? '':' hide';
		$m['now'] = date('Y-m-d');
		$m['cancel'] = $id || $err;
		if ($m['master_id']) {
			$m['master_room'] = isset ($rooms[$m['room_id']]) ? $rooms[$m['room_id']]['name']:'unknown';
			$m['master_active'] = ($m['active_from'] ? 'from ' . $m['active_from']:'') . ($m['active_to'] ? ' till ' . $m['active_to']:'');
			$m['class-sync'] = (int) $m['updated'] > (int) $m['master_updated'] ? ' sync1':' sync2';
			$a = (int) $m['updated'] < 0 ? 0:$this->getMasterInfo($m['master_id']);
			if (!empty ($a)) {
				$txt = moon :: shared('text');
				$m['master_title'] = nl2br(htmlspecialchars($a['title']));
				$m['master_url'] = nl2br(htmlspecialchars($a['url']));
				if ($a['prev_title']) {
					$m['master_title'] = $txt->htmlDiff($a['prev_title'], $a['title']);
				}
				if ($a['prev_url']) {
					$m['master_url'] = $txt->htmlDiff($a['prev_url'], $a['url']);
				}
			}
		}
		$res = $t->parse('viewForm', $m);
		//******* LIST **********
		$p->js('/js/tablednd_0_5.js');
		$m = array('items' => '');
		$dat = $this->getList($roomId);
		$goEdit = $this->linkas('#edit', $roomId . '.{id}');
		$t->save_parsed('items', array('goEdit' => $goEdit));
		$loc = & moon :: locale();
		$now = $loc->now();
		$today = $loc->to_days(gmdate('Y-m-d'));
		foreach ($dat as $d) {
			if (!$d['active'] || $d['hide']) {
				$d['class'] = 'item-hidden';
			}
			$d['styleTD'] = '';
			if (!empty ($d['master_id'])) {
				//sync ikona
				$sType = (int) $d['master_updated'] < (int) $d['updated'] ? 1:2;
				$d['styleTD'] = '' . $sType . '';
			}
			if ($id == $d['id']) {
				$d['styleTD'] .= '" style="background-color:#F0F8FF"';
			}
			if ($d['styleTD']) {
				$d['styleTD'] = ' class="sync' . $d['styleTD'] . '"';
			}
			$d['created'] = $d['created'] ? $loc->datef($d['created'], 'Date'):'&nbsp;';
			$d['timer'] = '';
			if ($d['active_from'] && $d['active_from'] !== '0000-00-00') {
				if ($loc->to_days($d['active_from']) > $today) {
					$d['active_from'] = '<b style="color:red">' . $d['active_from'] . '</b>';
				}
				$d['timer'] .= ' from ' . $d['active_from'];
			}
			if ($d['active_to'] && $d['active_to'] !== '0000-00-00') {
				$d['timer'] .= ' till ' . $d['active_to'];
				if ($loc->to_days($d['active_to']) < $today) {
					$d['expired'] = 1;
				}
			}
			$d['title'] = htmlspecialchars($d['title']);
			$d['url'] = htmlspecialchars($d['url']);
			$m['items'] .= $t->parse('items', $d);
		}
		$title = $win->current_info('title');
		$m['title'] = htmlspecialchars($title);
		$m['room_id'] = $roomId;
		$m['goDelete'] = $this->my('fullname') . '#delete';
		$m['goSort'] = $this->my('fullname') . '#sort';
		$res .= $t->parse('viewList', $m);
		$p->title($title);
		return $res;
	}

	//***************************************
	//           --- DB AND OTHER ---
	//***************************************
	function getList($roomId) {
		$sql = 'SELECT *,
			IF(hide=0 AND (ISNULL(active_to) || CURDATE()<= active_to),1,0) as active
		FROM ' . $this->myTable . '
		WHERE room_id=' . intval($roomId) . '
		ORDER BY active DESC, ord_no';
		return $this->db->array_query_assoc($sql);
	}

	function getItem($id) {
		return $this->db->single_query_assoc('
		SELECT * FROM ' . $this->myTable . ' WHERE
			id = ' . intval($id));
	}

	function saveItem() {
		$form = & $this->form;
		$form->fill($_POST);
		$d = $form->get_values();
		$id = intval($d['id']);
		$masterID = $d['master_id'];
		$roomID = intval($d['room_id']);
		if ($masterID && $id) {
			//gautu duomenu apdorojimas
			$d['hide'] = empty ($d['hide']) ? 0:1;
			$d = $form->get_values('title', 'url', 'hide') + $this->getItem($id);
			//validacija
			if ($d['title'] === '') {
				$form->fill($d, false);
				$this->set_var('error', 1);
				return false;
			}
			//save to database
			$ins = $form->get_values('title', 'url', 'hide');
			$ins['updated'] = time();
			$this->db->update($ins, $this->myTable, array('id' => $id));
			blame($this->my('fullname'), 'Updated', $id);
			//update master table
			$this->db->query('UPDATE ' . $this->table('PromotionsMaster') . ' SET prev_title = title, prev_url=url  WHERE id=' . intval($masterID));
			return $id;
		}
		//gautu duomenu apdorojimas
		$d['hide'] = empty ($d['hide']) ? 0:1;
		$d['active_from'] = $this->makeTime($d['active_from']);
		$d['active_to'] = $this->makeTime($d['active_to']);
		//jei bus klaida
		$form->fill($d, false);
		//validacija
		$err = 0;
		if ($d['title'] === '') {
			$err = 1;
		}
		if ($err) {
			$d['active_from'] = $_POST['active_from'];
			$d['active_to'] = $_POST['active_to'];
			$form->fill($d, false);
			$this->set_var('error', $err);
			return false;
		}
		//jei refresh, nesivarginam
		if ($wasRefresh = $form->was_refresh()) {
			return $id;
		}
		//save to database
		$ins = $form->get_values('title', 'url', 'active_from', 'active_to', 'room_id', 'hide');
		$ins['updated'] = time();
		if ($id) {
			$this->db->update($ins, $this->myTable, array('id' => $id));
			blame($this->my('fullname'), 'Updated', $id);
		}
		else {
			$ins['created'] = $ins['updated'];
			$ins['room_id'] = $roomID;
			$id = $this->db->insert($ins, $this->myTable, 'id');
			// log this action
			blame($this->my('fullname'), 'Created', $id);
		}
		return $id;
	}

	function makeTime($d) {
		if ($d) {
			if (count($a = explode('-', $d)) != 3 || !checkdate($a[1], $a[2], $a[0])) {
				return NULL;
			}
			return $d;
		}
		return NULL;
	}

	function deleteItem($ids) {
		if (!is_array($ids) || !count($ids))
			return;
		foreach ($ids as $k => $v)
			$ids[$k] = intval($v);
		$this->db->query('DELETE FROM ' . $this->myTable . ' WHERE id IN (' . implode(',', $ids) . ')');
		blame($this->my('fullname'), 'Deleted', $ids);
		return true;
	}

	function saveOrder($rows) {
		$rows = explode(';', $rows);
		$order = array();
		$when = '';
		$i = 1;
		$ids = array();
		foreach ($rows as $id) {
			$key = intval(substr($id, 3));
			if (!$key)
				continue;
			$ids[] = $key;
			$when .= 'WHEN id = ' . $key . ' THEN ' . $i++. ' ';
		}
		if (count($ids)) {
			$sql = 'UPDATE ' . $this->myTable . '
			SET ord_no =
				CASE
				' . $when . '
				END
			WHERE id IN (' . implode(', ', $ids) . ')';
			$this->db->query($sql);
			blame($this->my('fullname'), 'Updated', 'Changed order');
		}
	}

	function updateSortOrder($rows) {
		$rows = explode(';', $rows);
		$order = array();
		$when = '';
		$i = 1;
		$ids = array();
		foreach ($rows as $id) {
			$key = intval(substr($id, 3));
			if (!$key) {
				continue;
			}
			$ids[] = $key;
			$when .= 'WHEN id = ' . $key . ' THEN ' . $i++. ' ';
		}
		if (count($ids)) {
			$sql = 'UPDATE ' . $this->myTable . '
				SET ord_no =
					CASE
					' . $when . '
					END
				WHERE id IN (' . implode(', ', $ids) . ')';
			$this->db->query($sql);
			blame($this->my('fullname'), 'Updated', 'Changed order');
		}
	}

	function getRoomName($id) {
		$sql = 'SELECT `name`
			FROM ' . $this->table('Rooms') . '
			WHERE id = ' . $id;
		$result = $this->db->single_query_assoc($sql);
		if (!empty ($result)) {
			return $result['name'];
		}
		else {
			return '';
		}
	}

	function getMasterInfo($id) {
		return $this->db->single_query_assoc('
			SELECT * FROM ' . $this->table('PromotionsMaster') . ' WHERE
			id = ' . intval($id));
	}

}

?>