<?php
class news extends moon_com {

	function onload()
	{
		$this->type = $this->get_var('typeNews');
		$this->oShared = $this->object('shared');
		$this->oShared->articleType($this->type);

		$this->tblArticles = $this->table('Articles');
		$this->tblCategories = $this->table('Categories');

		$this->suffixStartId = $this->get_var('suffixStartId');
	}

	function events($event, $par)
	{
		$this->use_page('2col');

		$page = &moon::page();
		if ($uri = $page->requested_event('REST')) {
			
			$segments = $page->requested_event('segments');
			$cnt = count($segments);

			if (substr($uri, - 4) === '.htm') {
				// news article uri
				$articleUri = substr($uri, 0, - 4);

				$oShared = $this->oShared;
				if (($id = $oShared->getIdFromUri($articleUri)) !== FALSE) {
					$article = $this->getArticleById($id);
				} else {
					$article = $this->getArticleByUri($articleUri);
					if (empty($article) || $article['id'] >= $this->suffixStartId) {
						$page->page404();
					}
				}

				if (empty($article)) $page->page404();
				if(!empty($article['id'])) {
					$this->oShared->updateViewsCount($article['id']);
				}
				$this->set_var('article', $article);
				$this->set_var('view', 'article');
				$this->use_page('1col');
			} elseif (substr($uri, - 7) === 'rss.xml') {

				$catUri = '';
				if ($cnt === 2) {
					$catUri = $segments[0];
				} elseif ($cnt > 2) {
					$page->page404();
				}

				$fulldesc = FALSE;
				if (isset($_GET['description']) AND $_GET['description'] == 'full') {
					$fulldesc = TRUE;
				}
				$descrimage = FALSE;
				if (isset($_GET['descrimage'])) {
					$descrimage = TRUE;
				}
				$promofree = FALSE;
				if (isset($_GET['promofree'])) {
					$promofree = TRUE;
				}
				$nodescr = FALSE;
				if (isset($_GET['nodescr'])) {
					$nodescr = TRUE;
				}
				$titleLimit = FALSE;
				if (isset($_GET['titlelimit'])) {
					$titleLimit = TRUE;
				}

				$isTurbo = FALSE;
				$catIds = array();
				if (isset($_GET['c']) && is_scalar($_GET['c'])) {
					$catIds = explode(',', $_GET['c']);
					foreach ($catIds as $k => $value) {
						$catIds[$k] = intval($value);
						if (!$value) {
							unset($catIds[$k]);
						}
					}
					$catIds = array_unique($catIds);
				} if ($catUri == 'turbo') {
					$isTurbo = TRUE;
				} elseif ($catUri) {
					$cat = $this->getCategory($catUri);
					if (!empty($cat['id'])) $catIds[] = $cat['id'];
				}

				$limit = 10;
				if (isset($_GET['l']) && is_numeric($_GET['l'])) {
					$limit =
						($_GET['l'] > 0 && $_GET['l'] < 100)
						? $_GET['l']
						: $limit;
				}

				if (isset($_GET['xml'])) {
					header('Content-Type: text/xml; charset=UTF-8');
					print $this->xmlArticles($catIds, $limit, $isTurbo);
					moon_close();
					exit;
				}

				print $this->rssXml($catIds, $fulldesc, $descrimage, $promofree, $nodescr, $limit, $isTurbo, $titleLimit);
				moon_close();
				exit;
			} elseif (substr($uri, 0, 4) === 'tags') {
				if ($cnt === 2 || $cnt === 3) {
					if (substr($uri, -1) !== '/') {
						//gale truksta /
						$page->redirect($this->linkas("#$uri"), 301);
					}
					$tag = urldecode($segments[1]);

					$page->redirect($this->oShared->getTagUrl($tag), 301);
					
					$tagData = $this->getTagData($tag);
					if (empty($tagData)) {
						$page->page404();
					}
					$tagData['uri'] = $segments[0] . '/' . urlencode($tag);
				} else {
					$page->page404();
				}
				$this->set_var('tag', $tagData);
				$this->set_var('view', 'tag');

			} elseif ($cnt === 2 && is_numeric($segments[0]) && $segments[0] > 2000 && $segments[1] == '') {
				// year archive
				$year = intval($segments[0]);
				if (checkdate(1, 1, $year)) {
					$this->set_var('year', $year);
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
				} else {
					$page->page404();
				}
				
			} else {
				//category uri
				$catFullUri = trim($uri, '/');
				$category = $this->getCategory($catFullUri);
				if (empty($category)) {
					// wrong url
					$page->page404();
				} elseif (strlen($uri) - strlen($catFullUri) != 1) {
					//gale truksta /
					$page->redirect($this->linkas("#$catFullUri"), 301);
				}
				$this->set_var('category', $category);
			}
		} else {
			// index list
		}

		$this->setPaging();
		if (isset($_GET['print'])) {
			$this->set_var('print', TRUE);
			$this->use_page('');
			$page->set_local('output','print');
			return;
		}

	}

	function properties()
	{
		return array(
			'view' => '',
			'article' => array(),
			'category' => array(),
			'tag' => array(),
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
			case 'article':
				$output = $this->htmlArticle($vars);
				break;
			default:
				$output = $this->htmlList($vars);
				break;
		}
		return $output;
	}

	function htmlList($vars)
	{
		$page = &moon::page();
		$tpl = $this->load_template();
		$oShared = $this->oShared;
		$sitemap = &moon::shared('sitemap');
		
		$m = array(
			'list:items' => '',
			'categories:items' => '',
			'url.news' => $this->linkas('#'),
			'isCategory' => '',
			'title' => htmlspecialchars($sitemap->getTitle()),
			'description' => '',
			'paging' => ''
		);

		$year = $vars['year'];
		$month = $vars['month'];

		$catID = $m['isCategory'] = isset($vars['category']['id']) ? $vars['category']['id'] : 0;
		$catIsAu = isset($vars['category']['is_au']) ? $vars['category']['is_au'] : '';
		$catUri = '';
		$pageTitle = '';
		if (count($category = $vars['category'])) {
			$catUri = $category['uri'];
			
			$cUrl = $this->linkas('#' . $catUri);
			$sitemap->breadcrumb(array($cUrl => $category['title']));

			$page->meta('keywords', $category['meta_keywords']);
			$page->meta('description', $category['meta_description']);
		}
		$m['rss'] = TRUE;
		$m['url.self'] = $this->linkas('#' . $catUri);

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

		$m['title'] = $pageTitle;
		$page->title($pageTitle);
		$page->head_link($m['url.self'] . 'rss.xml', 'rss', $pageTitle);

		// categories
		$cItems = $this->getCategories();
		foreach ($cItems as $item) {
			$item['title'] = htmlspecialchars($item['title']);
			$item['url'] = $this->linkas('#'.$item['uri']);
			$item['class'] = $item['id'] === $catID ? ' class="on"' : '';
			$item['isAu'] = $item['is_au'];
			$m['categories:items'] .= $tpl->parse('categories:items', $item);
		}

		// generate list
		if ($count = $this->getListCount($catID, false, $year, $month)) {
			$m['paging'] = $this->getPaging($vars['currPage'], $count, $vars['listLimit'], $catUri);
			if ($vars['currPage'] > 1) {
				$page->meta('robots', 'noindex,follow');
			} else {
				$robots = 'index,follow';
			}
			
			$locale = &moon::locale();
			
			$items = $this->getList($catID, false, $year, $month);
			
			// sarase rodysim autorius
			$authors = array();
			foreach ($items as $item) {
				if ($item['authors']) {
					$authors = array_merge($authors, explode(',', $item['authors']));
				}
			}
			$authors = $oShared->getAuthorsData(implode(',', array_unique($authors)));

			foreach ($items as $k => $item) {
				$item['url.article'] = $oShared->getArticleUri($item['id'], $item['uri'], $item['published']);
				$item['url.comments'] = $item['comm_count'] ? $item['url.article'] . '#comm-list' : '';
				$item['commentsWord'] = $item['comm_count'] == 1 ? 'comment' : 'comments';
				$item['title'] = htmlspecialchars($item['title']);
				$item['summary'] = htmlspecialchars($item['summary']);
				if ($item['img']) {
					$item['img'] = $this->getImageSrc($item['img'], 'thumb_');
					$item['img_alt'] = htmlspecialchars($item['img_alt']);
				}
				$item['date'] = $locale->datef($item['published'], 'Date');
				$item['time'] = date('H:i', $item['published']);
				$item['authors'] = $oShared->htmlAuthors($item['authors'], FALSE, $authors);
				$item['class'] = ++$k%2 == 0 ? '' : ' class="odd"';
				$item['isAu'] = $catIsAu || $item['isAu'];
				$m['list:items'] .= $tpl->parse('list:items', $item);
			}
		}

		// insert archive box
		$page->insert_html($this->htmlArchiveBox($year, $month), 'column');

		return $tpl->parse('viewList', $m);
	}

	function htmlArticle($vars)
	{
		$page = &moon::page();
		if (!count($article = $vars['article'])) {
			//jei neradom
			$page->page404();
		}

		$tpl = $this->load_template();
		$locale = &moon::locale();
		$oShared = $this->oShared;

		$uri = $article['uri'];
		$print = empty($vars['print']) ? FALSE : TRUE;

		// page title
		$sitemap = &moon::shared('sitemap');
		$pageTitle = htmlspecialchars($sitemap->getTitle());
		$pathTitles = array();
		$bcr = $sitemap->breadcrumb();
		foreach ($bcr as $b) {
			$pathTitles[] = $b['title'];
		}
		if (!empty($pathTitles)) $pageTitle = implode(' | ', $pathTitles);

		$homeUrl = rtrim($page->home_url(), '/');
		$uriSelf = $oShared->getArticleUri($article['id'], $article['uri'], $article['published']);

		// meta info
		$page->title($article['title']);
		$page->css('/css/article.css');
		$page->meta('description', $article['meta_description']);
		$page->meta('robots', 'index,follow');
		$page->meta('twitter:card', 'summary');
		$page->meta('twitter:site', '@Poker_Network');
		$page->meta('twitter:creator', '@Pokernews'); // see below for override while iterating authors
		$page->fbMeta['og:url'] = htmlspecialchars($homeUrl . $uriSelf);         // required, or twitter:url
		$page->fbMeta['og:image'] = htmlspecialchars($homeUrl . $this->getImageSrc($article['img'], 'thumb_')); // twitter:image
		$page->fbMeta['og:title'] = htmlspecialchars($article['title']);         // required, or twitter:title
		$page->fbMeta['og:description'] = htmlspecialchars($article['summary']); // required, or twitter:description

		if ($article['is_turbo']) {
			$article['content_html'] .= $this->htmlArticleTurboContents($article['id'], TRUE);
		} else {
			$article['content_html'] = $article['content_html'];
		}

		$sharedTxt = & moon::shared('text');
		$article['content_html'] = $sharedTxt->check_timer($article['content_html']);

		if ($article['content_type'] == 'img') {
			$page->js('/js/pnslideshow.js');
		}

		$sharedTxt->agoMaxMin = 60*24*30; // 30 days;

		$m = array(
			'pageTitle' => $pageTitle,
			'title' => htmlspecialchars($article['title']),
			'authors' => $oShared->htmlAuthors($article['authors'], TRUE),
			'content_html' => $article['content_html'],
			'uri.date' => $this->linkas('#' . date('Y/m', $article['published'])),
			'uri.self' => $oShared->getArticleUri($article['id'], $article['uri'], $article['published']),
			'ago' => $sharedTxt->ago($article['published']),
			'date' => $locale->datef($article['published'], 'Article'),
			'time' => date('H:i', $article['published']),
			'relatedArticles' => '',
			'relatedPlayers' => '',
			'relatedTours' => '',
			'recentArticles' => '',
			'notPrint' => !$print,
			'bottom_banner' => '',
			'url.home' => $homeUrl
		);
		$m['authorsLinks'] = '';
		if (isset($oShared->htmlAuthorsData) && is_array($oShared->htmlAuthorsData)) {
			$gaVars = $page->get_local('gaCustomVars');
			if (!is_array($gaVars)) $gaVars = array();
			$markedTwitterAuthor = false;

			foreach ($oShared->htmlAuthorsData as $authorData) {
				if (!$markedTwitterAuthor && !empty($authorData['twitter'])) {
					$markedTwitterAuthor = true;
					$page->meta('twitter:creator', '@' . $authorData['twitter']);
				}

			if (empty($authorData['twitter']) && empty($authorData['gplus_url'])) continue;
			$m['authorsLinks'] .= $tpl->parse('authorsLinks', array(
				'name' => htmlspecialchars($authorData['name']),
				'twitter' => htmlspecialchars($authorData['twitter']),
				'gplus_url' => htmlspecialchars($authorData['gplus_url'])
			));
		}}

		if ($article['img'] && $vars['currPage'] == 1) {
			$m['img'] = $this->getImageSrc($article['img'], '');
			$m['img_alt'] = htmlspecialchars($article['img_alt']);
		}
		$m['url.self'] = $oShared->getArticleUri($article['id'], $article['uri'], $article['published']);
		$m['url.comments'] = ($m['comm_count'] = $article['comm_count']) ? $m['url.self'] . '#comm-list' : '';
		$m['commentsWord'] = $m['comm_count'] == 1 ? 'comment' : 'comments';

		$tools = &moon::shared('tools');
		$m['shareThis'] = $tools->toolbar(array('twitterTags' => $article['twitter_tags']));
		$m['fbLike'] = $tools->facebookLike();

		// tags
		$tags = array();
		if (strlen($article['tags'])) {
			$tagsAll = explode(',', $article['tags']);
			$tagsDB = explode(',', $this->db->escape($article['tags']));

			//$tagsActive = $oShared->getActiveTags($tagsDB);
			foreach ($tagsAll as $k => $name) {
				$t['tagName'] = htmlspecialchars($name);
				$t['tagUri'] = $oShared->getTagUrl($name);//(array_search($name, $tagsActive) !== FALSE) ? $oShared->getTagUrl($name) : '';

				$tags[] = trim($tpl->parse('tags', $t));
			}
		}
		$m['tags'] = implode(' &clubs; ', $tags);

		// banners
		if (!empty($article['room_id'])) {
			$page->set_local('banner.roomID',$article['room_id']);
		}
		if ($article['double_banner'] == 1) {
			$m['bottom_banner'] = $tpl->parse('bottom_banner');
		}

		if ($print === FALSE) {

			if (is_dev()) {
				$homeUrl = str_replace('.dev', '.com', $homeUrl);
			}
			$iCard = array();
			$iCard['title'] = $article['title'];
			$img = $this->getImageSrc($article['img'], 'thumb_');
			$iCard['img'] = $img ? $homeUrl . $img : '';
			$iCard['url'] = $homeUrl . $m['url.self'];
			$iCard['description'] = $article['summary'];

			$commentsComp = &$this->object('comments');
			if (is_object($commentsComp)) $m['comments'] = $commentsComp->show($article['id'], $iCard);
			else $article['comments'] = '';

			/*
			// related players
			$a = $oShared->getPlayersByTags($article['tags']);
			if (count($a)) {
				foreach ($a as $d) {
					$m['relatedPlayers'] .= $tpl->parse('relatedPlayers', $d);
				}
			}
			// related tournaments
			$a = $oShared->getToursByTags($article['tags']);
			if (count($a)) {
				foreach ($a as $d) {
					$m['relatedTours'] .= $tpl->parse('relatedTours', $d);
				}
			}
			*/

			// related articles, recent articles
			$recent = $this->getRecent($article['id'], $article['published']);
			$related = $this->getRelated($article['id'], $article['category'], $article['published'], $article['tags']);
			$items = array_merge($recent, $related);

			// sarase rodysim autorius
			$authors = array();
			foreach ($items as $item) {
				if ($item['authors']) {
					$authors = array_merge($authors, explode(',', $item['authors']));
				}
			}
			$authors = $oShared->getAuthorsData(implode(',', array_unique($authors)));

			if (count($recent)) {
				foreach ($recent as $d) {
					$d['url'] = $oShared->getArticleUri($d['id'], $d['uri'], $d['published']);
					$d['title'] = htmlspecialchars($d['title']);
					$d['date'] = $locale->datef($d['published'], 'Article');
					$d['authors'] = $oShared->htmlAuthors($d['authors'], FALSE, $authors);
					$m['recentArticles'] .= $tpl->parse('recentArticles', $d);
				}
			}
			if (count($related)) {
				foreach ($related as $d) {
					$d['url'] = $oShared->getArticleUri($d['id'], $d['uri'], $d['published']);
					$d['title'] = htmlspecialchars($d['title']);
					$d['date'] = $locale->datef($d['published'], 'Article');
					$d['authors'] = $oShared->htmlAuthors($d['authors'], FALSE, $authors);
					$m['relatedArticles'] .= $tpl->parse('relatedArticles', $d);
				}
			}
		} else {
			$m['fbLike'] = $m['fbLikeB'] = $article['comments'] = '';
		}
		return $tpl->parse('viewArticle', $m);
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
		$now = floor(moon::locale()->now() / 300) * 300;
		$sql = '
			SELECT FROM_UNIXTIME(a.published, \'%Y-%M\') as short_date, FROM_UNIXTIME(a.published, \'%Y\') as year, FROM_UNIXTIME(a.published, \'%m\') as month, count(*) as cnt
			FROM ' . $this->tblArticles . ' a 
			WHERE 
				a.published < ' . $now . ' AND
				a.article_type = ' . $this->type . ' AND
				a.is_hidden = 0
			GROUP BY short_date
			ORDER BY year DESC, month DESC';
		$result = $this->db->array_query_assoc($sql);

		$items = array();
		foreach ($result as $r) {
			$items[$r['year']][$r['month']] = $r['cnt'];
		}

		return $items;
	}

	function htmlArticleTurboContents($id, $turboOnly = FALSE)
	{
		$html = '';
		if (!$turboOnly) {
			$sql = 'SELECT content_html
				FROM ' . $this->table('Articles') . '
				WHERE 	id = ' . $this->db->escape($id) . ' AND
					is_hidden = 0';
			$result = $this->db->single_query_assoc($sql);

			if (!empty($result['content_html'])) {
				$html .= $result['content_html'];
			}
		}

		// get turbo stories
		$sql = 'SELECT id, title, content_html
			FROM ' . $this->table('Turbo') . '
			WHERE 	parent_id = ' . $this->db->escape($id) . ' AND
				is_deleted = 0
			ORDER BY created';
		$result = $this->db->array_query_assoc($sql);

		foreach ($result as $r) {
			if (!empty($r['content_html'])) {
				$html .= '<p id="story-' . $r['id'] . '"><i><b>' . $r['title'] . '</b></i></p>';
				$html .= $r['content_html'];
			}
		}
		return $html;
	}

	function getImageSrc($imageFn, $prefix = '')
	{
		static $articlesObj;
		if (!isset($articlesObj)) {
			$articlesObj = $this->object('shared');
		}
		return $articlesObj->getImageSrc($imageFn, $prefix);
	}

	//***************************************
	//           --- DB ---
	//***************************************
	function getListCount($catId = FALSE, $tag = FALSE, $year = 0, $month = 0)
	{
		$sql = 'SELECT count(*)
			FROM ' . $this->tblArticles . ' a USE INDEX (i_articles) ' .
			$this->sqlWhere($catId, $tag, $year, $month);
		$m = $this->db->single_query($sql);
		return (count($m) ? $m[0] : 0);
	}

	function getList($catId = FALSE, $tag = FALSE, $year = 0, $month = 0)
	{
		$sql = 'SELECT a.id,a.title,a.published,a.summary,a.img,a.img_alt,a.uri,a.authors,a.comm_count,c.is_au as isAu
			FROM ' . $this->tblArticles . ' a USE INDEX (i_articles) 
			LEFT JOIN ' . $this->tblCategories . ' c ON a.category_id=c.id ' .
			$this->sqlWhere($catId, $tag, $year, $month) . ' 
			ORDER BY published DESC ' .
			$this->sqlLimit();
		return $this->db->array_query_assoc($sql);
	}

	/**
	 * Sets sql where condition. Used in articles list
	 * @param int $catID
	 * @param mixed tag or tags array
	 * @return string
	 */
	function sqlWhere($catIds = FALSE, $tag = FALSE, $year = 0, $month = 0)
	{
		if (!isset($this->tmpWhere)) {
			$locale = &moon::locale();
			$now = floor($locale->now() / 300) * 300;
			$w = array();

			$w[] = 'a.published < ' . $now;
			$w[] = 'a.article_type = ' . $this->type;
			$w[] = 'a.is_hidden = 0';

			if (is_array($catIds) && !empty($catIds)) {
				$w[] = 'a.category_id IN (' . implode(',', $catIds) . ')';
			} elseif ($catIds && is_numeric($catIds)) {
				$w[] = 'a.category_id = ' . intval($catIds);
			}

			if(is_array($tag)) {
				$tmpArr = array();
				foreach($tag as $v) {
					if(!$v) continue;
					$tmpArr[] = ' FIND_IN_SET(\'' . $this->db->escape($v) . '\', tags) ';
				}
				$w[] = ' '.implode(' OR ', $tmpArr).' ';
			} elseif ($tag) {
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
				$w[] = '(' . $from . ' <= a.published AND a.published < ' . $to . ')';
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

	function getCategory($uri = FALSE)
	{
		if ($uri !== FALSE) {
			$sql = 'SELECT id, uri, title, meta_keywords, meta_description, description, is_au
				FROM ' . $this->tblCategories . '
				WHERE 	uri = "' . $this->db->escape($uri) . '" AND
					category_type = ' . $this->type . ' AND
					is_hidden = 0';
			$this->tmpCategory = $this->db->single_query_assoc($sql);
		}
		return (isset($this->tmpCategory) ? $this->tmpCategory : array());
	}

	function getCategories()
	{
		return $this->db->array_query_assoc('
			SELECT uri,title,id,is_au
			FROM ' . $this->tblCategories . '
			WHERE
				category_type = ' . $this->type . ' AND
				is_hidden = 0
			ORDER BY sort_order'
		);
	}

	function getArticleByUri($uri = FALSE)
	{
		if ($uri) {
			$sql = 'SELECT id, uri, title, published, authors, category_id as category, meta_keywords, meta_description, img, img_alt, tags, content_html, is_turbo, room_id, double_banner, content_type, is_promo, promo_text, promo_box_on, summary, twitter_tags, comm_count
				FROM ' . $this->tblArticles . ' USE INDEX (uri)
				WHERE	uri = "' . $this->db->escape($uri) . '" AND
				     	article_type = ' . $this->type . ' AND
				     	is_hidden = 0';
			$this->tmpArticle = $this->db->single_query_assoc($sql);
		}
		return (isset($this->tmpArticle) ? $this->tmpArticle : array());
	}

	function getArticleById($id = FALSE)
	{
		if ($id) {
			$sql = 'SELECT id, uri, title, published, authors, category_id as category, meta_keywords, meta_description, img, img_alt, tags, content_html, is_turbo, room_id, double_banner, content_type, is_promo, promo_text, promo_box_on, summary, twitter_tags, comm_count
				FROM ' . $this->tblArticles . '
				WHERE	id = ' . $id . ' AND
				     	article_type = ' . $this->type . ' AND
				     	is_hidden = 0';
			$this->tmpArticle = $this->db->single_query_assoc($sql);
		}
		return (isset($this->tmpArticle) ? $this->tmpArticle : array());
	}

	function getRelated($id, $catID, $date, $tags)
	{
		$if = array();
		$if[] = "IF(category_id=" .$catID . ", 1, 0)";
		if ($tags !== '') {
			$tags = explode(',', $tags);
			foreach ($tags as $tag) {
				$if[] = "IF(FIND_IN_SET('" . $this->db->escape($tag) . "', tags), 2, 0)";
			}
		}
		$strSelect = ', ' . implode(' + ', $if) . ' as weight';
		$sql = '
			SELECT id,title,published,authors,uri' . $strSelect . '
			FROM ' . $this->tblArticles . '
			WHERE 	id <> ' . $id . ' AND
				published <= ' . $date . ' AND
				article_type = ' . $this->type . ' AND
				is_hidden = 0
			ORDER BY weight DESC, published DESC
			LIMIT 3';
		return $this->db->array_query_assoc($sql);
	}

	function getRecent($id)
	{
		$locale = &moon::locale();
		$now = floor($locale->now() / 300) * 300;
		$sql = '
			SELECT id,title,published,uri,authors
			FROM ' . $this->tblArticles . '
			WHERE 	id <> '. intval($id) .' AND
				published < ' . $now . ' AND
				article_type = ' . $this->type . ' AND
				is_hidden = 0
			ORDER BY published DESC
			LIMIT 3';
		return $this->db->array_query_assoc($sql);
	}

	function getWeekMostPopular()
	{
		$locale = &moon::locale();
		$now = floor($locale->now() / 300) * 300;
		$weekAgo = $now - 3600*24*7;
		$sql = '
			SELECT id,title,published,uri
			FROM ' . $this->tblArticles . '
			WHERE 	published < ' . $now . ' AND published > ' . $weekAgo . ' AND
				article_type = ' . $this->type . ' AND
				is_hidden = 0
			ORDER BY views_count DESC, published DESC
			LIMIT 5';
		return $this->db->array_query_assoc($sql);
	}

	function getTagData($tag = FALSE)
	{
		if ($tag !== FALSE) {
			$item = array();
			$item['id'] = '';
			$item['name'] = mb_strtoupper(mb_substr($tag, 0, 1)) . mb_substr($tag, 1);
			$item['description'] = '';
			$item['category'] = '';

			$sql = 'SELECT id,name,description
				FROM ' . $this->table('Tags') . '
				WHERE 	name = "' . $this->db->escape($tag) . '" AND
					is_hidden = 0';
			$result = $this->db->single_query_assoc($sql);
			$this->tmpTag = array_merge($item, $result);
		}
		return (isset($this->tmpTag) ? $this->tmpTag : array());
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

	function getPagingArticle($currPage, $itemsCnt, $listLimit, $article)
	{
		$url = $this->oShared->getArticleUri($article['id'], $article['uri'], $article['published']);

		$pn = &moon::shared('paginate');
		$pn->set_curent_all_limit($currPage, $itemsCnt, $listLimit);
		$pn->skinID = 'nav_block_article';
		$pn->set_url(
			$url . '?page={pg}',
			$url
		);
		$pnInfo = $pn->get_info();

		$this->tmpLimit = $pnInfo['sqllimit'];
		return $pn->show_nav();
	}

	//***************************************
	//        --- RSS FEEDS, XML ---
	//***************************************
	function rssXml($catIds = array(), $fulldesc, $descrimage, $promofree, $nodescr, $limit, $isTurbo = FALSE, $titleLimit = FALSE)
	{
		$page = &moon::page();
		$sitemap = &moon::shared('sitemap');
		$txt = &moon::shared('text');
		$oShared = $this->oShared;
		$category = $this->getCategory();

		$homeURL = rtrim($page->home_url(), '\/');
		$rootUri = $sitemap->getLink();
		$catUri = !empty($category['uri']) ? $category['uri'] . '/' : '';
		$feedUrl = $homeURL . $rootUri . $page->requested_event('REST');
		$link = $homeURL . $rootUri . $catUri;
		$xml = &moon::shared('rss');
		$content = $xml->feed($feedUrl, 'rss', FALSE);

		if ($content === FALSE) {
			$tpl = &$this->load_template();
			$info = $tpl->parse_array('info');

			// feed info
			$feedTitle = $info['rssTitle'];
			if ($isTurbo) {
				$feedTitle .= ' Nightly Turbo';
				$promofree = FALSE;
			}

			$xml->info(
				array(
					'title' => $feedTitle,
					'description' => $info['rssDescription'],
					'url:page' => $link,
					'author' => 'PokerNetwork.com'
				)
			);

			// feed items
			$items = $this->getRssFeedItems($catIds, $limit, $promofree, $isTurbo);
			foreach($items as $item) {
				$item['uri'] = $oShared->getArticleUri($item['id'], $item['uri'], $item['published']);

				$summary = '';
				if ($descrimage) {

					$imageSrc = $homeURL . $this->getImageSrc($item['img'], 'thumb_');
					$summary = '<img src="' . $imageSrc . '" style="float: left; padding: 5px;" />';
				}
				if ($nodescr == FALSE) {
					$summary .= ($fulldesc && $item['is_promo'] == 0) ? $item['content_html'] : $item['summary'];
				}
				$xml->item(
					array(
						'title' => ($titleLimit) ? $txt->excerpt($item['title'], 40) : $item['title'],
						'url' => $homeURL . $item['uri'],
						'created' => $item['published'],
						'updated' => $item['updated'],
						'summary' => $summary
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

	// additionally used from sys.rss
	function getRssFeedItems($categoryIds = array(), $limit = 10, $promofree = FALSE, $isTurbo = FALSE)
	{
		$sql = '
			SELECT id,title,img,uri,summary,content_html,published,updated,is_promo,is_turbo
			FROM ' . $this->table('Articles') . ' a ' .
			$this->sqlWhere() . '
				' . (is_array($categoryIds) && count($categoryIds)
					? 'AND a.category_id IN (' . implode(',', $categoryIds) . ')'
					: ''
				) . (($promofree)
					? ' AND a.is_promo = 0 '
					: ''
				) . (($isTurbo)
					? ' AND a.is_turbo = 1 '
					: ''
				) . '
			ORDER BY
				published DESC,
				title ASC
			LIMIT ' . $limit . '
		';
		$result = $this->db->array_query_assoc($sql);
		$items = array();
		foreach ($result as $r) {
			if ($r['is_turbo']) {
				$r['content_html'] .= $this->htmlArticleTurboContents($r['id'], TRUE);
			}
			$items[] = $r;
		}
		return $items;
	}

	function xmlArticles($catIds = array(), $limit = 10, $isTurbo) {
		$page = &moon::page();
		$oShared = $this->oShared;
		$homeURL = rtrim($page->home_url(), '\/') . '/';
		$promofree = FALSE;

		$xmlWriter = new moon_xml_write;
		$xmlWriter->encoding('utf-8');
		$xmlWriter->open_xml();

		$articles = $this->getRssFeedItems($catIds, $limit, $promofree, $isTurbo);
		$xmlWriter->start_node('articles');
		foreach ($articles as $item) {
			$xmlWriter->start_node('item');
			$xmlWriter->node('title', '', $item['title']);
			$xmlWriter->node('url', '',  trim($homeURL, '/') . $oShared->getArticleUri($item['id'], $item['uri'], $item['published']));
			$xmlWriter->node('image', '', $homeURL . trim($this->getImageSrc($item['img'], 'thumb_'), '\/'));
			$xmlWriter->node('created', '', $item['published']);
			$xmlWriter->node('updated', '', $item['updated']);
			$xmlWriter->node('summary', '', $item['summary']);
			$xmlWriter->end_node('item');
		}
		$xmlWriter->end_node('articles');
		return $xmlWriter->close_xml();
	}

}
?>