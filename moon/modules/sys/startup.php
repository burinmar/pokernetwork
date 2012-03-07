<?php


class startup extends moon_com {


	function events($event, $par) {
		//transporterio iejimas
		if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset ($_COOKIE['voter2']) && $_COOKIE['voter2'] === 'Transporter-Pokernews') {
			$_COOKIE['voter2'] = '';
			include_class('transporter');
			$t = new transporter();
			$t->set_key('vemail');
			echo $ans = $t->answer('transporter_events', $this);
			//print_r($t->trans_info());
			moon_close();
			exit;
		}
		//end transporter
		switch ($event) {

			case 'error404' :
				//header("HTTP/1.0 404 Not Found", TRUE, 404);
				$this->use_page('page404');
				return;
				break;

			default :
				$this->use_page('Default');
		}
        // mobile version detect
		$page=&moon::page();
        $hstep=$page->history_step();


		$u = & moon :: user();

		if ($u->id() == 0) {
			include_class('moon_vb_relay');
			if (null != ($userInfo = moon_vb_relay::getInstance()->loggedIn())) {
				$loginObj = $this->object('login_object');
				$loginObj->loginUnconditional($userInfo);
				// redirect?
			}
		}
		// vb update

        //autologinam ir patikrinam teises
		/*if (is_object($loginObj = $this->object('login_object'))) {
			if ($u->get_user_id()) {
				$locale = & moon :: locale();
				$now = $locale->now();
				//patikrinam permissionai ar nepasikeite
				$p = & moon :: page();
				$hstep = $p->history_step();
				$was = (int) $u->get_user('last_seen');
				if ($p->pause_lasted() > 60 || ($hstep % 10 == 4)) {
					$loginObj->refresh();
					if ($loginObj->i_banned()) {
						$u->logout();
					}
				}
				elseif ($was < ($now - 60)) {
					$loginObj->online($u->get_user_id());
				}
			}
			else {
				//patikrinam autologina
				if (isset ($_COOKIE['cglg'])) {
					$loginObj->autologin($_COOKIE['cglg']);
				}
			}
		}*/

		//patikrinam, ar tai roomso urlas
		if (!empty($GLOBALS['review.roomID']) && is_object($obj = $this->object('reviews.review'))) {
			$obj->parse_request();
			if ($obj->id() && !$page->requested_event('POST')) {
				$e = & moon :: engine();
				$e->disable_event();
			}
		}
		//Gal atejo adminView ijungimas/isjungimas
		/*if (isset ($_GET['adminView'])) {
			$page->set_global('adminView', ($_GET['adminView'] === "enable" ? 1 : 0));
		}*/
		if (is_dev()) {
			$engine = & moon :: engine();
			$engine->debugOn = TRUE;
		}
		//$page->css('/css/main.css');
		$page->js('/js/jquery.js');
		$page->js('/js/banners.js');
		$page->js('/js/pokernetwork.js');

	}


	function transporter_events($par) {
		$e = & moon :: engine();
		$e->call_event($par['event'], $par['vars']);
		$p = & moon :: page();
		$r = $p->get_local('transporter');
		return $r;
	}


}
?>