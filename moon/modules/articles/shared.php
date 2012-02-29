<?php
class shared extends moon_com {

	function onload()
	{
		$this->articleType = null;
		$this->typeNews = $this->get_var('typeNews');
		
		$this->tblArticles = $this->table('Articles');
		$this->tblCategories = $this->table('Categories');
		$this->tblAuthors = $this->table('Authors');
		
		$this->addToSuffix = $this->get_var('addToSuffix');
		$this->suffixStartId = $this->get_var('suffixStartId');
	}

	function articleType($type)
	{
		$this->articleType = $type;
	}

	function properties() {
		return array();
	}
	
	function main($vars)
	{
		return '';
	}

	//***************************************
	//           --- HTML ---
	//***************************************
	function htmlAuthors($idsString, $enableLinks = FALSE, $idsArr = array())
	{
		$authors = '';
		if (!empty($idsString)) {
			if (empty($idsArr)) {
				$a = explode(',', $idsString);
				$ids = array();
				foreach ($a as $id) {
					if ($id = intval($id)) {
						$ids[] = $id;
					}
				}
				//kas yra istrinti ir turi pakaitala
				if (!count($ids)) return '';
				$m = $this->db->array_query_assoc(
					"SELECT duplicates,id FROM " . $this->tblAuthors .
					" WHERE id IN (" . implode(', ', $ids) . ") AND duplicates>0"
				);
				//pakeiciam id tu, kurie turi pakaitala
				foreach ($m as $v) {
					$k = array_search($v['id'],$ids);
					if (isset($ids[$k])) {
						$ids[$k] = $v['duplicates'];
					}
				}
				//dabar gaunam sarasa
				$m = $this->db->array_query_assoc(
					"SELECT name,id,uri,twitter,gplus_url FROM " . $this->tblAuthors .
					" WHERE id IN (" . implode(', ', $ids) . ")"
				);
				$authors = array();
				foreach ($m as $d) {
					$authors[$d['id']] = $d;
				}
				$r = array();
				foreach ($ids as $id) {
					if (isset($authors[$id])) {
						$r[$id] = $authors[$id];
					}
				}
				$idsString = implode(',', array_keys($r));
				$this->htmlAuthorsData = $r;
			} else {
				$r = $idsArr;
			}
			
			// create names string
			$au = explode(',', $idsString);
			if (count($au)) {
				$d = array();
				foreach ($au as $id) {
					if (isset($r[$id])) {
						$a = $r[$id];
						$a['name'] = htmlspecialchars($a['name']);
						if ($enableLinks && $a['uri']) {
							$sitemap = &moon::shared('sitemap');
							$a['name'] = '<a class="author" href="' . $sitemap->getLink('editors') . $a['uri'] . '/">' . $a['name'] . '</a>';
						}
						$d[] = $a['name'];
					}
				}
				$authors = implode(', ', $d);
			}
		}
		return $authors;
	}

	function getImageSrc($imageFn, $prefix = '', $absolute = FALSE)
	{
		if (empty($imageFn)) return NULL;
		$fnParts = explode(':', $imageFn);
		$imagePrefix = 1 == count($fnParts)
			? NULL
			: $fnParts[0];
		$imageFn = array_pop($fnParts);

		if ($imagePrefix == 'shr') return;

		static $srcArticlesStd;
		if (!isset($srcArticlesStd)) {
			$srcArticlesStd = $this->get_var('imagesSrcArticlesStd');
		}

		$imageFn = $srcArticlesStd . substr($imageFn, 0, 4) . '/' . $prefix . substr($imageFn, 4);
		if ($imagePrefix != NULL) {
			$rhost = $imagePrefix == 'com' ? 'www' : $imagePrefix;
			return 'http://' . $rhost . '.pokernetwork.' . (is_dev() ? 'dev' : 'com') . $imageFn;
		}
		if (TRUE === $absolute) {
			if (!isset($this->homeUrl)) {
				$p = &moon::page();
				$this->homeUrl = rtrim($p->home_url(), '/');
			}
			return $this->homeUrl . $imageFn;
		}
		return $imageFn;
	}

	function getAuthorsData($idsString)
	{
		$a = explode(',', $idsString);
		$ids = array();
		foreach ($a as $id) {
			if ($id = intval($id)) {
				$ids[] = $id;
			}
		}
		//kas yra istrinti ir turi pakaitala
		if (!count($ids)) return '';
		$m = $this->db->array_query_assoc(
			"SELECT duplicates,id FROM " . $this->table('Authors') .
			" WHERE id IN (" . implode(', ', $ids) . ") AND duplicates>0"
		);
		//pakeiciam id tu, kurie turi pakaitala
		foreach ($m as $v) {
			$k = array_search($v['id'],$ids);
			if (isset($ids[$k])) {
				$ids[$k] = $v['duplicates'];
			}
		}
		//dabar gaunam sarasa
		$m = $this->db->array_query_assoc(
			"SELECT name,id,uri FROM " . $this->table('Authors') .
			" WHERE id IN (" . implode(', ', $ids) . ")"
		);
		$authors = array();
		foreach ($m as $d) {
			$authors[$d['id']] = $d;
		}
		$r = array();
		foreach ($ids as $id) {
			if (isset($authors[$id])) {
				$r[$id] = $authors[$id];
			}
		}
		return $r;
	}
	
	//***************************************
	//           --- DB ---
	//***************************************
	function getArticles($sqlLimit = 'LIMIT 0,4', $withForumIds = false)
	{
		$locale = &moon::locale();
		$now = floor($locale->now() / 300) * 300;

		$sql = 'SELECT id,title,article_type,content_type,uri,authors,img,img_alt,published,summary,comm_count,category_id
			FROM ' . $this->table('Articles') . ' USE INDEX (i_articles)
			WHERE	published <= ' . $now . ' AND
				is_hidden = 0
			ORDER BY published DESC ' .
			$sqlLimit;
		$return = $this->db->array_query_assoc($sql);
		return $return;
	}

	/**
	 * Returns tags found in 'tags' db table
	 * @param array tags to search
	 * @return array tags found
	 */
	function getActiveTags($tags)
	{
		$sql = '
			SELECT name
			FROM ' . $this->table('Tags') . '
			WHERE 	name IN (\'' . implode('\',\'', $tags) . '\') AND
				is_hidden = 0';
		$result = $this->db->array_query_assoc($sql);
		$items = array();
		foreach ($result as $r) {
			$items[] = $r['name'];
		}
		return $items;
	}
	
	function getToursByTags($tags = '')
	{
		if ($tags == '') return array();
		$tags = explode(',', strtolower($tags));
		$tours = poker_tours();
		
		$items = array();
		foreach ($tours as $tour) {
			$uri = (!empty($tour['uri'])) ? str_replace('-', ' ', $tour['uri']) : null;
			$title = (!empty($tour['title'])) ? $tour['title'] : null;
			$key = (!empty($tour['key'])) ? $tour['key'] : null;
			
			if (
				in_array(strtolower($uri), $tags, true) ||
				in_array(strtolower($title), $tags, true) ||
				in_array(strtolower($key), $tags, true)
			) {
				$items[] = array(
					'url.tour' => '/' . $tour['uri'] . '/',
					'thumbSrc' => $tour['img1'],
					'imgAlt' => $tour['title'],
					'title' => htmlspecialchars($tour['title'])
				);
			}
		}
		return $items;
	}
	
	//***************************************
	//           --- URLS ---
	//***************************************
	/**
	 * Returns article uri with suffix at the end if needed
	 * If published arg provided - /year/month/ prefix will be added
	 * Additionally used from sys.rss
	 * 
	 * @param int article id
	 * @param string article uri
	 * @param int published
	 * @param int article type
	 */
	function getArticleUri($id, $uri = '', $published = FALSE, $articleType = FALSE)
	{
		if ($articleType === false && $this->articleType) $articleType = $this->articleType;
		/*if (($articleType == $this->typeNews) && ctype_digit($published)) {
			$uri = date('Y', $published) . '/' . date('m', $published) . '/' . $uri;
		}*/
		$suffix = '';
		if ($id >= $this->suffixStartId) {
			$suffix = '-' . ($this->addToSuffix + $id);
		}
		$uri = ($uri !== '') ? $uri . $suffix : $uri;
		$uri = $this->url('#', $uri, $articleType);
		return $uri;
	}
	
	/**
	 * Returns category uri
	 * 
	 * @param string category uri
	 * @param int article type
	 */
	function getCategoryUri($uri = '', $articleType = FALSE)
	{
		return $this->url('#' . $uri, '', $articleType);
	}
	
	/**
	 * Returns article tag url
	 * @param string tag
	 * @param string tag url
	 */
	function getTagUrl($tag) {

		$tagSrc = 'news';
		/*$sitemap = &moon::shared('sitemap');
		$uri = '';
		switch ($this->articleType) {
			case $this->typeNews:
			default:
				$tagSrc = 'news';
				//$uri = $sitemap->getLink('news');
				break;
		}*/
		$ctags = $this->object('other.ctags');
		return $ctags->getUrl($tag, $tagSrc);

		//return $uri . 'tags/' . moon::shared('text')->make_uri(urlencode($tag)) . '/';
		//return $this->linkas("$uri");
	}
	
	/**
	* If article type set, calls $this->linkas() of that component
	* @return string uri
	*/
	function url($event = '', $par = '', $articleType = FALSE)
	{
		$o = null;
		$type = (!empty($articleType)) ? $articleType : $this->articleType;
		switch ($type) {
			case $this->typeNews:
				$o = $this->object('news');
				break;
			default:
				break;
		}
		if ($o) {
			return $o->linkas($event, $par);
		}
		return '';
	}
	
	//***************************************
	//           --- HELPERS ---
	//***************************************
	/**
	 * Gets article id from it's uri
	 * @param string article uri
	 * @return mixed Article id if found, FALSE otherwise
	 */
	function getIdFromUri($uri)
	{
		$uriChunks = explode('-', $uri);
		$cnt = count($uriChunks);
		
		$suffix = FALSE;
		if ($cnt > 1 && isset($uriChunks[$cnt - 1]) AND is_numeric($uriChunks[$cnt - 1])) {
			$nr = $uriChunks[$cnt - 1];
			$id = $nr - $this->addToSuffix;
			if ($id >= $this->suffixStartId) {
				$suffix = $id;
			}
		}
		return $suffix;
	}
	
	//***************************************
	//          --- OTHER ---
	//***************************************
	function updateViewsCount($id = null)
	{
		if (!$id) return false;
		$this->db->query('
			UPDATE LOW_PRIORITY ' . $this->table('Articles') . '
			SET views_count=views_count+1 WHERE id = ' . intval($id)
		);
	}
	
}
?>