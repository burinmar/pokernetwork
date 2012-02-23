<?php
/**
 * @package livereporting
 */
/**
 * Controller for tours pages
 * @package livereporting
 */
class livereporting_tour extends moon_com
{
	/* @var $lrep livereporting */
	function events($event, $argv)
	{
		$tours = poker_tours();
		if (is_numeric($event) && isset($tours[$id = $event])) {
			//audrius. kad galima būtų meniu įdėti Live poker->WSOP
			$id = $event;
		} elseif (empty($argv[0]) || !isset($tours[$id = $argv[0]])) {
			$page = & moon :: page();
			$page->page404();
		}
		$this->set_var('tourId', $id);
		$this->set_var('tour', $tours[$id]);
		$this->set_var('render', 'tour');

		$this->use_page('LiveReporting2col');
	}

	function properties()
	{
		return array(
			'tournament_id' => NULL,
			'render' => NULL,
			'main404' => TRUE
		);
	}

	function main($argv)
	{
		$e = NULL;
		switch ($argv['render']) {
			case 'tour':
				$output = $this->renderTour($argv, $e);
				break;
			
			case 'partial-render-tour':
				return $this->partialRenderTours(@$argv['running']);

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
	 * notes:
	 * poker players country flag not available
	 * news tag - url (titleShort)
	 */
	private function renderTour($argv, &$e)
	{
		$page = &moon::page();
		$tpl  = $this->load_template();
		$t9n  = $tpl->parse_array('tour:t9n');
		$lrep = $this->object('livereporting');
		$lrepTools = $lrep->instTools();
		
		$page->css('/css/live_poker.css');
		
		$tourData = $lrep->instTourModel('_src_tour')->getTour($argv['tourId']);
		$tourData = array_merge($argv['tour'], (array)$tourData);
		$tourData['titleShort'] = $tourData['uri'];
		
		if (!isset($tourData['meta_title'])) {
			$tourData['meta_title'] = $tourData['title'];
		}
		if (!empty($argv['tour']['title_pre'])) {
			$page->title($argv['tour']['title_pre'] . ' ' . $t9n['live_reporting']. ' | ' . $tourData['meta_title']);
		} elseif (!empty($tourData['meta_title'])) {
			$page->title($t9n['live_reporting'] . ' | ' . $tourData['meta_title']);
		} else {
			$page->title($t9n['live_reporting']);
		}
		if (isset($tourData['meta_keywords'])) {
			$page->meta('keywords', $tourData['meta_keywords']);
			$page->meta('description', $tourData['meta_description']);
		}
		
		$sitemap = & moon :: shared('sitemap');
		$sitemap->breadcrumb(array(
				'' => $tourData['title']));		
		
		// tournaments summary
		list($tournamentsInGroup, $players) = $lrep->instTourModel('_src_tour')->getTournamentsSummary($argv['tourId']);
	
		$scheduledTournaments = '';
		$finishedTournaments = '';
		$i = 1;
		$j = 1;
		$playersUrl = $this->object('players.poker')->linkas('#');
		foreach ($tournamentsInGroup as $t) {			
			$tournamentArgv = array(
				'url.tournament' => $lrep->makeUri('#', array(
					'tournament_id' => $t['id']
				)),
				'title' => htmlspecialchars($t['name']),
				'date' => htmlspecialchars($t['duration'])
			);
			
			if ($t['state'] == 2) { // finished main event
				$winnerData = (!empty($players[$t['winner']])) ? $players[$t['winner']] : array();
				$runnerupData = (!empty($players[$t['runner_up']])) ? $players[$t['runner_up']] : array();
				$finishedTournaments .= $tpl->parse('tour:finished_tournaments.item', $tournamentArgv + array(
					'class' => ($i++%2) ? 'odd' : 'even',
					'winnerPrize' => ($t['prize']) ? $lrepTools->helperCurrencyWrite(number_format($t['prize']), $t['currency']) : '',
					'winner' => htmlspecialchars($t['winner']),
					'winningHand' => $lrepTools->helperFancyCards($t['winning_hand'], $tpl),
					'winnerImg' => (!empty($winnerData['img'])) 
						? img('player',  $winnerData['id']  . '-' . $winnerData['img'] )
						: '',
					'url.winner' => (!empty($winnerData['uri'])) 
						? $playersUrl . $winnerData['uri'] . '/' 
						: '',
					'runnerUp' => htmlspecialchars($t['runner_up']),
					'losingHand' => $lrepTools->helperFancyCards($t['losing_hand'], $tpl),
					'url.runnerup' => (!empty($runnerupData['uri'])) 
						? $playersUrl . $runnerupData['uri'] . '/' 
						: '',
				));
			} else { // live or upcoming main event
				$venue = array();
				if (!empty($t['place'])) {
					$venue[] = htmlspecialchars($t['place']);
				}
				if (!empty($t['address'])) {
					$venue[] = htmlspecialchars($t['address']);
				}
				$scheduledTournaments .= $tpl->parse('tour:scheduled_tournaments.item', $tournamentArgv + array(
					'venue' => implode(', ', $venue),
					'class' => ($j++%2) ? 'odd' : 'even',
					'isLive' => $t['state'] == 1 ? 1 : 0
				));
			}
		}
		
		// related articles (by tag)
		$articlesObject = $this->object('articles.shared');
		$articlesObject->articleType($articlesObject->typeNews);
		
		$mainArgv = array(
			'url.moreNews' => !empty($tourData['news_tag'])
				? $articlesObject->getTagUrl($tourData['news_tag'])
				: '',
			'tours' => $this->partialRenderTours(),
			'scheduled_tournaments' => $scheduledTournaments,
			'finished_tournaments' => $finishedTournaments,
		) + $tourData;
		return $tpl->parse('tour:main', $mainArgv);
	}
	
	/**
	 * Small tour list, with active marked.
	 * 
	 * Called from a number of pages
	 * @param <array> $tours List of tours
	 * @param <array> $running List of running tournaments
	 * @return <array>
	 */
	private function partialRenderTours($running = NULL)
	{
		$tpl = $this->load_template();
		$lrep = $this->object('livereporting');
		
		if (NULL == $running) {
			$running = $lrep->instTournamentModel('_src_tour')->getRunningTournaments(FALSE, FALSE);
		}
		$tours = $lrep->instTourModel('_src_tour')->getTours();
		
		$toursList = '';
		foreach ($running as $tournament) {
			if (isset($tours[$tournament['tour']]) && $tournament['livecov']) {
				$tours[$tournament['tour']]['live'] = 1;
			}
		}
		foreach ($tours as $tour) {
			$toursList .= $tpl->parse('partial:tours.item', array(
				'name' => htmlspecialchars($tour['title']),
				'url' => '/' . $tour['uri'] . '/',
				'logo' => $tour['img1'],
				'is_live' => !empty($tour['live'])
			));
		}
		return $toursList;
	}	
}