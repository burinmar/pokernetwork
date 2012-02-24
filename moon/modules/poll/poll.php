<?php

class poll extends moon_com {

	var $form;

	function events($event, $par)
	{
		$this->use_page('');
		switch ($event) {
			case 'vote':
				$this->form=&$this->form();
				$this->form->names('question', 'vote', 'method');
				$this->form->fill($_POST);
				$this->_doVote();
				break;
			case 'skipvote':
				$argv = array(
					'qid' => isset($par[0])
						? $par[0]
						: NULL,
					'method' => isset($_GET['method'])
						? $_GET['method']
						: NULL,
				);
				$this->_doVoteSkip($argv);
				break;
		}
	}

	function properties()
	{
		return array(
			'output' => 'std',
			'qid'    => NULL,
			'zone'   => NULL
		);
	}

	function main($vars)
	{
		$page = &moon::page();
		$user = &moon::user();
		$tpl = &$this->load_template();

		$myID = (int)$user->get_user_id();
		$myCookie = $user->cookie_id();

		if (NULL == $vars['zone'] && empty($vars['qid'])) {
			return '';
		}

		$vars['is_trivia'] = isset($vars['is_trivia'])
			? intval($vars['is_trivia'])
			: NULL;
		$question = $this->_getQuestion($vars['qid'], $vars['zone'], $vars['is_trivia']);
		if (!$question || empty($myID) && ($question['restrictions'] != '0')) {
			switch ($vars['output']) {
				case 'json':
					echo json_encode(array(
						'data' => ''
					));
					moon_close();
					exit;

				default:
					return '';
			}
		}

		$page->js('/js/modules/poll.js');
		$answeredId = $this->_hasAnswered($myID, $myCookie, $question['id']);

		$themes = $this->get_var('Themes');
		$ns = (isset($vars['zone']) && isset($themes[$vars['zone']]))
			? $themes[$vars['zone']]
			: 'default';
		$ns .= ':';

		$tp = array (
			'main' => $answeredId !== FALSE ? 'asked': 'ask',
			'vote.event' => $this->my('fullname').'#vote',
			'question-id' => $question['id'],
			'question' => htmlspecialchars($question['question']),
			'list.answers'  => '',
			'total-voted' =>  $question['total_voted'],
			'answer_text' => $question['answer_text'],
			'trivia' => $vars['is_trivia'],
			'url.view_results' => $this->linkas('#skipvote', $question['id'])
		);
		$votes =  $question['total_voted'] ?  $question['total_voted'] : 1; // do not divide by zero
		$tpblock = $answeredId !== FALSE
			? 'answers.item.asked' . ($vars['is_trivia'] ? '.trivia' : '')
			: 'answers.item.ask';
		foreach ($question['answers'] as $ans) {
			$prcnt = round($ans['votes']*100/$votes);
			$tp['list.answers'] .= $tpl->parse($ns . $tpblock, array(
				'id' => $ans['id'],
				'answer' => htmlspecialchars($ans['answer']),
				'percent' => $prcnt,
				'is_correct_answer' => $ans['is_correct_answer'],
				'is_voted_answer' => $ans['id'] == $answeredId,
				'trivia' => $vars['is_trivia']
			));
		}
		switch ($vars['output']) {
			case 'json':
				$data = $tpl->parse($ns . $tp['main'], $tp);
				$data = str_replace('{!action}', $_SERVER['REQUEST_URI'], $data);
				$htmlId = isset($_POST['html_id'])
						? $_POST['html_id']
						: '';
				if ($htmlId == '') {
				$htmlId = isset($_GET['html_id'])
						? $_GET['html_id']
						: '';
				}
				echo json_encode(array(
					'data' => $data,
					'html_id' => $htmlId
				));
				moon_close();
				exit;
			default:
				return $tpl->parse($ns . 'box', array(
					'data' => $tpl->parse($ns . $tp['main'], $tp)
				));
		}
	}
	
	function _getQuestion($qid = NULL, $zone = NULL, $isTrivia = 0)
	{
		$sql = ($zone !== NULL)
			? 'SELECT *, FIND_IN_SET("'.addslashes($zone).'", places) cz FROM '.$this->table('PollQuestions')
			: 'SELECT *, 1 cz FROM '.$this->table('PollQuestions');
		$qid = $this->getInteger_($qid);

		if (NULL === $qid) {
			$sql .= ' WHERE is_hidden=0' . ($isTrivia !== NULL
				  ? ' AND is_trivia = ' . $this->getInteger_($isTrivia)
				  : '');
			if ($zone !== NULL) {
				$sql .= ' AND FIND_IN_SET("'.addslashes($zone).'", places) ';
			}
			$sql .= ' ORDER BY id DESC';
		} else {
			$sql .= ' WHERE id=' . $qid;
		}
		$sql .= ' LIMIT 1';
		
		$r = $this->db->single_query_assoc($sql);
		if (empty($r)) {
			return NULL;
		}
		
		$r['is_active'] = $r['cz'] && !($r['is_hidden'])
			? TRUE
			: FALSE;
		$r['total_voted'] = 0;
		$r['answers'] = $this->db->array_query_assoc('
			SELECT id, answer, votes, is_correct_answer
			FROM ' . $this->table('PollAnswers') . '
			WHERE question_id=' . intval($r['id']) . '
			ORDER BY position ASC
		');
		foreach ($r['answers'] as $ans) {
			$r['total_voted'] += $ans['votes'];
		}
		return $r;
    }

	function _totalVoted($qid)
	{
		$r = $this->db->single_query_assoc('
			SELECT SUM(votes) AS total FROM '.$this->table('PollAnswers').'
			WHERE question_id='.$qid.'
		');
		return $r['total'];
	}

	function _hasAnswered($uid, $cid, $qid)
	{
		$userType = '0';
		if (empty($uid)) {
			$uid = str_replace('=', '', base64_encode(pack('H*', $cid)));
			$userType = '1';
		}
		return ($answer = $this->db->single_query_assoc('
			SELECT * FROM '.$this->table('PollVotes').'
			WHERE user_id="'. addslashes($uid) .'" AND user_type="' . $userType . '" AND question_id='.intval($qid)))
			? $answer['answer_id']
			: FALSE;
	}

	function _doVoteSkip($argv)
	{
		$user = &moon::user();
		$myID = (int)$user->get_user_id();
		$myCookie = $user->cookie_id();

		$question = $this->_getQuestion(intval($argv['qid']));
		$hasAnswered = $this->_hasAnswered($myID, $myCookie, $question['id']);

		$userType = '0';
		if (empty($myID)) {
			$myID = str_replace('=', '', base64_encode(pack('H*', $myCookie)));
			$userType = '1';
		}

		if ((NULL !== $question) && (FALSE === $hasAnswered) && !empty($myID) && (TRUE === $question['is_active'])) {
			$this->db->insert(array(
				'user_id'     => $myID,
				'user_type'   => $userType,
				'question_id' => $argv['qid'],
				'answer_id'   => NULL,
				'created_on'  => time()
			), $this->table('PollVotes'));
		}

		$this->set_var('is_trivia', $question['is_trivia']);
		$this->set_var('qid', $argv['qid']);

		switch ($argv['method']) {
			case 'ajax':
				$this->set_var('output', 'json');
			break;
			default:
				$page = &moon::page();
				$page->back(TRUE);
			break;
		}
	}

	function _doVote()
	{
		$page = &moon::page();
		$user = &moon::user();
		$myID = (int)$user->get_user_id();
		$myCookie = $user->cookie_id();
		$vals = $this->form->get_values();

		$vals['question'] = $this->getInteger_($vals['question']);
		$vals['vote']     = str_replace('id-', '', $vals['vote']);
		$vals['vote']     = $this->getInteger_($vals['vote']);

		$question = $this->_getQuestion($vals['question']);

		$hasAnswered = $this->_hasAnswered($myID, $myCookie, $question['id']);

		$this->set_var('is_trivia', $question['is_trivia']);

		$userType = '0';
		if (empty($myID)) {
			$myID = str_replace('=', '', base64_encode(pack('H*', $myCookie)));
			$userType = '1';
		}

		if ((NULL !== $question) && (FALSE === $hasAnswered) && !empty($myID) && (TRUE === $question['is_active'])
		  && (NULL !== $vals['question']) && (NULL !== $vals['vote'])) {
			$this->db->insert(array(
				'user_id'     => $myID,
				'user_type'   => $userType,
				'question_id' => $vals['question'],
				'answer_id'   => $vals['vote'],
				'created_on'  => time()
			), $this->table('PollVotes'));
			$this->db->query('
				UPDATE ' . $this->table('PollAnswers') . '
				SET votes=votes+1
				WHERE id=' . $vals['vote'] . ' AND question_id=' . $vals['question'] . '
			');
			$this->set_var('qid', $vals['question']);
		}
		switch ($vals['method']) {
			case 'ajax':
				$this->set_var('output', 'json');
			break;
			default:
				$page->back(TRUE);
			break;
		}
	}

   	function getInteger_($i) {
		if (preg_match('/^[\-+]?[0-9]+$/', $i)) {
			return intval($i);
		} else {
			return NULL;
		}
	}

}
