<?php
class twitter_import extends moon_com {

	function events($event)
	{
		switch($event) {
			case 'twitter-messages':
				$cronMsg = $this->getNewTwitterMessages();
				if (isset($_GET['debug'])) {
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
	function getNewTwitterMessages()
	{
		set_time_limit(360);

		include_class('twitter');
		$o = new TwitterOAuth('YT1Pz8404q5OXYPM31dVLA', '27dFZG4g7YuiRV2BF3ZVMsLZ2BCHYlONrzt3TCOWk', '19899685-hk2khre7pNtK7R3mfsczjwVqFAFkGvAsW6Wpfr1pQ', 'W1qMMH9FEZQkntNs3c97ONOchfyOrvC9cAwzvMkm6I');

		$msg = '';
		$lastMsgIds = $this->getTwitterLastMsgIds();
		$count = 5;
		$monthAgo = time() - 3600*24*30;
		
		$users = $this->getTwitterUsers();
		foreach ($users as $user) {
			
			$username = strtolower(trim($user['twitter_nick']));
			$name = trim($user['title']);
			if ($username == '') continue;
			
			// ---------- check rate limit
			$r = $o->get('account/rate_limit_status');

			if (!isset($r->remaining_hits)) {
				$msg .= '<span style="color:#FF0000;">Empty rate limit</span><br />';
				$msg .= 'response: ' . print_r($r, TRUE) . ' <br />';
			} else {
				$twitterRemainingHits = $r->remaining_hits;
				
				if ($twitterRemainingHits <= 1) {
					$msg .= '<span style="color:#FF0000;">Reached hourly-limit:</span> ' . print_r($r, TRUE) . '<br />';
				}
				$msg .= 'Remaining-hits: ' . $twitterRemainingHits . '<br />';
			}

			// ---------- get new messages
			$since = (isset($lastMsgIds[$username])) ? $lastMsgIds[$username] : 0;

			$params = array('count' => $count, 'screen_name' => $username);

			if(isset($lastMsgIds[$username])) {
				$params['since_id'] = $lastMsgIds[$username];
			}
			$response = $o->get('statuses/user_timeline', $params);
			if (!isset($response->error)) {
				if (is_array($response) && !empty($response)) {
					$this->db->ping();

					$sql = 'REPLACE INTO ' . $this->table('TwitterMessages') . ' (message_id, name, screen_name, image_url, created, message, is_last_message, is_hidden) VALUES ';
					$ins = array();
					$cnt = 0;
					$imageUrl = '';
					foreach($response as $st) {
						$createdAt = strtotime($st->created_at);
						if ($createdAt < $monthAgo) {
							continue;
						}

						$data = array();
						$data['message_id'] = $st->id;
						$data['name'] = ($name != '') ? $this->db->escape($name) : $this->db->escape($st->user->name);
						$data['screenName'] = $st->user->screen_name;
						$data['imageurl'] = $imageUrl = $st->user->profile_image_url;
						$data['created'] = $createdAt;
						$data['message'] = $this->db->escape($this->addTwitterUrls($st->text));
						$data['isLastMessage'] = 0;//($cnt == 1) ? 1 : 0;
						$data['isHidden'] = (substr(trim($st->text), 0, 1) === '@') ? 1 : 0;
						$ins[] = '(\'' . implode('\',\'', $data) . '\')';

						$cnt++;

					}
					$sql .= implode(',', $ins);
					if ($cnt > 0) {
						$this->db->query('
							UPDATE ' . $this->table('TwitterMessages') . '
							SET is_last_message = 0
							' . ((!empty($imageUrl)) ? ', image_url = "' . $imageUrl . '"' : '') . '
							WHERE screen_name = \'' . $username . '\'');
						$this->db->query($sql);

						// set is_last_message, make sure its not hidden
						$res = $this->db->single_query_assoc('
							SELECT max(message_id) as id
							FROM ' . $this->table('TwitterMessages') . '
							WHERE screen_name = \'' . $username . '\' AND
							is_hidden = 0'
						);
						$lastId = !empty($res['id']) ? $res['id'] : 0;
						if ($lastId) {
							$this->db->query('
								UPDATE ' . $this->table('TwitterMessages') . '
								SET is_last_message = 1
								WHERE message_id = ' . $lastId
							);
						}

					}
					if ($cnt > 0) $msg .= '<span style="color:#00FF00;">' . $cnt . '</span> new messages for user: <em>' . $username . '</em><br />';
				} else {
					$msg .= 'No new messages for user: <em>' . $username . '</em><br />';
				}
			} else {
				$msg .= '<span style="color:#FF0000;">Error getting messages for user:<br />' . $response->error . '</span> <em>' . $username . '</em><br />';
			}
		}
		$this->db->ping();
		$this->db->query('DELETE FROM ' . $this->table('TwitterMessages') . ' WHERE created < ' . $monthAgo);
		return $msg;
	}

	function getTwitterUsers()
	{
		$sql = 'SELECT twitter_nick, title
			FROM ' . $this->table('TwitterPlayers') . '
			WHERE is_hidden = 0';
		$result = $this->db->array_query_assoc($sql);
		return $result;
	}

	function getTwitterLastMsgIds()
	{
		$sql = 'SELECT max(message_id) as lastId, screen_name as nick
			FROM ' . $this->table('TwitterMessages') . '
			GROUP BY nick';
		$result = $this->db->array_query_assoc($sql);
		$items = array();
		foreach ($result as $r) {
			$nick = strtolower(trim($r['nick']));
			$items[$nick] = $r['lastId'];
		}
		return $items;
	}

	function addTwitterUrls($msg)
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

	function xmlEntities($str)
	{
		$xml = array('&#34;','&#38;','&#38;','&#60;','&#62;','&#160;','&#161;','&#162;','&#163;','&#164;','&#165;','&#166;','&#167;','&#168;','&#169;','&#170;','&#171;','&#172;','&#173;','&#174;','&#175;','&#176;','&#177;','&#178;','&#179;','&#180;','&#181;','&#182;','&#183;','&#184;','&#185;','&#186;','&#187;','&#188;','&#189;','&#190;','&#191;','&#192;','&#193;','&#194;','&#195;','&#196;','&#197;','&#198;','&#199;','&#200;','&#201;','&#202;','&#203;','&#204;','&#205;','&#206;','&#207;','&#208;','&#209;','&#210;','&#211;','&#212;','&#213;','&#214;','&#215;','&#216;','&#217;','&#218;','&#219;','&#220;','&#221;','&#222;','&#223;','&#224;','&#225;','&#226;','&#227;','&#228;','&#229;','&#230;','&#231;','&#232;','&#233;','&#234;','&#235;','&#236;','&#237;','&#238;','&#239;','&#240;','&#241;','&#242;','&#243;','&#244;','&#245;','&#246;','&#247;','&#248;','&#249;','&#250;','&#251;','&#252;','&#253;','&#254;','&#255;', '&#8250;');
		$html = array('&quot;','&amp;','&amp;','&lt;','&gt;','&nbsp;','&iexcl;','&cent;','&pound;','&curren;','&yen;','&brvbar;','&sect;','&uml;','&copy;','&ordf;','&laquo;','&not;','&shy;','&reg;','&macr;','&deg;','&plusmn;','&sup2;','&sup3;','&acute;','&micro;','&para;','&middot;','&cedil;','&sup1;','&ordm;','&raquo;','&frac14;','&frac12;','&frac34;','&iquest;','&Agrave;','&Aacute;','&Acirc;','&Atilde;','&Auml;','&Aring;','&AElig;','&Ccedil;','&Egrave;','&Eacute;','&Ecirc;','&Euml;','&Igrave;','&Iacute;','&Icirc;','&Iuml;','&ETH;','&Ntilde;','&Ograve;','&Oacute;','&Ocirc;','&Otilde;','&Ouml;','&times;','&Oslash;','&Ugrave;','&Uacute;','&Ucirc;','&Uuml;','&Yacute;','&THORN;','&szlig;','&agrave;','&aacute;','&acirc;','&atilde;','&auml;','&aring;','&aelig;','&ccedil;','&egrave;','&eacute;','&ecirc;','&euml;','&igrave;','&iacute;','&icirc;','&iuml;','&eth;','&ntilde;','&ograve;','&oacute;','&ocirc;','&otilde;','&ouml;','&divide;','&oslash;','&ugrave;','&uacute;','&ucirc;','&uuml;','&yacute;','&thorn;','&yuml;', '&rsaquo;');
		$str = str_replace($html,$xml,$str);
		$str = str_ireplace($html,$xml,$str);
		return $str;
	}

	function handleXmlErrors($xml)
	{
		$errors = libxml_get_errors();
		$xml = explode("\n", $xml);
		$errStr = '';

		foreach ($errors as $error) {
			$errStr .= $this->displayXmlError($error, $xml);
		}
		libxml_clear_errors();

		return $errStr;
	}

	function displayXmlError($error, $xml)
	{
		$return  = $xml[$error->line - 1] . "\n";
		$return .= str_repeat('-', $error->column) . "^\n";

		switch ($error->level) {
			case LIBXML_ERR_WARNING:
				$return .= "Warning $error->code: ";
			break;
			case LIBXML_ERR_ERROR:
				$return .= "Error $error->code: ";
			break;
			case LIBXML_ERR_FATAL:
				$return .= "Fatal Error $error->code: ";
			break;
		}

		$return .= trim($error->message) .
			"\n  Line: $error->line" .
			"\n  Column: $error->column";
		
		if ($error->file) {
			$return .= "\n  File: $error->file";
		}
		
		return "$return\n\n--------------------------------------------\n\n";
	}

}
?>