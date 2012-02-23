<?php
class homepage_news extends moon_com {

	function main($vars)
	{
		$page = moon::page();
		$tpl = $this->load_template();

		$locale = &moon::locale();
		$now = floor($locale->now() / 300) * 300;

		$shared = 'SELECT a.id,a.title,a.article_type,a.uri,a.authors,a.img,a.img_alt,a.published,a.summary,a.comm_count,a.category_id,c.is_au as isAu
			FROM ' . $this->table('Articles') . ' a USE INDEX (i_articles)
			LEFT JOIN ' . $this->table('Categories') . ' c ON a.category_id=c.id 
			WHERE	a.published <= ' . $now . ' AND
					a.is_hidden = 0';
		$sql = '
			(' . $shared . ' AND a.homepage_promo=1 ORDER BY a.published DESC LIMIT 4)
			UNION
			(' . $shared . ' ORDER BY a.published DESC LIMIT 12)
			LIMIT 12';

		$result = $this->db->array_query_assoc($sql);

		$sharedTxt = & moon::shared('text');
		$sharedTxt->agoMaxMin = 60*24*30; // 30 days;

		$oShared = $this->object('shared');
		$oShared->articleType($this->get_var('typeNews'));

		$page->js('/js/pnslider.js');

		$m = array(
			'news:slider:items' => '',
			'news:list:left:items' => '',
			'news:list:right:items' => '',
			'url.news' => $this->linkas('news#')
		);

		foreach ($result as $k=>$r) {

			$item = array(
				'url.article' => $oShared->getArticleUri($r['id'], $r['uri'], $r['published']),
				'title' => htmlspecialchars($r['title']),
				'comm_count' => $r['comm_count'],
				'isAu' => $r['isAu']
			);
			$item['url.comments'] = $r['comm_count'] ? $item['url.article'].'#cl' : '';

			if ($k < 4) {
				// slider
				$item['imgSrc'] = $oShared->getImageSrc($r['img']);
				$item['imgAlt'] = htmlspecialchars($r['img_alt']);
				$item['ago'] = $sharedTxt->ago($r['published']);
				$item['date'] = $locale->datef($r['published'], 'Article');
				$item['url.date'] = $this->linkas('news#' . date('Y/m', $r['published']));
				$item['notCurrent'] = $k != 0;
				$m['news:slider:items'] .= $tpl->parse('news:slider:items', $item);
			} elseif ($k < 8) {
				if ($k === 4) {
					$item['imgSrc'] = $oShared->getImageSrc($r['img'], 'mid_');
					$item['imgAlt'] = htmlspecialchars($r['img_alt']);
				}
				$item['summary'] = htmlspecialchars($r['summary']);
				$m['news:list:left:items'] .= $tpl->parse('news:list:left:items', $item);
			} else {
				if ($k === 11) {
					$item['imgSrc'] = $oShared->getImageSrc($r['img'], 'mid_');
					$item['imgAlt'] = htmlspecialchars($r['img_alt']);
				}
				$item['summary'] = htmlspecialchars($r['summary']);
				$m['news:list:right:items'] .= $tpl->parse('news:list:right:items', $item);
			}

		}

		return $tpl->parse('main', $m);
	}

}
?>