<?php

include_class('moon_memcache');

class event_list extends moon_com
{
	function onload()
	{
		$this->bluffable = in_array(_SITE_ID_, array('com', 'fr'));
		$this->starsable = in_array(_SITE_ID_, array('com'));
	}

	function properties()
	{
		return array(
			'render' => NULL,
			'id' => NULL
		);
	}

	function events($event, $argv)
	{
		switch ($event) {
			case 'save':
				$this->forget();
				$page = &moon::page();
				$form = &$this->form();

				$form->names('id','tournament_id','name',
					'from_date','from_time','to_date',
					'to_time','buyin','fee','rebuy','addon','is_live','is_main','is_syncable','alias','prizepool','chipspool','players_total','players_left',
					'stayhere', 'bluff_id', 'stars_id',
					'dayid', 'dayname', 'dayfrom_date', 'dayfrom_time'
				);
				$form->fill($_POST);
				$data = $form->get_values();

				$gData = $page->get_global($this->my('fullname'));
				if ('' === $gData) {
					$gData = array();
				}
				$gData['one-run'] = array();
				if ('' != $data['stayhere']) {
					$gData['one-run']['form-stay'] = 1;
					$page->set_global($this->my('fullname'), $gData);
				}

				$saved = $this->saveEntry_($data);
				if (NULL === $saved) {
					$gData['one-run']['failed-form-data'] = $data;
					$page->set_global($this->my('fullname'), $gData);
					switch ($data['id']) {
						case '':
							$this->redirect('#new', 'by-tour.' . $data['tournament_id']);
						default:
							$this->redirect('#', $data['id']);
					}
				} else {
					switch ($data['stayhere']) {
						case '':
							$this->redirect('#','by-tour.' . $data['tournament_id']);
						default:
							$this->redirect('#', $saved);
					}
				}
				exit;

			case 'delete':
				$form = &$this->form();
				$form->names('ids');
				$form->fill($_POST);
				$data = $form->get_values();
				ob_start();
				$this->deleteEntry_(explode(',', $data['ids']));
				$this->redirect('#', 'by-tour.' . $_POST['tournament_id']);
				break;

			case 'new':
				if (isset($argv[1]) && $argv[0] == 'by-tour'
				    && NULL !== ($id = $this->getInteger_($argv[1]))) {
					$this->set_var('tour-id', $id);
					$this->set_var('render', 'entry');
				}
				break;
 
			default:
				if (isset($argv[1]) && $argv[0] == 'by-tour'
				    && NULL !== ($id = $this->getInteger_($argv[1]))) {
					$this->set_var('tour-id', $id);
				} elseif (isset($argv[0]) && NULL !== ($id = $this->getInteger_($argv[0]))) {
					$this->set_var('render', 'entry');
					$this->set_var('id', $id);
				}
				break;
		}
		$this->use_page('Common');
	}

	function main($argv)
	{
		$window = &moon::shared('admin');
		$page   = &moon::page();
		$window->active($this->my('module').'.tour_list');
		$page->title(
			$window->current_info('title')
		);

		$gArgv = $page->get_global($this->my('fullname'));
		if (isset($gArgv['one-run']) && is_array($gArgv['one-run'])) {
			foreach ($gArgv['one-run'] as $key => $value) {
				$argv[$key] = $value;
			}
		}
		if (isset($gArgv['one-run'])) {
			unset($gArgv['one-run']);
		}
		if (!empty($gArgv)) {
			$page->set_global($this->my('fullname'), $gArgv);
		} else {
			$page->set_global($this->my('fullname'), '');
		}

		$e = NULL;
		switch ($argv['render']) {
			case 'entry':
				$output = $this->renderEntry_($argv, $e);
				break;

			default:
				$output = $this->renderList_($argv, $e);
				break;
		}

		switch ($e) {
			case NULL:
				return $output;

			default:
				$page->alert($e);
				$this->redirect('#');
		}
	}

	function renderList_($argv, &$e)
	{
		$page     = &moon::page();
		$tpl      = &$this->load_template();

		$page->js('/js/modules_adm/ng-list.js');

		if (!isset($argv['tour-id'])) {
			return '';
		}

		$mainArgv  = array(
			'url.add_entry' => $this->linkas('#new', 'by-tour.' . $argv['tour-id']),
			'url.tedit' => $this->linkas('tour_list#', $argv['tour-id']),
			'event.delete'  => $this->my('fullname') . '#delete',
			'list.entries'  => '',
			'tournament_id' => $argv['tour-id'],
			'url.back' => $this->linkas('tour_list#'),
		);

		$tournament = $this->getTour($argv['tour-id']);
		if (!$tournament) {
			$messages = $tpl->parse_array('messages');
			$e  = $messages['e.entry_not_found'];
			return ;
		}
		$mainArgv['tname'] = htmlspecialchars($tournament['name']);

		$statuses = array('<span class="scheduledIco" title="Scheduled"></span>', '<span class="liveIco" title="Started"></span>', '<span class="explain">Concluded</span>');
		$events = $this->getEvents($tournament['id']);
		$locale = &moon::locale();
		list($tzOffset) = $locale->timezone($tournament['timezone']);
		foreach ($events as $event) {
			$mainArgv['list.entries'] .= $tpl->parse('list:entries.item', array(
				'id' => $event['id'],
				'start_date' => strftime('%Y-%m-%d', $event['from_date'] + $tzOffset),
				'days' => $event['dayscnt'],
				'status' => $event['is_live']
					? $statuses[$event['state']]
					: '',
				'is_main' => $event['is_main'],
				'name' => htmlspecialchars($event['name']),
				'url' => $this->linkas('#', $event['id']),
				'urldays' => $this->linkas('day_list#', 'by-event.' . $event['id']),
				'urlwinner' => ($event['winner_id'])
					? $this->linkas('winner_list#', $event['winner_id'])
					: $this->linkas('winner_list#new', 'by-event.' . $event['id']),
				'winner' => htmlspecialchars($event['winner'])
			));
			foreach ($event['days'] as $day) {
				$mainArgv['list.entries'] .= $tpl->parse('list:entries.subitem', array(
					'id' => $event['id'] . '.' . $day['id'],
					'start_date' => strftime('%Y-%m-%d', $day['day_date'] + $tzOffset),
					'status' => $day['is_live']
						? $statuses[$day['state']]
						: '',
					'name' => htmlspecialchars($day['name'])
				));
			}
		}

		return $tpl->parse('list:main', $mainArgv);
	}

	function getTour($id)
	{
		$tour = $this->db->single_query_assoc('
			SELECT t.id, t.name, t.alias, t.currency, t.timezone, COUNT(e.id) ecnt, t.is_syncable
			FROM ' . $this->table('Tournaments') . ' t
			LEFT JOIN ' . $this->table('Events') . ' e
				ON t.id=e.tournament_id
			WHERE t.id="' . addslashes($id) . '" AND t.is_live>=0
			GROUP BY t.id
		');
		if (empty($tour)) {
			return NULL;
		}
		return $tour;
	}

	function daysSortCmp($a, $b)
	{
		return strnatcasecmp($a['name'], $b['name']);
	}
	
	function getEvents($id)
	{
		
		$events = array();

		$rEvents = $this->db->array_query_assoc('
			SELECT e.id, e.from_date, e.to_date, e.name, e.state, e.is_live, e.is_main, COUNT(d.id) dayscnt, w.id winner_id, w.winner
			FROM ' . $this->table('Events') . ' e
			LEFT JOIN ' . $this->table('Days') . ' d
				ON e.id=d.event_id
			LEFT JOIN ' . $this->table('Winners') . ' w
				ON e.id=w.event_id
			WHERE e.is_live>=0 AND e.tournament_id="' . addslashes($id) . '"
			GROUP BY e.id
			ORDER BY e.from_date DESC, e.id DESC
		');
		foreach ($rEvents as $rEvent) {
			$rEvent['days'] = array();
			$events[$rEvent['id']] = $rEvent;
		}

		if (0 == count($events)) {
			return $events;
		}
		$days = $this->db->array_query_assoc('
			SELECT id, day_date, name, is_live, state, event_id
			FROM ' . $this->table('Days') . '
			WHERE  is_live>=0 AND event_id IN (' . implode(',' , array_keys($events)) . ')
			ORDER BY name DESC
		');
		usort($days, array($this, 'daysSortCmp'));
		foreach ($days as $day) {
			$events[$day['event_id']]['days'][] = $day;
		}

		return $events;
	}

	function renderEntry_($argv, &$e)
	{
		$page     = &moon::page();
		$tpl      = &$this->load_template();
		$locale = &moon::locale();
		$mainArgv  = array(
			'event.save' => $this->my('fullname') . '#save',
			'stayhere' => !empty($argv['form-stay']),
			'days' => ''
		);

		$page->js('/js/modules_adm/ng-entry.js');
		$page->js('/js/modules_adm/livereporting.js');
		$page->js('/js/jquery/maskedinput-1.1.3.js');
		$page->js('/js/jquery/livequery.js');

		$nr = 0;
		if (NULL === $argv['id']) {
			$tournament = $this->getTour($argv['tour-id']);
			list($tzOffset, $tzName) = $locale->timezone($tournament['timezone']);
			$entryData = array(
				'id' => '',
				'tournament_id' => $tournament['id'],
				'name' => '',
				'alias' => '',
				'buyin' => '',
				'fee' => '',
				'rebuy' => '',
				'addon' => '',
				'from_date' => '',
				'from_time' => '09:00',
				'to_date' => '',
				'to_time' => '23:55',
				'players_total' => '',
				'players_left' => '',
				'prizepool' => '',
				'chipspool' => '',
				'bluff_id' => '',
				'stars_id' => '',
				'is_live' => '1',
				'is_main' => '',
				'is_syncable' => '1'
			);
			$mainArgv['title'] = 'New event';
			$mainArgv['tz'] = $tzName;
		} else {
			if (NULL === ($entryData = $this->getEntry_($argv['id']))) {
				$messages = $tpl->parse_array('messages');
				$e  = $messages['e.entry_not_found'];
				return ;
			}
			$tournament = $this->getTour($entryData['tournament_id']);
			list($tzOffset, $tzName) = $locale->timezone($tournament['timezone']);
			foreach (array('from_date','to_date') as $key) {
				if ($entryData[$key] != NULL) {
					$entryData[$key] += $tzOffset;
				}
			}
			$entryData = $this->extendArray_($entryData, array(
				'from_date' => gmdate('Y-m-d', $entryData['from_date']),
				'from_time' => gmdate('H:i', $entryData['from_date']),
				'to_date' => $entryData['to_date'] == null
					? ''
					: gmdate('Y-m-d', $entryData['to_date']),
				'to_time' => $entryData['to_date'] == null
					? '23:55'
					: gmdate('H:i', $entryData['to_date']),
			));
			$mainArgv['title'] = htmlspecialchars($entryData['name']);
			$mainArgv['tz'] = $tzName;

			if ($entryData['is_live'] >= 0) {
				$mainArgv['url.preview'] = moon::shared('sitemap')->getLink('reporting') 
					. rawurldecode($tournament['alias']) . '/'
					. rawurldecode($entryData['alias']) . '/';
			}

			$days = $this->db->array_query_assoc('
				SELECT id, day_date, name
				FROM ' . $this->table('Days') . '
				WHERE is_live>=0 AND event_id =' . intval($argv['id']) . '
				ORDER BY name DESC
			');
			usort($days, array($this, 'daysSortCmp'));
			foreach ($days as $day) {
				$mainArgv['days'] .= $tpl->parse('entry:day.item', array(
					'nr' => $nr + 1,
					'id' => $day['id'],
					'value' => htmlspecialchars($day['name']),
					'from_date' => gmdate('Y-m-d', $day['day_date'] + $tzOffset),
					'from_time' => gmdate('H:i',   $day['day_date'] + $tzOffset),
				));
				$nr++;
			}
		}
		if ($tournament['is_syncable']) {
			$mainArgv['is_tsyncable'] = true;
		}
		if ($this->bluffable) {
			$mainArgv['is_bluffable'] = true;
		}
		if ($this->starsable) {
			$mainArgv['is_starsable'] = true;
		}
		if (isset($argv['failed-form-data'])) {
			$entryData = $this->overwriteArray_($entryData, $argv['failed-form-data']);
		}

		if (isset($argv['failed-form-data'])) {
			foreach ($argv['failed-form-data']['dayid'] as $nr2 => $id) {
				if ('' != $id || '' == $argv['failed-form-data']['dayname'][$nr2]) {
					continue ;
				}
				$mainArgv['days'] .= $tpl->parse('entry:day.item', array(
					'nr' => $nr + 1,
					'id' => '',
					'value' => htmlspecialchars($argv['failed-form-data']['dayname'][$nr2]),
					'from_date' => htmlspecialchars($argv['failed-form-data']['dayfrom_date'][$nr2]),
					'from_time' => htmlspecialchars($argv['failed-form-data']['dayfrom_time'][$nr2]),
				));
				$nr++;
			}
		}

		$mainArgv['emptyDayControl'] = json_encode($tpl->parse('entry:day.item', array(
			'id' => '',
			'from_time' => '09:00',
		)));

		$mainArgv['url.back'] = $this->linkas('#', 'by-tour.' . $tournament['id']);
		$mainArgv['toururi']  = htmlspecialchars($tournament['alias']);
		$mainArgv['tourname']  = htmlspecialchars($tournament['name']);
		$mainArgv['tourcurr'] = htmlspecialchars($tournament['currency']);

		foreach ($entryData as $key => $value) {
			$mainArgv['entry.' . $key] = htmlspecialchars($value);
		}

		return $tpl->parse('entry:main', $mainArgv);
	}

	function getEntry_($id)
	{
		if (NULL === $this->getInteger_($id)) {
			return NULL;
		}
		$entry = $this->db->single_query_assoc('
			SELECT * FROM ' . $this->table('Events') . '
			WHERE id=' . $id . ' AND is_live>=0
		');
		if (empty($entry)) {
			return NULL;
		}
		return $entry;
	}

	/* data *must* be an array of strings */
	function saveEntry_($data)
	{
		$page     = &moon::page();
		$tpl      = &$this->load_template();
		$messages = $tpl->parse_array('messages');

		$saveData = array();
		$saveDataKeys = array(
				'id','tournament_id','name',
				'buyin','fee','rebuy','addon','is_live','is_main','is_syncable','alias','prizepool','chipspool','players_total','players_left'
			);
		if ($this->bluffable) {
			$saveDataKeys[] = 'bluff_id';
		}
		if ($this->starsable) {
			$saveDataKeys[] = 'stars_id';
		}
		foreach ($saveDataKeys as $key) {
			if (isset($data[$key])) {
				$saveData[$key] = $data[$key];
			}
		}

		if (NULL !== $this->getInteger_($saveData['id'])) {
			$check = $this->db->single_query_assoc('
				SELECT id FROM ' . $this->table('Events') . '
				WHERE tournament_id="' .addslashes($data['tournament_id']). '"
					AND id="' . addslashes($data['id']) . '"
			');
			if (empty($check)) {
				return ;
			}
		}

		$saveData['from_date'] = strtotime($data['from_date'] . ' ' . $data['from_time'] . ':00 +0000');
		$saveData['to_date'] = $data['to_date'] == ''
			? NULL
			: strtotime($data['to_date'] . ' ' . $data['to_time'] . ':59 +0000');

		if (0 === count($saveData)) {
			return ;
		}

		$isInvalid = FALSE;
		if (isset($saveData['id']) && '' !== $saveData['id']) {
			$saveData['id'] = $this->getInteger_($saveData['id']);
			if (NULL === $saveData['id']) {
				$page->alert($messages['e.invalid_id']);
				$isInvalid = TRUE;
			}
		} else {
			$saveData['id'] = NULL; // autoincrement ok, later used
		}
		$required = array('name', 'from_date');
		
		if (!empty($saveData['is_live'])) {
			$required[] = 'alias';
		}

		foreach ($required as $key) {
			if (empty($saveData[$key])) {
				$page->alert($messages['e.empty_' . $key]);
				$isInvalid = TRUE;
			}
		}

		foreach (array('alias') as $key) {
			if (empty($saveData[$key])) {
				$saveData[$key] = NULL;
			}
		}

		$uriDupe = $this->db->single_query_assoc('
			SELECT COUNT(id) cid FROM ' . $this->table('Events') . '
			WHERE is_live>0 AND tournament_id="'. addslashes($saveData['tournament_id']) .'" AND alias="' . addslashes($saveData['alias'])  . '"' .
			(($saveData['id'] !== NULL)
				? ' AND id!=' . $saveData['id']
				: '') . '
		');
		if ('0' != $uriDupe['cid']) {
			$page->alert($messages['e.uri_duplicate']);
			$isInvalid = TRUE;
		}

		if ($this->bluffable && !empty($saveData['bluff_id'])) {
		$bluffDupe = $this->db->single_query_assoc('
			SELECT COUNT(id) cid FROM ' . $this->table('Events') . '
			WHERE bluff_id="' . addslashes($saveData['bluff_id'])  . '"' .
			(($saveData['id'] !== NULL)
				? ' AND id!=' . $saveData['id']
				: '') . '
		');
		if ('0' != $bluffDupe['cid']) {
			$page->alert($messages['e.bluff_duplicate']);
			$isInvalid = TRUE;
		}}
		if ($this->bluffable) {
			$saveData['bluff_id'] = empty($saveData['bluff_id'])
				? NULL
				: $saveData['bluff_id'];
		}
		
		if ($this->starsable && !empty($saveData['stars_id'])) {
		$bluffDupe = $this->db->single_query_assoc('
			SELECT COUNT(id) cid FROM ' . $this->table('Events') . '
			WHERE stars_id="' . addslashes($saveData['stars_id'])  . '"' .
			(($saveData['id'] !== NULL)
				? ' AND id!=' . $saveData['id']
				: '') . '
		');
		if ('0' != $bluffDupe['cid']) {
			$page->alert($messages['e.bluff_duplicate']);
			$isInvalid = TRUE;
		}}
		if ($this->starsable) {
			$saveData['stars_id'] = empty($saveData['stars_id'])
				? NULL
				: $saveData['stars_id'];
		}
		
		if (TRUE === $isInvalid) {
			return NULL;
		}

		if (!empty($saveData['is_main'])) {
			$this->db->query('
				UPDATE ' . $this->table('Events') . '
				SET is_main=0
				WHERE is_main=1 AND tournament_id="'. addslashes($saveData['tournament_id']) .'"
			');
		}

		$tour = $this->getTour($saveData['tournament_id']);
		$locale = &moon::locale();
		list($tzOffset) = $locale->timezone($tour['timezone']);
		foreach (array('to_date','from_date') as $key) {
			if ($saveData[$key] != NULL) {
				$saveData[$key] -= $tzOffset;
			}
		}
		if ($saveData['to_date'] != NULL && 
			$_POST['from_date'] == $_POST['to_date']
			/*gmdate('Y-m-d', $saveData['to_date']) == gmdate('Y-m-d', $saveData['from_date'])*/) {
			$saveData['to_date'] = NULL;
		}

		if (NULL === $saveData['id']) {
			$saveData['created_on'] = time();
			$this->db->insert($saveData, $this->table('Events'));
			$rId = $this->db->insert_id();
			blame($this->my('fullname'), 'Created', $rId);
			livereporting_adm_alt_log($saveData['tournament_id'], $rId, 0, 'insert', 'events', $rId);
			$this->saveEntryDays_($saveData['tournament_id'], $rId, $tzOffset, $data);
			$this->saveTournamentState($saveData['tournament_id']);
			moon_memcache::getInstance()->delete(moon_memcache::getRecommendedPrefix() . 'reporting.events_uris');
			return $rId;
		} else {
			$saveData['updated_on'] = time();
			$this->db->update($saveData, $this->table('Events'), array(
				'id' => $saveData['id']
			));
			blame($this->my('fullname'), 'Updated', $saveData['id']);
			livereporting_adm_alt_log($saveData['tournament_id'], $saveData['id'], 0, 'update', 'events', $saveData['id']);
			$this->saveEntryDays_($saveData['tournament_id'], $saveData['id'], $tzOffset, $data);
			$this->saveTournamentState($saveData['tournament_id']);
			moon_memcache::getInstance()->delete(moon_memcache::getRecommendedPrefix() . 'reporting.events_uris');
			return $saveData['id'];
		}
	}

	function saveTournamentState($tourId)
	{
		$tournmanet = $this->db->single_query_assoc('
			SELECT id, state FROM ' . $this->table('Tournaments') . '
			WHERE id=' . intval($tourId) . '
		');

		// if all events completed
		$check = $this->db->single_query_assoc('
			SELECT id FROM ' . $this->table('Events') . '
			WHERE state!=2 AND is_live=1 AND tournament_id=' . intval($tourId) . '
			LIMIT 1
		');
		if (empty($check)) {
			$this->db->query('
				UPDATE ' . $this->table('Tournaments') . '
				SET state=2
				WHERE id=' . intval($tourId) . '
			');
			livereporting_adm_alt_log($tourId, 0, 0, 'update', 'tournaments', $tourId, 'state=2');
			return ;
		}

		 // if all events scheduled
		$check = $this->db->single_query_assoc('
			SELECT id FROM ' . $this->table('Events') . '
			WHERE state!=0 AND is_live=1 AND tournament_id=' . intval($tourId) . '
			LIMIT 1
		');
		if (empty($check)) {
			$this->db->query('
				UPDATE ' . $this->table('Tournaments') . '
				SET state=0
				WHERE id=' . intval($tourId) . '
			');
			livereporting_adm_alt_log($tourId, 0, 0, 'update', 'tournaments', $tourId, 'state=0');
			return ;
		}
		
		$this->db->query('
			UPDATE ' . $this->table('Tournaments') . '
			SET state=1
			WHERE id=' . intval($tourId) . '
		');
		livereporting_adm_alt_log($tourId, 0, 0, 'update', 'tournaments', $tourId, 'state=1');
	}

	function saveEntryDays_($tourId, $eventId, $tzOffset, $data)
	{
		if (empty($eventId)) {
			return ;
		}
		$evDays = $this->db->array_query_assoc('
			SELECT id FROM ' . $this->table('Days') . '
			WHERE event_id=' . intval($eventId) . '
		', 'id');

		$insDays = array();
		$updDays = array();
		$delDays = array();
		
		$dayDupes = array(); // simple check, rely on all days passed on save

		foreach ($data['dayid'] as $nr => $id) {
			if ($data['dayname'][$nr] == '') {
				if ($id != '') {
					$delDays[] = $id;
				}
				continue ;
			}

			if (isset ($dayDupes[$data['dayname'][$nr]])) {
				continue;
			}
			$dayDupes[$data['dayname'][$nr]] = 1;

			if ($id == '') {
				$insDays[] = $nr;
			} else {
				$updDays[] = $nr;
			}
		}

		foreach ($insDays as $nr) {
			$day_date = strtotime($data['dayfrom_date'][$nr] . ' ' . $data['dayfrom_time'][$nr] . ':00 +0000');
			$day_date -= $tzOffset;
			$saveData = array(
				'tournament_id' => $tourId,
				'event_id' => $eventId,
				'name' => $data['dayname'][$nr],
				'is_live' => 1,
				'is_empty' => 1,
				'state' => 0,
				'created_on' => time(),
				'day_date' => $day_date
			);
			$this->db->insert($saveData, $this->table('Days'));
			$did = $this->db->insert_id();
			blame($this->my('fullname'), 'Created day', $did);
			livereporting_adm_alt_log($tourId, $eventId, $did, 'insert', 'days', $did);
		}

		foreach ($updDays as $nr) {
			$day_date = strtotime($data['dayfrom_date'][$nr] . ' ' . $data['dayfrom_time'][$nr] . ':00 +0000');
			$day_date -= $tzOffset;
			$saveData = array(
				'name' => $data['dayname'][$nr],
				'updated_on' => time(),
				'day_date' => $day_date
			);
			$this->db->update($saveData, $this->table('Days'), array(
				'id' => $data['dayid'][$nr]
			));
			blame($this->my('fullname'), 'Updated day', $data['dayid'][$nr]);
			livereporting_adm_alt_log($tourId, $eventId, $data['dayid'][$nr], 'update', 'days', $data['dayid'][$nr]);
		}

		foreach ($delDays as $id) {
			$this->db->query('
				UPDATE ' . $this->table('Days') . '
				SET is_live=-1, updated_on=' . time() . '
				WHERE id=' . intval($id) . '
			');
			/*
			$this->db->query('
				UPDATE ' . $this->table('Days') . '
				SET is_live=-1
				WHERE is_empty=0 AND id=' . intval($id) . '
			');
			$this->db->query('
				DELETE FROM ' . $this->table('Days') . '
				WHERE is_empty=1 AND id=' . intval($id) . '
			');
			 */
			blame($this->my('fullname'), 'Deleted day', intval($id));
			livereporting_adm_alt_log($tourId, $eventId, $id, 'update', 'days', $id, 'softdel');
		}
	}

	function deleteEntry_($ids)
	{
		$deleteEIds = array();
		$deleteDIds = array();
		if (!is_array($ids)) {
			$ids = array($ids);
		}
		foreach ($ids as $id) {
			if (strpos($id, '.') !== FALSE) {
				$id = explode('.', $id);
				if (NULL !== ($id = $this->getInteger_($id[1]))) {
					$deleteDIds[] = $id;
				}
			} elseif (NULL !== ($id = $this->getInteger_($id))) {
				$deleteEIds[] = $id;
			}
		}

		if (!empty($deleteEIds)) {
			$addDIds = $this->db->array_query_assoc('
				SELECT id FROM ' . $this->table('Days') . '
				WHERE event_id IN (' . implode(',', $deleteEIds) . ') AND is_live!=-1
			');
			foreach ($addDIds as $day) {
				$deleteDIds[] = $day['id'];
			}
			$deleteDIds = array_unique($deleteDIds);
		}

		if (!empty($deleteDIds)) {
			$this->db->query('
				UPDATE ' . $this->table('Days') . '
				SET is_live=-1, updated_on=' . time() . '
				WHERE id IN (' . implode(',', $deleteDIds) . ')
			');
			/*
			$this->db->query('
				UPDATE ' . $this->table('Days') . '
				SET is_live=-1
				WHERE is_empty=0 AND id IN (' . implode(',', $deleteDIds) . ')
			');
			$this->db->query('
				DELETE FROM ' . $this->table('Days') . '
				WHERE is_empty=1 AND id IN (' . implode(',', $deleteDIds) . ')
			');
			 */
			blame($this->my('fullname'), 'Deleted days', $deleteDIds);
			foreach ($deleteDIds as $id) {
				// since delete is soft, the row is still there
				$delData = $this->db->single_query_assoc('
					SELECT tournament_id, event_id FROM ' . $this->table('Days') . '
					WHERE id=' . intval($id) . '
				');
				livereporting_adm_alt_log($delData['tournament_id'], $delData['event_id'], $id, 'update', 'days', $id, 'softdel');
			}
		}

		if (!empty($deleteEIds)) {
			$this->db->query('
				UPDATE ' . $this->table('Events') . '
				SET is_live=-1, updated_on=' . time() . '
				WHERE id IN (' . implode(',', $deleteEIds) . ')
			');
			blame($this->my('fullname'), 'Deleted events', $deleteEIds);
			foreach ($deleteEIds as $id) {
				// since delete is soft, the row is still there
				$delData = $this->db->single_query_assoc('
					SELECT tournament_id FROM ' . $this->table('Events') . '
					WHERE id=' . intval($id) . '
				');
				livereporting_adm_alt_log($delData['tournament_id'], $id, 0, 'update', 'events', $id, 'softdel');
			}
		}

		moon_memcache::getInstance()->delete(moon_memcache::getRecommendedPrefix() . 'reporting.events_uris');

		//$calc = $this->object('calc');
		//$calc->recalc();
	}

	function getInteger_($i) {
		if (preg_match('/^[\-+]?[0-9]+$/', $i)) {
			return intval($i);
		} else {
			return NULL;
		}
	}

	function overwriteArray_($base, $extend)
	{
		foreach ($base as $key => $value) {
			if (isset($extend[$key])) {
				$base[$key] = $extend[$key];
			}
		}

		return $base;
	}

	function extendArray_($base, $extend)
	{
		if (!is_array($base)) {
			$base = array();
		}
		if (!is_array($extend)) {
			$extend = array();
		}
		return array_merge($base, $extend);
	}
}