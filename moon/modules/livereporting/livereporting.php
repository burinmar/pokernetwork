<?php
/**
 * @package livereporting
 */
include_class('moon_memcache');

/**
 * Livereporting class provides url read/write mechanism, access to various reporting models.
 * 
 * Url reading and construction is currently done using an extra abstraction layer, the "components contract". 
 * Direct url construction is possible, but would be, if done, inconsistent.
 *
 * Puplic object attributes are to be accessed by models/livereporting_model_pylon and all it's descendants.
 * @package livereporting
 */
class livereporting extends moon_com
{
	/**
	 * Memcache object
	 * @var moon_memcache
	 */
	public $mcd = NULL;
	/**
	 * Memcache recommended prefix
	 * @var string
	 */
	public $mcdKey = NULL;
	function onload()
	{
		$this->mcd = moon_memcache::getInstance();
		$this->mcdKey = moon_memcache::getRecommendedPrefix();
		// global $_profiler;
		// $this->profiler = &$_profiler;
	}
	
	/**
	 * Dispatches all incoming events
	 * 
	 * May 404()
	 * @param string $event
	 * @param array $argv 
	 */
	function events($event, $argv)
	{
		switch($event) {
			case 'read_uri':
				if (is_array($argv)) {
					$last = array_pop($argv);
					if ($last != 'htm') {
						array_push($argv, $last);
					}
				}
				list($component, $argv) = $this->readUri_($event, $argv);
				break;

			case 'make_uri':
				return $this->makeUri_($event, $argv);
		}

		$page = moon::page();
		if (isset($component) && $page->call_event($component, $argv)) {
			return ;
		}
		$page->page404();
	}

	/**
	 * Generates output for widgets, included via xml files
	 *
	 * May 404()
	 * @param array $argv
	 * @return string
	 */
	function main($argv)
	{
		if (isset($argv['widget'])) {
			switch ($argv['widget']) {
			case 1:
				$obj = $this->object('livereporting.livereporting_index');
				return $obj->main(array(
					'render' => 'index-widget'
				));
			case 2:
				$obj = $this->object('livereporting.livereporting_index');
				return $obj->main(array(
					'render' => 'sidebar-live-widget'
				));
			case 'countdown':
				$obj = $this->object('livereporting.livereporting_index');
				return $obj->main(array(
					'render' => 'countdown-widget'
				));
			}
		}
		$page = &moon::page();
		$page->page404();
	}
	
	/**
	 * Tournament/event url data model
	 * 
	 * Also used from within forum
	 * @return livereporting_model_hierarchy 
	 */
	public function instHierarchyModel()
	{
		static $model;
		if (!$model) {
			require_once dirname(__FILE__) . '/models/livereporting_hierarchy.php';
			$model = new livereporting_model_hierarchy($this);
		}
		return $model;
	}

	/**
	 * Various formatting helpers
	 * @return livereporting_tools 
	 */
	public function instTools()
	{
		static $model;
		if (!$model) {
			require_once dirname(__FILE__) . '/models/livereporting_tools.php';
			$model = new livereporting_tools($this);
		}
		return $model;
	}

	/**
	 * Tour-related data model
	 * @param string $suffix Sub-model suffix
	 * @return livereporting_model_tour 
	 */
	public function instTourModel($suffix = '')
	{
		static $model = array();
		$class = 'livereporting_model_tour' . $suffix;
		if (!isset($model[$class])) {
			require_once dirname(__FILE__) . '/models/livereporting_tour.php';
			$model[$class] = new $class($this);
		}
		return $model[$class];		
	}

	/**
	 * Tournament-related data model
	 * @param string $suffix Sub-model suffix
	 * @return livereporting_model_tournament 
	 */
	public function instTournamentModel($suffix = '')
	{
		static $model = array();
		$class = 'livereporting_model_tournament' . $suffix;
		if (!isset($model[$class])) {
			require_once dirname(__FILE__) . '/models/livereporting_tournament.php';
			$model[$class] = new $class($this);
		}
		return $model[$class];
	}
	
	/**
	 * Event-related data model
	 * @param string $suffix Sub-model suffix
	 * @return livereporting_model_event 
	 */
	public function instEventModel($suffix = '')
	{
		static $model = array();
		$class = 'livereporting_model_event' . $suffix;
		if (!isset($model[$class])) {
			require_once dirname(__FILE__) . '/models/livereporting_event.php';
			$model[$class] = new $class($this);
		}
		return $model[$class];
	}

	/**
	 * Parses uri, when automatic parsing is skipped.
	 * 
	 * Automatic uri parsing is skipped on _POST submits, e.g. in livereporting_event save() methods.
	 * Basically, metod redirects to readUri_.
	 * Returns array:
	 * - name#event of component to continue parsing
	 * - arguments, which may include "relative" url(path), tournament/event ids,  etc.
	 * @return array (see description)
	 */
	public function readUri()
	{
		$page = &moon::page();
		$uri = $page->uri_segments(0);
		$this->cachedEnv();
		$prefix = '/' . $this->lrRoot . '/';
		if (strpos($uri, $prefix) === FALSE) {
			$prefix = '/' . urlencode($this->lrRoot) . '/';
		}
		$uri = str_replace($prefix, '', $uri);
		$uri = explode('.', $uri);
		if (in_array($uri[count($uri)-1], array('htm', 'js', 'rss', 'xml'))) {
			array_pop($uri);
		}
		return $this->readUri_('read_uri', $uri);
	}

	/**
	 * Parses uri according to the component contract.
	 * 
	 * Returns array:
	 * - name#event of component to continue parsing
	 * - arguments, which may include "relative" url(path), tournament/event ids,  etc.
	 */
	private function readUri_($event, $argv)
	{
		if (!isset($argv[0])) {
			$argv[0] = '';
		}

		$argv = explode('/', implode('/', $argv));
		while (count($argv) && '' === ($lastSegment = $argv[count($argv)-1])) {
			array_pop($argv);
		}

		$page = &moon::page();
		$uriSegments = $page->uri_segments();
		while ('' === ($lastSegment = $uriSegments[count($uriSegments)-1])) {
			array_pop($uriSegments);
		}

		$argv = array(
			'path' => $argv,
			'argv' => array(),
			'type' => 'htm'
		);
		if (preg_match('~(.+)\.(js|htm|rss|xml|json)$~', $uriSegments[count($uriSegments)-1], $matches)) {
			$argv['type'] = array_pop($matches);
			$matches = explode('.', $matches[1]);
			if ($argv['type'] != 'htm') {
				array_pop($argv['path']);
			}
			$argv['path'] = array_slice($argv['path'], 0, count($argv['path']) - count($matches));
			$argv['argv'] = $matches;
		}

		$uri = &$argv;

		if (count($uri['path']) == 0) {
			// * sync(_v2)
			// * imgsrv
			if (count($uri['argv']) == 1) {
				switch ($uri['argv'][0]) {
					case 'sync_v2':
					return array('sync_reporting_export#read_uri', array(
						'key' => $uri['argv'][0]
					));
					/* case 'imgsrv-import':
					return array('livereporting_transport#read_uri', array(
						'key' => 'imgsrv'
					)); */
				}
			}

			// * rtf-preview, rtf, ...
			return array('livereporting_index#read_uri', array(
				'uri' => $uri
			));
		}

		$tBase = $this->instHierarchyModel()->getTorunamentsUris();
		$path = implode('/', $uri['path']);
		//$path = preg_replace('~^_~', '../', $path);
		//$uri['path'][0] = preg_replace('~^_~', '../', $uri['path'][0]);
		if (($tId = array_search($uri['path'][0], $tBase)) === false) {
			return ;
		}
		$tUri = $tBase[$tId];
		if ($uri['path'][0] == $tUri) {
			if ($path == $tUri) {
				// index page for specific tournament
				$uri['path'] = array();
				return array('livereporting_tournament#read_uri', array(
					'uri' => $uri,
					'tournament_id' => $tId
				));
			} else {
				// tournament log
				$eBase = $this->instHierarchyModel()->getEventsUris();
				array_shift($uri['path']); // url to relative: tid/eid/.. => eid/..
				if (($eId = array_search(array($tId, $uri['path'][0]), $eBase)) === false) {
					return ;
				}
				$eUri = $eBase[$eId];
				array_shift($uri['path']); // url to relative: eid/.. => ..
				if (count($uri['argv']) == 2 && is_numeric($uri['argv'][1])) {
					array_unshift($uri['argv'], 'view'); // => [view.]post.123
				}
				return array('livereporting_event#read_uri', array(
					'uri' => $uri,
					'tournament_id' => $tId,
					'event_id' => $eId
				));
			}
		}

		return ;
	}

	/** 
	 * Constructs url according to the component contract
	 * @param string $event Currently not used
	 * @param array $argv Contract-depended url argumants
	 * @param array $get _get parameters
	 * @return string Url
	 */
	public function makeUri($event = '', $argv = array(), $get = array())
	{
		foreach ($get as $k => $v) {
			$get[$k] = $k . '=' . $v;
		}
		return $this->makeUri_('', array(
			'event' => $event,
			'params' => $argv
		)) . (!empty($get)
			? '?' . implode('&amp;', $get)
			: '');
	}
	
	/** 
	 * Constructs url (including domain) according to the component contract
	 * @param string $origin Host prefix (_site_id_ or www)
	 * @param string $event Currently not used
	 * @param array $argv Contract-depended url argumants
	 * @param array $get _get parameters
	 * @return string Url
	 */
	public function makeAltUri($origin, $event = '', $argv = array(), $get = array())
	{
		$url = 'http://' . ($origin == 'com' ? 'www' : $origin) . '.pokernews.com';

		$this->cachedEnv();
		$oldRoot = $this->lrRoot;
		$this->lrRoot = 'live-reporting';
		$url .= $this->makeUri($event, $argv, $get);
		$this->lrRoot = $oldRoot;
		
		return $url;
	}
	
	/** 
	 * Constructs uri according to the component contract
	 * Does not include _get[] parameters
	 */
	private function makeUri_($event, $argv)
	{
		$this->cachedEnv();
		$argv['event'] = str_replace($this->myModuleStr, '', $argv['event']);

		switch ($argv['event']) {
			case 'index#view':
				return '/' . $this->lrRoot . '/';
			case 'index#miscjs':
				return '/' . rawurlencode($this->lrRoot) . '/ajax.batch.htm';
			case 'index#tour-archive':
				return '/' . $this->lrRoot . '/archive' . (isset($argv['params']['page'])
					? '.' . $argv['params']['page']
					: '') . '.htm';
			case 'rtf#preview':
				return '/' . rawurlencode($this->lrRoot) . '/rtf-preview.htm?id=' . intval($argv['params']);
		}

		if (!is_array($argv['params'])) {
			$eventParts = explode('#', $argv['event']);
			if ($eventParts[0] == 'rtf') {
				return '/' . rawurlencode($this->lrRoot) . '/rtf.' . (!empty($eventParts[1]) ? $eventParts[1] : '-') . '.' . (!empty($argv['params'])
					? $argv['params']
					: '-') . '.htm';
			}

			return '/' . $this->lrRoot . '/';
		}

		$url = '/';
		$tournamentId = NULL;
		$eventId = NULL;

		if (isset($argv['params']['event_id'])) {
			$eventId =  $argv['params']['event_id'];
			$tournamentId = isset($this->eBase[$eventId][0])
				? $this->eBase[$eventId][0]
				: NULL;
		} elseif (isset($argv['params']['tournament_id'])) {
			$tournamentId = $argv['params']['tournament_id'];
		}
		$tournamentId = isset($this->tBase[$tournamentId])
			? $tournamentId
			: NULL;
		$eventId = isset($this->eBase[$eventId])
			? $eventId
			: NULL;
		if (NULL !== $tournamentId) {
			/*if (substr($this->tBase[$tournamentId], 0 , 3) == '../') {
				$url .= str_replace('../', '', $this->tBase[$tournamentId]) . '/';
			} else {*/
			if (isset($argv['params']['ext']) && $argv['params']['ext'] != 'htm' || $argv['event'] == 'event#load') {
				$url .= rawurlencode($this->lrRoot) . '/' . $this->tBase[$tournamentId] . '/'; // variuos .js urls for IE
			} else {
				$url .= $this->lrRoot . '/' . $this->tBase[$tournamentId] . '/';
			} //}
			if (NULL !== $eventId) {
				$url .= $this->eBase[$eventId][1] . '/';
			}
			if (!empty($argv['params']['path'])) {
				$url .= $argv['params']['path'] . '/';
			}
			if (!empty($argv['params']['argv'])) {
				$url .= $argv['params']['argv'];
			}
		}

		switch ($argv['event']) {
			case 'event#edit':
				return $url . 'edit.' . $argv['params']['type'] . '.' . $argv['params']['id'] . '.htm';

			case 'event#save':
				return $url . 'save.' . $argv['params']['type'] . '.' . $argv['params']['id'] . '.htm';

			case 'event#delete':
				return $url . 'delete.' . $argv['params']['type'] . '.' . $argv['params']['id'] . '.htm';

			case ($argv['event'] == 'event#view' && isset($argv['params']['id'])):
				$ext = 'htm';
				if (isset($argv['params']['ext'])) {
					$ext = $argv['params']['ext'];
				}
				if (is_numeric($argv['params']['id'])) {
					return $url . $argv['params']['type'] . '.' . $argv['params']['id'] . '.' . $ext;
				} else {
					return $url . 'view.' . $argv['params']['type'] . '.' . $argv['params']['id'] . '.' . $ext;
				}

			case ($argv['event'] == 'event#view' && isset($argv['params']['leaf'])):
				if ($argv['params']['leaf'] != '') {
					return $url . $argv['params']['leaf'] . '.htm';
				}
				break;
				
			case ($argv['event'] == 'event#load' && isset($argv['params']['id'])):
				return $url . 'load.' . $argv['params']['type'] . '.' . $argv['params']['id'] . '.js';

			case 'event#ipn-upload':
				return $url . 'ipn-upload.htm';

			case 'event#ipn-browse':
				return $url . 'ipn-browse.htm';

			case 'event#ipn-review':
				return $url . 'ipn-review.htm';
		}

		return $url;
	}

	/**
	 * Helper to save a bit on execution of slower methods.
	 * e.g. get_var is slow
	 * @var bool
	 */
	private $isCachedEnv = false;
	private function cachedEnv()
	{
		if ($this->isCachedEnv) {
			return ;
		}
		$this->isCachedEnv = true;
		$this->page = &moon::page();
		$this->myModuleStr = $this->my('module') . '.';
		preg_match('~^[/]?(.+?)[/]?$~', $this->get_var('root'), $this->lrRoot);
		if (isset ($this->lrRoot[1])) {
			$this->lrRoot = $this->lrRoot[1];
		} else {
			$this->lrRoot = 'live-reporting';
		}
		$this->tBase = $this->instHierarchyModel()->getTorunamentsUris();
		$this->eBase = $this->instHierarchyModel()->getEventsUris();
	}

	/**
	 * Start profiling timer
	 */
	public function startTimer() {
		$profile = (isset($this->profiler));
		if ($profile) {
			$this->profiler->startTimer(Profiler::Page);
		}
	}

	/**
	 * Stop profiling timer
	 * @param string $comment Timer label
	 */
	public function stopTimer($comment = '') {
		$profile = (isset($this->profiler));
		if ($profile) {
			return $this->profiler->stopTimer(Profiler::Page, $comment);
		}
	}

	public function altLog($trnId = 0, $evId = 0, $dayId = 0, $type = 'other', $table = 'other', $id = '0', $comment = '', $userId = NULL)
	{
		static $currentUserId;
		if (!isset($currentUserId)) {
			$currentUserId = moon::user()->id();
		}
		if (0) {
			return ;
		}
		$userId = $userId == NULL
			? $currentUserId
			: intval($userId); // null => 0
		$this->db->query('
			INSERT INTO reporting_ng_alt_log
			(trn_id, ev_id, day_id, type, performed_by, object_table, object_id, comment)
			VALUES(
				"' . $this->db->escape($trnId) . '",
				"' . $this->db->escape($evId) . '",
				"' . $this->db->escape($dayId) . '",
				"' . $this->db->escape($type) . '",
				"' . $this->db->escape($userId) . '",
				"' . $this->db->escape($table) . '",
				"' . $this->db->escape($id) . '",
				"' . $this->db->escape($comment) . '"
			)
		');
	}
}
