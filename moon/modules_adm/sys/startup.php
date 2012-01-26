<?php
//administravimo dalies startupas
class startup extends moon_com {

	function events($event, $par) {

		switch ($event) {

			case 'error404' :
				//$p=&moon::page();
				//$p->info('w','Neteisinga uzklausa '.implode('.',$par));
				return;
				break;

			default :
				$this->use_page('Default');
				$p = & moon :: page();
				$u = & moon :: user();
				if (is_object($loginObj = $this->object('login_object'))) {
					if ($u->get_user_id()) {
						//patikrinam permissionai ar nepasikeite
						$hstep = $p->history_step();
						if (!$u->i_admin() || $p->pause_lasted() > 180 || ($hstep % 5 == 4)) {
							$loginObj->refresh();
						}
					}
					else {
						//patikrinam autologina
						if (isset($_POST['cglg']) && isset($_POST['swfupload'])) {
							$_COOKIE["cglg"] = $_POST['cglg'];
						}
						if (isset ($_COOKIE['cglg'])) {
							$loginObj->autologin($_COOKIE['cglg']);
						}
					}
				}
				//vis dar ne adminas. blokuojam, nebent tai bandymas isiloginti
				if (!$u->i_admin()) {
					if ($u->get_user_id()) {
						$u->logout();
					}
					if ($p->requested_event() != 'sys.login#login') {
						$this->use_page('Login');
						$engine = & moon :: engine();
						$engine->disable_event();
					}
				}
				else {
					//load navigation
					$w = & moon :: shared('admin');
					$w -> sitemap(dirname($this->my('file')).'/navigation.txt');

					if (!$p->requested_event() && ($link = $w->getLink())) {
						$p->redirect($link);
					}
				}
		}
	}

}
?>