<?php
class video extends moon_com {

	function onload()
	{
		$this->tblVideos = $this->table('Videos');
		$this->tblVideosPlaylists = $this->table('VideosPlaylists');
	}

	function events($event, $par)
	{
		$page = moon::page();
		if ($uri = $page->requested_event('REST')) {
			$segments = $page->requested_event('segments');
			$cnt = count($segments);

			if (substr($uri, - 4) === '.htm') {
				//video uri
				$uriChunks = explode('-', $uri);
				$chunksCnt = count($uriChunks);
				$videoId = '';
				if ($chunksCnt > 1) {
					$videoId = str_replace('.htm', '', $uriChunks[$chunksCnt - 1]);
				}

				if (!empty($videoId)) {
					$video = $this->getVideoById(sprintf("%.0f",$videoId));
					if (empty($video)) $page->page404();
					$this->updateViewsCount($videoId);
					$this->set_var('video', $video);
					$this->set_var('view', 'video');
				} else 	$page->page404();
			} elseif (substr($uri, - 7) === 'rss.xml') {
				// rss xml uri
				$uri = trim($uri, '/');
				$uriChunks = explode('/', $uri);
				$cnt = count($uriChunks);
				$playlistUri = '';
				if ($cnt === 2) {
					$playlistUri = $uriChunks[0];
				} elseif ($cnt > 2) {
					$page->page404();
				}

				$twitter = FALSE;

				$playlistId = 0;
				$playlistTitle = '';
				if ($playlistUri) {
					$playlist = $this->getPlaylistByUri($playlistUri);
					if (isset($playlist['id'])) $playlistId = $playlist['id'];
				} else {
					if (isset($_GET['twitter'])) {
						$twitter = TRUE;
					}
				}

				$limit = 20;
				if (isset($_GET['l']) && is_numeric($_GET['l'])) {
					$limit =
						($_GET['l'] > 0 && $_GET['l'] < 100)
						? $_GET['l']
						: $limit;
				}

				print $this->rssXml($playlistId, $playlistUri, $playlistTitle, $limit, $twitter);
				moon_close();
				exit;
			} elseif (substr($uri, 0, 3) === 'tag') {
				// tag uri
				$tagUri = trim($uri, '/');
				$uriChunks = explode('/', $tagUri);
				$cnt = count($uriChunks);
				if ($cnt === 2) {
					if (substr($uri, -1) !== '/') {
						//gale truksta /
						$page->redirect($this->linkas("#$uri"), 301);
					}
					$tag = urldecode($uriChunks[1]);

					$ctags = $this->object('other.ctags');
					$newUrl = $ctags->getUrl($tag, $src = 'videos');
					$page->redirect($newUrl, 301);

					$this->set_var('tag', $tag);
					$this->set_var('view', 'tag');
				} else {
					$page->page404();
				}
			} elseif(trim($uri,'/') === 'latest-videos') {
				$this->set_var('view', 'latest');
			} elseif ($cnt === 2 && is_numeric($segments[0]) && $segments[0] > 2000 && $segments[1] == '') {
				// year archive
				$year = intval($segments[0]);
				if (checkdate(1, 1, $year)) {
					$this->set_var('year', $year);
					$this->set_var('view', 'archive');
				} else {
					$page->page404();
				}
				
			} elseif ($cnt === 3 && is_numeric($segments[0]) && $segments[0] > 2000 && is_numeric($segments[1]) && checkdate($segments[1], 1, 1) && $segments[2] == '' ) {
				//month archive
				$year = intval($segments[0]);
				$month = intval($segments[1]);
				
				if (checkdate($month, 1, $year)) {
					$this->set_var('year', $year);
					$this->set_var('month', $month);
					$this->set_var('view', 'archive');
				} else {
					$page->page404();
				}
				
			} else {
				// playlist uri
				$playlistUri = trim($uri, '/');
				$playlist = substr_count($playlistUri, '/') ? array() : $this->getPlaylistByUri($playlistUri);
				if (empty($playlist)) {
					//neteisingas urlas
					$page->page404();
				} elseif (strlen($uri) - strlen($playlistUri) != 1) {
					//gale truksta /
					$page->redirect($this->linkas("#$playlistUri"), 301);
				}
				$this->set_var('playlist', $playlist);
				$this->set_var('view', 'playlist');
				//else: automatiskai rodoma kategorija
			}
		} else {
			$this->set_var('view', 'mainPage');
		}

		$this->setPaging();
		$this->use_page('Main');
	}

	function properties()
	{
		return array(
			'view' => '',
			'playlist' => array(),
			'video' => array(),
			'tag' => '',
			'currPage' => 1,
			'listLimit' => 20,
			'year' => 0,
			'month' => 0
		);
	}

	function main($vars)
	{
		$output = '';
		switch ($vars['view']) {
			case 'homepage-box':
				$output = $this->htmlHomepageBox($vars);
				break;
			case 'reporting-box':
				$playlistId = !empty($vars['playlistId']) ? $vars['playlistId'] : 10005954001;
				$output = $this->htmlReportingBox($playlistId);
				break;
			case 'video':
				$output = $this->htmlVideo($vars);
				break;
			case 'tag':
			case 'playlist':
			case 'latest':
			case 'archive':
				$output = $this->htmlList($vars);
				break;
			default:
				$output = $this->htmlMain($vars);
				break;
		}
		return $output;
	}

	function htmlMain()
	{
		$tpl = $this->load_template();
		$page = &moon::page();
		$sitemap = moon::shared('sitemap');
		$locale = &moon::locale();
		$txt = &moon::shared('text');
		$tools = &moon::shared('tools');

		$page->css('/css/article.css');

		$txt->agoMaxMin = 60*24*30; // 30 days

		$m = array(
			'items:latest' => '',
			'items:popularWeek' => '',
			'items:popularMonth' => '',
			'title' => $sitemap->getTitle(),
			'uri.self' => $this->linkas('#'),
			'uri.latest' => $this->linkas('#latest-videos'),
			'uri.rss' => $this->linkas('#') . 'rss.xml'
		);

		$pageData = $sitemap->getPage();
		if (!empty($pageData)) {
			$m['description'] = $pageData['content_html'];
		}
		$page->head_link($m['uri.rss'], 'rss', $m['title']);

		$page->insert_html($this->htmlCategoriesBox(), 'column');
		$page->insert_html($this->htmlArchiveBox(), 'column');

		// latest videos
		$itemsLatest = $this->getLatestItems();

		$featuredVideo = array_shift($itemsLatest);

		if (!empty($featuredVideo)) {
			$uri = $this->getVideoUri($featuredVideo['id'], $featuredVideo['name']);
			$timeShorts = $locale->get_array('time.shorts');
			$minStr = (isset($timeShorts[0])) ? $timeShorts[0] : 'min.';

			// tags
			$tags = explode(',', $featuredVideo['tags']);
			$t = array();
			$tagsList = '';
			if (count($tags)) {
				$ctags = $this->object('other.ctags');
				foreach ($tags as $tag) {
					$item = array();
					$item['tagName'] = htmlspecialchars($tag);
					$item['uri.tag'] = $ctags->getUrl($tag, $src = 'videos');
					$t[] .= trim($tpl->parse('tags', $item));
				}
			}

			$m['videoId'] = $featuredVideo['id'];
			$m['playerId'] = $this->get_var('playerId');
			$m['name'] = htmlspecialchars($featuredVideo['name']);
			$m['uri.featured'] = $this->linkas('#', $uri);
			$m['publishedDate'] = $locale->datef(substr($featuredVideo['published_date'], 0, 10), 'News');
			$m['length'] = $this->miliSecToTime($featuredVideo['length']) . ' ' . $minStr;
			$m['description'] = htmlspecialchars($featuredVideo['short_description']);
			$m['tags'] = implode(' &clubs; ', $t);
			$m['url.comments'] = ($m['comm_count'] = $featuredVideo['comm_count']) ? $m['uri.featured'].'#cl' : '';
			$m['shareThis'] = $tools->toolbar(array('url'=>$m['uri.featured']));

			$page->js('http://admin.brightcove.com/js/BrightcoveExperiences.js');
		}

		foreach ($itemsLatest as $item) {
			$uri = $this->getVideoUri($item['id'], $item['name']);

			$time = substr($item['published_date'], 0, 10);
			$ago = $txt->ago($time, TRUE, TRUE, FALSE);
			$item['date'] = '';
			if ($time) {
				$item['date'] = ($ago) ? $ago : $locale->datef($time, 'News');
			}

			$item['url.video'] = $this->linkas('#', $uri);
			$item['title'] = htmlspecialchars($item['name']);
			$item['thumbSrc'] = $item['thumbnail_url'];
			$item['length'] = $this->miliSecToTime($item['length']);
			$item['url.comments'] = $item['comm_count'] ? $item['url.video'] . '#cl' : '';
			$m['items:latest'] .= $tpl->parse('items:latest', $item);
		}

		// most popular this month videos
		$items = $this->getPopularMonthItems();
		foreach ($items as $item) {
			$uri = $this->getVideoUri($item['id'], $item['name']);

			$time = substr($item['published_date'], 0, 10);
			$ago = $txt->ago($time, TRUE, TRUE, FALSE);
			$item['date'] = '';
			if ($time) {
				$item['date'] = ($ago) ? $ago : $locale->datef($time, 'News');
			}

			$item['url.video'] = $this->linkas('#', $uri);
			$item['title'] = htmlspecialchars($item['name']);
			$item['thumbSrc'] = $item['thumbnail_url'];
			$item['length'] = $this->miliSecToTime($item['length']);
			$item['url.comments'] = $item['comm_count'] ? $item['url.video'] . '#cl' : '';
			$m['items:popularMonth'] .= $tpl->parse('items:popularMonth', $item);
		}

		return $tpl->parse('viewMain', $m);
	}

	function htmlVideo($vars)
	{
		$page = &moon::page();
		if (!count($video = $vars['video'])) {
			//jei neradom
			$page->page404();
		}

		$tpl = $this->load_template();
		$locale = &moon::locale();
		$tools = &moon::shared('tools');
		$sitemap = moon::shared('sitemap');
		$txt = &moon::shared('text');

		$txt->agoMaxMin = 60*24*30; // 30 days

		$page->js('http://admin.brightcove.com/js/BrightcoveExperiences.js');
		$page->css('/css/article.css');

		$tags = explode(',', strtolower($video['tags']));

		// insert categories box
		$page->insert_html($this->htmlCategoriesBox(), 'column');
		$page->insert_html($this->htmlArchiveBox(), 'column');

		$timeShorts = $locale->get_array('time.shorts');
		$minStr = (isset($timeShorts[0])) ? $timeShorts[0] : 'min.';

		// related
		$relatedVideos = $this->getVideosRelated($video['id'], $video['tags'], $video['playlist_ids']);
		$relatedList = '';
		foreach ($relatedVideos as $v) {
			$item = array();

			$uri = $this->getVideoUri($v['id'], $v['name']);

			$time = substr($v['published_date'], 0, 10);
			$ago = $txt->ago($time, TRUE, TRUE, FALSE);
			$item['date'] = '';
			if ($time) {
				$item['date'] = ($ago) ? $ago : $locale->datef($time, 'News');
			}

			$item['title'] = htmlspecialchars($v['name']);
			$item['uri.video'] = $this->linkas('#', $uri);
			$item['thumbSrc'] = $v['thumbnail_url'];
			$item['length'] = $this->miliSecToTime($v['length']);
			$item['url.comments'] = ($item['comm_count'] = $v['comm_count']) ? $item['uri.video'] . '#cl' : '';
			$relatedList .= $tpl->parse('related', $item);
		}

		// latest
		$latestVideos = $this->getLatestItems(10, $video['id']);
		$latestList = '';
		foreach ($latestVideos as $v) {
			$item = array();

			$uri = $this->getVideoUri($v['id'], $v['name']);

			$time = substr($v['published_date'], 0, 10);
			$ago = $txt->ago($time, TRUE, TRUE, FALSE);
			$item['date'] = '';
			if ($time) {
				$item['date'] = ($ago) ? $ago : $locale->datef($time, 'News');
			}

			$item['title'] = htmlspecialchars($v['name']);
			$item['uri.video'] = $this->linkas('#', $uri);
			$item['thumbSrc'] = $v['thumbnail_url'];
			$item['length'] = $this->miliSecToTime($v['length']);
			$item['url.comments'] = ($item['comm_count'] = $v['comm_count']) ? $item['uri.video'] . '#cl' : '';
			$latestList .= $tpl->parse('latest', $item);
		}

		// tags
		$tags = explode(',', $video['tags']);
		$t = array();
		$tagsList = '';
		if (count($tags)) {
			$ctags = $this->object('other.ctags');
			foreach ($tags as $tag) {
				$item = array();
				$item['tagName'] = htmlspecialchars($tag);
				$item['uri.tag'] = $ctags->getUrl($tag, $src = 'videos');
				$t[] .= trim($tpl->parse('tags', $item));
			}
		}

		$page->title($video['name']);
		$page->meta('keywords', $video['name'] . ',' . $video['tags']);
		$page->meta('description', $video['short_description']);

		// facebook like button specific tags
		$page->meta('video_width', '435');
		$page->meta('video_height', '370');
		$page->meta('video_type', 'application/x-shockwave-flash');
		$page->meta('medium', 'video');
		$imgSrc = strpos($video['thumbnail_url'], 'http') !== false ? $video['thumbnail_url'] : rtrim($page->home_url(), '/') . $video['thumbnail_url'] . '?t=' . time();
		$videoSrc = 'http://c.brightcove.com/services/viewer/federated_f9/69609817001?isVid=1&amp;isUI=1&amp;autoStart=1&amp;dynamicStreaming=1&amp;publisherID=1544546948&amp;playerID=69609817001&amp;domain=embed&amp;videoId=' . $video['id'];
		$page->head_link($imgSrc, 'image_src');
		$page->head_link($videoSrc, 'video_src');

		$sitemap->breadcrumb(array('' => $video['name']));

		$uri = $this->getVideoUri($video['id'], $video['name']);

		$m = array(
			'videoId' => $video['id'],
			'playerId' => $this->get_var('playerId'),
			'name' => htmlspecialchars($video['name']),
			'uri.self' => $this->linkas('#', $uri),
			'uri.latest' => $this->linkas('#latest-videos'),
			'publishedDate' => $locale->datef(substr($video['published_date'], 0, 10), 'News'),
			'length' => $this->miliSecToTime($video['length']) . ' ' . $minStr,
			'description' => ($video['long_description']) ? htmlspecialchars($video['long_description']) : htmlspecialchars($video['short_description']),
			'related' => $relatedList,
			'latest' => $latestList,
			'tags' => implode(' &clubs; ', $t),
			'comments' => '',
			'shareThis' => $tools->toolbar(),
			'fbLike' => $tools->fbLikeWst()
		);

		$playlists = $this->getPlaylists();
		$playlistIds = explode(',',$video['playlist_ids']);
		$s = '';
		foreach ($playlistIds as $id) {
			$catName = isset($playlists[$id]) && !empty($playlists[$id]['name']) ? json_encode($playlists[$id]['name']) : '';
			if ($catName) {
				$s .= "_gaq.push(['_setCustomVar',1,'video',$catName,3]);";
			}
		}
		if ($s) $page->set_local('_gaq', $s);

		// comments
		$homeUrl = rtrim($page->home_url(), '/');
		if (is_dev()) {
			$homeUrl = str_replace('.dev', '.com', $homeUrl);
		}
		$iCard = array();
		$iCard['title'] = $video['name'];
		$imgSrc = strpos($video['thumbnail_url'], 'http') !== false ? $video['thumbnail_url'] : $homeUrl . $video['thumbnail_url'] . '?t=' . time();
		$iCard['img'] = $imgSrc;
		$iCard['url'] = $homeUrl . $m['uri.self'];
		$iCard['description'] = $video['long_description'] ? $video['long_description'] : $video['short_description'];
		$commentsComp = &$this->object('comments');
		if (is_object($commentsComp)) {
			$m['comments'] = $commentsComp->show($video['id'], $iCard);
		}
		$m['url.comments'] = ($m['comm_count'] = $video['comm_count']) ? $m['uri.self'] . '#cl' : '';

		return $tpl->parse('viewVideo', $m);
	}

	function htmlList($vars)
	{
		$tpl = $this->load_template();
		$page = &moon::page();
		$sitemap = moon::shared('sitemap');
		$locale = &moon::locale();
		$txt = &moon::shared('text');

		//$page->css('/css/article.css');

		$txt->agoMaxMin = 60*24*30; // 30 days

		$m = array(
			'items' => '',
			'title' => $sitemap->getTitle(),
			'uri.self' => $this->linkas('#'),
			'uri.rss' => $this->linkas('#') . 'rss.xml'
		);

		$year = $vars['year'];
		$month = $vars['month'];
		$pagingUri = '';
		$pageTitle = '';

		// path
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

		$m['title'] = $pageTitle;
		$page->title($pageTitle);

		$playlistID = 0;
		$playlistUri = '';
		if (count($playlist = $vars['playlist'])) {
			$playlistID = $playlist['id'];
			$playlistUri = $playlist['uri'];
			$pagingUri = $playlistUri;

			$page->title($playlist['name'] . ' | ' . $m['title']);
			$page->meta('keywords', $playlist['name']);
			$page->meta('description', $playlist['short_description']);

			$m['title'] = htmlspecialchars($playlist['name']);
			$m['description'] = htmlspecialchars($playlist['short_description']);
			$m['uri.self'] = $this->linkas('#' . $playlistUri);
			$m['uri.rss'] = $m['uri.self'] . 'rss.xml';
		} else {
			$pageData = $sitemap->getPage();
			if (!empty($pageData)) {
				$m['description'] = $pageData['content_html'];
			}
			if ($vars['view'] === 'latest') {

				$info = $tpl->parse_array('info');
				$m['title'] = $info['latestVideos'];
				$m['uri.self'] = $this->linkas('#latest-videos');

				$page->title($m['title']);
				$page->meta('description', $info['latestVideos']);
				$pagingUri = 'latest-videos';
			}
		}

		$page->head_link($m['uri.rss'], 'rss', $m['title']);

		$page->insert_html($this->htmlCategoriesBox($playlistID), 'column');
		$page->insert_html($this->htmlArchiveBox($year, $month), 'column');

		// generate list
		if ($count = $this->getListCount($playlistID, false, $year, $month)) {
			$m['paging'] = $this->getPaging($vars['currPage'], $count, $vars['listLimit'], $pagingUri);
			if ($vars['currPage'] > 1) {
				$page->meta('robots', 'noindex,follow');
			} else {
				$robots = 'index,follow';
			}

			$items = $this->getList($playlistID, false, $year, $month);

			foreach ($items as $item) {
				$uri = $this->getVideoUri($item['id'], $item['name']);

				$time = substr($item['published_date'], 0, 10);
				$ago = $txt->ago($time, TRUE, TRUE, FALSE);
				$item['date'] = '';
				if ($time) {
					$item['date'] = ($ago) ? $ago : $locale->datef($time, 'News');
				}

				$item['url.video'] = $this->linkas('#', $uri);
				$item['title'] = htmlspecialchars($item['name']);
				$item['thumbSrc'] = $item['thumbnail_url'];
				$item['length'] = $this->miliSecToTime($item['length']);

				$item['url.comments'] = $item['comm_count'] ? $item['url.video'] . '#cl' : '';

				$m['items'] .= $tpl->parse('items', $item);
			}
		}

		if ($playlistID || $vars['view'] === 'latest') {
			$sitemap->breadcrumb(array($m['uri.self'] => $m['title']));
		}

		return $tpl->parse('viewList', $m);
	}

	function htmlCategoriesBox($playlistId = 0)
	{
		$tpl = $this->load_template();
		$m = array('box:items' => '');
		$playlists = $this->getPlaylists();
		$output = '';
		if (!empty($playlists)) {
			foreach ($playlists as $d) {
				$d['url'] = $this->url('#' . $d['uri']);
				$d['title'] = htmlspecialchars($d['name']);
				$d['current'] = $playlistId == $d['id'] ? 1 : 0;
				$m['box:items'] .= $tpl->parse('box:items', $d);
			}

			$sitemap = &moon::shared('sitemap');
			if ($playlistId && isset ($playlists[$playlistId])) {
				$c = & $playlists[$playlistId];
				$sitemap->breadcrumb(array($this->url('#' . $c['uri']) => $c['name']));
			}
			$output = $tpl->parse('viewBox', $m);
		}
		return $output;
	}

	function htmlArchiveBox($activeYear = 0, $activeMonth = 0)
	{
		$tpl = $this->load_template();
		$loc = moon::locale();

		$archiveData = $this->getArchiveData();

		$activeYear = $activeYear ? $activeYear : max(array_keys($archiveData));

		$yearsList = '';
		foreach ($archiveData as $year=>$months) {
			krsort($months);
			$monthsList = '';
			$yCount = 0;
			foreach ($months as $month=>$cnt) {
				$monthsList .= $tpl->parse('months:items', array(
					'url.month' => $this->linkas('#' . $year . '/' . $month),
					'mName' => $loc->datef(strtotime($year.'-'.$month), '%{m}'),
					'mCount' => $cnt,
					'active' => $year == $activeYear && $month == $activeMonth
				));
				$yCount += $cnt;
			}
			$yearsList .= $tpl->parse('years:items', array(
				'months:items' => $monthsList,
				'yName' => $year,
				'yCount' => $yCount,
				'url.year' => $this->linkas('#' . $year),
				'expand' => $year == $activeYear
			));
		}
		return $tpl->parse('archive', array('years:items' => $yearsList));
	}

	function getArchiveData()
	{
		$now = (floor(moon::locale()->now() / 300) * 300);
		$sql = '
			SELECT FROM_UNIXTIME(published_date DIV 1000, \'%Y-%M\') as short_date, FROM_UNIXTIME(published_date DIV 1000, \'%Y\') as year, FROM_UNIXTIME(published_date DIV 1000, \'%m\') as month, count(*) as cnt
			FROM ' . $this->tblVideos . ' 
			WHERE 
				published_date < ' . $now . '000 AND
				is_hidden = 0
			GROUP BY short_date
			ORDER BY year DESC, month DESC';
		$result = $this->db->array_query_assoc($sql);

		$items = array();
		foreach ($result as $r) {
			$items[$r['year']][$r['month']] = $r['cnt'];
		}

		return $items;
	}

	function htmlHomepageBox($vars)
	{
		$page = moon::page();
		$page->js('http://admin.brightcove.com/js/BrightcoveExperiences.js');

		$videoRootUri = '';
		$sitemap = moon::shared('sitemap');
		$videoRootUri = $sitemap->getLink('video');

		$m = array(
			'url.more' => $videoRootUri,
			'playerId' => $this->getIndexPlayerId()
		);
		$size = !empty($vars['size']) ? $vars['size'] : '228x282';
		list($m['width'], $m['height']) = explode('x', $size, 2);
		$tpl = $this->load_template();
		return $tpl->parse('homepageBox', $m);
	}

	function htmlReportingBox($playlistId = 10005954001)
	{
		$page = moon::page();
		$page->js('http://admin.brightcove.com/js/BrightcoveExperiences.js');

		$videoRootUri = '';
		$sitemap = moon::shared('sitemap');
		$videoRootUri = $sitemap->getLink('video');

		$m = array(
			'url.more' => $videoRootUri,
			'playlistId' => $playlistId,
			'playerId' => $this->getIndexPlayerId()
		);
		//$m['wsopeBanner'] = 'com' == _SITE_ID_ && $page->uri_segments(1) == 'live-reporting' && $page->uri_segments(2) == '2010-wsop';
		$m['wsopeBanner'] = 0;
		//$m['cil'] = 'com' == _SITE_ID_ && $page->uri_segments(1) == 'live-reporting' && $page->uri_segments(3) == 'event-58-no-limit-hold-em-championship';
		$m['cil'] = 0;
		$tpl = $this->load_template();
		return $tpl->parse('reportingBox', $m);
	}

	//***************************************
	//           --- DB ---
	//***************************************
	function getVideoById($id)
	{
		$sql = 'SELECT id,name,short_description,long_description,length,published_date,tags,playlist_ids,thumbnail_url,comm_count
			FROM ' . $this->table('Videos') . '
			WHERE 	id = ' . $this->db->escape($id) . ' AND
				is_hidden = 0';
		$result = $this->db->single_query_assoc($sql);
		return $result;
	}

	function getVideosRelated($id = 0, $tags = '', $playlistIds = '', $limit = 10)
	{
		$if = array();
		if ($playlistIds !== '') {
			$playlistIds = explode(',', $playlistIds);
			foreach ($playlistIds as $pId) {
				$if[] = "IF(FIND_IN_SET('" . $this->db->escape($pId) . "', playlist_ids), 2, 0)";
			}
		}
		if ($tags !== '') {
			$tags = explode(',', $tags);
			foreach ($tags as $tag) {
				$if[] = "IF(FIND_IN_SET('" . $this->db->escape($tag) . "', tags), 1, 0)";
			}
		}

		$w = array();
		if ($id) {
			$w[] = 'id <> ' . $this->db->escape($id);
		}
		$w[] = 'is_hidden = 0';
		$where = count($w) ? (' WHERE ' . implode(' AND ', $w)) : '';

		$strSelect = ', ' . implode(' + ', $if) . ' as weight';
		$sql = 'SELECT	id,name,thumbnail_url,length,comm_count,published_date ' . $strSelect . '
			FROM ' . $this->table('Videos') . '
			' . $where . '
			ORDER BY weight DESC, published_date DESC
			LIMIT ' . intval($limit);
		return $this->db->array_query_assoc($sql);
	}

	function getVideosByTags($tags = '', $limit = 10)
	{
		$w = array();
		$w[] = 'is_hidden = 0';
		if ($tags !== '') {
			$tags = explode(',', $tags);
			$t = array();
			foreach ($tags as $tag) {
				if(!$tag) continue;
				$t[] = ' FIND_IN_SET(\'' . $this->db->escape($tag) . '\', tags) ';
			}
			$w[] = count($t) ? ('( ' . implode(' OR ', $t)) . ')' : '';
		}
		$where = count($w) ? (' WHERE ' . implode(' AND ', $w)) : '';

		$sql = 'SELECT	id,name,thumbnail_url,length,published_date,comm_count
			FROM ' . $this->table('Videos') . '
			' . $where . '
			ORDER BY published_date DESC
			LIMIT ' . intval($limit);
		return $this->db->array_query_assoc($sql);
	}

	function getPlaylists()
	{
		if (!isset($this->tmpPlaylists)) {
			$sql = 'SELECT id,name,uri
				FROM ' . $this->table('VideosPlaylists') . '
				WHERE is_hidden = 0
				ORDER BY sort_order ASC';
			$this->tmpPlaylists = $this->db->array_query_assoc($sql,'id');
		}
		return $this->tmpPlaylists;
	}

	function getListCount($playlistID = FALSE, $tag = FALSE, $year = 0, $month = 0)
	{
		$sql = 'SELECT count(*)
			FROM ' . $this->tblVideos . $this->sqlWhere($playlistID, $tag, $year, $month);
		$m = $this->db->single_query($sql);
		return (count($m) ? $m[0] : 0);
	}

	function getList($playlistID = FALSE, $tag = FALSE, $year = 0, $month = 0)
	{
		$sql = 'SELECT id,name,thumbnail_url,length,published_date,comm_count
			FROM ' . $this->tblVideos . $this->sqlWhere($playlistID, $tag, $year, $month) . '
			ORDER BY published_date DESC ' .
			$this->sqlLimit();
		return $this->db->array_query_assoc($sql);
	}

	function getLatestItems($limit = 11, $id = 0)
	{
		$sql = 'SELECT id,name,thumbnail_url,length,published_date,comm_count,tags,short_description
			FROM ' . $this->tblVideos . $this->sqlWhere() . ($id ? ' AND id <> ' . $id : '') . '
			ORDER BY published_date DESC
			LIMIT ' . $limit;
		return $this->db->array_query_assoc($sql);
	}

	function getPopularMonthItems()
	{
		$l = moon::locale();
		$now = floor($l->now() / 300) * 300;
		$viewsInterval = (string)($now - 3600*24*30) . '000';
		$sql = 'SELECT id,name,thumbnail_url,length,published_date,comm_count
			FROM ' . $this->tblVideos . $this->sqlWhere() . ' AND published_date > ' . $viewsInterval . '
			ORDER BY views_count DESC
			LIMIT 10';
		return $this->db->array_query_assoc($sql);
	}

	function getPlaylistByUri($uri)
	{
		$sql = 'SELECT id,name,uri,short_description
			FROM ' . $this->table('VideosPlaylists') . '
			WHERE 	uri = \'' . $this->db->escape($uri) . '\' AND
				is_hidden = 0';
		return $this->db->single_query_assoc($sql);
	}

	/**
	 * Sets sql where condition. Used in articles list
	 * @param int $catID
	 * @return string
	 */
	function sqlWhere($playlistID = FALSE, $tag = FALSE, $year = 0, $month = 0)
	{
		if (!isset($this->tmpWhere)) {
			$locale = &moon::locale();
			$now = floor($locale->now() / 300) * 300;
			$w = array();

			$w[] = 'is_hidden = 0';

			if ($playlistID) {
				$w[] = 'FIND_IN_SET(' . $this->db->escape($playlistID) . ', playlist_ids)';
			}

			if($tag) {
				$w[] = ' FIND_IN_SET(\'' . $this->db->escape($tag) . '\', tags) ';
			}

			if ($year > 2000) {
				$monthFrom = $month;
				$monthTo = $month;
				
				if ($month > 0) {
					$from = mktime(0, 0, 0, $month, 1, $year);
					$to = strtotime('+1 month', $from);
				} else {
					$from = mktime(0, 0, 0, 1, 1, $year);
					$to = strtotime('+1 year', $from);
				}
				$from .= '000'; // add miliseconds
				$to .= '000';
				$w[] = '(' . $from . ' <= published_date AND published_date < ' . $to . ')';
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

	function updateViewsCount($id = null)
	{
		$id = sprintf("%.0f",$id);
		if (!$id) return false;
		$this->db->query('
			UPDATE LOW_PRIORITY ' . $this->tblVideos . '
			SET views_count=views_count+1 WHERE id = ' . $id
		);
	}

	//***************************************
	//        --- COMMON ---
	//***************************************
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
	function rssXml($playlistId = 0, $playlistUri = '', $playlistTitle = '', $limit = '20', $twitter = FALSE)
	{
		$page = &moon::page();
		$sitemap = &moon::shared('sitemap');

		$homeURL = rtrim($page->home_url(), '\/');
		$rootUri = $sitemap->getLink();
		$feedUrl = $homeURL . $rootUri . $page->requested_event('REST');
		$link = $homeURL . $rootUri . $playlistUri;

		$xml = &moon::shared('rss');
		$content = $xml->feed($feedUrl, 'rss', FALSE);

		if ($content === FALSE) {
			// feed info
			$xml->info(
				array(
					'title' => ($playlistTitle) ? 'Poker Videos - ' . $playlistTitle : 'Poker Videos',
					'description' => '',
					'url:page' => $link,
					'author' => 'Pokernetwork.com'
				)
			);

			// feed items
			if ($twitter) {
				$items = $this->getRssFeedItemsTwitter();
				foreach($items as $item) {
					$uri = $this->getVideoUri($item['id'], $item['name']);
					$url = $homeURL . $this->linkas('#', $uri);

					$title = $item['name'];
					if (strlen($item['name']) > 140) {
						$title = substr($item['name'], 0, 137) . '...';
					}

					$xml->item(
						array(
							'title' => $title,
							'url' => $url,
							'created' => substr($item['published_date'], 0, 10)
						)
					);
				}
			} else {
				$items = $this->getRssFeedItems($playlistId, $limit);
				foreach($items as $item) {
					$uri = $this->getVideoUri($item['id'], $item['name']);
					$xml->item(
						array(
							'title' => $item['name'],
							'url' => $homeURL . $this->linkas('#', $uri),
							'created' => substr($item['published_date'], 0, 10),
							'updated' => substr($item['last_modified_date'], 0, 10),
							'summary' => htmlspecialchars($item['short_description'])
						)
					);
				}
			}
			// gets content
			$content = $xml->content();
		}

		//outputinam kontenta
		$xml->header();
		return $content;
	}

	function getRssFeedItems($playlistId, $limit)
	{
		$sql = 'SELECT id,name,short_description,published_date,last_modified_date
			FROM ' . $this->table('Videos') . '
			WHERE	' . (!empty($playlistId)
					? 'FIND_IN_SET(' . $this->db->escape($playlistId) . ', playlist_ids) AND'
					: ''
				) . '
				is_hidden = 0
			ORDER BY published_date DESC
			LIMIT ' . $limit;
		return $this->db->array_query_assoc($sql);
	}

	function getRssFeedItemsTwitter()
	{
		$sql = 'SELECT id,name,published_date
			FROM ' . $this->table('Videos') . '
			WHERE	is_hidden = 0
			ORDER BY published_date DESC
			LIMIT 20';
		return $this->db->array_query_assoc($sql);
	}

	//***************************************
	//        --- HELPERS ---
	//***************************************
	function miliSecToTime($miliseconds)
	{
		return date('i:s',mktime(0,0,floor(($miliseconds/1000)),0,0,0));
	}

	// additionally used from sys.rss
	function getVideoUri($id, $name)
	{
		return make_uri($name) . '-' . $id;
	}

	function getIndexPlayerId()
	{
		return 69161682001;
	}

}
?>
