<?php
/**
 * @package livereporting
 */
/**
 * Event model, common part. Has a number of livereporting_event_* sub-components.
 *
 * Blogumai:
 * - Visur reikia tampyti dienos ir tipo filtrą. Jei jo sudarymo principas pasikeis, gali būti blogai
 * - self::$requestArgv ir kt. pasiekiami tik ateinant per livereporting#read_uri. Dėl to events bendroji dalis, redirectToLogEntry, save* metodai ir save-* eventai faktiškai yra priklausomi. (patrikrinti šitą punktą)
 * - Iš evento galima kreiptis į kitus eventus ir turnyrus, bet ne į jų dienas (bent jau ne automatiškai)
 * @package livereporting
 */
class livereporting_event extends moon_com
{
	private static $requestArgv = NULL;
	protected static $socialLinksNotInitialized = true;
	private $tabsSafeDisplay = array();
	private $subObjCache = array();
	private $logPageBy = 10;

	function events($event, $argv)
	{
		// _POST (start limited)
		if (isset($_POST['event'])) {
			switch ($event) {
			case 'suggest-tags':
				// suggest post/chips tags combining post contents and players database + (possibly) earlier event tags
				$this->eSuggestTags();
				break;
			default:
				$this->eSaves($event, $argv);
			}
			moon::page()->page404();
		} // end limited

		// parse url, set vars (properties())
		// tab is not set at the moment
		if ((self::$requestArgv = $this->getRequestArgv($argv)) === NULL) {
			moon::page()->page404();
		}
		foreach (self::$requestArgv as $rKey => $rArg) {
			$this->set_var($rKey, $rArg);
		}

		// shoutbox
		if (isset($argv['uri']['path'][0]) && $argv['uri']['path'][0] == 'shoutbox') {
			return $this->object('shoutbox')
				->events('', $argv);
		}

		// a) log entries insides: view (set vars for main);
		//	  edit, delete (syntEvent + redirect)
		//    ajax calls (echo + exit)
		// b) user profiles (set vars for main)
		if (isset($argv['uri']['argv'][2])) { // ~/view.post.{nr}.htm
			$this->eEntryPages($argv);
		} elseif (count($argv['uri']['argv']) == 2) { // ~/export.{context}.htm
			$this->eFreightPages($argv);
		} elseif (count($argv['uri']['argv']) == 1) { // ~/ipn-upload.htm, ~/chips.htm
			$this->eSpecialPages($argv); // sets requestArgv.tab
		} elseif (count($argv['uri']['argv']) == 0) { // ~/
			$this->set_var('tab', 'log');
			$this->set_var('render', 'log');
		}

		if ($this->lrep()->instTools()->isAllowed('writeContent')) {
			$page = moon::page();
			if ($page->get_global('adminView') && $page->get_global('lrepAdmSwitch') == '') {
				// re-switched to [A]
				$page->set_global('lrepAdmSwitch', 1);
				$page->set_global($this->my('fullname') . '_ipnSid', NULL);
			}
			if (!$page->get_global('adminView')) {
				$page->set_global('lrepAdmSwitch', '');
			}
		}

		$this->use_page('LiveReporting1col');
	}

	/**
	 * Event delegate: redirect standard save request
	 */
	private function eSaves($event, $argv)
	{
		if (in_array($event, array(
			    'save-post',
			    'save-tweet',
			    'save-photos',
			    'save-round',
			    'save-round-datetime',
			    'save-chips',
			    'save-event',
			    'save-misc',      // -> eventObj
			    'save-sbplayers', // -> eventObj
			    'save-profile',
			    'save-list-profiles', // -> profileObj
			    'save-day-datetime'))) {
			// recreating environment as much as needed
			// (mainly for redirectToLogEntry)
			// {
			if (!$this->lrep()->instTools()->isAllowed('writeContent')) {
				moon::page()->page404();
			}
			list (, $uri) = $this->lrep()->readUri();
			self::$requestArgv = $this->getRequestArgv($uri);
			// }
			switch ($event) {
				case 'save-misc':
				case 'save-sbplayers':
					$object = $this->instEventEvent();
					break;
				case 'save-list-profiles':
					$object = $this->instEventProfile();
					break;
				default:
					list (, $object) = explode('-', $event);
					$object = 'instEvent' . ucfirst($object);
					$object = $this->$object();
					break;
			}
			if (is_object($object)) {
				$object->synthEvent($event, $uri);
			}
			moon_close(); exit;
		}
	}

	private function eSuggestTags()
	{
		if (!$this->lrep()->instTools()->isAllowed('writeContent')) {
			moon::page()->page404();
		}
		$text = isset($_POST['text'])
			? $_POST['text']
			: '';
		if ($text != '') {
			$eventId = isset($_POST['event_id'])
				? intval($_POST['event_id'])
				: 0;
			$tags = $this->getRecommendedTags($text, $eventId);
			if (!empty($tags)) {
				$output = array();
				foreach ($tags as $t) {
					$output[] = '<a href="javascript:;">' . $t . '</a>';
				}
				echo '<span>Recommended tags:</span> ' . implode(', ', $output);
				moon_close(); exit;
			}
		}
		header('HTTP/1.0 404 Not Found');
		moon_close(); exit;
	}

	private function getRecommendedTags($text, $eventId = 0)
	{
		$candidates = array();
		if ($eventId != 0) {
			$eventTags = $this->db->query('
				SELECT tag FROM ' . $this->table('Tags') . '
				WHERE event_id=' . intval($eventId) . '
				GROUP BY tag
			');
			while ($row = $this->db->fetch_row_assoc($eventTags)) {
				$candidates[$row['tag']] = null;
			}
		}
		if (1) {
			$playersTags = $this->db->query('
				SELECT title FROM ' . $this->table('PlayersPoker') . '
				WHERE hidden=0
			');
			while ($row = $this->db->fetch_row_assoc($playersTags)) {
				$candidates[$row['title']] = null;
			}
		}
		$candidates = array_keys($candidates);

		$text = preg_replace('~\[.{2,3}\]~', '', $text);
		$text = str_replace(array('.', ',', '"', ':', ']', '[', '=', '+', '-', ')', '(', '*', '!', '?', '', "\n", "\r"), ' ', $text);
		// transliterate {
		$prevLocale = setlocale(LC_ALL, 0);
		setlocale(LC_ALL, 'en_US.UTF-8');
		$text =	iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $text);
		setlocale(LC_ALL, $prevLocale);
		// }
		$recommended = array();
		foreach ($candidates as $tag) {
			if (stripos($text, $tag) !== FALSE) {
				$recommended[] = $tag;
			} elseif(($wordsCnt = count($words = explode(' ', $tag))) > 1) {
				$lastName = array_pop($words);
				if (strpos($text, $lastName . ' ') !== FALSE) { // case sensitive, not part of the word
					$recommended[] = $tag;
				}
			}
		}

		sort($recommended);
		return $recommended;
	}

	private function eEntryPages($argv)
	{
		$this->set_var('action', $argv['uri']['argv'][0]);
		$this->set_var('hide_write_controls', 'true');

		if (in_array($argv['uri']['argv'][0], array('edit', 'view'))) { //std view/edit
		switch ($argv['uri']['argv'][1]) {
			case 'post':
			case 'tweet':
			case 'photos':
			case 'chips':
			case 'round':
			case 'day':
			case 'profile':
				$this->set_var('render', 'entry');
				$this->set_var('type', $argv['uri']['argv'][1]);
				$this->set_var('id', $argv['uri']['argv'][2]);
				return;
		}}

		if ($argv['uri']['argv'][0] == 'delete') { // std delete
		switch ($argv['uri']['argv'][1]) {
			case 'post':
			case 'tweet':
			case 'chips':
			case 'round':
			case 'photos': // single photo
				$object = 'instEvent' . ucfirst($argv['uri']['argv'][1]);
				$object = $this->$object();
				$object->synthEvent('delete' . (!is_numeric($argv['uri']['argv'][2]) ? '-' . $argv['uri']['argv'][2] : ''), $argv);
				exit;
		}}

		if ($argv['uri']['argv'][0] == 'save') { // std supplimentary saves (using _GET to attach to endpoint, but may have _POST too)
		switch ($argv['uri']['argv'][1]) {
			case 'chips':   //`chips tab` save single chip
			case 'profile': // delete single chip
			case 'day':     // day state
			case 'event':   // event state
			case 'round':   // round state
				$object = 'instEvent' . ucfirst($argv['uri']['argv'][1]);
				$object = $this->$object();
				$object->synthEvent('save-' . $argv['uri']['argv'][2], $argv);
				exit;
		}}

		// exceptions/etc.
		switch ($argv['uri']['argv'][1]) {
			case 'event':
			case 'profile':
			case 'round':
				switch ($argv['uri']['argv'][0]) {
					case 'load':
						$object = 'instEvent' . ucfirst($argv['uri']['argv'][1]);
						$object = $this->$object();
						$object->synthEvent('load-' . $argv['uri']['argv'][2], $argv);
						exit;
				}
				break;
		}

		moon::page()->page404();
	}

	private function eFreightPages($argv)
	{
		switch ($argv['uri']['argv'][0]) {
			case 'export':
				//$uAgent = empty($_SERVER['HTTP_USER_AGENT']) ? '' : strtolower($_SERVER['HTTP_USER_AGENT']);
				//if (is_dev() /*|| $user->get_ip() == '217.28.251.172'*/ || strpos($uAgent,'pokerapp') === 0) {
					$this->set_var('render', 'bluff-xml');
					$this->set_var('dist', $argv['uri']['type']);
					$this->set_var('key', $argv['uri']['argv'][1]);
				/*} else {
					moon::error('Bandymas pavogti reportingo duomenis', 'N');
				}*/
				break;
		}
	}

	private function eSpecialPages($argv)
	{
		switch ($argv['uri']['argv'][0]) {
			case 'ipn-upload':
				$this->redirectIPN('ipnUploadUrl');
				exit;
			case 'ipn-browse':
				$this->redirectIPN('ipnBrowseUrl');
				exit;
			case 'ipn-review':
				$this->redirectIPN('ipnReviewUrl');
				exit;
		}
		$tpl = $this->load_template();
		$tabs = $tpl->parse_array('log:tabs');
		foreach ($tabs as $tabId => $tab) {
			list ($urlKey) = explode('|', $tab);
			if ($argv['uri']['argv'][0] == trim($urlKey)) {
				self::$requestArgv['tab'] = $tabId;
				$this->set_var('tab', $tabId);
				$this->set_var('render', 'log');
				return;
			}
		}

		moon::page()->page404();
	}

	function main($argv)
	{
		$e = NULL;
		switch ($argv['render']) {
			case 'log':
			case 'custom': // shoutbox full history
				$output = $this->renderLog($argv, $e);
				break;

			case 'entry':
				$output = $this->renderEntry($argv, $e);
				break;

			case 'bluff-xml':
				$output = $this->renderBluffXml($argv, $e);
				break;

			default:
				$e = 'not_found';
				break;
		}
		switch ($e) {
			case NULL:
				return $output;

			default:
				moon::page()->page404();
		}
	}

	/**
	 * Get request argument by key
	 * @param string $key
	 * @return mixed
	 */
	public function requestArgv($key)
	{
		return self::$requestArgv[$key];
	}

	/**
	 * Get request arguments
	 * @return array
	 */
	public function requestArgvAll()
	{
		return self::$requestArgv;
	}

	public function setRequestArgv($key, $value)
	{
		self::$requestArgv[$key] = $value;
	}

	private function lrep()
	{
		static $lrepObj = null;
		if (!$lrepObj) {
			$lrepObj = $this->object('livereporting');
		}
		return $lrepObj;
	}

	private function lrepEv()
	{
		static $lrepEvObj = null;
		if (!$lrepEvObj) {
			$lrepEvObj = $this->lrep()->instEventModel('_src_event_core');
		}
		return $lrepEvObj;
	}

	/**
	 * note: sets `tab` = null always, see eSpecialPages
	 */
	private function getRequestArgv($argv)
	{
		$dayName = !empty($argv['uri']['path'])
			? $argv['uri']['path'][0]
			: NULL;

		switch ($dayName) {
			case 'all':
				$dayId = 0;
				break;

			case 'shoutbox':
				$dayName = '';
				// no break
			case '':
				$dayId = $this->lrepEv()
					->getDaysDefaultId($argv['event_id']);
				break;

			default:
				$day = $this->lrepEv()->getDayData($argv['event_id'], $dayName);
				$dayId = ($day['is_live'] == '0' && !(moon::page()->get_global('adminView') && $this->lrep()->instTools()->isAllowed('writeContent')))
					? NULL
					: $day['id'];
				break;
		}
		if ($dayId === NULL) {
			return NULL;
		}

		$pageNr = isset($_GET['page'])
			? intval($_GET['page'])
			: NULL;

		$filter = array();
		$filter['show'] = isset($_GET['show'])
			? $_GET['show']
			: '';
		$filter['rsort'] = isset($_GET['rsort'])
			? $_GET['rsort']
			: '';
		if (isset($_GET['tag'])) {
			$tagsObj = $this->object('other.ctags')->getReportingHandle();
			moon::page()->redirect(
				$tagsObj->getUrl($_GET['tag']), 301
			);
		}

		return array(
			'tournament_id' => filter_var($argv['tournament_id'], FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE),
			'event_id' => filter_var($argv['event_id'], FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE),
			'day_id' => filter_var($dayId, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE),
			'filter_day' => $dayName,
			'filter' => $filter,
			'tab' => NULL, // see eSpecialPages
			'page' => $pageNr
		);
	}

	private function renderLog($argv, &$e)
	{
		$page = &moon::page();
		$tpl  = $this->load_template();
		$t9n  = $tpl->parse_array('log:t9n');
		$lrep = $this->lrep();

		// @note event is accessible by regular users even if it is not started yet
		$eventInfo = $this->lrepEv()->getEventData($argv['event_id']);
		if (NULL == $eventInfo) {
			$page->page404();
		}

		$this->bannerRoomAssign($eventInfo['ad_rooms']);
		$this->gaAssign($argv['event_id'], $eventInfo, $page);

		$mainArgv = array(
			'tournament_name' => htmlspecialchars($eventInfo['tname']),
			'event_name' => htmlspecialchars($eventInfo['ename']),
			'paginator' => '',
			'list.entries' => '',
			'url_events' =>  $lrep->makeUri('#', array(
				'tournament_id' => $argv['tournament_id']
			)),
			'url_tournaments' => $lrep->makeUri('index#tour-archive'),
		);

		switch ($argv['tab']) {
			case 'chips':
				$this->partialRenderChipsTab($argv, $mainArgv);
				break;
			case 'gallery':
				$this->partialRenderGalleryTab($argv, $mainArgv);
				break;
			case 'payouts':
				$this->partialRenderPayoutsTab($argv, $mainArgv);
				break;
			case 'log':
				$this->partialRenderLogTab($argv, $mainArgv, $eventInfo, $lrep, $page);
				break;
			case 'custom': // e.g. shoutbox
				$mainArgv['list.entries'] = $argv['content']['body'];
				$mainArgv['mainContainerId'] = $argv['content']['id'];
				$mainArgv['right'] = $argv['content']['right'];
				break;
		}

		$page->title($t9n['live_reporting'] . ' | ' . $eventInfo['tname'] . ' | ' . $eventInfo['ename']);
		moon::shared('sitemap')->breadcrumb(array(
			$lrep->makeUri('#', array(
				'tournament_id' => $argv['tournament_id']
			)) => $eventInfo['tname'],
			$lrep->makeUri('#', array(
				'event_id' => $argv['event_id']
			)) =>	$eventInfo['ename']
		));
		if ($eventInfo['autopublish']) {
			$page->head_link($lrep->makeAltUri($eventInfo['sync_origin'], 'event#view', array(
					'event_id' => $argv['event_id'],
					'path' => $this->getUriPath(),
					'leaf' => $this->getUriTab()
				), $this->getUriFilter(NULL, TRUE)
			), 'canonical', '');
		}

		$this->partialRenderSidewidgets($mainArgv, $argv, $tpl, $lrep, $eventInfo, $page); // + sets safeTabs for topnav
		$this->partialRenderTopnav($mainArgv, $argv, $page, $lrep, $tpl, $eventInfo); // + common css
		$this->partialRenderControls($mainArgv, $page, $lrep, $argv, $eventInfo); // + adm css

		return $tpl->parse('log:main', $mainArgv);
	}

	private function partialRenderChipsTab($argv, &$mainArgv)
	{
		$mainArgv['list.entries'] = $this->instEventChips()->render(array(
			'event_id' => $argv['event_id'],
			'day_id'   => $argv['day_id'],
		), array(
			'variation'=> 'logTab'
		));

		$mainArgv['mainContainerId'] = 'livePokerChipsCount';
	}

	private function partialRenderPayoutsTab($argv, &$mainArgv)
	{
		$mainArgv['list.entries'] = $this->instEventEvent()->render(array(
			'event_id' => $argv['event_id'],
		), array(
			'variation'=> 'logTab'
		));

		$mainArgv['mainContainerId'] = 'livePokerPayouts';
	}

	private function partialRenderGalleryTab($argv, &$mainArgv)
	{
		$mainArgv['list.entries'] = $this->instEventPhotos()->render(array(
			'event_id' => $argv['event_id'],
		), array(
			'variation'=> 'logTab'
		));

		$mainArgv['mainContainerId'] = 'livePokerPhotoGallery';
	}

	private function partialRenderLogTab($argv, &$mainArgv, $eventInfo, $lrep, $page)
	{
		$showHidden = $page->get_global('adminView') && $lrep->instTools()->isAllowed('viewLogHidden');
		$paginator  = moon::shared('paginate');
		$paginator->set_curent_all_limit(
			intval($argv['page']),
			$this->lrepEv()->getLogEntriesCount(
				$argv['event_id'], $argv['day_id'],
				$argv['filter'] + array('showHidden' => $showHidden)
			),
			$this->logPageBy
		);
		$paginator->set_url(
			$lrep->makeUri('event#view', array(
					'event_id' => $argv['event_id'],
					'path' => $this->getUriPath(),
				), $this->getUriFilter(array('page'=>'{pg}'))
			),
			$lrep->makeUri('event#view', array(
					'event_id' => $argv['event_id'],
					'path' => $this->getUriPath()
				), $this->getUriFilter()
			)
		);
		$paginatorInfo = $paginator->get_info();
		$mainArgv['paginator'] = $paginator->show_nav();

		$logEntries = $this->lrepEv()->getLogEntries(
			$argv['event_id'], $argv['day_id'],
			$argv['filter'] + array('showHidden' => $showHidden),
			$paginatorInfo['sqllimit']
		);
		foreach ($logEntries as $logEntry) {
			switch ($logEntry['type']) {
				case 'chips':
					$object = 'instEvent' . ucfirst($logEntry['type']);
					$mainArgv['list.entries'] .= $this->$object()->preRender($logEntry, array(
						'variation'=> 'logEntry',
					));
					break;
			}
		}
		foreach ($logEntries as $logEntry) {
			switch ($logEntry['type']) {
				case 'post':
				case 'tweet':
				case 'photos':
				case 'day':
				case 'round':
				case 'chips':
					$object = 'instEvent' . ucfirst($logEntry['type']);
					$mainArgv['list.entries'] .= $this->$object()->render($logEntry + array(
						'tzName' => $eventInfo['tzName'],
						'tzOffset' => $eventInfo['tzOffset']
					), array(
						'variation'=> 'logEntry',
					));
					break;
			}
		}

		$mainArgv['mainContainerId'] = 'livePokerLiveReporting';
	}

	private function partialRenderTopnav(&$mainArgv, $argv, $page, $lrep, $tpl, $eventInfo)
	{
		$mainArgv['list.days'] = '';
		$mainArgv['list.tabs'] = '';

		if (in_array($argv['tab'], array('log', 'chips'))) {
			$days = $this->lrepEv()->getDaysData($argv['event_id']);
			$dayUrl = $lrep->makeUri('event#view', array(
					'event_id' => $argv['event_id'],
					'path' => '{}',
					'leaf' => $this->getUriTab()
				), $this->getUriFilter()
			);
			$prevBigDay = '';
			foreach ($days as $day) {
				if ($day['is_live'] == '0') {
					continue;
				}
				if ($day['id'] != $argv['day_id'] && $day['is_empty'] == '1' ) {
					continue ;
				}
				preg_match('~^[0-9]+~i', $day['name'], $bigDay);
				if (isset ($bigDay[0])) {
					$bigDay = $bigDay[0];
				} else {
					$bigDay = '0';
				}
				$uriPath = $this->getUriPath($day['id']);
				$url = $uriPath == ''
					? str_replace('{}/', '', $dayUrl)
					: str_replace('{}', $this->getUriPath($day['id']), $dayUrl);
				$mainArgv['list.days'] .= $tpl->parse(
					($day['id'] == $argv['day_id']
						? 'log:days.active'
						: 'log:days.inactive'),
					array(
						'url' => $url,
						'name' => $day['name'],
						'nextDay' => $prevBigDay != $bigDay
					));
				$prevBigDay = $bigDay;
			}
		}

		$tabs = $tpl->parse_array('log:tabs');
		foreach ($tabs as $tabId => $tab) {
			list ($urlKey, $tabName) = explode("|", $tab);
			$urlKey = urlencode(trim($urlKey));
			$tabName = trim($tabName);

			$type = $argv['tab'] == $tabId
				? 'active'
				: 'inactive';

			if (in_array($tabId, array('chips', 'gallery', 'payouts'))) {
				if ($argv['tab'] != $tabId && empty($this->tabsSafeDisplay[$tabId]) && !($page->get_global('adminView') && $tabId == 'chips' && $eventInfo['state'] == '1')) {
					$type .= '_empty';
				}
			}
			if ($tabId == 'log' && $eventInfo['state'] == '1') {
				$tabName .= $tpl->parse('log:tab.part.liveupdate', array());
				$mainArgv['launch_live_count'] = true;
				$mainArgv['launch_live_day_id'] = $argv['day_id'];
				$mainArgv['launch_live_event_id'] = $argv['event_id'];
				$mainArgv['launch_live_since'] = time();
				$mainArgv['misc_ajax_url'] = $lrep->makeUri('index#miscjs');
			}

			$mainArgv['list.tabs'] .= $tpl->parse('log:tab.'. $type, array(
				'url' => $lrep->makeUri('event#view', array(
						'event_id' => $argv['event_id'],
						'path' => $this->getUriPath(),
						'leaf' => $urlKey
					)
				),
				'name' => $tabName
			));
		}

		$page->css('/css/live_poker.css');
		$page->css('/css/article.css');
		$page->js('/js/live-poker.js');
		$page->js('/js/jquery/lightbox-0.5.js');
		$page->js('/js/modules/lrslider.js');
		$page->css('/css/jquery/lightbox-0.5.css');
		if ($page->get_global('adminView') && $lrep->instTools()->isAllowed('writeContent')) {
			$page->js('/js/jquery/livequery.js');
			$page->js('/js/jquery/form.js');
			$page->js('/js/jquery/postmessage.js');
			$page->js('/js/jquery/cookie.js');
			$page->js('/js/modules/live_reporting.js');
		}
	}

	private function partialRenderSidewidgets(&$mainArgv, &$argv, &$tpl, &$lrep, &$eventInfo, &$page)
	{
		$cacheable = !$page->get_global('adminView') || !$lrep->instTools()->isAllowed('writeContent');

		if ($cacheable) {
			$cache = moon::cache();
			$ckey = 'reporting.sidebar-argv-' . $argv['event_id'] . '-' . $argv['day_id'];
			if (($stored = $cache->get($ckey)) !== FALSE) {
				list ($mainArgv_,
					$this->tabsSafeDisplay['chips'],
					$this->tabsSafeDisplay['gallery'],
					$this->tabsSafeDisplay['payouts']) = $stored;
				$mainArgv += $mainArgv_;
				return ;
			}
		}

		// events dropdown
		list ($mainArgv_['eventlist.list.events'],
			$mainArgv_['eventlist.list.events.pager'],
			$mainArgv_['eventlist.paged'],
			$mainArgv_['eventlist.singleevent']) = $this->partialRenderSidewidgetEventsList($argv, $page, $lrep, $tpl);

		$mainArgv_['widget.key_hands'] = $this->partialRenderTopwidgetKeyHands($argv, $page, $lrep, $tpl);

		list (
			$playersLeft,
			$winner,
			$mainArgv_['widget.winner'],
			$mainArgv_['winner.prize'],
			$mainArgv_['winner.winner'],
			$mainArgv_['winner.cards'],
			$mainArgv_['winner.winnerurl'],
			$mainArgv_['winner.winnerimg'],
		) = $this->partialRenderSidewidgetWinner($eventInfo, $argv, $lrep, $tpl);

		$mainArgv_['widget.latest_updates'] = false;

		list (
			$mainArgv_['round.round_block'],
			$mainArgv_['round.round'],
			$mainArgv_['round.smallblind'],
			$mainArgv_['round.bigblind'],
			$mainArgv_['round.limit_not_blind'],
			$mainArgv_['round.ante']
		) = $this->partialRenderSidewidgetRound($argv);
		$mainArgv_['widget.latest_updates'] |= $mainArgv_['round.round_block'];

		list (
			$mainArgv_['pl.players_block'],
			$mainArgv_['pl.pleft'],
			$mainArgv_['pl.ptotal'],
			$mainArgv_['pl.chips_avg'],
			$mainArgv_['pl.chips_total'],
			$mainArgv_['pl.prizepool']
		) = $this->partialRenderSidewidgetPlayersStats($eventInfo, $winner, $playersLeft);
		$mainArgv_['widget.latest_updates'] |= $mainArgv_['pl.players_block'];

		list (
			$this->tabsSafeDisplay['payouts'],
			$mainArgv_['npj.npj_block'],
			$mainArgv_['npj.place'],
			$mainArgv_['npj.sum']
		) = $this->partialRenderSidewidgetNextPayJump($eventInfo, $argv, $lrep);
		$mainArgv_['widget.latest_updates'] |= $mainArgv_['npj.npj_block'];

		list (
			$this->tabsSafeDisplay['chips'],
			$mainArgv_['widget.top_chip_counts'],
			$mainArgv_['chips.top_chip_counts'],
		) = $this->partialRenderSidewidgetChips($winner, $page, $lrep, $eventInfo, $argv, $tpl);

		list (
			$this->tabsSafeDisplay['gallery'],
			$mainArgv_['list.event_photos'],
			$mainArgv_['url.more_photos']
		) = $this->partialRenderSidewidgetPhotos($argv, $tpl, $lrep, $eventInfo);

		$mainArgv_['right_twitter_stars_pro'] = '';
		/*if (_SITE_ID_ == 'com' && $eventInfo['tstate'] == 1 && $argv['tournament_id'] == 194 && is_object($obj=$this->object('social.twitter_box'))) {
			$mainArgv_['right_twitter_stars_pro'] = $obj->main(
				array('reporting' => 1, 'starspro' => 1)
			);
		}*/
		$mainArgv_['right_twitter'] = '';
		if ($eventInfo['tstate'] == 1 && is_object($obj=$this->object('social.twitter_box'))) {
			$mainArgv_['right_twitter'] = $obj->main(
				array('reporting' => 1, 'tour' => $eventInfo['tour'])
			);
		}

		$mainArgv_['com_social'] = _SITE_ID_ == 'com';
		$mainArgv_['nl_social'] = _SITE_ID_ == 'nl';

		if ($cacheable) {
			$cache->save($ckey, array(
					$mainArgv_,
					$this->tabsSafeDisplay['chips'],
					$this->tabsSafeDisplay['gallery'],
					$this->tabsSafeDisplay['payouts'],
				), $eventInfo['tstate'] == 2 || $winner != NULL
				? 60  // is finished
				: 10  // is live or not started
			);
		}

		$mainArgv += $mainArgv_;
	}

	private function partialRenderTopwidgetKeyHands(&$argv, &$page, &$lrep, &$tpl)
	{
		return ;
		$keyHands = $this->lrepEv()->getKeyHandEntries($argv['event_id']);
		if (count($keyHands) < 4)
			return ;

		$tplArgv = array(
			'list.entries' => '',
		);
		$text = moon::shared('text');
		foreach ($keyHands as $entry) {
			$tplArgv['list.entries'] .= $tpl->parse('sidebar:key_hands.item', array(
				'url' => $lrep->makeUri('event#view', array(
					'event_id' => $entry['event_id'],
					'path' => $this->getUriPath($entry['day_id']),
					'type' => $entry['type'],
					'id' => $entry['id']
				), $this->getUriFilter(NULL)),
				'title' => htmlspecialchars($entry['title']),
				'created_on' => $text->ago($entry['created_on']), // no y-m-d format date
				'author_name' => htmlspecialchars($entry['author_name']),
			));
		}
		return $tpl->parse('sidebar:key_hands', $tplArgv);
	}

	private function partialRenderSidewidgetEventsList($argv, $page, $lrep, $tpl)
	{
		$return = array();
		// events list dropdown
		$return['list.events.pop'] = '';
		$return['list.event.pop.pager'] = '';

		$events = $this->lrepEv()->getLiveEvents($argv['tournament_id'], $page->get_global('adminView'));
		$eventsUris = $lrep->instHierarchyModel()->getEventsUris();

		$activePageNr = 0;
		$tournamentUrl = $lrep->makeUri('#', array(
			'tournament_id' => $argv['tournament_id']
		));
		foreach ($events as $k => $event) {
			if ($argv['event_id'] == $event['id']) {
				$activePageNr =  floor($k / 10);
			}
			$return['list.events.pop'] .= $tpl->parse('log:events.pop.item.' . ($argv['event_id'] == $event['id']
					? 'current'
					: 'active'), array(
				'name' => htmlspecialchars($event['name']),
				/* 'url' => $lrep->makeUri('#', array(
					'event_id' => $event['id']
				)), */
				'url' => $tournamentUrl . $eventsUris[$event['id']][1] . '/',
				'page' => floor($k / 10)
			));
		}
		for ($k = 0; $k < ceil(count($events) / 10); $k++) { // 10 events per page
			$return['list.event.pop.pager'] .= $tpl->parse('log:events.pop.pager.' . ($activePageNr == $k
				? 'current'
				: 'active'), array(
				'page' => $k,
				'pageTitle' => $k+1
			)) . ' ';
		}
		$return['paged'] = ceil(count($events) / 10) > 1;
		$return['singleevent'] = count($events) < 2;

		return array(
			$return['list.events.pop'],
			$return['list.event.pop.pager'],
			$return['paged'],
			$return['singleevent']
		);
	}

	private function partialRenderSidewidgetRound($argv)
	{
		$round = $this->lrepEv()->getLastRound($argv['event_id'], $argv['day_id']);
		if ($round != null) {
			return array(
				true, // show round block
				$round['round'], // round
				number_format($round['small_blind']),
				number_format($round['big_blind']),
				$round['variety'] == 'limits-round',
				number_format($round['ante']),
			);
		}
	}

	private function partialRenderSidewidgetWinner($eventInfo, $argv, $lrep, $tpl)
	{
		$playersLeft = 0;
		$winner = NULL;
		$return = array();

		if (intval($eventInfo['pleft']) == 0) {
			if (intval($eventInfo['ptotal']) != 0) {
				$playersLeft = intval($eventInfo['ptotal']);
			}
		} else {
			$playersLeft = intval($eventInfo['pleft']);
		}

		if ($playersLeft == 1) {
			$winner = $this->lrepEv()->getWinner($argv['event_id']);
			if (NULL != $winner) {
				$return = array(
					'pw.prize' => $winner['prize']
						? number_format($winner['prize'])
						: '',
					'pw.winner' => htmlspecialchars($winner['winner']),
					'pw.cards' => $lrep->instTools()->helperFancyCards($winner['winning_hand'], $tpl),
					'pw.winnerurl' => $winner['winner_uri']
						? $this->linkas('players.poker#') . $winner['winner_uri'] . '/'
						: '',
					'pw.winnerimg' => $winner['winner_img']
						? img('player',  $winner['id'] . '-' . $winner['winner_img'])
						: ''
				);
			}
		}

		return @array(
			$playersLeft,
			$winner,
			NULL != $winner, // show block
			$return['pw.prize'],
			$return['pw.winner'],
			$return['pw.cards'],
			$return['pw.winnerurl'],
			$return['pw.winnerimg'],
		);
	}

	private function partialRenderSidewidgetPlayersStats($eventInfo, $winner, $playersLeft)
	{
		$return = array();
		$avgStack = 0;

		if ($playersLeft != 0 && intval($eventInfo['cp']) != 0) {
			$avgStack = number_format(round($eventInfo['cp'] / $playersLeft));
		}

		if ($playersLeft && $winner == NULL) {
			$return['pl.players_block'] = true;
			$return['pl.pleft'] = $playersLeft;
			$return['pl.ptotal'] = max(intval($eventInfo['ptotal']), $playersLeft);
		}

		if ($avgStack && $winner == NULL) { // show if winner is not entered yet
			$return['pl.players_block'] = true;
			$return['pl.chips_avg']   = $avgStack;
			$return['pl.chips_total'] = $eventInfo['cp']
				? number_format($eventInfo['cp'])
				: '';
		}

		if ($eventInfo['ppool']) {
			$return['pl.players_block'] = true;
			$return['pl.prizepool']   = number_format($eventInfo['ppool']);
		}

		return @array(
			$return['pl.players_block'],
			$return['pl.pleft'],
			$return['pl.ptotal'],
			$return['pl.chips_avg'],
			$return['pl.chips_total'],
			$return['pl.prizepool'],
		);
	}

	private function partialRenderSidewidgetNextPayJump($eventInfo, $argv, $lrep)
	{
		$payouts = $this->lrepEv()->getPayoutBundle($argv['event_id']);
		$retrun = array(
			'has_payouts' => $payouts['has_payouts']
		);
		if (null != $payouts['next_payout']) {
			$retrun += array(
				'block' => true,
				'place' => $payouts['next_payout']['place'],
				'sum' => $lrep->instTools()->helperCurrencyWrite(number_format($payouts['next_payout']['prize']), $eventInfo['currency'])
			);
		}

		return @array(
			$retrun['has_payouts'],
			$retrun['block'],
			$retrun['place'],
			$retrun['sum'],
		);
	}

	private function partialRenderSidewidgetChips($winner, $page, $lrep, $eventInfo, $argv, $tpl)
	{
		$return = array();
		$isAdm = $page->get_global('adminView') && $lrep->instTools()->isAllowed('writeContent');
		if ($winner == NULL) {
			$topChips = $this->lrepEv()->getLastTinyChips($argv['event_id'], $argv['day_id']);
			if (!empty($topChips)) {
				$return = array(
					'tab_safe_display' => true, // @ full listing should be available
					'widget.top_chip_counts' => true,
					'list.top_chip_counts' => ''
				);
				if ($isAdm) {
					 $playerUrl = $lrep->makeUri('event#view', array(
							'event_id' => $argv['event_id'],
							'type' => 'profile',
							'id' => '{}'
						));
				}
				foreach ($topChips as $k => $chip) {
					$return['list.top_chip_counts'] .= $tpl->parse('log:top_chip_counts.item', array(
						'evenodd' => $k % 2
							? 'even'
							: 'odd',
						'place' => $k+1,
						'is_busted' => intval($chip['chips']) == 0,
						'player' => htmlspecialchars($chip['name']),
						'playerurl' => $isAdm
							? str_replace('{}', $chip['id'], $playerUrl)
							: '',
						'player_sponsorimg' => !empty($chip['sponsor.img'])
							? ($chip['sponsor_id'] > 0
								? img('rw', $chip['sponsor_id'], $chip['sponsor.img'])
								: $chip['sponsor.img'])
							: NULL,
						'chips' => number_format($chip['chips']),
					));
				}
			}
		} else {
			$cache = moon::cache();
			$ckey = 'reporting.finished-ev-chips-' . $argv['event_id'] . '-' . $argv['day_id'];
			if (($hasChips = $cache->get($ckey)) === FALSE) {
				$topChips = $this->lrepEv()->getLastTinyChips($argv['event_id'], $argv['day_id']);
				$hasChips = count($topChips);
				$cache->save($ckey, $hasChips, 3600);
			}
			$return['tab_safe_display'] = intval($hasChips) > 0;
		}
		if (empty($return['tab_safe_display']) && $isAdm) {
			$days = $lrep->instEventModel('_src_event')->getDaysData($argv['event_id']);
			if ($lrep->instTools()->isAllowed('viewSingleChipsControl', array(
				'event_synced' => $eventInfo['synced'],
				'day_state' => $days[$argv['day_id']]['state']
			))) {
				$return['tab_safe_display'] = true;
			}
		}

		return @array(
			$return['tab_safe_display'], // @todo why is this here?
			$return['widget.top_chip_counts'],
			$return['list.top_chip_counts']
		);
	}

	private function partialRenderSidewidgetPhotos($argv, $tpl, $lrep, $eventInfo)
	{
		$mainArgv = array(
			'list.event_photos' => '',
			'url.more_photos' => '',
			'tab_safe_display' => false
		);
		$mainArgv['list.event_photos'] = '';
		$mainArgv['url.more_photos'] = '';

		if ($argv['tab'] != 'gallery') {
			$lastPhotos = $this->lrepEv()->getLastPhotos($argv['event_id']);
			if (0 != count($lastPhotos)) {
				$mainArgv['tab_safe_display'] = true;
			}
			$mainArgv['url.more_photos'] = $lrep->makeUri('event#view', array(
				'event_id' => $argv['event_id'],
				'path' => $this->getUriPath(),
				'leaf' => $this->getUriTab('gallery')
			));

			$ipnReadBase = $this->get_var('ipnReadBase');
			$tpl->save_parsed('log:event_photos.item', array(
				'event_name' => htmlspecialchars($eventInfo['ename']),
				'tournament_name' => htmlspecialchars($eventInfo['tname'])
			));
			foreach ($lastPhotos as $lastPhoto) {
				$lastPhoto['src_big'] = $lastPhoto['image_src'];
				$lastPhoto['src_big'][strlen($lastPhoto['src_big']) - 15] = 'm';
				$mainArgv['list.event_photos'] .= $tpl->parse('log:event_photos.item', array(
					'src' => $ipnReadBase . $lastPhoto['image_src'],
					'alt' => empty($lastPhoto['image_alt'])
						? '&nbsp;'
						: htmlspecialchars($lastPhoto['image_alt']),
					'src_big' =>  $ipnReadBase . $lastPhoto['src_big'],
				));
			}
		}

		return array(
			$mainArgv['tab_safe_display'],
			$mainArgv['list.event_photos'],
			$mainArgv['url.more_photos']
		);
	}

	private function partialRenderControls(&$mainArgv, $page, $lrep, $argv, $eventInfo)
	{
		$mainArgv['controls'] = '';
		if ($page->get_global('adminView') && $lrep->instTools()->isAllowed('writeContent')) {
			$mainArgv['showControls'] = true;
			// $argv = array_merge($argv, $eventInfo);
			// $argv['synced'] = 0; // event is synced != entry is synced
			$argv = array_merge($argv, array(
				'tzName' => $eventInfo['tzName'],
				'tzOffset' => $eventInfo['tzOffset'],
				'show_wsop_eod' => $eventInfo['show_wsop_eod'], // _chips
			));

			if (!isset($argv['hide_write_controls'])) {
				foreach (array('event', 'profile') as $object) {
					$object = 'instEvent' . ucfirst($object);
					$object = $this->$object();
					$mainArgv['controls'] .= $object->render($argv, array(
						'variation' => 'logControl'
					));
				}
			}
			if (!isset($argv['hide_write_controls']) && intval($argv['day_id'])) {
				foreach (array('post', 'tweet', 'round', 'chips', 'photos') as $object) {
					$object = 'instEvent' . ucfirst($object);
					$object = $this->$object();
					$mainArgv['controls'] .= $object->render($argv, array(
						'variation' => 'logControl'
					));
				}
			}

			//
			$page->css('/css/live-poker-adm.css');
			//
			$mainArgv['sidebar_controls'] = $this->partialRenderAdmSidebar($argv);
		}
	}

	private function bannerRoomAssign($adRooms)
	{
		$adRooms = explode(',', $adRooms);
		if (isset($adRooms[0])) {
			moon::page()->set_local('banner.roomID', $adRooms[0]);
		}
	}

	private function gaAssign($eventId, $eventInfo, $page)
	{
		if (!is_array($gaCustomVars = $page->get_local('gaCustomVars')))
			$gaCustomVars = array();

		$gaSponsor = '';
		$roomId = explode(',', $eventInfo['ad_rooms']);
		if ($roomId = reset($roomId)) {
			$gaSponsor = '~0-' . $roomId;
		}

		$eventGaId = !empty($eventInfo['esync_id'])
			? array($eventInfo['sync_origin'], $eventInfo['esync_id'])
			: array(_SITE_ID_,                 $eventId);
		array_push($gaCustomVars, array(
			'index' => 2, 'scope' => 3,
			'name' => 'LiveReporting',
			'value' => implode(':', $eventGaId) . $gaSponsor
		));

		$page->set_local('gaCustomVars', $gaCustomVars);
	}

	private function renderEntry($argv)
	{
		$page = moon::page();
		$tpl  = $this->load_template();
		$t9n  = $tpl->parse_array('entry:t9n');
		$lrep = $this->lrep();

		$eventInfo = $this->lrepEv()->getEventData($argv['event_id']);
		if (NULL == $eventInfo) {
			$page->page404();
		}

		$this->bannerRoomAssign($eventInfo['ad_rooms']);
		$this->gaAssign($argv['event_id'], $eventInfo, $page);

		if ($argv['type'] == 'profile') {
			$entry = $argv;
		} else {
			if (($entry = $this->lrepEv()->getLogEntry(
				$argv['event_id'], $argv['type'], $argv['id'], $page->get_global('adminView') && $lrep->instTools()->isAllowed('viewLogHidden')
			   )) == NULL) {
				$page->page404();
			}
		}
		if ($argv['day_id'] != '0' && $argv['day_id'] != $entry['day_id']) { // when default day changes transparently for user
			if ($argv['filter_day'] == NULL) {
				$node = 'event#' . $argv['action']; // view/edit/delete
				$page->redirect($lrep->makeUri($node, array(
					'event_id' => $entry['event_id'],
					'path' => $this->getUriPath($entry['day_id']),
					'type' => $entry['type'],
					'id' => $entry['id']
				), $this->getUriFilter()));
			} else {
				$page->page404();
			}
		}

		if (isset($entry['sync_id']) && isset($entry['type']) && empty($entry['updated_on'])) { // freshly synced, untranslated
			$page->head_link($lrep->makeAltUri($eventInfo['sync_origin'], 'event#view', array(
					'event_id' => $entry['event_id'],
					'path' => $this->getUriPath($entry['day_id']),
					'type' => $entry['type'],
					'id' => $entry['sync_id']
				), $this->getUriFilter(NULL)
			), 'canonical');
		}

		$mainArgv = array(
			'tournament_name' => htmlspecialchars($eventInfo['tname']),
			'event_name' => htmlspecialchars($eventInfo['ename']),
			'paginator' => '',
			'list.entries' => '',
			'url_events' =>  $lrep->makeUri('#', array(
				'tournament_id' => $argv['tournament_id']
			)),
			'url_tournaments' => $lrep->makeUri('index#tour-archive'),
			'mainContainerId' => 'livePokerPhotoGallery'
		);

		$page->title($t9n['live_reporting'] . ' | ' . $eventInfo['tname'] . ' | ' . $eventInfo['ename']);
		$sitemap = moon::shared('sitemap');
		$sitemap->breadcrumb(array(
			$lrep->makeUri('#', array(
				'tournament_id' => $argv['tournament_id']
			)) => $eventInfo['tname'],
			$lrep->makeUri('#', array(
				'event_id' => $argv['event_id']
			)) =>	$eventInfo['ename']
		));

		$this->partialRenderSidewidgets($mainArgv, $argv, $tpl, $lrep, $eventInfo, $page); // + sets safeTabs for topnav
		$this->partialRenderTopnav($mainArgv, $argv, $page, $lrep, $tpl, $eventInfo); // + common css
		$this->partialRenderControls($mainArgv, $page, $lrep, $argv, $eventInfo); // + adm css

		switch ($entry['type']) {
			case 'post':
			case 'tweet':
			case 'photos':
			case 'day':
			case 'round':
			case 'chips':
			case 'profile':
				$object = 'instEvent' . ucfirst($entry['type']);
				$body = $this->$object()->render($entry + array(
					'tzName' => $eventInfo['tzName'],
					'tzOffset' => $eventInfo['tzOffset']
				), array(
					'action' => $argv['action'],
					'variation'=> 'individual'
				));
				break;
			default:
				$page->page404();
				break;
		}

		$mainArgv['list.entries'] = $body;
		return $tpl->parse('log:main', $mainArgv);
	}

	private function partialRenderAdmSidebar($argv)
	{
		$tpl  = $this->load_template();
		$t9n  = $tpl->parse_array('sidebar:t9n');
		$lrep = $this->lrep();

		$eventInfo = $this->lrepEv()->getEventData($argv['event_id']);
		$days      = $this->lrepEv()->getDaysData($argv['event_id']);
		$cUriPath  = $this->getUriPath();

		$mainArgv = array(
			'tname' => htmlspecialchars($eventInfo['tname']),
			'ename' => htmlspecialchars($eventInfo['ename']),
			'dname' => $argv['day_id']
				? htmlspecialchars($days[$argv['day_id']]['name'])
				: '',
			'show_write_controls' => (bool)$argv['day_id'] && !isset($argv['hide_write_controls']),
			'show_event_controls' => !isset($argv['hide_write_controls']),
			'show_full_controls' => !$eventInfo['synced'],
			'days' => '',
			'active' => isset($_GET['master'])
				? 'w' . $_GET['master'][0]
				: NULL
		);

		if ($mainArgv['show_write_controls']) {
			foreach(array(
				// tpl uri name, master filter key
				array('write-post',    'post'),
				array('write-photos',  'xphotos'),
				array('write-round',   'round'),
				array('write-event',   'event'),
				array('write-chips',   'chips'),
				array('write-evmisc',  'misc'),
				array('write-tweet',   'tweet'),
				array('write-profiles','list-profiles'),
			) as $swc) {
				$mainArgv['url.' . $swc[0]] = $lrep->makeUri('event#view', array(
						'event_id' => filter_var($argv['event_id'], FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE),
						'path' => $cUriPath,
					), $this->getUriFilter(array(
						'master' => $swc[1]
					), TRUE)
				);
			}
			if (!in_array(_SITE_ID_, array('com'))) {
				$mainArgv['url.write-tweet'] = null;
			}
			$mainArgv['url.upload-photos'] = $lrep->makeUri('event#ipn-upload', array(
				'event_id' => filter_var($argv['event_id'], FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE),
				'path' => $cUriPath,
			), array('x' => 'photos-inst'));
			$mainArgv['url.review-photos'] = $lrep->makeUri('event#ipn-review', array(
				'event_id' => filter_var($argv['event_id'], FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE),
				'path' => $cUriPath,
			));
		}

		switch ($eventInfo['state']) {
			case '0':
				$mainArgv['evtstate'] = $t9n['evt.scheduled']; break;
			case '1':
				$mainArgv['evtstate'] = $t9n['evt.started']; break;
			case '2':
				$mainArgv['evtstate'] = $t9n['evt.completed']; break;
		}

		if ($mainArgv['show_event_controls']) {
			if ($argv['day_id'] && $days[$argv['day_id']]['state'] == '0') {
				$mainArgv += array(
					'url.dprogress' => $lrep->makeUri('event#save', array(
						'event_id' => filter_var($argv['event_id'], FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE),
						'path' => $cUriPath,
						'type' => 'day.start',
						'id' => $argv['day_id']
					), $this->getUriFilter(NULL, TRUE)),
					'dstatus' => $t9n['day.not_started'],
					'dprogress' => $t9n['day.start'],
				);
			} elseif ($argv['day_id'] && $days[$argv['day_id']]['state'] == '1') {
				$mainArgv += array(
					'url.dprogress' => $lrep->makeUri('event#save', array(
						'event_id' => filter_var($argv['event_id'], FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE),
						'path' => $cUriPath,
						'type' => 'day.complete',
						'id' => $argv['day_id']
					), $this->getUriFilter(NULL, TRUE)),
					'url.dregress' => $lrep->makeUri('event#save', array(
						'event_id' => filter_var($argv['event_id'], FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE),
						'path' => $cUriPath,
						'type' => 'day.stop',
						'id' => $argv['day_id']
					), $this->getUriFilter(NULL, TRUE)),
					'dstatus' => $t9n['day.started'],
					'dprogress' => $t9n['day.complete'],
					'dregress' => $t9n['day.undo_started']
				);
			} elseif ($argv['day_id'] && $days[$argv['day_id']]['state'] == '2') {
				$mainArgv += array(
					'url.dregress' => $lrep->makeUri('event#save', array(
						'event_id' => filter_var($argv['event_id'], FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE),
						'path' => $cUriPath,
						'type' => 'day.resume',
						'id' => $argv['day_id']
					), $this->getUriFilter(NULL, TRUE)),
					'dstatus' => $t9n['day.completed'],
					'dregress' => $t9n['day.undo_completed'],
				);
			}

			// is event progress changeable at this point
			$mainArgv['show_event_progress_change'] = false;
			$lastDay = $days;
			$lastDay = array_pop($lastDay);
			if ($eventInfo['state'] == '1' && ($argv['day_id'] == 0 || $lastDay['id'] == $argv['day_id'])) {
				$mainArgv['show_event_progress_change'] = true;
				$mainArgv += array(
					'url.eprogress' => $lrep->makeUri('event#save', array(
						'event_id' => filter_var($argv['event_id'], FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE),
						'path' => $cUriPath,
						'type' => 'event.complete',
						'id' => $argv['event_id']
					), $this->getUriFilter(NULL, TRUE)),
					'eprogress'=> $t9n['evt.complete']
				);
			} elseif ($eventInfo['state'] == '2' && ($argv['day_id'] == 0 || $lastDay['id'] == $argv['day_id'])) {
				$mainArgv['show_event_progress_change'] = true;
				$mainArgv += array(
					'url.eregress' => $lrep->makeUri('event#save', array(
						'event_id' => filter_var($argv['event_id'], FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE),
						'path' => $cUriPath,
						'type' => 'event.resume',
						'id' => $argv['event_id']
					), $this->getUriFilter(NULL, TRUE)),
					'eregress' => $t9n['evt.resume']
				);
			}

			$cUrl = $lrep->makeUri('event#view', array(
						'event_id' => $argv['event_id'],
						'path' => $cUriPath,
					), $this->getUriFilter(array(
						'master' => 'event',
						'submaster'=> 'vsub'
					), TRUE)
				);
			$mainArgv += array(
				'ce.url.sect-prizepool' => str_replace('vsub', 'prizepool', $cUrl),
				'ce.url.sect-winners'   => str_replace('vsub', 'winners', $cUrl),
			);

			$mainArgv['pl.pleft'] = intval($eventInfo['pleft']);
			$mainArgv['pl.ptotal'] = intval($eventInfo['ptotal']);
			$mainArgv['pl.event'] = $this->my('fullname') . '#save-sbplayers';
			$mainArgv['write_sbplayers_url'] = htmlspecialchars_decode($lrep->makeUri('event#save', array(
				'event_id' => $argv['event_id'],
				'path' => $this->getUriPath(),
				'type' => 'event',
				'id' => 'sbplayers'
			), $this->getUriFilter(NULL, TRUE)));
		}

		foreach ($days as $day) {
			$mainArgv['days'] .= $tpl->parse('sidebar:day', array(
				'url' => $lrep->makeUri('event#view', array(
					'event_id' => $argv['event_id'],
					'path' => $this->getUriPath($day['id'])
					), $this->getUriFilter(/*$dAdd*/)
				),
				'is_started' => (string)($day['state'] == '1'),
				'is_completed' => (string)($day['state'] == '2'),
				'name' => str_replace('{name}', $day['name'], $t9n['day.named'])
			));
		}
		$mainArgv['days'] .=  $tpl->parse('sidebar:day', array(
			'url' => $lrep->makeUri('event#view', array(
				'event_id' => $argv['event_id'],
				'path' => $this->getUriPath('all')
				), $this->getUriFilter(/*$dAdd*/)
			),
			'is_active' => ($argv['day_id'] === 0)
				? '1'
				: '0',
			'name' => $t9n['day.all']
		));

		return $tpl->parse('sidebar:main', $mainArgv);
	}

	private function redirectIPN($redirUriName)
	{
		$page = moon::page();
		$user = moon::user();

		$eventInfo = $this->lrepEv()->getEventData($this->requestArgv('event_id'));
		$days      = $this->lrepEv()->getDaysData($this->requestArgv('event_id'));

		if (!$user->i_admin()) {
			return ;
		}

		$sid = $page->get_global($this->my('fullname') . '_ipnSid');
		if (!is_array($sid)) {
			$sid = array();
		}
		foreach ($sid as $k => $v) {
			if ($sid[$k][1] < time() - 1800) {
				unset($sid[$k]);
			}
		}

		$error = false;
		$key = $this->requestArgv('tournament_id') . '.' . $this->requestArgv('event_id') . '.' . $this->requestArgv('day_id');
		if (empty($sid[$key])) {
			$sendData = array(
				'ns' => 'lrep',
				'src' => _SITE_ID_,
				'user' => $user->get_user_id(),
				'user_nick' => $user->get_user('nick'),
				'root_uri' => array(
					array($this->requestArgv('tournament_id')),
				),
				'browse_uri' => array(
					array(
						$this->requestArgv('tournament_id'),
						$this->requestArgv('event_id')
					)
				),
				'upload_uri' => array(
					array($this->requestArgv('tournament_id'), $eventInfo['tname']),
					array($this->requestArgv('event_id'), $eventInfo['ename']),
					array($this->requestArgv('day_id'), 'Day ' . $days[$this->requestArgv('day_id')]['name']),
				),
				'transforms' => $this->get_var('ipnDataReq'),
				'time' => time()
			);
			if ($eventInfo['synced']) {
				$syncData = $this->lrepEv()->getTournamentSyncOrigin($this->requestArgv('event_id'));
				if (!empty($syncData)) {
					$sendData['root_uri'][] = array(
						$syncData[0],
						$syncData[1]
					);
				}
			}

			if (moon::shared('rpc')->getInstance('pnw-imgsrv')->send('app_login#remoteLogin', $sendData, $response) && is_array($response)) {
				$sid[$key] = [$response['sid'], time()];
			} else {
				$error = true;
			}
		}

		$page->set_global($this->my('fullname') . '_ipnSid', $sid);

		if (!empty($sid[$key]) && !$error) {
			// @no way to check if logged in
			$page->redirect($this->get_var('ipnWriteBase') . $this->get_var($redirUriName) . '?sid=' . $sid[$key][0] . '&key=' . (isset($_GET['x']) ? $_GET['x'] : ''));
		} else {
			$page->page404();
		}

		moon_close();
		exit;
	}

	private function renderBluffXml($argv, &$e)
	{
		if (isset($_GET['day'])) {
			$argv['day_id'] = intval($_GET['day']);
			$days = $this->lrepEv()->getDaysData($argv['event_id']);
			if (!isset($days[$argv['day_id']])) {
				moon::page()->page404();
			}
		}

		$output = $this->object('livereporting_bluff')->process($argv);
		if (empty($output)) {
			$e = true;
			return;
		}
		Header('Content-Type: application/xml; charset=utf-8');
		echo $output;
		moon_close();
		exit;
	}

	/**
	 * Returns the event url tab uri by key, or current tab uri
	 * @param mixed $tab Tab key: string|int|null
	 * @return string
	 */
	protected function getUriTab($tab = NULL)
	{
		if ($tab == NULL) {
			$tab = $this->requestArgv('tab');
		}
		static $resultCache = NULL;
		if ($resultCache == NULL) {
			$resultCache = array();
			// when called from descendant, wrong template is loaded, if not directly specified
			$tpl = $this->load_template('livereporting_event');
			$tabs = $tpl->parse_array('log:tabs');
			foreach ($tabs as $tabId => $tabString) {
				list ($urlKey, ) = explode("|", $tabString);
				$urlKey = urlencode(trim($urlKey));
				$resultCache[$tabId] = $urlKey;
			}
		}
		return isset($resultCache[$tab])
			? $resultCache[$tab]
			: '';
	}

	/**
	 * Returns the event url "path" chunk, e.g. 'day5', 'all' or ''
	 * @param string|int|null $dayId 'all' or 0 for all, id for day, null for default
	 * @return string
	 */
	protected function getUriPath($dayId = NULL)
	{
		if ($dayId === NULL) {
			return $this->requestArgv('filter_day');
		} elseif ($dayId === 0 || $dayId === 'all') {
			return 'all';
		} elseif ($dayId == $this->lrepEv()->getDaysDefaultId($this->requestArgv('event_id'))) {
			return '';
		} else {
			$days = $this->lrepEv()->getDaysData($this->requestArgv('event_id'));
			return 'day' . $days[$dayId]['name'];
		}
	}

	/**
	 * Returns the event url "tail" part: page nr, filters
	 * @param array $add
	 * @param mixed $pageNr true for current page, number for wanted page
	 * @return array
	 */
	protected function getUriFilter($add = NULL, $pageNr = NULL)
	{
		$filter = $this->requestArgv('filter');
		if (is_array($add)) {
			$filter = array_merge($filter, $add);
		}
		if ($pageNr !== NULL) {
			if ($pageNr === TRUE) {
				if ($this->requestArgv('page') != '') {
					$filter = array_merge($filter, array(
						'page' => $this->requestArgv('page')
					));
				}
			} else {
				if (intval($pageNr) > 1) {
					$filter = array_merge($filter, array(
						'page' => $pageNr
					));
				}
			}
		}
		foreach ($filter as $k => $v) {
			if ($v == '') {
				unset($filter[$k]);
			}
		}
		return $filter;
	}

	// independent environment, may work wrong if navigation changed
	// may 404
	protected function redirectToLogEntry($entry, $getParamsAdditional = array())
	{
		$lrep = $this->lrep();

		if (NULL == ($entry = $this->lrepEv()->getLogEntryRedirectable($entry['id'], $entry['type']))) {
			moon::page()->page404();
		}

		$redirToDayAll = intval($this->requestArgv('day_id')) === 0;

		$redirDayId = $redirToDayAll // *must* be rewritten to not null
			? 0
			: $entry['day_id'];
		$filter = $this->requestArgv('filter');
		$successivePostsCnt = $this->lrepEv()->getSuccessivePostsCount(
			$entry,
			$redirToDayAll,
			$filter['show'],
			$lrep->instTools()->isAllowed('viewLogHidden')
		);

		self::$requestArgv = array(
			//'tournament_id' => $entry['tournament_id'], - not used
			'event_id' => $entry['event_id'],
			//'day_id' => 0, - not used
			//'filter_day' => NULL,  - $redirDayId != null
			'filter' => $this->requestArgv('filter'),
			//'page' => 1, - not used
		);

		// $this->redirect does not work for the same reason as $this->linkas()
		moon::page()->redirect(htmlspecialchars_decode($lrep->makeUri('event#view', array(
					'event_id' => $entry['event_id'],
					'path' => $this->getUriPath($redirDayId)
				), $this->getUriFilter(
					@$getParamsAdditional['add'],
					$entry['is_hidden'] != 2
						? ceil(($successivePostsCnt+1) / $this->logPageBy)
						: 1
				)
			)) . (!empty($getParamsAdditional['noanchor'])
				? ''
				: ('#' . $entry['type'] . '-' . $entry['id'])
		));
	}
	// end independent

	/**
	 * Turn to public on demand
	 * @return livereporting_event_post
	 */
	protected function instEventPost()
	{
		return $this->getEventPylonObject('post');
	}

	/**
	 * Turn to public on demand
	 * @return livereporting_event_tweet
	 */
	protected function instEventTweet()
	{
		return $this->getEventPylonObject('tweet');
	}

	/**
	 * public since used from _bluff
	 * @return livereporting_event_photos
	 */
	public function instEventPhotos()
	{
		return $this->getEventPylonObject('photos');
	}

	/**
	 * Turn to public on demand
	 * @return livereporting_event_day
	 */
	protected function instEventDay()
	{
		return $this->getEventPylonObject('day');
	}

	/**
	 * Turn to public on demand
	 * @return livereporting_event_round
	 */
	protected function instEventRound()
	{
		return $this->getEventPylonObject('round');
	}

	/**
	 * Turn to public on demand
	 * @return livereporting_event_chips
	 */
	protected function instEventChips()
	{
		return $this->getEventPylonObject('chips');
	}

	/**
	 * public since used from _bluff, players.poker
	 * @return livereporting_event_event
	 */
	public function instEventEvent()
	{
		return $this->getEventPylonObject('event');
	}

	/**
	 * Turn to public on demand
	 * @return livereporting_event_profile
	 */
	protected function instEventProfile()
	{
		return $this->getEventPylonObject('profile');
	}

	/**
	 * @return mixed
	 */
	private function getEventPylonObject($identity)
	{
		if (empty($this->subObjCache[$identity])) {
			$obj = $this->object('livereporting_event_' . $identity);
			$this->subObjCache[$identity] = &$obj;
		}
		return $this->subObjCache[$identity];
	}
}
