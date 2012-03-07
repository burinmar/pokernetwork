<?php


class signup extends moon_com {


	function onload() {
		$this->form = & $this->form('nick', 'email', 'password', 'password2', 'subscribe', 'toc', 'code');
		$this->form->fill();
	}


	function events($event, $par) {
		$p = & moon :: page();
		$this->forget();
		if ($p->requested_event('REST') == 'js.php' || $event == 'js') {
			$this->viewJavaScript();
		}
		if (isset ($_GET['key'])) {
			//aktyvacija pagal nuoroda
			$a = explode('n', $_GET['key'], 2);
			if (count($a) == 2 && $a[0]) {
				$this->activate($a[0], $a[1]);
				$_POST['code'] = $a[1];
			}
		}
		switch ($event) {
			case 'logout' :
				$u = & moon :: user();
				if (is_object($loginObj = $this->object('login_object'))) {
					$loginObj->logout();
				}
				$p = & moon :: page();
				$p->back(true);
				break;

			case 'login' :
				$u = & moon :: user();
				if (!($userID = $u->get_user_id())) {
					$userID = (int)$u->get_user('tmpID');
				}

                if (!$userID) {
					if (isset ($_POST['nick']) && isset ($_POST['password'])) {
						if (is_object($loginObj = $this->object('login_object'))) {
							$loginObj->login($_POST['nick'], $_POST['password'], $err);
							if ($err) {
								$this->set_var('error', 100 + $err);
								$this->use_page('Signup');
								$this->set_var('view', 'login');
								break;
							}
						}
					}
					else {
						// anonimas, rodom login page
						$this->use_page('Signup');
						$this->set_var('view', 'login');
						return;
					}
				}
				if ($u->get_user('tmpID')) {
					$this->use_page('Signup');
				}
				else {
					$p = & moon :: page();
					$p->back(true);
				}
				break;

			case 'create' :
			   	$this->createAccount();
				$this->use_page('Signup');
				break;

			case 'resend' :
				$u = & moon :: user();

				/*if ($u->get_user_id()) {
				$this->use_page('Profile');
				return;
				}*/
				$tmpID = $u->get_user('tmpID');
				$this->send_activation_mail($tmpID, $u->get_user('nick'), $u->get_user('email'));
				$page = & moon :: page();
				$this->redirect('#');
				break;

			case 'activate' :
				$this->use_page('Signup');
				$u = & moon :: user();
				$tmpID = $u->get_user('tmpID');
				$ok = $this->activate($tmpID, $_POST['code']);
				if ($ok) {
					$this->redirect('profile#');
					//$this->use_page('Profile');
				}
				break;

			case 'change-email' :
				$this->use_page('Signup');
				$this->change_email();
				break;

			default :
				$this->forget();
				$u = & moon :: user();
				if ($u->get_user_id()) {
					$p = & moon :: page();
					$p->back(true);
				}
				else {
					$this->use_page('Signup');
				}
				break;
		}
	}

	function properties() {
		return array('view'=>'');
	}


	function main($vars) {
		$user = & moon :: user();
		if ($user->get_user_id()) {
			$this->redirect('profile#');
			return 'Ok :)';
		}
		elseif ($user->get_user('tmpID')) {
			$res = $this->viewActivation($vars);
		}
		else {
			$res = $vars['view'] === 'login' ? $this->viewLogin($vars) : $this->viewSignup($vars);
		}
		return $res;
	}


	function viewSignup($vars) {
		$t = & $this->load_template();
		$info = $t->parse_array('info');
		$p = & moon :: page();
		$p->title($info['title']);
		$p->set_local('header.hidesignIn', TRUE);
		$p->set_local('nobanners', TRUE);
		$m = array();
		$err = empty ($vars['error']) ? 0 : (int) $vars['error'];
		if ($err) {
			$errMsg = isset ($info['err' . $err]) ? $info['err' . $err] : 'Error: ' . $err;
			if ($err < 100) {
				$m['error2'] = $errMsg;
			}
		}

		//signup forma
		$m['eventCreate'] = $this->my('fullname') . '#create';
		$m['code'] = '';
		$f = $this->form;
		$m += $f->html_values();
		$m['subscribe'] = $f->checked('subscribe', 1);
		$m['toc'] = $f->checked('toc', 1);

		$m['srcCaptcha'] = 'captcha.php?t=' . time();
		$res = $t->parse('viewSignup', $m);
		return $res;
	}

	function viewLogin($vars) {
		$t = & $this->load_template();
		$info = $t->parse_array('info');
		$p = & moon :: page();
		$p->title($info['titleLogin']);
		$p->set_local('header.hidesignIn', TRUE);
		$p->set_local('nobanners', TRUE);
		$m = array();
		$err = empty ($vars['error']) ? 0 : (int) $vars['error'];
		if ($err) {
			$errMsg = isset ($info['err' . $err]) ? $info['err' . $err] : 'Error: ' . $err;
			$m['error'] = $errMsg;
		}
		$fL = & $this->form('nick', 'password', 'remember');
		$fL->fill($_POST);
		$m['eventLogin'] = $this->my('fullname') . '#login';
		$m += $fL->html_values();
		$m['remember'] = $fL->checked('remember', 1);
		$m['url.forgot'] = $this->linkas('forgot#');
		$res = $t->parse('viewLogin', $m);
		return $res;
	}


	function viewActivation($vars) {
		$t = & $this->load_template();
		$info = $t->parse_array('info');
		$p = & moon :: page();
		$p->title($info['title']);
		$m = array();
		$m['url.resend'] = $this->linkas('#resend');
		$u = & moon :: user();
		$m['email'] = $u->get_user('email');
		$m['code'] = '';
		$m['eventChangeEmail'] = $this->my('fullname') . '#change-email';
		$m['eventActivate'] = $this->my('fullname') . '#activate';
		$err = empty ($vars['error']) ? 0 : (int) $vars['error'];
		if ($err) {
			$info = $t->parse_array('info');
			$errMsg = isset ($info['err' . $err]) ? $info['err' . $err] : 'Error: ' . $err;
			if ($err < 100) {
				$m['error2'] = $errMsg;
				if (isset ($_POST['email'])) {
					$m['email'] = htmlspecialchars($_POST['email']);
				}
			}
			else {
				$m['error1'] = $errMsg;
				if (isset ($_POST['code'])) {
					$m['code'] = htmlspecialchars($_POST['code']);
				}
			}
		}
		return $t->parse('viewActivation', $m);
	}


	function viewJavaScript() {
		header('Content-type: text/javascript; charset=UTF-8');
		/*header('Expires: ' .   gmdate('r', time()+3600) , TRUE);
		header('Cache-Control: max-age=' . 3600 , TRUE);
		header('Pragma: public', TRUE);*/
		$t = & $this->load_template();
		$info = $t->parse_array('info');
		$m = array();

		//signup forma
		$m['eventCreate'] = $this->my('fullname') . '#create';
		$m['code'] = '';
		$f = $this->form;
		$m += $f->html_values();
		$m['subscribe'] = $f->checked('subscribe', 1);
		$m['toc'] = $f->checked('toc', 1);
		$fSignup = $t->parse('jsSignup', $m);

		//dabar login forma
		$n = array();
		$fL = & $this->form('nick', 'password', 'remember');
		$fL->fill();
		$n['eventLogin'] = $this->my('fullname') . '#login';
		$n += $fL->html_values();
		$n['remember'] = $fL->checked('remember', 1);
		$n['url.forgot'] = $this->linkas('forgot#');
		$fLogin = $t->parse('jsLogin', $n);
		readfile(dirname($this->my('file')) . '/signup.js');
		$m = array();
		$m['fLogin'] = $t->ready_js($fLogin);
		$m['fSignup'] = $t->ready_js($fSignup);
		$m['!action'] = $this->linkas('#');
		$m['jsErrors'] = array();
		foreach ($info as $k => $v) {
			if (substr($k, 0, 3) == 'err' && intval(substr($k, 3)) < 100) {
				$m['jsErrors'][] = "'$k' : \"" . $t->ready_js($v) . '"';
			}
		}
		$m['jsErrors'] = implode(",\n", $m['jsErrors']);
		$m['srcCaptcha'] = 'captcha.php?t=' . time();
		echo $t->parse('javascript', $m);
		moon_close();
		exit;
	}


	//***************************************
	//           --- DB AND OTHER ---
	//***************************************
	function createAccount() {
		$form = & $this->form;
		$form->fill($_POST);
		$d = $form->get_values();
		//gautu duomenu apdorojimas
		$d['toc'] = empty ($d['toc']) ? 0 : 1;
		$d['subscribe'] = empty ($d['subscribe']) ? 0 : 1;
		//jei bus klaida
		$form->fill($d, false);
		//validacija
		$err = 0;
		$reserved = array('pokernews', 'casinogrinder', 'manager', 'admin', 'moderator', 'support');
		//nebutina itraukti trumpesniu nei 4
		$isReserved = in_array(strtolower($d['nick']), $reserved);
		$reserved = array('pokernews', 'manager', 'admin', 'moderator');
		//ieskoti bet kurioje vietoje
		if (!$isReserved) {
			$lnick = strtolower($d['nick']);
			foreach ($reserved as $word) {
				if (strpos($lnick, $word) !== false) {
					$isReserved = true;
					break;
				}
			}
		}
		if (!($ilg = strlen($d['nick']))) {
			//nenurodytas nickas
			$err = 1;
		}
		elseif ($ilg < 4 || $ilg > 15 || preg_match('/[^a-z0-9_.-]/i', $d['nick'], $rMas)) {
			// nevalidus
			$err = 2;
		}
		elseif ($isReserved) {
			// negalimas (rezervuotas)
			$err = 7;
		}
		elseif (strlen($d['email'])>50 || $d['email'] === '') {
			$err = 3;
		}
		elseif ($d['password'] === '') {
			$err = 4;
		}
		elseif ($d['password'] !== $d['password2']) {
			$err = 5;
		}
		elseif (!$d['toc']) {
			$err = 6;
		}
		else {
			$p = & moon :: page();
			if (!($c = $p->get_global('captcha')) || $c != trim($d['code'])) {
				$err = 10;
			}
			$p->set_global('captcha', '');
		}
		if (!$err) {
			//ar geras emailas
			$mail = & moon :: mail();
			if (!$mail->is_email($d['email'])) {
				$err = 3;
			}
		}
		/*if (!$err) {
			if (is_object($oLogin = $this->object('login_object')) && $oLogin->i_banned()) {
				$err = 9;
			}
		}*/
		if ($err) {
			$this->set_var('error', $err);
			return false;
		}
		//jei refresh, nesivarginam
		if ($wasRefresh = $form->was_refresh()) {
			return false;
		}
		//save to database
		$ins = $form->get_values('nick', 'email', 'password', 'subscribe');
		$locale = & moon :: locale();
		//$ins['langs'] = $locale->language();
		$ins['password'] = md5($ins['password']);
		$oLogin = &$this->object('login_object');
		$id = (int) $oLogin->insert($ins, $err);
		//$mydb = & moon :: db('mypokernews');
		//$id = (int) $mydb->insert($ins, $err);
		if ($err < 0 || ($err == 0 && $id == 0)) {
			$err = 13;
		}
		if ($err) {
			$this->set_var('error', $err);
			return false;
		}
		$uInfo = $oLogin->login_info($id, $err);
		if (is_array($uInfo)) {
			$u = & moon :: user();
			$u->login($uInfo);
		}
		$this->send_activation_mail($id, $ins['nick'], $ins['email']);
		if ($form->get('subscribe')) {
			// nori prenumeruoti
			$this->db->update(array('subscribe'=>1), $this->table('Users'), $id);
		}
		return $id;
	}


	function send_activation_mail($uID, $nick, $email) {
		//if ($email === 'audriusn@one.lt') $email = 'audrius.naslenas@ntsg.lt';
		$t = & $this->load_template();
		$info = $t->parse_array('mailActivationHeaders');
		$tmp = array();
		$p = & moon :: page();
		$homeUrl = $p->home_url();
		$code = $this->generate_code($uID);
		$tmp['url_activate'] = $homeUrl . ltrim($this->linkas('#'), '/') . '?key=' . $uID . 'n' . $code;
		$tmp['code'] = $code;
		$tmp['nick'] = $nick;
		$tmp['url_home'] = $homeUrl;
		$tmp['url_password'] = $homeUrl . ltrim($this->linkas('forgot#change'), '/');
		$msg = $t->parse('mailActivation', $tmp);
		$msg = wordwrap($msg);
		//siuntimas
		$mail = & moon :: mail();
		if (!$mail->is_email($email)) {
			return false;
		}
		$mail->charset('utf-8');
		$mail->to($email);
		$mail->from($info['mail_from']);
		$mail->subject($info['mail_subject']);
		$mail->body($msg);
		$ok = $mail->send();
		if (!$ok) {
			moon :: error('Neveikia mail ('.$email.')');
		}
		return $ok;
	}


	function generate_code($id) {
		return abs(sprintf('%u', crc32("idis" . $id . "h[j,/u:a'c32")));
	}


	function activate($id, $code) {
		$err = 106;
		$id = intval($id);
		if ($this->generate_code($id) == trim($code)) {
			//valio galim aktyvuoti
			//$mydb = & moon :: db('mypokernews');
			$oLogin = &$this->object('login_object');
			$r = $oLogin->update($id, array('status' => ''), $err);
			if ($err) {
				// technine klaida
				$err = 105;
			}
			else {
				$this->db->query('UPDATE ' . $this->table('Users') . " SET status='' WHERE id=$id AND status='N' ");
				$u = & moon :: user();
				$u->set_user_id($id);
				$u->set_user('tmpID', '');

				/* Gal buvo pazymejes subscribe */
				$is = $this->db->single_query('
					SELECT subscribe, email
					FROM ' . $this->table('Users') . "
					WHERE id=$id
					");
				if (!empty($is[0])) {
					//vemail
					$email = $is[1];
					/*if (!empty($email)) {
						include_class('icontact');
						$db = new icontact;
						$db->subscribe($email);
					}*/
				}

				//siunciam privacia zinute
				/*if (!empty($r['activate'])) {
					$this->send_first_pm($id);
				}*/
			}
			return TRUE;
		}
		$this->set_var('error', $err);
		return FALSE;
	}


	function change_email() {
		$u = & moon :: user();
		$id = (int) $u->get_user('tmpID');
		if (!$id || !isset ($_POST['email'])) {
			return;
		}
		$newEmail = trim($_POST['email']);
		$oldEmail = $u->get_user('email');
		if ($oldEmail === $newEmail) {
			return;
		}
		$err = 0;
		$mail = & moon :: mail();
		if ($newEmail === '' || !$mail->is_email($newEmail)) {
			$err = 3;
		}
		if (!$err) {
			$oLogin = &$this->object('login_object');
			$oLogin->update($id, array('email' => $newEmail), $err);
			//$mydb = & moon :: db('mypokernews');
			//$mydb->update($id, array('email' => $newEmail), $err);
			if ($err) {

				/* $err:
				2 - bad email
				3 - this email is in use
				-1 technical error
				*/
				switch ($err) {

					case 2 :
						$err = 3;
						break;

					case 3 :
						$err = 8;
						break;

					default :
						$err = 13;
				}
			}
		}
		if ($err) {
			$this->set_var('error', $err);
			return;
		}
		$u->set_user('email', $newEmail);
		$email = $this->db->escape($newEmail);
		$this->db->query('UPDATE ' . $this->table('Users') . " SET email='" . $email . "' WHERE id=$id AND status='N' ");
		// issiunciam aktyvacijos linka
		$u = & moon :: user();
		$tmpID = $u->get_user('tmpID');
		$this->send_activation_mail($tmpID, $u->get_user('nick'), $u->get_user('email'));

		/*$this->db->query('UPDATE '.$this->table('Subscribers')." SET email='".$email."' WHERE user_id=$id");*/
	}

	function send_first_pm($to) {
    	// randam chief-editor
		if (_SITE_ID_ === 'nl') {
			$admin = array(1769);
		}
		else {
			$admin = $this->db->single_query("
				SELECT a.`user_id`, u.`nick`
				FROM " . $this->table('UsersAccess') . " a, " . $this->table('Users') . " u
				WHERE a.user_id=u.id AND a.`module`='chief-editor' AND u.`status`='A' AND NOT(user_id IN (
					SELECT `user_id` FROM " . $this->table('UsersAccess') . " WHERE  `module`='@developer' OR `module`='@administrator'
				))
				LIMIT 1
                ");
		}
		if (!empty($admin[0])) {
			// siunciam
			$m = array();
			$from = $admin[0];
			$tpl = $this->load_template('signup_pm', 'locale/users_signup_pm.txt');
			$subject = $tpl->parse('subject');
			$a = array();
			$page = & moon :: page();
			$a['http'] = rtrim($page->home_url(), '/');
			$body = $tpl->parse('body', $a);
            $send = array($from, $to, $subject, $body);
			if (strlen($body)>200) {
                if (!callPnEvent('adm', 'messages.pm', $send, $answer) || empty($answer)) {
					moon :: error('Can not send first pm!');
				}
			}
		}
	}


}

?>