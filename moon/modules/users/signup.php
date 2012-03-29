<?php

class signup extends moon_com {

	function events($event, $par) {
		$this->forget();
		switch ($event) {

			case 'logout':
				if (is_object($oLogin = $this->object('login_object'))) {
					$oLogin->vbLogout();
				}
				moon :: page()->back(true);
				break;

			case 'login':
				$u = & moon :: user();
				if (!$u->get_user_id()) {
					if (isset ($_POST['nick']) && isset ($_POST['password'])) {
						if (is_object($oLogin = $this->object('login_object'))) {
							$oLogin->vbLogin($_POST['nick'], $_POST['password'], $err);
							if ($err) {
								$this->set_var('error', $err);
								$this->use_page('Signup');
								break;
							}
						}
					}
					else {
						// anonimas, rodom login page
						$this->use_page('Signup');
						return;
					}
				}
				moon :: page()->back(true);
				break;

			default:
				moon :: page()->page404();
		}
	}

	function main($vars) {
		$user = & moon :: user();
		if ($user->get_user_id()) {
			$this->redirect('profile#');
			return 'Ok :)';
		}
		else {
			return $this->viewLogin($vars);
		}
	}

	function viewLogin($vars) {
		$t = & $this->load_template();
		$info = $t->parse_array('info');
		$p = & moon :: page();
		$p->title($info['title']);
		$p->set_local('nobanners', TRUE);
		$m = array();
		$err = empty ($vars['error']) ? 0:(int) $vars['error'];
		if ($err) {
			$m['error'] = isset ($info['err' . $err]) ? $info['err' . $err]:'Error: ' . $err;
		}
		$fL = & $this->form('nick', 'password');
		$fL->fill($_POST);
		$m['eventLogin'] = $this->my('fullname') . '#login';
		$m += $fL->html_values();
		$m['url.forgot'] = '/forums/login.php?do=lostpw';
		return $t->parse('viewLogin', $m);
	}
	//***************************************
	//           --- DB AND OTHER ---
	//***************************************

}

?>