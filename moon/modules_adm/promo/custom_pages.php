<?php

class custom_pages extends moon_com_ext
{
	use promos_base;
	private $promoId;
	private $promo;
	function events($event, $argv)
	{
		if ('' === $argv)
			moon::page()->page404();
		$promoId = array_pop($argv);
		if (empty($promoId) || !($this->promo = $this->getPromo($promoId)))
			moon::page()->page404();
		$this->promoId = intval($promoId);

		switch ($event) {
			case 'save':
				$data = $this->eventPostData(array_merge(array_keys($this->entryFromVoid()), [
					'stayhere'
				]));
				$savedId = $this->saveEntry($data);
				return $this->eventSaveRedirect($savedId, $data, ['#', $this->promoId], ['#', '{id}.' . $this->promoId]);

			case 'delete':
				$data = $this->eventPostData('ids');
				$this->deleteEntry(explode(',', $data['ids']));
				$this->redirect('#', $this->promoId);
				exit;

			case 'new':
				$this->set_var('render', 'entry');
				break;

			default:
				if (isset($argv[0]) && false !== ($id = filter_var($argv[0], FILTER_VALIDATE_INT))) {
					$this->set_var('render', 'entry');
					$this->set_var('id', $id);
				}
				if (isset ($_GET['page'])) {
					$this->set_var('page', (int)$_GET['page']);
				}
				break;
		}

		$this->use_page('Common');
		moon::page()->js('/js/modules_adm/promo.js');
	}

	private function getPromo($id)
	{
		$flds = $this->isSlaveHost()
			? 'id, alias, title, lb_auto, remote_id'
			: 'id, alias, title, lb_auto';
		$promo = $this->db->single_query_assoc('
			SELECT ' . $flds . ' FROM promos
			WHERE id=' . intval($id) . '
			  AND is_hidden<2
		');
		return $promo;
	}

	function main($argv)
	{
		moon::shared('admin')->active($this->my('fullname'));

		return parent::main($argv);
	}

	/**
	 * List
	 */
	protected function renderList($argv, &$e)
	{
		$page   = moon::page();
		$tpl    = $this->load_template();

		$page->js('/js/modules_adm/ng-list.js');

		$iAmHere = array_reverse(moon::shared('admin')->breadcrumb());
		$mainArgv  = array(
			'title' => isset($iAmHere[0])
				? $iAmHere[0]['name']
				: '',
			'url.add_entry' => !$this->isSlaveHost() || empty($this->promo['remote_id'])
				? $this->link('#new', $this->promoId) : null,
			'event.delete'  => !$this->isSlaveHost() || empty($this->promo['remote_id'])
				? $this->my('fullname') . '#delete' : null,
			'list.entries'  => '',
			'paging' => '',
			'submenu' => $this->getPreferredSubmenu($this->promoId, $this->promo, $this->my('fullname')),
			'synced_notice' => $this->isSlaveHost() && !empty($this->promo['remote_id']),
		);

		$pn = moon::shared('paginate');
		$pn->set_curent_all_limit($argv['page'], $this->getEntriesCount(), $this->get_var('entriesPerPage'));
		$pn->set_url($this->link('#', $this->promoId, ['page' => '{pg}']), $this->link('#', $this->promoId));
		$pnInfo = $pn->get_info();
		$mainArgv['paging'] = $pn->show_nav();

		$list = $this->getEntriesList($pnInfo['sqllimit']);
		foreach ($list as $row) {
			$rowArgv = array(
				'id' => $row['id'],
				'class' => $row['is_hidden'] ? 'item-hidden' : '',
				'url' => $this->link('#', $row['id'] . '.' . $this->promoId),
				'name' => htmlspecialchars($this->getEntryTitle($row)),
				'deletable' => empty($row['remote_id']),
				'pos' => $row['position'],
			);
			if (!empty($row['remote_id'])) {
				$rowArgv['synced'] = true;
				$rowArgv['sync_state'] = intval($row['updated_on'])>intval($row['remote_updated_on']) ? 1 : 2;
			}
			$mainArgv['list.entries'] .= $tpl->parse('list:entries.item', $rowArgv);
		}
		return $tpl->parse('list:main', $mainArgv);
	}

	private function getEntriesCount()
	{
		$cnt = $this->db->single_query_assoc('
			SELECT count(*) cnt
			FROM ' . $this->table('Entries') . ' e
			WHERE is_hidden<2 AND promo_id=' . $this->promoId
		);
		return $cnt['cnt'];
	}

	private function getEntriesList($limit)
	{
		$fields = array(
			'id', 'title', 'is_hidden', 'UNIX_TIMESTAMP(updated_on) updated_on',
			'position'
		);
		if ($this->isSlaveHost()) {
			$fields[] = 'remote_id';
			$fields[] = 'UNIX_TIMESTAMP(remote_updated_on) remote_updated_on';
		}
		return $this->db->array_query_assoc('
			SELECT ' . implode(',', $fields) . '
			FROM ' . $this->table('Entries') . ' e
			WHERE is_hidden<2 AND promo_id=' . $this->promoId .'
			ORDER BY position ' .
			$limit
		);
	}

	/**
	 * Entry
	 */
	protected function renderEntry($argv, &$e)
	{
		$page   = moon::page();
		$tpl    = $this->load_template();
		$locale = moon::locale();
		$text = moon::shared('text');
		$mainArgv  = array(
			'url.back' => $this->link('#', $this->promoId),
			'event.save' => $this->my('fullname') . '#save'
		);

		if (NULL === $argv['id']) {
			$entryData = $this->entryFromVoid();
		} else {
			if (NULL === ($entryData = $this->entryFromDB($argv['id']))) {
				$messages = $tpl->parse_array('messages');
				$e  = $messages['e.entry_not_found'];
				return ;
			}
		}
		$entryData = array_merge($entryData, $this->popFailedFormData());

		if (NULL !== $argv['id']) {
			$mainArgv['submenu'] = $this->getPreferredSubmenu($this->promoId, $this->promo, $this->my('fullname'));
		}
		if (!isset($mainArgv['title'])) {
			$mainArgv['title'] = htmlspecialchars($this->getEntryTitle($entryData));
		}

		//
		foreach ($entryData as $key => $value) {
			$mainArgv['entry.' . $key] = htmlspecialchars($value);
		}
		// varchar maxlen
		foreach ($this->getEntryCharLength() as $key => $value) {
			$mainArgv['form.' . $key . '.maxlen'] = $value;
		}
		// text toolbars
		$rtf = $this->object('rtf');
		if ('' != ($varRtf = $this->get_var('rtf'))) {
			$rtf->setInstance($varRtf);
		}
		foreach ($this->getEntryRtfFields() as $k) {
			$mainArgv['entry.' . $k . '.toolbar'] = $rtf->toolbar($k, (int)$entryData['id']);
		}
		//
		$mainArgv['form.hide'] = empty($entryData['is_hidden']) && !(NULL === $argv['id']) ? '1' : '1" checked="checked';

		//
		if ($this->isSlaveHost() && !empty($entryData['remote_id'])) {
			$mainArgv['syncStatus'] = intval($entryData['updated_on'])>intval($entryData['remote_updated_on']) ? 1 : 2;
			$mainArgv['remote_id'] = $entryData['remote_id'];
			$remote = $this->getOriginInfo($entryData['remote_id']);
			foreach ($this->getEntryTranslatables() as $k) {
				$mainArgv['form.origin_' . $k] = !empty($remote[$k . '_prev']) || $entryData['updated_on'] != '0'
					? nl2br($text->htmlDiff($remote[$k . '_prev'], $remote[$k]))
					: nl2br(htmlspecialchars(@$remote[$k]));
			}
		}

		if (empty($entryData['remote_id'])) {
			$mainArgv['promo_uri'] = htmlspecialchars($this->promo['alias']);
		}

		$tplName = empty($entryData['remote_id'])
			? 'entry:main'
			: 'entry:slaveMain';
		return $tpl->parse($tplName, $mainArgv);
	}

	/**
	 * Entry/save shared
	 */
	protected function entryFromVoid()
	{
		$entry = $this->entryFromMetadata($this->table('Entries'));
		$entry['promo_id'] = $this->promo['id'];
		$entry['position'] = $this->db->single_query('
			SELECT MAX(position) FROM promos_pages
			WHERE promo_id=' . $this->promoId . '
		');
		$entry['position'] = intval($entry['position'][0]) + 1;
		return $entry;
	}

	private function entryFromDB($id)
	{
		if (false === filter_var($id, FILTER_VALIDATE_INT)) {
			return NULL;
		}
		$fields = array('*');
		foreach ($this->getEntryTimestampFields() as $field) {
			$fields[] = 'UNIX_TIMESTAMP(' . $field . ') ' . $field . '';
		}
		$entry = $this->db->single_query_assoc('
			SELECT ' . implode(', ', $fields) . '
			FROM ' . $this->table('Entries') . '
			WHERE id=' . $id . '
		');
		if (empty($entry)) {
			return NULL;
		}
		return $entry;
	}

	/**
	 * Save
	 */
	protected function saveEntry($data)
	{
		return $this->saveEntryFlow($data, [
			'origin_validate' => function($data) {
				$errors = array();
				$uriDupe = $this->db->single_query_assoc('
					SELECT COUNT(id) cid FROM ' . $this->table('Entries') . '
					WHERE promo_id=' . $this->promoId . ' AND alias="' . $this->db->escape($data['alias'])  . '"' .
					(($data['id'] !== NULL)
						? ' AND id!=' . $data['id']
						: '') . '
				');
				if ('0' != $uriDupe['cid']) {
					$errors[] = 'e.dupe_alias';
				}
				if (in_array($data['alias'], array('results', 'schedule', 'terms-and-conditions'))) {
					$errors[] = 'e.reserved_alias';
				}
				return $errors;
			}
		]);
	}

	/**
	 * Delete
	 */
	private function deleteEntry($ids)
	{
		return $this->deleteEntryWorkflow($ids);
	}
}