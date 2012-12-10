<?php
/**
 * @package livereporting
 */
/**
 * @package livereporting
 * Controller mostly for /live-reporting/[*.(htm|js)] and xml inclusions
 */
class livereporting_index extends moon_com
{
	/* @var $lrep livereporting */
	/* @var $lrepTournament livereporting_tournament */
	function events($event, $argv)
	{
		switch ($event) {
			case 'read_uri':
				if (!isset($argv['uri'])) {
					$page = &moon::page();
					$page->page404();
				}
				$uri = $argv['uri'];
				break;
		}
		
		if (isset($uri['argv'][0])) {
			switch ($uri['argv'][0]) {
				case 'archive':
					$this->set_var('render', 'tour-archive');
					$this->set_var('page', isset($argv['uri']['argv'][1])
						? intval(($argv['uri']['argv'][1]))
						: 1);
					break;
				case 'ajax':
					$this->forget();
					$this->eAjax($argv);
					moon::page()->page404(); // on success, should have exited already
				case 'export':
					$argv['more'] = isset($_GET['more']);
					$this->eExport($argv);
					moon::page()->page404(); // on success, should have exited already
				case 'rtf':
					$this->forget();
					array_shift($argv['uri']['argv']);
					$event_ = array_shift($argv['uri']['argv']);
					$obj = $this->object('rtf');
					$obj->events($event_, $argv['uri']['argv']);
					return ;
				case 'rtf-preview':
					$this->forget();
					echo $this->previewText(isset($_GET['id']) ? $_GET['id'] : NULL, $_POST['body']);
					moon_close();
					exit;
				default:
					moon::page()->page404();
			}
		} else {
			$this->set_var('render', 'index');
		}
		$this->use_page('LiveReporting2col');
	}

	function properties()
	{
		return array(
			'render' => NULL,
			'main404' => TRUE
		);
	}

	function main($argv)
	{
		$e = NULL;
		switch ($argv['render']) {
			case 'index':
				$output = $this->renderIndex($e);
				break;

			case 'tour-archive':
				$output = $this->renderTournamentsArchive($argv, $e);
				break;
			
			case 'index-widget':
				return $this->renderIndexWidget();

			case 'countdown-widget':
				return $this->renderCountDownWidget();

			case 'sidebar-live-widget':
				return $this->renderSidebarLiveWidget();

			default:
				$e = 'not_found';
				break;
		}

		switch ($e) {
			case NULL:
				return $output;

			default:
				if (!empty($argv['main404'])) {
					$page = &moon::page();
					$page->page404();
				}
		}
	}

	/**
	 * Ajax requests from various tournaments, redirected to single entry point
	 * @param <array> $argv 
	 */
	private function eAjax($argv)
	{
		switch($argv['uri']['argv'][1]) {
			case 'batch':
				$form = $this->form();
				$form->names(array(
					'liveupdate', 'liveupdate_day', 'liveupdate_evt', 'liveupdate_since', 'shoutbox', 'shoutbox_event_id'
				));
				$form->fill($_POST);
				$this->renderAjaxBatch($form->get_values());
				break;
		}
	}

	/**
	 * Export xml's of various tournaments, 
	 * e.g. /export.tournaments.xml
	 * Bluff xml's are redirected.
	 * @param <array> $argv
	 * @return type 
	 */
	private function eExport($argv)
	{
		if (empty($argv['uri']['argv'][1])) {
			return ;
		}
		if ($argv['uri']['argv'][0] == 'export') {
		switch($argv['uri']['argv'][1]) {
			case 'tournaments':
				$this->renderExportTournaments($argv['more']);
				break;
			case 'tournaments-homerun':
				$this->renderExportTEBundle();
				break;
			case 'events':
				if (isset ($argv['uri']['argv'][2])) {
					$this->renderExportEvents($argv['uri']['argv'][2]);
				}
				break;
			case 'winners':
				$this->renderExportWinners();
				break;
			case 'bluff': // redirect
			case 'wpt':   // redirect
				$src = $argv['uri']['argv'][1];
				if ($src == 'bluff')
					$eventId = $this->object('livereporting_bluff')->bluffEventId($argv['uri']['argv'][3], TRUE);
				if ($src == 'wpt')
					$eventId = $this->object('livereporting_bluff')->wptEventId($argv['uri']['argv'][3], TRUE);
				if (!$eventId)
					return ;

				if (!isset($argv['uri']['argv'][4])) {
					$dayId = $this->object('livereporting')->instEventModel('_src_index')
						->getDaysDefaultId($eventId);
				} elseif ($argv['uri']['argv'][4] != 'all') {
					$day  = $this->object('livereporting')->instEventModel('_src_index')
						->getDayData($eventId, $argv['uri']['argv'][4]);
					if (empty($day)) {
						return ;
					}
					$dayId = $day['id'];
				} else {
					$dayId = NULL;
				}
				$this->object('livereporting_event')->main(array(
					'render' => 'bluff-xml',
					'event_id' => $eventId,
					'day_id' => $dayId,
					'key' => $argv['uri']['argv'][2],
					'dist' => 'xml',
					'src' => $src
				));
				exit; // should have exited anyway
			case 'bluff-grouped': // redirect, do not bother with event and day ids
				$this->object('livereporting_event')->main(array(
					'render' => 'bluff-xml',
					'nocache' => 1,
					'tournament_id' => $argv['uri']['argv'][3],
					'key' => $argv['uri']['argv'][2],
					'dist' => 'xml',
					'src' => 'bluff'
				));
				exit;
			case 'bluff-root': // redirect, do not bother with event and day ids
			case 'wpt-root': // redirect, do not bother with event and day ids
				$src = str_replace('-root', '', $argv['uri']['argv'][1]);
				$this->object('livereporting_event')->main(array(
					'render' => 'bluff-xml',
					'nocache' => 1,
					'key' => $argv['uri']['argv'][2],
					'dist' => 'xml',
					'src' => $src
				));
				exit;
		}}
		return;
	}

	/**
	 * Log updates, shoutbox updates counts
	 */
	private function renderAjaxBatch($argv)
	{
		$result = array();

		if (!empty($argv['liveupdate'])) {
			$result['liveupd'] = $this->object('livereporting')->instEventModel('_src_index')
				->countUpdates($argv['liveupdate_evt'], $argv['liveupdate_day'], $argv['liveupdate_since']);
		}

		if (!empty($argv['shoutbox'])) {
			$shoutObj = $this->object('shoutbox');
			$result['shoutbox'] = $shoutObj->events('ajax-redirected', array( // may exit
				'event_id' => intval($argv['shoutbox_event_id'])
			));
		}

		Header('Content-type: text/javascript');
		echo json_encode($result);
		moon_close();
		exit;
	}

	private function renderExportTournaments($more)
	{
		$xml=new moon_xml_write;
		$xml->encoding('utf-8');
		$xml->open_xml();
		$xml->start_node('tournaments');

		$limit = $more
			? 10
			: 0;
		$lrep = $this->object('livereporting');
		$lrepTournament = $lrep->instTournamentModel('_src_index');

		$tournaments = array();

		$buff = $lrepTournament->getRunningTournaments(FALSE, TRUE);
		foreach ($buff as $tnm) {
			$tournaments[$tnm['id']] = array(
				'id' => $tnm['id'],
				'name' => $tnm['name'],
			);
			$limit--;
		}

		if ($limit > 0) {
			$buff = $lrepTournament->getUpcomingTournaments();
			foreach ($buff as $tnm) {
				$tournaments[$tnm['id']] = array(
					'id' => $tnm['id'],
					'name' => $tnm['name'],
				);
				$limit--;
			}
		}

		if ($limit > 0) {
			$buff = $lrepTournament->getArchivedTournaments('LIMIT ' . $limit);
			foreach ($buff as $tnm) {
				$tournaments[$tnm['id']] = array(
					'id' => $tnm['id'],
					'name' => $tnm['name'],
				);
			}
		}

		foreach ($tournaments as $tnm) {
			$xml->start_node('tournament');
			$xml->node('id', '', $tnm['id']);
			$xml->node('name', '', $tnm['name']);
			$xml->node('url', '', 'http://www.pokernews.com' . $lrep->makeUri('event#view', array(
					'tournament_id' => $tnm['id']
				)));
			$xml->end_node('tournament');
		}
		$xml->end_node('tournaments');

		Header('Content-Type: application/xml');
		echo $xml->close_xml();
		moon_close();
		exit;
	}
	
	private function renderExportTEBundle()
	{
		$xml=new moon_xml_write;
		$xml->encoding('utf-8');
		$xml->open_xml();
		$xml->start_node('tournaments');
		
		$tournaments = $this->object('livereporting')->instTournamentModel('_src_index')
			->getTournamentsEventsSyncTree();
		foreach ($tournaments as $tnm) {
			$xml->start_node('tournament');
			$xml->node('id', '', $tnm['id']);
			$xml->node('is_live', '', $tnm['is_live']);
			$xml->node('state', '', $tnm['state']);
			$xml->node('timezone', '', $tnm['timezone']);
			$xml->node('name', '', $tnm['name']);
			$xml->node('duration', '', $tnm['duration']);
			$xml->node('from_date', '', $tnm['from_date']);
			foreach ($tnm['events'] as $event) {
				$xml->start_node('event');
				$xml->node('id', ($event['is_main'] ? array('is_main' => '1') : ''), $event['id']);
				$xml->node('name', '', $event['name']);
				$xml->end_node('event');
			}
			$xml->end_node('tournament');
		}
		$xml->end_node('tournaments');

		Header('Content-Type: application/xml');
		echo $xml->close_xml();
		moon_close();
		exit;
	}
	
	private function renderExportEvents($tournamentId)
	{
		$lrep = $this->object('livereporting');
		$tournament = $lrep->instTournamentModel('_src_index')->getTournament($tournamentId);
		if (empty($tournament)) {
			return ;
		}

		$xml=new moon_xml_write;
		$xml->encoding('utf-8');
		$xml->open_xml();
		$xml->start_node('tournament');

		$xml->start_node('info');
		$xml->node('name', '', $tournament['name']);
		$xml->end_node('info');

		$events = $this->object('livereporting')->instTournamentModel('_src_index')
			->getExportEvents($tournamentId);
		$locale = &moon::locale();
		foreach ($events as $event) {
			$xml->start_node('event');
			$xml->node('id', '', $event['id']);
			$xml->node('name', '', $event['name']);
			$xml->node('url', '', 'http://www.pokernews.com' . $lrep->makeUri('event#view', array(
					'event_id' => $event['id']
				)));
			$xml->node('date', '', $lrep->instTools()->helperFancyDate($event['from_date'], $event['to_date'], $locale));
			$xml->end_node('event');
		}
		$xml->end_node('tournament');
		
		Header('Content-Type: application/xml');
		echo $xml->close_xml();
		moon_close();
		exit;
	}

	private function renderExportWinners()
	{
		$xml=new moon_xml_write;
		$xml->encoding('utf-8');
		$xml->open_xml();

		$winners = $this->object('livereporting')->instTournamentModel('_src_index')
			->getExportWinners();

		$xml->start_node('winners', array(
			'total' => count($winners)
		));

		foreach ($winners as $winner) {
			$xml->start_node('winner');
			foreach ($winner as $field => $value) {
				$xml->node($field, '', $value);
			}
			$xml->end_node('winner');
		}
		$xml->end_node('winners');
		
		Header('Content-Type: application/xml');
		echo $xml->close_xml();
		moon_close();
		exit;
	}

	/**
	 * Renders widget for *site* index page
	 * @return <string> 
	 */
	private function renderIndexWidget()
	{
		$tpl = $this->load_template();
		$lrep = $this->object('livereporting');
		$lrepTools = $lrep->instTools();
		
		$running = $lrep->instTournamentModel('_src_index')->getRunningTournaments(TRUE, FALSE, FALSE, FALSE);
		if (empty($running)) {
			return ;
		}

		$tours = poker_tours();
		$skins = $this->get_var('skins');
		$result = '';

		$newsCountVar = array(
			'tournaments' => 0,
			'events' => 0,
		);
		
		// {{ wsop11
		/*
		$runningSpecial = array();
		foreach ($running as $k => $tournament) {
			if ($tournament['id'] == '194' || strpos($tournament['sync_id'], 'com:194') !== FALSE) {
				$runningSpecial = array($tournament);
				unset($running[$k]);
				break;
			}
		}
		if (0 != count($runningSpecial) && 0 != count($runningSpecial[0]['running_events'])) {
			$result .= $tpl->parse('widget_index:main_wsop11', array(
				'tournaments' => $this->helperRenderIndexWidgetTournaments($runningSpecial, $tours, $skins, $lrep, $lrepTools, $tpl, $newsCountVar)
			));
		}
		*/
		// }}

		if (0 != count($running)) {
			$result .= $tpl->parse('widget_index:main', array(
				'tournaments' => $this->helperRenderIndexWidgetTournaments($running, $tours, $skins, $lrep, $lrepTools, $tpl, $newsCountVar),
				'url.reporting' => $lrep->makeUri('index#view'),
			));
		}
		
		moon::page()->set_local('reporting-news-widget', $newsCountVar);
		return $result;
	}

	private function helperRenderIndexWidgetTournaments($running, $tours, $skins, $lrep, $lrepTools, $tpl, &$newsCountVar)
	{
		$result = '';
		$nr = 0;
		foreach ($running as $tournament) {
			$newsCountVar['tournaments']++;
			$rtArgv = array(
				'name' => htmlspecialchars($tournament['name']),
				'url' => $lrep->makeUri('#', array(
					'tournament_id' => $tournament['id'],
				)),
				'live_coverage' => $tournament['livecov'],
				'tour' => isset($tours[$tournament['tour']])
					? htmlspecialchars($tours[$tournament['tour']]['title'])
					: '',
				'index' => $nr == 0
					? ' first '
					: '',
				'events' => '',
			);
			if (count($tournament['running_events']) == 1) {
				$firstEvent = reset($tournament['running_events']);
				$rtArgv['url'] = $lrep->makeUri('event#view', array(
					'event_id' => $firstEvent['id']
				));
			}
			$nr++;
			$tournament['logo'] = $tournament['logo_idx'];
			$rtArgv += $lrepTools->helperGetLogosSkins($tournament, $skins, $tours, array(
				'skin5LogoSuffix' => 'idx',
				'skinNLogoSuffix' => 'idx',
				'logoDir' => $this->get_dir('web:LogosIdx')
			));
			foreach ($tournament['running_events'] as $event) {
				$rtArgv['events'] .= $tpl->parse('widget_index:events.item', array(
					'name' => htmlspecialchars($event['name']),
					'url' => $lrep->makeUri('event#view', array(
						'event_id' => $event['id']
					))
				));
			}
			$result .= $tpl->parse('widget_index:tournaments.item', $rtArgv);
		}
		
		return $result;
	}

	private function renderCountDownWidget()
	{
		$tpl = $this->load_template();
		$tplArgv = array(
			'list' => '',
			'list.live' => '',
		);
		$lrep = $this->object('livereporting');
		$lrepTools = $lrep->instTools();
		$locale = moon::locale();

		$skins = $this->get_var('skins');
		$tours = poker_tours();

		foreach ($lrep->instTournamentModel('_src_index')->getUpcomingTournaments(1) as $tournament) {
			$tournament['logo'] = $tournament['logo_small'];
			// if (empty($tournament['logo']))
			// 	continue;
			$rowArgv = $lrepTools->helperGetLogosSkins($tournament, $skins, $tours, array(
				'skin5LogoSuffix' => '-small',
				'skinNLogoSuffix' => 'thumb',
				'logoDir' => $this->get_dir('web:LogosSmall')
			));
			if (!isset($rowArgv['logo']))
				continue; 
			list($tzOffset, ) = $locale->timezone($tournament['timezone']);

			$tplArgv['list'] .= $tpl->parse('widget_countdown:item', array(
				'url' => $lrep->makeUri('#', array(
					'tournament_id' => $tournament['id'],
				)),
				'bg' => $rowArgv['logo'],
				'days' => max(0, floor(($tournament['from_date'] + $tzOffset + 86400 - time()) / 86400))
			));
		}

		foreach ($lrep->instTournamentModel('_src_index')->getRunningTournaments(FALSE, FALSE, FALSE) as $tournament) {
			$tournament['logo'] = $tournament['logo_small'];
			$rowArgv = $lrepTools->helperGetLogosSkins($tournament, $skins, $tours, array(
				'skin5LogoSuffix' => '-small',
				'skinNLogoSuffix' => 'thumb',
				'logoDir' => $this->get_dir('web:LogosSmall')
			));
			if (!isset($rowArgv['logo']))
				continue; 
			$tplArgv['list'] .= $tpl->parse('widget_countdown:item.end', array(
				'url' => $lrep->makeUri('#', array(
					'tournament_id' => $tournament['id'],
				)),
				'bg' => $rowArgv['logo']
			));
		}

		return $tpl->parse('widget_countdown:main', $tplArgv);
	}
	
	/**
	 * Renders live tournaments in a small column sidebox. 
	 * Currently called by xml file. Set `hideSLW` var to hide this.
	 * @return <string> 
	 */
	private function renderSidebarLiveWidget()
	{
		$page = &moon::page();
		if ($page->get_local('hideSLW')) {
			return ;
		}
		$tpl = $this->load_template();
		$lrep = $this->object('livereporting');
		$running = $lrep->instTournamentModel('_src_index')->getRunningTournaments(TRUE, FALSE, FALSE, FALSE);
		if (empty($running)) {
			return ;
		}

		foreach ($running as $k => $tournament) {
			/*if ($running[$k]['livecov'] != '1') {
				return ;
			}*/
			$running[$k]['events'] = array();
		}

		$tours = poker_tours();
		$result = '';

		$nr = 0;
		foreach ($running as $tournament) {
			$runningEventsString = '';
			foreach ($tournament['running_events'] as $event) {
				$resTpl = array(
					'evname' => htmlspecialchars($event['name']),
					'url' => $lrep->makeUri('#', array(
						'event_id' => $event['id'],
					))
				);
				$runningEventsString .= $tpl->parse('widget_live:running_events.item', $resTpl);
			}
			$rtArgv = array(
				'name' => htmlspecialchars($tournament['name']),
				'running_events' => $runningEventsString,
			);
			$nr++;
			$result .= $tpl->parse('widget_live:tournaments.item', $rtArgv);
		}
		return $tpl->parse('widget_live:main', array(
			'tournaments' => $result
		));
	}

	/**
	 * Reporting index
	 * @param <mixed> $e
	 * @return <string>
	 */
	private function renderIndex(&$e)
	{
		$page = &moon::page();
		$locale = &moon::locale();
		$tpl  = $this->load_template();
		$t9n  = $tpl->parse_array('index:meta');
		$text   = &moon::shared('text');
		$lrep = $this->object('livereporting');
		$lrepTournament = $lrep->instTournamentModel('_src_index');
		
		$page->title($t9n['live_reporting']);
		$page->css('/css/live_poker.css');
		$page->set_local('hideSLW', TRUE);

		$mainArgv = array(
			'running_tournaments' => '',
			'upcoming_tournaments' => '',
			'tours' => '',
			'intro' => ''
		);

		$pageData = moon::shared('sitemap')->getPage();
		if (!empty($pageData)) {
			$mainArgv['intro'] = $pageData['content_html'];
		}

		$tournamentsArchive = $tpl->parse('index:tournaments_archive', array(
			'url' => $lrep->makeUri('index#tour-archive')
		));

		$running =  $lrepTournament->getRunningTournaments();
		$upcoming = $lrepTournament->getUpcomingTournaments();
		$events =   $lrepTournament->helperGetUpcomingEvents(array_merge(
			array_keys($running),
			array_keys($upcoming)
		));
		foreach ($running as $k => $tournament) {
			$running[$k]['events'] = array();
		}
		foreach ($upcoming as $k => $tournament) {
			$upcoming[$k]['events'] = array();
		}
		foreach ($events as $event) {
			if (isset($running[$event['tid']])) {
				$running[$event['tid']]['events'][] = $event;
			} elseif (isset($upcoming[$event['tid']])) {
				$upcoming[$event['tid']]['events'][] = $event;
			}
		}

		$tours = poker_tours();
		$skins = $this->get_var('skins');

		foreach ($running as $tournament) {
			list($tzOffset, $tzName) = $locale->timezone($tournament['timezone']);
			$runningEventsString = '';
			foreach ($tournament['running_events'] as $event) {
				$playersLeft = '';
				if (intval($event['players_left']) == 0) {
					if (intval($event['players_total']) != 0) {
						$playersLeft = intval($event['players_total']);
					}
				} else {
					$playersLeft = intval($event['players_left']);
				}
				if ($playersLeft != 0 && intval($event['chipspool']) != 0) {
					$avgStack = number_format(round($event['chipspool'] / $playersLeft));
				} else {
					$avgStack = '';
				}
				$resTpl = array(
					'players_left' => $playersLeft,
					'average_chip_stack' => sprintf('%s', $avgStack),
					'evname' => htmlspecialchars($event['name']),
					'dname' => htmlspecialchars($event['dname'])
				);
				if ($event['post'] != null) {
					$resTpl['clickable'] = true;
					$resTpl['url'] = $lrep->makeUri('event#view', array(
						'event_id' => $event['id']
					));
					$resTpl['dark_background'] = $tournament['logo_is_dark'];
					$data = unserialize($event['post']['contents']);
					$resTpl['post_url'] = $lrep->makeUri('event#view', array(
						'event_id' => $event['id'],
						'type' => $event['post']['type'],
						'id' => $event['post']['id']
					));
					$resTpl['spec_url'] = $resTpl['url'] . '#' . $event['post']['type'] . '-' . $event['post']['id'];
					$data['contents'] = preg_replace('~<table.*?</table>~', '', $data['contents']);
					$data['contents'] = preg_replace('~{poll:[0-9]+}~', '', $data['contents']);
					$resTpl['post'] = $lrep->instTools()->helperHtmlExcerpt($data['contents'], 400, 3, '...', false, 2);
					if (FALSE !== strpos($resTpl['post'], 'BrightcoveExperience')) $page->js('http://admin.brightcove.com/js/BrightcoveExperiences.js');
					if (isset($data['xphotos'])) {
						$resTpl['list.photos'] = '';
						$page->css('/css/jquery/lightbox-0.5.css');
						$page->js('/js/jquery/lightbox-0.5.js');
						$page->js('/js/live-poker.js');
						$ipnReadBase = $this->get_var('ipnReadBase');
						$data['xphotos'] = array_slice($data['xphotos'], 0, 6);
						foreach ($data['xphotos'] as $photo) {
							$photo['src_big'] = $photo['src'];
							$photo['src_big'][strlen($photo['src_big']) - 15] = 'm';
							$resTpl['list.photos'] .= $tpl->parse('logEntry:photos.item', array(
								'src' =>  $ipnReadBase . $photo['src'],
								'src_big' =>  $ipnReadBase . $photo['src_big'],
								'event_name' => htmlspecialchars($event['name']),
								'tournament_name' => htmlspecialchars($tournament['name']),
								'alt' => htmlspecialchars($photo['title'])
							));
						}
					}
					$resTpl['post_title'] = htmlspecialchars($data['title']);
					$resTpl['post_ago'] =  $text->ago($event['post']['created_on']);
					$resTpl['post_date'] = $locale->gmdatef($event['post']['created_on'] + $tzOffset, 'Reporting') . ' ' . $tzName;
				}
				$runningEventsString .= $tpl->parse('index:running_tournaments.post_by_event.item', $resTpl);
			}
			$upcomingEventsString = '';
			foreach ($tournament['events'] as $k => $event) {
				$upcomingEventsString .= $tpl->parse('index:running_tournaments.upcoming_events.item', array(
						'name' => htmlspecialchars($event['name']),
						'dateFrom' => ($event['to_date'] - $event['from_date']) > 3600*24
							? $locale->gmdatef($event['from_date'] + $tzOffset, 'liveRepoShort')
							: $locale->gmdatef($event['from_date'] + $tzOffset, 'liveRepoLong'),
						'dateTo' => ($event['to_date'] - $event['from_date']) > 3600*24
							? $locale->gmdatef($event['to_date'] + $tzOffset, 'liveRepoShort')
							: '',
						'strange' => ($k % 2) == 0
					));
			}
			$rtArgv = array(
				'name' => htmlspecialchars($tournament['name']),
				'url' => $lrep->makeUri('#', array(
					'tournament_id' => $tournament['id'],
				)),
				'uriClass' => 'class_'.$lrep->tBase[$tournament['id']],
				'live_coverage' => $tournament['livecov'],
				'tour' => isset($tours[$tournament['tour']])
					? htmlspecialchars($tours[$tournament['tour']]['title'])
					: '',
				'running_events' => $runningEventsString,
				'upcoming_events' => $upcomingEventsString
			);
			$tournament['logo'] = $tournament['bg'];
			$rtArgv += $lrep->instTools()->helperGetLogosSkins($tournament, $skins, $tours, array(
				'skin5LogoSuffix' => 'def',
				'skinNLogoSuffix' => 'bg',
				'logoDir' => $this->get_dir('web:LogosBigBg')
			));
			$mainArgv['running_tournaments'] .= $tpl->parse('index:running_tournaments.item', $rtArgv);
		}

		if (0 == count($upcoming)) {
			$mainArgv['tournaments_archive'] = $tournamentsArchive;
		} else {
			$last = count($upcoming) - 1;
			$curr = 0;
			foreach ($upcoming as $tournament) {
				$upcomingEventsString = '';
				list($tzOffset, $tzName) = $locale->timezone($tournament['timezone']);
				foreach ($tournament['events'] as $event) {
					$upcomingEventsString .= $tpl->parse('index:upcoming_tournaments.events.item', array(
						'name' => htmlspecialchars($event['name']),
						'dateFrom' => ($event['to_date'] - $event['from_date']) > 3600*24
							? $locale->gmdatef($event['from_date'] + $tzOffset, 'liveRepoShort')
							: $locale->gmdatef($event['from_date'] + $tzOffset, 'liveRepoLong'),
						'dateTo' => ($event['to_date'] - $event['from_date']) > 3600*24
							? $locale->gmdatef($event['to_date'] - $tzOffset, 'liveRepoShort')
							: ''
					));
				}
				$utArgv = array(
					'name' => htmlspecialchars($tournament['name']),
					'url' => $lrep->makeUri('#', array(
						'tournament_id' => $tournament['id'],
					)),
					'tour' => isset($tours[$tournament['tour']])
						? htmlspecialchars($tours[$tournament['tour']]['title'])
						: '',
					'events' => $upcomingEventsString,
					'tournaments_archive' => $last == $curr
						? $tournamentsArchive
						: NULL,
				);
				$utArgv += $lrep->instTools()->helperGetLogosSkins($tournament, $skins, $tours, array(
					'skin5LogoSuffix' => '',
					'skinNLogoSuffix' => 'mid',
					'logoDir' => $this->get_dir('web:LogosMid')
				));
				$mainArgv['upcoming_tournaments'] .= $tpl->parse('index:upcoming_tournaments.item', $utArgv);
				$curr++;
			}
		}
		$mainArgv['tours'] = $this->object('livereporting_tour')->main(array(
			'render' => 'partial-render-tour',
			'running' => $running
		));
		
		return $tpl->parse('index:main', $mainArgv);
	}

	private function renderTournamentsArchive($argv, &$e)
	{
		$page = &moon::page();
		$tpl  = $this->load_template();
		$t9n  = $tpl->parse_array('index:meta');
		$lrep = $this->object('livereporting');
		$lrepTools = $lrep->instTools();
		$lrepTournament = $lrep->instTournamentModel('_src_index');

		$page->title($t9n['live_reporting']);
		$page->css('/css/live_poker.css');
		$sitemap = & moon :: shared('sitemap');
		$sitemap->breadcrumb(array(
				$lrep->makeUri('index#tour-archive') => $t9n['tournaments_archive']));

		$mainArgv = array(
			'tournaments' => '',
			'url.up' => $lrep->makeUri('index#view')
		);

		$paginator  = &moon::shared('paginate');
		$paginator->set_curent_all_limit(
			$argv['page'],
			$lrepTournament->getArchivedTournamentsCount(),
			10
		);

		$paginator->set_url(
			$lrep->makeUri('index#tour-archive', array(
				'page' => '{pg}'
			)),
			$lrep->makeUri('index#tour-archive')
		);
		$paginatorInfo = $paginator->get_info();

		$mainArgv['paginator'] = $paginator->show_nav();

		$tree = $lrepTournament->getArchivedTournaments($paginatorInfo['sqllimit']);
		$skins = $this->get_var('skins');
		$tours = poker_tours();

		foreach($tree as $tournament) {
			$eventsStr = '';
			foreach ($tournament['events'] as $event) {
				$eventsStr .= $tpl->parse('tournaments-archive:tournaments.events.item', array(
					'name' => htmlspecialchars($event['name']),
					'url' => $lrep->makeUri('event#view', array(
						'event_id' => $event['id']
					)),
					'show_url' => ($event['is_empty'] ? 0 : 1) || $page->get_global('adminView')
				));
			}
			$taArgv = array(
				'name' => htmlspecialchars($tournament['name']),
				'tour' => '',
				'url' => $lrep->makeUri('event#view', array(
					'tournament_id' => $tournament['id']
				)),
				'events' => $eventsStr
			);
			$taArgv += $lrepTools->helperGetLogosSkins($tournament, $skins, $tours, array(
				'skin5LogoSuffix' => '-small',
				'skinNLogoSuffix' => 'thumb',
				'logoDir' => $this->get_dir('web:LogosSmall')
			));
			$mainArgv['tournaments'] .= $tpl->parse('tournaments-archive:tournaments.item', $taArgv);
		}

		return $tpl->parse('tournaments-archive:main', $mainArgv);
	}
	
	/**
	 * Sets up image.pokernews.com session
	 * @return <null> Redirects
	 */
	private function redirectIPNNews()
	{
		$page = moon::page();
		$user = moon::user();

		if (!$user->i_admin()) {
			$page->page404();
		}

		$sid  = $page->get_global($this->my('module') . '.livereporting_event_ipnSid');
		if (!is_array($sid)) {
			$sid = array();
		}
		foreach ($sid as $k => $v) {
			if ($sid[$k][1] < time() - 1800) {
				unset($sid[$k]);
			}
		}

		$error = false;
		$key  = 'news';
		if (empty($sid[$key])) {
			$sendData = array(
				'ns' => 'lrep',
				'src' => _SITE_ID_,
				'user' => $user->get_user_id(),
				'user_nick' => $user->get_user('nick'),
				'time' => time()
			);

			$sendData = serialize($sendData);
			openssl_sign($sendData, $signature, $this->get_var('pnewsIpnPubKey'));

			$ch = curl_init($this->get_var('ipnWriteBase') . $this->get_var('ipnLoginUrl'));
			curl_setopt($ch, CURLOPT_HEADER, 0);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_TIMEOUT, 30);
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, array(
				'event' => 'core.login#remotelogin',
				'data' => $sendData,
				'signature' => base64_encode($signature)
			));
			curl_setopt($ch, CURLOPT_FAILONERROR, 1);
			$rawData = curl_exec($ch);
			curl_close($ch);

			$gotData = @unserialize($rawData);

			if (is_array($gotData)) {
				$sid[$key] = array(
					$gotData['sid'],
					time()
				);
			} else {
				$error = true;
			}
		}
		
		$page->set_global($this->my('module') . '.livereporting_event_ipnSid', $sid);

		if (!empty($sid[$key]) && !$error) {
			moon::error('redirectIPNNews: no $sid');
			// @no way to check if logged in -- reswitch [A] instead
			$page->redirect($this->get_var('ipnWriteBase') . $this->get_var('ipnBrowseNewsUrl') . '?sid=' . $sid[$key][0] . '&key=');
		} else {
			$page->page404();
		}

		moon_close();
		exit;
	}

	private function previewText($id, $text)
	{
		$rtf = $this->object('rtf');
		$rtf->setInstance($this->get_var('rtf') . '-post:0');
		list(,$data['body_compiled']) = $rtf->parseText($id, $text);
		return $data['body_compiled'] . ' ';
	}
}