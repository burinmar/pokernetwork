<?php

class contactus extends moon_com {

	function onload() {
		$this->form = & $this->form('name', 'email', 'subject', 'body', 'captcha');
		$this->form->fill();
		$fillFormArr = array();
		if (empty ($_POST)) {
			if (array_key_exists('s', $_GET)) {
				switch ($_GET['s']) {

					case 'piracy':
						$fillFormArr['subject'] = '8';
						break;
				}
			}
			if (array_key_exists('url', $_GET)) {
				$fillFormArr['subject'] = '1';
				$fillFormArr['body'] = urldecode($_GET['url']);
			}
			$this->form->fill($fillFormArr);
		}
	}

	function events($event, $par) {
		$this->use_page('Contact');
		switch ($event) {

			case 'send':
				if ($this->saveItem()) {
					$this->redirect('#', '', 'success');
				}
				break;

			default:
				if (isset ($_GET['success'])) {
					$this->set_var('view', 'success');
					$this->forget();
				}
		}
	}

	function properties() {
		return array('view' => '', 'error' => array());
	}

	function main($vars) {
		$t = & $this->load_template();
		if ($vars['view'] == 'success') {
			return $t->parse('success');
		}
		$a = array();
		$a['event'] = $this->my('fullname') . '#send';
		$a['refresh_field'] = moon :: page()->refresh_field();
		$a += $this->form->html_values();
		$sitemap = & moon :: shared('sitemap');
		$pageInfo = $sitemap->getPage();
		$a['pageIntro'] = $pageInfo['content_html'];
		$subjects = $t->parse_array('subject');
		$a['subject'] = $this->form->options('subject', $subjects);
		$a['error'] = '';
		if (!empty ($vars['error'])) {
			$msg = $t->parse_array('error');
			$errID = $vars['error'];
			$a['error'] = isset ($msg[$errID]) ? $msg[$errID]:'Error: ' . $errID;
		}
		return $t->parse('main', $a);
	}

	function saveItem() {
		$form = & $this->form;
		$form->fill($_POST);
		$d = $form->get_values();
		$t = & $this->load_template();
		$subjects = $t->parse_array('subject');
		$mail = & moon :: mail();

		/* gautu duomenu apdorojimas */
		// -
		//jei bus klaida
		$form->fill($d, FALSE);

		/* validacija */
		$err = 0;
		if ($d['email'] == '' || !$mail->is_email($d['email'])) {
			$err = 3;
		}
		elseif (!isset ($subjects[$d['subject']])) {
			$err = 2;
		}
		elseif ($d['body'] == '') {
			$err = 1;
		}
		else {
			$p = & moon :: page();
			$captchaCode = $p->get_global('captcha');
			$p->set_global('captcha', '');
			if ($captchaCode != $d['captcha']) {
				$err = 4;
			}
		}
		if ($err) {
			$this->set_var('error', $err);
			return FALSE;
		}

		/* jei refresh, nesivarginam */
		if ($wasRefresh = $form->was_refresh()) {
			return FALSE;
		}

		/* send message */
		$mail->charset('UTF-8');
		$mail->from('dontreply@pokernews.com');
		$mail->subject($subjects[$d['subject']]);
		$txt = $t->parse('mail_body', array('name' => $d['name'], 'from' => $d['email'], 'body' => $d['body']));
		$mail->body($txt);
		$to = 'info@pokernetwork.com';
		$ok = $mail->to($to);
		if (!$mail->send()) {
			$this->set_var('error', 5);
			return FALSE;
		}
		return TRUE;
	}

}

?>