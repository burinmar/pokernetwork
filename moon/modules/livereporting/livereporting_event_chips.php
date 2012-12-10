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

					case 'newplayer': // redirects to event_profile with loopback
						if (FALSE !== ($result = $this->renderSaveNewPlayer($data))) {
							echo json_encode($result);
						}
						moon_close();
						exit;

					case 'delplayer': // redirects to event_profile
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
				
				// import wsop feed chips (does not need all $data)
				// will wind up into renderSavePreview
				if (isset($_POST['import']) && $_POST['import'] == '1') {
					header('content-type: text/plain; charset=utf-8');
					echo json_encode(
						  $this->renderChipSavePreviewWXML($data)
					);
					moon_close();
					exit;
				}

				$data['chips'] = $this->helperParseChipsImportData($data);
				unset($data['column_order']);
				unset($data['import_textarea']);

				$this->chipsSort($data['chips']);

				// preview
				if (!isset($_POST['save'])) {
					header('content-type: text/html; charset=utf-8');
					echo $this->renderSavePreview($data);
					moon_close();
					exit;
				}
				
				$this->helperEventGetLeadingImage($data);
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

	protected function preRender(&$data, $args)
	{
		switch ($args['variation']) {
		case 'logEntry':
			return $this->collectPlayerIds($data);
		}
	}

	private $playerIdsOnPage = array();
	private function collectPlayerIds(&$data)
	{
		$data['contents'] = unserialize($data['contents']);
		foreach($data['contents']['chips'] as $chip) {
			if (!isset($chip['uname'])) // if new format
				$this->playerIdsOnPage[] = intval($chip['id']);
		}
	}

	private $playersOnPage;
	private function populatePrefetchedPlayers()
	{
		if (null !== $this->playersOnPage)
			return;
		$this->playersOnPage = array();
		if (!count($this->playerIdsOnPage))
			return;
		$this->playersOnPage = $this->db->array_query_assoc('
			SELECT id, name, status, sponsor_id, is_pnews, country_id
			FROM ' . $this->table('Players') . '
			WHERE id IN (' . implode(',', $this->playerIdsOnPage) . ')
		', 'id');

		$sponsorIds = array();
		foreach ($this->playersOnPage as $player) {
			if (!empty($player['sponsor_id']))
				$sponsorIds[] = $player['sponsor_id'];
		}
		$sponsors = $this->lrep()->instEventModel('_src_event')->getSponsorsById($sponsorIds);
		foreach ($this->playersOnPage as $k => $player) {
			if (isset($sponsors[$player['sponsor_id']])) {
				$sponsor = $sponsors[$player['sponsor_id']];
				$add = array();
				$add['sponsor.name'] = $sponsor['name'];
				$add['sponsor.img'] = $sponsor['favicon'];
				$add['sponsor.url'] = !$sponsor['is_hidden'] && !empty($sponsor['alias'])
					? '/' . $sponsor['alias'] . '/'
					: null;

				$this->playersOnPage[$k] += $add;
			}
		}
	}
	
	protected function render($data, $argv = NULL)
	{
		switch ($argv['variation']) {
		case 'logControl':
			return $this->renderControl(array_merge($data, array(
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
		$this->populatePrefetchedPlayers();
		
		$rArgv['entries'] = '';
		foreach ($data['contents']['chips'] as $k => $chip) {
			$chipsArgv = $this->helperRenderChipArgv($chip, $k, !empty($data['contents']['is_full_import']));
			if (isset($this->playersOnPage[$chip['id']])) {
				$player = $this->playersOnPage[$chip['id']];
				$chipsArgv = array_merge($chipsArgv, array(
					'player' => htmlspecialchars($player['name']),
					'player_url' => $playerUrl
						? str_replace('{}', $chip['id'], $playerUrl)
						: '',
					'player_is_pnews' => $player['is_pnews'],
				));
				if (!empty($player['country_id']))
				$chipsArgv = array_merge($chipsArgv, array(
					'country' => htmlspecialchars($player['country_id']),
					'country_id' => htmlspecialchars(strtolower($player['country_id']))
				));
				if (!empty($player['sponsor.name']))
				$chipsArgv = array_merge($chipsArgv, array(
					'player_sponsorimg' => $player['sponsor_id'] > 0
							? img('rw', $player['sponsor_id'], $player['sponsor.img'])
							: $player['sponsor.img'],
					'player_sponsor' => $lrepTools->helperPlayerStatus(
						$player['status'],
						$player['sponsor.name']),

				));
			} elseif (isset($chip['uname'])) {
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
			}
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

		if ($rArgv['show_controls']) {
			$chipsTxt = array();
			foreach ($entry['chips'] as $nr => $chip) {
				$chipsTxt[] = isset($chip['uname'])
					? htmlspecialchars($chip['uname']) . "\t" . $chip['chips']
					: '???(unknown ' . (-$nr + 1) . ')' . "\t" . $chip['chips'];
			}			
			$eventInfo = $lrep->instEventModel('_src_event')->getEventData($data['event_id']);
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
				'show_wsop_eod' => $eventInfo['show_wsop_eod'],
				'synced' => $entry['synced'],
				'chips_txt' => implode("\n", $chipsTxt),
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
		$showSingleChipsControls = 
			$isAdm 
			&& ($days = $lrep->instEventModel('_src_event')->getDaysData($data['event_id']))
			&& $lrep->instTools()->isAllowed('viewSingleChipsControl', array(
			'event_synced' => $eventInfo['synced'],
			'day_state' => $days[$data['day_id']]['state']
		));

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
				'adm' => $isAdm && $showSingleChipsControls,
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
				if ($isAdm && $showSingleChipsControls) {
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
					} else {
						$dtCtrl = $tpl->parse('logTab:chips.chips.item.dt_ctrl', array(
							'created_on' => ''
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
		
		$rArgv['adm'] = $isAdm && $showSingleChipsControls;
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
				usort($chips, array($this, 'chipsNameCmp'));
				break;
			case 4:
				$nameDesc = FALSE;
				usort($chips, array($this, 'chipsNameCmp'));
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

		$controlsArgv = array(
			'cc.unhide'     => !empty($argv['unhide']),
			'cc.bundled_control' => !empty($argv['bundled_control']),
			'cc.save_event' => $this->parent->my('fullname') . '#save-chips',
			'cc.day_id'     => $argv['day_id'],
			'cc.event_id'   => $argv['event_id'],
			'cc.import_id'  => isset($argv['import_id'])
				? intval($argv['import_id'])
				: '',
			'cc.show_full_controls' => empty($argv['synced']),
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
		);

		if ($argv['show_wsop_eod']) {
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
					'event_id' => filter_var($argv['event_id'], FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE),
					'path' => $this->getUriPath(),
				),
				array('x' => 'chips')
			);
		$controlsArgv['cc.url.ipnupload'] =
			$lrep->makeUri('event#ipn-upload',
				array(
					'event_id' => filter_var($argv['event_id'], FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE),
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

	/**
	 * Chips data. 
	 * Returns array with players ids as keys, or non-positive numbers for ones which were definitely deleted. 
	 * Player data _may_ be missing either way.
	 * @todo return more meaningful field names
	 */
	private function getEditableData($id, $eventId, $full)
	{
		$entry = $this->db->single_query_assoc('
			SELECT l.tournament_id, l.event_id, l.day_id, l.created_on, l.updated_on, l.is_hidden, ' . (
				$full
					? 'd.*, l.sync_id IS NOT NULL synced'
					: 'd.chips'
			) . '
			FROM ' . $this->table('Log') . ' l
			INNER JOIN ' . $this->table('tChips') . ' d
			ON l.id=d.id
			WHERE l.id=' . filter_var($id, FILTER_VALIDATE_INT) . ' AND l.type="chips"
			  AND l.event_id=' . filter_var($eventId, FILTER_VALIDATE_INT));
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
			WHERE id=' . filter_var($id, FILTER_VALIDATE_INT) . ' AND type="chips"
		');
		foreach ($tags as $tag) {
			$entry['tags'][] = $tag['tag'];
		}		
		
		return $entry;
	}

	// Slmost plain redirect, with loopback to saveSingleChipSrcEvent.
	// saveSingleChipSrcEvent could be called without loopback, but `Players info` control
	// needs chips insertion anyway.
	private function renderSaveNewPlayer($data)
	{
		$evProObj = $this->object('livereporting_event')->instEventProfile();
		if (FALSE === $evProObj->savePlayersSrcChips(array(
			'day_id'   => $data['day_id'],
			'event_id' => $data['event_id'],
			'del_players_list' => array(),
			'new_players_list' => array(explode(';', $data['data']))
		))) {
			return FALSE;
		}
		return $this->partialRenderChips($data);
	}

	/**
	 * Saves initial chip
	 * renderSaveNewPlayer / renderSaveDelPlayer -> _profile.savePlayersSrcChips -> _profile.savePlayers -> this
	 */
	private $saveSingleChipsSrcEventTimes = array();
	public function saveSingleChipSrcEvent($data)
	{
		if (!isset($this->saveSingleChipsSrcEventTimes[$data['day_id']]))
			$this->saveSingleChipsSrcEventTimes[$data['day_id']] = $this->lrep()->instEventModel('_src_event_chips')->getDayChipsTimeCap($data['event_id'], $data['day_id']);
		$this->db->insert(array(
			'import_id' => NULL,
			'day_id' => $data['day_id'],
			'player_id' => $data['player_id'],
			'is_hidden' => 0,
			'chips' => $data['chips'],
			'chips_change' => NULL,
			'created_on' => $this->saveSingleChipsSrcEventTimes[$data['day_id']]
		), $this->table('Chips'));
	}

	// plain redirect
	private function renderSaveDelPlayer($data)
	{
		$evProObj = $this->object('livereporting_event')->instEventProfile();
		$delPlayers = array();
		foreach ($data['data'] as $playerId => $null) {
			$delPlayers[] = array('id:' . $playerId);
		}
		if (FALSE === $evProObj->savePlayersSrcChips(array(
			'day_id' => $data['day_id'],
			'event_id' => $data['event_id'],
			'del_players_list' => $delPlayers,
			'new_players_list' => array()
		))) {
			return FALSE;
		}
		return $this->partialRenderChips($data);
	}

	private function renderSaveSingle($data)
	{
		$lrep = $this->lrep();
		if (!$lrep->instTools()->isAllowed('writeContent')) {
			return FALSE;
		}

		$days = $lrep->instEventModel('_src_event')->getDaysData($data['event_id']);

		// day does not exist
		if (!isset($days[$data['day_id']]))
			return FALSE;
		$day = $days[$data['day_id']];

		// only allow running days
		if ($day['state'] != 1)
			return FALSE;

		$singleChipTime = $lrep->instEventModel('_src_event_chips')->getDayChipsTimeCap($data['event_id'], $data['day_id']);
		$playerIds = array();
		foreach ($data['data'] as $playerId => $chips) {
			$playerIds[] = $playerId;
		}
		$players = $lrep->instEventModel('_src_event_chips')->getPreviousChipsByPlayerId($data['event_id'], $playerIds, $singleChipTime);
		foreach ($data['data'] as $playerId => $chips) {
			$chips = str_replace(array(',', '.'), '', $chips);
			$chips = filter_var($chips, FILTER_VALIDATE_INT);
			if ($chips === false)
				continue;
			if (!isset($players[$playerId]))
				continue;
			$player = $players[$playerId];
			$this->db->insert(array(
				'import_id' => NULL,
				'day_id' => $data['day_id'],
				'player_id' => $playerId,
				'is_hidden' => 0,
				'chips' => $chips,
				'chips_change' => $player['chips'] == NULL
					? NULL
					: $chips - $player['chips'],
				'created_on' => $singleChipTime
			), $this->table('Chips'));
		}

		return array(
			'status' => 0
		) + $this->partialRenderChips($data);
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

	private function renderChipSavePreviewWXML($data)
	{
		if (NULL == ($prereq = $this->helperSaveCheckPrerequisitesLocationByDay($data['day_id']))) {
			moon::page()->page404();
		}
		list ($location) = $prereq;

		$eventId = $this->object('livereporting_bluff')->bluffEventId($location['event_id']);

		$ch = curl_init('http://www.wsop.com/data/xml/wsop2010/EOD.aspx?EventID=' . $eventId . '&day=' . $location['day_name']);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
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

	private function renderSavePreview($data)
	{
		// synced mini-section 1 (alsmost)
		if (NULL == ($prereq = $this->helperSaveCheckPrerequisites($data['day_id'], $data['import_id'], 'chips', array('created_on')))) {
			moon::page()->page404();
		}
		list (
			$location,
			$entry
		) = $prereq;

		$tpl = $this->load_template();
		$previewArgv = array(
			'cc.chips' => ''
		);
		$lrep = $this->lrep();
		// synced mini-section 2
		$entryId = NULL;

		// synced mini-section 3
		if ($data['import_id'] != '') {
			if (false === ($entryId = filter_var($data['import_id'], FILTER_VALIDATE_INT)))
				moon::page()->page404();
			if ($data['datetime'] === NULL) // if not changing `created on`, use old
				$data['datetime'] = $entry['created_on'];
		}
		if ($data['datetime'] === NULL)
			moon::page()->page404();

		$chips = $this->helperSaveGetPlayersData(
			!empty($data['is_full_listing']),
			$location['event_id'],
			$location['day_id'],
			$data['datetime'],
			$data['chips']
		);
		foreach ($chips as $playerChip) {
			list($playerId, $playerName, $chip, $chipChange, ) = $playerChip;
			if (substr($playerName, 0, 3) != '???') {
				$previewArgv['cc.chips'] .= $tpl->parse('controls:chips.save_preview.item', array(
					'is_new_player' => null == $playerId,
					'is_busted' => intval($chip) == 0,
					'name' => htmlspecialchars($playerName),
					'chips' => $chip,
					'chips_change' => $chipChange,
					'url' => null != $playerId
						? $lrep->makeUri('event#edit', array(
							'event_id' => $location['event_id'],
							'type' => 'profile',
							'id' => $playerId
						)) : ''
				));
			}
		}
	
		return $tpl->parse('controls:chips.save_preview', $previewArgv);
	}

	private function save($data)
	{
		// synced mini-section 1
		if (NULL == ($prereq = $this->helperSaveCheckPrerequisites($data['day_id'], $data['import_id'], 'chips', array('created_on', 'sync_id IS NOT NULL as synced')))) {
			moon::page()->page404();
		}
		list (
			$location,
			$entry
		) = $prereq;

		$userId = intval(moon::user()->id());
		// synced mini-section 2
		$entryId = NULL;

		// synced mini-section 3
		if ($data['import_id'] != '') {
			if (false === ($entryId = filter_var($data['import_id'], FILTER_VALIDATE_INT)))
				moon::page()->page404();
			if ($data['datetime'] === NULL) // if not changing `created on`, use old
				$data['datetime'] = $entry['created_on'];
		}
		if ($data['datetime'] === NULL)
			moon::page()->page404();

		// alters db: creates players
		if (empty($entry['synced'])) {
			$chips = $this->helperSaveGetPlayersData(
				!empty($data['is_full_listing']),
				$location['event_id'],
				$location['day_id'],
				$data['datetime'],
				$data['chips']
			);

			$evProfileObj = $this->instEventProfile();

			$chipsBackupText = '';
			$topChips = array();
			$mentionedPlayersCnt = 0;
			foreach ($chips as $k => $playerChip) {
				list($playerId, $playerName, $chip, $chipChange, $explicit, $playerCountry) = $playerChip;
				if (null == $playerId && substr($playerName, 0, 3) != '???') {
					$playerId = $chips[$k][0] = $evProfileObj->savePlayerSrcChips($location, $playerName);
					if (null == $playerId)
						// most likely we screwed up calculating, which players should be created
						continue;
				}
				if (null != $playerId) {
					if (false !== $playerCountry)
						$this->db->update(array( // move to _profile?
							'country_id' => $playerCountry,
							'updated_on' => time(),
						), $this->table('Players'), array(
							'id' => $playerId
						));
					if ($explicit && !$entryId)
						$this->db->update(array( // move to _profile?
							'is_hidden' => 0,
							'updated_on' => time(),
						), $this->table('Players'), array(
							'id' => $playerId,
							'is_hidden' => 1,
						));
					$mentionedPlayersCnt++;
				}
				if ($explicit) {
					$chipsBackupText .= sprintf("%d,%d,%s\n",
						$playerId, // null => 0
						$chip,
						$chipChange);
					if (intval($chip) > 0 && count($topChips) <= 25)
						$topChips[] = array(
							'id' => intval($playerId), // null => 0
							'chips'  => $chip,
							'chipsc' => $chipChange,
						);
				}
			}

			// if 0 explicit chips, fuck off
			if ('' == $chipsBackupText)
				moon::page()->page404();
		} else {
			// must exist already. 
			$old = $this->db->single_query_assoc('
				SELECT contents FROM ' . $this->table('Log') . '
				WHERE id=' . $entryId . ' AND type="chips"'
			);
			if (empty($old))
				moon::page()->page404();

			$old = unserialize($old['contents']);
			$data['is_full_listing'] = $old['is_full_import'];
			$topChips = $old['chips'];
			$mentionedPlayersCnt = $old['chipstotal'];
		}

		$rtf = $this->object('rtf');
		$rtf->setInstance($this->get_var('rtf') . ':1');
		list(, $data['body_compiled']) = $rtf->parseText($entryId, $data['intro']);

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
		if (empty($entry['synced'])) {
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

		if (empty($entry['synced'])) { // @todo do not recreate everything
			$this->db->query('
				DELETE FROM ' . $this->table('Chips') . '
				WHERE import_id=' . $entryId . '
			');

			foreach ($chips as $k => $playerChip) {
				list($playerId, , $chip, $chipChange, $explicit, ) = $playerChip;
				if (null == $playerId)
					continue;
				$this->db->insert(array(
					'import_id' => $entryId,
					'day_id' => $location['day_id'],
					'player_id' => $playerId,
					'is_hidden' => $saveDataLog['is_hidden'],
					'chips' => $chip,
					'chips_change' => $chipChange,
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

	private function helperSaveGetPlayersData(
		$bustMissingPlayers,
		$eventId,
		$dayId,
		$dateTime,
		$chips
	) {
		// post synced, not event synced
		$players = array();
		foreach ($chips as $k => $row)
			$players[$k] = $row[self::importChipsName];
		$players = $this->lrep()->instEventModel('_src_event_chips')->getPreviousChipsByPlayerName($eventId, $players, $dateTime);

		$result = array(
			// player_id (null or int), player_name, chip, chip_change, is_explicit, country (false to leave as is, null or string)
		);

		foreach ($chips as $k => $row) {
			$country = false;
			if (isset($row[self::importChipsCountry])) {
				$country = $this->helperGetCountryIdByName($row[self::importChipsCountry]);
			}
			if (null != $players[$k])
				$result[] = array(
					$players[$k]['id'],
					$players[$k]['name'],
					$row[self::importChipsChip],
					$row[self::importChipsChip] - $players[$k]['chips'],
					true,
					$country
				);
			else
				$result[] = array(
					null,
					$row[self::importChipsName],
					$row[self::importChipsChip],
					null,
					true,
					$country
				);
		}
		if (!$bustMissingPlayers)
			return $result;

		$mentionedPlayerIds = array();
		foreach ($chips as $k => $row)
			if (null != $players[$k])
				$mentionedPlayerIds[] = $players[$k]['id'];

		$bustablePlayers = $this->lrep()->instEventModel('_src_event_chips')->getPreviousChipsByDayDT($eventId, $dayId, $dateTime);
		foreach ($bustablePlayers as $k => $player) {
			if (!in_array($player['id'], $mentionedPlayerIds))
				$result[] = array(
					$player['id'],
					$player['name'],
					0,
					-1*$player['chips'],
					false,
					false
				);
		}

		return $result;
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

	const importChipsName    = '1';
	const importChipsLName   = '10';
	const importChipsFName   = '11';
	const importChipsChip    = '2';
	const importChipsCountry = '3';
	/**
	 * Parses xls file or textarea, given actual data and column order
	 */
	private function helperParseChipsImportData($data)
	{
		$tools = $this->lrep()->instTools();
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
								? $row[$i + 1]
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
				$row = preg_split("~\t|;|,~", $row);
				$newRow = array();
				foreach ($row as $cell) {
					$newRow[] = trim($cell);
				}
				$chips[] = $newRow;
			}
		}
		$data['chips'] = array();
		$columnOrder = explode('.', $data['column_order']);
		foreach ($chips as $chip) {
			$newRow = array();
			foreach ($columnOrder as $cok => $cov) {
				if (!isset($chip[$cok])) {
					$chip[$cok] = '';
					//continue(2);
				}
				if ($cov == self::importChipsLName) {
					if (isset($newRow[self::importChipsName]))
						$chip[$cok] = $newRow[self::importChipsName] . ' ' . $chip[$cok];
					$cov = self::importChipsName;
				}
				if ($cov == self::importChipsFName) {
					if (isset($newRow[self::importChipsName]))
						$chip[$cok] = $chip[$cok] . ' ' . $newRow[self::importChipsName];
					$cov = self::importChipsName;
				}
				$newRow[$cov] = $chip[$cok];
			}
			if (!empty($newRow[self::importChipsName])) { // name
				$data['chips'][] = $newRow;
			}
		}
		foreach ($data['chips'] as $k => $chip) {
			if (isset($chip[self::importChipsName]))
				$data['chips'][$k][self::importChipsName] = $tools->helperNormalizeName($chip[self::importChipsName]);
			if (isset($chip[self::importChipsChip]))
				$data['chips'][$k][self::importChipsChip] = $tools->helperNormalizeName($chip[self::importChipsChip]);
		}
		return $data['chips'];
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
			WHERE import_id=' . filter_var($importId, FILTER_VALIDATE_INT) . '
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

	
	private function chipsCmp($a, $b)
	{
		$key = isset($a['chips'])
			? 'chips'
			: self::importChipsChip;
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
