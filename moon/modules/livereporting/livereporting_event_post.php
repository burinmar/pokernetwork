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
class livereporting_event_post extends livereporting_event_pylon
{
	protected function synthEvent($event, $argv)
	{
		switch ($event) {
			case 'save-post':
				$data = $this->helperEventGetData(array(
					'post_id', 'day_id', 'title', 'body', 'tags', 'published', 'is_exportable', 'is_keyhand', 'round_id', 'datetime_options'
				));
				$this->helperEventGetLeadingImage($data);

				$postId = $this->save($data);
				$this->redirectAfterSave($postId, 'post');
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

	protected function render($data, $argv)
	{
		if ($argv['variation'] == 'logControl') {
			return $this->renderControl(array_merge($data, array(
				'title' => '',
				'contents' => '',
				'tags' => '',
				'is_exportable' => 1,
				'is_keyhand' => 0,
				'unhide' => (!empty($_GET['master']) && $_GET['master'] == 'post')
			)));
		}
		
		$lrep = $this->lrep();
		$page = moon::page();
		$tpl = $this->load_template();
		
		$rArgv = $this->helperRenderCommonArgv($data, $argv, $tpl);
		
		$rArgv['body'] = str_replace('src="/i/cards_sign/', 'src="/img/cards/', $rArgv['body']);

		if ($argv['variation'] == 'logEntry') {
			return $tpl->parse('logEntry:post', $rArgv);
		} elseif ($argv['variation'] == 'individual') {
			if ($rArgv['show_controls']) {
				$entry = $this->getEditableData($data['id'], $data['event_id']);
				if (!empty($entry))
				$rArgv['control'] = $this->renderControl(array_merge($entry, array(
					'keep_old_dt' => true,
					'tzName' => $data['tzName'],
					'tzOffset' => $data['tzOffset'],
					'tags' => implode(', ', $entry['tags']),
					'published' => empty($entry['is_hidden']),
					'unhide' => ($argv['action'] == 'edit'),
					'bundled_control' => ($argv['action'] != 'edit'),
				)));
			}
			$page->title($page->title() . ' | ' . $rArgv['title']);
			$this->helperRenderOGMeta($rArgv);
			return $tpl->parse('entry:post', $rArgv);
		}
	}

	private function renderControl($argv)
	{
		if (empty($argv['day_id'])) {
			return ;
		}
		$lrep = $this->lrep();
		$rtf = $this->object('rtf');
		$rtf->setInstance($this->get_var('rtf') . '-post:0');

		if (!empty($argv['synced']))
			$argv['contents'] = preg_replace('~{poll:([0-9]+)}~', '' /*'<!-- {poll:com:\1} -->'*/, $argv['contents']);

		$controlsArgv = array(
			'cp.save_event' => $this->parent->my('fullname') . '#save-post',
			'cp.id' => isset($argv['id'])
				? intval($argv['id'])
				: '',
			'cp.title' => htmlspecialchars($argv['title']),
			'cp.body' => htmlspecialchars($argv['contents']),
			'cp.tags' => htmlspecialchars($argv['tags']),
			'cp.is_exportable' => $argv['is_exportable'],
			'cp.is_keyhand' => $argv['is_keyhand'],
			'cp.day_id' => $argv['day_id'],
			'cp.event_id' => $argv['event_id'],
			'cp.unhide' => !empty($argv['unhide']),
			'cp.bundled_control' => !empty($argv['bundled_control']),
			'cp.published' => !empty($argv['published']),
			'cp.round_id' => empty($argv['round_id']) 
				? '' 
				: $argv['round_id'],
			'cp.toolbar' => $rtf->toolbar('rq-wp-body', 
				isset($argv['id']) 
					? intval($argv['id'])
					: '',
				array('noarticle'=>true)),
		);
		
		list(
			$controlsArgv['cp.datetime_options'],
			$controlsArgv['cp.custom_datetime'],
			$controlsArgv['cp.custom_tz']
		) = $this->helperRenderControlDatetime($argv, $lrep);
		
		list(
			$controlsArgv['cp.url.ipn'],
			$controlsArgv['cp.url.ipnpreview'],
			$controlsArgv['cp.url.ipnupload'],
			$controlsArgv['cp.ipnimageid'],
			$controlsArgv['cp.ipnimagemisc'],
			$controlsArgv['cp.ipnimagetitle'],
			$controlsArgv['cp.ipnimagesrc'],
		) = $this->helperRenderIpn($argv, 'post');
		if ('com' === _SITE_ID_) $controlsArgv['hl'] = $argv['tournament_id']; else $controlsArgv['hl'] = FALSE;
		return $this->load_template()
			->parse('controls:post', $controlsArgv);
	}

	private function save($data)
	{
		if (NULL == ($prereq = $this->helperSaveCheckPrerequisites($data['day_id'], $data['post_id'], 'post', array('created_on')))) {
			return FALSE;
		}
		list (
			$location,
			$entry
		) = $prereq;

		$userId = intval(moon::user()->get_user_id());
		$tags = $this->helperSaveGetTags($data['tags']);
		
		$rtf = $this->object('rtf');
		$rtf->setInstance($this->get_var('rtf') . '-post:0');
		list(,$data['body_compiled']) = $rtf->parseText($entry['id'], $data['body']);
		
		$saveDataPost = array(
			'title' => $data['title'],
			'contents' => $data['body'],
			'is_exportable' => $data['is_exportable'],
			'is_keyhand' => $data['is_keyhand'],
			'image_misc' => (!empty($data['image']))
				? $data['image']['id'] . ',' . $data['image']['misc']
				: NULL,
			'image_src' => @$data['image']['src'],
			'image_alt' => @$data['image']['title'],
		);
		$saveDataLog = array(
			'type' => 'post',
			'is_hidden' => $data['published'] != '1',
			'contents' => array(
				'title' => $data['title'],
				'contents' => $data['body_compiled'], // maybe cut a little bit (e.g. 30kb) ?
				'i_misc' => (!empty($data['image']))
					? $data['image']['id'] . ',' . $data['image']['misc']
					: NULL,
				'i_src' => @$data['image']['src'],
				'i_alt' => @$data['image']['title'],
				'tags' => $tags
			)
		);
		
		$this->helperSaveAssignCommonLogAttrs($saveDataLog, $userId, $entry, $data, $location);

		if ($entry['id'] != NULL) { // update
			$createdOn = array_intersect_key(array_merge($entry, $saveDataLog), array('created_on' => '')); // was [+ saving]
			$saveDataLog['contents']['round'] = $this->lrep()->instEventModel('_src_event_post')->getRound($location['event_id'], $location['day_id'], $createdOn['created_on']);
			$saveDataPost['round_id'] = @$saveDataLog['contents']['round']['id'];
		} else { // create
			$saveDataLog['contents']['round'] = $this->lrep()->instEventModel('_src_event_post')->getCurrentRound($location['event_id'], $location['day_id']);
			$saveDataPost['round_id'] = @$saveDataLog['contents']['round']['id'];
		}
		$this->helperSaveManagedSerializeContents($saveDataLog['contents']);

		if ($entry['id'] != NULL) { // update
			$this->db->update($saveDataPost, $this->table('tPosts'), array(
				'id' => $entry['id']
			));
			if ($this->db->affected_rows()) {
				$this->lrep()->altLog($location['tournament_id'], $location['event_id'], $location['day_id'], 'update', 'sub_posts', $entry['id']);
			}
			$this->db->update($saveDataLog, $this->table('Log'), array(
				'id' => $entry['id'],
				'type' => 'post'
			));
			if ($this->db->affected_rows()) {
				$this->lrep()->altLog($location['tournament_id'], $location['event_id'], $location['day_id'], 'update', 'log', $entry['id']);
			}
		} else { // create
			$this->db->insert($saveDataPost, $this->table('tPosts'));
			if (!($entry['id'] = $saveDataLog['id'] = $this->db->insert_id())) {
				return ;
			}
			if ($this->db->affected_rows()) {
				$this->lrep()->altLog($location['tournament_id'], $location['event_id'], $location['day_id'], 'insert', 'sub_posts', $entry['id']);
			}
			$this->db->insert($saveDataLog, $this->table('Log'));
			if ($this->db->affected_rows()) {
				$this->lrep()->altLog($location['tournament_id'], $location['event_id'], $location['day_id'], 'insert', 'log', $entry['id']);
			}
		}

		if ($entry['id']) {
			$rtf->assignObjects($entry['id']);
		}

		$this->helperSaveDbUpdateTags($tags, $entry['id'], 'post', $saveDataLog['is_hidden'], $location);
		$this->helperUpdateNotifyCTags(
			$entry['id'], 'post', 
			$saveDataLog['is_hidden'], $tags, 
			isset($saveDataLog['created_on'])
				? $saveDataLog['created_on'] : NULL
		);

		$this->helperSaveNotifyEvent($saveDataLog['is_hidden'], $location);

		return $entry['id'];
	}

	private function delete($postId)
	{
		if (NULL == ($prereq = $this->helperDeleteCheckPrerequisites($postId, 'post'))) {
			return FALSE;
		}
		list (
			$location
		) = $prereq;
		
		$deletedRows = $this->helperDeleteDbDelete($postId, 'post', 'tPosts', TRUE);

		if ($deletedRows[0]) {
			$this->lrep()->altLog($location['tournament_id'], $location['event_id'], $location['day_id'], 'delete', 'log', $postId);
		}
		if ($deletedRows[1]) {
			$this->lrep()->altLog($location['tournament_id'], $location['event_id'], $location['day_id'], 'delete', 'sub_posts', $postId);
		}
		
		$this->helperDeleteNotifyEvent($location);
		$this->helperUpdateNotifyCTags($postId, 'post');
	}
	
	private function getEditableData($id, $eventId)
	{
		$entry = $this->db->single_query_assoc('
			SELECT l.tournament_id, l.event_id, l.day_id, l.created_on, l.updated_on, l.is_hidden, l.sync_id IS NOT NULL synced, d.*
			FROM ' . $this->table('Log') . ' l
			INNER JOIN ' . $this->table('tPosts') . ' d
				ON l.id=d.id
			WHERE l.id=' . filter_var($id, FILTER_VALIDATE_INT) . ' AND l.type="post"
				AND l.event_id=' . filter_var($eventId, FILTER_VALIDATE_INT));
		if (empty($entry)) {
			return NULL;
		}
		$entry['tags'] = array();
		$tags = $this->db->array_query_assoc('
			SELECT tag FROM ' . $this->table('Tags') . '
			WHERE id=' . filter_var($id, FILTER_VALIDATE_INT) . ' AND type="post"
		');
		foreach ($tags as $tag) {
			$entry['tags'][] = $tag['tag'];
		}
		return $entry;
	}
}