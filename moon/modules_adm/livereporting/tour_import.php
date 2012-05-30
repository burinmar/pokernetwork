<?php

include_class('moon_memcache');

class tour_import extends moon_com
{
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
				$form = $this->form();
				$form->names('id', 'events', 'event_ids');
				$form->fill($_POST);
				$this->redirectToSaveForm($form->get_values());
				exit;

			default:
				if (isset($argv[0]) && NULL !== ($id = $this->getInteger_($argv[0]))) {
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
		$window->active($this->my('module').'.tour_import');
		$page->title(
			$window->current_info('title')
		);

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

		$mainArgv  = array(
			'list.entries'  => '',
		);

		$statuses = array('<span class="scheduledIco" title="Scheduled"></span>', '<span class="liveIco" title="Started"></span>', '<span class="explain">Concluded</span>');
		$tours = $this->getTours();
		$locale = &moon::locale();
		foreach ($tours as $tour) {
			list($tzOffset) = $locale->timezone($tour['timezone']);
			$tour['from_date'] += $tzOffset;
			$mainArgv['list.entries'] .= $tpl->parse('list:entries.item', array(
				'id' => $tour['id'],
				'start_date' => strftime('%Y-%m-%d', $tour['from_date']),
				'events' => $tour['events'],
				'status' => $tour['is_live']
					? $statuses[$tour['state']]
					: '',
				'name' => htmlspecialchars($tour['name']),
				'url' => $this->linkas('#', $tour['id']),
			));
		}

		return $tpl->parse('list:main', $mainArgv);
	}

	function getSrcData()
	{
		include_class('moon_memcache');
		$mcd = moon_memcache::getInstance();
		$mcdKey = $mcd->getRecommendedPrefix() . 'adm.reporting.tour_import.data';

		if (FALSE == ($data = $mcd->get($mcdKey))) {
			$url = is_dev()
				? 'http://www.pokernews.dev/live-reporting/export.tournaments-homerun.xml'
				: 'http://www.pokernews.com/live-reporting/export.tournaments-homerun.xml';
			$ch = curl_init($url);
			curl_setopt($ch, CURLOPT_FAILONERROR, 1);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			$data = curl_exec($ch);

			$data_ = @simplexml_load_string($data);
			
			if (isset($data_->tournament)) {
				$mcd->set($mcdKey, $data, 0, 30);
			}
		}
		
		$data = @simplexml_load_string($data);
		
		return $data;
	}
	
	function getTours()
	{
		$result = array();
		$data = $this->getSrcData();
		
		if (!isset($data->tournament)) {
			return array();
		}
		
		foreach ($data->tournament as $row) {
			$result[] = array(
				'id' => (int)$row->id,
				'name' => (string)$row->name,
				'from_date' => (int)$row->from_date,
				'is_live' => (int)$row->is_live,
				'state' => (int)$row->state,
				'timezone' => (int)$row->timezone,
				'events' => count($row->event),
			);
		}
		
		return $result;
	}

	function renderEntry_($argv, &$e)
	{
		$page     = &moon::page();
		$tpl      = &$this->load_template();
		$mainArgv  = array(
			'url.back' => $this->linkas('#'),
			'event.save' => $this->my('fullname') . '#save',
			'list.events' => ''
		);
		$locale = &moon::locale();


		if (NULL === ($entryData = $this->getEntry_($argv['id']))) {
			$messages = $tpl->parse_array('messages');
			$e  = $messages['e.entry_not_found'];
			return ;
		}
		
		$mainArgv['title'] = htmlspecialchars($entryData['name']);

		$mainArgv['events_all'] = $entryData['sync'] == 'all';
		$mainArgv['events_selected'] = !$mainArgv['events_all'];
		
		foreach ($entryData['events'] as $event) {
			$mainArgv['list.events'] .= $tpl->parse('entry:events.item', array(
				'id' => $event['id'],
				'name' => htmlspecialchars($event['name']),
				'sync' => !empty($event['sync'])
			));
		}
		unset($entryData['events']);
		
		foreach ($entryData as $key => $value) {
			$mainArgv['entry.' . $key] = htmlspecialchars($value);
		}

		return $tpl->parse('entry:main', $mainArgv);
	}

	function getEntry_($id)
	{
		if (NULL === $this->getInteger_($id)) {
			return ;
		}

		$data = $this->getSrcData();
		$entry = $data->xpath('//tournament[id=' . $id . ']');
		if (0 == count($entry)) {
			return ;
		}
		$entry = $entry[0];
		
		$result = array(
			'id' => (int)$entry->id,
			'from_date' => (int)$entry->from_date,
			'name' => (string)$entry->name,
			'duration' => (string)$entry->duration,
			'sync' => 'main-event',
			'events' => array()
		);
		foreach ($entry->event as $event) {
			$result['events'][] = array(
				'id' => (int)$event->id,
				'name' => (string)$event->name,
			);
		}
		
		$existing = $this->db->single_query_assoc('
			SELECT id, sync_id FROM ' . $this->table('Tournaments') . '
			WHERE sync_id LIKE "com:' . $id . '%"
				AND is_live!=-1
				ORDER BY id DESC
				LIMIT 1
		');
		if (!empty($existing)) {
			preg_match('~{([0-9,]*)}~', $existing['sync_id'], $syncEvents);
			if (isset($syncEvents[1])) {
				$result['sync'] = 'selected';
				$syncEvents = explode(',', $syncEvents[1]);
			} else {
				$result['sync'] = 'all';
			}
			$result['local_id'] = $existing['id'];
		}
		
		switch ($result['sync']) {
		case 'all':
		case 'main-event':
			$id = $entry->xpath('event/id[@is_main]');
			if (isset($id[0])) {
				$id = (int)$id[0];
				foreach ($result['events'] as $k => $ev) {
					if ($ev['id'] == $id) {
						$result['events'][$k]['sync'] = true;
					}
				}
			}
			break;
		case 'selected':
			foreach ($result['events'] as $k => $ev) {
				if (in_array($ev['id'], $syncEvents)) {
					$result['events'][$k]['sync'] = true;
				}
			}
			break;
		}
		
		return $result;		
	}

	/* data *must* be an array of strings */
	function redirectToSaveForm($argv)
	{
		$page = moon::page();
		if (NULL === ($entryData = $this->getEntry_($argv['id']))) {
			$messages = $tpl->parse_array('messages');
			$e  = $messages['e.entry_not_found'];
			return ;
		}
		
		$gData = $page->get_global($this->my('module') . '.tour_list');
		if ('' === $gData) {
			$gData = array();
		}
		$gData['one-run'] = array();
		$gData['one-run']['failed-form-data'] = array(
			'name' => $entryData['name'],
			'duration' => $entryData['duration'],
			'sync_id' => 'com:' . $entryData['id']
		);
		if ($argv['events'] == 'events_selected') {
			$gData['one-run']['failed-form-data']['sync_id'] .= '{' . implode(',', $argv['event_ids']) . '}';
		};
		if (empty($entryData['local_id'])) {
			$gData['one-run']['failed-form-data'] += array(
				'is_live' => 0,
				'from_date' => strftime('%Y-%m-%d', $entryData['from_date'])
			);
		}
		$page->set_global($this->my('module') . '.tour_list', $gData);
		
		if (!empty($entryData['local_id'])) {
			$this->redirect('livereporting.tour_list', $entryData['local_id']);
		} else {
			$this->redirect('livereporting.tour_list#new');
		}
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