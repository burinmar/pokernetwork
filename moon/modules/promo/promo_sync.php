<?php

class promo_sync extends moon_com
{
	function events($event, $argv)
	{
		switch ($event) {
			// adm => *
			case 'synced-versions':
				moon::page()->set_local('transporter', $this->getVersionStatus());
				return ;
			case 'sync-base':
				ob_start();
				include_class('lock');
				$lock = SunLock::dbLock($this->db, 'promo_promos_sync.' . _SITE_ID_ . '.lock', 30);
				if ($lock->tryLock()) {
					$this->mergeBaseData($argv['data']);
					$lock->unlock();
				}
				moon::page()->set_local('transporter', ob_get_contents());
				ob_end_clean();
				return ;

			// com => *
			case 'sync-text':
				ob_start();
				include_class('lock');
				$lock = SunLock::dbLock($this->db, 'promo_promos_sync.' . _SITE_ID_ . '.lock', 30);
				if ($lock->tryLock()) {
					$this->mergeTextData($argv['data']);
					$lock->unlock();
				}
				moon::page()->set_local('transporter', ob_get_contents());
				ob_end_clean();
				return ;

			case 'get-active-promos':
				return moon::page()->set_local('transporter', ($this->getActivePromosExport()));

			default:
				moon::page()->page404();
		}
	}

	private function getVersionStatus()
	{
		return $this->db->array_query_assoc('
			SELECT id, base_version version FROM ' . $this->table('Promos') . '
		');
	}

	private function mergeBaseData($data)
	{
		$this->db->exceptions(true);
		try {
			$this->db->query('begin');

			$domainData = array();
			array_walk($data, function($row) use (&$domainData) {
				if (!isset($row['self'])) return ;
				$domainData[] = $row['self'];
			});
			$this->importBaseAbstract($domainData, 'Promos', 'PromosMaster', [
				'title',
				'prize',
				'lb_columns'
			], []);

			$domainData = array();
			array_walk($data, function($row) use (&$domainData) {
				if (isset($row['custom_pages']))
				foreach ($row['custom_pages'] as $k => $v)
					$domainData[] = $v;
			});
			$this->importBaseAbstract($domainData, 'PromosPages', 'PromosPagesMaster', [
				'title',
			], []);

			$domainData = array();
			array_walk($data, function($row) use (&$domainData) {
				if (isset($row['events']))
				foreach ($row['events'] as $k => $v)
					$domainData[] = $v;
			});
			$this->importBaseAbstract($domainData, 'PromosEvents', 'PromosEventsMaster', [
				'title',
				'results_columns'
			], [
				'start_date',
				'pwd_date'
			]);

			$this->db->query('commit');

			echo 'ok';
		} catch (Exception $ex) {
			$this->db->query('rollback');
			moon::error($ex->getMessage());
			echo 'nok';
		} finally {
			$this->db->exceptions(false);
		}
	}

	private function importBaseAbstract(
		$remote_rows, $localTable, $localTablePush,
		$translatableFlds, $timestampFlds
	) {
		$local_rows = $this->db->array_query_assoc('
			SELECT id, is_hidden, base_version,
				UNIX_TIMESTAMP(updated_on) updated_on
			FROM ' . $this->table($localTable) . '
		', 'id');

		foreach ($remote_rows as $remote) {
			$local = isset($local_rows[$remote['id']])
				? $local_rows[$remote['id']]
				: NULL;
			if (!is_null($local) && $remote['version'] < $local['base_version'])
				continue; // skip if older

			if (isset($remote['sites']))
				$promo_sites = explode(',', $remote['sites']);
			else
				$promo_sites = explode(',', $this->db->single_query_assoc('SELECT sites FROM promos WHERE id=' . intval($remote['promo_id']))['sites']);

			if (is_null($local)) { // completely fresh item
				// base data
				$data = array_merge($remote, [
					'is_hidden'  => max(1, $remote['is_hidden']), // always hidden, might be deleted too
					'updated_on' => '0000-00-00 00:00:00',
					'remote_updated_on' => ['CURRENT_TIMESTAMP'],
					'base_version' => $remote['version'],
				]);
				unset($data['version']);

				// additional hiding rules
				// mark site-disabled entries as deleted
				if (!in_array(_SITE_ID_, $promo_sites))
					$data['is_hidden'] = 2;
				// should wait for .com translation? if yes, mark as deleted until translation arrives
				else if (_SITE_ID_ != 'com' && in_array('com', $promo_sites))
					$data['is_hidden'] = 2;

				// timestamp fields
				foreach ($timestampFlds as $key)
					if (!is_null($data[$key]))
						$data[$key] = ['FROM_UNIXTIME(' . intval($remote[$key]) . ')'];

				// _push fields
				$data_push = ['id' => $data['id']];
				foreach ($translatableFlds as $key)
					$data_push[$key] = $remote[$key];

				$this->db->insert($data, $this->table($localTable));
				$this->db->insert($data_push, $this->table($localTablePush));
			} else { // existing item

				// if some translatable fields were updated
				$updated_translatables = false;

				// only when not receiving translations from .com, accept translatable changes
				// from .adm
				if (_SITE_ID_ == 'com' || !in_array('com', $promo_sites)) {
					// populate _push table with current translatable fields
					$data_push = [];
					foreach ($translatableFlds as $value)
						$data_push[$value] = $remote[$value];
					$this->db->update($data_push, $this->table($localTablePush), [
						'id' => $remote['id']
					]);
					if ($this->db->affected_rows() != 0)
						$updated_translatables = true;
				}

				// base data
				$data = array_merge($remote, [
					'updated_on' => ['updated_on'], // don't allow to auto-update "updated_on"
					'base_version' => $remote['version'],
				]); unset($data['version']);
				if ($updated_translatables)
					$data['remote_updated_on'] = ['CURRENT_TIMESTAMP'];

				// additional hiding rules
				// mark site-disabled entries as deleted
				if (!in_array(_SITE_ID_, $promo_sites))
					$data['is_hidden'] = 2;
				// should wait for .com translation?
				else if (_SITE_ID_ != 'com' && in_array('com', $promo_sites) && '2' == $local['is_hidden'])
					$data['is_hidden'] = 2;
				// undelete locally deleted entries
				else if ('2' != $remote['is_hidden'] && '2' == $local['is_hidden'])
					$data['is_hidden'] = 1;
				// do not unhide locally hidden entries
				else if ('0' == $remote['is_hidden'])
					unset($data['is_hidden']);

				// timestamp fields
				foreach ($timestampFlds as $key)
					if (NULL !== $data[$key])
						$data[$key] = ['FROM_UNIXTIME(' . intval($remote[$key]) . ')'];

				// translatables can sometimes be updated directly
				if ($local['updated_on'] == '0') {
					// if was never translated yet: update translatables
				} else {
					// otherwise do not, sufficient data should be in _push table
					foreach ($translatableFlds as $value)
						unset($data[$value]);
				}

				$this->db->update($data, $this->table($localTable), array(
					'id' => $data['id']
				));
			}
		}
	}

	private function mergeTextData($data)
	{
		$this->db->exceptions(true);
		try {
			$this->db->query('begin');

			$domainData = array();
			array_walk($data, function($row) use (&$domainData) {
				if (!isset($row['self'])) return ;
				$domainData[] = $row['self'];
			});
			$this->importTextAbstract($domainData, 'Promos', 'PromosMaster', [
				'title',
				'menu_title',
				'prize',
				'descr_intro',
				'descr_meta',
				'descr_qualify',
				'descr_prize',
				'descr_steps',
				'terms_conditions',
				'lb_columns',
				'lb_descr',
				'review_title',
				'review_descr'
			]);

			$domainData = array();
			array_walk($data, function($row) use (&$domainData) {
				if (isset($row['custom_pages']))
				foreach ($row['custom_pages'] as $k => $v)
					$domainData[] = $v;
			});
			$this->importTextAbstract($domainData, 'PromosPages', 'PromosPagesMaster', [
				'title',
				'description',
				'meta_kwd',
				'meta_descr'
			]);

			$domainData = array();
			array_walk($data, function($row) use (&$domainData) {
				if (isset($row['events']))
				foreach ($row['events'] as $k => $v)
					$domainData[] = $v;
			});
			$this->importTextAbstract($domainData, 'PromosEvents', 'PromosEventsMaster', [
				'title',
				'results_columns'
			]);

			$this->db->query('commit');
		} catch (Exception $ex) {
			$this->db->query('rollback');
			moon::error($ex->getMessage());
		} finally {
			$this->db->exceptions(false);
		}
	}

	private function importTextAbstract(
		$remote_rows, $localTable, $localTablePush,
		$translatableFlds
	) {
		$local_rows = $this->db->array_query_assoc('
			SELECT id, is_hidden, UNIX_TIMESTAMP(updated_on) updated_on, UNIX_TIMESTAMP(remote_updated_on) remote_updated_on
			FROM ' . $this->table($localTable) . '
		', 'id');

		foreach ($remote_rows as $remote) {
			$local = isset($local_rows[$remote['id']])
				? $local_rows[$remote['id']]
				: NULL;
			if (is_null($local))
				continue ;
			else if ($remote['updated_on'] < $local['remote_updated_on']) {
				continue; // skip if older
			}

			// if some translatable fields were updated
			$updated_translatables = false;
			// populate _push table with current translatable fields
			$data_push = [];
			foreach ($translatableFlds as $value)
				$data_push[$value] = $remote[$value];
			$this->db->update($data_push, $this->table($localTablePush), [
				'id' => $remote['id']
			]);
			if ($this->db->affected_rows() != 0)
				$updated_translatables = true;

			// base data
			$data = [
				'updated_on' => ['updated_on'], // don't allow to auto-update "updated_on"
			];
			if ($updated_translatables)
				$data['remote_updated_on'] = ['CURRENT_TIMESTAMP'];

			// undelete locally deleted entries
			if ('2' != $remote['is_hidden'] && '2' == $local['is_hidden'])
				$data['is_hidden'] = 1;

			// translatables can sometimes be updated directly
			if ($local['updated_on'] == '0') {
				// if was never translated yet: update translatables
				foreach ($translatableFlds as $value)
					$data[$value] = $remote[$value];
			}


			$this->db->update($data, $this->table($localTable), array(
				'id' => $remote['id']
			));
		}
	}

	private function getActivePromosExport()
	{
		return array_keys($this->db->array_query_assoc('
			SELECT id FROM promos
			WHERE is_hidden = 0
			  AND FIND_IN_SET("' . _SITE_ID_ . '", sites)
		', 'id'));
	}
}
