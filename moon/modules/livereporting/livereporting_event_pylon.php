<?php
/**
 * @package livereporting
 */
/**
 */
require_once 'livereporting_event.php';
/**
 * @package livereporting
 */
class livereporting_event_pylon extends livereporting_event
{
	/**
	 * @var livereporting_event 
	 */
	protected $parent = NULL;

	function __construct($a, $b, $c)
	{
		parent::__construct($a, $b, $c);
		$this->parent = $this->object('livereporting_event');
	}
	
	/**
	 * override livereporting_event -- default empty method
	 */
	function main() {}
	/**
	 * override livereporting_event -- default empty method
	 */
	function events() {}
	/**
	 * override livereporting_event -- default empty method
	 */
	function onload() {}
	/**
	 * Renders passed data
	 * @param array $data Data (usually from db)
	 * @param array $argv Rendering hints
	 * @return string Output
	 */
	protected function render($data, $argv) {}
	/**
	 * Method to process events
	 * 
	 * May 404(), exit, redirect.
	 * @param string $event 
	 * @param array $argv 
	 * @return mixed
	 */
	protected function synthEvent($event, $argv) {}

	/**
	 * Redirect helper function, which accepts reporting uri schema
	 * @param string $event 
	 * @param array $argv 
	 * @param array $filter 
	 */
	protected function redirect_($event, $argv = array(), $filter = array()) 
	{
		moon::page()->redirect(htmlspecialchars_decode($this->lrep()->makeUri($event, $argv, $filter)));
	}
	
	/**
	 * Redirect to log entry after save
	 * @param int $entryId 
	 * @param string $entryType 
	 * @param array $getParamsAdditional 
	 */
	protected function redirectAfterSave($entryId, $entryType, $getParamsAdditional = array())
	{
		$this->redirectToLogEntry(array(
			'id' => $entryId,
			'type' => $entryType
		), $getParamsAdditional);
	}
	
	/**
	 * Redirect to log after entry delete
	 *
	 * executes forget() method
	 * @param int $eventId 
	 * @param mixed $dayId 
	 * @param mixed $filterAdd 
	 */
	protected function redirectAfterDelete($eventId, $dayId, $filterAdd = NULL)
	{
		$this->forget();
		
		// $this->redirect does not work for the same reason as $this->linkas()
		$this->redirect_('event#view', array(
			'event_id' => getInteger($eventId),
			'path' => $this->getUriPath($dayId)
		), $this->getUriFilter($filterAdd, TRUE));
	}
	
	/**
	 * Macro to get $fields keys from $_POST
	 *
	 * Additionally parses common livereporting date form fields into timestamp/null
	 * @param array $fields Array of string keys
	 * @return array
	 */
	protected function helperEventGetData($fields)
	{
		$form = $this->form();
		$form->names($fields);
		$form->fill($_POST);
		$data = $form->get_values();

		if (isset($data['datetime_options'])) {
			if ($data['datetime_options'] == 'sct_dt') {
				$form->names = array();
				$form->names('year', 'month', 'day', 'hour_minute', 'second', 'timeshift');
				$form->fill($_POST);
				$data['datetime'] = $this->lrep()->instTools()
					->helperCustomDatetimeRead($form->get_values());
			} elseif ($data['datetime_options'] == 'now_dt') {
				$data['datetime'] = time();
			} else {
				$data['datetime'] = NULL;
			}
			unset($data['datetime_options']);
		}
		
		return $data;
	}
	
	/**
	 * Macro to get entry-attached image fields from _POST
	 * @param array &$data Array to append image data to
	 */
	protected function helperEventGetLeadingImage(&$data)
	{
		$form = $this->form();
		$form->names('ipnimageid', 'ipnimagemisc', 'ipnimagesrc', 'ipnimagetitle');
		$form->fill($_POST);
		$imgData = $form->get_values();
		if (!empty($imgData['ipnimageid']) && !empty($imgData['ipnimagesrc'])) {
			$data['image'] = array(
				'id'  => $imgData['ipnimageid'],
				'misc'  => $imgData['ipnimagemisc'],
				'src' => $imgData['ipnimagesrc'],
				'title' =>  $imgData['ipnimagetitle']
			);
		}
	}
	
	/**
	 * Macro to render common livereporting date form fields
	 * @param array $argv Combo of entry data and event tz data
	 * @param livereporting $lrep 
	 * @return array
	 */
	protected function helperRenderControlDatetime($argv, $lrep)
	{
		$timeOptions = array(
			'old_dt'=>'Leave as is',
			'now_dt'=>'Now',
			'sct_dt'=>'Set custom'
		);
		if (!isset($argv['keep_old_dt'])) {
			unset($timeOptions['old_dt']);
		}
		$controlsArgv['datetime_options'] = '';
		foreach ($timeOptions as $k => $v) {
			$controlsArgv['datetime_options'] .= '<option value="' . $k . '">' . $v . '</option>';
		}
		
		$controlsArgv['custom_datetime'] = $lrep->instTools()
			->helperCustomDatetimeWrite('+Y #m +d +H:M -S -z', (
				isset($argv['created_on']) 
					? intval($argv['created_on']) 
					: time()) + $argv['tzOffset'], 
				$argv['tzOffset']);
		$controlsArgv['custom_tz'] = $argv['tzName'];
		
		return array(
			$controlsArgv['datetime_options'],
			$controlsArgv['custom_datetime'],
			$controlsArgv['custom_tz']
		);
	}
	
	/**
	 * Macro to render entry image attachement snippet
	 * @param array $argv Entry data
	 * @param string $xApp Key to hint imgsrv behavior (search examples)
	 * @return array
	 */
	protected function helperRenderIpn($argv, $xApp)
	{
		$controlsArgv['url.ipn'] = $this->get_var('ipnReadBase');
		$controlsArgv['url.ipnpreview'] = 
			$this->lrep()->makeUri('event#ipn-browse',
				array(
					'event_id' => getInteger($argv['event_id']),
					'path' => $this->getUriPath(),
				),
				array('x' => $xApp)
			);
		$controlsArgv['url.ipnupload'] =
			$this->lrep()->makeUri('event#ipn-upload',
				array(
					'event_id' => getInteger($argv['event_id']),
					'path' => $this->getUriPath(),
				),
				array('x' => $xApp)
			);

		if (!empty($argv['image_src'])) {
			$argv['image_misc'] = explode(',', $argv['image_misc']);
			$ipnImageId = $argv['image_misc'][0];
			array_shift($argv['image_misc']);
			$controlsArgv += array(
				'ipnimageid' => $ipnImageId,
				'ipnimagemisc'  => implode(',', $argv['image_misc']),
				'ipnimagetitle' => htmlspecialchars($argv['image_alt']),
				'ipnimagesrc'   => htmlspecialchars($argv['image_src'])
			);
		}
		
		return array(
			$controlsArgv['url.ipn'],
			$controlsArgv['url.ipnpreview'],
			$controlsArgv['url.ipnupload'],
			isset($controlsArgv['ipnimageid'])
				? $controlsArgv['ipnimageid'] : NULL,
			isset($controlsArgv['ipnimagemisc'])
				? $controlsArgv['ipnimagemisc'] : NULL,
			isset($controlsArgv['ipnimagetitle'])
				? $controlsArgv['ipnimagetitle'] : NULL,
			isset($controlsArgv['ipnimagesrc'])
				? $controlsArgv['ipnimagesrc'] : NULL,
		);
	}
	
	/**
	 * Macro to populate entry template arguments array with most commons attributes. 
	 *
	 * Essentially is spaghetti, which includes anything common in entry rendering.
	 * May 404().
	 * @param array &$data Entry data (may be modified in process)
	 * @param array $argv Rendering hints array
	 * @param mixed $tpl moon_com_template
	 * @return array 
	 */
	protected function helperRenderCommonArgv(&$data, $argv, $tpl = NULL)
	{
		static $locale, $text, $usersUrl, $ipnReadBase, $page, $allowWrite, $tools;
		if (!$locale) {
			$locale = moon::locale();
			$text   = moon::shared('text');
			$page   = moon::page();
			$tools  = moon::shared('tools');
			$usersUrl = moon::shared('sitemap')->getLink('users');
			$ipnReadBase = $this->get_var('ipnReadBase');
			$allowWrite = $page->get_global('adminView') && $this->lrep()->instTools()->isAllowed('writeContent');
		}
		
		$createdOn = $text->ago($data['created_on']);
		if ($createdOn == '' || $data['created_on'] > time()) {
			$createdOn = $locale->gmdatef($data['created_on'] + $data['tzOffset'], 'Reporting') . ' ' . $data['tzName'];
		}
		
		$data['contents'] = unserialize($data['contents']);
		$rArgv = array(
			'id' => $data['id'],
			'title' => isset($data['contents']['title'])
				? htmlspecialchars($data['contents']['title'])
				: NULL,
			'is_hidden' => $data['is_hidden'],
			'created_on' => $createdOn,
			'author_name' => htmlspecialchars($data['author_name']),
			'author_url'  => $usersUrl . rawurlencode($data['author_name']) . '/',
			'url.view' => $this->lrep()->makeUri('event#view', array(
					'event_id' => $data['event_id'],
					'path' => $this->getUriPath(),
					'type' => $data['type'],
					'id' => $data['id']
				), $this->getUriFilter(NULL))
		);
		
		if (isset($data['contents']['contents'])) {
			$rArgv['body'] = $data['contents']['contents'];
		}

		if (in_array($data['type'], array('post', 'chips', 'photos'))) {
			$rArgv['sociallinks'] = $tools->toolbar(
				array(
					'variant' => 'reporting',
					'title' => $rArgv['title'],
					'firstuse' => parent::$socialLinksNotInitialized,
				) +	($argv['variation'] == 'logEntry'
					? array('url' => $rArgv['url.view'])
					: array())
			);
			parent::$socialLinksNotInitialized = false;
		}

		if (!empty($data['contents']['i_src'])) {
			//$rArgv['src'][strlen($rArgv['src']) - 15] = 'b';
			$data['contents']['i_misc'] = is_array($data['contents']['i_misc']) // @ should not be an array, fixing temporarily
				? array($data['contents']['i_misc'], NULL)
				: explode(',', $data['contents']['i_misc']);
			$rArgv += array(
				'image' => true,
				'image_src' => $ipnReadBase . $data['contents']['i_src'],
				'image_width' => isset($data['contents']['i_misc'][1])
					? $data['contents']['i_misc'][1]
					: 250,
				'image_description' => htmlspecialchars($data['contents']['i_alt']),
			);
		}
		if (!empty($data['contents']['tags'])) {
			$rArgv['tags'] = '';
			$lastTag = count($data['contents']['tags']) - 1;
			$tagsObj = $this->object('other.ctags')->getReportingHandle();
			foreach ($data['contents']['tags'] as $k => $tag) {
				$rArgv['tags'] .= $tpl->parse('entry:tag', array(
					'tag' => htmlspecialchars($tag),
					'url' => $tagsObj->getUrl($tag),
					'comma' => $k == $lastTag
						? ''
						: ', '
				));
			}
		}
		if (!empty($data['contents']['round']) && is_array($data['contents']['round'])) {
			$round = $data['contents']['round'];
			$rArgv['round_id'] = $round['id'];
			$rArgv['round_nr'] = $round['round'];
			if (!empty($round['big_blind']) && !empty($round['small_blind']) && !empty($round['ante'])) {
				$rArgv['round_details'] = $round['id'];
				$rArgv['round_big_blind'] = number_format($round['big_blind']);
				$rArgv['round_small_blind'] = number_format($round['small_blind']);
				$rArgv['round_ante'] = number_format($round['ante']);
			}
		}
		
		if ($argv['variation'] == 'logEntry') {
			if ($allowWrite) {
				$rArgv += array(
					'show_controls' => true,
					'url.edit'   => $this->lrep()->makeUri('event#edit', array(
							'event_id' => $data['event_id'],
							'path' => $this->getUriPath(),
							'type' => $data['type'],
							'id' => $data['id']
						), $this->getUriFilter(NULL, true)),
					'url.delete'   => $this->lrep()->makeUri('event#delete', array(
							'event_id' => $data['event_id'],
							'path' => $this->getUriPath(),
							'type' => $data['type'],
							'id' => $data['id']
						), $this->getUriFilter(NULL, true))
				);
			}
		} elseif ($argv['variation'] == 'individual') {
			if ($argv['action'] == 'edit') {
				if (!$allowWrite) {
					$page->page404();
				}
			}
			$rArgv += array(
				'url.up' => $this->lrep()->makeUri('event#view', array(
					'event_id' => $data['event_id'],
					'path' => $this->getUriPath($this->requestArgv('day_id')),
				), $this->getUriFilter(NULL, TRUE)),
				'show_controls' => $allowWrite,
				'control' => ''
			);
		}
		
		return $rArgv;
	}

	protected function helperRenderOGMeta($rArgv, $data = array())
	{
		$page = moon::page();
		$page->fbMeta['og:title'] = $rArgv['title'];
		$page->fbMeta['og:description'] = $this->lrep()->instTools()->helperHtmlExcerpt(
			strip_tags($rArgv['body']), 
			220, 1, '...', false, false);
		if (!empty($rArgv['image_src']))
			$page->fbMeta['og:image'] = $rArgv['image_src'];
		elseif (isset($data['contents']['xphotos'][0]))
			$page->fbMeta['og:image'] = $this->get_var('ipnReadBase') . $data['contents']['xphotos'][0]['src'];	
	}
	
	public function helperRenderCommonArgvMobileapp(&$data, $argv, $tpl)
	{
		return $this->helperRenderCommonArgv($data, $argv, $tpl);
	}

	/**
	 * Pre-save location check macro
	 * @param int $dayId 
	 * @return mixed Location data array on success, null on failure
	 */
	protected function helperSaveCheckPrerequisitesLocationByDay($dayId)
	{
		if (!$this->lrep()->instTools()->isAllowed('writeContent')) {
			return ;
		}

		$location = $this->db->single_query_assoc('
			SELECT tournament_id, event_id, id day_id, name day_name FROM ' . $this->table('Days') . '
			WHERE id=' . getInteger($dayId) . '
		');
		if (empty($location)) {
			return ;
		}
		
		return array(
			$location,
		);		
	}
	
	/**
	 * Pre-save location check macro
	 * @param int $eventId 
	 * @return mixed Location data array on success, null on failure
	 */
	protected function helperSaveCheckPrerequisitesLocationByEvent($eventId)
	{
		if (!$this->lrep()->instTools()->isAllowed('writeContent')) {
			return ;
		}

		$location = $this->db->single_query_assoc('
			SELECT tournament_id, id event_id FROM ' . $this->table('Events') . '
			WHERE id=' . getInteger($eventId) . '
		');
		if (empty($location)) {
			return ;
		}
		
		return array(
			$location,
		);		
	}
	
	/**
	 * Entry pre-save access rights check macro
	 * @param int $dayId 
	 * @param int $rowId 
	 * @param string $rowType 
	 * @param array $additionalTextFields Additional data row fields to fetch
	 * @return mixed On success - [0:location, 1:entry] array. Null on failure
	 */
	protected function helperSaveCheckPrerequisites($dayId, $rowId, $rowType, $additionalTextFields = array())
	{
		if (NULL == ($prereq = $this->helperSaveCheckPrerequisitesLocationByDay($dayId))) {
			return ;
		}
		list (
			$location
		) = $prereq;
		
		$entry = array(
			'id' => NULL,
			'is_hidden' => NULL,
		);
		foreach ($additionalTextFields as $field) {
			$entry[$field] = NULL;
		}
		if ($rowId != '') {
			$entry = $this->db->single_query_assoc('
				SELECT id, is_hidden' . (0 != count($additionalTextFields)
					? ',' . implode(',', $additionalTextFields)
					: ''
				) . 
				' FROM ' . $this->table('Log') . '
				WHERE id=' . getInteger($rowId) . ' AND type="' . addslashes($rowType) . '"
			');
			if (empty($entry)) {
				return ;
			}
			$entry['id'] = getInteger($entry['id']);
			$entry['is_hidden'] = getInteger($entry['is_hidden']);
		}
		
		return array(
			$location,
			$entry
		);
	}
	
	/**
	 * Macro to parse tags string to tags array
	 * @param string $tags 
	 * @return array
	 */
	protected function helperSaveGetTags($tags)
	{
		$cTags = explode(',', $tags);
		$tags = array();
		foreach ($cTags as $k => $tag) {
			$tag = trim($tag);
			if ($tag != '') {
				$tags[] = $tag;
			}
		}
		return array_unique($tags);
	}
	
	/**
	 * Macro to populate common `log` table row fields
	 * @param array &$saveDataLog Array to populate
	 * @param int $userId 
	 * @param array $entry Entry data, if available (`id` field required)
	 * @param array $data Data to use for population (probably from _POST)
	 * @param array $location Location data to use for population
	 */
	protected function helperSaveAssignCommonLogAttrs(&$saveDataLog, $userId, $entry, $data, $location)
	{
		if ($entry['id'] != NULL) { // update
			if (NULL != $data['datetime']) {
				$saveDataLog['created_on'] = $data['datetime'];
			}
			$saveDataLog['updated_on'] = time();
			
			// now publishing when was hidden
			if (!$saveDataLog['is_hidden'] && $entry['is_hidden']) {
				$saveDataLog['author_id'] = $userId;
			}
		} else { // create
			$saveDataLog += array(
				'tournament_id' => $location['tournament_id'],
				'event_id' => $location['event_id'],
				'day_id' => $location['day_id'],
				'author_id' => $userId,
				'updated_on' => NULL,
				'created_on' => (NULL == $data['datetime'])
					? time()
					: $data['datetime']
			);
		}
	}
	
	/**
	 * Macro to save entry tags to db (per-entry tags only)
	 * @param array $tags 
	 * @param int $entryId 
	 * @param string $entryType 
	 * @param bool $entryIsHidden 
	 * @param array $location 
	 */
	protected function helperSaveDbUpdateTags($tags, $entryId, $entryType, $entryIsHidden, $location)
	{
		$this->db->query('
			DELETE FROM ' . $this->table('Tags') . '
			WHERE id="' . $entryId . '" AND type="' . $entryType . '"
		');
		foreach ($tags as $tag) {
			$this->db->insert(array(
				'id' => $entryId,
				'type' => $entryType,
				'tournament_id' => $location['tournament_id'],
				'event_id' => $location['event_id'],
				'day_id' => $location['day_id'],
				'tag' => $tag,
				'is_hidden' => $entryIsHidden
			), $this->table('Tags'));
		}
	}
	
	/**
	 * Method to be called on entry save
	 *
	 * Currently, only entries that affect event visibility should be calling this
	 * @param bool $isHidden 
	 * @param array $location 
	 */
	protected function helperSaveNotifyEvent($isHidden, $location)
	{
		if ($isHidden == false) {
			$this->lrep()->instEventModel('_src_event')->updateStatesOnLogEntryAdded(
				$location['tournament_id'],
				$location['event_id'],
				$location['day_id']
			);
		} else {
			$this->lrep()->instEventModel('_src_event')->updateStatesOnLogEntryRemoved(
				$location['tournament_id'],
				$location['event_id'],
				$location['day_id']
			);
		}
	}
	
	/**
	 * Entry pre-delete access rights check
	 * @param int $rowId 
	 * @param string $rowType 
	 * @return mixed On success -- [0:location] array. Null on failure.
	 */
	protected function helperDeleteCheckPrerequisites($rowId, $rowType)
	{
		if (!$this->lrep()->instTools()->isAllowed('writeContent')) {
			return ;
		}

		$location = $this->db->single_query_assoc('
			SELECT tournament_id, event_id, day_id FROM ' . $this->table('Log') . '
			WHERE id=' . getInteger($rowId) . ' AND type="' . addslashes($rowType) . '"
		');
		if (empty($location)) {
			return ;
		}
		
		return array(
			$location,
		);
	}
	
	/**
	 * Macro to delete common log entry from db
	 *
	 * AC may be done using helperDeleteCheckPrerequisites()
	 * @param int $rowId 
	 * @param string $rowType 
	 * @param string $rowTable 
	 * @param bool $withTags 
	 */
	protected function helperDeleteDbDelete($rowId, $rowType, $rowTable = NULL, $withTags = FALSE)
	{
		$deletedRows = array();

		$this->db->query('
			DELETE FROM ' . $this->table('Log') . '
			WHERE id=' . getInteger($rowId) . ' AND type="' . addslashes($rowType) . '"
		');
		$deletedRows[] = $this->db->affected_rows();

		if ($rowTable) {
			$this->db->query('
				DELETE FROM ' . $this->table($rowTable) . '
				WHERE id=' . getInteger($rowId) . '
			');
			$deletedRows[] = $this->db->affected_rows();
		}
		if ($withTags) {
			$this->db->query('
				DELETE FROM ' . $this->table('Tags') . '
				WHERE id=' . getInteger($rowId) . ' AND type="' . addslashes($rowType) . '"
			');
			$deletedRows[] = $this->db->affected_rows();
		}

		return $deletedRows;
	}
	
	/**
	 * Method to be called on entry deletion
	 *
	 * Currently, only entries that affect event visibility should be calling this
	 * @param array $location 
	 */
	protected function helperDeleteNotifyEvent($location)
	{
		$this->lrep()->instEventModel('_src_event')->updateStatesOnLogEntryRemoved(
			$location['tournament_id'],
			$location['event_id'],
			$location['day_id']
		);
	}

	protected function helperUpdateNotifyCTags($id, $type, $isHidden = true, $tags = array(), $createdOn = NULL)
	{
		if ($isHidden) {
			$tags = array();
		}
		$this->object('other.ctags')->getReportingHandle()
			->update($id, $type, $tags, $createdOn);
	}

	/**
	 * Quicker way to get livereporting object
	 * @return livereporting
	 */
	protected function lrep()
	{
		static $lrepObj;
		if (!$lrepObj) {
			$lrepObj = $this->object('livereporting');
		}
		return $lrepObj;
	}
}
