<?php


class profile extends moon_com {


	function onload() {
		$user = & moon :: user();
		($id = $user->get_user_id()) || ($id = $user->get_user('tmpID'));
		$this->userID = (int) $id;

		/* Settings*/
		$this->form1 = & $this->form('id', 'name', 'birthdate', 'sex', 'homepage', 'email', 'timezone', 'avatar', 'about', 'twitter');
		$this->form1->fill(array( 'sex' => 'M', 'timezone' => 0));

		/* Password */
		$this->form2 = & $this->form('password', 'pass1', 'pass2');
		$this->form2->fill();

		/* Notifications */
		$this->form3 = & $this->form('pm_mailnotify', 'subscribe');
		$this->form3->fill();

		/* Forum */
		$this->form4 = & $this->form('forum_paging', 'forum_signature');
		$this->form4->fill();

		/* Forum subscriptions */
		$this->form5 = & $this->form('id');
		$this->form5->fill();

		/* table Users*/
		$this->myTable = $this->table('Users');
	}


	function events($event, $par) {
		$this->use_page('Profile');
		if (isset ($_GET['key']) && isset($_GET['e'])) {
			//emailo keitimas
			$this->saveEmail($_GET['key'], $_GET['e']);
		}
		switch ($event) {

			case 'save-settings' :
				$this->saveSettings();
				$event = '';
				break;

			case 'save-password' :
				$this->savePassword();
				$event = '';
				break;

			case 'save-notifications' :
				$this->saveNotifications();
				$event = 'notifications';
				break;

			case 'save-forum' :
				$this->saveForum();
				$event = 'forum';
				break;

			case 'save-forum-subscriptions' :
				$this->saveForumSubscriptions();
				$event = 'forum-subscriptions';
				break;

			default :
		}
		$this->set_var('tab', $event);
	}


	function properties() {
		return array('tab' => '');
	}


	function main($vars) {
		$tpl = & $this->load_template();
		//tabs
		$m = array();
		$m['pageTitle'] = '';
		$m['page'] = $m['tab-item'] = '';
		$tabs = $tpl->parse_array('tabs');
		if (!is_dev()) {
			unset($tabs['tab5']);
		}
		$a = array();
		$onTab = '#' . $vars['tab'];
		foreach ($tabs as $v) {
			list($url, $name) = explode('|', $v, 2);
			$url = trim($url);
			$a['on'] = $url == $onTab;
			$a['url'] = $this->linkas($url);
			$a['name'] = htmlspecialchars(trim($name));
			$m['tab-item'] .= $tpl->parse('tab-item', $a);
			if ($a['on']) {
				$m['pageTitle'] = $a['name'];
			}
		}
		$tpl->save_parsed('viewSaved', $m);
		$user = & moon :: user();
		$userID = $user->get_user_id();
		if (!$userID && !($tmpID = $user->get_user('tmpID'))) {
			$m['page'] = $tpl->parse('viewAnonymous');
		}
		else {
			switch ($onTab) {

				/*case '#password' :
					$m['page'] = $this->viewPassword($vars);
					break;*/

				case '#forum' :
					$m['page'] = $this->viewForum($vars);
					break;

				case '#notifications' :
					$m['page'] = $this->viewNotifications($vars);
					break;

				case '#forum-subscriptions' :
					$m['page'] = $this->viewForumSubscriptions($vars);
					break;

				default :
					$m['page'] = $this->viewSettings($vars);
			}
		}
		$page = & moon :: page();
		//$page->css('/css/profile.css');
		$page->title($m['pageTitle']);
		$page->set_local('nobanners', 1);
		$navi = & moon :: shared('sitemap');
		$user = & moon :: user();
		$nick = $user->get_user('nick');
		$a = array($this->linkas('#') => $nick);
		$a[''] = $m['pageTitle'];
		$navi->breadcrumb($a);
		return $m['page'];
		return $tpl->parse('main', $m);
	}


	function viewSettings($vars) {
		$page = & moon :: page();
		$locale = & moon :: locale();
		$tpl = & $this->load_template();
		$info = $tpl->parse_array('info');
		if (isset ($vars['ok'])) {
			//tik ka atnaujintas
			$m = array();
			if (!empty($vars['mailSent'])) {
				$m['msg'] = $info['changeEmailMsg'];
				$m['email'] = $vars['mailSent'];
			}
			else {
				$m['msg'] = $info['okSettings'];
			}
			return $tpl->parse('viewSaved', $m);
		}

		/******* FORM **********/
		$err = (isset ($vars['error'])) ? $vars['error'] : 0;
		$f = $this->form1;

		$favRoomsList = '';
		$allRoomsList = '';
		$roomsMinipager = '';
		
		if (!$err || $err >= 30) {
			$oLogin = & $this->object('login_object');
			$is = $oLogin->vCard($this->userID);
			$f->fill($is);
			if (!count($is)) {
				$err = 7;
			}
		}

		// get local data
		// about field
		$is = $this->db->single_query_assoc('SELECT about FROM ' . $this->myTable . ' WHERE id = ' . $this->userID);
		$f->fill($is);

		
		//$page->js('/js/jquery/livequery.js');
		//$page->js('/js/modules/users.profile.js');


		// end get local data

		$m = array();
		$m['event'] = $this->my('fullname') . '#save-settings';
		$m['refresh'] = $page->refresh_field();
		$m['id'] = $this->userID;
		if ($err) {
			$m['error'] = isset ($info['err' . $err]) ? $info['err' . $err] : 'Error: ' . $err;
		}
		$m += $f->html_values();

		$m['sex=M'] = $m['sex'] == 'M' ? 'M" checked="checked' : 'M';
		$m['sex=F'] = $m['sex'] != 'M' ? 'F" checked="checked' : 'F';
		list($dY, $dM, $dD) = explode('-', $m['birthdate'] . '--');
		$m['birthdate[y]'] = (int) $dY ? (int) $dY : '';
		$tmpF = $this->form('m');
		$tmpF->fill(array('m' => intval($dM)));
		$m['birthdate[m]'] = $tmpF->options('m', $locale->months_names());
		$m['birthdate[d]'] = (int) $dD ? (int) $dD : '';
		$m['timezone'] = $f->options('timezone', $locale->select_timezones());
		if ($m['avatar']) {
			$m['avatarSrc'] = img('avatar', $m['id'] . '-' . $m['avatar'] .'?o');
		}
		
		/* password */
		$f2 = $this->form2;
		$m['event_pwd'] = $this->my('fullname') . '#save-password';
		if (!empty ($vars['errorPWD'])) {
			$err = $vars['errorPWD'];
			$m['errorPWD'] = isset ($info['err' . $err]) ? $info['err' . $err] : 'Error: ' . $err;
		}
		$m += $f2->html_values();

		$res =  $tpl->parse('viewSettings', $m);

        return $res;
	}


	function viewPassword($vars) {
		$page = & moon :: page();
		$tpl = & $this->load_template();
		$info = $tpl->parse_array('info');
		if (isset ($vars['ok'])) {
			//tik ka atnaujintas
			$m = array();
			$m['msg'] = $info['okPassword'];
			return $tpl->parse('viewSaved', $m);
		}

		/******* FORM **********/
		$f = $this->form2;
		$m = array();
		$m['event'] = $this->my('fullname') . '#save-password';
		$m['refresh'] = $page->refresh_field();
		if (!empty ($vars['error'])) {
			$err = $vars['error'];
			$m['error'] = isset ($info['err' . $err]) ? $info['err' . $err] : 'Error: ' . $err;
		}
		$m += $f->html_values();
		return $tpl->parse('viewPassword', $m);
	}

	function viewForumSubscriptions($vars) {
		$page = & moon :: page();
		$tpl = & $this->load_template();
		$info = $tpl->parse_array('info');
		$fs = $this->object('board.board');
		$threadUrl = $this->linkas('board.board#topic{id}');

		if (isset ($vars['ok'])) {
			//tik ka atnaujintas
			$mainArgv = array();
			$mainArgv['msg'] = $info['okForumSubscriptions'];
			return $tpl->parse('viewSaved', $mainArgv);
		}

		$err = (isset ($vars['error'])) ? $vars['error'] : 0;
		$f = $this->form4;

		$subscriptions = $fs->instCurrentUSer()->getSubscribedThreads($this->userID);
		/*$unsubscribe = array();
		if ($err) {
			//$db = & moon :: db('mypokernews');
			//$is = $db->get_user($this->userID, $err, 'forum_signature,forum_paging');
			//$f->fill($is);
			$unsubscribe = array(1);
		}*/

		$mainArgv = array(
			'list.subscribed' => '',
			'event' => $this->my('fullname') . '#save-forum-subscriptions',
			'refresh' => $page->refresh_field()
		);

		foreach ($subscriptions as $subscription) {
			$mainArgv['list.subscribed'] .= $tpl->parse('viewForumSubscriptionsItem', array(
				'id' => $subscription['id'],
				'url' => str_replace('{id}', $subscription['id'], $threadUrl),
				'title' => htmlspecialchars($subscription['title'])
			));
		}

		if (!empty($vars['error'])) {
			$err = $vars['error'];
			$mainArgv['error'] = isset ($info['err' . $err]) ? $info['err' . $err] : 'Error: ' . $err;
		}

		return $tpl->parse('viewForumSubscriptions', $mainArgv);
	}

	function viewNotifications($vars) {
		$page = & moon :: page();
		$tpl = & $this->load_template();
		$info = $tpl->parse_array('info');
		if (isset ($vars['ok'])) {
			//tik ka atnaujintas
			$m = array();
			$m['msg'] = $info['okNotifications'];
			return $tpl->parse('viewSaved', $m);
		}

		/******* FORM **********/
		$err = (isset ($vars['error'])) ? $vars['error'] : 0;
		$f = $this->form3;
		$m = array();
		if (!$err) {

			$oLogin = & $this->object('login_object');
			$is = $oLogin->vCard($this->userID);
            //icontact
			/*if (!empty($is['email'])) {
				include_class('icontact');
				$db = new icontact;
				$subs = $db->is_subscribed($is['email']);
				if (!$subs) {
					$m['icontact'] = TRUE;
				}
				$is['subscribe'] = $subs ? 1 : 0;
			}*/
			$f->fill($is);
		}
		$m['event'] = $this->my('fullname') . '#save-notifications';
		$m['refresh'] = $page->refresh_field();
		if (!empty ($vars['error'])) {
			$err = $vars['error'];
			$m['error'] = isset ($info['err' . $err]) ? $info['err' . $err] : 'Error: ' . $err;
		}
		$m += $f->html_values();
		$m['pm_mailnotify'] = $f->checked('pm_mailnotify', 1);
		$m['subscribe'] = $f->checked('subscribe', 1);
		return $tpl->parse('viewNotifications', $m);
	}


	function viewForum($vars) {
		$page = & moon :: page();
		$tpl = & $this->load_template();
		$info = $tpl->parse_array('info');
		if (isset ($vars['ok'])) {
			//tik ka atnaujintas
			$m = array();
			$m['msg'] = $info['okForum'];
			return $tpl->parse('viewSaved', $m);
		}

		/******* FORM **********/
		$err = (isset ($vars['error'])) ? $vars['error'] : 0;
		$f = $this->form4;
		if (!$err) {
			$oLogin = & $this->object('login_object');
			$is = $oLogin->vCard($this->userID);
			$f->fill($is);
		}
		$m = array();
		$m['event'] = $this->my('fullname') . '#save-forum';
		$m['refresh'] = $page->refresh_field();
		if (!empty ($vars['error'])) {
			$err = $vars['error'];
			$m['error'] = isset ($info['err' . $err]) ? $info['err' . $err] : 'Error: ' . $err;
		}
		$m += $f->html_values();
		$m['tools'] = $this->makeSignatureTool();
		$m['paging20'] = $f->checked('forum_paging', 20);
		$m['paging50'] = $f->checked('forum_paging', 50);
		$m['paging100'] = $f->checked('forum_paging', 100);
		//$m['forum_paging'] = $f->options('forum_paging', array(20, 50, 100));
		return $tpl->parse('viewForum', $m);
	}


	//***************************************
	//           --- DB AND OTHER ---
	//***************************************
	function saveSettings() {
		$form = & $this->form1;
		$form->fill($_POST);
		$d = $form->get_values();
		$id = intval($d['id']);

		/* gautu duomenu apdorojimas */
		$dateOK = FALSE;
		if (isset ($_POST['birthdate']) && is_array($_POST['birthdate'])) {
			$b = $_POST['birthdate'];
			if ($b['y'] == '' && $b['d']=='') {
				$dateOK = TRUE;
				$d['birthdate'] = '';
			}
			else {
				$d['birthdate'] = $b['y'] . '-' . $b['m'] . '-' . $b['d'];
				foreach ($b as $k => $v) {
					$b[$k] = intval($v);
				}
				if ($b['y'] > 1900 && $b['y'] <= date('Y') && $b['d'] > 0 && $b['d'] <= 31 && checkdate($b['m'], $b['d'], $b['y'])) {
					$dateOK = TRUE;
				}
			}
		}

		/* jei bus klaida */
		$form->fill($d, false);

		/* validacija */
		$changeMail = FALSE;
		$user = & moon :: user();
		$mail = & moon :: mail();
		$err = 0;
		if (!$dateOK) {
			$err = 23;
		}
		elseif ($d['email'] == '' || !$mail->is_email($d['email'])) {
			$err = 21;
		}
		elseif ($d['email'] != $user->get_user('email')) {
			//patikrinam ar bazeje emailas unikalus
			$oLogin = & $this->object('login_object');
			$is = $oLogin->vCard($this->userID);
			$oLogin = $this->object('login_object');
			$r = array('email'=>$d['email']);
			$oLogin->_send_request('findUser', $r , $err2);
			if ($err2) {
				$err = 7;
			}
			elseif (isset($r['email'])) {
				$err = 22;
			}
			else {
            	$changeMail = TRUE;
			}
		}
		if ($err) {
			$this->set_var('error', $err);
			//$this->set_var('view', 'form');
			return false;
		}

		/* jei refresh, nesivarginam */
		if ($wasRefresh = $form->was_refresh()) {
			$this->set_var('ok', 1);
			return;
		}

		/* nori keisti mail */
		if ($changeMail) {
			//generuojam tmp ir siunciam emaila
			$code = $this->generate_code($id, $d['email']);
			$page = & moon :: page();
			$get = array('key' => $id . 'n' . $code, 'e' => $d['email']);
			$url = rtrim($page->home_url(), '/') . $this->url('#', '', $get);
			$tpl = & $this->load_template();
			$msg = $tpl->parse('mailChange', array('url.change' => $url));
			$mail = & moon :: mail();
			$mail->charset('utf-8');
			$mail->to($d['email']);
			//$u->get_user('email')
			$einfo = $tpl->parse_array('mailChangeHeaders');
			$mail->from($einfo['from']);
			$mail->subject($einfo['subject']);
			$mail->body($msg);
			if (!$mail->send()) {
				$err = 7;
				moon :: error('Neveikia mail ('.$d['email'].')');
				$this->set_var('error', $err);
				return false;
			}
			else {
				$this->set_var('mailSent', $d['email']);
			}
		}

		/* save to database */
		$ins = $form->get_values('name', 'birthdate', 'sex', 'homepage', 'show_public', 'timezone', 'twitter');
		//dabar image
		$del = empty ($_POST['del_avatar']) ? FALSE : TRUE;
		$file = & moon :: file();
		if ($file->is_upload('avatar', $e)) {
			if (!$file->has_extension('jpg,jpeg,gif,png')) {
				//neleistinas pletinys
				$err = - 1;
			}
			else {
				$ext = rtrim('.' . $file->file_ext(), '.');
				$name = uniqid('') . $ext;
				$tmpFile = _W_DIR_ . $name;
				$img =& moon :: shared('img');
				if ($img->thumbnail($file, $tmpFile, 200, 200) && $file->is_file($tmpFile)) {
					$base64 = base64_encode(file_get_contents($file->file_path()));
					$ins['avatar'] = $name;
					$ins['avatar-file'] = $base64;
					$file->delete();
				}
			}
		}
		elseif ($del) {
			$ins['avatar'] = '';
		}

		$oLogin = &$this->object('login_object');
		$r = $oLogin->update($this->userID, $ins, $err);


			if ($err) {
				$err = 7;
			}
			if ($err) {
			$this->set_var('error', $err);
			return false;
		}

		$db = & $this->db();
		$ins2 = $form->get_values('name', 'timezone', 'about', 'twitter', 'homepage', 'show_public', 'birthdate', 'sex');
		$user = & moon :: user();
		$user->set_user('timezone', $ins2['timezone']);
		if (isset($r['avatar'])) {
			$ins2['avatar'] = $r['avatar'];
			$user->set_user('avatar', $ins2['avatar']);
		}
		$db->update($ins2, $this->myTable, array('id' => $this->userID));

		$form->clear();
		$this->set_var('ok', 1);
		return TRUE;
	}

	function saveForumSubscriptions()
	{
		$form = & $this->form5;
		$form->fill($_POST);
		$d = $form->get_values();
		$fs = $this->object('board.board');

		if (!isset($d['id']) || !is_array($d['id'])) {
			$this->set_var('error', 51);
			return false;
		}

		if (($wasRefresh = $form->was_refresh()) == true) {
			$this->set_var('ok', 1);
			return;
		}
		
		$deleteIds = array_keys($d['id']);
		foreach ($deleteIds as $id) {
			$fs->instCurrentUser()->saveThreadIsSubscribed($id, false);
		}

		$form->clear();
		$this->set_var('ok', 1);
	}

	function savePassword() {
		$form = & $this->form2;
		$form->fill($_POST);
		$d = $form->get_values();

		/* validacija */
		$err = 0;
		if (empty ($d['password'])) {
			//neivestas senas pass
			$err = 3;
		}
		elseif (empty ($d['pass1']) && empty ($d['pass2'])) {
			//neivesti nauji pass
			$err = 4;
		}
		elseif ($d['pass1'] !== $d['pass2']) {
			//nauji pass nesutampa
			$err = 5;
		}
		elseif ($d['password'] === $d['pass1']) {
			// senas = naujas, kam keisti?
			$err = 8;
		}
		if ($err) {
			$this->set_var('errorPWD', $err);
			return false;
		}

		/* jei refresh, nesivarginam */
		if ($wasRefresh = $form->was_refresh()) {
			$this->set_var('ok', 1);
			return;
		}

		$oLogin = &$this->object('login_object');
		$a = $oLogin->vCard($this->userID);
		if (empty ($a['password']) || $a['password'] !== md5($d['password'])) {
			$err = 6;
		}
		else {
			$oLogin->update($this->userID, array('password' => md5($d['pass1'])), $err);
			if ($err) {
				$err = 7;
			}
		}
		if ($err) {
			$this->set_var('error', $err);
			return false;
		}
		$form->clear();
		$this->set_var('ok', 1);
	}

    function saveNotifications() {
		$form = & $this->form3;
		$form->fill($_POST);
		$d = $form->get_values();


		/* jei refresh, nesivarginam */
		if ($wasRefresh = $form->was_refresh()) {
			$this->set_var('ok', 1);
			return;
		}
		$err = 0;
		if ($err) {
			$this->set_var('error', $err);
			return false;
		}
		$ins = $form->get_values('pm_mailnotify');
		$oLogin = &$this->object('login_object');
		$oLogin->update($this->userID, $ins, $err);

		//vemail
		$user = & moon :: user();
		$email = $user->get_user('email');
		$subs = $form->get('subscribe');
		if (!empty($email)) {
			include_class('icontact');
			$db = new icontact;
			if ($subs) {
				$db->subscribe($email);
			}
			else {
				$db->unsubscribe($email);
			}
		}

		//finalize
        $form->clear();
		$this->set_var('ok', 1);
	}

	function saveForum() {
		$form = & $this->form4;
		$form->fill($_POST);
		$d = $form->get_values();
		if (strlen($d['forum_signature'])) {
			$d['forum_signature'] = substr($d['forum_signature'], 0, 1000);
		}
		/* jei bus klaida */
		$form->fill($d, false);


		/* jei refresh, nesivarginam */
		if ($wasRefresh = $form->was_refresh()) {
			$this->set_var('ok', 1);
			return;
		}
		$ins = $form->get_values('forum_paging', 'forum_signature');
		$oLogin = &$this->object('login_object');
		$oLogin->update($this->userID, $ins, $err);

		$txt = & moon :: shared('text');
		$ins['forum_signature'] = $txt->excerpt($ins['forum_signature'], 300);
		$txt->features = array_merge($txt->features,array('tags' => 'b|i|url|sub|sup|strike'));
		$ins['forum_signature'] = $txt->article($ins['forum_signature']);

		$db = & $this->db();
		$user = & moon :: user();
		$user->set_user('forum_paging', $ins['forum_paging']);
		$db->update($ins, $this->myTable, array('id' => $this->userID));

        $form->clear();
		$this->set_var('ok', 1);
	}


	function saveEmail($key, $email) {
		$a = explode('n', $_GET['key'], 2);
		if (count($a) == 2 && $a[0]) {
			list($userID, $code) = $a;
		}
		else {
			$this->set_var('error', 30);
			return;
		}
		$u = & moon :: user();
		$id = $u->id();
		$email = trim(substr($email,0,50));
		$mail = & moon :: mail();
		if ($userID != $id || trim($code) != $this->generate_code($id, $email) || !$mail->is_email($email)) {
        	$this->set_var('error', 30);
			return;
		}
		$oldEmail = $u->get_user('email');
		if ($oldEmail === $email) {
			$this->set_var('ok', 1);
			return;
		}
		$err = 0;

		$oLogin = &$this->object('login_object');
		$oLogin->update($id, array('email' => $email), $err);
		if ($err) {

			/* $err:
			2 - bad email
			3 - this email is in use
			-1 technical error
			*/
			switch ($err) {

				case 2 :
					$err = 31;
					break;

				case 3 :
					$err = 32;
					break;

				default :
					$err = 7;
			}
		}
		if ($err) {
			$this->set_var('error', $err);
			return;
		}
		$this->set_var('ok', 1);
		$u->set_user('email', $email);
		$email = $this->db->escape($email);
		$this->db->query('UPDATE ' . $this->table('Users') . " SET email='" . $email . "' WHERE id=$id ");
	}

	function generate_code($id, $email) {
		return (string)abs(sprintf('%u', crc32("idis" . $id . $email . "h[j,/mailc32")));
	}




	function makeSignatureTool() {

	$t=&$this->load_template();

    // kortos
	$ca1=array(2,3,4,5,6,7,8,9,10,'j','q','k','a');
	$ca2=array('&clubs;','&spades;','&hearts;','&diams;');
	$ca3=array('c','s','h','d');
	$m['cards']='';
	$s1='';
	foreach ($ca2 as  $k=>$v) {
		foreach($ca1 as $n) {
			$img=strtolower($n.$ca3[$k]);
			if ($img=='ad') $img='da';
			$s1.='<td><a href="" onclick="justInsert(\'{'.$n.$ca3[$k].'}\');return false;"><img src="/img/cards/'.$img.'.gif" alt="" width="25" height="15" border="0" id="crd1'.$n.$ca3[$k].'" /></a></td> ';
		}
		$m['cards'] .= "<tr>".$s1."</tr>";
		$s1='';
	}
	return $m['cards'];

    // sypseneles
    /*
    $m['smileys']='';
    $s2 = '';
    $text = &moon::shared('text');
    $smiles = $text->available_smiles();
    foreach ($smiles as $k=>$v) {
    $k = str_replace("'","\\'",$k);
        $s2 .= '<a href="" onclick="justInsert(\''.$k.'\');return false;">'.$v.'</a>';
    }
    $m['smileys'] .= "<tr><td>".$s2."</td></tr>";
    */

	return $t->parse('signatureTools', $m);
}


}

?>