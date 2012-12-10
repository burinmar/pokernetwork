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
class livereporting_event_profile extends livereporting_event_pylon
{
	private $listPageBy = 100;

	protected function synthEvent($event, $argv)
	{
		switch ($event) {
			case 'save-profile':
				$data = $this->helperEventGetData(array(
					'id', 'event_id', 'name', 'card', 'is_pnews', 'sponsor', 'status', 'country_id',
					'bluff_id', 'city', 'state', 'country', 'is_hidden'
				));
				$profileId = $this->saveProfile($data);
				$this->redirect('event#view', array(
					'event_id' => filter_var($argv['event_id'], FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE),
					'path' => $this->getUriPath(),
					'type' => 'profile',
					'id' => $profileId
				), $this->getUriFilter(NULL, TRUE));
				exit;
			case 'save-chip-discard':
				$this->forget();
				$profileId = $this->instEventChips()->deleteSingleSrcProfile($argv['uri']['argv'][3]);
				$this->redirect('event#view', array(
					'event_id' => filter_var($argv['event_id'], FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE),
					'path' => $this->getUriPath(),
					'type' => 'profile',
					'id' => $profileId
				), $this->getUriFilter(NULL, TRUE));
				exit;
			case 'save-list-profiles':
				$data = $this->helperEventGetData(array(
					'event_id', 'day_id', 'new_players_list'
				));
				$data['new_players_list'] = str_replace("\r", '', $data['new_players_list']);
				$npList = array();
				$dpList = array();
				$rows = explode("\n", $data['new_players_list']);
				foreach ($rows as $row) {
					$row = preg_split("~\t|;|,~", $row);
					$newRow = array();
					foreach ($row as $cell) {
						$newRow[] = trim($cell);
					}
					if (strlen($newRow[0])) {
						if ($newRow[0][0] != '#') {
							$npList[] = $newRow;
						} else {
							$newRow[0] = substr($newRow[0], 1);
							$dpList[] = $newRow;
						}
					}
				}
				$data['new_players_list'] = $npList;
				$data['del_players_list'] = $dpList;
				if (isset($_POST['action_save_new_plist'])) {
					if (FALSE === $this->saveProfiles($data)) {
						moon::page()->page404();
					}
					$this->redirect('event#view', array(
						'event_id' => filter_var($argv['event_id'], FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE),
						'path' => $this->getUriPath()
					), $this->getUriFilter(array('master' => 'list-profiles'), TRUE));
				} else { // if isset action_preview_new_plist
					$this->forget();
					header('content-type: text/plain; charset=utf-8');
					if (FALSE !== ($preview = $this->saveProfiles($data, true))) {
						echo json_encode(array(
							'preview' => $preview,
						));
					}
				}
				moon_close();
				exit;
			case 'load-list-profiles':
				$this->forget();
				if (FALSE !== ($data = $this->getProfilesAjaxData($argv['event_id']))) {
					echo json_encode(array(
						'status' => 0,
						'data' => $data,
					));
				}
				moon_close();
				exit;
			default:
				$page = &moon::page();
				$page->page404();
		}
	}
	
	protected function render($data, $argv = NULL)
	{
		if ($argv['variation'] == 'logControl') {
			return $this->renderListProfiles(array_merge($data, array(
				'unhide' => (!empty($_GET['master']) && $_GET['master'] == 'list-profiles'),
			)));
		} else {
			return $this->renderProfile($data, $argv);
		}
	}

	private function renderListProfiles($argv)
	{
		$tpl = $this->load_template();

		$controlsArgv = array(
			'cl.unhide'     => !empty($argv['unhide']),
			'cl.save_event' => $this->parent->my('fullname') . '#save-list-profiles',
			'cl.event_id'   => $argv['event_id'],
			'cl.day_id'     => $argv['day_id'],
			'cl.paging' => array(),
			'cl.page_by' => $this->listPageBy,
			'sponsor' => '',
			'status' => ''
		);

		$cUriPath = $this->getUriPath();
		$cUriPathZ = $this->getUriPath(0);

		$controlsArgv['cl.url.ajax_load'] = htmlspecialchars_decode($this->linkas('event#load', array(
			'event_id' => $argv['event_id'],
			'path' => $cUriPathZ,
			'type' => 'profile',
			'id' => 'list-profiles'
		), $this->getUriFilter(NULL, TRUE)));

		$playersPaging = $this->getProfilesPagingLabels($argv['event_id']);
		foreach ($playersPaging as $k => $playerPaging) {
			$controlsArgv['cl.paging'][]= $tpl->parse('controls:event.list-profiles.paging.item', array(
				'page' => $k,
				'name' => htmlspecialchars($playerPaging['name'])
			));
		};
		if (count($playersPaging) > 1) {
			$controlsArgv['cl.paging'] = implode('', $controlsArgv['cl.paging']);
		} else {
			$controlsArgv['cl.paging'] = '';
		}
		$controlsArgv['cl.profile_url'] = $this->linkas('event#edit', array(
			'event_id' => $argv['event_id'],
			'path' => $cUriPath,
			'type' => 'profile',
			'id' => '-id-'
		), $this->getUriFilter(NULL, TRUE));
		$controlsArgv['cl.sponsors'] = json_encode(
			$this->lrep()->instEventModel('_src_event')->getSponsors()
		);

		return $tpl->parse('control:list-profiles', $controlsArgv);
	}

	private function getProfilesPagingLabels($eventId) 
	{
		$this->db->query('SET @ROW := -1');
		return $this->db->array_query_assoc('
			SELECT id, name FROM (
				SELECT @ROW:=@ROW+1 AS rk, p.id, p.name
				FROM ' . $this->table('Players') . ' p
				WHERE p.event_id=' . filter_var($eventId, FILTER_VALIDATE_INT) . '
				ORDER BY name
			) i
			GROUP BY FLOOR(rk/' . $this->listPageBy . ')
		');
	}

	private function getProfilesAjaxData($eventId)
	{
		if (NULL == ($prereq = $this->helperSaveCheckPrerequisitesLocationByEvent($eventId))) {
			return FALSE;
		}
		$this->db->query('SET @ROW := -1');
		$rPlayers = $this->db->query('
			SELECT @ROW:=@ROW+1 AS nr, p.id, p.name, p.is_hidden, p.sponsor_id, p.status
			FROM ' . $this->table('Players') . ' p
			WHERE p.event_id=' . filter_var($eventId, FILTER_VALIDATE_INT) . '
			ORDER BY name
		');
		$players = array();
		while ($player = $this->db->fetch_row_assoc($rPlayers)) {
			$players[] = array(
				intval($player['nr']),
				intval($player['id']),
				htmlspecialchars($player['name']),
				intval($player['is_hidden']),
				intval($player['sponsor_id']),
				$player['status'],
			);
		}
		return $players;
	}

	/**
	 * Creates, updates (very limited), deletes (hides)
	 * Data input is slightly processed textarea text
	 */
	private function saveProfiles($data, $pretend = false)
	{
		if (NULL == ($prereq = $this->helperSaveCheckPrerequisitesLocationByDay($data['day_id']))) {
			return FALSE;
		}
		list ($location) = $prereq;

		$tools = $this->lrep()->instTools();
		if ($pretend) {
			$tpl = $this->load_template();
			$previewArgv = array(
				'cc.plist' => ''
			);
		} else {
			$chipsObj = $this->instEventChips();
		}

		foreach ($data['del_players_list'] as $row) {
			if (null === ($name = $tools->helperNormalizeName($row[0])))
				continue;
			if (strpos($name, 'id:') === 0) {
				if (null === ($profile = $this->getProfilesProfileForDeletionById($location['event_id'], substr($name, 3))))
					continue;
			} elseif (null === ($profile = $this->getProfilesProfileForDeletionByName($location['event_id'], $name)))
				continue;

			if ($pretend) {
				$previewArgv['cc.plist'] .= $tpl->parse('controls:list-profiles.save-preview.item', array(
					'is_del_player' => 1,
					'name' => htmlspecialchars($profile['name']),
					'chips' => '',
					'sponsor' => '',
				));
			} else {
				$this->db->update(array(
					'is_hidden' => 1,
					'updated_on' => time()
				), $this->table('Players'), array(
					'id' => $profile['id'],
					'is_hidden' => 0
				));
			}
		}

		$sponsors = array();
		$rSponsors = $this->lrep()->instEventModel('_src_event')->getSponsors();
		foreach ($rSponsors as $rSponsor) {
			$sponsors[strtolower($rSponsor['name'])] = $rSponsor['id'];
		}

		foreach ($data['new_players_list'] as $row) {
			if (null === ($name = $tools->helperNormalizeName($row[0])))
				continue;
			if (!isset($row[1]) || null === ($chips = $tools->helperNormalizeChips($row[1])))
				continue;

			if ($pretend) {
				$tplItemArgs = array(
					'is_new_player' => 1,
					'name' => htmlspecialchars($name),
					'chips' => htmlspecialchars($chips),
					'sponsor' => '',
				);
				$profile = $this->getProfilesProfileForSave($location['event_id'], $name);
				if (isset($profile['id'])) {
					$tplItemArgs['is_new_player'] = 0;
					$tplItemArgs['name'] = htmlspecialchars($profile['name']);
				}
				if (isset($row[2]) && ($row[2] = strtolower($row[2])) && isset($sponsors[$row[2]]))
					$tplItemArgs['sponsor'] = $rSponsors[$sponsors[$row[2]]]['name'];
				$previewArgv['cc.plist'] .= $tpl->parse('controls:list-profiles.save-preview.item', $tplItemArgs);
			} else /* !$pretend */ {
				if (null !== ($profile = $this->getProfilesProfileForSave($location['event_id'], $name))) {
					$saveData = array(
						'updated_on' => time(),
						'is_hidden'  => 0
					);
					if (!$profile['has_chips'])
						$saveData['day_enter_id'] = $location['day_id'];
					if (isset($row[2]) && ($row[2] = strtolower($row[2])) && isset($sponsors[$row[2]]))
						$saveData['sponsor_id'] = $sponsors[$row[2]];
					$this->db->update(
						$saveData,
						$this->table('Players'),
						array(
							'id' => $profile['id']
						)
					);
				} else {
					$saveData = array(
						'created_on'    => time(),
						'name'          => $name,
						'tournament_id' => $location['tournament_id'],
						'event_id'      => $location['event_id'],
						'day_enter_id'  => $location['day_id'],
					);
					if (isset($row[2]) && ($row[2] = strtolower($row[2])) && isset($sponsors[$row[2]]))
						$saveData['sponsor_id'] = $sponsors[$row[2]];
					$this->db->insert(
						$saveData,
						$this->table('Players')
					);
					$profile['id'] = $this->db->insert_id();
					$this->updateProfilePPRels($profile['id'], $saveData['name']);
				}

				if ($profile['id']) // @todo maybe put into transaction together with player insert
					$chipsObj->saveSingleChipSrcEvent(array(
						'day_id' => $location['day_id'],
						'event_id' => $location['event_id'],
						'player_id' => $profile['id'],
						'chips' => $chips
					));
			}
		}

		if ($pretend)
			return $tpl->parse('controls:list-profiles.save-preview', $previewArgv);		
	}	

	private function getProfilesProfileForDeletionByName($eventId, $name)
	{
		$profile = $this->db->single_query_assoc('
			SELECT id, name
			FROM ' . $this->table('Players') . ' 
			WHERE event_id=' . intval($eventId) . '
			  AND name="' . $this->db->escape($name) . '"
			  AND is_hidden=0
		');
		return isset($profile['id'])
			? $profile
			: null;
	}

	private function getProfilesProfileForDeletionById($eventId, $id)
	{
		$profile = $this->db->single_query_assoc('
			SELECT id, name
			FROM ' . $this->table('Players') . ' 
			WHERE event_id=' . intval($eventId) . '
			  AND id=' . intval($id) . '
			  AND is_hidden=0
		');
		return isset($profile['id'])
			? $profile
			: null;
	}

	private function getProfilesProfileForSave($eventId, $name)
	{
		$profile = $this->db->single_query_assoc('
			SELECT p.id, p.name, IFNULL(MAX(c.id),0) has_chips
			FROM ' . $this->table('Players') . ' p
			LEFT JOIN ' . $this->table('Chips') . ' c
				ON c.player_id=p.id
			WHERE p.event_id=' . intval($eventId) . '
			  AND p.name="' . $this->db->escape($name) . '"
			GROUP BY p.id
		');
		return isset($profile['id'])
			? $profile
			: null;
	}

	private function renderProfile($data, $argv)
	{
		$page = moon::page();
		$lrep = $this->lrep();
		$tpl  = $this->load_template();
		$locale = moon::locale();
		$text   = moon::shared('text');

		if (!($page->get_global('adminView') && $lrep->instTools()->isAllowed('writeContent'))) {
			$page->page404();
		}

		if (($profile = $this->getProfile($data['id'], $data['event_id'])) == NULL) {
			$page->page404();
		}
		$sponsors = $lrep->instEventModel('_src_event_profile')->getSponsors();

		$page->title($page->title() . ' | ' . $profile['name']);
		$page->set_local('entry', 'Profile #' . $profile['id']);

		$profileArgv = array(
			'cu.name' => htmlspecialchars($profile['name']),
			'cu.card' => htmlspecialchars($profile['card']),
			'cu.is_pnews' => htmlspecialchars($profile['is_pnews']),
			'cu.is_hidden' => htmlspecialchars($profile['is_hidden']),
			'cu.sponsor' => $profile['sponsor'],
			'cu.sponsorimg' => $profile['sponsor_id'] > 0
				? img('rw', $profile['sponsor_id'], $profile['sponsorimg'])
				: $profile['sponsorimg'],
			'cu.sponsorurl' => $profile['sponsorurl'],
			'cu.status'  => $lrep->instTools()->helperPlayerStatus($profile['status'], $profile['sponsor']),
			'cu.chips_list' => ''
		);

		$chipsHistory = $lrep->instEventModel('_src_event_profile')->getChipsHistory($profile['id'], $data['event_id']);
		foreach ($chipsHistory as $chip) {
			$createdOn = $text->ago($chip['created_on']);
			if ($createdOn == '' || $chip['created_on'] > time()) {
				$createdOn = $locale->gmdatef($chip['created_on'] + $data['tzOffset'], 'Reporting') . ' ' . $data['tzName'];
			}
			$profileArgv['cu.chips_list'] = $tpl->parse('cu.chips_list.item', array(
				'chips' => $chip['chips'],
				'busted' => intval($chip['chips']) == 0,
				'chips_change' => $chip['chips_change'],
				'chips_change_direction' => $chip['chips_change'] != 0
					? (intval($chip['chips_change']) > 0
						? 'up'
						: 'down')
					: NULL,
				'created_on' => $createdOn,
				'delete_url' => $chip['import_id']
					? ''
					: $this->linkas('event#save', array(
						'event_id' => $data['event_id'],
						'type' => 'profile',
						'id' => 'chip-discard.' . $chip['id'])),
				'import_url' => $chip['import_id']
					? $this->linkas('event#view', array(
						'event_id' => $data['event_id'],
						'type' => 'chips',
						'id' => $chip['import_id']))
					: ''
			)) . $profileArgv['cu.chips_list'];
		}

		$profileArgv['control'] = $this->renderProfileControl(array(
			'unhide' => ($argv['action'] == 'edit'),
			'bundled_control' => ($argv['action'] != 'edit'),
			'id' => $profile['id'],
			'event_id' => $data['event_id'],
			'name' => $profile['name'],
			'card' => $profile['card'],
			'is_pnews' => $profile['is_pnews'],
			'is_hidden' => $profile['is_hidden'],
			'sponsor' => $profile['sponsor_id'],
			'sponsors' => $sponsors,
			'status' => $profile['status'],
			'country_id' => strtolower($profile['country_id']),
			'countries' => moon::shared('countries')->getCountries(),
			'bluff' => isset($profile['bluff'])
				? $profile['bluff']
				: NULL
		));

		return $tpl->parse('entry:profile', $profileArgv);
	}
	
	private function getProfile($id, $eventId)
	{
		$profile = $this->db->single_query_assoc('
			SELECT p.id, p.name, p.card, p.is_pnews, p.is_hidden, p.sponsor_id, p.status, p.country_id
			FROM ' . $this->table('Players') . ' p
			WHERE p.id=' . filter_var($id, FILTER_VALIDATE_INT) . '
				AND p.event_id=' . filter_var($eventId, FILTER_VALIDATE_INT)
		);
		if (0 == count($profile)) {
			return NULL;
		}

		$sponsors = $profile['sponsor_id'] !== null
			? $this->lrep()->instEventModel('_src_event')->getSponsorsById(array($profile['sponsor_id']))
			: array();
			
		if (isset($sponsors[$profile['sponsor_id']])) {
			$sponsor = $sponsors[$profile['sponsor_id']];
			$profile += array(
				'sponsor'    => $sponsor['name'],
				'sponsorimg' => $sponsor['favicon'],
				'sponsorurl' => !$sponsor['is_hidden'] && !empty($sponsor['alias'])
					? '/' . $sponsor['alias'] . '/'
					: null
			);
		} else {
			$profile += array(
				'sponsor'    => null,
				'sponsorimg' => null,
				'sponsorurl' => null
			);
		}

		$bluffData = $this->db->single_query_assoc('
			SELECT bluff_id, city, state, country FROM ' . $this->table('PlayersBluff') . '
			WHERE event_id=' . intval($eventId) . ' AND name="' . $this->db->escape($profile['name']) . '"
		');
		if (!empty($bluffData)) {
			$profile['bluff'] = $bluffData;
		}

		return $profile;
	}

	private function renderProfileControl($argv)
	{
		$controlsArgv = array(
			'cu.save_event' => $this->parent->my('fullname') . '#save-profile',
			'cu.unhide' => !empty($argv['unhide']),
			'cu.bundled_control' => !empty($argv['bundled_control']),
			'cu.id' => intval($argv['id']),
			'cu.name' => htmlspecialchars($argv['name']),
			'cu.card' => htmlspecialchars($argv['card']),
			'cu.is_pnews' => htmlspecialchars($argv['is_pnews']),
			'cu.is_hidden' => htmlspecialchars($argv['is_hidden']),
			'cu.event_id' => $argv['event_id'],
			'cu.sponsor' => '',
			'cu.country' => '',
			'cu.status' => $argv['status'],
		);

		$argv['countries'] = array(
			'' => ' ' // alt+255
		) + $argv['countries'];
		foreach ($argv['countries'] as $countryId => $countryName) {
			$controlsArgv['cu.country'] .= '<option value="' . $countryId . '"' . ($countryId == $argv['country_id'] ? ' selected="selected"' : '') . '>' . htmlspecialchars($countryName) . '</option>' ;
		}

		foreach ($argv['sponsors'] as $sponsor) {
			if ($sponsor['is_hidden'] == '0' || $sponsor['id'] == $argv['sponsor']) {
				$controlsArgv['cu.sponsor'] .= '<option value="' . $sponsor['id'] . '"' . ($sponsor['id'] == $argv['sponsor'] ? ' selected="selected"' : '') . '>' . htmlspecialchars($sponsor['name']) . '</option>' ;
			}
		}

		if (!empty($argv['bluff'])) {
			$controlsArgv += array(
				'cu.bluff' => true,
				'cu.bluff.bluff_id' => htmlspecialchars($argv['bluff']['bluff_id']),
				'cu.bluff.country'  => htmlspecialchars($argv['bluff']['country']),
				'cu.bluff.state'    => htmlspecialchars($argv['bluff']['state']),
				'cu.bluff.city'     => htmlspecialchars($argv['bluff']['city']),
			);
		}

		return $this->load_template()
			->parse('controls:profile', $controlsArgv);
	}

	private function saveProfile($argv)
	{
		if (!$this->lrep()->instTools()->isAllowed('writeContent')) {
			return ;
		}

		$this->db->update(array(
			'sponsor_id' => $argv['sponsor'],
			'status' => $argv['status'],
			'country_id' => strtoupper($argv['country_id']),
			'is_pnews' => $argv['is_pnews'],
			'is_hidden' => $argv['is_hidden'],
			'name' => $argv['name'],
			'card' => $argv['card'],
			'updated_on' => time()
		), $this->table('Players'), array(
			'id' => $argv['id']
		));
		$this->updateProfilePPRels($argv['id'], $argv['name']);
		if (!empty($argv['bluff_id'])) {
			$this->db->update(array(
				'country' => $argv['country'],
				'state' => $argv['state'],
				'city' => $argv['city'],
			), $this->table('PlayersBluff'), array(
				'event_id' => $argv['event_id'],
				'bluff_id' => $argv['bluff_id'],
			));
		}

		return filter_var($argv['id'], FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
	}

	/**
	 * Saving players from _chips controls (e.g. chips tab) (with possible loopback back to _chips)
	 * Uses intermediate internal saveProfiles() data format
	 */
	public function savePlayersSrcChips($data)
	{
		return $this->saveProfiles($data);
	}

	/**
	 * used from _event_chips.save_()
	 * For player creation only, when chips component thinks there is no such player present
	 * (which is assumed based on getPreviousChipsByPlayerName(), getPreviousChipsByDayDT() from models/lr_events)
	 */
	public function savePlayerSrcChips($location, $playerName)
	{
		$this->db->insert(array(
			'tournament_id' => $location['tournament_id'],
			'event_id' => $location['event_id'],
			'name' => $playerName,
			'created_on' => time(),
			'day_enter_id' => $location['day_id']
		), $this->table('Players'));
		$playerId = $this->db->insert_id();
		if (!$playerId) {
			return ; // can't distinguish between dupe error (which is ok) and others (which are not)
		}
		$this->updateProfilePPRels($playerId, $playerName);
		return $playerId;
	}

	// saveProfiles, saveProfile, savePlayerSrcChips
	private function updateProfilePPRels($id, $name)
	{
		$player = $this->db->single_query_assoc('
			SELECT id FROM ' . $this->table('PlayersPoker') . '
			WHERE title="' . $this->db->escape($name) . '"
				OR find_in_set("' . $this->db->escape($name) . '", alternative_names)
		');
		$this->db->update(array(
			'pp_id' => !empty($player['id'])
				? $player['id']
				: NULL
		), $this->table('Players'), array(
			'id' => $id
		));
	}
}
