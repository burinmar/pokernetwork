<?php
/**
 * @package livereporting
 */
/**
 */
require_once 'livereporting_event_pylon.php';
/**
 * @package livereporting
 */
class livereporting_event_chips extends livereporting_event_pylon
{
	protected function synthEvent($event, $argv)
	{
		switch ($event) {
			case 'save-ctchips': // chips tab chips
				$data = $this->helperEventGetData(array('data', 'sub', 'day_id', 'sort_key'));
				$data['event_id'] = $this->requestArgv('event_id');
				if ($this->requestArgv('day_id') != $data['day_id']) {
					moon::page()->page404();
				}
				switch ($data['sub']) {
					case 'chips':
						if (FALSE !== ($result = $this->renderSaveSingle($data))) {
							echo json_encode($result);
						}
						moon_close();
						exit;

					case 'newplayer':
						if (FALSE !== ($result = $this->renderSaveNewPlayer($data))) {
							echo json_encode($result);
						}
						moon_close();
						exit;

					case 'delplayer':
						if (FALSE !== ($result = $this->renderSaveDelPlayer($data))) {
							echo json_encode($result);
						}
						moon_close();
						exit;
				}
				moon::page()->page404();
			
			case 'save-chips': // chips post
				$data = $this->helperEventGetData(array(
					'day_id', 'import_id', 'column_order', 'is_full_listing', 'published', 'import_textarea', 'title', 'intro', 'tags', 'is_exportable', 'is_keyhand', 'datetime_options'
				));
				$this->helperEventGetLeadingImage($data);
				$this->helperEventGetChipsData($data);
				$this->chipsSort($data['chips']);
				
				// import wsop feed chips (does not need all $data)
				if (isset($_POST['import']) && $_POST['import'] == '1') {
					header('content-type: text/plain; charset=utf-8');
					echo json_encode(
						  $this->renderChipSavePreviewWXML($data)
					);
					moon_close();
					exit;
				}
				// preview
				if (!isset($_POST['save'])) {
					header('content-type: text/html; charset=utf-8');
					echo $this->renderSavePreview($data);
					moon_close();
					exit;
				}
				
				$chipsId = $this->save($data);
				$this->redirectAfterSave($chipsId, 'chips');
				exit;

			case 'delete':
				if (FALSE === $this->delete($argv['uri']['argv'][2])) {
					moon::page()->page404();
				}
				$this->redirectAfterDelete($argv['event_id'], $this->requestArgv('day_id'));
				exit;
			
			default:
				moon::page()->page404();
		}
	}
	
	protected function render($data, $argv = NULL)
	{
		switch ($argv['variation']) {
		case 'logControl':
			return $this->renderControl(array_merge($data, array(
				'wsopimport' => in_array($data['tournament_id'], $this->get_var('wsopxml')),
				'unhide' => (!empty($_GET['master']) && $_GET['master'] == 'chips'),
				'is_keyhand' => 0,
				'is_exportable' => 1
			)));

		case 'logTab':
			return $this->renderLogTab($data, $argv);

		case 'logEntry':
			return $this->renderLogEntry($data, $argv);

		case 'individual':
			return $this->renderIndividualEntry($data, $argv);
		}
	}

	private function renderLogEntry(&$data, $argv)
	{
		$lrep   = $this->lrep();
		$lrepTools = $lrep->instTools();
		$tpl = $this->load_template();
		
		$rArgv = $this->helperRenderCommonArgv($data, $argv, $tpl);
		$rArgv += array(
			'is_full'    => $data['contents']['is_full_import'],
			'is_preview' => $data['contents']['chipstotal'] > count($data['contents']['chips']),
		);
		
		$playerUrl = !empty($rArgv['show_controls'])
			? $lrep->makeUri('event#view', array(
				'event_id' => $data['event_id'],
				'type' => 'profile',
				'id' => '{}'
			)) : NULL;

		//if (!$data['contents']['is_full_import'])
		$this->chipsSort($data['contents']['chips']);
		
		$rArgv['entries'] = '';
		foreach ($data['contents']['chips'] as $k => $chip) {
			$chipsArgv = $this->helperRenderChipArgv($chip, $k, !empty($data['contents']['is_full_import']));
			$chipsArgv = array_merge($chipsArgv, array(
				'player' => htmlspecialchars($chip['uname']),
				'player_url' => $playerUrl && $chip['id']
					? str_replace('{}', $chip['id'], $playerUrl)
					: '',
				'player_sponsorimg' => isset($chip['sponsor']['ico'])
					? (isset($chip['sponsor']['id']) && $chip['sponsor']['id'] > 0
						? img('rw', $chip['sponsor']['id'], $chip['sponsor']['ico'])
						: $chip['sponsor']['ico'])
					: NULL,
				'player_sponsor' => $lrepTools->helperPlayerStatus(
					isset($chip['status'])  ? $chip['status'] : '',
					isset($chip['sponsor'])	? $chip['sponsor']['name'] : ''),
				'player_is_pnews' => !empty($chip['ispn']),
				'country' => !empty($chip['country_id'])
					? htmlspecialchars($chip['country_id'])
					: '',
				'country_id' => !empty($chip['country_id'])
					? htmlspecialchars(strtolower($chip['country_id']))
					: ''
			));
			$rArgv['entries'] .= $tpl->parse('logEntry:chips.chips.item', $chipsArgv);
		}
		
		return $tpl->parse('logEntry:chips', $rArgv);
	}

	private function renderIndividualEntry(&$data, $argv)
	{
		$lrep = $this->lrep();
		$page = moon::page();
		$tpl = $this->load_template();
		
		$rArgv = $this->helperRenderCommonArgv($data, $argv, $tpl);

		if (NULL === ($entry = $this->getEditableData(
			$data['id'],
			$data['event_id'],
			$rArgv['show_controls']
		))) {
			// sub_chips entry lost, editing chips not available
			$entry['chips'] = array();
			if (time() - $data['created_on'] < 3600*24*30) { // whine for 1 month
				moon::error('Reporting chips: damaged entry: ' . $data['id']);
			}
			if ($rArgv['show_controls']) {
				$rArgv['show_controls'] = false;
				$rArgv['title'] = '(adm: damaged entry)';
			}
		};
		$chipsTxt = array();
		foreach ($entry['chips'] as $nr => $chip) {
			$chipsTxt[] = isset($chip['uname'])
				? htmlspecialchars($chip['uname']) . "\t" . $chip['chips']
				: '???(unknown ' . (-$nr + 1) . ')' . "\t" . $chip['chips'];
		}

		if ($rArgv['show_controls']) {
			$lastFullChips = $this->getLastFullChipsId($data['event_id']);
			$rArgv['control'] = $this->renderControl(array(
				'unhide' => ($argv['action'] == 'edit'),
				'bundled_control' => ($argv['action'] != 'edit'),
				'keep_old_dt' => true,
				'event_id'    => $entry['event_id'],
				'day_id'    => $entry['day_id'],
				'import_id' => $entry['id'],
				'title'     => $entry['title'],
				'intro'     => $entry['contents'],
				'tags'     => implode(', ', $entry['tags']),
				'created_on'=> $entry['created_on'],
				'published' => empty($entry['is_hidden']),
				'is_exportable' => $entry['is_exportable'],
				'is_keyhand' => $entry['is_keyhand'],
				'fulllist'  => !empty($entry['is_full_import']),
				'tzName' => $data['tzName'],
				'tzOffset' => $data['tzOffset'],
				'chips_txt' => implode("\n", $chipsTxt),
				'fullist_change_disabled' => intval($lastFullChips) == intval($entry['id']),
				'i_src' => $entry['image_src'],
				'i_alt' => $entry['image_alt'],
				'i_misc'=> $entry['image_misc']
			));
		}

		$playerUrl = $rArgv['show_controls']
			? $lrep->makeUri('event#view', array(
				'event_id' => $data['event_id'],
				'type' => 'profile',
				'id' => '{}'
			)) : NULL;
		
		//if (!$data['contents']['is_full_import'])
		$this->chipsSort($entry['chips']);

		$rArgv['entries'] = '';
		foreach ($entry['chips'] as $k => $chip) {
			$chipsArgv = $this->helperRenderChipArgv($chip, $k, !empty($data['contents']['is_full_import']));
			if (isset($chip['uname'])) {
				$chipsArgv = array_merge($chipsArgv, array(
					'player' => htmlspecialchars($chip['uname']),
					'player_url' => $playerUrl
						? str_replace('{}', $chip['id'], $playerUrl)
						: '',
					'player_sponsor' => $chip['sponsor'],
					'player_sponsorimg' => !empty($chip['sponsorimg'])
						? ($chip['sponsor_id'] > 0
							? img('rw', @$chip['sponsor_id'], $chip['sponsorimg'])
							: $chip['sponsorimg'])
						: NULL,
					'player_sponsorurl' =>  $chip['sponsorurl'],
					'player_status'  =>  $lrep->instTools()->helperPlayerStatus($chip['status'], $chip['sponsor']),
					'player_is_pnews' => !empty($chip['ispn']),
					'country' => !empty($chip['country_id'])
						? htmlspecialchars($chip['country_id'])
						: '',
					'country_id' => !empty($chip['country_id'])
						? htmlspecialchars(strtolower($chip['country_id']))
						: ''
				));
			}
			$rArgv['entries'] .= $tpl->parse('entry:chips.chips.item', $chipsArgv);
		}

		$page->title($page->title() . ' | ' . $rArgv['title']);
		$this->helperRenderOGMeta($rArgv);
		return $tpl->parse('entry:chips', $rArgv);
	}

	private function helperRenderChipArgv($chip, $k, $isFullImport)
	{
		$chipsArgv = array(
			'evenodd' => $k % 2
				? 'even'
				: 'odd',
			'is_busted' => intval($chip['chips']) == 0,
			'chips' => number_format($chip['chips']),
			'chips_change' => $chip['chipsc'] !== NULL
				? number_format($chip['chipsc'])
				: '',
			'chips_change_direction' => $chip['chipsc'] != 0
				? (intval($chip['chipsc']) > 0
					? 'pos'
					: 'neg')
				: NULL,
			'place' => $isFullImport
				? $k+1
				: '',
			'player' => NULL
		);
		
		return $chipsArgv;
	}
	
	private function renderLogTab($data, $argv)
	{
		$tpl = $this->load_template('livereporting_event_chips_tab');
		$locale = moon::locale();
		$lrep = $this->lrep();
		$page = moon::page();
		$text   = moon::shared('text');
		$eventInfo = $lrep->instEventModel('_src_event')->getEventData($this->requestArgv('event_id'));
		$isAdm = $page->get_global('adminView') && $lrep->instTools()->isAllowed('writeContent');
		$isActiveDay = false;

		if ($isAdm && !$eventInfo['synced']) {
			$days = $lrep->instEventModel('_src_event')->getDaysData($data['event_id']);
			$isActiveDay = isset($days[$data['day_id']]) && $days[$data['day_id']]['state'] == 1;
		}

		$entry['chips'] = $lrep->instEventModel('_src_event')->getLastTodayChips($data['event_id'], $data['day_id']);
		$rArgv = array();

		if ($isAdm) {
			if (isset($argv['sort_key'])) {
				$sortKey = intval($argv['sort_key']);
			} else {
				$sortKey = isset($_GET['sort'])
					? intval($_GET['sort'])
					: 1;
			}
			$sortParams = $this->helperLogTabSort($entry['chips'], $sortKey);
			$playerUrl_ = $lrep->makeUri('event#view', array(
					'event_id' => $data['event_id'],
					'type' => 'profile',
					'id' => '{}'
				));
			$rArgv += array(
				'cts_name_url' => $lrep->makeUri('event#view', array(
						'event_id' => $data['event_id'],
						'path' => $this->getUriPath(),
						'leaf' => $this->getUriTab()
					), $this->getUriFilter($sortParams['nameUrl'])
				),
				'cts_name_class' => $sortParams['nameActive'] == true
					? 'active'
					: '',
				'cts_chip_url' =>  $lrep->makeUri('event#view', array(
						'event_id' => $data['event_id'],
						'path' => $this->getUriPath(),
						'leaf' => $this->getUriTab()
					), $this->getUriFilter($sortParams['chipUrl'])
				),
				'cts_chip_class' => $sortParams['chipActive'] == true
					? 'active'
					: '',
				'cts_sort' => $sortParams['key']
			);
		} else {
			$playerUrl_ = moon::shared('sitemap')->getLink('poker-players') . '{}/';
			$pplayers = array();
			foreach ($entry['chips'] as $chip) {
				if (!empty($chip['pp_id'])) {
					$pplayers[] = $chip['pp_id'];
				}
			}
			if (0 != count($pplayers)) {
				$pplayers = $this->db->array_query_assoc('
					SELECT id, uri FROM ' . $this->table('PlayersPoker') . '
					WHERE id IN (' . implode(',', $pplayers) . ')
				', 'id');
			} else {
				$pplayers = array();
			}
		}

		$chipsLastUpdatedOn = 0;
		$chips = '';
		$k = 0;
		foreach ($entry['chips'] as $chip) {
			$chipsArgv = array(
				'adm' => $isAdm && $isActiveDay,
				'evenodd' => $k % 2
					? 'even'
					: 'odd',
				'is_busted'    => $chip['chips'] !== NULL && intval($chip['chips']) == 0,
				'is_nreported' => $chip['chips'] === NULL,
				'chips' => number_format($chip['chips']),
				'chips_change' => $chip['chipsc'] !== NULL
					? number_format($chip['chipsc'])
					: '',
				'chips_change_direction' => $chip['chipsc'] != 0
					?(intval($chip['chipsc']) > 0
						? 'pos'
						: 'neg')
					: NULL,
			);

			if (isset($chip['uname'])) {
				if ($isAdm && $isActiveDay) {
					$newCtrl = $tpl->parse('logTab:chips.chips.item.new_ctrl', array('playerid' => $chip['id']));
					$delCtrl = $tpl->parse('logTab:chips.chips.item.del_ctrl', array('playerid' => $chip['id']));
					$dtCtrl = '';
				} else {
					$newCtrl = '';
					$dtCtrl = '';
					$delCtrl = '';
				}
				if ($isAdm) {
					$playerUrl = str_replace('{}', $chip['id'], $playerUrl_);
					if (!empty($chip['created_on'])) {
						$createdOn = $text->ago($chip['created_on'], true, false);
						if ($createdOn == '') {
							$createdOn = $locale->gmdatef($chip['created_on'] + $eventInfo['tzOffset'], 'ReportingShort') /* . ' ' . $data['tzName'] */;
						}
						$createdOnOld = !empty($chip['created_on'])
							? (time() - $chip['created_on'] > 30*60)
							: false;
						$dtCtrl = $tpl->parse('logTab:chips.chips.item.dt_ctrl', array(
							'created_on' => $createdOn,
							'created_on_old' => $createdOnOld,
						));
					}
				} else {
					$playerUrl = !empty($chip['pp_id']) && isset($pplayers[$chip['pp_id']])
						? str_replace('{}', $pplayers[$chip['pp_id']]['uri'], $playerUrl_)
						: '';
				}
				$chipsArgv += array(
					'player' => htmlspecialchars($chip['uname']),
					'playerid' => $chip['id'],
					'playerurl' => $playerUrl,
					'sponsor' => '',
					'adm_newctrl' => $newCtrl,
					'adm_dtctrl' => $dtCtrl,
					'adm_delctrl' => $delCtrl,
					'country' => !empty($chip['country_id'])
						? htmlspecialchars($chip['country_id'])
						: '',
					'country_id' => !empty($chip['country_id'])
						? htmlspecialchars(strtolower($chip['country_id']))
						: ''
				);
				$chipsArgv['sponsor'] = trim($tpl->parse('logTab:chips.chips.item.sponsor', array(
					'sponsorimg' => !empty($chip['sponsorimg'])
						? ($chip['sponsor_id'] > 0 
							? img('rw', $chip['sponsor_id'], $chip['sponsorimg'])
							: $chip['sponsorimg'])
						: null,
					'sponsorurl' =>  $chip['sponsorurl'],
					'sponsor' => $chip['sponsor'],
					'status'  => $lrep->instTools()->helperPlayerStatus($chip['status'], $chip['sponsor']),
					'is_pnews' => $chip['is_pnews'],
				)));
			} else {
				$chipsArgv += array(
					'player' => ''
				);
			}
			$k++;
			$chips .= $tpl->parse('logTab:chips.chips.item', $chipsArgv);
			
			$chipsLastUpdatedOn = max($chipsLastUpdatedOn, $chip['created_on']);
		}
		$rArgv['entries'] = $chips;
		
		$rArgv['adm'] = $isAdm && $isActiveDay;
		if ($rArgv['adm']) {
			$rArgv['write_ctchips_url'] = htmlspecialchars_decode($lrep->makeUri('event#save', array(
				'event_id' => $data['event_id'],
				'path' => $this->getUriPath(),
				'type' => 'chips',
				'id' => 'ctchips'
			), $this->getUriFilter(NULL, TRUE)));
			$rArgv['write_ctchips_day'] = $data['day_id'];
		}

		if (isset($argv['only_entries'])) {
			return $rArgv['entries'];
		}
		
		if ($chipsLastUpdatedOn) {
			$createdOn = $text->ago($chipsLastUpdatedOn);
			if ($createdOn == '' || $chipsLastUpdatedOn > time()) {
				$createdOn = $locale->gmdatef($chipsLastUpdatedOn + $eventInfo['tzOffset'], 'Reporting') . ' ' . $eventInfo['tzName'];
			}
			$rArgv['chips_last_updated'] = $createdOn;
			
		}

		return $tpl->parse('logTab:main', $rArgv);
	}

	private function chipsNameCmp($a, $b)
	{
		$key = 'uname';
		return strcasecmp($a[$key], $b[$key]);
	}

	private function chipsNameSort(&$chips)
	{
		usort($chips, array($this, 'chipsNameCmp'));
	}

	private function helperLogTabSort(&$chips, $sortKey)
	{
		$nameDesc = NULL;
		$chipDesc = NULL;

		switch ($sortKey) {
			case 2:
				$chipDesc = FALSE;
				$chips = array_reverse($chips);
				break;
			case 3:
				$nameDesc = TRUE;
				$this->chipsNameSort($chips);
				break;
			case 4:
				$nameDesc = FALSE;
				$this->chipsNameSort($chips);
				$chips = array_reverse($chips);
				break;
			case 1:
			default:
				$chipDesc = TRUE;
		}
		return array(
			'nameActive' => $nameDesc !== NULL,
			'chipActive' => $chipDesc !== NULL,
			'nameUrl' => array(
				'sort' => $nameDesc === NULL
					? 3
					: ($nameDesc ? 4 : 3),
			),
			'chipUrl' => array(
				'sort' => $chipDesc === NULL
					? 1
					: ($chipDesc ? 2 : 1)
			),
			'key' => $sortKey
		);
	}

	private function renderControl($argv)
	{
		if (empty($argv['day_id'])) {
			return ;
		}
		$tpl = $this->load_template();
		$lrep = $this->lrep();
		$eventInfo = $lrep->instEventModel('_src_event')->getEventData($this->requestArgv('event_id'));

		$controlsArgv = array(
			'cc.unhide'     => !empty($argv['unhide']),
			'cc.bundled_control' => !empty($argv['bundled_control']),
			'cc.save_event' => $this->parent->my('fullname') . '#save-chips',
			'cc.day_id'     => $argv['day_id'],
			'cc.event_id'   => $argv['event_id'],
			'cc.import_id'  => isset($argv['import_id'])
				? intval($argv['import_id'])
				: '',
			'cc.show_full_controls' => !$eventInfo['synced'],
			'cc.skip_must_preview' => isset($argv['import_id']),// && empty($argv['fulllist']),
			'cc.chips'	=> !empty($argv['chips_txt'])
				? $argv['chips_txt']
				: '',
			'cc.title'	=> isset($argv['title'])
				? htmlspecialchars($argv['title'])
				: '',
			'cc.intro'	=> isset($argv['intro'])
				? htmlspecialchars($argv['intro'])
				: '',
			'cc.tags' => isset($argv['tags'])
				? htmlspecialchars($argv['tags'])
				: '',
			'cc.fulllist'  => !empty($argv['fulllist']),
			'cc.published' => !empty($argv['published']),
			'cc.is_exportable' => !empty($argv['is_exportable']),
			'cc.is_keyhand' => $argv['is_keyhand'],
			'cc.datetime_options' => '',
			'cc.custom_datetime' => $lrep->instTools()->helperCustomDatetimeWrite('+Y #m +d +H:M -S -z', (isset($argv['created_on']) ? intval($argv['created_on']) : time()) + $argv['tzOffset'], $argv['tzOffset']),
			'cc.custom_tz' => $argv['tzName'],
			'cc.fullist_change_disabled' => !empty($argv['fullist_change_disabled'])
		);
		if (!empty($argv['wsopimport'])) {
			$controlsArgv['cc.wsopimport'] = true;
		}
		if (is_object( $rtf = $this->object('rtf') )) {
			$rtf->setInstance($this->get_var('rtf') . ':1');
			$controlsArgv['cc.toolbar'] = $rtf->toolbar('rq-wc-body', isset($argv['import_id']) ? intval($argv['import_id']) : '', array('noarticle'=>true));
		}

		$timeOptions = $tpl->parse_array('controls:chips.datetime_options.items');
		if (!isset($argv['keep_old_dt'])) {
			unset($timeOptions['old_dt']);
		}
		foreach ($timeOptions as $k => $v) {
			$controlsArgv['cc.datetime_options'] .= '<option value="' . $k . '">' . $v . '</option>';
		}

		$controlsArgv['cc.url.ipn'] = $this->get_var('ipnReadBase');
		$controlsArgv['cc.url.ipnpreview'] = 
			$lrep->makeUri('event#ipn-browse',
				array(
					'event_id' => getInteger($argv['event_id']),
					'path' => $this->getUriPath(),
				),
				array('x' => 'chips')
			);
		$controlsArgv['cc.url.ipnupload'] =
			$lrep->makeUri('event#ipn-upload',
				array(
					'event_id' => getInteger($argv['event_id']),
					'path' => $this->getUriPath(),
				),
				array('x' => 'chips')
			);
		if (!empty($argv['i_src'])) {
			$argv['i_misc'] = explode(',', $argv['i_misc']);
			$ipnImageId = $argv['i_misc'][0];
			array_shift($argv['i_misc']);
			$controlsArgv += array(
				'cc.ipnimageid' => $ipnImageId,
				'cc.ipnimagemisc' => implode(',', $argv['i_misc']),
				'cc.ipnimagetitle' => htmlspecialchars($argv['i_alt']),
				'cc.ipnimagesrc' => htmlspecialchars($argv['i_src'])
			);
		} else {
			$controlsArgv += array(
				'cc.ipnimageid' => '',
				'cc.ipnimagemisc' => '',
				'cc.ipnimagetitle' => '',
				'cc.ipnimagesrc' => ''
			);
		}

		return $tpl->parse('controls:chips', $controlsArgv);
	}

	private function renderChipSavePreviewWXML($data)
	{
		if (NULL == ($prereq = $this->helperSaveCheckPrerequisitesLocationByDay($data['day_id']))) {
			moon::page()->page404();
		}
		list ($location) = $prereq;
		if (!in_array($location['tournament_id'], $this->get_var('wsopxml'))) {
			moon::page()->page404();
		}

		$eventId = $this->object('livereporting_bluff')->bluffEventId($location['event_id']);

		$ch = curl_init('http://www.wsop.com/data/xml/wsop2010/EOD.aspx?EventID=' . $eventId . '&day=' . $location['day_name']);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
		curl_setopt($ch, CURLOPT_TIMEOUT, 60*20);
		curl_setopt($ch, CURLOPT_FAILONERROR, 1);
		$gotData = curl_exec($ch);
		if (curl_errno($ch)) {
			curl_close($ch);
			moon::page()->page404();
		}
		curl_close($ch);

		$xml = new SimpleXMLElement($gotData);

		if (!isset($xml->chip_count)) {
			moon::page()->page404();
		}
		
		// silently update players_bluff as well
		foreach ($xml->chip_count as $row) {
			$this->db->query('
				DELETE FROM ' . $this->table('PlayersBluff') . '
				WHERE event_id=' . intval($location['event_id']) . '
				  AND name="' . addslashes(trim((string)$row->player)) . '"
			');
			$this->db->query('
				DELETE FROM ' . $this->table('PlayersBluff') . '
				WHERE event_id=' . intval($location['event_id']) . '
				  AND bluff_id="' . intval($row->player['id']) . '"
			');
			$this->db->insert(array(
					'event_id' => $location['event_id'],
					'bluff_id' => (int)$row->player['id'],
					'name' => trim((string)$row->player),
					'city' => trim((string)$row->city),
					'country' => trim((string)$row->country),
					'state' => trim((string)$row->state)
				), $this->table('PlayersBluff'));
		}
		// and update reporting player profiles too
		$this->db->query('
			UPDATE ' . $this->table('Players') . ' p
			INNER JOIN (
				SELECT name, event_id, country FROM ' . $this->table('PlayersBluff') . '
				WHERE event_id=' . $location['event_id'] . '
			) pb
				ON p.event_id=pb.event_id AND p.name=pb.name
			SET p.country_id=pb.country
		');

		$data['chips'] = array();
		$chipss = array();
		foreach ($xml->chip_count as $row) {
			$data['chips'][] = array(
				1 => trim((string)$row->player),
				2 => (int)$row->amount
			);
			$chipss[] = trim((string)$row->player) . "\t" . (int)$row->amount;
		}

		return array(
			'preview' => $this->renderSavePreview($data),
			'data' => implode("\n", $chipss)
		);
	}

	/**
	 * @todo probably should drop place/position code
	 */
	private function renderSavePreview($data)
	{
		if (NULL == ($prereq = $this->helperSaveCheckPrerequisitesLocationByDay($data['day_id']))) {
			return;
		}
		list (
			$location
		) = $prereq;

		$lrep = $this->lrep();
		$tpl = $this->load_template();
		$previewArgv = array(
			'cc.chips' => ''
		);

		if ($data['datetime'] === NULL) {
			$entry = $this->db->single_query_assoc('
				SELECT created_on FROM  ' . $this->table('Log') . '
				WHERE id=' . getInteger($data['import_id']) . ' AND type="chips"
			');
			if (empty($entry)) {
				echo 'error';
				return;
			}
			$data['datetime'] = $entry['created_on'];
		}

		$players = $this->getPlayersData($location['event_id'], $location['day_id'], $data['datetime'], TRUE);
		$playerNames = array();
		foreach ($players as $k => $player) {
			$playerNames[$k] = strtolower($player['name']);
		}

		// always sync this section with save(preprocess) !
		$place = 1;
		$mentionedPlayers = array();
		$newPlayerNames = array();
		$oldPlayerNames = array();
		$lastFullChips = $this->getLastFullChipsId($location['event_id']);
		foreach ($data['chips'] as $row) {
			if (in_array(strtolower($row[1]), $playerNames)) {
				if (in_array(strtolower($row[1]), $oldPlayerNames)) {
					continue;
				}
				$oldPlayerNames[] = strtolower($row[1]);
				$player = $players[array_search(strtolower($row[1]), $playerNames)];
				$mentionedPlayers[] = $player['id'];
				if ($player['chips'] == NULL) {
					$player['chips_change'] = NULL;
				} else {
					$player['chips_change'] = $row[2] - $player['chips'];
				}
				$player['chips']  = isset($row[2])
						? intval($row[2])
						: NULL;
			} else {
				if (in_array(strtolower($row[1]), $newPlayerNames)) {
					continue;
				}
				$newPlayerNames[] = strtolower($row[1]);
				$player = array(
					'id' => NULL,
					'card' => NULL,
					'name' => $row[1],
					'chips' => isset($row[2])
						? intval($row[2])
						: NULL,
					'chips_change' => NULL,
					'sponsor_id' => NULL
				);
			}
			if ($data['is_full_listing'] && intval($player['chips']) == 0 && 
				($data['import_id'] == '' || intval($lastFullChips) == intval($data['import_id']))) { // checkme
				$player['place'] = $place;
				$player['chips'] = '';
			} else {
				$player['place'] = NULL;
			}
			if (substr($player['name'], 0, 3) != '???') {
				$previewArgv['cc.chips'] .= $tpl->parse('controls:chips.save_preview.item', array(
					'is_new_player' => !$player['id'],
					'is_busted' => intval($player['chips']) == 0,
					'id' => htmlspecialchars($player['card']),
					'name' => htmlspecialchars($player['name']),
					'chips' => $player['chips'],
					'place' => isset($player['place'])
						? $player['place']
						: NULL,
					'amount' => isset($player['amount'])
						? $player['amount']
						: NULL,
					'chips_change' => $player['chips_change'],
					'url' => $player['id']
						? $lrep->makeUri('event#edit', array(
							'event_id' => $location['event_id'],
							'type' => 'profile',
							'id' => $player['id']
						)) : ''
				));
			}
			$place++;
		}
		if (!empty($data['is_full_listing'])) {
			$this->helperSaveGetBustablePlayers($players, $mentionedPlayers, $location);
			foreach ($players as $player) {
				if ($player['chips'] === '0') {
					continue;
				}
				$previewArgv['cc.chips'] .= $tpl->parse('controls:chips.save_preview.item', array(
					'is_busted' => 1,
					'id' => htmlspecialchars($player['card']),
					'name' => htmlspecialchars($player['name']),
					'chips' => 0,
					'place' => NULL,
					'amount' => NULL,
					'chips_change' => $player['chips'] != NULL
						? -1 * intval($player['chips'])
						: NULL ,
					'url' => $lrep->makeUri('event#edit', array(
							'event_id' => $location['event_id'],
							'type' => 'profile',
							'id' => $player['id']
						))
				));
			}
		}

		return $tpl->parse('controls:chips.save_preview', $previewArgv);
	}
	
	/**
	 * Chips data. 
	 * Returns array with players ids as keys, or non-positive numbers for ones which were definitely deleted. 
	 * Player data _may_ be missing either way.
	 * @todo return more meaningful filed names
	 */
	private function getEditableData($id, $eventId, $full)
	{
		$entry = $this->db->single_query_assoc('
			SELECT l.tournament_id, l.event_id, l.day_id, l.created_on, l.updated_on, l.is_hidden, ' . (
				$full
					? 'd.*'
					: 'd.chips'
			) . '
			FROM ' . $this->table('Log') . ' l
			INNER JOIN ' . $this->table('tChips') . ' d
			ON l.id=d.id
			WHERE l.id=' . getInteger($id) . ' AND l.type="chips"
				AND l.event_id=' . getInteger($eventId));
		if (empty($entry)) {
			return NULL;
		}

		$chips = array();
		$rChips = explode("\n", $entry['chips']);
		$nrDels = 0; // deleted players counter
		foreach($rChips as $rChip) {
			if ('' == $rChip) {
				continue;
			}
			$rChip = explode(",", $rChip);
			$nr = intval($rChip[0]); // player id
			if ($nr == 0) {
				$nr = $nrDels--; // or [0,-1,..] if was deleted
			}
			$chips[$nr] = array(
				'chips' => intval($rChip[1]),
				'chipsc' => intval($rChip[2]),
				'pos' => isset($rChip[3])
					? intval($rChip[3])
					: 0
			);
		}
		if (count($chips) > 0) {
			$players = $this->db->array_query_assoc('
				SELECT p.id, p.name, p.is_pnews, p.sponsor_id, p.status, p.country_id
				FROM ' . $this->table('Players') . ' p
				WHERE p.id IN (' . implode(',', array_keys($chips)) . ')
			');
			$sponsorIds = array();
			foreach ($players as $row) {
				if (!empty($row['sponsor_id'])) {
					$sponsorIds[] = $row['sponsor_id'];
				}
			}
			$sponsors = $this->lrep()->instEventModel('_src_event')->getSponsorsById($sponsorIds);
			foreach ($players as $player) {
				$add = array(
					'id'      => $player['id'],
					'uname'   => $player['name'],
					'ispn'    => $player['is_pnews'],
					'status'  => $player['status'],
					'country_id' => $player['country_id'],
					'sponsor_id' => $player['sponsor_id'],
					'sponsor'    => null,
					'sponsorimg' => null,
					'sponsorurl' => null
				);
				if (isset($sponsors[$player['sponsor_id']])) {
					$sponsor = $sponsors[$player['sponsor_id']];
					$add['sponsor'] = $sponsor['name'];
					$add['sponsorimg'] = $sponsor['favicon'];
					$add['sponsorurl'] = !$sponsor['is_hidden'] && !empty($sponsor['alias'])
						? '/' . $sponsor['alias'] . '/'
						: null;
				}

				$chips[intval($player['id'])] += $add;
			}
		}
		$entry['chips'] = $chips;
		
		$entry['tags'] = array();
		$tags = $this->db->array_query_assoc('
			SELECT tag FROM ' . $this->table('Tags') . '
			WHERE id=' . getInteger($id) . ' AND type="chips"
		');
		foreach ($tags as $tag) {
			$entry['tags'][] = $tag['tag'];
		}		
		
		return $entry;
	}

	private function getPlayersData($eventId, $dayId, $datetime, $includeIntermediate = FALSE)
	{
		// $includeIntermediate = no chips change / chips change since last major update
		$sql = '
			SELECT p.id, p.card, p.sponsor_id, p.status, p.is_pnews, p.name, p.place, p.country_id, ce.chips
			FROM ' . $this->table('Players') . ' p
			LEFT JOIN ' . $this->table('Chips') . ' ce
				ON ce.id=(
					SELECT id FROM ' . $this->table('Chips') . '
					WHERE player_id=p.id
					AND event_id=' . getInteger($eventId) . '
					AND created_on<' . getInteger($datetime) . '
					AND is_hidden=0
					ORDER BY ' . ($includeIntermediate == TRUE ? '' : 'is_full_import DESC,') . 'created_on DESC
					LIMIT 1
				)
			WHERE p.event_id=' . getInteger($eventId);
		// do not delete {
		//$sql = '
		//SELECT p.id, p.card, p.sponsor_id, p.name, ce.chips, ce.chips-ceo.chips diff
		//FROM ' . $this->table('Players') . ' p
		//LEFT JOIN ' . $this->table('Chips') . ' ce
		//	ON ce.id=(
		//		SELECT id FROM ' . $this->table('Chips') . '
		//		WHERE player_id=p.id
		//		AND day_id=' . getInteger($dayId) . '
		//		AND created_on<' . getInteger($datetime) . '
		//		ORDER BY created_on DESC
		//		LIMIT 1
		//	)
		//LEFT JOIN ' . $this->table('Chips') . ' ceo
		//	ON ceo.id=(
		//		SELECT id FROM ' . $this->table('Chips') . '
		//		WHERE player_id=p.id
		//		AND day_id=' . getInteger($dayId) . '
		//		AND created_on<ce.created_on
		//		ORDER BY is_full_import DESC, created_on DESC
		//		LIMIT 1
		//	)
		//LEFT JOIN ' . $this->table('tChips') . ' co
		//	ON co.id=ceo.import_id
		//WHERE p.event_id=' . getInteger($eventId);
		// }
		return $this->db->array_query_assoc($sql, 'id');
	}

	private function getLastFullChipsId($eventId, $forceFresh = FALSE, $dayId = NULL, $includeHidden = TRUE)
	{
		static $lastFullChips = array();
		if (!isset($lastFullChips[$eventId . '-' . $dayId])  || $forceFresh) {
			$where = array();
			if (!empty($dayId)) {
				$where[] = 'l.day_id=' . getInteger($dayId);
			} else {
				$where[] = 'l.event_id=' . getInteger($eventId);
			}
			$where[] = 'l.type="chips"';
			if (!$includeHidden) {
				$where[] = 'l.is_hidden=0';
			}
			$where[] = 'c.is_full_import=1';
			$chips = $this->db->single_query_assoc('
				SELECT l.id FROM ' . $this->table('Log') . ' l
				INNER JOIN ' . $this->table('tChips') . ' c
					ON l.id=c.id
				WHERE ' . implode(' AND ', $where) . '
				ORDER BY l.created_on DESC
				LIMIT 1
			');
			if (empty($chips)) {
				$lastFullChips[$eventId . '-' . $dayId] = NULL;
			} else {
				$lastFullChips[$eventId . '-' . $dayId] = $chips['id'];
			}
		}
		return $lastFullChips[$eventId . '-' . $dayId];
	}
	
	private function delete($importId)
	{
		if (NULL == ($prereq = $this->helperDeleteCheckPrerequisites($importId, 'chips'))) {
			return FALSE;
		}
		list (
			$location
		) = $prereq;

		$deletedRows = $this->helperDeleteDbDelete($importId, 'chips', 'tChips');
		$this->db->query('
			DELETE FROM ' . $this->table('Chips') . '
			WHERE import_id=' . getInteger($importId) . '
		');

		if ($deletedRows[0]) {
			$this->lrep()->altLog($location['tournament_id'], $location['event_id'], $location['day_id'], 'delete', 'log', $importId);
		}
		if ($deletedRows[1]) {
			$this->lrep()->altLog($location['tournament_id'], $location['event_id'], $location['day_id'], 'delete', 'sub_chips', $importId);
		}

		$this->helperDeleteNotifyEvent($location);
		$this->helperUpdateNotifyCTags($importId, 'chips');
	}

	public function deleteSingleSrcProfile($chipId)
	{
		if (!$this->lrep()->instTools()->isAllowed('writeContent')) {
			return ;
		}

		$chip = $this->db->single_query_assoc('
			SELECT import_id, player_id FROM ' . $this->table('Chips') . '
			WHERE id=' . intval($chipId) . '
		');

		if (empty($chip)) {
			return ;
		}
		if (!empty($chip['import_id'])) {
			return $chip['player_id'];
		}

		$this->db->query('DELETE FROM ' . $this->table('Chips') . ' WHERE id="' . intval($chipId) . '" AND import_id IS NULL');

		return $chip['player_id'];
	}

	private function partialRenderChips($data)
	{
		$eventInfo = $this->lrep()->instEventModel('_src_event')->getEventData($data['event_id']);
		return array(
			'data' => $this->renderLogTab(array(
				'event_id' => $data['event_id'],
				'day_id' => $data['day_id'],
				'tzOffset' => $eventInfo['tzOffset'],
			), array(
				'only_entries' => true,
				'sort_key' => $data['sort_key']
			))
		);
	}

	// plain workaround, maybe reimplement later
	private function renderSaveDelPlayer($data)
	{
		$evEvObj = $this->object('livereporting_event')->instEventEvent();
		$players = $evEvObj->getPlayersDataSrcChips($data['event_id']);
		$delPlayers = array();
		$delPlayerIds = array_keys($data['data']);
		foreach ($players as $player) {
			if (in_array($player['id'], $delPlayerIds)) {
				$delPlayers[] = array($player['name']);
			}
		}
		if (FALSE ===$evEvObj->savePlayersSrcChips(array(
			'day_id' => $data['day_id'],
			'event_id' => $data['event_id'],
			'del_players_list' => $delPlayers,
			'new_players_list' => array()
		))) {
			return FALSE;
		}
		return $this->partialRenderChips($data);
	}

	// almost plain redirect
	private function renderSaveNewPlayer($data)
	{
		$evEvObj = $this->object('livereporting_event')->instEventEvent();
		if (FALSE ===$evEvObj->savePlayersSrcChips(array(
			'day_id'   => $data['day_id'],
			'event_id' => $data['event_id'],
			'del_players_list' => array(),
			'new_players_list' => array(array($data['data']))
		))) {
			return FALSE;
		}
		return $this->partialRenderChips($data);
	}

	/**
	 * Saves initial (hidden) chip, used to simplify queries siginificantly.
	 * renderSaveNewPlayer / renderSaveDelPlayer -> _event.savePlayersSrcChips -> _event.savePlayers -> this
	 */
	public function saveSingleInitialSrcEvent($data)
	{
		$register = false;
		if (!empty($data['existing_player'])) {
			// some big time BS going on
			$dayId = $data['day_id'];
			$eventId = $data['event_id'];
			$daysData = $this->lrep()->instEventModel('_src_event')->getDaysData($eventId);
			preg_match('~^([0-9]+)([a-d]+)~i', $daysData[$dayId]['name'], $tmpDayName);
			if (isset($tmpDayName[2]) && $tmpDayName[1] == '1') {
				$registered = $this->db->single_query_assoc('
					SELECT 1 FROM reporting_ng_chips
					WHERE player_id=' . intval($data['player_id']) . '
					  AND day_id=' . intval($dayId) . '
					LIMIT 1
				');
				if (empty($registered)) {
					$register = true;
				}
			}
		} else {
			$register = true;
		}

		if (!$register) {
			return ;
		}
		$this->db->insert(array(
			'import_id' => NULL,
			'day_id' => $data['day_id'],
			'player_id' => $data['player_id'],
			'is_full_import' => 0,
			'is_hidden' => 0,
			'chips' => $data['chips'],
			'chips_change' => NULL,
			'created_on' => time()
		), $this->table('Chips'));
	}

	private function renderSaveSingle($data)
	{
		$lrep = $this->lrep();
		if (!$lrep->instTools()->isAllowed('writeContent')) {
			return FALSE;
		}

		$days = $lrep->instEventModel('_src_event')->getDaysData($data['event_id']);

		// day exists
		if (!isset($days[$data['day_id']])) {
			return FALSE;
		}
		$day = $days[$data['day_id']];

		// only allow running days
		if ($day['state'] != 1) {
			return FALSE;
		}

		$players = $this->getPlayersData($data['event_id'], $data['day_id'], time(), TRUE);
		foreach ($data['data'] as $playerId => $chips) {
			if (($chips = getInteger($chips)) === NULL) {
				continue;
			}
			if (($playerId = getInteger($playerId)) === NULL || !isset($players[$playerId])) {
				continue;
			}
			$player = $players[$playerId];
			$this->db->insert(array(
				'import_id' => NULL,
				'day_id' => $data['day_id'],
				'player_id' => $playerId,
				'is_full_import' => 0,
				'is_hidden' => 0,
				'chips' => $chips,
				'chips_change' => $player['chips'] == NULL
					? NULL
					: $chips - $player['chips'],
				'created_on' => time()
			), $this->table('Chips'));
		}

		return array(
			'status' => 0
		) + $this->partialRenderChips($data);
	}

	private function save($data)
	{
		$lrep = $this->lrep();

		if (NULL == ($prereq = $this->helperSaveCheckPrerequisites($data['day_id'], $data['import_id'], 'chips', array('created_on')))) {
			return FALSE;
		}
		list (
			$location,
			$entry
		) = $prereq;		

		$entryId = NULL;
		$userId = intval(moon::user()->id());
		$eventData = $lrep->instEventModel('_src_event')->getEventData($location['event_id']);

		if ($data['import_id'] != '') {
			$entryId = getInteger($data['import_id']);
			if ($data['datetime'] === NULL) { // if not changing `created on`, use old
				$data['datetime'] = $entry['created_on'];
			}
		}
		if ($data['datetime'] === NULL) {
			moon::page()->page404();
		}

		$rtf = $this->object('rtf');
		$rtf->setInstance($this->get_var('rtf') . ':1');
		list(,$data['body_compiled']) = $rtf->parseText($entryId, $data['intro']);

		// alters db: creates players
		if (NULL === ($preprocessed = $this->helperSavePreprocess(
			$eventData['synced'] == '0', $location, $entryId, $data
		))) {
			return;
		}
		list(
			$data, $chips, $topChips, 
			$mentionedPlayersCnt, $chipsBackupText, $newPlayerIds
		) = $preprocessed; unset($preprocessed);
		
		$tags = $this->helperSaveGetTags($data['tags']);

		$saveDataChips = array(
			'title' => $data['title'],
			'contents' => $data['intro'],
			'is_exportable' => $data['is_exportable'],
			'is_keyhand' => $data['is_keyhand'],
			'image_misc' => (!empty($data['image']))
				? implode(',', array(
					$data['image']['id'], $data['image']['misc']
				))
				: NULL,
			'image_src' => (!empty($data['image']))
				? $data['image']['src']
				: NULL,
			'image_alt' => (!empty($data['image']))
				? $data['image']['title']
				: NULL,
		);
		if ($eventData['synced'] == '0') {
			$saveDataChips += array(
				'chips' => $chipsBackupText,
				'is_full_import' => $data['is_full_listing']
			);
		}
		$saveDataLog = array(
			'type' => 'chips',
			'is_hidden' => $data['published'] != '1',
			'contents' => serialize(array(
				'title' => $data['title'],
				'contents' => $data['body_compiled'],
				'is_full_import' => intval($data['is_full_listing']),
				'chipstotal' => $mentionedPlayersCnt,
				'chips' => $topChips,
				'i_misc' => (!empty($data['image']))
					? $data['image']['id'] . ',' . $data['image']['misc']
					: NULL,
				'i_src' => (!empty($data['image']))
					? $data['image']['src']
					: NULL,
				'i_alt' => (!empty($data['image']))
					? $data['image']['title']
					: NULL,
				'tags' => $tags
			))
		);

		$this->helperSaveAssignCommonLogAttrs($saveDataLog, $userId, $entry, $data, $location);

		if ($entryId != NULL) {
			$this->db->update($saveDataChips, $this->table('tChips'), array(
				'id' => $entryId
			));
			if ($this->db->affected_rows()) {
				$this->lrep()->altLog($location['tournament_id'], $location['event_id'], $location['day_id'], 'update', 'sub_chips', $entryId);
			}
			$this->db->update($saveDataLog, $this->table('Log'), array(
				'id' => $entryId,
				'type' => 'chips'
			));
			if ($this->db->affected_rows()) {
				$this->lrep()->altLog($location['tournament_id'], $location['event_id'], $location['day_id'], 'update', 'log', $entryId);
			}
		} else {
			$this->db->insert($saveDataChips, $this->table('tChips'));
			$entryId = $saveDataLog['id'] = $this->db->insert_id();
			if (!$entryId) {
				return;
			}
			if ($this->db->affected_rows()) {
				$this->lrep()->altLog($location['tournament_id'], $location['event_id'], $location['day_id'], 'insert', 'sub_chips', $entryId);
			}
			$this->db->insert($saveDataLog, $this->table('Log'));
			if ($this->db->affected_rows()) {
				$this->lrep()->altLog($location['tournament_id'], $location['event_id'], $location['day_id'], 'insert', 'log', $entryId);
			}
		}

		if ($eventData['synced'] == '0') {
			$this->db->query('
				DELETE FROM ' . $this->table('Chips') . '
				WHERE import_id=' . $entryId . '
			');

			foreach ($chips as $chipNr => $chip) {
				if (substr($chip['uname'], 0, 3) == '???') {
					continue;
				}
				if ($chip['player_id'] == NULL) {
					$chips[$chipNr]['player_id'] = $chip['player_id'] = $newPlayerIds[strtolower($chip['uname'])];
				}
				$this->db->insert(array(
					'import_id' => $entryId,
					'day_id' => $location['day_id'],
					'player_id' => $chip['player_id'],
					'is_full_import' => $data['is_full_listing'],
					'is_hidden' => $saveDataLog['is_hidden'],
					'chips' => $chip['chips'],
					'chips_change' => $chip['chips_change'],
					'created_on' => $saveDataLog['created_on'],
					//'updated_on' => $saveDataLog['updated_on']
				), $this->table('Chips'));
			}

			//if (!empty($data['is_full_listing'])) {
			//    could assign places and positions
			// could assign players_left
		}
		
		$this->helperSaveDbUpdateTags($tags, $entryId, 'chips', $saveDataLog['is_hidden'], $location);

		$this->helperSaveNotifyEvent($saveDataLog['is_hidden'], $location);
		$this->helperUpdateNotifyCTags(
			$entryId, 'chips', 
			$saveDataLog['is_hidden'], $tags, 
			$saveDataLog['created_on']
		);
		
		return $entryId;
	}

	/**
	 * Alters db: creates players
	 */
	private function helperSavePreprocess($isNotSyncedEvent, $location, $entryId, $data)
	{
		$evProfileObj = $this->instEventProfile();
		/**
		 * if synced, only update serialized `chips` field, and populate $mentionedPlayers
		 * otherwise, do full supplimentary work (create players)
		 */
		if ($isNotSyncedEvent) {
			$players = $this->getPlayersData($location['event_id'], $location['day_id'], $data['datetime'], TRUE);
			$playerNames = array();
			$sponsors = array();
			foreach ($players as $k => $player) {
				$playerNames[$k] = strtolower($player['name']);
				if ($player['sponsor_id']) {
					$sponsors[] = $player['sponsor_id'];
				}
			}
			$sponsors = $this->lrep()->instEventModel('_src_event')->getSponsorsById($sponsors);

			// always sync this section with preview() !
			// loop-populate variables below
			$mentionedPlayers = array(); // old players, mentioned in this import (ids)
			$newPlayerNames = array(); // names
			$oldPlayerNames = array(); // names
			$chips = array();
			foreach ($data['chips'] as $row) {
				$chip = array(
					'chips' => intval($row[2]),
				);
				// existing players
				if (in_array(strtolower($row[1]), $playerNames)) {
					// if dupe name -- skip
					if (in_array(strtolower($row[1]), $oldPlayerNames)) {
						continue;
					}
					$oldPlayerNames[] = strtolower($row[1]);
					$player = $players[array_search(strtolower($row[1]), $playerNames)];
					$mentionedPlayers[] = $player['id'];
					$chip['player_id'] = $player['id'];
					$chip['uname'] = $player['name'];
					if (isset($sponsors[$player['sponsor_id']])) {
						$chip['sponsor'] = $sponsors[$player['sponsor_id']];
					}
					if (!empty($player['status'])) {
						$chip['status'] = $player['status'];
					}
					if (!empty($player['country_id'])) {
						$chip['country_id'] = $player['country_id'];
					}
					if (!empty($player['is_pnews'])) {
						$chip['is_pnews'] = 1;
					}
					if ($player['chips'] == NULL) {
						$chip['chips_change'] = NULL;
					} else {
						$chip['chips_change'] = $chip['chips'] - $player['chips'];
					}
				} else { // new players
					// if dupe name -- skip
					if (in_array(strtolower($row[1]), $newPlayerNames)) {
						continue;
					}
					$newPlayerNames[]= strtolower($row[1]);
					$chip['player_id'] = NULL;
					$chip['uname'] = isset($row[1])
						? $row[1]
						: '';
					$chip['ucard'] = isset($row[0])
						? $row[0]
						: '';
					$chip['chips_change'] = NULL;
				}
				if (isset($row[3])) {
					$chip['country_id'] = $this->helperGetCountryIdByName($row[3]);
				}
				$chips[] = $chip;
			}
			if (!empty($data['is_full_listing'])) {
				$this->helperSaveGetBustablePlayers($players, $mentionedPlayers, $location);
				// for what's left (aka busted players) create chip=0 entries
				foreach ($players as $player) {
					if ($player['chips'] === '0') { // do not rebust
						continue;
					}
					$chip = array(
						'chips' => 0,
						'player_id' => $player['id'],
						'uname' => $player['name'],
						'autobusted' => TRUE
					);
					if ($player['chips'] == NULL) {
						$chip['chips_change'] = NULL;
					} else {
						$chip['chips_change'] = -1 * $player['chips'];
					}
					$chips[] = $chip;
				}
			}

			// create players as needed, except do not recreate deleted players on update
			// also notify profiles
			$newPlayerIds = array();
			$dayEnterId = $location['day_id']; // unroll
			foreach ($chips as $chip) {
				if ($chip['player_id'] == NULL) {
					if (substr($chip['uname'], 0, 3) == '???') {
						continue;
					}
					$this->db->insert(array(
						'tournament_id' => $location['tournament_id'],
						'event_id' => $location['event_id'],
						'name' => $chip['uname'],
						'card' => $chip['ucard'],
						'country_id' => $chip['country_id'],
						'created_on' => time(),
						'day_enter_id' => $dayEnterId
					), $this->table('Players'));
					$iId = $this->db->insert_id();
					$evProfileObj->notifyPlayerSaved($iId, $chip['uname']);
					$newPlayerIds[strtolower($chip['uname'])] = $iId;
				} else {
					$this->db->update(array(
						'country_id' => $chip['country_id'],
					), $this->table('Players'), array(
						'id' => $chip['player_id']
					));
				}
			}

			$topChips = array();
			foreach (array_slice($chips, 0, 25) as $chip) {
				if (isset($chip['autobusted'])) {
					continue;
				}
				if ($chip['player_id'] == NULL) {
					$topChip = array(
						'id' => substr($chip['uname'], 0, 3) != '???'
							? $newPlayerIds[strtolower($chip['uname'])]
							: '0',
					);
				} else {
					$topChip = array(
						'id' => $chip['player_id'],
					);
				}
				$topChip += array(
					'chips'  => $chip['chips'],
					'chipsc' => $chip['chips_change'],
					'uname'  => substr($chip['uname'], 0, 3) != '???'
						? $chip['uname']
						: ''
				);
				if (isset($chip['sponsor'])) {
					$topChip['sponsor'] = array(
						'id'  => $chip['sponsor']['id'],
						'uri'  => $chip['sponsor']['alias'],
						'name' => $chip['sponsor']['name'],
						'ico'  => $chip['sponsor']['favicon'],
					);
				}
				if (isset($chip['status'])) {
					$topChip['status'] = $chip['status'];
				}
				if (isset($chip['country_id'])) {
					$topChip['country_id'] = $chip['country_id'];
				}
				if (isset($chip['is_pnews'])) {
					$topChip['ispn'] = intval($chip['is_pnews']);
				}
				$topChips[] = $topChip;
			}

			$chipsBackupText = '';
			foreach ($chips as $chip) {
				if (isset($chip['autobusted'])) {
					continue;
				}
				if ($chip['player_id'] == NULL) {
					if (substr($chip['uname'], 0, 3) != '???') {
						$chipsBackupText .= $newPlayerIds[strtolower($chip['uname'])];
					} else {
						$chipsBackupText .= '0';
					}
				} else {
					$chipsBackupText .= $chip['player_id'];
				}
				$chipsBackupText .= ',' . $chip['chips'] . ',' .  $chip['chips_change'];
				$chipsBackupText .= "\n";
			}
		} else {
			// must exist already. 
			$old = $this->db->single_query_assoc('
				SELECT contents FROM ' . $this->table('Log') . '
				WHERE id=' . intval($entryId) . ' AND type="chips"'
			);
			if (empty($old)) {
				return ;
			}
			$old = unserialize($old['contents']);
			$data['is_full_listing'] = $old['is_full_import'];
			$topChips = $old['chips'];
			$mentionedPlayers = array();
			for ($i = 0; $i < $old['chipstotal']; $i++) {
				$mentionedPlayers[] = 1;
			}

			// should not be used while saving, if event is synced
			$chipsBackupText = NULL;
			$newPlayerIds = NULL;
			$chips = NULL;
		}
		
		return array(
			$data,
			$chips,
			$topChips,
			count($mentionedPlayers),
			$chipsBackupText,
			$newPlayerIds
		);		
	}

	private function helperGetCountryIdByName($name)
	{
		static $countries = null;
		if (!$countries) {
			$countries = array();
			$countriesStock = moon::shared('countries')->getCountries();
			foreach ($countriesStock as $key => $value) {
				$countries[strtolower($value)] = $key;
				$countries[$key] = $key;
			}
			$countries['usa'] = 'us';
			$countries['uk'] = 'gb';
			$countries['great britain'] = 'gb';
		}

		$name = strtolower(trim($name));
		return isset($countries[$name])
			? strtoupper($countries[$name])
			: null;
	}

	private function helperSaveGetBustablePlayers(&$players, $mentionedPlayers, $location)
	{
		// intention is to bust unmentioned players if full list
		// from the full players list, delete mentioned
		foreach ($mentionedPlayers as $mentionedPlayer) {
			unset($players[$mentionedPlayer]);
		}
		// from the full players list, delete players from parallel days (1a => 1b, 1c). not super reliable
		$omitDays = $this->lrep()->instEventModel('_src_event')->dayParallel($location['event_id'], $location['day_id']);
		if (0 != count($omitDays)) {
			$omitPlayers = $this->db->array_query_assoc('
				SELECT id FROM ' . $this->table('Players') . '
				WHERE event_id=' . getInteger($location['event_id']) . '
				  AND day_enter_id IN (' . implode(',', $omitDays) . ')
			');
			foreach ($omitPlayers as $omitPlayer) {
				if (isset($players[$omitPlayer['id']])) {
					unset($players[$omitPlayer['id']]);
				}
			}
		}
	}
	
	private function helperEventGetChipsData(&$data)
	{
		$data['chips'] = array();
		$chips = array();
		include_class('moon_file');
		$file = new moon_file;
		if ($file->is_upload('import_upload', $error)) {
			include_class('excel_reader/reader');
			$ole = new Spreadsheet_Excel_Reader();
			$ole->setOutputEncoding('UTF8');
			$ole->setUTFEncoder('mb');
			$ole->read($file->file_path());
			foreach ($ole->sheets as $sheet) {
				if (!isset($sheet['cells'])) {
					continue;
				}
				$numColumns = count(explode('.', $data['column_order']));
				foreach ($sheet['cells'] as $row) {
					$newRow = array();
					for ($i = 0; $i < $numColumns; $i++) {
						$newRow[$i] =
							isset($row[$i + 1])
								? rtrim($row[$i + 1], chr(160)) // no-break space (broken leftover)
								: '';
					}
					/*foreach ($row as $cell) {
						$newRow[] = trim($cell);
					}*/
					$chips[] = $newRow;
				}
			}
		} elseif ($data['import_textarea'] != '') {
			$data['import_textarea'] = str_replace("\r", '', $data['import_textarea']);
			$rows = explode("\n", $data['import_textarea']);
			foreach ($rows as $row) {
				$row = str_replace(chr(194).chr(160), ' ', $row); // no-break space
				$row = preg_split("~\t|;|,~", $row);
				$newRow = array();
				foreach ($row as $cell) {
					$newRow[] = trim($cell);
				}
				$chips[] = $newRow;
			}
		}
		$columnOrder = explode('.', $data['column_order']);
		// id, name, chipcount, sponsor
		foreach ($chips as $chip) {
			$newRow = array();
			foreach ($columnOrder as $cok => $cov) {
				if (!isset($chip[$cok])) {
					$chip[$cok] = '';
					//continue(2);
				}
				if ($cov == '10') {
					if (isset($newRow[1])) {
						$chip[$cok] = trim($newRow[1] . ' ' . $chip[$cok]);
					}
					$cov = '1';
				}
				if ($cov == '11') {
					if (isset($newRow[1])) {
						$chip[$cok] = trim($chip[$cok] . ' ' . $newRow[1]);
					}
					$cov = '1';
				}
				$newRow[$cov] = $chip[$cok];
			}
			if (!empty($newRow[1])) { // name
				$data['chips'][] = $newRow;
			}
		}
		unset($data['column_order']);
		unset($data['import_textarea']);
	}
	
	private function chipsCmp($a, $b)
	{
		$key = isset($a['chips'])
			? 'chips'
			: 2;
		if ($a[$key] == $b[$key]) {
			return 0;
		}
		return ($a[$key] > $b[$key])
			? -1 : 1;
	}

	private function chipsSort(&$chips)
	{
		usort($chips, array($this, 'chipsCmp'));
	}
}
