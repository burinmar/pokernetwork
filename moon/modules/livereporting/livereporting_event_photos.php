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
class livereporting_event_photos extends livereporting_event_pylon
{
	private $ipnHas4thImageTS = 1303293600;

	protected function synthEvent($event, $argv)
	{
		switch ($event) {
			case 'save-photos':
				$data = $this->helperEventGetData(array(
					'photos_id', 'day_id', 'title', 'body', 'published', 'datetime_options'
				));
				$form = $this->form();
				$form->names('description', 'tags', 'src', 'misc');
				$form->fill($_POST);
				$imgData = $form->get_values();
				$xPhotos = array();
				if (!is_array($imgData['src'])) {
					$imgData['src'] = array();
				}
				foreach ($imgData['src'] as $k => $v) {
					$xPhotos[] = array(
						'id' => $k,
						'src' => $v,
						'misc'  => isset($imgData['misc'][$k])        ? $imgData['misc'][$k]        : '',
						'title' => isset($imgData['description'][$k]) ? $imgData['description'][$k] : '',
						'tags'  => isset($imgData['tags'][$k])        ? $imgData['tags'][$k]        : ''
					);
				}
				$data['xphotos'] = $xPhotos;
				
				$photosId = $this->save($data);
				if (isset($_POST['ajax'])) {
					echo json_encode(array(
						'id' => $photosId
					));
					moon_close();
					exit;
				}
				$this->redirectAfterSave($photosId, 'photos');
				exit;
			case 'delete':
				if (FALSE === $this->delete($argv['uri']['argv'][2])) {
					moon::page()->page404();
				}
				$this->redirectAfterDelete($argv['event_id'], $this->requestArgv('day_id'));
				exit;
			case 'delete-single':
				if (FALSE === ($this->deleteSingle($argv['uri']['argv'][3]))) {
					moon::page()->page404();
				}
				$this->forget();
				moon::page()->back(TRUE);
				exit;
			default:
				moon::page()->page404();
		}
	}

	/**
	 * @todo getEditableData : lighten
	 */
	protected function render($data, $argv)
	{
		if ($argv['variation'] == 'logControl') {
			return $this->renderControl(array_merge($data, array(
				'title' => '',
				'contents' => '',
				'unhide' => (!empty($_GET['master']) && $_GET['master'] == 'xphotos')
			)));
		} elseif ($argv['variation'] == 'logTab') {
			return $this->renderLogTab($data);
		}
		
		$page = moon::page();
		$page->js('/js/pnslideshow.js');
		$tpl = $this->load_template();

		$rArgv = $this->helperRenderCommonArgv($data, $argv, $tpl);
		
		if ($argv['variation'] == 'logEntry') {
			$rArgv += array(
				'photos_large' => '',
				'photos_thumbnails' => '',
				'is_preview' => $data['contents']['cnt'] > 10
			);
			$ipnReadBase = $this->get_var('ipnReadBase');
			$nr = 1;
			foreach ($data['contents']['xphotos'] as $photo) {
				$pArgv = $this->helperRenderPhotoItem($data['created_on'], $ipnReadBase, $nr, $photo);
				$rArgv['photos_large'] .= $tpl->parse('logEntry:photos.large', $pArgv['large']);
				$rArgv['photos_thumbnails'] .= $tpl->parse('logEntry:photos.thumbnail', $pArgv['thumbnails']);
				$nr++;
			}
			return $tpl->parse('logEntry:photos', $rArgv);
		} elseif ($argv['variation'] == 'individual') {
			$rArgv += array(
				'photos_large' => '',
				'photos_thumbnails' => ''
			);
			if (NULL === ($entry = $this->getEditableData($data['id'], $data['event_id']))) {
				$entry['xphotos'] = array();
				if (time() - $data['created_on'] < 3600*24*30) { // whine 1 month
					moon::error('Reporting photos: damaged entry: ' . $data['id']);
				}
				if ($rArgv['show_controls']) {
					$rArgv['show_controls'] = false;
					$rArgv['title'] = '(adm: damaged entry)';
				}
			}
			$nr = 1;
			$ipnReadBase = $this->get_var('ipnReadBase');
			foreach ($entry['xphotos'] as $photo) {
				$pArgv = $this->helperRenderPhotoItem($data['created_on'], $ipnReadBase, $nr, $photo);
				$rArgv['photos_large'] .= $tpl->parse('entry:photos.large', $pArgv['large']);
				$rArgv['photos_thumbnails'] .= $tpl->parse('entry:photos.thumbnail', $pArgv['thumbnails']);
				$nr++;
			}
			if ($rArgv['show_controls']) {
				$rArgv['control'] = $this->renderControl(array(
					'tournament_id' => $entry['tournament_id'],
					'event_id' => $entry['event_id'],
					'keep_old_dt' => true,
					'day_id'  => $entry['day_id'],
					'photos_id' => $entry['id'],
					'created_on' => $entry['created_on'],
					'tzName' => $data['tzName'],
					'tzOffset' => $data['tzOffset'],
					'title' => $entry['title'],
					'contents' => $entry['contents'],
					'published' => empty($entry['is_hidden']),
					'unhide' => ($argv['action'] == 'edit'),
					'bundled_control' => ($argv['action'] != 'edit'),
					'xphotos' => $entry['xphotos']
				));
			}
			$page->title($page->title() . ' | ' . $rArgv['title']);
			$this->helperRenderOGMeta($rArgv, $data);
			return $tpl->parse('entry:photos', $rArgv);
		}
	}
	
	private function helperRenderPhotoItem($createdOn, $ipnReadBase, $nr, $photo)
	{
		$photo['src_big'] = $photo['src'];
		if ($createdOn > $this->ipnHas4thImageTS) { // 4-th ipn format
			$photo['src_big'] = str_replace('/s', '/ms', $photo['src_big']);
		} else {
			$photo['src_big'] = str_replace('/s', '/m', $photo['src_big']);
		}
		return array(
			'large' => array(
				'nr' => $nr,
				'alt' => htmlspecialchars($photo['title']),
				'src_big' =>  $ipnReadBase . $photo['src_big'],
			),
			'thumbnails' => array(
				'nr' => $nr,
				'src' =>  $ipnReadBase . $photo['src'],
			)
		);
	}

	private function renderControl($argv)
	{
		if (empty($argv['day_id'])) {
			return ;
		}
		$tpl = $this->load_template();
		$lrep = $this->lrep();
		$ipnReadBase = $this->get_var('ipnReadBase');
		$rtf = $this->object('rtf');
		$rtf->setInstance($this->get_var('rtf') . ':2');
		$controlsArgv = array(
			'cx.save_event' => $this->parent->my('fullname') . '#save-photos',
			'cx.unhide'     => !empty($argv['unhide']),
			'cx.bundled_control' => !empty($argv['bundled_control']),
			'cx.id' => isset($argv['photos_id'])
				? intval($argv['photos_id'])
				: '',
			'cx.title' => htmlspecialchars($argv['title']),
			'cx.published' => !empty($argv['published']),
			'cx.day_id' => $argv['day_id'],
			'cx.body' => htmlspecialchars($argv['contents']),
			'cx.url.ipn' => $ipnReadBase,
			'cx.url.ipnpreview' => $this->linkas('event#ipn-browse',
				array(
					'event_id' => getInteger($argv['event_id']),
					'path' => $this->getUriPath(),
				),
				array('x' => 'photos')
			),
			'cx.js.xphoto_item' => json_encode($tpl->parse('controls:photos.item', array(
				'title' => '',
				'tags' => '',
				'psrc' => '',
				'misc' => ''
			))),
			'cx.xphotos_preview' => '',
			'cx.toolbar' =>  $rtf->toolbar('rq-wx-body', 
				isset($argv['photos_id']) ? intval($argv['photos_id']) : '', 
				array('noarticle'=>true))
		);
		
		list(
			$controlsArgv['cx.datetime_options'],
			$controlsArgv['cx.custom_datetime'],
			$controlsArgv['cx.custom_tz']
		) = $this->helperRenderControlDatetime($argv, $lrep);
		
		if (isset($argv['xphotos'])) {
			foreach ($argv['xphotos'] as $xPhoto) {
				$controlsArgv['cx.xphotos_preview'] .= $tpl->parse('controls:photos.item', array(
					'title' => htmlspecialchars($xPhoto['title']),
					'tags'  => htmlspecialchars(implode(', ', $xPhoto['tags'])),
					'psrc'  => $xPhoto['src'],
					'src'   => $ipnReadBase . $xPhoto['src'],
					'id'    => $xPhoto['remote_id'],
					'misc'  => implode(',', $xPhoto['remote_misc'])
				));
			}
		}
		
		return $tpl->parse('controls:photos', $controlsArgv);
	}

	private function renderLogTab($data)
	{
		$page = moon::page();
		$page->css('/css/jquery/lightbox-0.5.css');
		$page->js('/js/jquery/lightbox-0.5.js');
		$lrep = $this->lrep();
		$tpl = $this->load_template();
		
		$paginator  = moon::shared('paginate');
		$paginator->set_curent_all_limit(
			isset($_GET['page'])
				? getInteger($_GET['page'])
				: 1,
			$this->getPhotosCount($data['event_id'], 0), 120 // 0 => $data['day_id']
		);
		$paginator->set_url(
			$this->linkas('event#view', array(
					'event_id' => $data['event_id'],
					'path' => $this->getUriPath(),
					'leaf' => $this->getUriTab()
				), $this->getUriFilter(array('page'=>'{pg}'))
			),
			$this->linkas('event#view', array(
					'event_id' => $data['event_id'],
					'path' => $this->getUriPath(),
					'leaf' => $this->getUriTab()
				), $this->getUriFilter()
			)
		);
		$paginatorInfo = $paginator->get_info();
		$mainArgv['paginator'] = $paginator->show_nav();

		$srcUrlShow = $page->get_global('adminView') && $lrep->instTools()->isAllowed('writeContent');

		$photos = $this->getPhotos($data['event_id'], 0 /* day id */, $paginatorInfo['sqllimit']);
		$eventInfo = $lrep->instEventModel('_src_event')->getEventData($this->requestArgv('event_id'));
		$ipnReadBase = $this->get_var('ipnReadBase');
		$mainArgv['entries'] = '';
		foreach ($photos as $photo) {
			$photo['src_big'] = $photo['image_src'];
			$photo['src_big'][strlen($photo['src_big']) - 15] = 'm';
			$mainArgv['entries'] .= $tpl->parse('logTab:entries.item', array(
				'src' => $ipnReadBase . $photo['image_src'],
				'alt' => empty($photo['image_alt'])
					? '&nbsp;'
					: htmlspecialchars($photo['image_alt']),
				'src_big' =>  $this->get_var('ipnReadBase') . $photo['src_big'],
				'event_name' => htmlspecialchars($eventInfo['ename']),
				'tournament_name' => htmlspecialchars($eventInfo['tname']),
				'srcurl' => $srcUrlShow && $photo['iid']
					? $this->linkas('event#edit', array(
						'event_id' => $data['event_id'],
						'path' => $this->getUriPath(),
						'type' => 'photos',
						'id' => $photo['iid']
						), $this->getUriFilter(NULL, true))
					: '',
				'delurl' => $srcUrlShow && !$photo['iid']
					? $this->linkas('event#delete', array(
						'event_id' => $data['event_id'],
						'path' => $this->getUriPath(),
						'type' => 'photos',
						'id' => 'single.' . $photo['id']
						), $this->getUriFilter(NULL, true))
					: '',
			));
		}

		return $tpl->parse('logTab:main', $mainArgv);
	}

	private function getPhotosCount($eventId, $dayId)
	{
		$where = array();
		if (!empty($dayId)) {
			$where[] = 'day_id=' . getInteger($dayId);
		} else {
			$where[] = 'event_id=' . getInteger($eventId);
		}
		$where[] = 'is_hidden=0';
		$count = $this->db->single_query_assoc('
			SELECT COUNT(id) cid FROM ' . $this->table('Photos') . '
			WHERE ' . implode(' AND ', $where)
		);
		return $count['cid'];
	}

	private function getPhotos($eventId, $dayId, $limit) 
	{
		$where = array();
		if (!empty($dayId)) {
			$where[] = 'day_id=' . getInteger($dayId);
		} else {
			$where[] = 'event_id=' . getInteger($eventId);
		}
		$where[] = 'is_hidden=0';
		return $this->db->array_query_assoc('
			SELECT id, image_src, image_alt, import_id iid FROM ' . $this->table('Photos') . '
			WHERE ' . implode(' AND ', $where) . '
			ORDER BY created_on DESC, id DESC ' .
			$limit
		);
	}
	
	// used from _bluff.exGalleryPub()
	public function getPhotosSrcBluff($eventId, $dayId, $limit)
	{
		return $this->getPhotos($eventId, $dayId, $limit);
	}

	private function save($data)
	{
		if (NULL == ($prereq = $this->helperSaveCheckPrerequisites($data['day_id'], $data['photos_id'], 'photos', array('created_on')))) {
			return FALSE;
		}
		list (
			$location,
			$entry
		) = $prereq;

		if ($entry['id'] != NULL) {
			if ($data['datetime'] === NULL) { 
				// will set `created_on` for updated row, but the value should remain
				// needed for import images timestamp
				$data['datetime'] = $entry['created_on'];
			}
			$oldPhotos = array_keys($this->db->array_query_assoc('
				SELECT id FROM ' . $this->table('Photos') . '
				WHERE import_id="' . $entry['id'] . '"
			', 'id'));
		}

		$xPhotos = array();
		foreach ($data['xphotos'] as $k => $xPhoto) {
			$cTags = explode(',', $xPhoto['tags']);
			$tags = array();
			foreach ($cTags as $k => $tag) {
				$tag = trim($tag);
				if ($tag != '') {
					$tags[] = $tag;
				}
			}
			$tags = array_unique($tags);
			$xPhotos[$xPhoto['id']] = array(
				'id' => $xPhoto['id'],
				'src' => $xPhoto['src'],
				'misc' => $xPhoto['misc'],
				'tags' => $tags,
				'title' => $xPhoto['title']
			);
		}
		$xTopPhotos = array_slice($xPhotos, 0, 24); 

		$rtf = $this->object('rtf');
		$rtf->setInstance($this->get_var('rtf') . ':2');
		list(,$data['body_compiled']) = $rtf->parseText($entry['id'], $data['body']);

		$saveDataPhotos = array(
			'title' => $data['title'],
			'contents' => $data['body'],
		);
		$saveDataLog = array(
			'type' => 'photos',
			'is_hidden' => $data['published'] != '1',
			'contents' => serialize(array(
				'cnt' => count($xPhotos),
				'title' => $data['title'],
				'contents' => $data['body_compiled'],
				'xphotos' => $xTopPhotos
			))
		);

		$userId = intval(moon::user()->get_user_id());
		$this->helperSaveAssignCommonLogAttrs($saveDataLog, $userId, $entry, $data, $location);

		if ($entry['id'] != NULL) {
			$this->db->update($saveDataPhotos, $this->table('tPhotos'), array(
				'id' => $entry['id']
			));
			if ($this->db->affected_rows()) {
				$this->lrep()->altLog($location['tournament_id'], $location['event_id'], $location['day_id'], 'update', 'sub_photos', $entry['id']);
			}
			$this->db->update($saveDataLog, $this->table('Log'), array(
				'id' => $entry['id'],
				'type' => 'photos'
			));
			if ($this->db->affected_rows()) {
				$this->lrep()->altLog($location['tournament_id'], $location['event_id'], $location['day_id'], 'update', 'log', $entry['id']);
			}
		} else {
			$this->db->insert($saveDataPhotos, $this->table('tPhotos'));
			if (!($entry['id'] = $saveDataLog['id'] = $this->db->insert_id())) {
				return;
			}
			if ($this->db->affected_rows()) {
				$this->lrep()->altLog($location['tournament_id'], $location['event_id'], $location['day_id'], 'insert', 'sub_photos', $entry['id']);
			}
			$this->db->insert($saveDataLog, $this->table('Log'));
			if ($this->db->affected_rows()) {
				$this->lrep()->altLog($location['tournament_id'], $location['event_id'], $location['day_id'], 'insert', 'log', $entry['id']);
			}
		}

		$this->db->query('
			DELETE FROM ' . $this->table('Photos') . '
			WHERE import_id=' . $entry['id'] . '
		');

		if (isset($oldPhotos) && count($oldPhotos) > 0) {
			$this->db->query('
				DELETE FROM ' . $this->table('Tags') . '
				WHERE id IN (' . implode(',', $oldPhotos) . ') AND type="photo"
			');
		}

		foreach ($xPhotos as $xPhoto) {
			$autoSynced = $this->db->single_query_assoc('
				SELECT id FROM ' . $this->table('Photos') . '
				WHERE event_id=' . $location['event_id'] . '
					AND image_src="' . $this->db->escape($xPhoto['src']) . '"
					AND import_id IS NULL
				LIMIT 1
			');
			if (isset($autoSynced['id'])) {
				// if image auto-synced, and then used in post, delete "auto" to have no dupe
				$this->db->query('
					DELETE FROM ' . $this->table('Photos') . '
					WHERE id=' . $autoSynced['id'] . '
				');
				$this->db->query('
					DELETE FROM ' . $this->table('Tags') . '
					WHERE id=' . $autoSynced['id'] . ' AND type="photo"
				');
			}

			$this->db->insert(array(
				'import_id' => $entry['id'],
				'day_id' => $location['day_id'],
				'event_id' => $location['event_id'],
				'image_misc' => implode(',', array($xPhoto['id'], $xPhoto['misc'])),
				'image_src' => $xPhoto['src'],
				'image_alt' => $xPhoto['title'],
				'created_on' => $saveDataLog['created_on'],
				'is_hidden' => $saveDataLog['is_hidden']
				//'updated_on' => $saveDataLog['updated_on']
			), $this->table('Photos'));
			$localPhotoId = $this->db->insert_id();
			foreach ($xPhoto['tags'] as $tag) {
				$this->db->insert(array(
					'id' => $localPhotoId,
					'type' => 'photo',
					'tournament_id' => $location['tournament_id'],
					'event_id' => $location['event_id'],
					'day_id' => $location['day_id'],
					'tag' => $tag,
					'is_hidden' => $saveDataLog['is_hidden']
				), $this->table('Tags'));
			}
		}

		$this->helperSaveNotifyEvent($saveDataLog['is_hidden'], $location);
		
		return $entry['id'];
	}

	private function deleteSingle($imageId)
	{
		if (!$this->lrep()->instTools()->isAllowed('writeContent')) {
			return FALSE;
		}

		$this->db->query('
			UPDATE ' . $this->table('Photos') . '
			SET is_hidden=1
			WHERE id="' . getInteger($imageId) . '" AND import_id IS NULL
		');
		$this->db->query('
			DELETE FROM ' . $this->table('Tags') . '
			WHERE id=' . getInteger($imageId) . ' AND type="photo"
		');
	}

	private function delete($photosId)
	{
		if (NULL == ($prereq = $this->helperDeleteCheckPrerequisites($photosId, 'photos'))) {
			return FALSE;
		}
		list (
			$location
		) = $prereq;
		
		$deletedRows = $this->helperDeleteDbDelete($photosId, 'photos', 'tPhotos');
		$affectedPhotos = array_keys($this->db->array_query_assoc('
			SELECT id FROM ' . $this->table('Photos') . '
			WHERE import_id="' . getInteger($photosId) . '"
		', 'id'));
		$this->db->query('
			DELETE FROM ' . $this->table('Photos') . '
			WHERE import_id=' . getInteger($photosId) . '
		');
		if (count($affectedPhotos) > 0) {
			$this->db->query('
				DELETE FROM ' . $this->table('Tags') . '
				WHERE id IN (' . implode(',', $affectedPhotos) . ') AND type="photo"
			');
		}

		if ($deletedRows[0]) {
			$this->lrep()->altLog($location['tournament_id'], $location['event_id'], $location['day_id'], 'delete', 'log', $photosId);
		}
		if ($deletedRows[1]) {
			$this->lrep()->altLog($location['tournament_id'], $location['event_id'], $location['day_id'], 'delete', 'sub_photos', $photosId);
		}

		$this->helperDeleteNotifyEvent($location);
	}
	
	private function getEditableData($id, $eventId)
	{
		$entry = $this->db->single_query_assoc('
			SELECT l.tournament_id, l.event_id, l.day_id, l.created_on, l.updated_on, l.is_hidden, x.*
			FROM ' . $this->table('Log') . ' l
			INNER JOIN ' . $this->table('tPhotos') . ' x
			ON l.id=x.id
			WHERE l.id=' . getInteger($id) . ' AND l.type="photos"
				AND l.event_id=' . getInteger($eventId));
		if (empty($entry)) {
			return NULL;
		}
		$entry['xphotos'] = array();
		$rPhotos = $this->db->array_query_assoc('
			SELECT id, image_misc, image_src, image_alt FROM ' . $this->table('Photos') . '
			WHERE import_id="' . getInteger($id) . '"
			ORDER BY id
		');
		foreach ($rPhotos as $rPhoto) {
			$pId = explode(',',$rPhoto['image_misc']);
			$remoteId = $pId[0];
			array_shift($pId);
			$entry['xphotos'][$rPhoto['id']] = array(
				'remote_id' => $remoteId,
				'remote_misc' => $pId,
				'id' => $rPhoto['id'],
				'src'   => $rPhoto['image_src'],
				'title' => $rPhoto['image_alt'],
				'tags' => array()
			);
		}
		$rTags = array();
		if (count($entry['xphotos']) > 0) {
			$rTags = $this->db->array_query_assoc('
				SELECT id, tag FROM ' . $this->table('Tags') . '
				WHERE id IN (' . implode(',', array_keys($entry['xphotos'])) . ') AND type="photo"
			');
		}
		foreach ($rTags as $rTag) {
			$entry['xphotos'][$rTag['id']]['tags'][] = $rTag['tag'];
		}
		return $entry;
	}
}