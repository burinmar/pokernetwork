<?php
class ctags extends moon_com 
{
	function events($event,$par)
	{
		$page = moon::page();
		$urlSeg = $page->requested_event('segments');

		if (isset($urlSeg[0]) && !empty($urlSeg[0])) {
			if (!isset($urlSeg[1])) {
				$this->redirect('#' . $urlSeg[0]);
			}
			$this->set_var('render', 'tag');
			$this->set_var('tag',    $urlSeg[0]);
			if (empty($urlSeg[1])) {
				$this->set_var('filter', NULL);
			} else {
				if (FALSE == ($filter = array_search($urlSeg[1], $this->getFilterUris()))) {
					moon::page()->page404();
				}
				$this->set_var('filter', $urlSeg[1]);
			}
		} else {
			$this->set_var('render', 'cloud');
		}

		$this->set_var('page', isset($_GET['page'])
			? $_GET['page']
			: 1);

		$this->use_page('ctags');
	}

	function main($argv) 
	{
		switch (array_get_del($argv, 'render')) {
		case 'tag':
			return $this->renderTag($argv);
		case 'cloud':
			return $this->renderCloud();
		}
	}

	private function renderCloud()
	{
		$tpl = $this->load_template();
		$sitemap = moon::shared('sitemap');
		$pageInfo = $sitemap->getPage();
		$tplArgv = array(
			'list.tags' => array(),
			'description' => $pageInfo['content_html']
		);

		$tags = $this->getTopTags();
		$minWeight = 99999;
		$maxWeight = 1;
		foreach($tags as $id => $tag) {
			$minWeight = min($minWeight, $tag['use_count']);
			$maxWeight = max($maxWeight, $tag['use_count']);
		}

		if (0 != count($tags)) {
			$minFontSize = 14;
			$maxFontSize = 36;
			$spread = max(1, $maxWeight - $minWeight);
			$step = ($maxFontSize - $minFontSize) / ($spread);
			
			usort($tags, array($this, 'helperTagCmp'));
			foreach ($tags as $tag) {
				$weight = $tag['use_count'];
				$size = round($minFontSize + (($weight - $minWeight) * $step));
				
				$tplArgv['list.tags'][] = trim($tpl->parse('cloud:tag', array(
					'name' => htmlspecialchars($tag['title']),
					'url.tag' => $this->linkas('#' . urlencode($tag['tag'])),
					'fontSize' => $size
				)));
			}
		}
		$tplArgv['list.tags'] = implode(' ', $tplArgv['list.tags']);

		return $tpl->parse('cloud:main', $tplArgv);
	}

	private function helperTagCmp($a, $b)
	{
		return strcmp($a['tag'], $b['tag']);
	}
	
	/**
	 * @todo cache for a bit 
	 * @todo maybe add WHERE active_since<CURRENT_TIMESTAMP
	 */
	private function getTopTags()
	{
		$tags = $this->db->array_query_assoc('
			SELECT tag_id id, COUNT(tag_id) use_count FROM ctags_usages
			GROUP BY tag_id
			ORDER BY COUNT(tag_id) DESC
			LIMIT 50
		', 'id');
		if (0 != count($tags)) {
			$tagNames = $this->db->array_query_assoc('
				SELECT id, tag, title FROM ctags_tags
				WHERE id IN (' . implode(',', array_keys($tags)) . ')
			', 'id');
			foreach ($tags as $key => $tag) {
				if (!isset($tagNames[$tag['id']])) {
					unset($tags[$key]);
				} else {
					$tags[$key]['tag'] = $tagNames[$tag['id']]['tag'];
					$tags[$key]['title'] = $tagNames[$tag['id']]['title'];
				}
			}
		}

		return $tags;
	}

	private function renderTag($argv)
	{
		$page = moon::page();
		$locale = moon::locale();
		$tpl = $this->load_template();
		$tplArgv = array(
			'tag' => htmlspecialchars($this->getReadableTag($argv['tag'])),
			'list.tabs' => '',
			'list.items' => '',
			'pagination' => ''
		);

		$tabs = $tpl->parse_array('list:tabs');
		$nonEmptyTabs = $this->getNonemptySources($argv['tag']);
		foreach ($tabs as $src => $tabName) {
			if (!empty($src) && !in_array($src, $nonEmptyTabs)) {
				continue;
			}

			$tplArgv['list.tabs'] .= $tpl->parse('list:tabs.item', array(
				'url' => $this->linkas('#' . $argv['tag']) . $this->getFilterUris($src),
				'title' => htmlspecialchars($tabName),
				'on' => $argv['filter'] == $src
			));
		}

		$itemsCnt = $this->getTagCount($argv['tag'], $argv['filter']);
		if (0 == $itemsCnt) {
			moon::page()->page404();
		}
		$pn = moon::shared('paginate');
		$pn->set_curent_all_limit($argv['page'], $itemsCnt, 20);
		$pn->set_url(
			$this->linkas('#' . $argv['tag']) . $this->getFilterUris($argv['filter']) . '?page={pg}',
			$this->linkas('#' . $argv['tag']) . $this->getFilterUris($argv['filter'])
		);
		$pnInfo = $pn->get_info();
		$tplArgv['pagination'] = $pn->show_nav();

		$entries = $this->getTagEntries($argv['tag'], $argv['filter'], $pnInfo['sqllimit']);
		foreach ($entries as $entry) {
			$tplArgv['list.items'] .= $tpl->parse('list:items.item', array(
				'url' => $entry['url'],
				'title' => htmlspecialchars($entry['title']),
				'img' => $entry['img'],
				'img_alt' => htmlspecialchars($entry['title']),
				'date'=> $locale->datef($entry['date'], 'Date'),
				'excerpt' => $entry['excerpt']
			));
		}

		$page->title($this->getReadableTag($argv['tag']) . ' | ' . $page->title());
		if (!empty($argv['filter']) || $pnInfo['curPage']>1) {
			$page->head_link($this->linkas('#' . $argv['tag']), 'canonical', '', array());
			//$tplArgv['filter'] = htmlspecialchars($tabs[$argv['filter']]);
			moon::shared('sitemap')->breadcrumb(array(
				'' => $tabs[$argv['filter']]
			));
		}

		return $tpl->parse('list:main', $tplArgv);
	}
	
	private function getNonemptySources($tag)
	{
		return array_keys($this->db->array_query_assoc('
			SELECT src_type
			FROM ctags_usages
			WHERE tag_id=' . $this->numericTag($tag) . '
			  AND active_since<FROM_UNIXTIME(' . (ceil(time() / 30) * 30) . ')
			GROUP BY src_type
		', 'src_type'));
	}

	private function getTagCount($tag, $filter)
	{
		$cnt = $this->db->single_query_assoc('
			SELECT COUNT(*) cnt FROM ctags_usages
			WHERE ' . $this->helperTagWhere($tag, $filter)
		);
		return $cnt['cnt'];
	}

	private function getTagEntries($tag, $filter, $limit)
	{
		$entriesR = $this->db->query('
			SELECT src_type, src_id FROM ctags_usages
			WHERE ' . $this->helperTagWhere($tag, $filter) . '
			ORDER BY active_since DESC ' .
			$limit
		);
		$entries = array();
		$grouped = array();
		while ($entry = $this->db->fetch_row_assoc($entriesR)) {
			$entries[] = $entry;
			$group_ = &$grouped[$entry['src_type']];
			if (!isset($group_)) {
				$group_ = array();
			}
			$group_[] = $entry['src_id'];
		}
		foreach ($grouped as $src => $group) {
			switch ($src) {
			case 'live-reporting':
				$grouped[$src] = $this->getLiveReportingEntries($group);
				break;
			case 'news':
			case 'strategy':
			case 'spanish-poker-show':
			case 'interviews':
				$grouped[$src] = $this->getArticlesEntries($src, $group);
				break;
			case 'videos':
				$grouped[$src] = $this->getVideosEntries($src, $group);
				break;
			}
		}
		foreach ($entries as $key => $entry) {
			if (isset($grouped[$entry['src_type']][$entry['src_id']])) {
				$entries[$key] = $grouped[$entry['src_type']][$entry['src_id']];
			} else {
				// soft error: warning
				unset($entries[$key]);
			}
		}

		return $entries;
	}

	private function helperTagWhere($tag, $filter)
	{
		$where = array(
			'tag_id=' . $this->numericTag($tag),
			'active_since<FROM_UNIXTIME(' . (ceil(time() / 30) * 30) . ')'
		);
		if (!empty($filter) ) {
			$where[] = 'src_type="' . $this->db->escape($filter). '"';
		} else {
			/**
			 * @todo check: on $filter == null, `src_type IN (*)` is constructed to use more of the `search` index; may or may not be benefitial (most likely not)
			 */
			$where[] = 'src_type IN ("interviews", "live-reporting", "news", "strategy", "spanish-poker-show", "videos")';
		}

		return implode(' AND ', $where);
	}

	private $tagCache = array();
	private function fetchTag($tag)
	{
		if (!isset($this->tagCache[$tag])) {
			$tag_ = $this->db->single_query_assoc('
				SELECT id, title FROM ctags_tags
				WHERE tag="' . $this->db->escape($tag) . '"
			');
			$this->tagCache[$tag] = isset($tag_['id'])
				? $tag_
				: NULL;
		}
	}

	private function numericTag($tag)
	{
		$this->fetchTag($tag);

		return isset($this->tagCache[$tag])
			? $this->tagCache[$tag]['id']
			: 0;
	}

	private function getReadableTag($tag)
	{
		$this->fetchTag($tag);

		return isset($this->tagCache[$tag])
			? $this->tagCache[$tag]['title']
			: NULL;
	}

	private function getLiveReportingEntries($ids) 
	{
		foreach ($ids as $k => $id) {
			$id = explode('-', $id);
			$ids[$k] = array(
				'id' => $id[0],
				'type' => $id[1]
			);
		}

		$entries = array();
		foreach ($this->object('livereporting.livereporting')->instEventModel('_src_tags')
				->getLiveReportingEntries($ids) as $entry) {
			$entries[$entry['id'] . '-' . $entry['type']] = array(
				'title' => $entry['title'],
				'excerpt' => $this->helperExcerpt($entry['contents']),
				'date' => $entry['date'],
				'url' => $entry['url'],
				'img' => $entry['img'],
			);
		}

		return $entries;
	}

	private function getArticlesEntries($src, $ids)
	{
		$entries = array();
		$entriesR = $this->db->query('
			SELECT id, title, published, uri, article_type, summary, img
			FROM articles
			WHERE is_hidden=0 AND id IN(' . implode(',', $ids) . ')
		');
		$article = $this->object('articles.shared');
		while ($entry = $this->db->fetch_row_assoc($entriesR)) {
			$entry_ = array(
				'title' => $entry['title'],
				'excerpt' => $entry['summary'],
				'date' => $entry['published'],
				'url' => $article->getArticleUri($entry['id'], $entry['uri'], $entry['published'], $entry['article_type']),
				'img' => $article->getImageSrc($entry['img'], 'thumb_'),
			);
			$entries[$entry['id']] = $entry_;
		}

		return $entries;
	}

	private function getVideosEntries($src, $ids)
	{
		$video = $this->object('video.video');
		$entries = array();
		$entriesR = $video->getVideoCtagsItems($ids);
		while ($entry = $this->db->fetch_row_assoc($entriesR)) {
			$entry_ = array(
				'title' => $entry['name'],
				'excerpt' => isset($entry['description'])
					? $entry['description']
					: $entry['short_description'],
				'date' => $entry['published'],
				'url' => isset($entry['uri'])
					? $video->linkas('#', $entry['uri'])
					: $video->linkas('#', $video->getVideoUri($entry['id'], $entry['name'])),
				'img' => $entry['thumbnail_url'],
			);
			$entries[$entry['id']] = $entry_;
		}

		return $entries;
	}

	private function helperExcerpt($text)
	{
		$text = strip_tags($text);
		if (strlen($text)>220) {
			$text = substr($text, 0, 240);
			$tmpPos = strrpos($text, '.');
			if ($tmpPos !== false) {
				$text = substr($text, 0, $tmpPos+1);
			}
		}
		return $text;
	}

	private function getFilterUris($key = FALSE)
	{
		// key => localised uri
		$uris = array(
			'interviews'         => 'interviews',
			'live-reporting'     => 'live-reporting',
			'news'               => 'news',
			'strategy'           => 'strategy',
			'spanish-poker-show' => 'spanish-poker-show',
			'videos'             => 'videos'
		);
		if (FALSE === $key) {
			return $uris;
		}

		return isset($uris[$key])
			? $uris[$key]
			: NULL;
	}

	/**
	 * Gets url. 
	 * For more actions, such as updates, inherit ctags_updater and add get*Handle method.
	 * Livereporting does not use this method, it uses one from ctags_updater.
	 * @param string $tag 
	 * @param string $src 
	 * @return string
	 */
	public function getUrl($tag, $src = '')
	{
		return $this->linkas('#' . make_uri($tag)) . $this->getFilterUris($src);
	}

	function getReportingHandle()
	{
		return new ctags_updater_livereporting($this->my('module'), 'ctags_updater_livereporting');
	}
}

class ctags_updater extends moon_com
{
	protected $src;

	function __construct($a, $b)
	{
		parent::__construct($a, $b);
		$this->ctags = $this->object('ctags');
	}

	public function getUrl($tag)
	{
		return $this->ctags->getUrl($tag, $this->src);
	}

	protected function updateTags($srcId, $tags, $activeSince = NULL)
	{
		foreach ($tags as $key => $title) {
			$tag = make_uri(trim($title));
			$title = trim($title);
			if (empty($tag)) {
				unset($tags[$key]);
				continue;
			}
			$tags[$key] = $this->getNumericTag($tag, $title);
		}

		$this->db->query('
			DELETE FROM `ctags_usages`
			WHERE src_type="' . $this->db->escape($this->src) . '" AND src_id="' . $this->db->escape($srcId) . '"' . 
			(0 != count($tags)
				? 'AND tag_id NOT IN(' . implode(',', $tags) . ')'
				: '')
		);
		foreach ($tags as $tag) {
			$this->dbInsert(array(
				'src_type' => $this->src,
				'active_since' => $activeSince
					? array('FROM_UNIXTIME', $activeSince)
					: '0000-00-00 00:00:00',
				'tag_id' => $tag,
				'src_id' => $srcId,
			), 'ctags_usages', 'IGNORE');
		}
	}

	private $tagsCache = array();
	private function getNumericTag($tag, $title)
	{
		if (!isset($this->tagsCache[$tag])) {
			$dbTag = $this->db->single_query_assoc('
				SELECT id FROM `ctags_tags`
				WHERE tag="' . $this->db->escape($tag) . '"
			');
			if (!empty($dbTag)) {
				$this->tagsCache[$tag] = $dbTag['id'];
			} else {
				$this->tagsCache[$tag] = $this->db->insert(array(
					'tag' => $tag,
					'title' => $title
				), 'ctags_tags', 'id');
			}
		}

		return $this->tagsCache[$tag];
	}

	private function dbInsert($row, $table, $hint='')
	{
		foreach ($row as $k => $v) {
			$row[$k] = is_null($v) ? 'NULL' : (
				is_array($v)
					? ($v[0] . '(\'' . $this->db->escape($v[1]) . '\')')
					: ("'" . $this->db->escape($v) . "'")
			);
		}
		$sql = "INSERT " . $hint . " INTO `". $table . "` (`" . implode("`, `", array_keys($row)) . "`) VALUES (" . implode(',', array_values($row)) . ')';
		$r = $this->db->query($sql);
		return $r
			? $this->db->insert_id()
			: FALSE;
	}	
}

class ctags_updater_livereporting extends ctags_updater
{
	function __construct($a, $b)
	{
		parent::__construct($a, $b);
		$this->src = 'live-reporting';
	}

	public function update($id, $type, $tags, $activeSince = NULL)
	{
		$srcId = $id . '-' . $type;
		$this->updateTags($srcId, $tags, $activeSince);
	}
}
