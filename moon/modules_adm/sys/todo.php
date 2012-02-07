<?php

class todo extends moon_com {

	function events($event) {
		switch ($event) {

			case 'support':
				$ini = & moon :: moon_ini();
				if ($ini->has('other', 'support.userHash')) {
					$userHash = $ini->get('other', 'support.userHash');
					$url = 'http://support.pokernews.com/client/index.php?hash=' . urlencode($userHash);
				}
				$p = & moon :: page();
				$p->redirect($url);
				break;
		}
	}

	function getNotifyingHeader() {
		$this->tpl = & $this->load_template();
		$this->messages = $this->tpl->parse_array('messages');
		$this->tasks = '';
		$this->count = 0;
		$m = array();
		$user = & moon :: user();


		/* special freerols */
		if (is_object($oF = & $this->object('tour.special'))) {
			$this->task('freerolls', $oF->countFreerollsTODO(), 'tour.special#');
		}

		/* reviews promotions */
		if (is_object($oP = & $this->object('reviews.promotions'))) {
			$this->task('promotions', $oP->countPromotionsTODO(), 'reviews.promotions#');
		}


		/*  leagues and promotions */
		if ($user->i_admin('tournaments') && is_object($o = & $this->object('promo.promos'))) {
			$updatesBatch = $o->updates();
			if (0 != count($updatesBatch)) {
				foreach ($updatesBatch as $updateType => $updates) {
					foreach ($updates as $v) {
						switch ($updateType) {

							case 0:
								$this->task('promo0', $v[1], 'promo.promos#edit|' . $v[0]);
								break;

							case 1:
								$this->task('promo1', $v[2], 'promo.custom_pages|' . $v[0] . '.' . $v[1]);
								break;

							case 2:
								$this->task('promo1', $v[2], 'promo.schedule#|' . $v[0] . '.' . $v[1]);
								break;

							default:
						}
					}
				}
			}
		}



		/* comments spam */
		/*if ($user->i_admin('content')) {
			// articles
			$r = $this->db->single_query('SELECT COUNT(*) FROM articles_comments WHERE spam != 0');
			$this->task('comments0', (int) $r[0], 'articles.spam_comments#');

			//video
			$r = $this->db->single_query('SELECT COUNT(*) FROM videos_comments WHERE spam != 0');
			$this->task('comments1', (int) $r[0], 'video.spam_comments#');
		}*/
		//what's new
		$m['whatsnew'] = false;
		if (!$user->i_admin('developer') && is_object($oW = & $this->object('sys.whatsnew'))) {
			$d = $oW->getLastFeature();
			if (!empty ($d)) {
				$this->task('whatsnew', $d['title'], 'sys.whatsnew#edit|' . intval($d['id']));
			}
		}

		/* output */
		$m['class'] = '';
		$m['count'] = 0;
		if ($this->count) {
			$m['class'] = 'undone';
			$m['undone'] = true;
			$m['tasks'] = $this->tasks;
			$m['count'] = $this->count;
		}
		return $this->tpl->parse('todo_box', $m);
	}

	function task($name, $count, $url) {
		if (!empty ($count)) {
			$a = array();
			list($a['class'], $a['msg']) = explode('|', $this->messages[$name], 2);
			list($event, $par) = explode('|', $url . '|');
			$a['url'] = $this->link($event, $par);
			if (is_numeric($count)) {
				$this->count += $count;
				$a['c'] = $count;
			}
			else {
				$this->count++;
				$a['title'] = $count;
			}
			$this->tasks .= $this->tpl->parse('tasks', $a);
		}
	}

	function checkSupport() {
		$ini = & moon :: moon_ini();
		if (!is_dev() && $ini->has('other', 'support.userID') && $ini->has('other', 'support.userHash')) {
			$userID = $ini->get('other', 'support.userID');
			$userHash = $ini->get('other', 'support.userHash');
		}
		else
			return 0;
		//
		$locale = & moon :: locale();
		$now = $locale->now();
		//
		$p = & moon :: page();
		$lastCheck = (int) $p->get_global('support.checkTime');
		//$lastCheck=0;
		if ($lastCheck && abs($now - $lastCheck) < 120)
			$count = intval($p->get_global('support.checkResult'));
		else {
			$ch = curl_init('http://support.pokernews.com/msg.php');
			curl_setopt($ch, CURLOPT_HEADER, 0);
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_TIMEOUT, 4);
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 4);
			curl_setopt($ch, CURLOPT_POSTFIELDS, 'id=' . $userID . '&siteID=' . _SITE_ID_);
			$s = curl_exec($ch);
			$err = curl_error($ch);
			curl_close($ch);
			if ($err) {
				moon :: error('Support curl error: ' . $err, 'F');
				$s = 0;
			}
			$count = intval($s);
			$p->set_global('support.checkResult', $count);
			$p->set_global('support.checkTime', $now);
		}
		if ($count)
			$this->urlSupport = 'http://support.pokernews.com/client/index.php?hash=' . urlencode($userHash);
		return $count;
	}

}

?>