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
class livereporting_event_tweet extends livereporting_event_pylon
{
	protected function synthEvent($event, $argv)
	{
		switch ($event) {
			case 'save-tweet':
				$data = $this->helperEventGetData(array(
					'tweet_id', 'day_id', 'body'
				));
				
				$postId = $this->save($data);
				$this->redirectAfterSave($postId, 'tweet');
				exit;
			case 'delete':
				if (FALSE === $this->delete($argv['uri']['argv'][2])) {
					moon::page()->page404();
				}
				$this->redirectAfterDelete($argv['event_id'], $this->requestArgv('day_id'));
				exit;
			default:
				moon::page()->page404();
		}
	}

	protected function render($data, $argv)
	{
		if ($argv['variation'] == 'logControl') {
			return $this->renderControl(array_merge($data, array(
				'title' => '',
				'contents' => '',
				'unhide' => (!empty($_GET['master']) && $_GET['master'] == 'tweet')
			)));
		}

		$page = moon::page();
		$tpl = $this->load_template();
		
		$rArgv = $this->helperRenderCommonArgv($data, $argv);

		if ($argv['variation'] == 'logEntry') {
			if (_SITE_ID_ == 'com') {
				$rArgv['url.edit'] = null;
			}
			return $tpl->parse('logEntry:tweet', $rArgv);
		} elseif ($argv['variation'] == 'individual') {
			if ($rArgv['show_controls']) {
				$rArgv['control'] = $this->renderControl(array_merge(
					$this->getEditableData($data['id'], $data['event_id']), array(
					'unhide' => ($argv['action'] == 'edit')
				)));
			}
			$page->title($page->title() . ' | Tweet');
			return $tpl->parse('entry:tweet', $rArgv);
		}
	}

	private function renderControl($argv)
	{
		if (empty($argv['day_id'])) {
			return ;
		}
		$controlsArgv = array(
			'ct.save_event' => $this->parent->my('fullname') . '#save-tweet',
			'ct.id' => isset($argv['id'])
				? intval($argv['id'])
				: '',
			'ct.body' => htmlspecialchars($argv['contents']),
			'ct.day_id' => $argv['day_id'],
			'ct.unhide' => !empty($argv['unhide']),
		);

		return $this->load_template()
			->parse('controls:tweet', $controlsArgv);
	}

	private function sendToTwitter($message, &$e) 
	{
		$isLocal = is_local_version();
		
		// services API
		include_class('twitter');
		$a = $this->get_var('twitter');
		$o = new TwitterOAuth($a[0], $a[1], $a[2], $a[3]);
		$r = $o->get('account/rate_limit_status');
		
		if (!isset($r->remaining_hits)) {
			$e = 'Empty rate limit.';
			return false;
		}
		$twitterRemainingHits = $r->remaining_hits;

		if ($twitterRemainingHits <= 1) {
			$e = 'Reached hourly-limit';
			return false;
		}
		
		$page = &moon::page();
		$homeURL = $page->home_url();
		$homeURL = preg_replace('~/$~', '', $homeURL);

		$url = $homeURL . $this->lrep()->makeUri('event#view', array(
				'event_id' => getInteger($message['event_id']),
				'path' => $this->getUriPath(),
				'type' => 'tweet',
				'id' => $message['id']
			), $this->getUriFilter(TRUE, NULL));
		
		$tweet = '';
		//$twitterTag = '';
		$shortLink = short_url($url);
		
		/*$tag = $this->db->single_query_assoc('
			SELECT e.twitter_tag etag, t.twitter_tag ttag
			FROM ' . $this->table('Events') . ' e
			INNER JOIN ' . $this->table('Tournaments') . ' t
				ON t.id=e.tournament_id
			WHERE e.id=' . getInteger($message['event_id']) . '
		');
		if (!empty($tag['etag'])) {
			$twitterTag = $tag['etag'];
		} else if (!empty($tag['ttag'])) {
			$twitterTag = $tag['ttag'];
		}
		if ($twitterTag && strpos($twitterTag, '#') !== 0) {
			$twitterTag = '#' . $twitterTag;
		}
		$tweet .= $twitterTag;*/

		$txt = $message['contents'];
		$txt = str_replace(array("\n", "\r"), ' ', $txt);
		//$txt = preg_replace('/\[[^]]*(\]|$)/', '', $txt);
		$txt = str_replace(array("#"), '', $txt);
		//$txt = strip_tags($txt);

		$tweet .= ' ' . $txt;
		$strOver = mb_strlen($tweet) + mb_strlen($shortLink) + 1 - 140;
		if ($strOver > 0) {
			$tweet = trim($tweet, '.!- :');
			$tweet = mb_substr($tweet, 0, (mb_strlen($tweet) - $strOver - 4));
		}
		$tweet .= ' ' . $shortLink;

		//if (!$isLocal) {
		$r = $o->post('statuses/update', array(
			'status' => $tweet,
			'trim_user' => 1, 
			'include_entities' => 0
		));
		if (isset($r) && !empty($r->id)) {
			return true;
		}
		//}

		return false;
	}
	
	private function save($data)
	{
		if (NULL == ($prereq = $this->helperSaveCheckPrerequisites($data['day_id'], $data['tweet_id'], 'tweet'))) {
			return FALSE;
		}
		list (
			$location,
			$entry
		) = $prereq;
		
		$userId = intval(moon::user()->get_user_id());

		$data['body'] = str_replace("\r", '', $data['body']);
		$data['body'] = mb_substr($data['body'], 0, 115);
		$saveDataTweet = array(
			'contents' => $data['body'],
		);
		$saveDataLog = array(
			'type' => 'tweet',
			'is_hidden' => 0,
			'contents' => serialize(array(
				'contents' => $data['body']
			))
		);

		$this->helperSaveAssignCommonLogAttrs($saveDataLog, $userId, $entry, $data, $location);
		if ($entry['id'] != NULL) { // update
			unset($saveDataLog['created_on']);
			$saveDataLog['updated_on'] = time();
		} else { // create
			$saveDataLog['created_on']  = time();
		}

		if ($entry['id'] != NULL) { // update
			$this->db->update($saveDataTweet, $this->table('tTweets'), array(
				'id' => $entry['id']
			));
			$this->db->update($saveDataLog, $this->table('Log'), array(
				'id' => $entry['id'],
				'type' => 'tweet'
			));
		} else { // create
			$this->db->insert($saveDataTweet, $this->table('tTweets'));
			if (!($entry['id'] = $saveDataLog['id'] = $this->db->insert_id())) {
				return ;
			}
			if (!$this->sendToTwitter(array(
				'id' => $saveDataLog['id'],
				'event_id' => $saveDataLog['event_id'],
				'contents' => $saveDataTweet['contents']
			), $e)) {
				$this->db->query('
					DELETE FROM ' . $this->table('tTweets') . '
					WHERE id="' . $saveDataLog['id'] . '"
				');
				return ;
			}
			$this->db->insert($saveDataLog, $this->table('Log'));
		}

		$this->helperSaveNotifyEvent($saveDataLog['is_hidden'], $location);

		return $entry['id'];
	}

	private function delete($tweetId)
	{
		if (NULL == ($prereq = $this->helperDeleteCheckPrerequisites($tweetId, 'tweet'))) {
			return FALSE;
		}
		list (
			$location
		) = $prereq;
		
		$this->helperDeleteDbDelete($tweetId, 'tweet', 'tTweets', FALSE);
		
		$this->helperDeleteNotifyEvent($location);
	}
	
	private function getEditableData($id, $eventId)
	{
		$entry = $this->db->single_query_assoc('
			SELECT l.tournament_id, l.event_id, l.day_id, l.created_on, l.updated_on, l.is_hidden, d.*
			FROM ' . $this->table('Log') . ' l
			INNER JOIN ' . $this->table('tTweets') . ' d
				ON l.id=d.id
			WHERE l.id=' . getInteger($id) . ' AND l.type="tweet"
				AND l.event_id=' . getInteger($eventId));
		if (empty($entry)) {
			return NULL;
		}
		return $entry;
	}
}