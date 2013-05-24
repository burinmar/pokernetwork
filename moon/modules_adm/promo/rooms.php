<?php
class rooms extends moon_com {
	var $form, $view;

	function events($ev, $par) {
		$p = moon::page();
		$t = &$this->load_template();
		$info = $t->parse_array('info');
		$this->use_page('Common', '');
		if ($ev == 'add' || $ev == 'edit' || $ev == 'save') {
			$this->item = '';
			$this->form = &$this->form();
			$this->form->names('id', 'name', 'url');
			$this->form->fill();
		}
		switch($ev) {
		case 'add':
		$this->view = 'form';
		return;
		case 'edit':
		$this->view = 'form';
		if (isset($par[0])) {
			$this->item = $this->item($par[0]);
			if ($this->item) $this->form->fill($this->item);
			else {
				header('HTTP/1.0 404 Not Found');
				$p->alert($info['404']);
			}
		}
		return;
		case 'save':
			$this->view = 'form';
			$this->form->fill($_POST);
			$d = $this->form->get_values();
			$err = 0;
			$i = array();
			$d['id']=(int)$d['id'];
			if ($d['id']) {
				$i = $this->item = $this->item($d['id']);
				$this->set_var('item', $i);
				if (empty($i)) {
					header('HTTP/1.0 404 Not Found');
					$p->alert($info['404']);
					return;
				}
			}
			if ($d['name'] == '') {
				$p->alert($info['e1']);
				return;
			}
			if (!empty($i)) {
				$id = $i['id'];
				blame($this->my('fullname'), 'Updated', $id);
				$result = $this->db->update($d, $this->table('CustomRooms'), array('id' => $id));
				$p->alert($info['updated'], 'ok');
			} else {
				$this->db->insert_query($d, $this->table('CustomRooms'));
				$id = $this->db->insert_id();
				blame($this->my('fullname'), 'Created', $id);
				$p->alert($info['added'], 'ok');
			}
			if (count($_FILES)) {
				$d = array();
				$del = array_key_exists('delF', $_POST) && $_POST['delF'] ? TRUE : FALSE;
				$fName = $this->saveLogo($id, 'favicon', $err, $del);
				if ($fName !== FALSE && !$err) {
					$i['favicon'] = $d['favicon'] = $fName;
					$this->db->update_query($d, $this->table('CustomRooms'), array('id' => $id));
				}
				if ($err) $p->alert($info['ferr' . $err]);
			}
			if (array_key_exists('return', $_POST)) return $this->events('edit', array($id));
			else $this->view = '';
			return;
		case 'del':
			$a = ((isset($_POST['it']) && is_array($_POST['it'])) ? $_POST['it'] : array());
			$r = 0;
			foreach ($a as $id) {
				if (is_numeric($id)) {
					$d = $this->db->array_query('SELECT e.title FROM ' . $this->table('CustomRooms') . ' c, ' . $this->table('Leagues') . ' l, ' . $this->table('Events') . ' e WHERE e.room_id = -c.id AND e.promo_id = l.id AND c.id = ' . $id);
					if (count($d)) {
						$i = $this->db->single_query('SELECT name FROM ' . $this->table('CustomRooms') . ' WHERE id = ' . $id);
						$e = array();
						foreach ($d as $v) $e[] = $v[0];
						$p->alert('Can not delete ' . $i[0] . '. Room is assigned to events: ' . implode(', ', $e));
						continue;
					}
					$fName = $this->saveLogo($id, 'favicon', $err, TRUE);
					$this->db->query('DELETE FROM ' . $this->table('CustomRooms') . ' WHERE id = ' . $id);
					blame($this->my('fullname'), 'Deleted', $id);
					$r += $this->db->affected_rows();
				}
			}
			if ($r) $p->alert($info['deleted'], 'ok');
		}
	}

	function main($vars) {
		$win = moon::shared('admin');
		$win->active($this->my('fullname'));
		$t = &$this->load_template();
		if ('form' == $this->view) {
			$info = $t->parse_array('info');
			$a['submenu'] = $win->subMenu();
			$a['event'] = $this->my('fullname') . '#save';
			$a['goBack'] = $this->linkas('#');
			$a['fTitle'] = $info['new'];
			$d = $this->form->html_values();
			$a['id'] = $d['id'];
			$a['name'] = $d['name'];
			$a['url'] = $d['url'];		
			$p = moon::page();
			$p->title($info['new']);
			if ($this->item) {
				$title = htmlspecialchars($this->item['name']);
				$a['fTitle'] = $info['edit'] . ': ' . $title;
				$p->title('Custom room: ' . $title);
				$dir = $this->get_dir('srcCustomRooms');
				if ($this->item['favicon']) $a['fSrc'] = $dir . $this->item['favicon'];
			}
			return $t->parse('form', $a);
		}
		$r = $this->db->single_query('SELECT COUNT(*) FROM ' . $this->table('CustomRooms'));
		$c = empty($r[0]) ? 0 : $r[0];
		$a = array();
		$win = moon::shared('admin');
		$a['submenu'] = $win->subMenu();
		$a['items'] = '';
		$a['goAdd'] = $this->linkas('#add');
		if ($c) {
			$l = $this->db->array_query_assoc('SELECT * FROM ' . $this->table('CustomRooms') . ' ORDER BY name');
			$a['event'] = $this->my('fullname') . '#del';
			$dir = $this->get_dir('srcCustomRooms');
			foreach ($l as $v) {
				$i['id'] = $v['id'];
				$i['title'] = htmlspecialchars($v['name']);
				$i['url'] = htmlspecialchars($v['url']);
				if ($v['favicon']) $i['fSrc'] = $dir . $v['favicon']; else $i['fSrc'] = '';
				$i['goEdit'] = $this->linkas('#edit', $v['id']);
				$a['items'] .= $t->parse('items', $i);
			}
		}
		return $t->parse('ls', $a);
	}

	function item($id) {
		return $this->db->single_query_assoc('SELECT * FROM ' . $this->table('CustomRooms') . ' WHERE id = ' . $id);
	}

	function saveLogo($id, $name, &$err, $del = false) {
		$err = 0;
		$f = new moon_file;
		$isUpload = $f->is_upload($name, $e);
		if (!$isUpload && !$del) return FALSE;
		$dir = $this->get_dir('CustomRooms');
		$is = $this->db->single_query_assoc('SELECT ' . $name . ' FROM ' . $this->table('CustomRooms') . ' WHERE id = ' . $id);
		if ($isUpload && !$f->has_extension('jpg,jpeg,gif,png')) {
			$err = 1; //neleistinas pletinys
			return FALSE;
		}
		$newPhoto=$curPhoto=isset($is[$name]) ? $is[$name] : '';
		//ar reikia sena trinti?
		if (($isUpload || $del) && $curPhoto) {
			$fDel = new moon_file;
			if ($fDel->is_file($dir . $curPhoto)) {
				$fDel->delete();
				$newPhoto = NULL;
			}
		}
		if ($isUpload) { //isaugom faila
			$nameSave = $dir . $id . '.' . $f->file_ext();
			if ($f->save_as($nameSave)) $newPhoto = basename($f->file_path());
			else $err = 3; //technine klaida
		}
		if ($newPhoto === '') $newPhoto = NULL;
		return $newPhoto;
	}
}

?>