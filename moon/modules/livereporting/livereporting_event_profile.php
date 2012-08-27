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
	protected function synthEvent($event, $argv)
	{
		switch ($event) {
			case 'save-profile':
				$data = $this->helperEventGetData(array(
					'id', 'event_id', 'name', 'card', 'is_pnews', 'sponsor', 'status', 'country_id',
					'bluff_id', 'city', 'state', 'country'
				));
				$profileId = $this->save($data);
				$this->redirect('event#view', array(
					'event_id' => getInteger($argv['event_id']),
					'path' => $this->getUriPath(),
					'type' => 'profile',
					'id' => $profileId
				), $this->getUriFilter(NULL, TRUE));
				exit;
			case 'save-chip-discard':
				$this->forget();
				$profileId = $this->instEventChips()->deleteSingleSrcProfile($argv['uri']['argv'][3]);
				$this->redirect('event#view', array(
					'event_id' => getInteger($argv['event_id']),
					'path' => $this->getUriPath(),
					'type' => 'profile',
					'id' => $profileId
				), $this->getUriFilter(NULL, TRUE));
				exit;
			case 'delete':
				if (FALSE === $this->delete($argv['uri']['argv'][2])) {
					moon::page()->page404();
				}
				$this->redirect('event#view', array(
					'event_id' => getInteger($argv['event_id']),
					'path' => $this->getUriPath($this->requestArgv('day_id'))
				), $this->getUriFilter(NULL, TRUE));
				exit;
			default:
				$page = &moon::page();
				$page->page404();
		}
	}
	
	protected function render($data, $argv = NULL)
	{
		$page = moon::page();
		$lrep = $this->lrep();
		$tpl  = $this->load_template();
		$locale = moon::locale();
		$text   = moon::shared('text');

		if (!($page->get_global('adminView') && $lrep->instTools()->isAllowed('writeContent'))) {
			$page->page404();
		}

		if (($profile = $this->getProfile($data['id'], $data['event_id'], $data['day_id'])) == NULL) {
			$page->page404();
		}
		$sponsors = $lrep->instEventModel('_src_event_profile')->getSponsors();

		$page->title($page->title() . ' | ' . $profile['name']);
		$page->set_local('entry', 'Profile #' . $profile['id']);

		$profileArgv = array(
			'cu.name' => htmlspecialchars($profile['name']),
			'cu.card' => htmlspecialchars($profile['card']),
			'cu.is_pnews' => htmlspecialchars($profile['is_pnews']),
			'cu.sponsor' => $profile['sponsor'],
			'cu.sponsorimg' => $profile['sponsor_id'] > 0
				? img('rw', $profile['sponsor_id'], $profile['sponsorimg'])
				: $profile['sponsorimg'],
			'cu.sponsorurl' => $profile['sponsorurl'],
			'cu.status'  => $lrep->instTools()->helperPlayerStatus($profile['status'], $profile['sponsor']),
			'cu.chips_list' => ''
		);

		$chipsHistory = $this->getChipsHistory($profile['id'], $data['day_id'], $data['event_id']);
		$seenNullChip = false;
		foreach ($chipsHistory as $chip) {
			$createdOn = $text->ago($chip['created_on']);
			if ($createdOn == '' || $chip['created_on'] > time()) {
				$createdOn = $locale->gmdatef($chip['created_on'] + $data['tzOffset'], 'Reporting') . ' ' . $data['tzName'];
			}
			if ($chip['chips'] === NULL && !$seenNullChip) { // should be initial chip
				$seenNullChip = true;
				continue;
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
				'is_full' => $chip['fi'],
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

		$profileArgv['control'] = $this->renderControl(array(
			'unhide' => ($argv['action'] == 'edit'),
			'bundled_control' => ($argv['action'] != 'edit'),
			'id' => $profile['id'],
			'event_id' => $data['event_id'],
			'name' => $profile['name'],
			'card' => $profile['card'],
			'is_pnews' => $profile['is_pnews'],
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
	
	private function getProfile($id, $eventId, $dayId)
	{
		$profile = $this->db->single_query_assoc('
			SELECT p.id, p.name, p.card, p.is_pnews, p.sponsor_id, p.status, p.country_id
			FROM ' . $this->table('Players') . ' p
			WHERE p.id=' . getInteger($id) . '
				AND p.event_id=' . getInteger($eventId)
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

	private function getChipsHistory($playerId, $dayId, $eventId)
	{
		$lrep = $this->lrep();
		$page = moon::page();
		//if (0 == $dayId) {
		$dayId = array_keys($lrep->instEventModel('_src_event')->getDaysData($eventId));
		//}
		if ($lrep->instTools()->isAllowed('viewLogHidden') && $page->get_global('adminView')) {
			$isHiddenSql = 'is_hidden!=2';
		} else {
			$isHiddenSql = 'is_hidden=0';
		}
		return $this->db->array_query_assoc('
			SELECT c.id, c.chips, c.chips_change, c.import_id, c.is_full_import fi, c.created_on, c.day_id
			FROM ' . $this->table('Chips') . ' c
			LEFT JOIN ' . $this->table('Log') . ' l
				ON l.id=c.import_id AND l.type="chips"
			WHERE c.player_id=' . getInteger($playerId) . '
				AND ' . (is_array($dayId)
					? 'c.day_id IN (' . implode(',', $dayId) . ')'
					: 'c.day_id=' . getInteger($dayId)
				) . '
				AND (l.' . $isHiddenSql . ' OR l.is_hidden IS NULL)
			ORDER BY c.created_on
		');
	}

	private function renderControl($argv)
	{
		$controlsArgv = array(
			'cu.save_event' => $this->parent->my('fullname') . '#save-profile',
			'cu.unhide' => !empty($argv['unhide']),
			'cu.bundled_control' => !empty($argv['bundled_control']),
			'cu.id' => intval($argv['id']),
			'cu.name' => htmlspecialchars($argv['name']),
			'cu.card' => htmlspecialchars($argv['card']),
			'cu.is_pnews' => htmlspecialchars($argv['is_pnews']),
			'cu.event_id' => $argv['event_id'],
			'cu.sponsor' => '',
			'cu.country' => '',
			'cu.status' => $argv['status'],
			'cu.playerdelete' => $this->linkas('event#delete', array(
					'event_id' => $argv['event_id'],
					'type' => 'profile',
					'id' => $argv['id']
				))
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

	private function save($argv)
	{
		if (!$this->lrep()->instTools()->isAllowed('writeContent')) {
			return ;
		}

		$this->db->update(array(
			'sponsor_id' => $argv['sponsor'],
			'status' => $argv['status'],
			'country_id' => strtoupper($argv['country_id']),
			'is_pnews' => $argv['is_pnews'],
			'name' => $argv['name'],
			'card' => $argv['card'],
			'updated_on' => time()
		), $this->table('Players'), array(
			'id' => $argv['id']
		));
		$this->updatePlayerPPRels($argv['id'], $argv['name']);
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

		return getInteger($argv['id']);
	}

	private function updatePlayerPPRels($id, $name)
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

	// used from _event_chips.save_(), _event_event.savePlayers()
	public function notifyPlayerSaved($id, $name) 
	{
		$this->updatePlayerPPRels($id, $name);
	}

	/**
	 * @todo verify more
	 */
	private function delete($playerId)
	{
		if (!$this->lrep()->instTools()->isAllowed('writeContent')) {
			return FALSE;
		}

		$imports = $this->db->array_query_assoc('
			SELECT l.id, l.contents lc, sc.chips scc FROM ' . $this->table('Log') . ' l
			INNER JOIN ' . $this->table('tChips') . ' sc
				ON sc.id=l.id
			INNER JOIN ' . $this->table('Chips') . ' c
				ON c.import_id=l.id AND c.player_id=' . getInteger($playerId) . '
			WHERE l.type="chips"
		');
		foreach ($imports as $import) {
			$import['lc'] = unserialize($import['lc']);
			foreach ($import['lc']['chips'] as $k => $v) {
				if ($v['id'] == $playerId) {
					$import['lc']['chips'][$k]['id'] = 0;
					//$import['lc']['chips'][$k]['uname'] = ''; // keep name for display
				}
			}
			$import['lc'] = serialize($import['lc']);
			$import['scc'] = explode("\n", $import['scc']);
			foreach ($import['scc'] as $k => $v) {
				$v = explode(',', $v);
				if ($v[0] == $playerId) {
					$v[0] = 0;
				}
				$import['scc'][$k] = implode(',', $v);
			}
			$import['scc'] = implode("\n", $import['scc']);
			$this->db->update(array(
				'chips' => $import['scc']
			), $this->table('tChips'), array(
				'id' => $import['id']
			));
			$this->db->update(array(
				'contents' => $import['lc'],
				'updated_on' => time()
			), $this->table('Log'), array(
				'id' => $import['id'],
				'type' => 'chips'
			));
		}

		$this->db->query('
			DELETE FROM ' . $this->table('Players') . '
			WHERE id=' . getInteger($playerId) . '
		');
		$this->db->query('
			DELETE FROM ' . $this->table('Chips') . '
			WHERE player_id=' . getInteger($playerId) . '
		');
	}

	// used from _event_event.savePlayers
	public function deleteProfileFromSubEvent($playerId)
	{
		return $this->delete($playerId);
	}
}