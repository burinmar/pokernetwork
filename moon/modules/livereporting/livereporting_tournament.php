<?php
/**
 * @package livereporting
 */
/**
 * Controller for tournament page
 * @package livereporting
 */
class livereporting_tournament extends moon_com
{
	/* @var $lrep livereporting */
	function events($event, $argv)
	{
		if ($event != 'read_uri') {
			$page = &moon::page();
			$page->page404();
		}

		$this->set_var('tournament_id', $argv['tournament_id']);
		$this->set_var('render', 'tournament');

		$this->use_page('LiveReporting2col');
	}

	function properties()
	{
		return array(
			'tournament_id' => NULL,
			'render' => NULL
		);
	}

	function main($argv)
	{
		$e = NULL;
		switch ($argv['render']) {
			case 'tournament':
				$output = $this->renderTournament($argv, $e);
				break;

			default:
				$e = 'not_found';
				break;
		}

		switch ($e) {
			case NULL:
				return $output;

			default:
				$page = &moon::page();
				$page->page404();
		}
	}

	private function renderTournament($argv, &$e)
	{
		$page = &moon::page();
		$tpl  = $this->load_template();
		$t9n  = $tpl->parse_array('tour:t9n');
		$locale = &moon::locale();
		$lrep = $this->object('livereporting');
		$lrepTools = $lrep->instTools();
		$skins = $this->get_var('skins');
		$tours = poker_tours();
		$lrepTournament = $lrep->instTournamentModel('_src_tournament');
		$playersUrl = $this->object('players.poker')->linkas('#');

		$tournamentInfo = $lrepTournament->getTournament($argv['tournament_id']);
		if (NULL == $tournamentInfo) {
			$page->page404();
		}

		$page->css('/css/live_poker.css');
		$page->title($t9n['live_reporting'] . ' | ' . $tournamentInfo['name']);
		if ($tournamentInfo['autopublish']) {
			$page->head_link($lrep->makeAltUri($tournamentInfo['sync_origin'], '#', array(
				'tournament_id' => $argv['tournament_id']
			)), 'canonical', '');
		}
		
		$sitemap = & moon :: shared('sitemap');
		$sitemap->breadcrumb(array(
				$lrep->makeUri('#', array(
					'tournament_id' => $argv['tournament_id']
				)) => $tournamentInfo['name']));

		list($tzOffset) = $locale->timezone($tournamentInfo['timezone']);

		$this->bannerRoomAssign($tournamentInfo['ad_rooms']);

		$rtf = $this->object('rtf');
		list(, $tournamentInfo['intro']) = $rtf->parseText(0, $tournamentInfo['intro']);
		$mainArgv = array(
			'title' => htmlspecialchars($tournamentInfo['name']),
			'name' => htmlspecialchars($tournamentInfo['name']),
			'tour' => isset($tours[$tournamentInfo['tour']])
					? htmlspecialchars($tours[$tournamentInfo['tour']]['title'])
					: '',
			'intro' => $tournamentInfo['intro'],
			'tours' => $this->object('livereporting_tour')->main(array(
				'render' => 'partial-render-tour'
			)),
			'scheduled_events' => '',
			'finished_events' => '',
			'currency' => $tournamentInfo['currency']
		) + $lrepTools->helperGetLogosSkins($tournamentInfo, $skins, $tours,array(
			'skin5LogoSuffix' => '',
			'skinNLogoSuffix' => 'mid',
			'logoDir' => $this->get_dir('web:LogosMid')
		));

		$scheduledRunningEvents = $lrepTournament->getScheduledAndRunningEvents($argv['tournament_id']);
		$j = 0;
		foreach ($scheduledRunningEvents as $event) {
			$mainArgv['scheduled_events'] .= $tpl->parse('tour:scheduled_events.item', array(
				'class' => ($j++%2) ? 'odd' : 'even',
				'isLive' => $event['state'] == 1 ? 1 : 0,
				'date' => $lrepTools->helperFancyDate($event['from_date'] + $tzOffset, $event['to_date'] == NULL ? NULL : $event['to_date'] + $tzOffset, $locale),
				'name' => htmlspecialchars($event['name']),
				'url' => $lrep->makeUri('event#view', array(
					'event_id' => $event['id']
				)),
				'show_url' => ($event['state'] == 1 ? 1 : 0) || $page->get_global('adminView')
			));
		}

		$finishedEvents = $lrepTournament->getFinishedEvents($argv['tournament_id']);
		$j = 0;
		foreach ($finishedEvents as $event) {
			$mainArgv['finished_events'] .= $tpl->parse('tour:finished_events.item', array(
				'class' => ($j++%2) ? 'odd' : 'even',
				'date' => $lrepTools->helperFancyDate($event['from_date'] + $tzOffset, $event['to_date'] == NULL ? NULL : $event['to_date'] + $tzOffset, $locale),
				'name' => htmlspecialchars($event['name']),
				'url' => $lrep->makeUri('event#view', array(
					'event_id' => $event['id']
				)),
				'winningHand' => $lrepTools->helperFancyCards($event['winning_hand'], $tpl),
				'losingHand' => $lrepTools->helperFancyCards($event['losing_hand'], $tpl),
				'winner' => htmlspecialchars($event['winner']),
				'runnerUp' => htmlspecialchars($event['runner_up']),
				'winnerPrize' => $event['prize']
					? number_format($event['prize'])
					: '',
				'url.winner' => $event['winner_uri']
					? $playersUrl . $event['winner_uri'] . '/'
					: '',
				'url.runnerup' => $event['runner_up_uri']
					? $playersUrl . $event['runner_up_uri'] . '/'
					: '',
				'winnerImg' => $event['winner_img']
					? img('player',  $event['winner_id'] . '-' . $event['winner_img'])
					: '',
				'show_url' => ($event['is_empty'] ? 0 : 1) || $page->get_global('adminView')
			));
		}

		return $tpl->parse('tour:main', $mainArgv);
	}

	private function bannerRoomAssign($adRooms)
	{
		$adRooms = explode(',', $adRooms);
		if (isset($adRooms[0])) {
			moon::page()->set_local('banner.roomID', $adRooms[0]);
		}
	}
}
