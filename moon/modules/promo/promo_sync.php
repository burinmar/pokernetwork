<?php

class promo_sync extends moon_com 
{
	function events($event, $argv)
	{
		switch ($event) {
			case 'sync':
				ob_start();
				$this->mergeData($argv['data']);
				moon::page()->set_local('transporter', ob_get_contents());
				ob_end_clean();
				return ;
			default:
				moon::page()->page404();
		}
	}

	private function mergeData($data)
	{
		$customPages = array();
		$events = array();
		foreach ($data as $k => $v) {
			if (isset($v['custom_pages'])) {
			foreach ($v['custom_pages'] as $v2) {
				$customPages[] = $v2;
			}}
			unset($data[$k]['custom_pages']);

			if (isset($v['events'])) {
			foreach ($v['events'] as $v2) {
				$events[] = $v2;
			}}
			unset($data[$k]['events']);
			
			if ('0' == $data[$k]['update']) {
				unset($data[$k]);
			} else {
				unset($data[$k]['update']);
			}
		}
		$this->importAbstract($data, 'Promos', 'PromosMaster', array(
			'title',
			'prize',
			'descr_intro',
			'descr_meta',
			'descr_qualify',
			'descr_prize',
			'descr_steps',
			'terms_conditions',
			'lb_columns',
			'lb_descr',
		), array(), FALSE);

		$promosIds = $this->db->array_query_assoc('
			SELECT id, remote_id FROM promos
			WHERE remote_id!=0
		', 'remote_id');
		
		foreach ($customPages as $k => $v) {
			$customPages[$k]['promo_id'] = $promosIds[$v['promo_id']]['id'];
			if (empty($customPages[$k]['promo_id'])) {
				unset($customPages[$k]);
			}
		}
		$this->importAbstract($customPages, 'PromosPages', 'PromosPagesMaster', array(
			'title',
			'description',
			'meta_kwd',
			'meta_descr'
		), array(), FALSE);

		foreach ($events as $k => $v) {
			$events[$k]['promo_id'] = $promosIds[$v['promo_id']]['id'];
			if (empty($events[$k]['promo_id'])) {
				unset($events[$k]);
			}
		}
		$this->importAbstract($events, 'PromosEvents', 'PromosEventsMaster', array(
			'title',
			'results_columns'
		), array(
			'start_date',
			'pwd_date'
		), FALSE);		
	}

	/**
	 * Fork of club.import
	 * Requirements: 
	 * data[] = {id, is_hidden, updated_on}
	 * table {id, is_hidden, updated_on, remote_updated_on}
	 * table_push {id, updated_on}
	 */
	private function importAbstract(
		$data, $localTable, $localTablePush, 
		$translatableFlds, $timestampFlds,
		$autoUnhide
	) {
		$localItems = $this->db->array_query_assoc('
			SELECT id, is_hidden, UNIX_TIMESTAMP(updated_on) updated_on, remote_id, UNIX_TIMESTAMP(remote_updated_on) remote_updated_on
			FROM ' . $this->table($localTable) . '
		', 'remote_id');

		// $deletedIds = array();
		// foreach ($localItems as $item) {
		// 	$deletedIds[$item['id']] = $item['id'];
		// }
		// foreach ($data as $item) {
		// 	unset($deletedIds[$item['id']]);
		// }
		// if (0 != count($deletedIds)) {
		// $this->db->query('
		// 	UPDATE ' . $this->table($localTable) . '
		// 	SET is_hidden=2
		// 	WHERE id IN(' . implode(',', $deletedIds) . ')
		// ');}

		foreach ($data as $item) {
			$local = isset($localItems[$item['id']])
				? $localItems[$item['id']]
				: NULL;
			if (NULL != $local && $item['updated_on'] <= $local['remote_updated_on']) {
				continue; // skip unless newer
			}

			foreach ($timestampFlds as $key) {
				if (NULL !== $item[$key]) {
					$item[$key] = array('FROM_UNIXTIME', $item[$key]);
				}
			}

			if (NULL == $local) { // completely fresh item
				if ($item['is_hidden']) {
					continue; // no need to import yet (probably incomplete yet / deleted before first sync)
				}

				$data = array_merge($item, array(
					'is_hidden'  => 1,
					'updated_on' => '0000-00-00 00:00:00',
					'remote_id' => $item['id'],
					'remote_updated_on' => array('FROM_UNIXTIME', $item['updated_on'])
				));
				if ($autoUnhide) {
				$data = array_merge($data, array(
					'is_hidden'  => 0,
					'updated_on' => array('FROM_UNIXTIME', $item['updated_on'])
				));}

				$dataPush = array(
					'id' => $data['id']
				);
				foreach ($translatableFlds as $key) {
					$dataPush[$key] = $data[$key];
				}

				unset($data['id']);
				$this->dbInsert($data, $this->table($localTable));
				$this->dbInsert($dataPush, $this->table($localTablePush));
			} else { // existing item
				$wasLocalTranslated = $local['updated_on'] >= $local['remote_updated_on'];
				// if entry was translated, store translatable fields in _prev columns
				// entry in `remote` table may not exist if there was previously an error

				// if some translatable fields were updated
				$updatingTranslations = true;

				if ($wasLocalTranslated) {
					$data = array();
					foreach ($translatableFlds as $value) {
						$data[] = $value . '_prev=' . $value;
					}
					$this->db->query('UPDATE ' . $this->table($localTablePush) . ' 
						SET ' .
						implode(', ', $data) . '
						WHERE id=' . $item['id']);
				}

				// populate `remote` table with current translatable fields
				$remoteItemExists = $this->db->single_query_assoc('
					SELECT id FROM ' . $this->table($localTablePush) . '
					WHERE id=' . $item['id'] . '
				');
				$data = array();
				foreach ($translatableFlds as $value) {
					$data[$value] = $item[$value];
				}
				if (!empty($remoteItemExists)) {
					$this->dbUpdate($data, $this->table($localTablePush), array(
						'id' => $item['id']
					));
					if ($this->db->affected_rows() == 0) {
						$updatingTranslations = false;
					}
				} else {
					$data['id'] = $item['id'];
					$this->dbInsert($data, $this->table($localTablePush));
				}

				$data = array_merge($item, array(
					'remote_updated_on' => array('FROM_UNIXTIME', $item['updated_on'])
				));
				unset($data['updated_on']);
				if ('0' == $item['is_hidden']) {
					// do not unhide locally hidden entries
					unset($data['is_hidden']);
				}
				if ('2' != $item['is_hidden'] && '2' == $local['is_hidden']) {
					// but undelete locally deleted entries
					$data['is_hidden'] = 1;
				}

				if ($autoUnhide || $local['updated_on'] == '0') {
					// if was never translated yet, or autounhide: update translatables
				} else {
					foreach ($translatableFlds as $value) {
						unset($data[$value]);
					}
				}
				$data = array_merge($data, array( // either leave updated_on as it was, or bump to mark as translated
					'updated_on' => $autoUnhide || (!$updatingTranslations && $wasLocalTranslated)
						? array('FROM_UNIXTIME', $item['updated_on'])
						: array('FROM_UNIXTIME', $local['updated_on']) // don't allow to auto-update "updated_on"
				));
				if ($data['updated_on'][1] == 0) {
					$data['updated_on'] = '0000-00-00 00:00:00';
				}

				unset($data['id']);
				$this->dbUpdate($data, $this->table($localTable), array(
					'remote_id' => $item['id']
				));
			}
		}
	}

	private function dbInsert($row, $table)
	{
		foreach ($row as $k => $v) {
			$row[$k] = is_null($v) ? 'NULL' : (
				is_array($v)
					? ($v[0] . '(\'' . $this->db->escape($v[1]) . '\')')
					: ("'" . $this->db->escape($v) . "'")
			);
		}
		$sql = "INSERT INTO `". $table . "` (`" . implode("`, `", array_keys($row)) . "`) VALUES (" . implode(',', array_values($row)) . ')';
		$r = $this->db->query($sql);
		return $r
			? $this->db->insert_id()
			: FALSE;
	}

	private function dbUpdate($row, $table, $id = false)
	{
		$where = '';
		if (is_array($id)) {
			foreach ($id as $k => $v) {
				$where .= $where === '' ? ' WHERE ' : ' AND ';
				$where .= "(`$k`='" . $this->db->escape($v) . "')";
			}
		}
		$set = array();
		foreach ($row as $k => $v) {
			$set[] = '`' . $k . '`=' . (is_null($v) ? 'NULL' : (
				is_array($v)
					? ($v[0] . (isset($v[1]) ? ('(\'' . $this->db->escape($v[1]) . '\')') : ''))
					: ("'" . $this->db->escape($v) . "'")
			));
		}
		$sql = "UPDATE `" . $table . "` SET " . implode(',', $set) . $where;
		$r = $this->db->query($sql);
		return $r
			? $this->db->insert_id()
			: FALSE;
	}	
}
