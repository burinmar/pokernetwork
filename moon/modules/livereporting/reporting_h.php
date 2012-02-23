<?php
class reporting_h extends moon_com {
	function events($ev, $par) {
		if (array_key_exists('p', $_POST) && is_string($_POST['p']) && '' !== $_POST['p']) {
			$p=&moon::page();
			$p->forget();
			require_once($GLOBALS['CMS_PATH'] . 'moon/classes/hands/1.php');
			moon_close(); exit;
		} else if (array_key_exists('h', $_GET) && is_numeric($_GET['h'])) {
			$p=&moon::page();
			$p->forget();
			require_once($GLOBALS['CMS_PATH'] . 'moon/classes/hands/h.php');
			moon_close(); exit;
		} else if (array_key_exists('l', $_GET) && is_numeric($_GET['l'])) {
			$p=&moon::page();
			$p->forget();
			require_once($GLOBALS['CMS_PATH'] . 'moon/classes/hands/l.php');
			moon_close(); exit;
		}
		if (array_key_exists('i', $_GET)) {
			$this->forget();
			$_GET['i'] = intval($_GET['i']);
			$l = $this->db->array_query('SELECT id,game_no FROM ' . $this->table('Hands') . ' WHERE parent_id = ' . $_GET['i'] . ' ORDER BY id ASC');
			$s = ''; 
			foreach ($l as $k => $v) $s .= $v[0]  . '	' . $v[1] . "\n";
			header('Content-type:text/plain;charset=UTF-8');
			echo substr($s, 0, -1);
			moon_close(); die();
		}
		$this->use_page('');
		$u = &moon::user();
		if (!empty($_POST) && array_key_exists('n', $_POST) && array_key_exists('w', $_POST)) {
			$o = &$this->object('sys.login_object');
			if ($o->login($_POST['n'], $_POST['w'], $err)) {
				if (FALSE === $u->i_admin('content')) $e = 'User ' . $u->get('nick') . ' do not have permission to access this page';
				else $f = FALSE;
			} else $e = 'Incorrect username or password.';
		} elseif ((int) $u->get_user_id()) {
			if (FALSE === $u->i_admin('content')) $e = 'User ' . $u->get('nick') . ' do not have permission to access this page';
			else $f = FALSE;
		} else $e = '';
		if  (!isset($f)) { ?>
<html><body><form method="post">
<?php if ('' !== $e) {echo '<div style="color:red">' . $e . '</div>';}?>
Name: <input type="password" name="n"> Password: <input type="password" name="w"> <input type="submit" value="Login">
</form></body></html>
<?php
				exit;
		}
		if (array_key_exists('d', $_GET)) {
			$u = &moon::user();
			if (FALSE === $u->i_admin('content')) exit;
			if (array_key_exists('c', $_GET)) {
				foreach ($_GET['c'] as $v) {
					$v = intval($v);
					$r = $this->db->array_query('SELECT id FROM hands WHERE parent_id = ' . $v);
					$h = '';
					if (!empty($r)) {
						foreach ($r as $d) $h .= $d[0] . ',';
						$h = substr($h, 0, -1);
					} else {
						$r = $this->db->query('DELETE FROM hands_list WHERE lid IN (' . $v . ')');
					}
					$this->db->query('DELETE l,h,a,i,r,p FROM hands_list l, hands h, hands_actions a, hands_game_info i, hands_l2h r, hands_players p WHERE l.lid IN (' . $v . ') AND l.lid = h.parent_id AND h.id = a.hand_id AND h.id = i.hand_id AND h.id = r.hid AND h.id = p.hand_id');
					$this->db->query('DELETE FROM hands_odds WHERE hand_id IN (' . $h . ')');
				}
			}
			if (array_key_exists('s', $_GET)) {
				$h = '';
				$g = array();
				foreach ($_GET['s'] as $v) {
					$a = explode('.', $v);
					if (array_key_exists($a[0], $g)) ++$g[$a[0]];
					else $g[$a[0]] = 1;
					$h .= $a[1] . ',';
				}
				$h = substr($h, 0, -1);
				$this->db->query('DELETE h,a,i,r,p FROM hands h, hands_actions a, hands_game_info i, hands_l2h r, hands_players p WHERE h.id IN (' . $h . ') AND h.id = a.hand_id AND h.id = i.hand_id AND h.id = r.hid AND h.id = p.hand_id');
				$this->db->query('DELETE FROM hands_odds WHERE hand_id IN (' . $h . ')');
				foreach ($g as $id => $v) {
					$d = $this->db->single_query('SELECT id FROM hands WHERE parent_id = ' . $id . ' ORDER BY game_no LIMIT 1');
					if (empty($d)) continue;
					$this->db->query('UPDATE hands_list SET firsthid = ' . $d[0] . ', hands_cnt = hands_cnt - ' . $v . ' WHERE lid = ' . $id);
				}
			}
		}
echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml"  xml:lang="en" lang="en" dir="ltr">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<title>Select hands log</title>
<link rel="stylesheet" href="/css/main.css" type="text/css" />
<link rel="stylesheet" href="/css/modal.css" type="text/css" />
<script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.3.2/jquery.min.js"></script>
<body class="modal"><div class="modalWindowContent">' . $this->main(NULL) . '</div>
</body>
</html>';
moon_close();
exit;
	}

	function main($vars) {
		$err = '';
		if (array_key_exists('t', $_GET)) {
			$hl = TRUE;
			$l = 't=' . @$_GET['t'];
		} elseif (array_key_exists('e', $_GET) && is_numeric($_GET['e'])) {
			$r = $this->db->single_query('SELECT h.log,l.tId,h.parent_id,l.created,l.title FROM ' . $this->table('Hands') . ' h, ' . $this->table('hLs') . ' l WHERE h.id = ' . $_GET['e'] . ' AND h.parent_id=l.lid');
			if (empty($r)) $err = 'Hand not found.';
			else {
				$hl = TRUE;
				$h = array(''); $j = 0; $s = $r[0];
				for ($i = 0; $i < strlen($s); $i++) {
					$c = $s[$i];
					if ("\n" === $c) break;
					if ("\r" === $c) break;
					if ("\t" === $c) {
						++$j;
						$h[$j] = '';
						continue;
					}
					$h[$j] .= $c;
				}
				if (2 === $j) $s = $h[1] . '	' . $h[2] . '	' . $r[3] . '	' . $r[4] . "\t" . substr($s, $i);
				else if (3 === $j) $s = $h[1] . '	' . $h[2] . '	' . $r[3] . '	' . $r[4] . '	' . $h[3] . substr($s, $i);
				else  if (4 === $j) $s = $h[1] . '	' . $h[2] . '	' . $h[3] . '	' . $h[4] . "\t" . substr($s, $i);
				else $s = $h[1] . '	' . $h[2] . '	' . $h[3] . '	' . $h[4] . '	' . $h[5] . substr($s, $i);
				//echo $s;exit;
				$l = 'e=' . $_GET['e'] . '&l=' . urlencode($s) . '&t=' . $r[1] . '&p=' . $r[2];
				$_GET['t'] = $r[1];
			}
		}
		if (isset($hl)) {
$tId = @$_GET['t'];
if (!is_numeric($tId)) {
	if ('' !== $err) $err .= ' ';
	$err .= 'Tournament id not set. Please set tournament id';
	$t = &$this->load_template();
	$err = $t->parse('err', array('t' => $err));
	$title = '';
} else {
	$r = $this->db->single_query('SELECT name FROM ' . $this->table('Tournaments') . ' WHERE id=' . $tId);
	if (empty($r)) {
		if ('' !== $err) $err .= ' ';
		$err .= 'Tournament not found.';
		$t = &$this->load_template();
		$err = $t->parse('err', array('t' => $err));
		$title = '';
	} else $title = $r[0];
}
$m = @filemtime($GLOBALS['CMS_PATH']  . 'img/h.swf');
echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml"  xml:lang="en" lang="en" dir="ltr">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
<title>Hands log</title>
<link rel="stylesheet" href="/css/main.css" type="text/css"/>
<link rel="stylesheet" href="/css/modal.css" type="text/css"/>
</head>
<body>
' . $title . '<br/>' . $err . '
<a href="/h/?a=' . $tId . '">Hands List</a><br/>
<div style="margin:10px auto;width:918px"><object classid="clsid:d27cdb6e-ae6d-11cf-96b8-444553540000" codebase="http://download.macromedia.com/pub/shockwave/cabs/flash/swflash.cab#version=9,0,0,0" width="918" height="710" id="h" align="middle">
<param name="allowScriptAccess" value="sameDomain"/>
<param name="allowFullScreen" value="false"/>
<param name="movie" value="/h/h.swf?' . $m . '"/><param name="quality" value="high"/><param name="bgcolor" value="#ffffff"/>
<embed src="/img/h.swf?' . $m . '" quality="high" bgcolor="#ffffff" width="918" height="710" name="h" align="middle" allowScriptAccess="sameDomain" allowFullScreen="false" flashvars="' . $l . '" type="application/x-shockwave-flash" pluginspage="http://www.macromedia.com/go/getflashplayer"/>
</object></div>
<script type="text/javascript">
var w,h;
if(document.documentElement && (!document.compatMode || document.compatMode=="CSS1Compat")){w=top.document.documentElement.clientWidth;h=top.document.documentElement.clientHeight;}
else if(document.compatMode && document.body && document.body.clientWidth){w=top.document.body.clientWidth;h=top.document.body.clientHeight;}
else if(window.innerWidth){w=window.innerWidth;h=window.innerHeight;}
if(1020>w && 900>h)window.resizeTo(1020,900);else if(1020>w)window.resizeTo(1020,h);else if(900>h)window.resizeTo(w,900);
</script>
</body>
</html>';
moon_close();
exit;
		}
		$t = &$this->load_template();
		$a = array('items' => '', 'title' => '');
		$tId = @$_GET['a'];
		if (!is_numeric($tId)) {
			if ('' !== $err) $err .= ' ';
			$err .= 'Tournament id not set. Please set tournament id';
		} else {
			$l = $this->db->single_query('SELECT name FROM ' . $this->table('Tournaments') . ' WHERE id=' . $tId);
			if (empty($l)) {
				if ('' !== $err) $err .= ' ';
				$err .= 'Tournament not found.';
			} else $a['title'] = $l[0];
		}
		$a['tI'] = $tId;
		$l = $this->db->single_query('SELECT COUNT(*) FROM ' . $this->table('hLs') . ' WHERE tId=' . $tId);
		if (!empty($l) && (int) $l[0] > 0) {
			$pn = &moon::shared('paginate');
			$pn->set_curent_all_limit(array_key_exists('p', $_GET) ? $_GET['p'] : 1, $l[0], 20);
			$pn->set_url('/' . $this->my('module') . '-reporting_h/?p={pg}&a=' . $tId);
			$i = $pn->get_info();
			$l = $this->db->array_query('SELECT lid,hands_cnt,title,created FROM ' . $this->table('hLs') . ' WHERE tId=' . $tId . ' ORDER BY created DESC, lid DESC ' . $i['sqllimit']);
			$loc = &moon::locale();
			$c = 0;
			foreach ($l as $k => $v) {
				++$c;
				$a['items'] .= $t->parse('i1', array(
					'lid' => $v[0],
					'lc' => '*' . $v[0],
					'title' => htmlspecialchars($v[2]),
					'c' => $v[1],
					'o' => 0 === $c%2 ? 'even' : 'odd',
					'created' => $loc->datef($v[3], 'DateTime')
				));
			}
			$a['pg'] = $pn->show_nav();
			$a['s'] = _SITE_ID_;
			$p = &moon::page();
			if ('beta' === substr($p->home_url(), 7, 4)) $a['dev'] = ',bt=true';
			else $a['dev'] = is_dev() ? ',dev=true' : '';
			$a['!action'] = '/livereporting-reporting_h/';
			$a['e'] = '' !== $err ? $t->parse('err', array('t' => $err)) : '';
			return $t->parse('ls', $a);
		}
		return $t->parse('empty', array('e' => isset($err) ? $t->parse('err', array('t' => $err)) : '', 'title' => $a['title'], 'tI' => $a['tI']));
	}
}