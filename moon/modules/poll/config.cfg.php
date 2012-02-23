<?php

$cfg = array();
$cfg['poll'] = array(
	'vocabulary{poll}' => '{dir.multilang}sys.txt',

    'page.Archive' => 'xml.common,fake',

	'tb.PollAnswers' => 'poll_answers',
	'tb.PollQuestions' => 'poll_questions',
	'tb.PollVotes' => 'poll_votes',

	'var.PollArchiveGroupYears' => 2,

	'var.Themes' => array(
		'op' => 'default',
		'shared-lr' => 'shared-lr'
	)
);

