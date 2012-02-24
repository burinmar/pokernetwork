<?php
class spotlight extends moon_com {
	
	function properties()
	{
		return array('view' => '');
	}
	
	function main($vars)
	{
		switch ($vars['view']) {
			case 'home':
				$vars['view'] = 'home';
				break;
			default:
				return '';
				break;
		}
		return $this->htmlSpotlight($vars);
	}
	
	function htmlSpotlight($vars)
	{
		$tpl = $this->load_template();
		$geoId = geo_my_id();

		$tplSuffix = '';
		$place = '';
		switch ($vars['view']) {
			case 'home':
				$tplSuffix = ':home';
				$place = 'home';
				break;
			default:
				return '';
		}
		
		$m = array(
			'items:spotlight' . $tplSuffix => '',
			//'items:pager' . $tplSuffix => '',
			'itemsTotal' => 0
		);
		
		$selected = array();
		$items = $this->getList($place);
		$output = '';
		if (!empty($items)) {
			$locale = &moon::locale();
			$nowDay = floor($locale->now() / 86400) * 86400;
			$imgSrc = $this->get_var('imgSrcSpotlight');
			$i = 1;
			foreach ($items as $item) {
				$current = $i === 1;
				
				// filter by geo id
				if (!is_null($item['geo_target']) && $item['geo_target'] > 0 && !($item['geo_target'] & (1 << $geoId))) continue;
				
				// filter by date intervals
				if ($item['date_intervals']) {
					$skip = TRUE;
					$ranges = explode(';', $item['date_intervals']);
					foreach ($ranges as $range) {
						list($from, $to) = explode(',', $range);
						if ($nowDay >= $from && $nowDay <= $to) {
							$skip = FALSE;
							break;
						}
					}
					// skip the item
					if ($skip) continue;
				}
				
				$s = array(
					'imgSrc' => $imgSrc . ($vars['view'] == 'playpoker' ? '_' : '') . $item['img'],
					'imgAlt' => htmlspecialchars($item['img_alt']),
					'url.item' => htmlspecialchars($item['uri']),
					'title' => htmlspecialchars($item['title']),
					'notCurrent' => !$current,
					'nr' => $i
				);
				/*$p = array(
					'current' => $current,
					'nr' => $i,
					'title' => htmlspecialchars($item['title'])
				);*/
				$m['items:spotlight' . $tplSuffix] .= $tpl->parse('items:spotlight' . $tplSuffix, $s);
				//$m['items:pager' . $tplSuffix] .= $tpl->parse('items:pager' . $tplSuffix, $p);
				$i++;$m['itemsTotal']++;
			}
		}
		
		if ($m['items:spotlight' . $tplSuffix]) {
			$page = moon::page();
			$page->js('/js/pnslider.js');
		}
		
		$output = $tpl->parse('view' . $tplSuffix, $m);
		return $output;
	}
	
	function getList($place = '')
	{
		if (!isset($this->tmpList)) {
			$sql = 'SELECT id,title,uri,img,img_alt,date_intervals,geo_target
				FROM ' . $this->table('Spotlight') . '
				WHERE is_hidden = 0 AND place = \'' . $place . '\'
				ORDER BY sort_order ASC';
			return $this->db->array_query_assoc($sql);
		}
		return $this->tmpList;
	}

}
?>
