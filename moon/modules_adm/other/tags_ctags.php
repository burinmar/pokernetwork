<?php

class tags_ctags extends moon_com
{
	function events($event)
	{
		if ($event == 'update_all') {
			ob_start();
			$this->batchUpdateAll();
			$page = moon :: page();
			$page->set_local('cron', ob_get_contents());
			if (isset($_GET['debug'])) {
				header('content-type: text/plain; charse=utf-8');
				moon_close(); 
				exit;
			}
			return;
		}
	}

	function main(){}

	public function batchUpdateAll()
	{
		set_time_limit(3600);
		if (method_exists($this->db, 'operateOnMaster')) {
			$this->db->operateOnMaster();
		}

		foreach (array(
			array('livereporting', 'live-reporting'),
			// array('news', 'news'),
			// array('interviews', 'interviews'),
			// array('strategy', 'strategy'),
			// array('sps', 'spanish-poker-show'),
			// array('videos', 'videos'),
		) as $src) {
			$t1 = microtime(true);
			$obj = 'tags_core_batch_updater_' . $src[0];
			$obj = new $obj('other', $obj); // load object in context of $src
			$obj->update($src[1]);
			$t2 = microtime(true);
			echo $src[1] . ' ' . round($t2 - $t1, 4) . "s\n";
		}
	}

	const news = 'news';
	const interviews = 'interviews';
	const strategy = 'strategy';
	const videos = 'videos';
	const sps = 'spanish-poker-show';
	public function update($srcId, $src, $tags, $activeSince = NULL)
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
			WHERE src_type="' . $this->db->escape($src) . '" AND src_id="' . $this->db->escape($srcId) . '"' . 
			(0 != count($tags)
				? 'AND tag_id NOT IN(' . implode(',', $tags) . ')'
				: '')
		);
		foreach ($tags as $tag) {
			$this->dbInsert(array(
				'src_type' => $src,
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
		$r = &$this->db->query($sql);
		return $r
			? $this->db->insert_id()
			: FALSE;
	}	

}

/**
 * Batch updates tags
 * @package tags
 */
abstract class tags_core_batch_updater extends moon_com
{
	/**
	 * Entires count since requested time.
	 * Should include all (visible, hidden and deleted) elements.
	 * @param int $sinceTs Timestamp
	 * @return int
	 */
	abstract protected function getEntriesCnt($sinceTs);

	/**
	 * Mysql result resource for requested entries.
	 * Ideally, should include all requered data for getEntryTags to process.
	 * Should include all (visible, hidden and deleted) elements.
	 * @param int $sinceTs Timestamp
	 * @param string $limit Sql limit string
	 * @return resource
	 */
	abstract protected function getEntriesResource($sinceTs, $limit);

	/**
	 * Get entry tags. If entry is somehow hidden (except timestamp), it should return empty set.
	 * If request is invalid, and tags update should be skipped, NULL should be returned.
	 * @param array $entry Entry, as fetched from getEntriesResource
	 * @return mixed array or null
	 */
	abstract protected function getEntryTags($entry);

	/**
	 * Overridable method to extract entry id
	 * @param array $entry 
	 * @return string
	 */
	abstract protected function extractId($entry);

	/**
	 * Overridable method to extract entry `visible since` timestamp
	 * @param array $entry 
	 * @return int
	 */
	abstract protected function extractActiveSinceTs($entry);

	/**
	 * Command to update tags. Overridable
	 * @param string $src livereporting|?
	 */
	public function update($src)
	{
		$sinceTs = intval($this->getLatestTagTs($src));

		$cnt = $this->getEntriesCnt($sinceTs);
		for ($i = 0; $i < ceil($cnt / 1000); $i++) {
			$entries = $this->getEntriesResource(
				$sinceTs,
				'LIMIT ' . ($i * 1000) . ',1000'
			);
			while ($entry = $this->db->fetch_row_assoc($entries)) {
				$this->processEntry($entry, $src);
			}
		}
	}

	protected function getLatestTagTs($src)
	{
		$sinceTs = $this->db->single_query_assoc('
			SELECT UNIX_TIMESTAMP(MAX(updated_on)) ts FROM ctags_usages
			WHERE src_type="' . $this->db->escape($src) . '"
		');
		$sinceTs = intval($sinceTs['ts']);
		$sinceTs = max(0, $sinceTs - 3600);
		//$sinceTs = time() - 3600*24*30*2;
		return $sinceTs;
	}

	protected function processEntry($entry, $src)
	{
		if (NULL === ($tags = $this->getEntryTags($entry))) {
			return;
		}

		foreach ($tags as $key => $title) {
			$tag = make_uri(trim($title));
			$title = trim($title);
			if (empty($tag)) {
				unset($tags[$key]);
				continue;
			}
			$tags[$key] = $this->getNumericTag($tag, $title);
		}

		$srcId = $this->extractId($entry);
		$this->db->query('
			DELETE FROM `ctags_usages`
			WHERE src_type="' . $this->db->escape($src) . '" AND src_id="' . $this->db->escape($srcId) . '"' . 
			(0 != count($tags)
				? 'AND tag_id NOT IN(' . implode(',', $tags) . ')'
				: '')
		);

		foreach ($tags as $tag) {
			$this->dbInsert(array(
				'src_type' => $src,
				'active_since' => array('FROM_UNIXTIME', $this->extractActiveSinceTs($entry)),
				'tag_id' => $tag,
				'src_id' => $srcId,
			), 'ctags_usages', 'IGNORE');
		}
	}

	// shouldn't be too much
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
		$r = &$this->db->query($sql);
		return $r
			? $this->db->insert_id()
			: FALSE;
	}	
}

class tags_core_batch_updater_livereporting extends tags_core_batch_updater
{
	protected function getEntriesCnt($sinceTs)
	{
		$cnt = $this->db->single_query_assoc('
			SELECT COUNT(*) cnt FROM `reporting_ng_log`
			WHERE (created_on>' . $sinceTs . ' OR updated_on>' . $sinceTs . ')
			  AND type IN ("post", "chips")
		');
		return $cnt['cnt'];
	}

	protected function getEntriesResource($sinceTs, $limit)
	{
		return $this->db->query('
			SELECT id, type, is_hidden, created_on FROM `reporting_ng_log`
			WHERE (created_on>' . $sinceTs . ' OR updated_on>' . $sinceTs . ')
			  AND type IN ("post", "chips")
			ORDER BY id ' . 
			$limit
		);
	}

	protected function getEntryTags($entry)
	{
		if ($entry['is_hidden'] != '0') {
			return array();
		}

		if (!in_array($entry['type'], array('post', 'chips'))) {
			return ;
		}

		$tags_ = $this->db->array_query_assoc('
			SELECT t.tag FROM `reporting_ng_tags` t
			INNER JOIN reporting_ng_days d
				ON d.id=t.day_id
			WHERE t.id=' . intval($entry['id']) . ' AND t.type="' . $entry['type'] . '"
			  AND d.is_live=1
		');
		$tags = array();
		foreach ($tags_ as $tag) {
			$tags[] = $tag['tag'];
		}

		return $tags;
	}

	protected function extractId($entry)
	{
		return $entry['id'] . '-' . $entry['type'];
	}

	protected function extractActiveSinceTs($entry)
	{
		return $entry['created_on'];
	}	
}

class tags_core_batch_updater_articles_abstr extends tags_core_batch_updater
{
	protected $typeId;

	protected function getEntriesCnt($sinceTs)
	{
		$cnt = $this->db->single_query_assoc('
			SELECT COUNT(*) cnt FROM `articles`
			WHERE (created>' . $sinceTs . ' OR updated>' . $sinceTs . ')
			  AND article_type=' . $this->typeId . '
		');
		return $cnt['cnt'];
	}

	protected function getEntriesResource($sinceTs, $limit)
	{
		return $this->db->query('
			SELECT id, is_hidden, is_deleted, published, tags FROM `articles`
			WHERE (created>' . $sinceTs . ' OR updated>' . $sinceTs . ')
			  AND article_type=' . $this->typeId . '
			ORDER BY id ' . 
			$limit
		);
	}

	protected function getEntryTags($entry)
	{
		if ($entry['is_hidden'] != '0' || $entry['is_deleted'] != '0') {
			return array();
		}

		return explode(',', $entry['tags']);
	}

	protected function extractId($entry)
	{
		return $entry['id'];
	}

	protected function extractActiveSinceTs($entry)
	{
		return $entry['published'];
	}
}

class tags_core_batch_updater_news extends tags_core_batch_updater_articles_abstr
{
	public function __construct($a, $b)
	{
		$this->typeId = 1;
		parent::__construct($a, $b);
	}
}

class tags_core_batch_updater_interviews extends tags_core_batch_updater_articles_abstr
{
	public function __construct($a, $b)
	{
		$this->typeId = 6;
		parent::__construct($a, $b);
	}
}

class tags_core_batch_updater_strategy extends tags_core_batch_updater_articles_abstr
{
	public function __construct($a, $b)
	{
		$this->typeId = 2;
		parent::__construct($a, $b);
	}
}

class tags_core_batch_updater_sps extends tags_core_batch_updater_articles_abstr
{
	public function __construct($a, $b)
	{
		$this->typeId = 5;
		parent::__construct($a, $b);
	}
}

class tags_core_batch_updater_videos extends tags_core_batch_updater
{
	public function update($src)
	{
		$sinceTs = intval($this->getLatestTagTs($src));

		$entries = $this->getEntriesResource(
			$sinceTs, ''
		);
		while ($entry = $this->db->fetch_row_assoc($entries)) {
			$this->processEntry($entry, $src);
		}
	}
	
	protected function getEntriesCnt($sinceTs)
	{}

	protected function getEntriesResource($sinceTs, $limit)
	{
		return $this->db->query('
			SELECT id, is_hidden, is_deleted, LEFT(published_date, LENGTH(published_date) - 3) published, tags
			FROM `videos`
			WHERE (last_modified_date>' . $sinceTs . '*1000)
			ORDER BY id'
		);
	}

	protected function getEntryTags($entry)
	{
		if ($entry['is_hidden'] != '0' || $entry['is_deleted'] != '0') {
			return array();
		}

		return explode(',', $entry['tags']);
	}

	protected function extractId($entry)
	{
		return $entry['id'];
	}

	protected function extractActiveSinceTs($entry)
	{
		return $entry['published'];
	}
}

class tags_core_single_updater extends moon_com
{
	
}

