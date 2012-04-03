<?php
class posts extends moon_com {
	
	function onload()
	{
		$this->blog = $this->object('blog');
		$this->sqlOrder = 'ORDER BY created_on DESC';
		$this->uri = ''; // used for paging now
		$this->uriMostViewed = 'most-viewed';
	}
	
	function events($event, $par)
	{
		$page = &moon::page();
		//if (!isset($par[0])) $page->page404();	
		$this->uri = $uri = trim($page->requested_event('REST'), '/');
		
		$segments = $page->uri_segments();

		$cnt = count($segments);
		if (substr($uri, - 4) === '.htm') {
			//single post
			$postUri = substr($uri, 0, - 4);
			$post = $this->getPostByUri($postUri);
			if (empty($post)) $page->page404();
			
			$this->updateViewsCount($post['id']);
			$this->set_var('post', $post);
			$this->set_var('view', 'post');
			$this->blog->set('isBlogOwner', $post['user_id'] == moon::user()->get_user_id());

		} elseif (!empty($segments[3]) && $segments[2] == 'tag') {
			// tag
			$tag = urldecode($segments[3]);
			$this->set_var('tag', $tag);
		} elseif (substr($uri, - 7) === 'rss.xml') {
			// rss
			$feedType = $uri;
			print $this->rssXml($feedType);
			moon_close();
			exit;
		} elseif (trim($uri,'/') === $this->uriMostViewed) {
			$this->sqlOrder = 'ORDER BY views_count DESC';
		} elseif ($cnt === 4 && is_numeric($segments[2]) && $segments[2] > 2000 && $segments[3] == '') {
			// year archive
			$year = intval($segments[2]);
			
			if (checkdate(1, 1, $year)) {
				$this->set_var('year', $year);
			} else {
				$page->page404();
			}
			
		} elseif ($cnt === 5 && is_numeric($segments[2]) && $segments[2] > 2000 && is_numeric($segments[3]) && checkdate($segments[3], 1, 1) && $segments[4] == '' ) {
			//month archive
			$year = intval($segments[2]);
			$month = intval($segments[3]);
			
			if (checkdate($month, 1, $year)) {
				$this->set_var('year', $year);
				$this->set_var('month', $month);
				
				$page->set_local('year', $year);
				$page->set_local('month', $month);
			} else {
				$page->page404();
			}
			
		} elseif ($cnt === 6 && is_numeric($segments[2]) && $segments[2] > 2000 && is_numeric($segments[3]) && checkdate($segments[3], 1, 1) && is_numeric($segments[4]) && checkdate($segments[3], $segments[4], $segments[2]) && $segments[5] == '' ) {
			//day archive
			$year = intval($segments[2]);
			$month = intval($segments[3]);
			$day = intval($segments[4]);
			
			if (checkdate($month, $day, $year)) {
				$this->set_var('year', $year);
				$this->set_var('month', $month);
				$this->set_var('day', $day);
				
				$page->set_local('year', $year);
				$page->set_local('month', $month);
				$page->set_local('day', $day);
			} else {
				$page->page404();
			}
			
		} elseif(count($segments) === 3) {
			
			// posts list

		} else {
			//$page->page404();
		}
		
		$this->setPaging();
		$this->use_page('Main');
	}
	
	function properties()
	{
		return array(
			'view' => '',
			'post' => array(),
			'currPage' => 1,
			'listLimit' => 10,
			'tag' => '',
			
			'year' => 0,
			'month' => 0,
			'day' => 0
		);
	}
	
	function main($vars)
	{
		$output = '';
		switch ($vars['view']) {
			case 'post':
				$output = $this->htmlPost($vars);
				break;
			default:
				$output = $this->htmlList($vars);
				break;
		}
		return $output;
	}
	
	function htmlPost($vars) 
	{
		if (!count($post = $vars['post'])) {
			//jei neradom
			$page->page404();
		}
		
		$blog = $this->blog;
		
		$page = &moon::page();
		$page->css('/css/article.css');

		$page->set_local('postId', $post['id']);
		
		$tpl = $this->load_template();
		$loc = &moon::locale();
		
		$print = empty($vars['print']) ? FALSE : TRUE;
		$isBlogOwner = $blog->get('isBlogOwner');
		
		$postTitle = htmlspecialchars($post['title']);
		// meta info
		$page->title($postTitle);
		$page->meta('description', $postTitle);
		
		// tags
		$tags = explode(',', $post['tags']);
		$t = array();
		foreach ($tags as $tag) {
			if (!$tag) continue;
			$t1 = array();
			$t1['tagName'] = htmlspecialchars($tag);
			$t1['url.tag'] = $this->linkas('#tag/' . urlencode($tag));
			$t[] .= trim($tpl->parse('tags', $t1));
		}
		
		$usersData = $this->object('users.vb')->users($post['user_id']);
		$userInfo = isset($usersData[$post['user_id']]) ? $usersData[$post['user_id']] : array();
		$userNick = !empty($userInfo['nick']) ? $userInfo['nick'] : '';
		$userAvatar = !empty($userInfo['avatar']) ? $userInfo['avatar'] : '';

		$tools = &moon::shared('tools');
		$m = array(
			'title' => $postTitle,
			'body' => $post['body'],
			'tags' => implode(', ', $t),
			'date' => $loc->datef($post['created_on'], 'BlogPostList'),
			'commentsCount' => $post['disable_comments'] ? 0 : $post['comm_count'],
			'is_hidden' => $post['is_hidden'],
			'isBlogOwner' => $isBlogOwner,

			'userNick' => htmlspecialchars($userNick),
			'userAvatarSrc' => $userAvatar ? $userAvatar : $this->get_var('srcDefaultAvatar'),
			'url.userBlog' => $this->linkas('#user') . $userNick . '/',

			'url.blog' => $this->linkas('#'),
			'url.post' => $this->linkas('#', $post['uri']),
			'url.edit' => $this->linkas('posts_edit#edit',$post['id']),
			'url.delete' => $this->linkas('posts_edit#delete',$post['id']),
			
			'notPrint' => !$print,
			'comments' => '',
			
			'share' => $tools->toolbar()
		);

		$m['url.comments'] = ($m['comm_count'] = $post['comm_count']) ? $m['url.post'] . '#comm-list' : '';
		$m['commentsWord'] = $m['comm_count'] == 1 ? 'comment' : 'comments';

		$commentsComp = &$this->object('blogcomments');
		if (!$post['disable_comments'] && is_object($commentsComp)) $m['comments'] = $commentsComp->show($post['id']);
		else $m['comments'] = '';

		$page->set_local('year', $vars['year']);
		$page->set_local('month', $vars['month']);
		$page->insert_html($blog->htmlRightColumn(), 'column');

		return $tpl->parse('viewPost', $m);
	}
	
	function htmlList($vars)
	{
		$blog = $this->blog;
		
		$page = &moon::page();
		$user = moon::user();
		$sitemap = moon::shared('sitemap');

		$page->js('/js/jquery/lightbox-0.5.js');
		$page->css('/js/jquery/lightbox-0.5.css');
		
		$tpl = $this->load_template();
		$loc = &moon::locale();

		$m = array(
			'title' => '',

			'items' => '',
			'paging' => '',
			'url.rss' => $this->linkas('#') . 'rss.xml',

			'userHasBlog' => $blog->userHasBlog(),

			'onLatest' => $this->uri == '',
			'onMostViewed' => $this->uri == $this->uriMostViewed,
			'onMyBlog' => $blog->isBlogOwner(),
			'notOnUserBlog' => '' == $blog->get('user_id'),

			'url.blogs' => $this->linkas('#'),
			'url.mostViewed' => $this->linkas('#' . $this->uriMostViewed),
			'url.myBlog' => $this->linkas('#user') . $user->get('nick') . '/'
		);
		$page->head_link($this->linkas('#rss'), 'rss');
		
		$year = $vars['year'];
		$month = $vars['month'];
		$day = $vars['day'];

		$pageTitle = '';

		$info = $tpl->parse_array('info');
		
		if ($m['onMostViewed']) {
			$pageTitle = $info['titleMostViewed'];
			$sitemap->breadcrumb(array($m['url.mostViewed'] => $info['titleMostViewed']));
		}

		if ($m['onMyBlog']) {
			$pageTitle = $info['titleMyBlog'];
			$sitemap->breadcrumb(array($m['url.myBlog'] => $info['titleMyBlog']));
		}

		// path start
		if ($year) {
			$path = array();
			$path[$this->linkas('#' . $year)] = $year;

			if ($month) {
				$path[$this->linkas('#' . $year . '/' . $month)] = date('F', strtotime($year . '-' . $month));
			}

			$sitemap->breadcrumb($path);
		}

		
		$pathTitles = array();
		$bcr = $sitemap->breadcrumb();
		foreach ($bcr as $b) {
			$pathTitles[] = $b['title'];
		}
		if (!empty($pathTitles)) $pageTitle = htmlspecialchars(implode(' | ', $pathTitles));
		// path end

		if ($m['onLatest']) {
			$pageTitle = $info['titleLatest'];
		}

		$m['title'] = $pageTitle;
		$page->title($pageTitle);
		$page->head_link($m['url.rss'], 'rss', $pageTitle);

		$pagingUri = $this->uri;
		$isBlogOwner = $blog->get('isBlogOwner');

		$tag = '';
		if (!empty($vars['tag'])) {
			$tag = $vars['tag'];
			$pagingUri .= '/tags/' . urlencode($tag);
		}

		if($count = $this->getItemsCount($tag, $year, $month, $day)) {
			$m['paging'] = $this->getPaging($vars['currPage'], $count, $vars['listLimit'], $pagingUri);

			$items = $this->getItems($tag, $year, $month, $day);
			$userIds = array();
			foreach ($items as $item) {
				$userIds[$item['user_id']] = 1;
			}
			$usersData = $this->object('users.vb')->users(array_keys($userIds));

			$defaultAvatarSrc = $this->get_var('srcDefaultAvatar');
			foreach ($items as $item) {

				$item['title'] = htmlspecialchars($item['title']);
				$item['date'] = $loc->datef($item['created_on'], 'BlogPostList');

				$item['isBlogOwner'] = $isBlogOwner;
				
				$userInfo = isset($usersData[$item['user_id']]) ? $usersData[$item['user_id']] : array();
				$userNick = !empty($userInfo['nick']) ? $userInfo['nick'] : '';
				$userAvatar = !empty($userInfo['avatar']) ? $userInfo['avatar'] : '';
				$item['userNick'] = htmlspecialchars($userNick);
				$item['userAvatarSrc'] = $userAvatar ? $userAvatar : $defaultAvatarSrc;
				$item['url.userBlog'] = $this->linkas('#user') . $userNick . '/';

				$item['url.post'] = $this->linkas('#', $item['uri']);
				$item['url.edit'] = $this->linkas('posts_edit#edit',$item['id']);
				$item['url.delete'] = $this->linkas('posts_edit#delete',$item['id']);
				
				$item['url.comments'] = $item['comm_count'] ? $item['url.post'] . '#comm-list' : '';
				$item['commentsWord'] = $item['comm_count'] == 1 ? 'comment' : 'comments';

				$item['notOnUserBlog'] = $m['notOnUserBlog'];

				$m['items'] .= $tpl->parse('items', $item);
				
			}
		}
		
		$page->set_local('year', $vars['year']);
		$page->set_local('month', $vars['month']);
		$page->insert_html($blog->htmlRightColumn(), 'column');

		return $tpl->parse('viewList', $m);
	}
	
	function getItems($tag = '', $year = 0, $month = 0, $day = 0)
	{
		$sql = 'SELECT id, user_id, title, created_on, body_short, uri, tags, comm_count, is_hidden, disable_comments, rating
			FROM ' . $this->table('Posts') . ' ' .
			$this->sqlWhere($tag, $year, $month, $day) . ' ' .
			$this->sqlOrder . ' ' .
			$this->sqlLimit();
		return $this->db->array_query_assoc($sql);
	}
	
	function getItemsCount($tag = '', $year = 0, $month = 0, $day = 0)
	{
		$sql = 'SELECT count(*)
			FROM ' . $this->table('Posts') . ' ' .
			$this->sqlWhere($tag, $year, $month, $day);
		$m = $this->db->single_query($sql);
		return (count($m) ? $m[0] : 0);
	}
	
	function getPostByUri($uri = FALSE)
	{
		$post = array();
		if ($uri) {
			$sql = 'SELECT id, user_id, title, created_on, body, uri, tags, comm_count, is_hidden, disable_comments, rating
				FROM ' . $this->table('Posts') . ' ' .
				$this->sqlWhere('', 0, 0, 0, $uri);
			$post = $this->db->single_query_assoc($sql);
		}
		return $post;
	}
	
	/**
	 * Sets sql where condition. Used in posts list
	 * @return string
	 */
	function sqlWhere($tag = '', $year = 0, $month = 0, $day = 0, $uri = '')
	{
		if (!isset($this->tmpWhere)) {
			$w = array();
			
			if ($this->blog->get('user_id')) $w[] = 'user_id = ' . $this->blog->get('user_id');

			if ($this->blog->get('isBlogOwner')) {
				$w[] = 'is_hidden < 2';
			} else {
				$w[] = 'is_hidden = 0';
			}
			
			if($tag) {
				$w[] = ' FIND_IN_SET(\'' . $this->db->escape($tag) . '\', tags) ';
			}
			
			if($uri) {
				$w[] = 'uri = "' . $this->db->escape($uri) . '"';
			}
			
			if ($year > 2000) {
				$monthFrom = $month;
				$monthTo = $month;
				
				if ($month > 0 && $day > 0) {
					$from = mktime(0, 0, 0, $month, $day, $year);
					$to = strtotime('+1 day', $from);
				} elseif ($month > 0) {
					$from = mktime(0, 0, 0, $month, 1, $year);
					$to = strtotime('+1 month', $from);
				} else {
					$from = mktime(0, 0, 0, 1, 1, $year);
					$to = strtotime('+1 year', $from);
				}
				$w[] = '(' . $from . ' <= created_on AND created_on < ' . $to . ')';
			}
			
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
	//        --- RSS FEEDS, XML ---
	//***************************************
	function rssXml($feedType = 'rss')
	{
		$page = &moon::page();
		$blog = $this->blog;
		
		$homeURL = rtrim($page->home_url(), '\/');
		$feedUrl = $homeURL . $this->linkas('#');
		$link = $feedUrl.'rss/';
		
		$xml = &moon::shared('rss');
		$content = $xml->feed($feedUrl, $feedType, FALSE);
		
		if ($content === FALSE) {
			
			// feed info
			$xml->info(
				array(
					'title' => 'PokerNetwork Blogs',
					'description' => '',
					'url:page' => $link,
					'author' => 'PokerNetwork Blogs'
				)
			);
			
			// feed items
			$items = $this->getRssFeedItems();
			foreach($items as $item) {
				$xml->item(
					array(
						'title' => $item['title'],
						'url' => $homeURL . $this->linkas('#', $item['uri']),
						'created' => $item['created_on'],
						'updated' => $item['updated_on'],
						'summary' => $item['body']
					)
				);
			}
			// gets content
			$content = $xml->content();
		}
		
		//outputinam kontenta
		$xml->header();
		return $content;
	}
	
	function getRssFeedItems()
	{
		$sql = 'SELECT id,title,uri,body,created_on,updated_on
			FROM ' . $this->table('Posts') . 
			$this->sqlWhere() . '
			ORDER BY created_on DESC, title ASC
			LIMIT 10';
		return $this->db->array_query_assoc($sql);
	}
	
	//***************************************
	//          --- OTHER ---
	//***************************************
	function updateViewsCount($id = null)
	{
		if (!$id) return false;
		$this->db->query('
			UPDATE LOW_PRIORITY ' . $this->table('Posts') . '
			SET views_count=views_count+1 WHERE id = ' . intval($id)
		);
	}
	
}

?>