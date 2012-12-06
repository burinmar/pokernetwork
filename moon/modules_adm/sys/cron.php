<?php
class cron extends moon_com {


	function onload() {
		$this->formEdit = & $this->form();
		$this->formEdit->names('id', 'event', 'priority', 'comment', 'schedule', 'disabled', 'last_run', 'in_background');

		$this->myTable = $this->table('CronTasks');
	}


	function events($event, $par) {
		switch ($event) {

			case 'edit' :
				$id = isset ($par[0]) ? intval($par[0]) : 0;
				if ($id) {
					if (count($values = $this->getItem($id))) {
						$this->formEdit->fill($values);
					}
					else {
						$this->set_var('error', '404');
					}
				}
				$this->set_var('view', 'form');
				break;

			case 'save' :
				if ($id = $this->saveItem()) {
					if (isset ($_POST['return'])) {
						$this->redirect('#edit', $id);
					}
					else {
						$this->redirect('#');
					}
				}
				else {
					$this->set_var('view', 'form');
				}
				break;

			case 'delete' :
				if (isset ($_POST['it'])) {
					$this->deleteItem($_POST['it']);
				}
				$this->redirect('#');
				break;

			case 'run' :
				$id = isset ($par[0]) ? intval($par[0]) : 0;
				$this->run($id);
				$this->redirect('#');
				break;

			case 'background' :
				$user = & moon :: user();
				$user->set('admin', '*');
				$id = intval($_SERVER['argv'][1]);
				if ($id) {
					$this->run($id, $background = true);
				}
				moon_close();
				exit;
				break;

			case 'jobs' :
				$user = & moon :: user();
				$user->set('admin', '*');
				$this->start_jobs();
                // toliau nebeateinama
				break;

			case 'test' :
				//testas, ar cron veikia
				$page = & moon :: page();
				$page->set_local('cron', 'Test was successful!');
				return;
				break;

			default :
				if (isset ($_GET['ord'])) {
					$this->set_var('sort', (int) $_GET['ord']);
					$this->set_var('psl', 1);
					$this->forget();
				}
				if (isset ($_GET['page'])) {
					$this->set_var('psl', (int) $_GET['page']);
				}
		}
		$this->use_page('Common');
	}


	function properties() {
		return array('psl' => 1, 'sort' => '', 'view' => 'list');
	}


	function main($vars) {
		$p = & moon :: page();
		$locale = & moon :: locale();
		$t = & $this->load_template();
		$win = & moon :: shared('admin');
		$win->active($this->my('fullname'));
		$info = $t->explode_ini('info');
		$err = isset ($vars['error']) ? $vars['error'] : 0;
		$submenu = $win->subMenu();
		$now = $locale->now();
		$status = $t->parse_array('status');
		if ($vars['view'] == 'form') {

			//******* FORMA **********
			$f = $this->formEdit;
			$title = $f->get('id') ? $info['titleEdit'] . ' :: ' . $f->get('event') : $info['titleNew'];
			$m = array(
				'error' => $err ? $info['error' . $err] : '',
				'_event_' => $this->my('fullname') . '#save',
				'refresh' => $p->refresh_field(),
				'id' => ($id = $f->get('id')),
				'goBack' => $this->linkas('#'),
				'pageTitle' => $win->current_info('title'),
				'formTitle' => htmlspecialchars($title),
				'submenu' => $submenu,
				'disabled' => $f->checked('disabled', 1),
				'in_background' => $f->checked('in_background', 1),
				) + $f->html_values();
			$m['server_time'] = date('Y-m-d H:i:s O');
			$res = $t->parse('viewForm', $m);

			$save = array('psl' => $vars['psl'], 'sort' => $vars['sort']);
			$this->save_vars($save);
		}
		else {

			//******* SARASAS **********
			$m = array('items' => '');
			$pn = & moon :: shared('paginate');

			// rusiavimui
			$ord = & $pn->ordering();
			$ord->set_values(
				//laukai, ir ju defaultine kryptis
				array('priority' => 1, 'last_run' => 0, 'next_run' => 1, 'event' => 1),
				//antras parametras kuris lauko numeris defaultinis.
				1
				);
			//gauna linkus orderby{nr}
			$m += $ord->get_links($this->linkas('#', '', array('ord' => '{pg}')), $vars['sort']);

			//generuojam sarasa
			if ($count = $this->getListCount()) {
				//puslapiavimui
				if (!isset ($vars['psl'])) {
					$vars['psl'] = 1;
				}
				$pn->set_curent_all_limit($vars['psl'], $count, 30);
				$pn->set_url($this->linkas('#', '', array('page' => '{pg}')));
				$m['puslapiai'] = $pn->show_nav();
				$psl = $pn->get_info();

				$dat = $this->getList($psl['sqllimit'], $ord->sql_order());
				$goEdit = $this->linkas('#edit', '{id}');
				$goRun = $this->linkas('#run', '{id}');
				$goLog = $this->linkas('cronlog#filter', '', array('task' => ''));
				$t->save_parsed('items', array('goEdit' => $goEdit, 'goRun' => $goRun, 'goLog' => $goLog));
				foreach ($dat as $d) {
					$d['class'] = $d['disabled'] ? 'item-hidden' : '';
					$d['event'] = htmlspecialchars($d['event']);
					$d['comment'] = htmlspecialchars($d['comment']);
					$d['schedule'] = htmlspecialchars($d['schedule']);
					if ($d['next_run'] == - 10) {
						$d['next_run'] = $status['running'] . ' <br>' . $this->duration($now - $d['last_run']);
					}
					elseif ($d['disabled']) {
						$d['next_run'] = $status['disabled'];
					}
					elseif ($d['next_run'] == - 1) {
						$d['next_run'] = $status['never'];
					}
					elseif ($d['next_run'] <= $now) {
						$d['next_run'] = $status['instantly'];
					}
					else {
						$d['next_run'] = date('Y-m-d H:i', $d['next_run']) . '<br />(' . $this->duration($d['next_run'] - $now, TRUE) . ')';
					}
					$d['last_run'] = $d['last_run'] ? date('Y-m-d H:i', $d['last_run']) : $status['unknown'];
					$m['items'] .= $t->parse('items', $d);
				}
			}
			$m['submenu'] = $submenu;
			$m['server_time'] = date('Y-m-d H:i:s');
			$m['server_time_ms'] = (time() + date('Z')) * 1000;
			$m['server_gmt'] = date('O');
			$m['goNew'] = $this->linkas('#edit');
			$m['goDelete'] = $this->my('fullname') . '#delete';
			$title = $win->current_info('title');
			$m['title'] = htmlspecialchars($title);
			$res = $t->parse('viewList', $m);

			$save = array('psl' => $vars['psl'], 'sort' => (int) $vars['sort']);
			$this->save_vars($save);
		}
		//*****************************
		$p->title($title);
		return $res;
	}


	//***************************************
	//           --- DB AND OTHER ---
	//***************************************
	function saveItem() {
		$form = & $this->formEdit;
		$form->fill($_POST);
		$d = $form->get_values();
		$id = intval($d['id']);
		//gautu duomenu apdorojimas
		$d['disabled'] = $d['disabled'] ? 1 : 0;
		$d['in_background'] = $d['in_background'] ? 1 : 0;
		$d['next_run'] = $this->next_run(time(), $d['schedule']);
		$form->fill($d, false);
		//jei bus klaida
		//validacija
		$err = 0;
		if ($d['event'] === '') {
			$err = 1;
		}
		if ($err) {
			$this->set_var('error', $err);
			return false;
		}
		//jei refresh, nesivarginam
		if ($wasRefresh = $form->was_refresh()) {
			return $id;
		}
		//save to database
		$ins = $form->get_values('event', 'comment', 'schedule', 'disabled', 'next_run', 'priority', 'in_background');
		$db = & $this->db();
		if ($id) {
			$db->update_query($ins, $this->myTable, array('id' => $id));
			// log this action
			blame($this->my('fullname'), 'Updated', $id);
		}
		else {
			$id = $db->insert_query($ins, $this->myTable, 'id');
			blame($this->my('fullname'), 'Created', $id);
		}
		$form->fill(array('id' => $id));
		return $id;
	}


	function getListCount() {
		$sql = 'SELECT count(*) FROM ' . $this->myTable . $this->_where();
		$m = $this->db->single_query($sql);
		return (count($m) ? $m[0] : 0);
	}


	function getList($limit = '', $order = '') {
		//if ($order) $order=' ORDER BY '.$order;
		if (substr($order, 0, 8) == 'next_run') {
			$order = ' ORDER BY disabled,never,' . $order;
		}
		else {
			$order = ' ORDER BY disabled,' . $order;
		}
		$sql = 'SELECT *, IF(next_run<0,1,0) as never FROM ' . $this->myTable . $this->_where() . $order . $limit;
		return $this->db->array_query_assoc($sql);
	}


	function _where() {
		return '';
	}


	function getItem($id) {
		return $this->db->single_query_assoc('
			SELECT * FROM ' . $this->myTable . ' WHERE id = ' . intval($id));
	}


	function deleteItem($ids) {
		if (!is_array($ids) || !count($ids)) {
			return;
		}
		foreach ($ids as $k => $v) {
			$ids[$k] = intval($v);
		}
		$this->db->query('DELETE FROM ' . $this->myTable . ' WHERE id IN (' . implode(',', $ids) . ')');
		blame($this->my('fullname'), 'Deleted', $ids);
		return true;
	}


	//****************** Other ************************
	function start_jobs() {
		$now = time();
		$unlockAfter = (int) $this->get_var('cronUnlockAfter');
		$m = $this->db->array_query_assoc('
			SELECT * FROM ' . $this->myTable . '
			WHERE  disabled=0 AND (
				(next_run>0 AND next_run<' . $now . ')' . ($unlockAfter ? ' OR (next_run=-10 AND last_run<' . ($now - $unlockAfter * 60) . ')' : '') . ')
				ORDER BY `priority` ');
		foreach ($m as $v) {
			$this->run($v['id']);
		}
		moon_close();
		exit;
	}


	function run($id, $inBackground = false) {
		$db = & $this->db();
		$m = $db->single_query_assoc('SELECT * FROM ' . $this->myTable . ' WHERE id=' . intval($id));
		if (count($m)) {
			if ($m['next_run'] == - 10) {
				$p = & moon :: page();
				//kiek min praejo
				$elapsed = floor((time() - (int) $m['last_run']) / 60);
				//pakibes taskas, galim ignoruot
				if (($ignore = $this->get_var('cronUnlockAfter')) && $ignore < $elapsed) {
					$ins = array('task_id' => $m['id'], 'start_time' => $m['last_run'], 'end_time' => time(), 'message' => 'The task was running too long! The system is not waiting response from it anymore...');
					$db->insert_query($ins, $this->table('CronLog'));
					$p->alert('Task is running too long! Ignoring...', 'n');
				}
				else {
					$p->alert('Task is already running!', 'n');
					return;
				}
			}
			if (!$inBackground && $m['in_background']) {
				$this->init_background_process($id);
				return;
			}
			$wh = array('id' => $m['id']);
			$start = time();
			$a = array('last_run' => $start, 'next_run' => - 10);
			$db->update_query($a, $this->myTable, $wh);
			//echo 'Running '.$m['event'];
			$p = & moon :: page();
			if ($p->call_event($m['event'])) {
				$r = $p->get_local('cron');
				$p->set_local('cron', '');
			}
			else {
				$r = "Error";
			}
			$end = time();
			$ins = array('task_id' => $m['id'], 'start_time' => $start, 'end_time' => $end, 'message' => $r);
			if (abs($end - $start) > 15) {
				$db->ping();
			}
			if ($r != 'nolog') {
				$db->insert_query($ins, $this->table('CronLog'));
			}
			$a = array('next_run' => $this->next_run($end, $m['schedule']));
			$db->update_query($a, $this->myTable, $wh);
			if ($inBackground) {
				moon_close();
				exit;
			}
			//psl rodyti nebereikia
		}
	}


	function queue($event) {
		$db = & $this->db();
		$m = $db->single_query_assoc('
		SELECT id,last_run,next_run,disabled FROM ' . $this->myTable . "
		WHERE event='" . $db->escape($event) . "'");
		$start = $now = time();
		if (count($m)) {
			if ($m['next_run'] == - 10) {
				//taskas dabar vykdomas
				$msg = 'Queue: the running task was found!.. Overwritten...';
				//jei vyksta, duosim dar 10 min
				$start += 600;
			}
			elseif ($m['next_run'] > 0 && $m['next_run'] < $now) {
				//taskas jau uzstatytas
				$msg = 'Queue: the task already is queued...';
				$start = 0;
			}
			else {
				$msg = 'Queue: task updated';
			}
			if ($start) {
				$a = array('next_run' => $start);
				$db->update_query($a, $this->myTable, $id = $m['id']);
			}
		}
		else {
			//naujas taskas
			$ins = array();
			$ins['event'] = $event;
			$ins['comment'] = 'Queued task.';
			$ins['next_run'] = $start;
			$id = $db->insert_query($ins, $this->myTable, 'id');
			$msg = 'Queue: task created';
		}
		$ins = array('task_id' => $id, 'start_time' => $now, 'end_time' => $now, 'message' => $msg);
		$db->insert_query($ins, $this->table('CronLog'));
	}


	function init_background_process($id) {
		$ini = & moon :: moon_ini();
		if ($ini->has('mikehup', 'command')) {
			$cmd = $ini->get('mikehup', 'command');
		}
		else {
			$cmd = '';
		}
		if ($cmd == '') {
			$this->run($id, true);
			return;
		}
		$cmd .= ' cron.php ' . $id . ' &';
		//$cmd=$cmd.' '.$id.'  &' ;
		//$cmd='/usr/local/bin/mikehup /usr/local/bin/php xml.php '.$id.' '.$skipSite.' &';
		//$cmd='/usr/local/bin/php xml.php '.$id.' '.$skipSite;
		//$cmd='/usr/local/bin/php -v';
		//$cmd='start /B /D D:\SVN\my.pokernews D:\programs\mikehup.exe D:\programs\php\php.exe cron.php '.$id;
		$p = & moon :: page();
		$p->alert('Started in background!', 'n');
		$ok = system($cmd, $err);
		//$p->alert('Out '.$ok,'ok');
		if ($err) {
			$p->alert('system() error ' . $err);
		}
		sleep(2);
		//kad spetu bazej pasizymeti
	}


	function next_run($lastRun, $timeLine) {
		//$s='d[12]h[22,12]m[32];h[11];w[2,3,4]on[14:00]';
		if ($timeLine === '*') {
			return $lastRun;
		}
		elseif ($timeLine === '') {
			return - 1;
		}
		$tskArray = array();
		$d = explode(';', $timeLine);
		$min = - 1;
		$lastRun += date('Z');
		$lastRunTime = $lastRun % 86400;
		$lastRunDay = $lastRun - $lastRunTime;
		foreach ($d as $v) {
			$v = trim($v);
			if ($v === '') {
				continue;
			}
			elseif ($v === '*') {
				$was = true;
			}
			// jei * reiskia visais atvejais run
			else {
				preg_match_all('/([d|w|h|m]{1}|on)\[([^\]]*)\]/', $v, $p);
				$run = array('w' => '*', 'd' => '*', 'h' => '*', 'm' => '*', 'on' => '');
				foreach ($p[1] as $i => $j) {
					$run[$j] = $p[2][$i];
				}
				$days = $this->_find_day($lastRunDay, $run['d'], $run['w']);
				$time = $this->_find_time($run['h'], $run['m'], $run['on']);
				$i = 0;
				foreach ($days as $d) {
					if ($time === '*') {
						$ts = $d > $lastRunDay ? $d : $lastRun;
						//echo gmdate('Y-m-d H:i',$ts).'<br>';
						$min = $min === - 1 ? $ts : min($min, $ts);
						break;
					}
					else {
						foreach ($time as $t) {
							if (($ts = ($d + $t * 60)) > $lastRun) {
								//echo gmdate('Y-m-d H:i',$ts).'<br>';
								$min = $min === - 1 ? $ts : min($min, $ts);
								break (2);
							}
						}
					}
				}
			}
		}
		if ($min > 10000) {
			$min -= date('Z');
		}
		return $min;
	}


	function _find_day($nuo, $d, $w) {
		$aW = $aD = array();
		$aD = $this->_find_interval($d, 'd');
		$aW = $this->_find_interval($w, 'w');
		if (count($aD) + count($aW) == 0) {
			return array($nuo, $nuo + 86400);
		}
		$found = array();
		for ($i = $nuo; $i < $nuo + 86400 * 365; $i += 86400) {
			$iD = gmdate('j', $i);
			$iW = gmdate('w', $i);
			if (!$iW) {
				$iW = 7;
			}
			if (count($aD) && !in_array($iD, $aD)) {
				continue;
			}
			if (count($aW) && !in_array($iW, $aW)) {
				continue;
			}
			$found[] = $i;
			if (count($found) > 3) {
				break;
			}
		}
		//foreach ($found as $i) echo gmdate('Y-m-d',$i).'<br>';
		sort($found);
		return $found;
	}


	function _find_time($h, $m, $on) {
		$found = array();
		//Pirmiausia on
		$d = explode(',', $on);
		foreach ($d as $v)
			if (strpos($v, ':')) {
				list($i, $j) = explode(':', $v);
				$i = intval($i);
				$j = intval($j);
				if ($i >= 0 && $i < 24 && $j >= 0 && $j < 60) {
					$found[] = $i * 60 + $j;
				}
			}
			if (!count($found)) {
				$aH = $aM = array();
				$aH = $this->_find_interval($h, 'h');
				$aM = $this->_find_interval($m, 'm');
				if (count($aH) + count($aM) == 0) {
					return '*';
				}
				$found = array();
				for ($i = 0; $i < 1440; $i += 1) {
					$h = floor($i / 60);
					$m = $i % 60;
					if (count($aH) && !in_array($h, $aH)) {
						continue;
					}
					if (count($aM) && !in_array($m, $aM)) {
						continue;
					}
					$found[] = $i;
				}
			}
			//foreach ($found as $i) echo floor($i/60).":".($i%60).'<br>';
			sort($found);
		return $found;
	}


	function _find_interval($s, $t) {
		if (trim($s) === '*') {
			return array();
		}
		$a = array();
		$b = explode(',', $s);
		switch ($t) {

			case 'd' :
				$min = 1;
				$max = 31;
				break;

			case 'w' :
				$min = 1;
				$max = 7;
				break;

			case 'h' :
				$min = 0;
				$max = 23;
				break;

			default :
				$min = 0;
				$max = 59;
		}
		foreach ($b as $v) {
			$v = trim($v);
			if ($pos = strpos($v, '-')) {
				list($n1, $n2) = explode('-', $v);
				$n1 = max(intval($n1), $min);
				$n2 = min(intval($n2), $max);
				for ($i = $n1; $i <= $n2; $i++) {
					$a[] = $i;
				}
			}
			else {
				$n = intval($v);
				if ($n <= $max && $n >= $min) {
					$a[] = $n;
				}
			}
		}
		$a = array_unique($a);
		sort($a);
		return $a;
	}


	function duration($s, $short = FALSE) {
		//trukme
		$r = '';
		$sec = $short && $s > 3600 ? FALSE : TRUE;
		if ($s > 3600) {
			$r .= floor($s / 3600) . 'h ';
			$s = $short ? floor(($s % 3600) / 60) * 60 : $s % 3600;
		}
		if ($s > 60) {
			$r .= floor($s / 60) . 'min ';
			$s = $s % 60;
		}
		if ($sec) {
			$r .= $s . 's';
		}
		return trim($r);
	}


}

?>