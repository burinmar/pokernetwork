<?php

class promo extends moon_com
{
	function synthEvents($argv)
	{
		if (!isset($argv[1])) {
			$this->redirect('promos#' . $argv[0]);
		}

		$promoAlias = $argv[0];
		$this->set_var('promo-alias', $promoAlias);

		$sub = isset($argv[1])
			? $argv[1]
			: NULL;
		switch ($sub) {
		case '':
			$this->set_var('render', 'index');
			break;
		case 'schedule':
			if (isset($_GET['archive'])/* && 'schedule' == $sub*/) {
				$this->set_var('render', $sub . '-archive');
				break;
			}
			// no break
		case 'results':
			if (isset($_GET['s']) && 'results' == $sub) {
				$this->set_var('render', $sub . '-search');
				$this->set_var('search', $_GET['s']);
				break;
			}
			if (isset($_GET['filter']) && 'results' == $sub) {
				$this->set_var('filter', $_GET['filter']);
			}
			if (isset($argv[2]) && $argv[2] == 'events' && 'results' == $sub) {
				$this->set_var('render', $sub . '-event');
				$this->set_var('event-id', $argv[3]);
				break;
			}
			// no break
		case 'terms-and-conditions':
			$this->set_var('render', $sub);
			break;
		default:
			$this->set_var('render', 'custom-page');
			$this->set_var('sub-alias', $sub);
		}

		$this->set_var('page', isset($_GET['page'])
			? intval($_GET['page'])
			: 1);
		$this->use_page('Main');
	}

	function main($argv)
	{
		if (NULL == ($promo = $this->getPromo($argv['promo-alias']))) {
			moon::page()->page404();
		}
		moon::page()->set_local('nobanners', 1);
		switch ($argv['render']) {
		case 'index':
			return $this->renderIndex($argv);
		case 'schedule':
			return $this->renderSchedule($argv);
		case 'schedule-archive':
			return $this->renderScheduleArchive($argv);
		case 'results':
			return $this->renderResults($argv);
		case 'results-event':
			return $this->renderResultsEvent($argv);
		case 'results-search':
			return $this->renderResultsSearch($argv);
		case 'terms-and-conditions':
			return $this->renderTermsConditions($argv);
		case 'custom-page':
			return $this->renderCustomPage($argv);
		default:
			moon::page()->page404();
		}
	}

	private function renderIndex($argv)
	{
		$tpl = $this->load_template();
		$text = moon::shared('text');

		$entry = $this->getPromo($argv['promo-alias']);
		$this->partialRenderCommon($mainArgv, $entry, 'index');

		$promoTz = moon::locale()->timezone($entry['timezone']);
		list(, $tzShift) = $this->userTzData($promoTz[0]);

		if (is_object( $rtf = $this->object('rtf') )) {
			$rtf->setInstance($this->get_var('rtf'));
			list(, $entry['descr_prize']) = $rtf->parseText('', $entry['descr_prize']);
		}

		$contentArgv = array(
			'description' => $this->parseRoomVars($entry['descr_prize'], $entry['room']),
			'leaderboard' => '',
			'schedule' => $this->partialRenderIndexEvents($entry),
			'list.steps' => $this->partialRenderSteps($entry),
			'prize' => htmlspecialchars($entry['prize']),
			'prize_small' => strlen($entry['prize']) > 7,
			'qualification' => $this->parseRoomVars($entry['descr_qualify'], $entry['room']),
			'when' => $text->dateRange(
				strtotime($entry['date_start'] . ' 00:00:00 +0000') + $tzShift,
				$entry['date_end'] === null ? null : strtotime($entry['date_end']   . ' 00:00:00 +0000') + $tzShift/* + 24*3600 - 1*/,
				shared_text::dataRangeShorter
			),
			'skrill' => in_array(_SITE_ID_, array('de', 'fr', 'it', 'es', 'gr', 'pl', 'ro', 'ru', 'bg'))
				? str_replace('{site_id}', _SITE_ID_, 'https://www.skrill.com/{site_id}/affiliates-partners/poker-news-fixed-{site_id}')
				: 'https://www.skrill.com/affiliates-partners/poker-news-fixed'
		);

		if (NULL != ($lbChunks = $this->parseResults($entry['lb_data'], $entry['lb_columns']))) {
			$lbChunks['data'] = array_slice($lbChunks['data'], 0, 10);
			if (0 != count($lbChunks['data'])
				&& isset($lbChunks['data'][0][$lbChunks['idx.player']])
				&& isset($lbChunks['data'][0][$lbChunks['idx.points']])
			) {
				$lbArgv = array(
					'url.results' => $this->linkas('promos#' . $entry['alias'] . '/results'),
					'rows' => ''
				);
				foreach ($lbChunks['data'] as $k => $row) {
					$lbArgv['rows'] .= $tpl->parse('index:leaderboard.row', array(
						'place' => $k+1,
						'nick'   => htmlspecialchars($row[$lbChunks['idx.player']]),
						'points' => htmlspecialchars($row[$lbChunks['idx.points']])
					));
				}
				$contentArgv['leaderboard'] = $tpl->parse('index:leaderboard', $lbArgv);
			}
		}

		$mainArgv = array_merge($mainArgv, array(
			'content' => $tpl->parse('index:content', $contentArgv + $mainArgv)
		));
		return $tpl->parse($this->altTpl('all:main', $entry), $mainArgv);
	}

	private function partialRenderIndexEvents($entry)
	{
		list($events, $evtRemain, $pwdCol) = $this->getEventsIndex($entry['id']);
		if (0 == count($events))
			return ;

		$locale = moon::locale();
		$tpl = $this->load_template();
		$currencies = array('USD' => '$', 'EUR' => '€');

		$promoTz = $locale->timezone($entry['timezone']);
		list($tzName, $tzShift) = $this->userTzData($promoTz[0]);

		$result = '';
		$groupArgv = array(
			'pwdCol' => $pwdCol,
			'tzName' => $tzName,
			'events' => '',
			'events_left' => $evtRemain,
		);
		foreach ($events as $event) {
			$eventArgv = array(
				'id' => $event['id'],
				'date' => $locale->gmdatef($event['start_date'] + $tzShift, 'Date'),
				'time' => gmdate('H:i', $event['start_date'] + $tzShift),
				'title' => htmlspecialchars($event['title']),
				'pwd' => '',
				'pwdCol' => $pwdCol,
				'entryFee' => round($event['entry_fee'], 2),
				'fee'      => round($event['fee'], 2),
				'currency' => isset($entry['room']['currency']) && isset($currencies[$entry['room']['currency']])
					? $currencies[$entry['room']['currency']]
					: $entry['room']['currency'],
				'url' => $event['has_results']
					? $this->linkas('promos#' . $entry['alias'] . '/results/events/', $event['id'])
					: '',
				'live' => time() >= $event['start_date'] && time() < $event['start_date'] + 86400,
			);
			if ($event['pwd_date'] == 0 || $event['pwd_date'] < time()) {
				$eventArgv['pwd'] = htmlspecialchars($event['pwd']);
			} elseif (!empty($event['pwd'])) {
				$eventArgv['pwd'] = $tpl->parse('index:schedule.pwd_pending', array(
					'date' => $locale->gmdatef($event['pwd_date'], 'freerollTime', $tzShift)
				));
			}

			$groupArgv['events'] .= $tpl->parse('index:month.event', $eventArgv);
		}
		$result .= $tpl->parse('index:schedule', $groupArgv);
		return $result;
	}

	private function partialRenderSteps($entry)
	{
		$webDir = $this->get_dir('web:Css') . 'default/';

		$tpl = $this->load_template();
		$stepsDescr = explode("\n", $entry['descr_steps'] . "\n\n");
		$stepsImages = explode("\n", $entry['descr_steps_images'] . "\n\n");
		$return = '';

		for ($i = 0; $i < 3; $i++) {
			if (!$stepsImages[$i])
				continue;
			$return .= $tpl->parse('index:steps.item', array(
				'img' => $webDir . rawurlencode($stepsImages[$i]),
				'title' => htmlspecialchars($stepsDescr[$i]),
			));
		}

		return $this->parseRoomVars($return, $entry['room']);
	}

	private function getEventsIndex($promoId)
	{
		$events = array();
		$pwdCol = false;
		$events_ = array();
		foreach (array(
			array('start_date>=FROM_UNIXTIME(' . (floor((time()-86400)/60)*60) . ')', 'start_date'),
			array('start_date<FROM_UNIXTIME(' . (floor((time()-86400)/60)*60) . ')', 'start_date DESC')
		) as $displayingArchive => $search) {
			list ($timeWhere, $order) = $search;
			$events_ = $this->db->array_query_assoc('
				SELECT SQL_CALC_FOUND_ROWS id, title, UNIX_TIMESTAMP(start_date) start_date,
					pwd, UNIX_TIMESTAMP(pwd_date) pwd_date,
					entry_fee, fee, IF(results <> "",1,0) has_results
				FROM promos_events
				WHERE is_hidden=0 AND promo_id=' . intval($promoId) . '
					AND ' . $timeWhere . '
				ORDER BY ' . $order . '
				LIMIT 3');
			if (0 != count($events_)) {
				break;
			}
		}

		$evtRemain = 0;
		if (!$displayingArchive) {
			$evtRemain = $this->db->single_query('SELECT FOUND_ROWS()');
			$evtRemain = $evtRemain[0];
		}

		foreach ($events_ as $event) {
			$pwdCol |= !empty($event['pwd']);
		}

		return array($events_, $evtRemain, $pwdCol);
	}

	private function renderSchedule($argv)
	{
		$tpl = $this->load_template();

		$entry = $this->getPromo($argv['promo-alias']);
		$this->partialRenderCommon($mainArgv, $entry, 'schedule');

		$contentArgv = array(
			'list' => '',
			'has_archive' => $this->eventsArchiveExist($entry['id']),
			'url.archive' => $this->linkas('promos#' . $entry['alias'] . '/schedule', '', array(
				'archive' => 1
			))
		);

		$locale = moon::locale();
		$promoTz = $locale->timezone($entry['timezone']);
		list($tzName, $tzShift) = $this->userTzData($promoTz[0]);
		list($events, $pwdCol) = $this->getEventsGrouped($entry['id'], $tzShift, TRUE);
		$contentArgv['list'] = $this->partialRenderScheduleEvents($entry, $events, $pwdCol, $tzName, $tzShift);

		if ($contentArgv['list'] == '' && $contentArgv['has_archive']) {
			$contentArgv['has_archive'] = false;
			list($events, $pwdCol) = $this->getEventsGrouped($entry['id'], $tzShift, FALSE);
			$contentArgv['list'] = $this->partialRenderScheduleEvents($entry, $events, $pwdCol, $tzName, $tzShift);
			$contentArgv['instant_archive'] = 1;
		}

		$mainArgv = array_merge($mainArgv, array(
			'content' => $tpl->parse('schedule:content', $contentArgv)
		));

		return $tpl->parse($this->altTpl('all:main', $entry), $mainArgv);
	}

	private function renderScheduleArchive($argv)
	{
		$locale = moon::locale();
		$entry = $this->getPromo($argv['promo-alias']);
		$promoTz = $locale->timezone($entry['timezone']);
		list($tzName, $tzShift) = $this->userTzData($promoTz[0]);
		list($events, $pwdCol) = $this->getEventsGrouped($entry['id'], $tzShift, FALSE);

		$result = $this->partialRenderScheduleEvents($entry, $events, $pwdCol, $tzName, $tzShift);

		$this->helperResondAjax($result);
	}

	private function partialRenderScheduleEvents($entry, $events, $pwdCol, $tzName, $tzShift)
	{
		$locale = moon::locale();
		$tpl = $this->load_template();
		$currencies = array('USD' => '$', 'EUR' => '€');

		$result = '';
		foreach ($events as $group) {
			$monthName = $locale->month_name(intval(gmdate('m', $group[0]['start_date'] + $tzShift)), 'm');
			$yearName = gmdate('Y', $group[0]['start_date'] + $tzShift);
			$groupArgv = array(
				'date' => $yearName != gmdate('Y')
					? $yearName . ', ' . $monthName
					: $monthName,
				'pwdCol' => $pwdCol,
				'tzName' => $tzName,
				'events' => '',
			);
			foreach ($group as $event) {
				$eventArgv = array(
					'id' => $event['id'],
					'date' => gmdate('d', $event['start_date'] + $tzShift),
					'time' => gmdate('H:i', $event['start_date'] + $tzShift),
					'title' => htmlspecialchars($event['title']),
					'pwd' => '',
					'pwdCol' => $pwdCol,
					'entryFee' => round($event['entry_fee'], 2),
					'fee'      => round($event['fee'], 2),
					'currency' => isset($entry['room']['currency']) && isset($currencies[$entry['room']['currency']])
						? $currencies[$entry['room']['currency']]
						: $entry['room']['currency'],
					'url' => $event['has_results']
						? $this->linkas('promos#' . $entry['alias'] . '/results/events/', $event['id'])
						: '',
					'live' => time() >= $event['start_date'] && time() < $event['start_date'] + 86400,
				);
				if ($event['pwd_date'] == 0 || $event['pwd_date'] < time()) {
					$eventArgv['pwd'] = htmlspecialchars($event['pwd']);
				} elseif (!empty($event['pwd'])) {
					$eventArgv['pwd'] = $tpl->parse('schedule:pwd_pending', array(
						'date' => $locale->gmdatef($event['pwd_date'], 'freerollTime', $tzShift)
					));
				}
				$groupArgv['events'] .= $tpl->parse('schedule:month.event', $eventArgv);
			}
			$result .= $tpl->parse('schedule.month', $groupArgv);
		}
		return $result;
	}

	private function getEventsGrouped($promoId, $tzShift, $searchForward)
	{
		$events = array();
		$pwdCol = false;
		$timeWhere = $searchForward
			? 'start_date>=FROM_UNIXTIME(' . (floor((time()-86400)/60)*60) . ')'
			: 'start_date<FROM_UNIXTIME(' . (floor((time()-86400)/60)*60) . ')';
		$order = $searchForward
			? 'start_date'
			: 'start_date DESC';
		$events_ = $this->db->array_query_assoc('
			SELECT id, title, UNIX_TIMESTAMP(start_date) start_date,
				pwd, UNIX_TIMESTAMP(pwd_date) pwd_date,
				entry_fee, fee, IF(results <> "",1,0) has_results
			FROM promos_events
			WHERE is_hidden=0 AND promo_id=' . intval($promoId) . '
				AND ' . $timeWhere . '
			ORDER BY ' . $order);
		foreach ($events_ as $event) {
			$events[gmdate('Y-m', $event['start_date'] + $tzShift)][] = $event;
			$pwdCol |= !empty($event['pwd']);
		}

		return array($events, $pwdCol);
	}

	private function eventsArchiveExist($promoId)
	{
		$events_ = $this->db->array_query_assoc('
			SELECT 1 FROM promos_events
			WHERE is_hidden=0 AND promo_id=' . intval($promoId) . '
				AND start_date<FROM_UNIXTIME(' . (floor((time()-86400)/60)*60) . ')
			LIMIT 1
		');
		return !empty($events_);
	}

	private function eventsExist($promoId)
	{
		$events_ = $this->db->array_query_assoc('
			SELECT 1 FROM promos_events
			WHERE is_hidden=0 AND promo_id=' . intval($promoId) . '
			LIMIT 1
		');
		return !empty($events_);
	}

	private function userTzData($promoTzShift)
	{
		$locale = moon::locale();
		$user   = moon::user();
		$userTz = $user->id()
			? $locale->timezone((int)$user->get_user('timezone'))
			: array(intval(date('Z')), 'GMT' . str_replace(array('+0','-0','00'), array('+','-',''), date('O')), date('P'), '', '');
		return array(
			$userTz[1],
			$userTz[0] - $promoTzShift
		);
	}

	private function renderResults($argv)
	{
		$tpl = $this->load_template();
		$sitemap = moon::shared('sitemap');
		moon::page()->js('/js/ajaxSuggestions.js');

		$entry = $this->getPromo($argv['promo-alias']);
		$this->partialRenderCommon($mainArgv, $entry, 'results');

		$pageInfo = $sitemap->getPage();
		$ajaxUri = explode('/', rtrim($sitemap->getLink(), '/'));
		$ajaxUri[] = $entry['alias'];
		$ajaxUri[] = 'results';
		$ajaxUri = array_map('urlencode', $ajaxUri);

		$contentArgv = array(
			'description' => $entry['lb_descr'],
			'result.headers' => '',
			'result.data.rows' => '',
			'ajaxuri' => implode('/', $ajaxUri),
			'paging' => '',
		);
		if (NULL == ($resultChunks = $this->parseResults($entry['lb_data'], $entry['lb_columns']))) {
			moon::page()->page404();
		}
		if (count($resultChunks['data']) == 0) {
			moon::page()->page404();
		}


		if (!empty($argv['filter']) && strlen($argv['filter']) > 2) {
			$searchName = strtolower(trim($argv['filter']));
			$found = array();
			$playerCol = $resultChunks['idx.player'];
			foreach ($resultChunks['data'] as $row) {
				if (strpos(strtolower($row[$playerCol]), $searchName) !== false) {
					$found[] = $row;
				}
			}
			$resultChunks['data'] = $found;
		} else {
			$paging = moon::shared('paginate');
			$paging->set_curent_all_limit($argv['page'], count($resultChunks['data']), 25);
			$pgInfo = $paging->get_info();
			$paging->set_url($this->linkas('promos#' . $entry['alias'] . '/results', '', array('page' => '{pg}')));
			$contentArgv['paging'] = $paging->show_nav();
			$pagedData = array();
			for ($i = $pgInfo['from'] - 1; $i < $pgInfo['to']; $i++) {
				$pagedData[] = $resultChunks['data'][$i];
			}
			$resultChunks['data'] = $pagedData;
		}

		$defaultClass = 'before-player';
		foreach ($resultChunks['columns'] as $k => $row) {
			$class = $defaultClass;
			if ($k == $resultChunks['idx.player']) {
				$defaultClass = '';
				$class = '';
			} elseif ($k == $resultChunks['idx.points']) {
				$class = 'tar nowrap';
			}
			$contentArgv['result.headers'] .= $tpl->parse('results:header', array(
				'name' => htmlspecialchars($row),
				'class' => $class
			));
		}
		foreach($resultChunks['data'] as $row) {
			$rowData = '';
			$defaultClass = 'before-player';
			foreach ($row as $k => $v) {
				$class = $defaultClass;
				if ($k == $resultChunks['idx.player']) {
					$defaultClass = '';
					$class = '';
				} elseif ($k == $resultChunks['idx.points']) {
					$class = 'tar count';
				}
				$rowData .= $tpl->parse('results:data', array(
					'value' => htmlspecialchars($v),
					'class' => $class
				));
			}
			$contentArgv['result.data.rows'] .= $tpl->parse('results:data.row', array(
				'result.data' => $rowData
			));
		}
		$mainArgv = array_merge($mainArgv, array(
			'content' => $tpl->parse('results:content', $contentArgv)
		));

		return $tpl->parse($this->altTpl('all:main', $entry), $mainArgv);
	}

	private function renderResultsEvent($argv)
	{
		$tpl = $this->load_template();

		$entry = $this->getPromo($argv['promo-alias']);
		if (NULL == ($event = $this->getEvent($argv['event-id']))) {
			moon::page()->page404();
		}
		$this->partialRenderCommon($mainArgv, $entry, 'schedule');

		$contentArgv = array(
			'result.headers' => '',
			'result.data.rows' => '',
			'url.schedule' => $this->linkas('promos#' . $entry['alias'] . '/schedule')
		);
		if (NULL == ($resultChunks = $this->parseResults($event['results'], $event['results_columns']))) {
			moon::page()->page404();
		}
		if (count($resultChunks['data']) == 0) {
			moon::page()->page404();
		}

		$defaultClass = 'before-player';
		foreach ($resultChunks['columns'] as $k => $row) {
			$class = $defaultClass;
			if ($k == $resultChunks['idx.player']) {
				$defaultClass = '';
				$class = '';
			} elseif ($k == $resultChunks['idx.points']) {
				$class = 'tar nowrap';
			}
			$contentArgv['result.headers'] .= $tpl->parse('schedule.results:header', array(
				'name' => htmlspecialchars($row),
				'class' => $class
			));
		}
		foreach($resultChunks['data'] as $row) {
			$rowData = '';
			$defaultClass = 'before-player';
			foreach ($row as $k => $v) {
				$class = $defaultClass;
				if ($k == $resultChunks['idx.player']) {
					$defaultClass = '';
					$class = '';
				} elseif ($k == $resultChunks['idx.points']) {
					$class = 'tar count';
				}
				$rowData .= $tpl->parse('schedule.results:data', array(
					'value' => htmlspecialchars($v),
					'class' => $class
				));
			}
			$contentArgv['result.data.rows'] .= $tpl->parse('schedule.results:data.row', array(
				'result.data' => $rowData
			));
		}
		$mainArgv = array_merge($mainArgv, array(
			'content' => $tpl->parse('schedule:content.results', $contentArgv)
		));

		return $tpl->parse($this->altTpl('all:main', $entry), $mainArgv);
	}

	private function renderResultsSearch($argv)
	{
		$tpl = $this->load_template();
		$entry = $this->getPromo($argv['promo-alias']);
		$notFoundStr = $tpl->parse('results:player_not_found');

		if (NULL == ($resultChunks = $this->parseResults($entry['lb_data'], $entry['lb_columns']))) {
			$this->helperResondAjax($notFoundStr);
		}

		$searchName = strtolower(trim($argv['search']));
		$playerCol = $resultChunks['idx.player'];
		$foundLink = $this->linkas('promos#' . $entry['alias'] . '/results?filter=');
		$found = array();
		foreach ($resultChunks['data'] as $row) {
			if (strpos(strtolower($row[$playerCol]), $searchName) !== false) {
				$found[] = $tpl->parse('results:players.item', array(
					'name' => htmlspecialchars($row[$playerCol]),
					'uri' => $foundLink . htmlspecialchars($row[$playerCol])
				));
			}
		}

		if (0 != count($found))
			$this->helperResondAjax($tpl->parse('results:players', array(
				'items' => implode('', $found)
			)));
		else
			$this->helperResondAjax($notFoundStr);
	}

	private function helperResondAjax($str)
	{
		echo $str;
		moon_close();
		exit;
	}

	private function renderTermsConditions($argv)
	{
		$tpl = $this->load_template();

		$entry = $this->getPromo($argv['promo-alias']);
		if (is_object( $rtf = $this->object('rtf') )) {
			$rtf->setInstance($this->get_var('rtf'));
			list(, $entry['terms_conditions']) = $rtf->parseText('', $entry['terms_conditions']);
		}

		$this->partialRenderCommon($mainArgv, $entry, 'terms-and-conditions');
		$mainArgv = array_merge($mainArgv, array(
			'content' => $this->parseRoomVars($entry['terms_conditions'], $entry['room'])
		));

		return $tpl->parse($this->altTpl('all:main', $entry), $mainArgv);
	}

	private function renderCustomPage($argv)
	{
		$tpl = $this->load_template();
		$page = moon::page();

		$entry = $this->getPromo($argv['promo-alias']);

		if (NULL == ($cp = $this->getCustomPage($entry['id'], $argv['sub-alias']))) {
			moon::page()->page404();
		}
		if (is_object( $rtf = $this->object('rtf') )) {
			$rtf->setInstance($this->get_var('rtf'));
			list(, $cp['description']) = $rtf->parseText('', $cp['description']);
		}

		$this->partialRenderCommon($mainArgv, $entry, $cp['alias']);
		$mainArgv = array_merge($mainArgv, array(
			'content' => $this->parseRoomVars($cp['description'], $entry['room'])
		));

		$page->meta('keywords',    $cp['meta_kwd']);
		$page->meta('description', $cp['meta_descr']);

		return $tpl->parse($this->altTpl('all:main', $entry), $mainArgv);
	}

	private function partialRenderCommon(&$mainArgv, $entry, $src)
	{
		$tpl = $this->load_template();
		$page = moon::page();
		$storage = moon::shared('storage');
		// misc
		$mainArgv = array(
			'title' => htmlspecialchars($entry['title']),
			'menu' => '',
			'intro' => $this->parseRoomVars($entry['descr_intro'], $entry['room']),
			'id' => $entry['id'],
		);
		if ('' != $entry['img_main'])
			$mainArgv['img_main'] = $storage->location('promo-main')->url($entry['img_main'], 0);
		if ($entry['room']) {
			$mainArgv = array_merge($mainArgv, array(
				'room.name'           => htmlspecialchars($entry['room']['name']),
				'room.bonus_code'     => htmlspecialchars($entry['room']['bonus_code']),
				'room.marketing_code' => htmlspecialchars($entry['room']['marketing_code']),
			));
			if ($entry['room']['id'] > 0)
				$mainArgv = array_merge($mainArgv, array(
					'room.download' => htmlspecialchars('/' . $entry['room']['alias'] . '/download/?EL=League'),
					'room.visit'    => htmlspecialchars('/' . $entry['room']['alias'].'/?EL=League'),
					'room.logo' => img('rw', $entry['room']['id'], $entry['room']['logo'])
				));
			else
				$mainArgv = array_merge($mainArgv, array(
					'room.download' => htmlspecialchars($entry['room']['download']),
					'room.visit'    => htmlspecialchars($entry['room']['visit']),
					'room.logo' => !empty($entry['room']['logo']) ? $this->get_dir('CustomRooms') . $entry['room']['logo'] : ''
				));
		}

		// tab menu
		$menu = $tpl->parse_array('all:menuTitles');
		$menu = array(
			'index'    => array('', $menu['index']),
			'schedule' => array('schedule/', $menu['schedule']),
			'results'  => array('results/', $menu['results']),
			'terms-and-conditions'
			           => array('terms-and-conditions/', $menu['terms-and-conditions'])
		);
		if ('' == $entry['lb_data'])
			unset($menu['results']);
		if ('' == $entry['terms_conditions'])
			unset($menu['terms-and-conditions']);
		if (!$this->eventsExist($entry['id']))
			unset($menu['schedule']);
		foreach ($this->getCustomPages($entry['id']) as $row)
			$menu[$row['alias']] = array($row['alias'] . '/', $row['title']);
		foreach ($menu as $id => $item)
			$mainArgv['menu'] .= $tpl->parse('all:menu.item', array(
				'title' => htmlspecialchars($item[1]),
				'url' => $this->linkas('promos#' . $entry['alias']) . $item[0],
				'active' => $src == trim($id, '#')
			));

		// css, title
		if ($entry['skin_dir'] != '') {
			$page->css('/img/promo/' . rawurlencode($entry['skin_dir']) . '/style.css');
		}
		$page->css('/css/article.css');
		$page->title($entry['title']);
	}

	private function parseRoomVars($text, $room)
	{
		if (!$room)
			return $text;
		$tplArgs = array(
			'{bonus_code}'     => $room['bonus_code'],
			'{marketing_code}' => $room['marketing_code'],
		);
		if (isset($room['uri']))
			$tplArgs = array_merge($tplArgs, array(
				'http://{visit}'    => '/' . $room['alias'] . '/ext/?EL=League',
				'http://{download}' => '/' . $room['alias'] . '/download/?EL=League',
			));
		foreach ($tplArgs as $key => $value) {
			$text = str_replace($key, htmlspecialchars($value), $text);
		}
		return $text;
	}

	private function parseResults($results, $columns)
	{
		$columns = explode(';', $columns);
		if (end($columns) == '') {
			array_pop($columns);
		}
		$playerCol = null;
		$pointsCol = null;
		foreach ($columns as $k => $column) {
			if (strpos($column, '*') !== false) {
				$playerCol = $k;
				$column = str_replace('*', '', $column);
			} elseif (strpos($column, '+') !== false) {
				$pointsCol = $k;
				$column = str_replace('+', '', $column);
			}
			$columns[$k] = trim($column);
		}
		if ($playerCol === null || $pointsCol === null) {
			return ;
		}

		$data = explode("\n", $results);
		if (end($data) == '') {
			array_pop($data);
		}
		foreach ($data as $k => $row) {
			$data[$k] = explode("\t", $row);
		}

		return array(
			'columns' => $columns,
			'idx.player' => $playerCol,
			'idx.points' => $pointsCol,
			'data' => $data
		);
	}

	private $promo;
	private function getPromo($alias)
	{
		if (!$this->promo) {
			$iAdmin = moon::user()->i_admin('content') && moon::page()->get_global('adminView');
			$altView = $this->getPromoPreFilter($alias);
			$hidePokernewsCup = (_SITE_ID_ === 'com') ? 'pokernewscup = 0' : 'pokernewscup < 2';

			$promo = $this->db->single_query_assoc('
				SELECT * FROM promos
				WHERE alias="' . $this->db->escape($alias) . '" AND ' . $hidePokernewsCup . '
					AND ' . ($iAdmin
						? 'is_hidden!=2'
						: 'is_hidden=0')
			);
			if (0 == count($promo)) {
				$promo = null;
			} else {
				$promo['room'] = $this->getPromoRoom($promo['room_id']);
				$this->getPromoPostFilter($promo, $altView);
			}
			$this->promo = $promo;
		}
		return $this->promo;
	}

	private $promoAltView = null;
	private function getPromoPreFilter(&$alias)
	{
		switch ($alias) {
		case '888-4-500-wsop-package-1':
		case '888-4-500-wsop-package-2':
			$altView = ['alias' => $alias, 'variant' => str_replace('888-4-500-wsop-package', '', $alias)];
			$alias = '888-4-500-wsop-package';
			return $altView;
		case 'team-everest-7200-wsop-package-1':
		case 'team-everest-7200-wsop-package-2':
			$altView = ['alias' => $alias, 'variant' => str_replace('team-everest-7200-wsop-package', '', $alias)];
			$alias = 'team-everest-7200-wsop-package';
			return $altView;
		case 'pokernews-20k-freerolls-1':
		case 'pokernews-20k-freerolls-2':
			$altView = ['alias' => $alias, 'variant' => str_replace('pokernews-20k-freerolls', '', $alias)];
			$alias = 'pokernews-20k-freerolls';
			return $altView;
		}
	}

	private function getPromoPostFilter(&$promo, $altView)
	{
		if ($altView) {
			$promo['alias'] = $altView['alias'];
			if ($promo['skin_dir'])
				$promo['skin_dir'] .= $altView['variant'];
		}
	}

	private function getPromoRoom($roomIds)
	{
		$room = $this->db->single_query_assoc('
			SELECT r.id, r.alias, r.name, r.logo, t.bonus_code, r.currency FROM ' . $this->table('Rooms') . ' r
			INNER JOIN ' . $this->table('Trackers') . ' t
				ON t.parent_id=r.id AND t.alias=""
			WHERE id IN(' . implode(',', array_map('intval', explode(',', $roomIds))) . ')
		');
		if (0 == count($room)) {
			$room = null;
		} else {
			list($room['bonus_code'], $room['marketing_code']) = explode('|', $room['bonus_code'] . '|');
		}

		return $room;
	}

	private $event_;
	private function getEvent($id)
	{
		if (!$this->event_) {
			$this->event_ = $this->db->single_query_assoc('
				SELECT * FROM promos_events
				WHERE id="' . $this->db->escape($id) . '"
					AND is_hidden=0
			');
			$this->event_ = 0 != count($this->event_)
				? $this->event_
				: null;
		}
		return $this->event_;
	}

	private function getCustomPages($promoId)
	{
		return $this->db->array_query_assoc('
			SELECT title, alias FROM promos_pages
			WHERE promo_id=' . intval($promoId) . ' AND is_hidden=0
			  ORDER BY position
		');
	}

	private $customPage;
	private function getCustomPage($promoId, $alias)
	{
		if (!$this->customPage) {
			$this->customPage = $this->db->single_query_assoc('
				SELECT * FROM promos_pages
				WHERE promo_id="' . intval($promoId) . '" AND alias="' . $this->db->escape($alias) . '"
					AND is_hidden=0
			');
			$this->customPage = 0 != count($this->customPage)
				? $this->customPage
				: null;
		}
		return $this->customPage;
	}

	private function altTpl($tplName, $entry)
	{
		$tpl = $this->load_template();
		return $tpl->has_part($tplName . ':' . $entry['alias'])
			? $tplName . ':' . $entry['alias']
			: $tplName;
	}
}
