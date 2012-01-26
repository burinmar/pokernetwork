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
			$p = &moon::page();
			$p->redirect($url);
			break;
		}
	}

	function getNotifyingHeader() {
		$c1 = in_array(_SITE_ID_, array('com','br','china','cz','dk','fi','fr','il','jp','kr','lt','no','ru','se','tr','tw','uk')); // articles
		$c2 = in_array(_SITE_ID_, array('it','bg','es','fr'));
		$m = array();
		$m['tasksReturned'] = $m['tasksInProgress'] = 0;
		$m['hasTranslation'] = FALSE;
		if (is_object($oT = & $this->object('translator.tasks'))) {
			$countArr = $oT->getUnfinishedTaskCount();
			if (isset ($countArr['progress'])) $m['tasksInProgress'] = $countArr['progress'];
			if (isset ($countArr['returned'])) $m['tasksReturned'] = $countArr['returned'];
			if ($m['tasksReturned'] || $m['tasksInProgress']) $m['hasTranslation'] = true;
		}
		if (_SITE_ID_ !== 'com' && is_object($oF = & $this->object('tour.special'))) $m['freerolls'] = $oF->countFreerollsTODO();
		if (_SITE_ID_ !== 'com' && is_object($oP = & $this->object('reviews.promotions'))) $m['promotions'] = $oP->countPromotionsTODO();
		if (_SITE_ID_ !== 'com' && is_object($oG = & $this->object('games.freegames'))) $m['games'] = $oG->countGamesTODO();
		$m['support'] = '';
		//if ($m['support'] = $this->checkSupport()) $m['url.support'] = $this->urlSupport;
		$m['t'] = '';

		$u = &moon::user();

		$m['whatsnew'] = false;
		if (!$u->i_admin('developer') && is_object($oW = & $this->object('sys.whatsnew'))) {
			$d = $oW->getLastFeature();
			if (!empty($d)) {
				$m['whatsnew'] = true;
				$m['wID'] = intval($d['id']);
				$m['wTitle'] = htmlspecialchars($d['title']);
			}
		}
		if ($m['hasTranslation'] || !empty ($m['freerolls']) || !empty ($m['promotions']) || !empty ($m['games']) || !empty ($m['feedback']) || $m['support'] || $m['t'] || $m['whatsnew']) {
			$t = & $this->load_template();
			return $t->parse('todo_box', $m);
		}
		return '';
	}

	function checkSupport() {
		$ini = & moon :: moon_ini();
		if (!is_dev() && $ini->has('other', 'support.userID') && $ini->has('other', 'support.userHash')) {
			$userID = $ini->get('other', 'support.userID');
			$userHash = $ini->get('other', 'support.userHash');
		}
		else return 0;
		//
		$locale = & moon :: locale();
		$now = $locale->now();
		//
		$p = &moon::page();
		$lastCheck = (int) $p->get_global('support.checkTime');
		//$lastCheck=0;
		if ($lastCheck && abs($now - $lastCheck) < 120) $count = intval($p->get_global('support.checkResult'));
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
		if ($count) $this->urlSupport = 'http://support.pokernews.com/client/index.php?hash=' . urlencode($userHash);
		return $count;
	}
}
?>