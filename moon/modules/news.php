<?php
class news extends moon_com {

	function events($event, $par) {

		$this->use_page('2Col');
		$page = & moon :: page();
		if ($url = $page->requested_event('REST')) {
			if (substr($url, - 4) === '.htm') {
				//article uri
				$this->set_var('uri', substr($url, 0, - 4));
			}
			else {
				//neteisingas urlas
				$page->page404();
			}
		}
		$pg = isset ($_GET['page']) ? $_GET['page'] : 1;
		$this->set_var('psl', (int) $pg);
	}


	function properties() {
		return array('psl' => 1);
	}


	function main($vars) {
		$this->myTable = $this->table('News');
		if (isset ($vars['uri'])) {
			$output = $this->htmlArticle($vars['uri']);
		}
		else {
			$output = $this->htmlList($vars);
		}
		return $output;
	}


	function htmlList($vars) {
		$tpl = $this->load_template();
		$page = & moon :: page();

		$m = array('items' => '', 'puslapiai' => '');

		$sitemap = & moon :: shared('sitemap');
		$m['title'] = $sitemap->getTitle();

		// iterpiam kategoriju box
		//$page->insert_html($this->htmlCategoryBox($catID), 'right');
		$catID = 0;
		//generuojam sarasa
		if ($count = $this->getListCount($catID)) {
			//puslapiavimui
			if (!isset ($vars['psl'])) {
				$vars['psl'] = 1;
			}
			$pn = & moon :: shared('paginate');
			$pn->set_curent_all_limit($vars['psl'], $count, 20);
			$pn->set_url(
				$this->linkas("#", '', array('page' => '{pg}')),
				$this->linkas("#")
				);
			$m['puslapiai'] = $pn->show_nav();
			$psl = $pn->get_info();
			if ($psl['curPage'] > 1) {
				$page->meta('robots', 'noindex,follow');
			}
			$dat = $this->getList($catID, $psl['sqllimit']);
			$archiveLastId = $this->get_var('archiveLastId');

			$urlArticle = $this->linkas('#', '{prefix}{uri}');
			$tpl->save_parsed('items', array('url.article' => $urlArticle));
			$locale = & moon :: locale();
			$srcImg = $this->get_var('srcImgNews');
			foreach ($dat as $d) {
				//kita
				$d['title'] = htmlspecialchars($d['title']);
				$d['summary'] = htmlspecialchars($d['summary']);
				$d['authors'] = htmlspecialchars($d['authors']);
				if ($d['img']) {
					$d['img'] = $srcImg . substr_replace($d['img'], '_', 13, 0);
					$d['img_alt'] = htmlspecialchars($d['img_alt']);
				}
				$d['prefix'] = $d['id'] > $archiveLastId ? date('Y/m/', $d['date']): '';
				$d['date'] = $locale->datef($d['date'], 'Date');
				$m['items'] .= $tpl->parse('items', $d);
			}
		}
		return $tpl->parse('viewList', $m);
	}


	function htmlArticle($uri) {
		$tpl = $this->load_template();
		$page = & moon :: page();
		$locale = & moon :: locale();

		if (!count($article = $this->getArticle($uri))) {
			//jei neradom
			$page->page404();
		}

		$page->css('/i/article.css');

		// iterpiam kategoriju box
		//$page->insert_html($this->htmlCategoryBox($article['category']), 'right');

		// meta info
		$pageTitle = $page->title();
		$page->title($article['title'] . ' | ' . $page->title());
		$page->meta('keywords', $article['meta_keywords']);
		$page->meta('description', $article['meta_description']);

		//gal reikia pergeneruoti html
		if ($article['recompile'] && ($html = $this->recompile($article['id'])) !== FALSE) {
			$article['content_html'] = $html;
		}
		$m = array(
			'pageTitle' => htmlspecialchars($pageTitle),
			'title' => htmlspecialchars($article['title']),
			'authors' => htmlspecialchars($article['authors']),
			'content_html' => $article['content_html'],
			'url.faq_index' => $this->linkas('#'),
			'date' => $locale->datef($article['date'], 'Article')
			);
		if ($article['img']) {
			$m['img'] = $this->get_var('srcImgNews') . $article['img'];
			$m['img_alt'] = htmlspecialchars($article['img_alt']);
		}
		$m['url.self'] = $this->linkas('#', $uri);

		$tools = & moon :: shared('tools');
		$m['shareThis'] = $tools->toolbar();

		return $tpl->parse('viewArticle', $m);
	}


	//***************************************
	//           --- DB AND OTHER ---
	//***************************************
	function getListCount($catID) {
		$sql = 'SELECT count(*) FROM ' . $this->myTable . $this->_where($catID);
		$m = $this->db->single_query($sql);
		return (count($m) ? $m[0] : 0);
	}


	function getList($catID, $limit = '') {
		$sql = '
			SELECT id,title,date,summary,img,img_alt,uri, authors
			FROM ' . $this->myTable . $this->_where($catID) . ' ORDER BY date DESC' . $limit;
		return $this->db->array_query_assoc($sql);
	}


	function _where($catID) {
		if (isset ($this->tmpWhere)) {
			return $this->tmpWhere;
		}
		$locale = & moon :: locale();
		$now = floor($locale->now() / 300) * 300;
		$w = array();
		if ($catID) {
			$w[] = 'category=' . intval($catID);
		}
		$w[] = "hide=0";
		$w[] = "date<$now";
		$where = count($w) ? (' WHERE ' . implode(' AND ', $w)) : '';
		return ($this->tmpWhere = $where);
	}


	function getCategory($uri = FALSE) {
		if ($uri !== FALSE) {
			$this->tmpCategory = $this->db->single_query_assoc('
				SELECT id, uri, title, meta_keywords, meta_description, description
				FROM ' . $this->table('NewsCategories') . '
				WHERE uri="' . $this->db->escape($uri) . '" AND hide=0
			');
		}
		return (isset ($this->tmpCategory) ? $this->tmpCategory : array());
	}


	function getArticle($uri) {
		// first try to get by new uri with year month separated
		$uriChunks = explode('/', $uri);
		$cnt = count($uriChunks);
		if ($cnt === 3 && is_numeric($uriChunks[0]) && is_numeric($uriChunks[1])) {
			$articleUri = $uriChunks[2];
			$year = intval($uriChunks[0]);
			$month = intval($uriChunks[1]);

			$result = $this->db->single_query_assoc('
				SELECT id, uri, title, date, authors, category, meta_keywords, meta_description, recompile, img, img_alt, tags, content_html
				FROM ' . $this->myTable . '
				WHERE	uri="' . $this->db->escape($articleUri) . '" AND
				FROM_UNIXTIME(date, \'%Y\') = ' . $year . ' AND
				FROM_UNIXTIME(date, \'%m\') = ' . $month . ' AND
				hide=0');
			if (!empty($result)) {
				return $result;
			}
		}
		// archive article
		return $this->db->single_query_assoc('
			SELECT id, uri, title, date, authors, category, meta_keywords, meta_description, recompile, img, img_alt, tags, content_html
			FROM ' . $this->myTable . '
			WHERE uri="' . $this->db->escape($uri) . '" AND hide=0
		');
	}

	function recompile($id) {
		$a = $this->db->single_query_assoc('
			SELECT id, content	FROM ' . $this->myTable . '
			WHERE id=' . (int) $id);
		$s = FALSE;
		if (count($a)) {
			$rtf = $this->object('rtf');
			$rtf->setInstance($this->get_var('rtf'));
			list(, $s) = $rtf->parseText($id, $a['content']);
			$ins = array('content_html' => $s, 'recompile' => 0);
			$this->db->update($ins, $this->myTable, $id);
		}
		return $s;
	}

}

?>