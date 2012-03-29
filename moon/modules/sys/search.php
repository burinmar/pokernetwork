<?php


class search extends moon_com {


	function events($event, $par) {
		$this->use_page('2col');
		if (isset ($par[0])) {
			$this->set_var('onTab', $par[0]);
		}
	}


	function main($vars) {
		$tpl = & $this->load_template();
		$page = & moon :: page();
		$page->meta('robots', 'noindex,nofollow');
		$m = array('tab-item' => '', 'results' => '', 'puslapiai' => '');
		$q = isset ($_GET['q']) ? trim($_GET['q']) : '';
		$m['word'] = htmlspecialchars($q);
		$m['pageTitle'] = htmlspecialchars($page->title());
		$m['action'] = $this->linkas('#');
		if ($q !== '') {
			$cachedCount = $page->get_global($this->my('fullname') .'.cs');
			if ($cachedCount != '' && $cachedCount['t'] > time() - 1 && $cachedCount['q'] == $q) {
				$cachedCount['t'] = time();
				$countAll = $cachedCount['s'];
			} else {
				$countAll = $this->searchCount($q);
				$cachedCount = array(
					't' => time(),
					'q' => $q,
					's' => $countAll
				);
			}
			$page->set_global($this->my('fullname') .'.cs', $cachedCount);
			$m['countTotal'] = array_sum($countAll);
			//tabs
			$tabs = $tpl->parse_array('tabs');
			$a = array();
			$onTab = !empty ($vars['onTab']) && isset ($tabs[$vars['onTab']]) ? $vars['onTab'] : '';
			$sitemap = moon :: shared('sitemap');
			$tpl->save_parsed('tab-item', array('url' => $this->linkas('#', '{tab}', array('q' => $q))));
			foreach ($countAll as $tab => $count) {
				if (!$count) {
					continue;
				}
				if ($onTab === '') {
					$onTab = $tab;
				}
				$label = isset ($tabs[$tab]) && $tabs[$tab] ? $tabs[$tab] : $sitemap->getTitle($tab);
				if ($label === '') {
					$label = $tab;
				}
				$a['title'] = htmlspecialchars($label);
				$a['count'] = $count;
				$a['tab'] = $tab;
				$a['on'] = $tab == $onTab;
				$m['tab-item'] .= $tpl->parse('tab-item', $a);
			}
			if ($m['countTotal'] == 0) {
				$m['clear_url'] = true;
			}
			//puslapiavimui
			if ($onTab && ($count = $countAll[$onTab])) {
				$onPage = isset ($_GET['pg']) ? (int) $_GET['pg'] : 1;
				$pn = & moon :: shared('paginate');
				// kiek irasu puslapyje
				switch ($onTab) {

					case 'images':
						$iPerPage = 35;
						break;

					/*
					case 'video':
						$iPerPage = 25;
						break;
					*/

					default:
						$iPerPage = 20;
				}
				$pn->set_curent_all_limit($onPage, $count, $iPerPage);
				$get = array('q' => $q);
				$pn->set_url($this->linkas("#", $onTab, $get + array('pg' => '{pg}')), $this->linkas("#", $onTab, $get));
				$m['puslapiai'] = $pn->show_nav();
				$m['tabId'] = $onTab;
				$psl = $pn->get_info();
				//results
				$func = 'getResults' . ucfirst($onTab);
				$m['results'] = $this->$func($q, $psl['sqllimit']);
			}
		}
		$res = $tpl->parse('main', $m);
		return $res;
	}


	//***************************************
	//           --- DB AND OTHER ---
	//***************************************
	function searchCount($q) {
		$locale = & moon :: locale();
		$now = floor($locale->now() / 300) * 300;
		$a = array();
		$a[] = "(SELECT 'news', count(*)  FROM articles
			WHERE  is_hidden=0 AND published<$now
				" . $this->_where($q, array('title', 'img_alt')) . ')';
		$a[] = "(SELECT 'video', count(*)  FROM videos
			WHERE is_hidden=0 
				" . $this->_where($q, array('name', 'tags', 'short_description')) . ')';
		$a[] = "(SELECT 'images', count(*)  FROM reporting_ng_photos
			WHERE is_hidden=0
				" . $this->_where($q, array('image_alt')) . ')';
		$sql = implode(' UNION ALL ', $a);
		return $this->db->array_query($sql, TRUE);
	}


	function _where($q, $fields) {
		$q = $this->db->escape($q, TRUE);
		$w = array();
		foreach ($fields as $f) {
			$w[] = "$f like '%$q%'";
		}
		$where = count($w) ? (' AND (' . implode(' OR ', $w) . ')') : '';
		return $where;
	}


	function marker($q, $s) {
		$q = preg_quote($q, '/');
		$s = preg_replace("/$q/i", '<span class="searchTerm">$0</span>', $s);
		return $s;
	}


	/******** Results ****/
	function getResultsNews($q, $limit) {
		$locale = & moon :: locale();
		$now = floor($locale->now() / 300) * 300;
		$sql = "
			SELECT id,title,published as date,summary,img,img_alt,uri,article_type
			FROM articles
			WHERE is_hidden=0 AND published<$now " . $this->_where($q, array('title', 'img_alt')) . '
			ORDER BY date DESC' . $limit;
		$dat = $this->db->array_query_assoc($sql);
		$tpl = & $this->load_template();
		$locale = & moon :: locale();
		$oShared = $this->object('articles.shared');
		$s = '';
		foreach ($dat as $d) {
			//kita
			$d['title'] = $this->marker($q, htmlspecialchars($d['title']));
			$d['summary'] = $this->marker($q, htmlspecialchars($d['summary']));
			if ($d['img']) {
				$d['img'] = $oShared->getImageSrc($d['img'], 'thumb_');
				$d['img_alt'] = htmlspecialchars($d['img_alt']);
			}
			$d['url'] = $oShared->getArticleUri($d['id'], $d['uri'], $d['date'], $d['article_type']);
			$d['date'] = $locale->datef($d['date'], 'Date');
			$s .= $tpl->parse('itemArticle', $d);
		}
		return $s;
	}


	function getResultsVideo($q, $limit) {
		$sql = "
			SELECT id,name,published_date,thumbnail_url,length
			FROM videos
			WHERE is_hidden=0
				" . $this->_where($q, array('name', 'tags', 'short_description')) . '
			ORDER BY creation_date DESC' . $limit;
		$dat = $this->db->array_query_assoc($sql);
		$tpl = & $this->load_template();
		$locale = & moon :: locale();
		$page = moon::page();
		$oVideo = $this->object('video.video');
		$page->css('/css/video.css');
		$s = '';
		foreach ($dat as $d) {
			$uri = $oVideo->getVideoUri($d['id'], $d['name']);
			$time = substr($d['published_date'], 0, 10);
			$d['date'] = $locale->datef($time, 'News');
			$d['url.video'] = $this->linkas('video.video#', $uri);
			$d['title'] = htmlspecialchars($d['name']);
			$d['thumbSrc'] = $d['thumbnail_url'];
			$d['length'] = $oVideo->miliSecToTime($d['length']);
			$s .= $tpl->parse('itemVideo', $d);
		}
		return $s;
	}

	function getResultsImages($q, $limit)
	{
		$tpl = & $this->load_template();
		$sImages = '';
		$srcImg = 'http://pnimg.net';
		$page = &moon::page();
		$page->css('/css/jquery/lightbox-0.5.css');
		$page->js('/js/jquery/lightbox-0.5.js');

		$sql = "
			SELECT id, image_src, image_alt, event_id
			FROM reporting_ng_photos WHERE is_hidden=0
				" . $this->_where($q, array('image_alt')) . ' ORDER BY created_on DESC' . $limit;
		$photos = $this->db->array_query_assoc($sql);

		$eventIds = array();
		foreach ($photos as $photo) {
			$eventIds[] = $photo['event_id'];
		}
		$eventIds = array_unique($eventIds);
		if (0 != count($eventIds)) {
			$events = $this->db->array_query_assoc('
				SELECT e.id, e.name ename, t.name tname
				FROM reporting_ng_events e
				INNER JOIN reporting_ng_tournaments t
					ON t.id=e.tournament_id
				WHERE e.id IN (' . implode(',', $eventIds) . ')
			', 'id');
		}

		foreach ($photos as $photo) {
			$photo['src_big'] = $photo['image_src'];
			$photo['src_big'][strlen($photo['src_big']) - 15] = 'm';
			$sImages .= $tpl->parse('itemImg', array(
				'src' => $srcImg . $photo['image_src'],
				'alt' => empty($photo['image_alt'])
					? '&nbsp;'
					: htmlspecialchars($photo['image_alt']),
				'src_big' =>  $srcImg . $photo['src_big'],
				'event_name' => isset($events[$photo['event_id']])
					? htmlspecialchars($events[$photo['event_id']]['ename'])
					: '',
				'tournament_name' => isset($events[$photo['event_id']])
					? htmlspecialchars($events[$photo['event_id']]['tname'])
					: ''
			));
		}

		return $tpl->parse('itemsImg', array(
			'photos' => $sImages
		));
	}
}

?>