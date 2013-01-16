<?php

/** 
 * each data block should either send all items minimum data, 
 * or pass exception rules (like ng_events do)
 */

class sync_reporting_v2 extends moon_com
{
	private $version = '0.2';
	private $pass = 'Covered in bees, send help';
	private $requestPrivKey = '-----BEGIN RSA PRIVATE KEY-----
MIICWwIBAAKBgQCz/tOVp4V1g4V2Z6MUUbn5zbXdbUwOdv4sbaJgvD1+iMmrJbq6
exC8JmIB53+z+2cv+6LO5n1i70JVgo89K0/61DJyT0ucCvlh/Dv97QZbI5RpSgAs
cx8CXxImNjXv1IX3MdSnxTjp+JeQeOCUHLqWl141JvmSgCci+sWTWFPaCwIDAQAB
AoGAP9NBpdSUT3pGrhjLzB26y6i1L4JdMNfjA1AQ/ypgx+irUkP7tbqD0aPupuw2
7VRdX7dkIOe8WIOsyvOT5UXhguDEpIPZ5apnBlF2o382tDmmE2i7peO4qSIHuOw1
pHO6jivMG1sAs6Pa4g2kM+H/ugGg7qyuztTc7a02ViFxjkECQQDdCAXT7KWXuirk
MZh7JDVRM00Xrx1ivl8N9ZIjxZ+qq1814q3BQAqUlq/TvOO/pag7LnL+Rqof/4zw
t9EYA7RhAkEA0HjPa//HcRdZ/LMP6FMn2xnFJodnngPDkzKwHpJ0kTHO5RWDL2Ks
g0WhEPBWdciq4icCJ6tHbMrnIFEeHaNl6wJAAv6w1YZHWB71pdHmNwTulAMV8FQ3
Gbdqok3JhSKQX0ejKp+/qvarLgg8qanNjDM6bFLczAU5GOXliv1yn9itAQJAPaj9
8LueidyWSR/NPLIbv7pHjbXO9/W1CvybCu/Went47lkGjCVrUQhvM0tix0OrB2jy
QjluzsbUxcI4XhvOMQJAfnVuvHVQui1lIpphX/kNNq3DFCSk0GphwPo3VaL13w1C
kiyQMrKMzzoSiMPFCs0XrbV8cjmfWJc9+/uzhJyj8g==
-----END RSA PRIVATE KEY-----';

	const lockfileTimeLimit = 2400; // 40 min
	const perTournamentHardTimeLimit = 1800; // 30 min (excluding net wait)
	const overallSoftTimeLimit = 1200; // 20 min (checked before each tournament)

	/**
	 * Sync all tournaments one by one
	 */
	public function syncAll()
	{
		set_include_path(get_include_path() . PATH_SEPARATOR . MOON_CLASSES . 'pear');
		require_once(MOON_CLASSES . 'pear/Archive/Tar.php');
		include_class('lock');

		if (isset($_GET['debug'])) {
			Header('content-type: text/plain; charset=utf8');
			Header('Cache-Control: no-cache');
			ini_set("html_errors","off");
		}
		ignore_user_abort();

		$lock = SunLock::fileLock('tmp/reporting_import.pnw.lock', self::lockfileTimeLimit);
		if (!$lock->tryLock()) {
			echo 'Lock file in place' . "\n";
			return ;
		}

		$tournaments = $this->db->array_query_assoc('
			SELECT id, name, sync_id, autopublish, state, updated_on FROM reporting_ng_tournaments
			WHERE is_syncable=1 AND is_live>=0 
			ORDER BY from_date DESC
		');
		echo 'Pending ' . count($tournaments) . ' item(s).' . "\n";

		$globalSoftTimer = time();

		foreach ($tournaments as $tournament) {
			// Be very generous and let each tournament sync for 25 minutes before *hard* abort
			// Don't forget it does not include e.g. network wait
			set_time_limit(self::perTournamentHardTimeLimit);
			// But only let the sync run at most 20 minutes before *soft* abort (as soon as possible)
			if ((time() - $globalSoftTimer) > self::overallSoftTimeLimit) {
				echo $tournament['name'] . ' global soft timer abort (do not start)' . "\n";
				break;
			}

			// This does not check if the tournament contents have changed recently, but the tournament itself
			// Since state change triggers updated_on, it is good enough
			if ($tournament['state'] == 2 && $tournament['updated_on'] < (time() - 7*24*3600)) {
				echo $tournament['name'] . ' skipped' . "\n";
				continue;
			}
			$tournament['sync_id'] = explode(':', $tournament['sync_id']);
			if (count($tournament['sync_id']) != 2) {
				continue ;
			}
			preg_match('~^(.+){(.*)}$~', $tournament['sync_id'][1], $eventsIdFilter);
			if (0 !== count ($eventsIdFilter)) {
				$tournament['sync_id'][1] = $eventsIdFilter[1];
				$eventsIdFilter = explode(',', $eventsIdFilter[2]);
				foreach ($eventsIdFilter as $k => $v) {
					$eventsIdFilter[$k] = intval($v);
				}
				$eventsIdFilter = array_unique($eventsIdFilter);
			} else {
				$eventsIdFilter = null;
			}

			$this->syncTournament(
				intval($tournament['id']),
				$tournament['sync_id'][0],
				intval($tournament['sync_id'][1]),
				$eventsIdFilter,
				$tournament['name'],
				$tournament['autopublish']
			);
		}

		$lock->unlock();

		echo 'All done. (' . round(memory_get_peak_usage() / 1024 / 1024, 3) . 'MiB maxmem)' . "\n";
	}
	
	/**
	 * Sync one specific tournament, called by syncAll
	 */
	private function syncTournament($localId, $remoteSite, $remoteId, $eventsIdFilter, $title, $autopublishLog)
	{
		echo $title . ':' . "\n";
		$time1 = microtime(true);
		$syncUrl = is_dev()
			? 'http://' . ($remoteSite == 'com' ? 'www.' : $remoteSite . '.') . 'pokernews.dev/livereporting-livereporting/read_uri/sync_v2.htm?moon_hello'
			: 'http://' . ($remoteSite == 'com' ? 'www.' : $remoteSite . '.') . 'pokernews.com/livereporting-livereporting/read_uri/sync_v2.htm?moon_hello';

		$this->lTournamentId  = $localId;
		$this->rTournamentId = $remoteId;
		$this->rEventsIdFilter = $eventsIdFilter;
		$this->lAutopublish = $autopublishLog;
		
		$baseDir = 'tmp/reporting-sync-' . strftime('%Y%m%d%H%M%S');
		mkdir($baseDir);
		mkdir($baseDir . '/request');
		mkdir($baseDir . '/response');
		
		if (FALSE !== $this->syncTournamentRequestData($syncUrl, $baseDir . '/request', $baseDir)) {
			$this->syncTournamentReplicateChanges($baseDir . '/response', $baseDir);
		}
		
		$this->rmdir_rec($baseDir . '/');
		$time2 = microtime(true);
		
		echo "	done in " . round($time2 - $time1, 4) . " s.\n\n";
	}
	
	/**
	 * Step 1 of syncTournament() - prepare and execute request
	 */
	private function syncTournamentRequestData($url, $requestDir, $workDir)
	{
		/**
		 * List of methods to prepare data for sending
		 */
		file_put_contents($requestDir . '/version.txt', $this->version);
		$this->_sendBase($requestDir . '/00_base');
		$this->_sendEventMisc($requestDir . '/05_event_misc');
		$this->_sendReporting($requestDir . '/10_reports');
		file_put_contents($requestDir . '/timestamp.txt', time());
		
		// Pack, encrypt, sign
		$tar = new Archive_Tar($requestDir. '.tbz2', 'bz2');
		$tar->createModify($requestDir, '', $requestDir);	
		$this->rmdir_rec($requestDir . '/');
		
		$plain     = fopen($requestDir. '.tbz2', 'rb');
		$encrypted = fopen($requestDir. '.tbz2.enc', 'wb');
		if ($plain === false || $encrypted === false) {
			echo "\tfailed to open request file\n";
			return FALSE;
		}
		if (function_exists('mcrypt_get_iv_size')) {
			$iv_size = mcrypt_get_iv_size(MCRYPT_BLOWFISH, MCRYPT_MODE_ECB);
			$iv = mcrypt_create_iv($iv_size, MCRYPT_RAND);
			while (!feof($plain)) {
				$string = fread($plain, 4096);
				if (FALSE === $string) {
					echo "\tmsg: failed decrypt read\n";
					return FALSE;
				}
				$string = mcrypt_encrypt(MCRYPT_BLOWFISH, $this->pass, $string, MCRYPT_MODE_ECB, $iv);
				if (FALSE === fwrite($encrypted, $string)) {
					echo "\tmsg: failed write encrypted\n";
					return FALSE;
				}
				$string = null;
			}
		} else {
			echo "\tmsg: mcrypt_get_iv_size unavailable\n";
			return FALSE;
		}
		fclose($plain);
		fclose($encrypted);
		unlink($requestDir. '.tbz2');
		echo '	sending ' . round(filesize($requestDir. '.tbz2.enc')/1024, 2) . "kb,\n";

		// Send & receive response
		$receiveFile = $workDir. '/response.tbz2.enc';
		$fh = fopen($receiveFile,'wb');
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_BINARYTRANSFER, 1);
		curl_setopt($ch, CURLOPT_FILE, $fh);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
		curl_setopt($ch, CURLOPT_TIMEOUT, 60*20);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, array(
			'request' => '@' . realpath($requestDir. '.tbz2.enc'),
			'signature' => $this->signFile($this->requestPrivKey, realpath($requestDir. '.tbz2.enc')),
			'site' => _SITE_ID_
		));
		curl_setopt($ch, CURLOPT_FAILONERROR, 1);
		
		curl_exec($ch);
		fclose($fh);
		echo curl_error($ch);
		unlink($requestDir. '.tbz2.enc');
	}
	
	private function _sendBase($baseDir)
	{
		mkdir($baseDir);
		$this->lEventsIdInitFilter = array();
		// Tournament
		$this->_sendBaseTournament($baseDir);
		// Events
		$this->_sendBaseEvents($baseDir);
		// Days
		$this->_sendBaseDays($baseDir);
	}
	
	private function _sendBaseTournament($baseDir)
	{
		$this->sendBaseTournament($baseDir);
	}
	
	private function _sendBaseEvents($baseDir)
	{
		$this->sendBaseEvents($baseDir);
	}
	
	private function sendBaseTournament($baseDir)
	{
		$data = $this->db->single_query_assoc('
			SELECT synced_on,logo_big_bg,logo_mid,logo_idx,logo_small FROM reporting_ng_tournaments
			WHERE id=' . $this->lTournamentId . ' AND is_live!=-1
		');
		$data['id'] = $this->rTournamentId;
		foreach (array(
			array('logo_mid',   'fs:LogosMid'),
			array('logo_big_bg','fs:LogosBigBg'),
			array('logo_small', 'fs:LogosSmall'),
			array('logo_idx',   'fs:LogosIdx'),
		) as $k) {
			$filename = $this->get_dir($k[1]) . @$data[$k[0]];
			if (!empty($data[$k[0]]) && !is_file($filename)) {
				$data[$k[0]] = null;
				$data['synced_on'] = null;
			}
		}
		$send = json_encode($data);
		file_put_contents($baseDir . '/tournament.txt', $send);
	}
	
	private function sendBaseEvents($baseDir)
	{
		$this->lrEventsMap = array();
		$data = $this->db->array_query_assoc('
			SELECT id,sync_id,synced_on,
				(is_syncable=1 ' . ($this->rEventsIdFilter !== null ? '
				AND sync_id IN(' . implode(',', $this->rEventsIdFilter) . ')' : '') . ') sendable
			FROM reporting_ng_events
			WHERE tournament_id=' . $this->lTournamentId . ' AND sync_id IS NOT NULL'
		, 'id');
		$send = '';
		$this->lEventsIdInitFilter = array();
		foreach ($data as $row) {
			$this->lrEventsMap[$row['sync_id']] = $row['id'];
			if (!$row['sendable']) {
				continue;
			}
			$this->lEventsIdInitFilter[] = $row['id'];
			$send .= json_encode(array(
				'sync_id' => $row['sync_id'],
				'synced_on' => $row['synced_on'],
			)) . "\n";
		}
		file_put_contents($baseDir . '/events.txt', $send);
		
		if ($this->rEventsIdFilter !== null) {
			file_put_contents($baseDir . '/events_limit.txt', implode(',', $this->rEventsIdFilter));
		}
		
		$exevs = $this->db->array_query_assoc('
			SELECT sync_id FROM reporting_ng_events
			WHERE tournament_id=' . $this->lTournamentId . ' 
				AND is_syncable=0 AND sync_id IS NOT NULL
		', 'sync_id'); // add "AND sync_id IS NOT NULL", and deleted events will be undeleted, regardless of is_syncable
		if (0 != count($exevs)) {
			file_put_contents($baseDir . '/events_exclude.txt', implode(',', array_keys($exevs)));
		}
	}
	
	private function _sendBaseDays($baseDir)
	{
		$this->sendStd(
			$baseDir . '/days.txt', 
			'lrDaysMap', 
			'reporting_ng_days', 
			'1'
		);		
	}
	
	private function _sendEventMisc($baseDir)
	{
		mkdir($baseDir);
		// Event first/second hands
		$this->_sendEventMiscWinners($baseDir);
		$this->_sendEventMiscPayouts($baseDir);
		$this->_sendEventMiscPlayers($baseDir);
	}
	
	private function _sendEventMiscWinners($baseDir)
	{
		$this->sendStd(
			$baseDir . '/winners.txt', 
			'lrWinnersMap', 
			'reporting_ng_winners', 
			'1'
		);
	}
	
	private function _sendEventMiscPayouts($baseDir)
	{
		$this->sendStd(
			$baseDir . '/payouts.txt', 
			'lrPayoutsMap', 
			'reporting_ng_payouts', 
			'1'
		);
	}
	
	private function _sendEventMiscPlayers($baseDir)
	{
		$this->sendStd(
			$baseDir . '/players.txt', 
			'lrPlayersMap', 
			'reporting_ng_players', 
			'1'
		);
	}
	
	private function _sendReporting($baseDir)
	{
		mkdir($baseDir);
		$this->_sendReportingLog($baseDir);
		$this->_sendReportingSingleChips($baseDir);
		$this->_sendReportingSinglePhotos($baseDir);
	}
	
	private function _sendReportingLog($baseDir)
	{
		$this->sendStd(
			$baseDir . '/log.txt', 
			'lrLogMap', 
			'reporting_ng_log', 
			'1',
			array('sync_id', 'type')
		);
	}
	
	private function _sendReportingSingleChips($baseDir)
	{
		$this->sendTimed(
			$baseDir . '/single_chips.txt', 
			'reporting_ng_chips'
		);
	}
	
	private function _sendReportingSinglePhotos($baseDir)
	{
		$this->sendTimed(
			$baseDir . '/single_photos.txt', 
			'reporting_ng_photos'
		);
	}
	
	// rely on exids/inids passed earlier
	private function sendStd($responseFile, $lrMapName, $baseTable, $sendCondition, $sendFields = array('sync_id'))
	{
		$lrMap = &$this->$lrMapName;
		$lrMap = array();
		$send = '# ' . json_encode(array_merge($sendFields, array('synced_on'))) . "\n";
		if (0 != count($this->lEventsIdInitFilter)) {
			$data = $this->db->array_query_assoc('
				SELECT id,' . implode(',', $sendFields) . ',synced_on,
					(' . $sendCondition . ') sendable
				FROM ' . $baseTable . '
				WHERE tournament_id=' . $this->lTournamentId . ' 
					AND event_id IN(' . implode(',', $this->lEventsIdInitFilter) . ')
					AND sync_id IS NOT NULL
			');
			foreach ($data as $row) {
				$array = array();
				foreach ($sendFields as $k => $field) {
					$array[$k] = $row[$field];
				}
				$lrMap[implode('-', $array)] = $row['id'];
				if (!$row['sendable']) {
					continue;
				}
				$array[] = NULL == $row['synced_on']
					? NULL
					: intval($row['synced_on']);
				$send .= json_encode($array) . "\n";
			}
		}
		file_put_contents($responseFile, $send);
	}
	
	/*
	 * Fetch last hour. If synced table in 30 min, fetch only last 10 min
	 */
	private function sendTimed($responseFile, $baseTable)
	{
		$send = '# ' . json_encode(array('created_from')) . "\n";
		
		$requestSendFrom = time() - 2.5 * 24 * 3600;
		$timestampsFn = 'tmp/cache/lrep.' . $this->version . '.cache';
		$timestamps = array();
		if (file_exists($timestampsFn)) {
			$timestamps = @unserialize(file_get_contents($timestampsFn));
			if (!is_array($timestamps)) {
				$timestamps = array();
			}
		}
		
		if (isset($timestamps[$baseTable]) && (time() - $timestamps[$baseTable]) < 1800) {
			$requestSendFrom = time() - 1 * 600;
		} else {
			$timestamps[$baseTable] = time();
			file_put_contents($timestampsFn, serialize($timestamps));
		}
		
		if (isset($_GET['timed_full'])) {
			$requestSendFrom = 0;
		}
		
		$send .= $requestSendFrom . "\n";
		$this->lrTimed[$baseTable] = $requestSendFrom;
		
		file_put_contents($responseFile, $send);
	}
		
	/**
	 * Step 2 of syncTournament() - process request
	 */
	private function syncTournamentReplicateChanges($responseDir, $workDir)
	{
		if (!file_exists($workDir . '/response.tbz2.enc')) {
			echo "\tfailed to locate response file\n";
			return ;
		}
		echo '	received ' . round(filesize($workDir . '/response.tbz2.enc')/1024, 2) . "kb,\n";
		
		//echo mb_substr(file_get_contents($workDir . '/response.tbz2.enc'), 0, 400);
		
		$plain     = fopen($workDir . '/response.tbz2', 'wb');
		$encrypted = fopen($workDir . '/response.tbz2.enc', 'rb');
		if ($plain === false || $encrypted === false) {
			echo '	failed to open response file' . "\n";
			return ;
		}
		
		if (function_exists('mcrypt_get_iv_size')) {
			$iv_size = mcrypt_get_iv_size(MCRYPT_BLOWFISH, MCRYPT_MODE_ECB);
			$iv = mcrypt_create_iv($iv_size, MCRYPT_RAND);
			while (!feof($encrypted)) {
				$string = fread($encrypted, 4096);
				if (FALSE === $string) {
					echo "\tmsg: failed decrypt read\n";
					return ; // failed decrypt
				}
				$string = mcrypt_decrypt(MCRYPT_BLOWFISH, $this->pass, $string, MCRYPT_MODE_ECB, $iv);
				fwrite($plain, $string);
				$string = null;
			}
		} else {
				echo "\tmsg: mcrypt_get_iv_size unavailable\n";
				return ;
		}
		fclose($plain);
		fclose($encrypted);
		//unlink($workDir . '/response.tbz2.enc');
		
		$tar = new Archive_Tar($workDir . '/response.tbz2');
		$tar->extract($responseDir);
		unlink($workDir . '/response.tbz2');
		
		if (!file_exists($responseDir . '/note.txt')) {
			echo '	failed to read response' . "\n";
			echo file_get_contents($workDir . '/response.tbz2.enc');
			return;
		}
		echo "\tmsg: " . trim(file_get_contents($responseDir . '/note.txt')) . "\n";
		
		$this->_replicateBase($responseDir . '/00_base');
		moon::cache('memcache')->delete('reporting.tourns_uris');
		moon::cache('memcache')->delete('reporting.events_uris');
		$this->_replicateEventMisc($responseDir . '/05_event_misc');
		$this->_replicateReporting($responseDir . '/10_reports');
	
	}
	
	private function _replicateBase($responseDir)
	{
		$this->replicateBaseTournament($responseDir);
		$this->_replicateBaseEventsDeleted($responseDir);
		$this->_replicateBaseEvents($responseDir);
		$this->_replicateBaseDaysDeleted($responseDir);
		$this->_replicateBaseDays($responseDir);
	}
	
	private function replicateBaseTournament($responseDir)
	{
		if (!file_exists($responseDir . '/tournament.txt')) {
			return;
		}
		$data = file_get_contents($responseDir . '/tournament.txt');
		$data = json_decode($data, TRUE);
		if (empty($data)) {
			return;
		}
		$localTournament = $this->db->single_query_assoc('
			SELECT logo_big_bg,logo_mid,logo_idx,logo_small,duration,intro,place FROM reporting_ng_tournaments
			WHERE id=' . $this->lTournamentId . '
		');
		foreach (array('duration', 'intro', 'place') as $localPreferKey) {
			if (!in_array(@$localTournament[$localPreferKey], array(NULL,'','-','none','n/a','N/A'))) {
				unset ($data[$localPreferKey]);
			}
		}
		$data['synced_on'] = ($data['updated_on'] == NULL)
			? $data['created_on']
			: max($data['created_on'], $data['updated_on']);
		//unset($data['created_on']);
		$data['updated_on'] = time();
		
		foreach (array(
			array('logo_mid',   'fs:LogosMid'),
			array('logo_big_bg','fs:LogosBigBg'),
			array('logo_small', 'fs:LogosSmall'),
			array('logo_idx',   'fs:LogosIdx'),
		) as $k) {
			$img = $k[0];
			$dir = $k[1];
			if (@$localTournament[$img] != @$data[$img]) {
				$filename = $this->get_dir($dir) . @$localTournament[$img];
				if (!empty($localTournament[$img]) && is_file($filename)) {
					@unlink($filename);
				}
			}
			
			$filename = $responseDir . '/' . $img . '_' . $data[$img];
			if (is_file($filename)) {
				copy($filename, $this->get_dir($dir) . $data[$img]);
			}
		}

		$this->db->update($data, 'reporting_ng_tournaments', array(
			'id' => $this->lTournamentId
		));
		livereporting_adm_alt_log($this->lTournamentId, 0, 0, 'update', 'tournaments', $this->lTournamentId, '', -1);
	}
	
	private function _replicateBaseEvents($responseDir)
	{
		$this->replicateStd(
			'reporting_ng_events', 
			$responseDir . '/events.txt', 
			'lrEventsMap', 
			'events', 
			__METHOD__,
			'tgt_event'
		);
	}
	
	private function _replicateBaseEventsDeleted($responseDir)
	{
		$this->replicateStdDelete(
			'reporting_ng_events', 
			$responseDir . '/events_deleted.txt', 
			'lrEventsMap', 
			'events_d'
		);
	}
	
	private function _replicateBaseDays($responseDir)
	{
		$this->replicateStd(
			'reporting_ng_days', 
			$responseDir . '/days.txt', 
			'lrDaysMap', 
			'days', 
			__METHOD__
		);		
	}
	
	private function _replicateBaseDaysDeleted($responseDir)
	{
		$this->replicateStdDelete(
			'reporting_ng_days', 
			$responseDir . '/days_deleted.txt', 
			'lrDaysMap', 
			'days_d'
		);
	}
	
	private function _replicateEventMisc($responseDir)
	{
		$this->_replicateEventMiscWinnersDeleted($responseDir);
		$this->_replicateEventMiscWinners($responseDir);
		$this->_replicateEventMiscPayoutsDeleted($responseDir);
		$this->_replicateEventMiscPayouts($responseDir);
		$this->_replicateEventMiscPlayersDeleted($responseDir);
		$this->_replicateEventMiscPlayers($responseDir);
		$this->lrWinnersMap = null;
		$this->lrPayoutsMap = null;
	}
	
	private function _replicateEventMiscWinners($responseDir)
	{
		$this->replicateStd(
			'reporting_ng_winners', 
			$responseDir . '/winners.txt', 
			'lrWinnersMap', 
			'winners', 
			__METHOD__
		);
	}
	
	private function _replicateEventMiscWinnersDeleted($responseDir)
	{
		$this->replicateStdDelete(
			'reporting_ng_winners', 
			$responseDir . '/winners_deleted.txt', 
			'lrWinnersMap', 
			'winners_d'
		);
	}
	
	private function _replicateEventMiscPayouts($responseDir)
	{
		$this->replicateStd(
			'reporting_ng_payouts', 
			$responseDir . '/payouts.txt', 
			'lrPayoutsMap', 
			'payouts', 
			__METHOD__
		);
	}
	
	private function _replicateEventMiscPayoutsDeleted($responseDir)
	{
		$this->replicateStdDelete(
			'reporting_ng_payouts', 
			$responseDir . '/payouts_deleted.txt', 
			'lrPayoutsMap', 
			'payouts_d'
		);
	}
	
	private function _replicateEventMiscPlayers($responseDir)
	{
		$this->replicateStd(
			'reporting_ng_players', 
			$responseDir . '/players.txt', 
			'lrPlayersMap', 
			'players', 
			__METHOD__
		);
	}
	
	private function _replicateEventMiscPlayersDeleted($responseDir)
	{
		$this->replicateStdDelete(
			'reporting_ng_players', 
			$responseDir . '/players_deleted.txt', 
			'lrPlayersMap', 
			'players_d'
		);
	}
	
	private function _replicateReporting($responseDir) {
		$this->_replicateReportingLogDeleted($responseDir);
		$this->_replicateReportingLog($responseDir);
		$this->_replicateReportingSingleChips($responseDir);
		$this->_replicateReportingSinglePhotos($responseDir);
	}
	
	private function _replicateReportingLogDeleted($responseDir)
	{
		$this->replicateLogDelete($responseDir . '/log_deleted.txt', 'log_d');
	}
	
	private function _replicateReportingLog($responseDir)
	{
		$this->replicateLog(
			$responseDir . '/log.txt', 
			__METHOD__
		);
	}
	
	private function _replicateReportingSingleChips($responseDir)
	{
		$this->replicateTimed(
			'reporting_ng_chips',
			$responseDir . '/single_chips.txt', 
			'single_chips',
			__METHOD__
		);
	}
	
	private function _replicateReportingSinglePhotos($responseDir)
	{
		$this->replicateTimed(
			'reporting_ng_photos',
			$responseDir . '/single_photos.txt', 
			'single_photos',
			__METHOD__
		);
	}
	
	/**
	 * Events, days, winners, payouts, players
	 */
	private function replicateStd($baseTable, $responseFile, $lrMapName, $msgCntPrefix, $msgMethod, $hint = null)
	{
		if ('tgt_event' != $hint) {
			$this->replicateHelperDeleteLostByEvent($baseTable, $msgCntPrefix);
		}
		
		if (!file_exists($responseFile)) {
			return;
		}
		$remoteData = fopen($responseFile, 'rb');
		$remoteDataCnt = 0;
		
		while (($row = fgets($remoteData)) !== FALSE) {
			$row = json_decode($row, true);
			$remoteDataCnt++;
			
			$this->replicateStdRow($row, $baseTable, $lrMapName, $msgMethod);
		}
		
		fclose($remoteData);
		echo "\t" . $msgCntPrefix . '(' . $remoteDataCnt . ');' . "\n";
	}

	private function replicateStdRow($row, $baseTable, $lrMapName, $msgMethod)
	{
		// "synced" timestamp
		$row['synced_on'] = ($row['updated_on'] == NULL)
			? $row['created_on']
			: max($row['created_on'], $row['updated_on']);

		// retranslate remote ids to local ids
		$row['tournament_id'] = $this->lTournamentId;
		if (isset($row['event_id'])) { // $hint != 'tgt_event'
			if (!isset($this->lrEventsMap[$row['event_id']])) {
				moon::error('repsync ' . $msgMethod . '() no event');
				return;
			}
			$row['event_id'] = $this->lrEventsMap[$row['event_id']];
		}
		if (isset($row['player_id'])) { // exists & != null
			if (!isset($this->lrPlayersMap[$row['player_id']])) {
				moon::error('repsync ' . $msgMethod . '() no player');
				return;
			}
			$row['player_id'] = $this->lrPlayersMap[$row['player_id']];
		}
		$row['sync_id'] = $row['id'];
		unset($row['id']);
		$syncId = $row['sync_id'];

		// db
		$lrMap = &$this->$lrMapName;
		if (!empty($lrMap[$syncId])) {
			// update
			$this->db->update($row, $baseTable, array(
				'id' => $lrMap[$syncId]
			));
			livereporting_adm_alt_log(
				$this->lTournamentId, isset($row['event_id']) ? $row['event_id'] : 0, 0, 
				'update', str_replace('reporting_ng_', '', $baseTable), $lrMap[$syncId], '', -1);

			if ($this->db->affected_rows() == 0) {
				moon::error($msgMethod . '() possibly failed update');
			}
		} else {
			// insert
			//$row['updated_on'] = NULL;
			$cnt = $this->db->replace($row, $baseTable);
			$id = $this->db->insert_id();
			if ($id) {
				$lrMap[$syncId] = $id;
				livereporting_adm_alt_log(
					$this->lTournamentId, isset($row['event_id']) ? $row['event_id'] : 0, 0, 
					'insert', str_replace('reporting_ng_', '', $baseTable), $id, '', -1);
			} else {
				moon::error($msgMethod . '() failed insert' . "\n");
			}
			if ($cnt > 1) {
				moon::error($msgMethod . '() warn insert (dupe handled unsafely)' . "\n");
			}
		}		
	}
	
	/**
	 * Log (sub_*, tags)
	 */
	private function replicateLog($responseFile, $msgMethod)
	{
		$this->replicateHelperDeleteLostByEvent('reporting_ng_log', 'log');
		
		if (!file_exists($responseFile)) {
			return;
		}
		
		//$this->db->query('SET autocommit=0');
		$remoteData = fopen($responseFile, 'rb');
		$remoteDataCnt = 0;
		while (($row = fgets($remoteData)) !== FALSE) {
			$row = json_decode($row, true);
			$remoteDataCnt++;
			
			$this->replicateLogRow($row, $msgMethod);
			//$this->db->query('COMMIT');
		}
		//$this->db->query('SET autocommit=1');

		fclose($remoteData);
		echo "\t" . 'log(' . $remoteDataCnt . ');' . "\n";
	}
	
	private function replicateLogRow($row, $msgMethod)
	{
		$logRow = &$row['log'];
		$subRow = &$row['sub'];
		$lrMap = &$this->lrLogMap;

		// "synced" timestamp
		$logRow['synced_on'] = ($logRow['updated_on'] == NULL)
			? $logRow['created_on']
			: max($logRow['created_on'], $logRow['updated_on']);		
		
		// retranslate remote ids to local ids ("log" row) (part 1)
		$logRow['tournament_id'] = $this->lTournamentId;
		if (!isset($this->lrEventsMap[$logRow['event_id']])) {
			moon::error('repsync ' . $msgMethod . '() no event');
			return;
		}
		$logRow['event_id'] = $this->lrEventsMap[$logRow['event_id']];
		if (!isset($this->lrDaysMap[$logRow['day_id']])) {
			moon::error('repsync ' . $msgMethod . '() no day');
			return;
		}
		$logRow['day_id'] = $this->lrDaysMap[$logRow['day_id']];
		$logRow['sync_id'] = $logRow['id'];
		unset($logRow['id']);
		$syncId = $logRow['sync_id'] . '-' . $logRow['type'];
		if (empty($this->lAutopublish) && $logRow['is_hidden'] == 0) {
			$logRow['is_hidden'] = 1;
		}
		// $logRow['updated_on'] must be reset later

		// special case: update to already-translated entry
		if (!empty($lrMap[$syncId])) {			
			$exists = $this->db->single_query_assoc('
				SELECT id,updated_on FROM reporting_ng_log
				WHERE tournament_id="' . intval($logRow['tournament_id']) . '"
					AND sync_id="' . intval($logRow['sync_id']) . '" 
					AND type="' . $this->db->escape($logRow['type']) . '"
			');
			// bump translated posts to prevent endless downloading of updates to translated items
			if (!is_null($exists['updated_on'])) {
				$this->db->update(array(
					'synced_on' => max($logRow['created_on'], $logRow['updated_on'])
				), 'reporting_ng_log', array(
					'tournament_id' => $logRow['tournament_id'],
					'sync_id' => $logRow['sync_id'],
					'type'=> $logRow['type']
				));
				livereporting_adm_alt_log($this->lTournamentId, $logRow['event_id'], $logRow['day_id'], 'update', 'log', $exists['id'], $logRow['type'] . ' sync-ts bump', -1);
				// "dirty"
				if ($logRow['type'] == 'chips') {
					$this->replicateLogRowSpecialBumpChips($row, $msgMethod);
				}
				// @todo probably should bump publish dates, post rounds and some other non-translatable info
				// @todo force hiding rounds (no translation), if hidden on master
				return ;
			}
			unset($exists);
		}

		// some definitions
		// "dirty" (semi)
		$perPostTags = false;
		switch ($logRow['type']) {
			case 'post':
			case 'chips':
				$perPostTags = true;
				$tagsType = $logRow['type'];
				break;
		}
		switch ($logRow['type']) {
			case 'chips':
			case 'photos':
				$subTable = 'reporting_ng_sub_' . $logRow['type'];
				break;
			default:
				$subTable = 'reporting_ng_sub_' . $logRow['type'] . 's';
				break;
		}			

		// retranslate remote ids to local ids ("log", "sub", "ext" rows) (m.b. unreliable)
		// "dirty"
		$logRow['updated_on'] = NULL; // for when was remotely updated (if was locally updated, one should not even be here)
		switch ($logRow['type']) {
			case 'post': // round ids
				$this->replicateLogRowRetranslatePost($logRow, $subRow, $row);
				break;
			case 'chips': // players ids
				$this->replicateLogRowRetranslateChips($logRow, $subRow, $row);
				break;
		}

		// replication (log, sub)
		if (!empty($lrMap[$syncId])) { 
			// update
			$logRow['id'] = $lrMap[$syncId];
			$this->db->update($subRow, $subTable, array(
				'id' => $logRow['id']
			));
			livereporting_adm_alt_log($this->lTournamentId, $logRow['event_id'], $logRow['day_id'], 'update', str_replace('reporting_ng_', '', $subTable), $logRow['id'], '', -1);
			// secondary update
			$this->db->update($logRow, 'reporting_ng_log', array(
				'id'  => $logRow['id'],
				'type'=> $logRow['type']
			));
			livereporting_adm_alt_log($this->lTournamentId, $logRow['event_id'], $logRow['day_id'], 'update', 'log', $logRow['id'], $logRow['type'], -1);
			if ($this->db->affected_rows() == 0) {
				moon::error($msgMethod . '() possibly failed update' . "\n");
			}
		} else {
			// insert
			$cnt = $this->db->replace($subRow, $subTable);
			$logRow['id'] = $this->db->insert_id();
			if ($logRow['id']) {
				livereporting_adm_alt_log($this->lTournamentId, $logRow['event_id'], $logRow['day_id'], 'insert', str_replace('reporting_ng_', '', $subTable), $logRow['id'], '', -1);
				$lrMap[$syncId] = $logRow['id'];
				// secondary insert
				$this->db->insert($logRow, 'reporting_ng_log');
				livereporting_adm_alt_log($this->lTournamentId, $logRow['event_id'], $logRow['day_id'], 'insert', 'log', $logRow['id'], $logRow['type'], -1);
			} else {
				moon::error($msgMethod . '() failed insert' . "\n");
			}
			if ($cnt > 1) {
				moon::error($msgMethod . '() warn insert (dupe handled unsafely)' . "\n");
			}
		}
	
		// replication (ext)
		// "dirty"
		switch ($logRow['type']) {
			case 'photos':
				$this->replicateLogRowExtPhotos($logRow, $row);		
				break;
			case 'chips':
				$this->replicateLogRowExtChips($logRow, $row);
				break;
		}

		// replication (per-post tags)
		if ($perPostTags) {
			livereporting_adm_alt_log($this->lTournamentId, $logRow['event_id'], $logRow['day_id'], 'update', 'tags', $logRow['id'], 'repl ' . $tagsType . ' imprt all', -1);
			$this->db->query('
				DELETE FROM reporting_ng_tags
				WHERE id="' . $logRow['id'] . '" AND type="' . $logRow['type'] . '"
			');
			foreach ($row['tags'] as $tag) {
				$this->db->insert(array(
					'id' => $logRow['id'],
					'type' => $tagsType,
					'tournament_id' => $logRow['tournament_id'],
					'event_id' => $logRow['event_id'],
					'day_id' => $logRow['day_id'],
					'tag' => $tag['tag'],
					'is_hidden' => 1
				), 'reporting_ng_tags');
			}
		}
		}

	private function replicateLogRowRetranslatePost(&$logRow, &$subRow, &$row)
	{
		if (empty($subRow['round_id'])) {
			return;
		}
		$contents = unserialize($logRow['contents']);
		$subRow['round_id'] = $this->replicateHelperGetRoundId($contents['round']['round'], $logRow['event_id']);
		if ($subRow['round_id']) {
			$contents['round']['id'] = $subRow['round_id'];
		} else {
			$contents['round'] = null;
		}
		$logRow['contents'] = serialize($contents);
	}
	
	private function replicateLogRowRetranslateChips(&$logRow, &$subRow, &$row)
	{
		$contents = unserialize($logRow['contents']);
		foreach ($contents['chips'] as $k => $chip) {
			$contents['chips'][$k]['id'] = isset($this->lrPlayersMap[$chip['id']])
				? $this->lrPlayersMap[$chip['id']]
				: NULL;
		}
		$logRow['contents'] = serialize($contents);

		$subRow['chips'] = explode("\n", $subRow['chips']);
		foreach ($subRow['chips'] as $k => $chip) {
			$chip = explode(',', $chip);
			if ($chip[0] != '') {
				$chip[0] = isset($this->lrPlayersMap[$chip[0]])
					? $this->lrPlayersMap[$chip[0]]
					: NULL;
			}
			$subRow['chips'][$k] = implode(',', $chip);
		}
		$subRow['chips'] = implode("\n", $subRow['chips']);

		foreach ($row['ext'] as $k => $chip) {
			$row['ext'][$k]['player_id'] = isset($this->lrPlayersMap[$chip['player_id']])
				? $this->lrPlayersMap[$chip['player_id']]
				: NULL;
		}
	}
	
	private function replicateLogRowExtPhotos(&$logRow, &$row)
	{
		// delete old photos and corresponding tags
		/* $oldPhotoIds = array_keys($this->db->array_query_assoc('
			SELECT id FROM reporting_ng_photos
			WHERE import_id=' . $logRow['id'] . '
		', 'id')); */
		$this->db->query('
			DELETE FROM reporting_ng_photos
			WHERE import_id=' . $logRow['id'] . '
		');
		/* if (count($oldPhotoIds) > 0) {
			$this->db->query('
				DELETE FROM reporting_ng_tags
				WHERE id IN (' . implode(',', $oldPhotoIds) . ') AND type="photo"
			');
		}
		// rearrange tags
		$imgTags = array();
		foreach ($row['tags'] as $tag) {
			$imgTags[$tag['id']][] = $tag['tag'];
		}
		$row['tags'] = $imgTags; */
		// insert new
		foreach ($row['ext'] as $photo) {
			/* $oldId = $photo['id']; */
			unset($photo['id']);
			$photo += array(
				'import_id' => $logRow['id'],
				'day_id'    => $logRow['day_id'],
				'event_id'  => $logRow['event_id'],
				'created_on' => $logRow['created_on'],
				'is_hidden' => empty($this->lAutopublish)
			);
			$id = $this->db->insert($photo, 'reporting_ng_photos', 'id');
			/* if ($id && isset($row['tags'][$oldId])) {
			foreach ($row['tags'][$oldId] as $tag) {
				$this->db->insert(array(
					'id' => $id,
					'type' => 'photo',
					'tournament_id' => $logRow['tournament_id'],
					'event_id' => $logRow['event_id'],
					'day_id' => $logRow['day_id'],
					'tag' => $tag,
					'is_hidden' => 1
				), $this->table('Tags'));
			}} */
		}
		livereporting_adm_alt_log($this->lTournamentId, $logRow['event_id'], $logRow['day_id'], 'update', 'photos', $logRow['id'], 'repl imprt all', -1);
	}
	
	private function replicateLogRowExtChips(&$logRow, &$row)
	{
		// delete old chips
		$this->db->query('
			DELETE FROM reporting_ng_chips
			WHERE import_id=' . $logRow['id'] . '
		');
		// insert new
		foreach ($row['ext'] as $chip) {
			if (NULL == $chip['player_id']) {
				echo '*';
				continue ;
			}
			unset($chip['id']);
			$chip += array(
				'import_id'  => $logRow['id'],
				'day_id'     => $logRow['day_id'],
				'created_on' => $logRow['created_on'],
				'is_hidden' => 0
			);
			$this->db->insert($chip, 'reporting_ng_chips');
		}
		livereporting_adm_alt_log($this->lTournamentId, $logRow['event_id'], $logRow['day_id'], 'update', 'chips', $logRow['id'], 'repl imprt all', -1);
	}
	
	private function replicateLogRowSpecialBumpChips(&$row, $msgMethod)
	{
		$logRow = &$row['log'];
		$subRow = &$row['sub'];
		$lrMap = &$this->lrLogMap;
		$subTable = 'reporting_ng_sub_chips';
		$syncId = $logRow['sync_id'] . '-chips';
		
		$this->replicateLogRowRetranslateChips($logRow, $subRow, $row);
		
		$logRow['id'] = $lrMap[$syncId];
		
		// primary update
		$this->db->update(array(
			'chips' => $subRow['chips']
		), $subTable, array(
			'id' => $logRow['id']
		));
		livereporting_adm_alt_log($this->lTournamentId, $logRow['event_id'], $logRow['day_id'], 'update', 'sub_chips', $logRow['id'], 'numbers bump', -1);
		
		// secondary update
		$logRowOld = $this->db->single_query_assoc('
			SELECT contents FROM reporting_ng_log
			WHERE id=' . intval($logRow['id']) . ' AND type="chips"
		');
		if (isset($logRowOld)) {
			// replace ['chips'] form old row with new row's chips
			$logRow['contents']     = unserialize($logRow['contents']);
			$logRowOld['contents'] = unserialize($logRowOld['contents']);
			$logRowOld['contents']['chips'] = $logRow['contents']['chips'];
			$logRow['contents']     = serialize($logRowOld['contents']);
			$this->db->update(array(
				'contents' => $logRow['contents']
			), 'reporting_ng_log', array(
				'id'  => $logRow['id'],
				'type'=> 'chips'
			));
			livereporting_adm_alt_log($this->lTournamentId, $logRow['event_id'], $logRow['day_id'], 'update', 'log', $logRow['id'], 'chips numbers bump', -1);
			if ($this->db->affected_rows() == 0) {
				moon::error($msgMethod . '() possibly failed bump update' . "\n");
			}
		}

		$this->replicateLogRowExtChips($logRow, $row);
	}

	private function replicateHelperGetRoundId($round, $eventId)
	{
		static $rounds = array();
		$k = intval($round) . '-' . intval($eventId);
		if (!isset($rounds[$k])) {
			$round = $this->db->single_query_assoc('
				SELECT r.id FROM reporting_ng_log l
				INNER JOIN reporting_ng_sub_rounds r
					ON r.id=l.id
				WHERE r.round=' . intval($round) . ' AND l.type="round"
					AND l.event_id=' . intval($eventId) . '
				LIMIT 1
			');
			$rounds[$k] = isset($round['id'])
				? $round['id']
				: null;
		}
		return $rounds[$k];
	}
	
	
	private function replicateHelperDeleteLostByEvent($baseTable, $msgCntPrefix)
	{
		$evIds = array_keys($this->db->array_query_assoc('
			SELECT id FROM reporting_ng_events
			WHERE tournament_id=' . $this->lTournamentId . '	
		', 'id'));
		if (0 != count($evIds)) {
			$this->db->query('
				DELETE FROM ' . $baseTable . '
				WHERE tournament_id=' . $this->lTournamentId . ' 
					AND event_id NOT IN(' . implode(',', $evIds) . ')
			');
			$cnt = $this->db->affected_rows();
			if ($cnt) {
				livereporting_adm_alt_log($this->lTournamentId, 0, 0, 'delete', str_replace('reporting_ng_', '', $baseTable), 0, 'del lost in ev ' . $cnt, -1);
				echo "\t" . $msgCntPrefix . '_deleted_lost(' . $cnt . ');' . "\n";
			}
		}
	}
	
	/**
	 * Events, days, winners, payouts, players
	 */
	private function replicateStdDelete($baseTable, $responseFile, $lrMapName, $msgCntPrefix)
	{
		if (!file_exists($responseFile)) {
			return;
		}
		$data = file_get_contents($responseFile);
		$data = explode(',', $data);
		if (empty($data)) {
			return;
		}
		
		// only delete what really belongs to the tournament (tournament_id=?, from lrDaysMap in this case)
		$lrMap = &$this->$lrMapName;
		foreach ($data as $row) {
			if (isset($lrMap[$row])) {
				$id = $lrMap[$row];
				unset($lrMap[$row]);
				$this->db->query('
					DELETE FROM ' . $baseTable . '
					WHERE id=' . intval($id) . '
				');
				livereporting_adm_alt_log($this->lTournamentId, 0, 0, 'delete', str_replace('reporting_ng_', '', $baseTable), $id, '', -1);
			}
		}
		
		echo "\t" . $msgCntPrefix . '(' . count($data) . ');' . "\n";
	}
	
	/**
	 * Log
	 */
	private function replicateLogDelete($responseFile, $msgCntPrefix)
	{
		if (!file_exists($responseFile)) {
			return;
		}
		$data = file_get_contents($responseFile);
		if (empty($data)) {
			return;
		}
		$data = explode(',', $data);
		
		// "dirty"
		// only delete what really belongs to the tournament (tournament_id=?, from lrDaysMap in this case)
		// @todo maybe check if was translated, and then delete conditionally
		foreach ($data as $row) {
			if (isset($this->lrLogMap[$row])) {
				list($syncId, $type) = explode('-', $row);
				$id = $this->lrLogMap[$row];
				unset($this->lrLogMap[$row]);
				
				switch ($type) {
					case 'chips':
					case 'photos':
						$subTable = 'reporting_ng_sub_' . $type;
						break;
					default:
						$subTable = 'reporting_ng_sub_' . $type . 's';
						break;
				}
				
				$exists = $this->db->single_query_assoc('
					SELECT tournament_id, event_id FROM reporting_ng_log
					WHERE id=' . intval($id) . ' 
					  AND type="' . $this->db->escape($type) . '"
				');
				$exists += array(
					'tournament_id' => 0,
					'event_id' => 0,
				);
				// log, sub
				$this->db->query('
					DELETE FROM reporting_ng_log
					WHERE id=' . intval($id) . ' AND type="' . $this->db->escape($type) . '"
				');
				livereporting_adm_alt_log($this->lTournamentId, $exists['tournament_id'], $exists['event_id'], 'delete', 'log', $id, $type, -1);
				$this->db->query('
					DELETE FROM ' . $subTable . '
					WHERE id=' . intval($id) . '
				');
				livereporting_adm_alt_log($this->lTournamentId, $exists['tournament_id'], $exists['event_id'], 'delete', str_replace('reporting_ng_', '', $subTable), $id, '', -1);
				
				// ext, tags (if any)
				if ('post' == $type) {
					$this->db->query('
						DELETE FROM reporting_ng_tags
						WHERE id=' . intval($id) . ' AND type="post"
					');
					livereporting_adm_alt_log($this->lTournamentId, $exists['tournament_id'], $exists['event_id'], 'delete', 'tags', $id, 'post all', -1);
				} elseif ('photos' == $type) {
					/*$oldPhotoIds = array_keys($this->db->array_query_assoc('
						SELECT id FROM reporting_ng_photos
						WHERE import_id=' . intval($id) . '
					', 'id'));*/
					$this->db->query('
						DELETE FROM reporting_ng_photos
						WHERE import_id=' . intval($id) . '
					');
					livereporting_adm_alt_log($this->lTournamentId, $exists['tournament_id'], $exists['event_id'], 'delete', 'tags', $id, 'photos imprt all', -1);
					/*if (count($oldPhotoIds) > 0) {
						$this->db->query('
							DELETE FROM reporting_ng_tags
							WHERE id IN (' . implode(',', $oldPhotoIds) . ') AND type="photo"
						');
					}*/
				} elseif ('chips' == $type) {
					$this->db->query('
						DELETE FROM reporting_ng_chips
						WHERE import_id=' . intval($id) . '
					');
					livereporting_adm_alt_log($this->lTournamentId, $exists['tournament_id'], $exists['event_id'], 'delete', 'chips', $id, 'imprt', -1);
				}
			}
		}
		
		// livereporting_ng_event.notifyLogEntryDeleted() may possibly be here, but better not
		echo "\t" . $msgCntPrefix . '(' . count($data) . ');' . "\n";
	}
	
	private function replicateTimed($baseTable, $responseFile, $msgCntPrefix, $msgMethod)
	{
		if (!file_exists($responseFile)) {
			return;
		}
		$remoteData = fopen($responseFile, 'rb');
		$remoteDataCnt = 0;
		$requestedDataSince = $this->lrTimed[$baseTable];
		
		if (0 != count($this->lEventsIdInitFilter)) {
			// synced days
			$days = $this->db->array_query_assoc('
				SELECT id
				FROM reporting_ng_days
				WHERE tournament_id=' . $this->lTournamentId . ' 
					AND event_id IN(' . implode(',', $this->lEventsIdInitFilter) . ')
					AND sync_id IS NOT NULL
			', 'id');
			$days = array_keys($days);
			if (0 != count($days)) {
				// delete data from latest timeslice, to replace with new
				$this->db->query('
					DELETE FROM ' . $baseTable . '
					WHERE created_on>' . intval($requestedDataSince) . ' 
					  AND day_id IN(' . implode(',', $days) . ')
					  AND import_id IS NULL
				');
				$delCnt = $this->db->affected_rows();
				if ($delCnt) {
					livereporting_adm_alt_log($this->lTournamentId, 0, 0, 'delete', str_replace('reporting_ng_', '', $baseTable), 0, $delCnt . ' from ' . $requestedDataSince, -1);
				}
			}
		}
			
		while (($row = fgets($remoteData)) !== FALSE) {
			$row = json_decode($row, true);
			$remoteDataCnt++;
			
			$this->replicateTimedRow($row, $baseTable, $msgMethod);
		}
		
		fclose($remoteData);
		echo "\t" . $msgCntPrefix . '(' . $remoteDataCnt . ');' . "\n";
		if ($remoteDataCnt) {
			livereporting_adm_alt_log($this->lTournamentId, 0, 0, 'insert', str_replace('reporting_ng_', '', $baseTable), 0, $remoteDataCnt . ' from ' . $requestedDataSince, -1);
		}
	}
	
	private function replicateTimedRow($row, $baseTable, $msgMethod)
	{
		$row['day_id'] = $this->lrDaysMap[$row['day_id']];
		$row['import_id'] = NULL;
		if (isset($row['event_id'])) {
			$row['event_id'] = $this->lrEventsMap[$row['event_id']];
		}
		if (isset($row['player_id'])) {
			$row['player_id'] = isset($this->lrPlayersMap[$row['player_id']])
				? $this->lrPlayersMap[$row['player_id']]
				: NULL;
		}
		
		// insert
		$this->db->insert($row, $baseTable);
		$id = $this->db->insert_id();
		if (!$id) {
			moon::error($msgMethod . '() failed insert' . "\n");
		}
	}
	
	private function rmdir_rec($dir)
	{
		$files = glob($dir . '*');
		foreach ($files as $file) {
			if (is_dir($file)) {
				$this->rmdir_rec( $file . '/' );
			} else {
				unlink( $file );
			}
		}
		rmdir($dir);
	}
	
	public function cleanupFailedSyncs()
	{
		$files = glob('tmp/reporting-sync-*', GLOB_ONLYDIR);
		foreach ($files as $file) {
			if (time() - filemtime($file) < 5*24*3600) {
				continue;
			}
			$this->rmdir_rec($file . '/');
			moon::error()->error('Reporting: removed failed sync dir "' . $file . '/"');
		}
	}

	private function signFile($privKey, $file) 
	{
		$digest = hash_file('sha1', $file, true);
		$asn1  = chr(0x30).chr(0x21); // SEQUENCE, 33
		$asn1 .= chr(0x30).chr(0x09); // SEQUENCE, 9
		$asn1 .= chr(0x06).chr(0x05); // OBJECT IDENTIFIER, 5
		$asn1 .= chr(0x2b).chr(0x0e).chr(0x03).chr(0x02).chr(0x1a); // 1.3.14.3.2.26 (SHA1)
		$asn1 .= chr(0x05).chr(0x00); // NULL
		$asn1 .= chr(0x04).chr(0x14); // OCTET STRING, 20
		$asn1 .= $digest;

		openssl_private_encrypt($asn1, $signature, $privKey);
		return $signature;
	}

	public function getTodoTasks()
	{
		$tasks = array();
		$tournaments = $this->db->array_query_assoc('
			SELECT id, name FROM reporting_ng_tournaments
			WHERE is_syncable=1 AND is_live>=0 AND state=2 AND updated_on<' . (time() - 7*24*3600) . '
		');
		foreach ($tournaments as $tournament) {
			$tasks[] = array(
				'title' => $tournament['name'],
				'uri' => 'livereporting.tour_list#edit|' . $tournament['id']
			);
		}
		return $tasks;
	}
}
