<?php
$cfg = array();
$cfg['poll'] = array(
	'sys.moduleDir' => 'poll/',
	'sys.multiLang' => 0,

	'tb.Questions' => 'poll_questions',
	'tb.Answers'   => 'poll_answers',
	'tb.Votes'     => 'poll_votes',

	'page.Common' => 'sys.adm,fake',

	'comp.rtf' => 'MoonShared.rtf',
	'var.rtf' => 'poll',

	'var.paginateBy' => 20,
	'var.pollZones' => array(
		'home' => 'Home',
	),
	'var.restrictions' => array(
		'0' => 'Everybody',
		'1' => 'Registered users'
	),

	//Trivia component
	'comp.trivia' => 'poll.polls',
	'var.pollType{trivia}' => 'trivia'
);
