<?php

include_class('moon_memcache');

class tour_list extends moon_com
{
	function onload()
	{}

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
				$form->names('id', 'name', 'from_date', 'duration',
				    'currency', 'intro', 'tour', 'is_live', 'alias', 'logo_bgcolor', 'logo_is_dark',
				    'delete_logo_big_bg', 'delete_logo_small', 'timezone', 'place', 'address', 'geolocation',
				    'sync_id','is_syncable','autopublish',
				    'delete_logo_mid', 'delete_logo_idx', 'delete_logo_mobile_1', 'delete_logo_mobile_2',
				    'skin', 'ad_rooms', 'priority', 'stayhere');
				$form->fill($_POST);
				$data = $form->get_values();

				foreach (array(
					'logo_mid',
					'logo_big_bg',
					'logo_small',
					'logo_idx',
					'logo_mobile_1',
					'logo_mobile_2',
				) as $k) {
					$file[$k] = new moon_file;
					$data[$k] = $file[$k]->is_upload($k, $fe = NULL)
						? $file[$k]
						: NULL;
				}

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
					foreach (array(
						'logo_mid',
						'logo_big_bg',
						'logo_small',
						'logo_idx',
						'logo_mobile_1',
						'logo_mobile_2'
					) as $k) {
						if (isset($data[$k])) {
							unset($data[$k]);
						}
					}
					$gData['one-run']['failed-form-data'] = $data;
					$page->set_global($this->my('fullname'), $gData);
					switch ($data['id']) {
						case '':
							$this->redirect('#new');
						default:
							$this->redirect('#', $data['id']);
					}
				} else {
					switch ($data['stayhere']) {
						case '':
							$this->redirect('#');
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
				$this->redirect('#');
				break;

			case 'new':
				$this->set_var('render', 'entry');
				break;
/*
			case 'recalc':
				$calc = $this->object('calc');
				$calc->recalc();
				exit;*/

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

		$mainArgv  = array(
			'url.add_entry' => $this->linkas('#new'),
			'event.delete'  => $this->my('fullname') . '#delete',
			'list.entries'  => '',
			'url.tours' => $this->linkas('tours#')
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
				'misswinners' => $tour['misswinners'],
				'status' => $tour['is_live']
					? $statuses[$tour['state']]
					: '',
				'name' => htmlspecialchars($tour['name']),
				'url' => $this->linkas('#', $tour['id']),
				'urlevents' => $this->linkas('event_list#', 'by-tour.' . $tour['id']),
			));
		}

		return $tpl->parse('list:main', $mainArgv);
	}

	function getTours()
	{
		$tours = $this->db->array_query_assoc('
			SELECT t.id, t.from_date, t.timezone, t.duration, t.name, t.state, t.is_live, COUNT(e.id) events, COUNT(e.id) - COUNT(w.id) misswinners
			FROM ' . $this->table('Tournaments') . ' t
			LEFT JOIN ' . $this->table('Events') . ' e
				ON t.id=e.tournament_id AND e.is_live>=0
			LEFT JOIN ' . $this->table('Winners') . ' w
				ON w.event_id=e.id AND w.winner!=""
			WHERE t.is_live>=0
			GROUP BY t.id
			ORDER BY t.from_date DESC, id DESC
		');
		return $tours;
	}

	function renderEntry_($argv, &$e)
	{
		$page     = &moon::page();
		$tpl      = &$this->load_template();
		$mainArgv  = array(
			'url.back' => $this->linkas('#'),
			'event.save' => $this->my('fullname') . '#save',
			'stayhere' => !empty($argv['form-stay'])
		);
		$locale = &moon::locale();


		$page->js('/js/modules_adm/ng-entry.js');
		$page->js('/js/modules_adm/livereporting.js');
		$page->js('/js/jquery/maskedinput-1.1.3.js');
		$page->js('http://maps.google.com/maps/api/js?sensor=false');

		if (NULL === $argv['id']) {
			$entryData = array(
				'id' => '',
				'name' => '',
				'logo_mid' => '',
				'logo_bgcolor' => '#000000',
				'logo_big_bg' => '',
				'logo_idx' => '',
				'logo_small' => '',
				'logo_is_dark' => '',
				'logo_mobile_1' => '',
				'logo_mobile_2' => '',
				'timezone' => '',
				'skin' => '',
				'currency' => 'USD',
				'intro' => '',
				'from_date' => '',
				'duration' => '',
				'alias' => '',
				'place' => '',
				'address' => '',
				'geolocation' => '',
				'is_live' => '1',
				'tour' => '',
				'ad_rooms' => '',
				'sync_id' => '',
				'is_syncable' => '',
				'autopublish' => '',
				'priority' => ''
			);
			$mainArgv['title'] = 'New tournament';
		} else {
			if (NULL === ($entryData = $this->getEntry_($argv['id']))) {
				$messages = $tpl->parse_array('messages');
				$e  = $messages['e.entry_not_found'];
				return ;
			}
			$locale = &moon::locale();
			list($tzOffset) = $locale->timezone($entryData['timezone']);
			foreach (array('from_date') as $key) {
				$entryData[$key] += $tzOffset;
			}
			$entryData = $this->extendArray_($entryData, array(
				'from_date' => gmdate('Y-m-d', $entryData['from_date']),
				'logo_big_bg' => !empty($entryData['logo_big_bg'])
					? $this->get_dir('web:LogosBigBg') . $entryData['logo_big_bg']
					: '',
				'logo_small' => !empty($entryData['logo_small'])
					? $this->get_dir('web:LogosSmall') . $entryData['logo_small']
					: '',
				'logo_mid' => !empty($entryData['logo_mid'])
					? $this->get_dir('web:LogosMid') . $entryData['logo_mid']
					: '',
				'logo_idx' => !empty($entryData['logo_idx'])
					? $this->get_dir('web:LogosIdx') . $entryData['logo_idx']
					: '',
				'logo_mobile_1' => !empty($entryData['logo_mobile_1'])
					? $this->get_dir('web:LogosM1') . $entryData['logo_mobile_1']
					: '',
				'logo_mobile_2' => !empty($entryData['logo_mobile_2'])
					? $this->get_dir('web:LogosM2') . $entryData['logo_mobile_2']
					: '',
			));
			$mainArgv['title'] = htmlspecialchars($entryData['name']);
			$mainArgv['url.schedule'] = $this->linkas('event_list#', 'by-tour.' . $entryData['id']);
			if ($entryData['is_live'] >= 0) {
				$mainArgv['url.preview'] = moon::shared('sitemap')->getLink('reporting') . rawurldecode($entryData['alias']) . '/';
			}
		}
		if (isset($argv['failed-form-data'])) {
			$entryData = $this->overwriteArray_($entryData, $argv['failed-form-data']);
			$entryData['ad_rooms'] = is_array($entryData['ad_rooms'])
				? implode(',', $entryData['ad_rooms'])
				: $entryData['ad_rooms'];
		}

		$timezones = $locale->select_timezones();

		$mainArgv['mobile_provider'] = _SITE_ID_ == 'com';

		$mainArgv['list.timezones'] = '';
		foreach ($timezones as $timezoneId => $timezoneName) {
			$mainArgv['list.timezones'] .= $tpl->parse('timezones.item', array(
				'value' => $timezoneId,
				'name'  => htmlspecialchars($timezoneName),
				'selected' => $timezoneId == $entryData['timezone']
			));
		}

		$currencies = $tpl->parse_array('currencies.data');
		$mainArgv['list.currencies'] = '';
		foreach ($currencies as $currencyId => $currencyName) {
			$mainArgv['list.currencies'] .= $tpl->parse('currencies.item', array(
				'value' => $currencyId,
				'name'  => htmlspecialchars($currencyName),
				'selected' => $currencyId == $entryData['currency']
			));
		}

		if (is_object( $rtf = $this->object('rtf') )) {
			$rtf->setInstance($this->get_var('rtf'));
			$mainArgv['entry.intro.toolbar'] = $rtf->toolbar('entryintro', (int)$argv['id']);
		}

		$tours = poker_tours();
		uasort($tours, array($this, 'pokerTourCmp'));
		$mainArgv['list.tours'] = '';
		foreach ($tours as $id => $tour) {
			$mainArgv['list.tours'] .= $tpl->parse('tours.item', array(
				'value' => $id,
				'name'  => htmlspecialchars($tour['title']),
				'selected' => $id == $entryData['tour']
			));
		}

		$rRooms = $this->db->array_query_assoc('
			SELECT id,name
			FROM '.$this->table('Rooms').'
			WHERE is_hidden=0
			ORDER BY name
		');
		$rooms = array();
		$split = ceil(count($rRooms) / 4);
		foreach ($rRooms as $k => $room) {
			$rooms[$k % $split][] = $room;
		}
		$rRooms = $rooms;
		$rooms = array();
		foreach ($rRooms as $rRoom) {
			foreach ($rRoom as $room) {
				$rooms[] = $room;
			}
		}

		$eRooms = explode(',', $entryData['ad_rooms']);
		$mainArgv['list.rooms'] = '';
		foreach ($rooms as $room) {
			$mainArgv['list.rooms'] .= $tpl->parse('rooms.item', array(
				'value' => $room['id'],
				'name'  => htmlspecialchars($room['name']),
				'selected' => in_array($room['id'], $eRooms)
			));
		}

		$skins = $tpl->parse_array('skins.data');
		$mainArgv['list.skins'] = '';
		foreach ($skins as $fbId => $fbName) {
			$mainArgv['list.skins'] .= $tpl->parse('skins.item', array(
				'value' => $fbId,
				'name'  => htmlspecialchars($fbName),
				'selected' => $fbId == $entryData['skin']
			));
		}

		foreach ($entryData as $key => $value) {
			$mainArgv['entry.' . $key] = htmlspecialchars($value);
		}

		return $tpl->parse('entry:main', $mainArgv);
	}

	private function pokerTourCmp($a, $b)
	{
		return strcmp($a['title'], $b['title']);
	}

	function getEntry_($id)
	{
		if (NULL === $this->getInteger_($id)) {
			return NULL;
		}
		$entry = $this->db->single_query_assoc('
			SELECT * FROM ' . $this->table('Tournaments') . '
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
		foreach (array(
				'id', 'alias', 'name', 'place', 'address', 'geolocation',
				'ad_rooms', 'skin', 'is_live', 'currency', 'intro', 'tour',
				'timezone', 'from_date', 'duration', 'logo_bgcolor', 'logo_is_dark',
				'sync_id', 'is_syncable', 'autopublish', 'priority'
			) as $key) {
			if (isset($data[$key])) {
				$saveData[$key] = $data[$key];
			}
		}

		$saveData['from_date'] = strtotime($saveData['from_date'] . ' 00:00:00 +0000');

		$adRooms = array();
		if (is_array($saveData['ad_rooms'])) {
			foreach ($saveData['ad_rooms'] as $adRoom) {
				if (intval($adRoom) != 0) {
					$adRooms[] = intval($adRoom);
				}
			}
		}
		$saveData['ad_rooms'] = implode(',', $adRooms);

		if (0 === count($saveData)) {
			return ;
		}

		foreach (array('tour', 'alias', 'intro', 'place', 'address', 'geolocation', 'ad_rooms','sync_id') as $key) {
			if (empty($saveData[$key])) {
				$saveData[$key] = NULL;
			}
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

		$required = array('name', 'from_date', 'duration');
		if (!empty($saveData['is_live'])) {
			//$required[] = 'place';
			$required[] = 'alias';
		}

		foreach ($required as $key) {
			if (empty($saveData[$key])) {
				$page->alert($messages['e.empty_' . $key]);
				$isInvalid = TRUE;
			}
		}
		$uriDupe = $this->db->single_query_assoc('
			SELECT COUNT(id) cid FROM ' . $this->table('Tournaments') . '
			WHERE is_live>0 AND alias="' . addslashes($saveData['alias'])  . '"' .
			(($saveData['id'] !== NULL)
				? ' AND id!=' . $saveData['id']
				: '') . '
		');
		if ('0' != $uriDupe['cid']) {
			$page->alert($messages['e.uri_duplicate']);
			$isInvalid = TRUE;
		}

		if (preg_match('~^[0-9a-f]{3,6}$~i', $saveData['logo_bgcolor'])) {
			$saveData['logo_bgcolor'] = '#' . $saveData['logo_bgcolor'];
		}

		if (TRUE === $isInvalid) {
			return NULL;
		}

		$locale = &moon::locale();
		list($tzOffset) = $locale->timezone($saveData['timezone']);
		foreach (array('from_date') as $key) {
			$saveData[$key] -= $tzOffset;
		}

		foreach (array(
			array('logo_mid',      'fs:LogosMid'),
			array('logo_big_bg',   'fs:LogosBigBg'),
			array('logo_small',    'fs:LogosSmall'),
			array('logo_idx',      'fs:LogosIdx'),
			array('logo_mobile_1', 'fs:LogosM1'),
			array('logo_mobile_2', 'fs:LogosM2'),
		) as $k) {
			if (NULL !== ($file[$k[0]] = $data[$k[0]]) && !$file[$k[0]]->has_extension('jpg,gif,png')) {
				$page->alert($messages['e.invalid_file']);
				$file[$k[0]] = NULL;
				$isInvalid = TRUE;
			}

			if (NULL !== $saveData['id'] && ( // existing tournament
				(NULL !== $file[$k[0]]) // and uploading file
				 ||
				(NULL === $file[$k[0]] && isset($data['delete_' . $k[0]]) && '' !== $data['delete_' . $k[0]])) // or checked "delete file"
			   )
			{
				$mediaDir = $this->get_dir($k[1]);
				$oldFile = $this->db->single_query_assoc('
					SELECT ' . $k[0] . ' as file FROM '.$this->table('Tournaments').'
					WHERE id=' . $saveData['id']
				);
				if (isset($oldFile['file'])) {
					$deleteFile = new moon_file;
					if ($deleteFile->is_file($mediaDir. $oldFile['file'])) {
						$deleteFile->delete();
					}
					$this->db->query('
						UPDATE '.$this->table('Tournaments').'
						SET ' . $k[0] . '=NULL
						WHERE id=' . $saveData['id']
					);
				}
			}
			if (NULL !== $file[$k[0]]) {
				$mediaDir = $this->get_dir($k[1]);
				$fileName = uniqid('') . '.' . $file[$k[0]]->file_ext();
				if ($file[$k[0]]->save_as($mediaDir . $fileName)) {
					$saveData[$k[0]]   = $fileName;
				} else {
					$page->alert($messages['e.file_save_error']);
					$isInvalid = TRUE;
				}
			}
		}

		if (TRUE === $isInvalid) {
			return NULL;
		}

		if (NULL === $saveData['id']) {
			$saveData['created_on'] = time();
			$this->db->insert($saveData, $this->table('Tournaments'));
			$rId = $this->db->insert_id();
			blame($this->my('fullname'), 'Created', $rId);
			livereporting_adm_alt_log($rId, 0, 0, 'insert', 'tournaments', $rId);
			moon_memcache::getInstance()->delete(moon_memcache::getRecommendedPrefix() . 'reporting.tourns_uris');
			return $rId;
		} else {
			// update event, day times
			list($tzNewOffset) = $locale->timezone($saveData['timezone']);
			$oldTimezone = $this->db->single_query_assoc('
				SELECT timezone FROM ' . $this->table('Tournaments') . '
				WHERE id="' . intval($saveData['id']) . '"
			');
			if (empty($oldTimezone)) {
				return ;
			}
			list($tzOldOffset) = $locale->timezone($oldTimezone['timezone']);
			$shift = $tzOldOffset - $tzNewOffset;
			if ($shift != 0) {
				if ($shift > 0) {
					$shift = '+' . $shift;
				} else {
					$shift = '-' . abs($shift);
				}
				$this->db->query('
					UPDATE ' . $this->table('Events') . '
					SET from_date=from_date' . $shift . ',
					    to_date=to_date' . $shift . ',
					    updated_on=' . time() . '
					WHERE tournament_id=' . intval($saveData['id']) . '
				');
				livereporting_adm_alt_log($saveData['id'], 0, 0, 'update', 'events', 0, 'tz upd');
				$this->db->query('
					UPDATE ' . $this->table('Days') . '
					SET day_date=day_date' . $shift . ',
					    updated_on=' . time() . '
					WHERE tournament_id=' . intval($saveData['id']) . '
				');
				livereporting_adm_alt_log($saveData['id'], 0, 0, 'update', 'days', 0, 'tz upd');
			}
			$saveData['updated_on'] = time();
			$this->db->update($saveData, $this->table('Tournaments'), array(
				'id' => $saveData['id']
			));
			livereporting_adm_alt_log($saveData['id'], 0, 0, 'update', 'tournaments', $saveData['id']);
			blame($this->my('fullname'), 'Updated', $saveData['id']);
			moon_memcache::getInstance()->delete(moon_memcache::getRecommendedPrefix() . 'reporting.tourns_uris');
			return $saveData['id'];
		}
	}

	function deleteEntry_($ids)
	{
		$deleteIds = array();
		if (!is_array($ids)) {
			$ids = array($ids);
		}
		foreach ($ids as $id) {
			if (NULL !== ($id = $this->getInteger_($id))) {
				$deleteIds[] = $id;
			}
		}
		if (empty($deleteIds)) {
			return ;
		}

		$this->db->query('
			UPDATE ' . $this->table('Tournaments') . '
			SET is_live=-1, updated_on=' . time() . '
			WHERE id IN (' . implode(',', $deleteIds) . ')
		');

		foreach ($deleteIds as $did) {
			livereporting_adm_alt_log($did, 0, 0, 'delete', 'tournaments', $did, 'soft');
		}
		blame($this->my('fullname'), 'Deleted', $deleteIds);
		moon_memcache::getInstance()->delete(moon_memcache::getRecommendedPrefix() . 'reporting.tourns_uris');
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