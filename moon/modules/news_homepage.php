<?php
class news_homepage extends moon_com {

	function main($vars) {

		$this->myTable = $this->table('News');
		$tpl = $this->load_template();
		$page = & moon :: page();
		$sitemap = & moon :: shared('sitemap');
		$locale = moon::locale();

		$m = array(
			'url.viewAll' => $sitemap->getLink('news'),
			'homepage-news:items' => '',
			'homepage-news-more:items' => ''
		);


		$m['title'] = $sitemap->getTitle();

		$items = $this->getList();
		$itemsMore = array_splice($items, 1);

		$urlArticle = $this->linkas('news#', '{prefix}{uri}');
		$tpl->save_parsed('homepage-news:items', array('url.article' => $urlArticle));
		$tpl->save_parsed('homepage-news-more:items', array('url.article' => $urlArticle));
		$srcImg = $this->get_var('srcImgNews');

		// main list
		if (($count = count($items)) != 0) {
			foreach ($items as $item) {
				$item['title'] = htmlspecialchars($item['title']);
				$item['description'] = htmlspecialchars($item['summary']);
				if ($item['img']) {
					$item['img'] = $srcImg . $item['img'];
					$item['img_alt'] = htmlspecialchars($item['img_alt']);
				}
				$item['prefix'] = date('Y/m/', $item['date']);
				$item['date'] = $locale->datef($item['date'], 'News');
				$m['homepage-news:items'] .= $tpl->parse('homepage-news:items', $item);
			}
		}

		// additional articles list
		foreach ($itemsMore as $item) {
			$item['title'] = htmlspecialchars($item['title']);
			$item['description'] = htmlspecialchars($item['summary']);
			$item['prefix'] = date('Y/m/', $item['date']);
			$item['date'] = $locale->datef($item['date'], 'News');
			$m['homepage-news-more:items'] .= $tpl->parse('homepage-news-more:items', $item);
		}

		return $tpl->parse('main', $m);
	}

	//***************************************
	//           --- DB AND OTHER ---
	//***************************************
	function getList($catID = 0) {
		$sql = '
			SELECT id,title,date,summary,img,img_alt,uri, authors
			FROM ' . $this->myTable . $this->_where($catID) . ' ORDER BY date DESC LIMIT 4';
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

}

?>