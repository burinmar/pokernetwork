<?php
class twitter extends moon_com {

	function onload()
	{
		$this->tblPlayers = $this->table('TwitterPlayers');
		$this->tblMessages = $this->table('TwitterMessages');

		$this->playersUri = 'tweeters';
	}

	function events($event, $par)
	{
		$page = moon::page();
		if ($uri = $page->requested_event('REST')) {
			$pUri = trim($uri, '/');
			if ($pUri === $this->playersUri) {
				// players list
				$this->set_var('view', 'players');
				if (strlen($uri) - strlen($pUri) != 1) {
					//gale truksta /
					$page->redirect($this->linkas("#$pUri"), 301);
				}
			} elseif($pUri == 'ajax-suggest') {
				if(!isset($_GET['s']) || !$_GET['s']=trim($_GET['s'])) {
					print ''; die;
				}
				$suggestions = $this->findSuggestion($_GET['s']);
				$items = '';
				foreach($suggestions as $k=>$v) {
					$val = htmlspecialchars($v['title'] . ' ('. $v['twitter_nick'] . ')');
					$uri = $this->linkas('#').$v['twitter_nick'].'/';
					$items .= '<li><a class="item" href="'.$uri.'">'.$val.'</a></li>';
				}

				print '<ul class="suggestion-list">'.$items.'</ul>';
				moon_close();
				exit;
			} elseif ($pUri == 'statuses-show') {
				if (!empty($_GET['id']))
					$this->proxiedFetchTweet($_GET['id']);
				moon_close(); exit;
			} elseif($pUri) {
				// author messages
				$this->sqlWhere($pUri);
				$this->set_var('view', 'player');
				$this->set_var('playerUri', $pUri);
			} else {
				$page->page404();
			}
		}

		$this->setPaging();
		$this->use_page('twitter');
	}

	function properties()
	{
		return array(
			'view' => '',
			'currPage' => 1,
			'listLimitMessages' => 20,
			'listLimitPlayers' => 18
		);
	}

	function main($vars)
	{
		$output = '';
		switch ($vars['view']) {
			case 'twitter-box':
				$output = $this->htmlTwitterBox();
				break;
			case 'players':
				$output = $this->htmlListAuthors($vars);
				break;
			case 'messages':
			case 'player':
			default:
				$output = $this->htmlListMessages($vars);
				break;
		}
		return $output;
	}

	function htmlListAuthors($vars)
	{
		$page = moon::page();
		$sitemap = moon::shared('sitemap');
		$tpl = $this->load_template();

		$m = array(
			'uri.messages' => $sitemap->getLink(),
			'uri.players' => $sitemap->getLink() . $this->playersUri . '/',
			'items:players' => '',
			'searchBox' => ''
		);
		// generate list
		if ($count = $this->getItemsCountPlayers()) {
			$txt = &moon::shared('text');
			$txt->agoMaxMin = 60*24*30; // 30 days;

			$m['paging'] = $this->getPaging($vars['currPage'], $count, $vars['listLimitPlayers'], $this->playersUri);
			if ($vars['currPage'] > 1) {
				$page->meta('robots', 'noindex,follow');
			}

			$items = $this->getItemsPlayers();

			$itemsList = '';
			foreach ($items as $item) {
				$ago = $txt->ago($item['lastPublished']);
				$item['date'] = '';
				if ($item['lastPublished']) {
					$item['date'] = ($ago) ? $ago : date('D, d M Y H:i', $item['lastPublished']);
				}
				$item['url.author'] = $m['uri.messages'] . $item['screen_name'] . '/';
				$item['avatarSrc'] = $item['image_url'];
				$item['author'] = htmlspecialchars($item['name']);

				$m['items:players'] .= $tpl->parse('items:players', $item);
			}
		}
		$page->title('Poker Tweeters');
		$sitemap->breadcrumb(array($m['uri.players'] => 'Tweeters'));

		$page->js('/js/ajaxSuggestions.js');
		$search = array();
		$search['ajaxuri'] = $this->linkas('#');
		$search['goSearch'] = '';//$this->linkas('#search');
		$search['searchKeyword'] = '';
		$m['searchBox'] = $tpl->parse('search_box',$search);

		return $tpl->parse('list_players', $m);
	}

	function htmlListMessages($vars)
	{
		$page = moon::page();
		$sitemap = moon::shared('sitemap');
		$tpl = $this->load_template();
		$homeUrl = rtrim($page->home_url(), '/');

		$m = array(
			'uri.messages' => $sitemap->getLink(),
			'uri.players' => $sitemap->getLink() . $this->playersUri . '/',
			'items:messages' => ''
		);

		// generate list
		if ($count = $this->getItemsCount()) {
			$txt = &moon::shared('text');
			$txt->agoMaxMin = 60*24*30; // 30 days;

			$pUri = (!empty($vars['playerUri'])) ? $vars['playerUri'] : '';
			$m['paging'] = $this->getPaging($vars['currPage'], $count, $vars['listLimitMessages'], $pUri);
			if ($vars['currPage'] > 1) {
				$page->meta('robots', 'noindex,follow');
			}

			$items = $this->getItems();
			foreach ($items as $item) {
				$item['url.author'] = $m['uri.messages'] . $item['screen_name'] . '/';
				$item['avatarSrc'] = $item['image_url'];
				$item['name'] = htmlspecialchars($item['name']);
				$item['screen_name'] = htmlspecialchars($item['screen_name']);
				$item['authorUri'] = $homeUrl . $this->linkas('#' . $item['screen_name']);
				$item['author'] = htmlspecialchars($item['name']);
				$item['message'] = $item['message'];
				$ago = $txt->ago($item['created']);
				$item['date'] = '';
				if ($item['created']) {
					$item['date'] = ($ago) ? $ago : date('D, d M Y H:i', $item['created']);
				}

				$m['items:messages'] .= $tpl->parse('items:messages', $item);
			}
		} else {
			$page->page404();
		}

		if ($vars['view'] === 'player' && isset($item['name'])) {
			$m['playerName'] = $item['name'];
			$page->title($m['playerName'] . ' ' . $page->title());
			$sitemap->breadcrumb(array('' => $item['name']));
		}

		return $tpl->parse('list_messages', $m);
	}

	function htmlTwitterBox()
	{
		//cache
		$oCache = &moon::cache();
		$oCache->on(is_dev() ? 0 : 1);
		$cacheFileName = 'twitter_box';
		$oCache->file($cacheFileName);
		$res = $oCache->get();
		if($res !== FALSE) {
			return $res;	//return cached
		}

		$page = &moon::page();
		$tpl = &$this->load_template();
		$txt = &moon::shared('text');
		$txt->agoMaxMin = 1440 * 30; // 30 days

		$sitemap = &moon::shared('sitemap');
		$rootUri = $sitemap->getLink('twitter');
		$homeUrl = rtrim($page->home_url(), '/');

		$m = array(
			'items:box' => '',
			'allMessagesUri' => $homeUrl . $rootUri
		);

		$items = $this->getLastItems(false);
		foreach ($items as $item) {
			$ago = $txt->ago($item['created']);
			$item['time'] = ($ago) ? $ago : date('D, d M Y H:i', $item['created']);
			$item['authorUri'] = $homeUrl . $this->linkas('#' . $item['screen_name']);
			$item['avatarSrc'] = $item['image_url'];
			$item['name'] = htmlspecialchars($item['name']);
			$item['screen_name'] = htmlspecialchars($item['screen_name']);
			$item['authorUri'] = $homeUrl . $this->linkas('#' . $item['screen_name']);
			$item['message'] = $item['message'];
			$m['items:box'] .= $tpl->parse('items:box', $item);
		}

		$output = $tpl->parse('twitter_box', $m);
		$oCache->save($output, '2m'); // cache
		return $output;
	}

	function htmlTwitterReportingBox()
	{
		//cache
		$oCache = &moon::cache();
		$oCache->on(is_dev() ? 0 : 1);
		$cacheFileName = 'twitter_box_lrep';
		$oCache->file($cacheFileName);
		$res = $oCache->get();
		if($res !== FALSE) {
			return $res;	//return cached
		}

		$page = &moon::page();
		$tpl = &$this->load_template();
		$txt = &moon::shared('text');
		$txt->agoMaxMin = 1440 * 30; // 30 days

		$sitemap = &moon::shared('sitemap');
		$rootUri = $sitemap->getLink('twitter');
		$homeUrl = rtrim($page->home_url(), '/');

		$m = array(
			'items:box:reporting' => '',
			'allMessagesUri' => $homeUrl . $rootUri
		);

		$items = $this->getLastItems(false);
		$itemsList = '';
		foreach ($items as $item) {
			$ago = $txt->ago($item['created']);
			$item['time'] = ($ago) ? $ago : date('D, d M Y H:i', $item['created']);
			$item['authorUri'] = $homeUrl . $this->linkas('#' . $item['screen_name']);
			$item['name'] = htmlspecialchars($item['name']);
			$item['screen_name'] = htmlspecialchars($item['screen_name']);
			$item['authorUri'] = $homeUrl . $this->linkas('#' . $item['screen_name']);
			$item['message'] = $item['message'];
			$m['items:box:reporting'] .= $tpl->parse('items:box:reporting', $item);
		}

		$output = $tpl->parse('twitter_box_reporting', $m);
		$oCache->save($output, '2m'); // cache
		return $output;
	}

	function getItemsCount()
	{
		$sql = 'SELECT count(*) as cnt
			FROM ' . $this->tblMessages . ' ' .
			$this->sqlWhere();
		$result = $this->db->single_query_assoc($sql);
		return $result['cnt'];
	}

	function getItems()
	{
		$sql = 'SELECT message_id, name, screen_name, image_url, created, message
			FROM ' . $this->tblMessages . ' ' .
			$this->sqlWhere() . '
			ORDER BY created DESC ' .
			$this->sqlLimit();
		$result = $this->db->array_query_assoc($sql);
		return $result;
	}

	function getItemsCountPlayers()
	{
		$sql = 'SELECT count(*) as cnt
			FROM ' . $this->tblMessages . '
			WHERE	is_last_message = 1 AND
			     	is_hidden = 0';
		$result = $this->db->single_query_assoc($sql);
		return $result['cnt'];
	}

	function getItemsPlayers()
	{
		$sql = 'SELECT name, screen_name, image_url, created as lastPublished
			FROM ' . $this->tblMessages . '
			WHERE	is_last_message = 1 AND is_hidden = 0
			ORDER BY created DESC ' .
			$this->sqlLimit();
		return $this->db->array_query_assoc($sql);
	}

	function getLastItems($lastMessages = true)
	{
		$sql = 'SELECT message_id, name, screen_name, image_url, created, message
			FROM ' . $this->tblMessages  . '
			WHERE is_hidden = 0 ' . ($lastMessages ? ' AND is_last_message = 1' : '') . '
			ORDER BY created DESC
			LIMIT 5';
		$result = $this->db->array_query_assoc($sql);
		return $result;
	}

	/**
	 * Sets sql where condition. Used in tweets list
	 * @param screen name
	 * @return string
	 */
	function sqlWhere($screenName = FALSE)
	{
		if (!isset($this->tmpWhere)) {

			if ($screenName) {
				$w[] = 'screen_name = \'' . $this->db->escape($screenName) . '\'';
			}
			$w[] = 'is_hidden = 0';

			$where = count($w) ? (' WHERE ' . implode(' AND ', $w)) : '';
			$this->tmpWhere = $where;
		}
		return $this->tmpWhere;
	}

	/**
	 * Sql limit condition. Used in articles list paging
	 * @return string
	 */
	function sqlLimit()
	{
		return isset($this->tmpLimit) ? $this->tmpLimit : '';
	}

	function setPaging()
	{
		if (isset($_GET['page']) && is_numeric($_GET['page'])) {
			$currPage = $_GET['page'];
			$this->set_var('currPage', (int)$currPage);
		}
	}

	function getPaging($currPage, $itemsCnt, $listLimit, $uri)
	{
		$pn = &moon::shared('paginate');
		$pn->set_curent_all_limit($currPage, $itemsCnt, $listLimit);
		$pn->set_url(
			$this->linkas("#$uri", '', array('page' => '{pg}')),
			$this->linkas("#$uri")
		);
		$pnInfo = $pn->get_info();

		$this->tmpLimit = $pnInfo['sqllimit'];
		return $pn->show_nav();
	}

	//***************************************
	//        --- OTHER ---
	//***************************************
	function findSuggestion($s)
	{
		$sql = "
			SELECT title, twitter_nick
			FROM " . $this->tblPlayers . "
			WHERE is_hidden=0 AND concat(title,' ', twitter_nick) LIKE ('%". $this->db->escape($s)."%')
			ORDER BY title DESC
			LIMIT 0,12 ";
		return $this->db->array_query_assoc($sql);
	}

	private function proxiedFetchTweet($id)
	{
		if (!moon::user()->i_admin())
			moon::page()->page404();

		$twitter = moon::shared('twitter')->getInstance('pokernews_rtfembed');
		$r = $twitter->get('statuses/show', array(
			'id' => $id,
			'include_entities' => false
		));
		if (empty($r->id_str))
			moon::page()->page404();

		header('content-type: application/json; charset=utf-8');
		echo json_encode($r);
	}
}
?>
