<?php
class twitter_import extends moon_com {

	function events($event)
	{
		switch($event) {
			case 'twitter-messages':
				$cronMsg = $this->getNewTwitterMessages();
				if (isset($_GET['debug'])) {
					header('content-type: text/plain; charset=utf-8');
					moon_close();
					die($cronMsg);
				}
				$page = &moon::page();
				$page->set_local('cron', $cronMsg);
				return;
			default:
				break;
		}
		$this->use_page('Common');
	}

	//***************************************
	//           --- TWITTER ---
	//***************************************
	private function getNewTwitterMessages()
	{
		set_time_limit(360);

		$twitter = moon::shared('twitter')->getInstance('PokerNetwork_SocialImport');
		$cache = moon::cache();
		$cache->on(TRUE);
		$msg = '';

		$limits = $twitter->get('application/rate_limit_status');
		if (isset($limits->errors) || !isset($limits->resources)) {
			$msg .= 'No connectivity? Invalid keys?' . "\n";
			return $msg;
		}
		if ($limits->resources->lists->{'/lists/members'}->remaining == 0
		 || $limits->resources->lists->{'/lists/list'}->remaining == 0) {
			$msg .= 'Clearly out of rate limits. ' . "\n";
			return $msg;
		}
		
		// Find and, if necessarey, create PokerPlayers list
		// List is identified by slug, which is a normalized name with a max of 25 characters
		$playersListId = null;
		$lists = $twitter->get('lists/list');
		if (isset($lists->errors)) {
			$msg .= 'Failed retrieveing lists list. ' . "\n";
			return $msg;
		}
		foreach ($lists as $list) {
			if ($list->slug == 'pnw-com-tracker') {
				$playersListId = $list->id_str;
				break;
			}
		}
		if (!$playersListId) {
			$list = $twitter->post('lists/create', array(
				'name' => 'pnw.com tracker',
				'mode' => 'private',
				'description' => 'pnw.com Twitter Tracker'
			));
			if (!isset($list->errors) && isset($list->id)) {
				$msg .= 'Created PokerPlayers list. ' . "\n";
				$playersListId = $list->id_str;
				$cache->delete($this->my('fullname').'.usersInList'); // edge case
			} else {
				$msg .= 'Failed to create PokerPlayers list. ' . "\n";
				return $msg;
			}
		}
		$msg .= 'Found required list (' . $playersListId . '). ' . "\n";

		// Get twitter users list, store to cache for some time
		$usersInList = $cache->get($this->my('fullname'). '.usersInList');
		if ($usersInList === false) {
			$usersInList = array();
			$cursor = '-1';
			$msg .= 'Retrieving PokerPlayers members list page: ';
			do {
				$response = $twitter->get('lists/members', array(
					'list_id' => $playersListId,
					'skip_status' => 'true',
					'cursor' => $cursor
				));
				if (isset($response->errors) || !isset($response->users)) {
					$msg .= '. ' . "\n";
					$msg .= 'Failed while retrieving PokerPlayers members. ' . "\n";
					return $msg;
				}
				foreach ($response->users as $user) {
					$usersInList[] = strtolower($user->screen_name);
				}
				$cursor = $response->next_cursor_str;
				$msg .= '~';
			} while ($cursor !== '0');
			$msg .= '. ' . "\n";
			$msg .= 'Saving PokerPlayers members list to cache. ' . "\n";
			$cache->save(serialize($usersInList), '4h');
		} else {
			$usersInList = unserialize($usersInList);
			$msg .= 'Got PokerPlayers members list from cache (' . count($usersInList) . ').' . "\n";
		}

		// Get db users lists
		$this->db->ping();
		$users = $this->getTwitterUsers();
		if (0 == count($users)) {
			$msg .= 'No players in db, aborting' . "\n";
			return $msg;
		}

		// Calculate users, missing in twitter list.
		// Check if those players exists on twitter at all, if not - mark as hidden in db 
		$missingListUsers = array_diff(array_keys($users), $usersInList);
		$missingListUsers = array_slice($missingListUsers, 0, 99); // max 100 users limit for any operation, 1 script-reserved
		if (0 != count($missingListUsers)) {
			// include one existing account, otherwise if all users are non-existent, an error is returned
			// that's one way to check 
			$usersInfo = $twitter->get('users/lookup', array(
				'screen_name' => implode(',', $missingListUsers) . ',pokernews',  // +absolutely any existing account, that's where reserve goes
				'include_entities' => false
			));
			if (!isset($usersInfo->errors) && is_array($usersInfo)) {
				foreach ($missingListUsers as $k => $userName) {
					$found = false;
					foreach ($usersInfo as $tUser) {
						if (strtolower($tUser->screen_name) == $userName) {
							$found = true;
							break;
						}
					}
					if (!$found) {
						$this->db->update(array(
							'is_hidden' => 1
						), $this->table('TwitterPlayers'), array(
							'twitter_nick' => $userName
						));
						$msg .= 'Forcing ' . $userName . ' to be hidden. ' . "\n";
						unset($missingListUsers[$k]);
					}
				}
			} else {
				$msg .= 'Failed to lookup missing players, skipping. ' . "\n";
			}
		}

		// Calculate users, missing in db, but present in twitter list
		$extraListUsers = array_diff($usersInList, array_keys($users));
		$extraListUsers = array_slice($extraListUsers, 0, 99); // max 100 users limit for any operation, 1 script-reserved

		// Add and remove should be exclusive in one sessions, as per twitter advice.
		if (count($missingListUsers) > count($extraListUsers)) {
			// adding
			$msg .= 'Adding ' . count($missingListUsers) . ' player(s): ' . implode(',', $missingListUsers) . '. ' . "\n";
			$twitter->post('lists/members/create_all', array(
				'list_id' => $playersListId,
				'screen_name' => implode(',', $missingListUsers)
			));
			$msg .= 'Deleting PokerPlayers members list cache. ' . "\n";
			$cache->delete($this->my('fullname').'.usersInList');
		} elseif (count($extraListUsers) > 0) {
			// removing 
			$msg .= 'Removing ' . count($extraListUsers) . ' player(s): ' . implode(',', $extraListUsers) . '. ' . "\n";
			$twitter->post('lists/members/destroy_all', array(
				'list_id' => $playersListId,
				'screen_name' => implode(',', $extraListUsers)
			));
			$msg .= 'Deleting PokerPlayers members list cache. ' . "\n";
			$cache->delete($this->my('fullname').'.usersInList');
		}

		// Get timeline, from oldest to newest
		$this->db->ping();
		$timeline = $twitter->get('lists/statuses', array(
			'list_id' => $playersListId,
			'include_rts' => 'false',
			'include_entities' => 'false',
			'since_id' => $this->getLastTweetId(),
			'count' => '200' // default is, like, ~20
		));
		if (isset($timeline->errors) || !is_array($timeline)) {
			$msg .= 'Failed to get timeline' . "\n";
			return $msg;
		}
		$timeline = array_reverse($timeline);

		// Glorious insertions
		$this->db->ping();
		foreach ($timeline as $tweet) {
			$twitterNick = strtolower($tweet->user->screen_name);
			if (!isset($users[$twitterNick])) {
				$msg .= 'Skipping ' . $twitterNick . 'message. ' . "\n";
				continue;
			}
			$user = $users[$twitterNick];

			$isHidden = $tweet->in_reply_to_status_id_str != '';
			$isLastMessage = !$isHidden;

			if ($isLastMessage) {
				// it is ok to update image on new visible tweet only, i guess
				// otherwise, just 
				$this->db->update(array(
					'is_last_message' => 0,
					'image_url' => $tweet->user->profile_image_url,
				), $this->table('TwitterMessages'), array(
					'screen_name' => $tweet->user->screen_name
				));
			}
			$this->db->replace(array(
				'message_id' => $tweet->id_str,
				'name' => !empty($user['title'])
					? $user['title']
					: $tweet->user->name,
				'screen_name' => $tweet->user->screen_name,
				'image_url' => $tweet->user->profile_image_url,
				'created' => strtotime($tweet->created_at),
				'message' => $this->addTwitterUrls($tweet->text),
				'is_last_message' => $isLastMessage,
				'is_hidden' => $isHidden,
			), $this->table('TwitterMessages'));
			$msg .= $twitterNick . '(' . $tweet->id_str . ')' . "\n";
		}

		// update profile images

		// Glorious cleanups
		$this->db->query('DELETE FROM ' . $this->table('TwitterMessages') . ' WHERE created < ' . (time() - 3600*24*30));
		$msg .= 'Deleted ' . $this->db->affected_rows() . ' old messages. ' . "\n";

		return $msg;
	}

	// nicks must be normalized, lowercased, and also be array keys
	private function getTwitterUsers()
	{
		$sql = 'SELECT LOWER(TRIM(twitter_nick)) twitter_nick, title
			FROM ' . $this->table('TwitterPlayers') . '
			WHERE is_hidden = 0';
		$result = $this->db->array_query_assoc($sql, 'twitter_nick');
		return $result;
	}

	private function getLastTweetId()
	{
		$lastMessage = $this->db->single_query_assoc('
			SELECT MAX(message_id) message_id FROM ' . $this->table('TwitterMessages') . '
		');
		return !empty($lastMessage['message_id'])
			? $lastMessage['message_id']
			: 0;
	}

	private function addTwitterUrls($msg)
	{
		// http://ow.ly/cQF5, http://pro.betfair.com/2009/annette_15/
		$pattern = '((http://)([w\.]{0,4}[a-zA-Z1-9\-_\.]+\.[a-z]{2,3}(/[a-zA-Z0-9\-/_\?=&~#;\.\+]+)?))';
		
		$matches = array();
		if(preg_match_all($pattern, $msg, $matches)) {
			if (!empty($matches[0])) {
				foreach ($matches[0] as $m) {
					$msg = str_replace($m, '<a href="' . htmlspecialchars($matches[1][0] . $matches[2][0]) . '" target="_blank" rel="nofollow">' . htmlspecialchars($m) . '</a>', $msg);
				}
			}
		}

		// @poker_2_0
		$pattern = '((@)([a-zA-Z0-9\-_]+)([,\s\n\r\t]+))';
		$replace = '\\1<a href="http://twitter.com/\\2" target="_blank" rel="nofollow">\\2</a> ';
		$msg = preg_replace($pattern, $replace, $msg);
		return $msg;
	}
}
