<?php
class video2 extends moon_com {

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
				$videoUri = substr($uri, 0, - 4);
				//$video = $this->getVideoById('uri', $videoUri);
				$uriChunks = explode('-', $videoUri);
				if (count($uriChunks) > 1 && is_numeric($videoId = array_pop($uriChunks))) {
					if (strlen($videoId) > 8) {
						$video = $this->getVideoById('brightcove_id', $videoId);
						if (!empty($video)) {
							$this->redirect('#', $video['uri'] . '-' . $video['id']);
						}
					}
					else {
						$video = $this->getVideoById('id', $videoId);
					}
				}
				if (empty($video)) {
					$page->page404();
				}
				elseif (isset($_GET['embed'])) {
					$this->forget();
					$preset = $_GET['embed'] ? $_GET['embed'] : 'PokerNetwork';
					$tpl = $this->load_template();
					$a = array();
					$pnPlayer = moon::shared('pnplayer');
					$flv = $video['youtube_video_id'] ? $video['youtube_video_id'] : $video['flv_url'];
					$a['video'] = $pnPlayer->getHtml($flv, $video['length'], $preset, null, $pnPlayer->getDefaultAdsConfig('embed'));
					$a['video'] = $tpl->ready_js($a['video']);
					$a['into'] = !empty($_GET['into']) ? $tpl->ready_js($_GET['into']) : '';
					header('Content-type: text/javascript; charset=UTF-8');
					header('Expires: ' .   gmdate('r', time()+3600) , TRUE);
					header('Cache-Control: max-age=' . 3600 , TRUE);
					header('Pragma: public', TRUE);
					echo $tpl->parse('embed', $a);
					moon_close();
					exit;
				}
				$this->updateViewsCount($video['id']);
				$this->set_var('video', $video);
				$this->set_var('view', 'video');
			}elseif (substr($uri, - 7) === 'rss.xml') {
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
			'listLimit' => 40,
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

	private function htmlMain()
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
			$m['thumbSrc'] = $this->defaultThumbnail($featuredVideo['youtube_video_id']);
			$m['uri.featured'] = $this->linkas('#', $featuredVideo['uri'] . '-' . $featuredVideo['id']);
			$m['publishedDate'] = $locale->datef($featuredVideo['published_date'], 'News');
			$m['length'] = $this->secToTime($featuredVideo['length']) . ' ' . $minStr;
			$m['description'] = htmlspecialchars($featuredVideo['short_description']);
			$m['tags'] = implode(' &clubs; ', $t);
			$m['url.comments'] = ($m['comm_count'] = $featuredVideo['comm_count']) ? $m['uri.featured'].'#comm-list' : '';
			$m['commentsWord'] = $m['comm_count'] == 1 ? 'comment' : 'comments';
			$m['shareThis'] = $tools->toolbar(array('url'=>$m['uri.featured']));

			if (!empty($featuredVideo['youtube_video_id'])) {
				$featuredVideo['player_uri'] = $featuredVideo['youtube_video_id'];
			} else {
				$featuredVideo['player_uri'] = preg_replace('/\?.*$/', '', $featuredVideo['flv_url']);
			}
			$pnPlayer = moon::shared('pnplayer');
			$m['video'] = $pnPlayer->getHtml($featuredVideo['player_uri'], $featuredVideo['length'], 'videoPage', null, $pnPlayer->getDefaultAdsConfig('video'));
		}

		foreach ($itemsLatest as $item) {
			$time = $item['published_date'];
			$ago = $txt->ago($time, TRUE, TRUE, FALSE);
			$item['date'] = '';
			if ($time) {
				$item['date'] = ($ago) ? $ago : $locale->datef($time, 'News');
			}

			$item['url.video'] = $this->linkas('#', $item['uri'] . '-' . $item['id']);
			$item['title'] = htmlspecialchars($item['name']);
			$item['thumbSrc'] = $this->defaultThumbnail($item['youtube_video_id']);
			$item['length'] = $this->secToTime($item['length']);
			$item['url.comments'] = $item['comm_count'] ? $item['url.video'] . '#comm-list' : '';
			$item['commentsWord'] = $item['comm_count'] == 1 ? 'comment' : 'comments';
			$m['items:latest'] .= $tpl->parse('items:latest', $item);
		}

		// most popular this month videos
		$items = $this->getPopularMonthItems();
		foreach ($items as $item) {
			$time = $item['published_date'];
			$ago = $txt->ago($time, TRUE, TRUE, FALSE);
			$item['date'] = '';
			if ($time) {
				$item['date'] = ($ago) ? $ago : $locale->datef($time, 'News');
			}

			$item['url.video'] = $this->linkas('#', $item['uri'] . '-' . $item['id']);
			$item['title'] = htmlspecialchars($item['name']);
			$item['thumbSrc'] = $this->defaultThumbnail($item['youtube_video_id']);
			$item['length'] = $this->secToTime($item['length']);
			$item['url.comments'] = $item['comm_count'] ? $item['url.video'] . '#comm-list' : '';
			$item['commentsWord'] = $item['comm_count'] == 1 ? 'comment' : 'comments';
			$m['items:popularMonth'] .= $tpl->parse('items:popularMonth', $item);
		}

		return $tpl->parse('viewMain', $m);
	}

	private function htmlVideo($vars)
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

			$time = $v['published_date'];
			$ago = $txt->ago($time, TRUE, TRUE, FALSE);
			$item['date'] = '';
			if ($time) {
				$item['date'] = ($ago) ? $ago : $locale->datef($time, 'News');
			}

			$item['title'] = htmlspecialchars($v['name']);
			$item['uri.video'] = $this->linkas('#', $v['uri'] . '-' . $v['id']);
			$item['thumbSrc'] = $this->defaultThumbnail($v['youtube_video_id']);
			$item['length'] = $this->secToTime($v['length']);
			$item['url.comments'] = ($item['comm_count'] = $v['comm_count']) ? $item['uri.video'] . '#comm-list' : '';
			$item['commentsWord'] = $item['comm_count'] == 1 ? 'comment' : 'comments';
			$relatedList .= $tpl->parse('related', $item);
		}

		// latest
		$latestVideos = $this->getLatestItems(10, $video['id']);
		$latestList = '';
		foreach ($latestVideos as $v) {
			$item = array();

			$time = $v['published_date'];
			$ago = $txt->ago($time, TRUE, TRUE, FALSE);
			$item['date'] = '';
			if ($time) {
				$item['date'] = ($ago) ? $ago : $locale->datef($time, 'News');
			}

			$item['title'] = htmlspecialchars($v['name']);
			$item['uri.video'] = $this->linkas('#', $v['uri'] . '-' . $v['id']);
			$item['thumbSrc'] = $this->defaultThumbnail($v['youtube_video_id']);
			$item['length'] = $this->secToTime($v['length']);
			$item['url.comments'] = ($item['comm_count'] = $v['comm_count']) ? $item['uri.video'] . '#comm-list' : '';
			$item['commentsWord'] = $item['comm_count'] == 1 ? 'comment' : 'comments';
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
		$page->meta('description', $video['description']);

		// facebook like button specific tags
		if (!empty($video['youtube_video_id'])) {
			$page->meta('twitter:card', 'player');
			$page->meta('twitter:site', '@Pokernews');
			$page->meta('twitter:creator', '@Pokernews'); // see below for override while iterating authors
			$page->fbMeta['og:url'] =                                                  // required, or twitter:url
				htmlspecialchars(rtrim($page->home_url(), '/') . $this->linkas('#', $video['uri'] . '-' . $video['id'])); 
			$page->fbMeta['og:image'] = $this->defaultThumbnail($video['youtube_video_id']);        // twitter:image			$page->fbMeta['og:title'] = htmlspecialchars($video['name']);              // required, or twitter:title
			$page->fbMeta['og:description'] = htmlspecialchars($video['description']); // required, or twitter:description
			//
			$page->fbMeta['og:video'] = 'https://www.youtube.com/v/' . $video['youtube_video_id'];
			$page->fbMeta['og:video:width'] = '455';
			$page->fbMeta['og:video:height'] = '260';
			$page->fbMeta['og:video:type'] = 'application/x-shockwave-flash';
			$page->meta('twitter:player', 'https://www.youtube.com/embed/' . $video['youtube_video_id'] . '?wmode=transparent');
			$page->meta('twitter:player:width', '455');
			$page->meta('twitter:player:heigh', '251');

			$videoSrc = 'http://youtube.googleapis.com/v/' . $video['youtube_video_id'];
			$video['player_uri'] = $video['youtube_video_id'];
		} else {
			// $videoSrc = 'http://c.brightcove.com/services/viewer/federated_f9/69609817001?isVid=1&amp;isUI=1&amp;autoStart=1&amp;dynamicStreaming=1&amp;publisherID=1544546948&amp;playerID=69609817001&amp;domain=embed&amp;videoId=' . $video['id'];
			$video['player_uri'] = preg_replace('/\?.*$/', '', $video['flv_url']);
		}

		$sitemap->breadcrumb(array('' => $video['name']));

		$m = array(
			'videoId' => $video['id'],
			'playerId' => $this->get_var('playerId'),
			'name' => htmlspecialchars($video['name']),
			'uri.self' => $this->linkas('#', $video['uri'] . '-' . $video['id']),
			'uri.latest' => $this->linkas('#latest-videos'),
			'publishedDate' => $locale->datef($video['published_date'], 'News'),
			'length' => $this->secToTime($video['length']) . ' ' . $minStr,
			'description' => htmlspecialchars($video['description']),
			'related' => $relatedList,
			'latest' => $latestList,
			'tags' => implode(' &clubs; ', $t),
			'comments' => '',
			'shareThis' => $tools->toolbar(),
			'fbLike' => $tools->fbLikeWst()
		);

		$pnPlayer = moon::shared('pnplayer');
		$m['video'] = $pnPlayer->getHtml($video['player_uri'], $video['length'], 'videoPage', null, $pnPlayer->getDefaultAdsConfig('video'));

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
		$imgSrc = $this->defaultThumbnail($video['youtube_video_id']);
		$iCard['img'] = $imgSrc;
		$iCard['url'] = $homeUrl . $m['uri.self'];
		$iCard['description'] = $video['description'];
		$commentsComp = &$this->object('comments');
		if (is_object($commentsComp)) {
			$m['comments'] = $commentsComp->show($video['id'], $iCard);
		}
		$m['url.comments'] = ($m['comm_count'] = $video['comm_count']) ? $m['uri.self'] . '#comm-list' : '';
		$m['commentsWord'] = $m['comm_count'] == 1 ? 'comment' : 'comments';

		return $tpl->parse('viewVideo', $m);
	}

	private function htmlList($vars)
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
			'uri.rss' => $this->linkas('#') . 'rss.xml',
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
			$page->meta('description', $playlist['description']);

			$m['title'] = htmlspecialchars($playlist['name']);
			$m['description'] = htmlspecialchars($playlist['description']);
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
				$time = $item['published_date'];
				$ago = $txt->ago($time, TRUE, TRUE, FALSE);
				$item['date'] = '';
				if ($time) {
					$item['date'] = ($ago) ? $ago : $locale->datef($time, 'News');
				}

				$item['url.video'] = $this->linkas('#', $item['uri'] . '-' . $item['id']);
				$item['title'] = htmlspecialchars($item['name']);
				$item['thumbSrc'] = $this->defaultThumbnail($item['youtube_video_id']);
				$item['length'] = $this->secToTime($item['length']);
				$item['url.comments'] = $item['comm_count'] ? $item['url.video'] . '#comm-list' : '';
				$item['commentsWord'] = $item['comm_count'] == 1 ? 'comment' : 'comments';

				$m['items'] .= $tpl->parse('items', $item);
			}
		}

		if ($playlistID || $vars['view'] === 'latest') {
			$sitemap->breadcrumb(array($m['uri.self'] => $m['title']));
		}

		return $tpl->parse('viewList', $m);
	}

	private function defaultThumbnail($youtubeVideoId)
	{
		return sprintf('http://i.ytimg.com/vi/%s/mqdefault.jpg', rawurlencode($youtubeVideoId));
	}

	private function htmlCategoriesBox($playlistId = 0)
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

	private function htmlArchiveBox($activeYear = 0, $activeMonth = 0)
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

	private function getArchiveData()
	{
		$now = (floor(moon::locale()->now() / 300) * 300);
		$sql = '
			SELECT FROM_UNIXTIME(created, \'%Y-%M\') as short_date, FROM_UNIXTIME(created, \'%Y\') as year, FROM_UNIXTIME(created, \'%m\') as month, count(*) as cnt
			FROM ' . $this->tblVideos . ' 
			WHERE 
				created < ' . $now . ' AND
				hide = 0
			GROUP BY short_date
			ORDER BY year DESC, month DESC';
		$result = $this->db->array_query_assoc($sql);

		$items = array();
		foreach ($result as $r) {
			$items[$r['year']][$r['month']] = $r['cnt'];
		}

		return $items;
	}

	private function htmlHomepageBox($vars)
	{
		$page = moon::page();
		$pnPlayer = moon::shared('pnplayer');

		$videos = $this->getLatestHomepageItems();
		if (0 == count($videos))
			return ;

		$playlist = array();
		foreach ($videos as $video) {
			$playlist[] = array(
				$video['youtube_video_id'],
				$video['duration'],
				$video['title'],
				$this->linkas('#', $video['uri'] . '-' . $video['id']),
			);
		}

		$size = !empty($vars['size']) ? $vars['size'] : '228x282';
		switch ($size) {
			case '228x282':
				$preset = 'w230';
				break;
			
			case '478x320':
			default:
				$preset = 'homePage';
				break;
		}

		$m = array(
			'url.more' => moon::shared('sitemap')->getLink('video'),
			'video' => $pnPlayer->getHtml($videos[0]['youtube_video_id'], $videos[0]['duration'], $preset, $playlist, $pnPlayer->getDefaultAdsConfig('home'))
		);

		$tpl = $this->load_template();
		return $tpl->parse('homepageBox', $m);
	}

	private function htmlReportingBox($playlistId)
	{
		$page = moon::page();
		$pnPlayer = moon::shared('pnplayer');

		$videos = $this->getLatestHomepageItems();
		if (0 == count($videos))
			return ;

		$playlist = array();
		foreach ($videos as $video) {
			$playlist[] = array(
				$video['youtube_video_id'],
				$video['duration'],
				$video['title'],
				$this->linkas('#', $video['uri'] . '-' . $video['id']),
			);
		}
		$m = array(
			'url.more' => moon::shared('sitemap')->getLink('video'),
			'video' => $pnPlayer->getHtml($videos[0]['youtube_video_id'], $videos[0]['duration'], 'w300', $playlist, $pnPlayer->getDefaultAdsConfig('reporting')),
			'cil' => 0,          //'com' == _SITE_ID_ && $page->uri_segments(1) == 'live-reporting' && $page->uri_segments(3) == 'event-58-no-limit-hold-em-championship';
			'wsopeBanner' => 0, // 'com' == _SITE_ID_ && $page->uri_segments(1) == 'live-reporting' && $page->uri_segments(2) == '2010-wsop';
		);

		return $this->load_template()->parse('reportingBox', $m);
	}

	//***************************************
	//           --- DB ---
	//***************************************
	private function getVideoById($type, $id)
	{
		if ($type == 'uri') {
			$where = 'uri = "' . $this->db->escape($id) . '"';
		} elseif ($type == 'id') {
			$where = 'id = "' . intval($id) . '"';
		} elseif ($type == 'brightcove_id') {
			$where = 'brightcove_id = "' . $this->db->escape(sprintf("%.0f",$id)) . '"';
		} else {
			return array();
		}
		$idField = (strlen($id) >= 9) 
			? 'brightcove_id'
			: 'id';
		$sql = 'SELECT id,title name,uri,description,duration length,created published_date,tags,category playlist_ids,flv_url,youtube_video_id,comm_count
			FROM ' . $this->table('Videos') . '
			WHERE ' . $where . ' AND
				hide = 0';
		$result = $this->db->single_query_assoc($sql);
		return $result;
	}

	private function getVideosRelated($id = 0, $tags = '', $playlistIds = '', $limit = 10)
	{
		$if = array();
		if ($playlistIds !== '') {
			$playlistIds = explode(',', $playlistIds);
			foreach ($playlistIds as $pId) {
				$if[] = "IF(FIND_IN_SET('" . $this->db->escape($pId) . "', category), 2, 0)";
			}
		}
		if ($tags !== '') {
			$tags = explode(',', $tags);
			foreach ($tags as $tag) {
				$if[] = "IF(FIND_IN_SET('" . $this->db->escape($tag) . "', tags), 1, 0)";
			}
		}
		if (0 == count($if))
			$if[] = '1';

		$w = array();
		if ($id) {
			$w[] = 'id <> ' . $this->db->escape($id);
		}
		$w[] = 'hide = 0';
		$where = count($w) ? (' WHERE ' . implode(' AND ', $w)) : '';

		$strSelect = ', ' . implode(' + ', $if) . ' as weight';
		$sql = 'SELECT	id,title name,uri,youtube_video_id,duration length,comm_count,created published_date ' . $strSelect . '
			FROM ' . $this->table('Videos') . '
			' . $where . '
			ORDER BY weight DESC, created DESC
			LIMIT ' . intval($limit);
		return $this->db->array_query_assoc($sql);
	}

	// used from players.poker
	/*public function getVideosByTags($tags = '', $limit = 10)
	{
		$w = array();
		$w[] = 'hide = 0';
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

		$sql = 'SELECT	id,title name,uri,youtube_video_id,duration length,created published_date
			FROM ' . $this->table('Videos') . '
			' . $where . '
			ORDER BY created DESC
			LIMIT ' . intval($limit);
		return $this->db->array_query_assoc($sql);
	}*/

	private function getPlaylists()
	{
		if (!isset($this->tmpPlaylists)) {
			$sql = 'SELECT id,title name,uri
				FROM ' . $this->table('VideosPlaylists') . '
				WHERE hide = 0
				ORDER BY sort_order ASC';
			$this->tmpPlaylists = $this->db->array_query_assoc($sql,'id');
		}
		return $this->tmpPlaylists;
	}	

	private function getListCount($playlistID = FALSE, $tag = FALSE, $year = 0, $month = 0)
	{
		$sql = 'SELECT count(*)
			FROM ' . $this->tblVideos . $this->sqlWhere($playlistID, $tag, $year, $month);
		$m = $this->db->single_query($sql);
		return (count($m) ? $m[0] : 0);
	}

	private function getList($playlistID = FALSE, $tag = FALSE, $year = 0, $month = 0)
	{
		$sql = 'SELECT id,title name,uri,youtube_video_id,duration length,created published_date,comm_count
			FROM ' . $this->tblVideos . $this->sqlWhere($playlistID, $tag, $year, $month) . '
			ORDER BY created DESC ' .
			$this->sqlLimit();
		return $this->db->array_query_assoc($sql);
	}

	private function getLatestItems($limit = 11, $id = 0)
	{
		$sql = 'SELECT id,title name,uri,youtube_video_id,duration length,created published_date,comm_count,tags,description short_description, youtube_video_id, flv_url
			FROM ' . $this->tblVideos . $this->sqlWhere() . ($id ? ' AND id <> ' . $id : '') . '
			ORDER BY created DESC
			LIMIT ' . $limit;
		return $this->db->array_query_assoc($sql);
	}

	private function getLatestHomepageItems($limit = 20)
	{
		$sql = 'SELECT id,title,duration,youtube_video_id,uri
			FROM ' . $this->tblVideos . '
			WHERE hide=0 AND youtube_video_id!=""
			ORDER BY created DESC
			LIMIT ' . $limit;
		return $this->db->array_query_assoc($sql);
	}

	private function getPopularMonthItems()
	{
		$l = moon::locale();
		$now = floor($l->now() / 300) * 300;
		$viewsInterval = (string)($now - 3600*24*30);
		$sql = 'SELECT id,title name,uri,youtube_video_id,duration length,created published_date,comm_count
			FROM ' . $this->tblVideos . $this->sqlWhere() . ' AND created > ' . $viewsInterval . '
			ORDER BY views_count DESC
			LIMIT 10';
		return $this->db->array_query_assoc($sql);
	}

	private function getPlaylistByUri($uri)
	{
		$sql = 'SELECT id,title name,uri,description
			FROM ' . $this->table('VideosPlaylists') . '
			WHERE 	uri = \'' . $this->db->escape($uri) . '\' AND
				hide = 0';
		return $this->db->single_query_assoc($sql);
	}

	/**
	 * Sets sql where condition. Used in articles list
	 * @param int $catID
	 * @return string
	 */
	private function sqlWhere($playlistID = FALSE, $tag = FALSE, $year = 0, $month = 0)
	{
		if (!isset($this->tmpWhere)) {
			$locale = &moon::locale();
			$now = floor($locale->now() / 300) * 300;
			$w = array();

			$w[] = 'hide = 0';

			if ($playlistID) {
				$w[] = 'FIND_IN_SET(' . $this->db->escape($playlistID) . ', category)';
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
				$w[] = '(' . $from . ' <= created AND created < ' . $to . ')';
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
	private function sqlLimit()
	{
		return isset($this->tmpLimit) ? $this->tmpLimit : '';
	}

	private function updateViewsCount($id = null)
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
	private function setPaging()
	{
		if (isset($_GET['page']) && is_numeric($_GET['page'])) {
			$currPage = $_GET['page'];
			$this->set_var('currPage', (int)$currPage);
		}
	}

	private function getPaging($currPage, $itemsCnt, $listLimit, $uri)
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

	// ***************************************
	//        --- RSS FEEDS, XML ---
	// ***************************************
	private function rssXml($playlistId = 0, $playlistUri = '', $playlistTitle = '', $limit = '20', $twitter = FALSE)
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
					$title = $item['name'];
					if (strlen($item['name']) > 140) {
						$title = substr($item['name'], 0, 137) . '...';
					}

					$xml->item(
						array(
							'title' => $title,
							'url' => $homeURL . $this->linkas('#', $item['uri'] . '-' . $item['id']),
							'created' => substr($item['published_date'], 0, 10)
						)
					);
				}
			} else {
				$items = $this->getRssFeedItems($playlistId, $limit);
				foreach($items as $item) {
					$xml->item(
						array(
							'title' => $item['name'],
							'url' => $homeURL . $this->linkas('#', $item['uri'] . '-' . $item['id']),
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

	private function getRssFeedItems($playlistId, $limit)
	{
		$sql = 'SELECT id,title name,uri,description short_description,created published_date,created last_modified_date
			FROM ' . $this->table('Videos') . '
			WHERE	' . (!empty($playlistId)
					? 'FIND_IN_SET(' . $this->db->escape($playlistId) . ', playlist_ids) AND'
					: ''
				) . '
				hide = 0
			ORDER BY created DESC
			LIMIT ' . $limit;
		return $this->db->array_query_assoc($sql);
	}
	
	private function getRssFeedItemsTwitter()
	{
		$sql = 'SELECT id,title name,uri,created published_date
			FROM ' . $this->table('Videos') . '
			WHERE	hide = 0
			ORDER BY created DESC
			LIMIT 20';
		return $this->db->array_query_assoc($sql);
	}

	//***************************************
	//        --- HELPERS ---
	//***************************************
	// players.poker
	public function secToTime($miliseconds)
	{
		return date('i:s',mktime(0,0,($miliseconds),0,0,0));
	}

	// used from other.ctags
	public function getVideoCtagsItems($ids)
	{
		if (0 == count($ids))
			return array();
		foreach ($ids as $key => $value)
			$ids[$key] = intval($value);
		return $this->db->query('
			SELECT id, title name,CONCAT(uri, "-", id) as uri, created published, description, concat("http://i.ytimg.com/vi/", youtube_video_id, "/mqdefault.jpg") thumbnail_url
			FROM ' . $this->tblVideos . '
			WHERE hide=0 AND id IN(' . implode(',', $ids) . ')
		');
	}	
}

