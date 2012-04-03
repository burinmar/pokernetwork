<?php

require_once 'base_inplace_syncable.php';
class custom_pages extends base_inplace_syncable
{
	private $promoId;
	private $promo;
	function events($event, $argv)
	{
		if ('' === $argv) {
			moon::page()->page404();
		}
		$promoId = array_pop($argv);
		if (empty($promoId) || !($this->promo = $this->getPromo($promoId))) {
			moon::page()->page404();
		}
		$this->promoId = intval($promoId);
		parent::events($event, $argv);
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

	protected function getUrlNew() 
	{
		return array('#new', $this->promoId);
	}

	protected function getUrlEdit($id)
	{
		return array('#', $id . '.' . $this->promoId);
	}

	protected function getUrlList()
	{
		return array('#', $this->promoId);
	}

	function main($argv)
	{
		$argv['submenu'] = $this->object('promos')->getPreferredSubmenu($this->promoId, $this->promo, $this->my('fullname'));

		return parent::main($argv);
	}

	protected function partialRenderList(&$mainArgv, $tpl, $argv)
	{
		$mainArgv['title'] = htmlspecialchars($this->promo['title']) . ': ' . $mainArgv['title'];
		$mainArgv['submenu'] = $argv['submenu'];
	}

	protected function getEntriesAdditionalFields()
	{
		return array('position');
	}

	protected function partialRenderListRow($row, &$rowArgv, $tpl)
	{
		$rowArgv['pos'] = $row['position'];
	}

	protected function getEntriesAdditionalWhere()
	{
		return array(
			'promo_id=' . $this->promoId
		);
	}

	protected function getEntriesAdditionalOrderBy()
	{
		return array('position');
	}

	protected function getEntriesCanBeAddedDeleted()
	{
		return !$this->getEntriesCanBeSynced();
	}

	protected function getEntriesCanBeSynced()
	{
		return $this->isSlaveHost() && !empty($this->promo['remote_id']);
	}

	protected function partialRenderEntry($argv, &$mainArgv)
	{
		$mainArgv['title'] = htmlspecialchars($this->promo['title']);
		$mainArgv['submenu'] = $argv['submenu'];
	}
	
	protected function getEntryTextFields()
	{
		return array(
			'description'
		);
	}

	protected function getEntryDefault()
	{
		$entry = parent::getEntryDefault();
		$entry['promo_id'] = $this->promo['id'];
		$entry['position'] = $this->db->single_query('
			SELECT MAX(position) FROM promos_pages 
			WHERE promo_id=' . $this->promoId . '
		');
		$entry['position'] = intval($entry['position'][0]) + 1;
		return $entry;
	}

	protected function partialRenderEntryFormOrigin(&$mainArgv, $entryData, $tpl)
	{
		$mainArgv['promo_uri'] = htmlspecialchars($this->promo['alias']);
	}

	protected function getSaveRequiredNoEmptyFields()
	{
		return array('title', 'alias', 'description');
	}
	
	protected function getSaveCustomValidationErrors($saveData)
	{
		$errors = array();
		$uriDupe = $this->db->single_query_assoc('
			SELECT COUNT(id) cid FROM ' . $this->table('Entries') . '
			WHERE promo_id=' . $this->promoId . ' AND alias="' . addslashes($saveData['alias'])  . '"' .
			(($saveData['id'] !== NULL)
				? ' AND id!=' . $saveData['id']
				: '') . '
		');
		if ('0' != $uriDupe['cid']) {
			$errors[] = 'e.dupe_alias';
		}
		return $errors;
	}
}