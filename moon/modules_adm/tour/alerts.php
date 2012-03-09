<?php

class alerts extends moon_com {
	function events($event) {
		switch ($event) {
			case 'sendmail':
				ob_start();
				$this->sendmail();
				$page = &moon::page();
				$page->set_local('cron', ob_get_contents());
				return;
		}
	}

	function sendmail()	{
		set_time_limit(3400);
		$this->populateQueue();
		$this->processQueue();
	}

	var $queueMinTime = 10800; // 3h
	var $queueMaxTime = 86400; // 24h
	var $queueLimit = 1000;
	var $sendMaxTime = 7200; // 2h
	var $sendLimit = 1000;

	function populateQueue() {
		$queueMinTime = $this->queueMinTime;
		$queueMaxTime = $this->queueMaxTime;

		$this->db->query('SET time_zone = "+0:00"');
		$tournaments = $this->db->array_query_assoc('
			SELECT id, room_id, unix_timestamp(qualification_from) qualification_from, date
			FROM tournaments_special
			WHERE hide=0 AND `date`>' . time(), 'id');
		$oldTournaments = array_keys($this->db->array_query_assoc('
			SELECT id FROM tournaments_special
			WHERE GREATEST(UNIX_TIMESTAMP(qualification_from), `date`)<	' . time(), 'id'));
		$this->db->query('SET time_zone = "SYSTEM"');
		$qualifTourns = array();
		$launchTourns = array();
		foreach ($tournaments as $tournament) {
			if ($tournament['qualification_from'] > (time()+$queueMinTime) && $tournament['qualification_from'] < (time()+$queueMaxTime)) $qualifTourns[] = $tournament['id'];
			if ($tournament['date'] > (time()+$queueMinTime) && $tournament['date'] < (time()+$queueMaxTime)) $launchTourns[] = $tournament['id'];
		}

		if (count($launchTourns) == 0 && count($qualifTourns) == 0) return ;

		$users = $this->db->query('
			SELECT user_id, rooms, tournaments
			FROM tournaments_special_subscriptions
			ORDER BY processed_on, user_id
			LIMIT ' . $this->queueLimit . '
		');

		while ($user = $this->db->fetch_row_assoc($users)) {
			$user['rooms'] =explode(',', $user['rooms']);
			$user['tournaments'] =explode(',', $user['tournaments']);
			if ($user['rooms'][0] === '') unset($user['rooms'][0]);
			if ($user['tournaments'][0] === '') unset($user['tournaments'][0]);
			foreach ($user['tournaments'] as $k => $v) {
				if (in_array(abs($v), $oldTournaments)) {
					unset($user['tournaments'][$k]);
				}
			}
			foreach ($launchTourns as $tId) {
				$roomId = $tournaments[$tId]['room_id'];
				if ($this->isSubscribed($tId, $roomId, $user)) {
					$this->db->query('
						INSERT IGNORE INTO tournaments_special_alert_queue
						(user_id, tournament_id, type, actual_until)
						VALUES(' . $user['user_id'] . ', ' . $tId . ', "launch", ' . $tournaments[$tId]['date'] . ')
					');
				}
			}
			foreach ($qualifTourns as $tId) {
				$roomId = $tournaments[$tId]['room_id'];
				if ($this->isSubscribed($tId, $roomId, $user)) {
					$this->db->query('
						INSERT IGNORE INTO tournaments_special_alert_queue
						(user_id, tournament_id, type, actual_until)
						VALUES(' . $user['user_id'] . ', ' . $tId . ', "qualification", ' . $tournaments[$tId]['qualification_from'] . ')
					');
				}
			}
			$this->db->update(array(
				'processed_on' => time(),
				'tournaments' => implode(',', $user['tournaments'])
			), 'tournaments_special_subscriptions', array(
				'user_id' => $user['user_id']
			));
		}
	}

	function currency($num, $currency) {
		$codes = array('USD' => '$', 'EUR' => '€', 'GBP' => '£');
		if (isset ($codes[$currency])) return $codes[$currency] . '' . $num;
		else return $num . ' ' . $currency;
	}


	function processQueue() {
		$this->db->query('DELETE FROM tournaments_special_alert_queue WHERE actual_until<' . (time()+$this->sendMaxTime));
		$users = $this->db->array_query_assoc('
			SELECT user_id FROM tournaments_special_alert_queue
			WHERE is_sent=0
			GROUP BY user_id
			ORDER BY MIN(actual_until)
			LIMIT ' . $this->sendLimit . '
		');

		$locale = & moon :: locale();
		//$ini = &moon::moon_ini();
		$tpl = $this->load_template();
		/*$lang = $locale->language();
		$langFile = $ini->get('engine', 'dir.multilang') . 'tour.txt' . ';' .
			$ini->get('engine', 'dir.multilang') . 'shared.txt';
		$tpl->set_language_pack($langFile, $lang);
		$tpl->use_language_pack();
		$tpl->load_file(MOON_MODULES . $this->my('location') . 'alerts.htm');*/


		$mailSent = 0;
		$mailSkipped =0 ;
		foreach ($users as $user) {
			//if ($user['user_id']!=16441) continue;
			$user = $this->db->single_query_assoc('
				SELECT id, email, timezone FROM users
				WHERE id=' . $user['user_id'] . '
			');
			if (empty($user)) continue;
			
			$send = $this->db->array_query_assoc('
				SELECT q.id queue_id, q.tournament_id, q.type, t.name, t.date, t.qualification_from q_from, t.qualification_to q_to, t.body, t.prizepool, r.name room_name, r.currency
				FROM tournaments_special_alert_queue q
				INNER JOIN tournaments_special t
					ON t.id=q.tournament_id
				INNER JOIN rw2_rooms r
					ON t.room_id=r.id
				WHERE q.user_id=' . $user['id'] . ' AND q.is_sent=0
				ORDER BY q.actual_until
			');
			if (0 == count($send)) return ;

			list($tOffset, $gmt) = $locale->timezone($user['timezone']);
			$p = &moon::page();
			$s = &moon::shared('sitemap');
			$url = rtrim($p->home_url(), "/") . '/' . ltrim($s->getLink('freerolls-special'), "/");

			$mail = &moon::mail();
			$mail->charset('UTF-8');
			$mail->from('info@pokernews.com');
			foreach ($send as $tournament) {
				$mail->to($user['email']);
				$titleTpl = ($tournament['type'] == 'launch') ? 'start:title' : 'qualification:title';
				$mail->subject($tpl->parse($titleTpl, array('tournament_name'=> $tournament['name'])));

				$bodyTpl = ($tournament['type'] == 'launch') ? 'start:body'	: 'qualification:body';
				$bodyArgv = array(
					'tournament_name'=> $tournament['name'],
					'qualification_period' => 'n/a',
					'start_date' => $locale->gmdatef($tournament['date'] + $tOffset, 'freerollTime') . ' ' . $gmt,
					'poker_room' => $tournament['room_name'],
					'prize_pool' => intval($tournament['prizepool']) ? $this->currency($tournament['prizepool'], $tournament['currency']) : 'n/a',
					'description' => $tournament['body'],
					'url' => $url
				);
				
				//$tournament['body'] = str_ireplace(array('[b]', '[/b]', '[i]', '[/i]', '[h]', '[/h]', '[table]', '[/table]', ), '', $tournament['body']);
				$bodyArgv['description'] = preg_replace('~\[\/?(b|i|h|table|url|quote)[^\]]*\]~i', '', $bodyArgv['description']);

				$date = $tournament['q_from'];
				if ($date !== '0000-00-00 00:00:00') {
					$time = strtotime($tournament['q_from'] . ' +0000') + $tOffset;
					$bodyArgv['qualification_period'] = $locale->gmdatef($time, 'freerollTime');
					if ($tournament['q_to'] !== '0000-00-00 00:00:00') {
						$time = strtotime($tournament['q_to'] . ' +0000') + $tOffset;
						$bodyArgv['qualification_period'] .= ' - ' . $locale->gmdatef($time, 'freerollTime');
					}
				}

				$mail->body($tpl->parse($bodyTpl, $bodyArgv));

				if (!$mail->send()) {
					sleep(1);
					$mailSkipped++;
					continue;
				}
				$mailSent++;
				$this->db->query('
					UPDATE tournaments_special_alert_queue
					SET is_sent=1
					WHERE id="' . $tournament['queue_id'] . '"
				');
			}			
		}
		echo 'Sent ' . $mailSent . ', failed ' . $mailSkipped;
	}

	function isSubscribed($id, $roomId, &$subscriptions) {
		return in_array($id, $subscriptions['tournaments'])	|| in_array($roomId, $subscriptions['rooms']) && !in_array(-$id, $subscriptions['tournaments']);
	}
}