<?php


/* Password reminder */
class forgot extends moon_com {


	function onload() {
		$this->fRemind = & $this->form('email');
		$this->fRemind->fill();
	}


	function events($event, $par) {
		$user = & moon :: user();
		($id = $user->get_user_id()) || ($id = $user->get_user('tmpID'));
		if ($id) {
			// useris jau isilogines, komponento rodyti nebereikia
			$page = & moon :: page();
			$page->back(TRUE);
		}
		switch ($event) {

			case 'save-password' :
				$this->changePassword();
				break;

			case 'remind' :
				$this->sendMail();
				break;

			default :
		}
		$this->use_page('Signup');
		$this->forget();
	}


	function properties() {
		return array('action' => '', 'pwd' => '');
	}


	function main($vars) {
		//$page = & moon :: page();
		//$page->css('/css/profile.css');
		if (!empty ($_GET['pwd'])) {
			if ($this->pwdOK($_GET['pwd'])) {
				$vars['pwd'] = $_GET['pwd'];
				$vars['action'] = 'change';
			}
			else {
				$vars['error'] = 8;
			}
		}
		switch ($vars['action']) {

			case 'change' :
				$res = $this->viewPassword($vars);
				break;

			default :
				$res = $this->viewReminder($vars);
		}
		return $res;
	}


	function viewReminder($vars) {
		$tpl = & $this->load_template();
		$info = $tpl->parse_array('info');
		$page = & moon :: page();
		$page->title($info['title']);
		$page->meta('robots', 'noindex,follow');
		// form
		$m = array();
		$m['refresh'] = $page->refresh_field();
		$m['event'] = $this->my('fullname') . '#remind';
		$m += $this->fRemind->html_values();
		$err = empty ($vars['error']) ? 0 : (int) $vars['error'];
		if ($err) {
			$m['error'] = isset ($info['err' . $err]) ? $info['err' . $err] : 'Error: ' . $err;
		}
		if (!empty ($vars['ok'])) {
			$m['mailSent'] = 1;
		}
		return $tpl->parse('viewReminder', $m);
	}


	function viewPassword($vars) {
		$tpl = & $this->load_template();
		$info = $tpl->parse_array('info');
		$page = & moon :: page();
		$page->title($info['title_password']);
		$page->meta('robots', 'noindex,follow');
		// form
		$m = array();
		$m['refresh'] = $page->refresh_field();
		$m['event'] = $this->my('fullname') . '#save-password';
		$m['pwd'] = isset ($vars['pwd']) ? htmlspecialchars($vars['pwd']) : '';
		$err = empty ($vars['error']) ? 0 : (int) $vars['error'];
		if ($err) {
			$m['error'] = isset ($info['err' . $err]) ? $info['err' . $err] : 'Error: ' . $err;
		}
		if (!empty ($vars['ok'])) {
			$m['saved'] = 1;
		}
		return $tpl->parse('viewPassword', $m);
	}


	//***************************************
	//           --- DB and other---
	//***************************************
	/* siuncia laiska su password keitimo puslapio adresu */
	function sendMail() {
		$form = & $this->fRemind;
		$form->fill($_POST);
		$email = $form->get('email');
		if (!$form->was_refresh()) {
			$err = 0;
			$mail = & moon :: mail();
			$findBy = $mail->is_email($email) ? 'email' : 'nick';
			if ($email == '') {
				$err = 3;
			}
			else {
				// Randam userio email
				$oLogin = $this->object('login_object');
				$d = array($findBy=>$email);
				$oLogin->_send_request('findUser', $d , $err);

				if (empty ($d['email']) || $err) {
					$err = $findBy == 'nick' ? 9 : 1;
				}
			}
			if ($err) {
				$this->set_var('error', $err);
				return false;
			}
			$id = $d['id'];
			//if ($d['email'] == 'audrius@vpu.lt') $d['email'] = 'audrius.naslenas@ntsg.lt';
			$key = $id . 'a' . $this->generateCRC($id);
			$m = array();
			$m['nick'] = $d['nick'];
			$page = & moon :: page();
			$m['url.change'] = $page->home_url() . ltrim($this->url('#', '', array('pwd' => $key)), '/');
			$tpl = & $this->load_template();
			$info = $tpl->parse_array('info');
			$msg = wordwrap($tpl->parse('mail', $m));
			$mail->charset('utf-8');
			$mail->to($d['email']);
			$mail->from($info['mailFrom']);
			$mail->subject($info['mailSubject']);
			$mail->body($msg);
			if (!$mail->send()) {
				moon :: error('Neveikia mail ('.$d['email'].')');
				$err = 2;
				$this->set_var('error', $err);
			}
			else {
				$this->set_var('ok', 1);
			}
		}
	}


	/* patikrina, ar pwd string yra teisingas */
	function pwdOK($key) {
		if (strpos($key, 'a')) {
			list($id, $crc) = explode('a', $key, 2);
			$crcReal = $this->generateCRC($id);
			if ($crc === $crcReal) {
				// teisingas
				return TRUE;
			}
		}
		return FALSE;
	}


	/* sugeneruoja pwd kodo kontrolini stringa */
	function generateCRC($id) {
		$oLogin = $this->object('login_object');
		$a = $oLogin->vCard($id);
		/*$a = $this->db->single_query('
			SELECT password FROM ' . $this->table('Users') . ' WHERE id=' . intval($id));*/
		$code = empty ($a['password']) ? '6g9r0' : $a['password'];
		return substr(sha1('g]jg2' . $id . $code), - 15);
		//dabar padedam koda, jeigu noretu vartotojas pakeisti password
		//$code = rand(10000, 99999);if (strlen($code) != 5) {$code = '46812';}
		//$code = '5g7r0';
		//$this->db->update(array('tmp' => $code), $this->table('Users'), $id);
	}


	function changePassword() {
		$this->set_var('action', 'change');
		$f = & $this->form('pass1', 'pass2', 'pwd');
		$f->fill($_POST);
		$d = $f->get_values();
		$err = 0;
		if (empty ($d['pass1']) && empty ($d['pass2'])) {
			$err = 4;
		}
		elseif ($d['pass1'] != $d['pass2']) {
			$err = 5;
		}
		elseif (empty ($d['pwd']) || !$this->pwdOK($d['pwd'])) {
			$err = 8;
		}
		$this->set_var('pwd', $d['pwd']);
		if ($err) {
			$this->set_var('error', $err);
			return false;
		}

		/* jei refresh, nesivarginam */
		if ($wasRefresh = $f->was_refresh()) {
			return;
		}
		//keiciam
		list($id) = explode('a', $d['pwd'], 2);
		$oLogin = $this->object('login_object');
		$a = array( 'password' => md5($d['pass1']));
		$oLogin->update($id,$a, $err);
		if ($err) {
			$this->set_var('error', 7);
			return false;
		}
		$this->set_var('ok', 1);
		//isiloginam
		//$login_obj = $this->object("login_object");
		//$uInfo = $login_obj->db_login_info($id, $err);
		//if ($vars['uid'] && is_array($uInfo)) {
		//	$u = & moon :: user();
		//	$u->login($uInfo);
		//}
	}


}

?>