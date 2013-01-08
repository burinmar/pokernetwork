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
class livereporting_event_day extends livereporting_event_pylon
{
	protected function synthEvent($event, $argv)
	{
		switch ($event) {
			case 'save-day-datetime':
				$_POST['datetime_options'] = 'sct_dt';
				$data = $this->helperEventGetData(array('id', 'datetime_options'));
				
				$dayId = $this->saveDatetime($data);
				$this->redirectAfterSave($dayId, 'day');
				exit;
			case 'save-complete': // states
			case 'save-resume':
			case 'save-stop':
			case 'save-start':
				// save day state
				$this->saveState(array(
					'event_id' => $argv['event_id'],
					'day_id'   => $argv['uri']['argv'][3],
					'state'    => $argv['uri']['argv'][2]
				));
				$this->lrep()->instEventModel('_src_event_day')
					->unsetDefaultDayCache($argv['event_id']);
				$this->redirect('event#view', array(
					'event_id' => filter_var($argv['event_id'], FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE),
					'path' => $this->getUriPath($this->requestArgv('day_id'))
				), $this->getUriFilter(NULL, TRUE));
				exit;
			default:
				moon::page()->page404();
		}
	}

	protected function render($data, $argv = NULL)
	{
		$page = moon::page();
		$lrep = $this->lrep();
		$t9n = $this->load_template('livereporting_event')->parse_array('log:t9n');

		$tpl = $this->load_template();
		
		$rArgv = $this->helperRenderCommonArgv($data, $argv, $tpl);
		
		if ($argv['variation'] == 'logEntry') {
			$eventInfo = $lrep->instEventModel('_src_event')->getEventData($this->requestArgv('event_id'));
			$rArgv += array(
				'event_name' => htmlspecialchars($eventInfo['ename']),
				'day_name' => str_replace('{name}', $data['contents']['name'], $t9n['day.named']),
				'has_author'  => $data['author_name'] !== NULL,
				'is_started' => $data['contents']['state'] == 'started',
				'is_completed' => $data['contents']['state'] == 'completed',
			);
			unset($rArgv['url.delete']);
			return $tpl->parse('logEntry:day', $rArgv);
		} elseif ($argv['variation'] == 'individual') {
			if ($argv['action'] == 'view') {
				$page->page404();
			}
			$rArgv += array(
				'day_name' => str_replace('{name}', $data['contents']['name'], $t9n['day.named']),
				'is_started' => $data['contents']['state'] == 'started',
				'is_completed' => $data['contents']['state'] == 'completed',
			);
			if ($rArgv['show_controls']) {
				$rArgv['control'] = $this->renderControl(array(
					'id'  => $data['id'],
					'created_on' => $data['created_on'],
					'tzName' => $data['tzName'],
					'tzOffset' => $data['tzOffset'],
					'unhide' => ($argv['action'] == 'edit'),
					'bundled_control' => ($argv['action'] != 'edit')
				));
			}
			return $tpl->parse('entry:day', $rArgv);
		}
	}

	private function renderControl($argv)
	{
		if (empty($argv['id'])) {
			return ;
		}

		$controlsArgv = array(
			'cd.unhide'          => !empty($argv['unhide']),
			'cd.bundled_control' => !empty($argv['bundled_control']),
			'cd.save_event'      => $this->parent->my('fullname') . '#save-day-datetime',
			'cd.id'              => $argv['id'],
			'cd.custom_datetime' => $this->lrep()->instTools()->helperCustomDatetimeWrite('+Y #m +d +H:M -S -z', (isset($argv['created_on']) ? intval($argv['created_on']) : time()) + $argv['tzOffset'], $argv['tzOffset']),
			'cd.custom_tz'       => $argv['tzName'],
		);

		return $this->load_template()->parse('controls:day', $controlsArgv);
	}

	private function saveDatetime($argv)
	{
		if (!$this->lrep()->instTools()->isAllowed('writeContent')) {
			return ;
		}

		$saveDataLog = array(
			'updated_on' => time(),
			'is_hidden' => 0,
			'author_id' => intval(moon::user()->get_user_id())
		);
		if (NULL != $argv['datetime']) {
			$saveDataLog['created_on'] = $argv['datetime'];
		}
		$this->db->update($saveDataLog, $this->table('Log'), array(
			'id' => $argv['id'],
			'type' => 'day'
		));

		return intval($argv['id']);
	}

	private function saveState($argv)
	{
		if (!$this->lrep()->instTools()->isAllowed('writeContent')) {
			return ;
		}
		$user = moon::user();
		$dayId = filter_var($argv['day_id'], FILTER_VALIDATE_INT);
		
		$dayData = $this->db->single_query_assoc('
			SELECT * FROM ' . $this->table('Days') . '
			WHERE event_id=' . filter_var($argv['event_id'], FILTER_VALIDATE_INT) . '
				AND id=' . $dayId . '
		');
		if (empty($dayData)) {
			return ;
		}
		
		$dayState = NULL;
		switch ($argv['state']) {
			case 'start':
			case 'resume':
				$dayState = 1;
				break;

			case 'stop':
				$dayState = 0;
				break;

			case 'complete':
				$dayState = 2;
				break;
		}
		$this->db->update(array(
			'state' => $dayState,
			'updated_on' => time(),
		), $this->table('Days'), array(
			'id' => $dayId
		));

		$dayStartedId = $this->db->single_query_assoc('
			SELECT l.id FROM ' . $this->table('Log') . ' l
			INNER JOIN ' . $this->table('tDays') . ' d
				ON d.id=l.id
			WHERE l.day_id=' . $dayId . ' AND l.type="day"
				AND d.state=1
		');
		$dayStartedId = !empty($dayStartedId)
			? $dayStartedId['id']
			: NULL;
		$dayCompletedId = $this->db->single_query_assoc('
			SELECT l.id FROM ' . $this->table('Log') . ' l
			INNER JOIN ' . $this->table('tDays') . ' d
				ON d.id=l.id
			WHERE l.day_id=' . $dayId . ' AND l.type="day"
				AND d.state=2
		');
		$dayCompletedId = !empty($dayCompletedId)
			? $dayCompletedId['id']
			: NULL;
		
		// if screwed up
		if ($dayCompletedId && !$dayStartedId) {
			$this->db->query('
				DELETE FROM ' . $this->table('Log') . '
				WHERE id=' . $dayCompletedId . ' AND type="day"
			');
			$this->db->query('
				DELETE FROM ' . $this->table('tDays') . '
				WHERE id=' . $dayCompletedId . '
			');
			$dayCompletedId = NULL;
		}

		switch ($argv['state']) {
			case 'start':
				if ($dayStartedId || $dayCompletedId) {
					return;
				}
				$this->db->insert(array(
					'state' => 1
				), $this->table('tDays'));
				if (!($id = $this->db->insert_id())) {
					return;
				}
				$this->db->insert(array(
					'id' => $id,
					'type' => 'day',
					'tournament_id' => $dayData['tournament_id'],
					'event_id' => $dayData['event_id'],
					'day_id' => $dayId,
					'created_on' => time(),
					'author_id' => intval($user->get_user_id()),
					'contents' => serialize(array(
						'name' => $dayData['name'],
						'state' => 'started'
					))
				), $this->table('Log'));
				break;
			case 'stop':
				if (!$dayStartedId) {
					return;
				}
				$this->db->query('
					DELETE FROM ' . $this->table('Log') . '
					WHERE id=' . $dayStartedId . ' AND type="day"
				');
				$this->db->query('
					DELETE FROM ' . $this->table('tDays') . '
					WHERE id=' . $dayStartedId . '
				');
				// no break
			case 'resume':
				if (!$dayCompletedId) {
					return;
				}
				$this->db->query('
					DELETE FROM ' . $this->table('Log') . '
					WHERE id=' . $dayCompletedId . ' AND type="day"
				');
				$this->db->query('
					DELETE FROM ' . $this->table('tDays') . '
					WHERE id=' . $dayCompletedId . '
				');
				break;
			case 'complete':
				if ($dayCompletedId || !$dayStartedId) {
					return;
				}
				$this->db->insert(array(
					'state' => 2
				), $this->table('tDays'));
				if (!($id = $this->db->insert_id())) {
					return;
				}
				$this->db->insert(array(
					'id' => $id,
					'type' => 'day',
					'tournament_id' => $dayData['tournament_id'],
					'event_id' => $dayData['event_id'],
					'day_id' => $dayId,
					'created_on' => time(),
					'author_id' => intval($user->get_user_id()),
					'contents' => serialize(array(
						'name' => $dayData['name'],
						'state' => 'completed'
					))
				), $this->table('Log'));
				break;
		}
	}
}