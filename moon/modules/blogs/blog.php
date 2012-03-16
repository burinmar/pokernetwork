<?php
class blog extends moon_com {
	
	function events($event, $par)
	{
		$p = moon::page();
		$segments = $p->requested_event('segments');

		switch ($event) {

			case 'manage':
				
				$u = moon::user();
				if ($uID = $u->get_user_id()) {
					$action = !empty($segments[1]) ? $segments[1] : '';
					$param = !empty($segments[2]) ? $segments[2] : '';

					if (is_numeric($param)) {
						// check if user is post owner
						$sql = 'SELECT user_id
								FROM ' . $this->table('Posts') . '
								WHERE id = ' . intval($param);
						$m = $this->db->single_query($sql);
						$postUserId = (count($m) ? $m[0] : 0);
						if ($uID != $postUserId) {
							$p->page404();
						}
					}
					
					$p->call_event('blogs.posts_edit#'.$action, $param);
				} else $this->redirect('#');
				break;

			case 'user':

				if (!empty($segments[1])) {

					$nick = $segments[1];
					$vb = $this->object('users.vb');
					$uID = $vb->getUserIdByNick($nick);
					if ($uID) {
						$this->set('user_id', $uID);
						$this->set('nick', $nick);
						$this->set('isBlogOwner', $uID == moon::user()->get_user_id());

						moon::shared('sitemap')->breadcrumb(array($p->uri_segments('') => $nick));

						$p->call_event('blogs.posts#');
					} else $this->redirect('#');

				} else $this->redirect('#');

				break;

			case 'most-viewed':

				$p->call_event('blogs.posts#most-viewed');

				break;
			default:
				$p->call_event('blogs.posts#'.$event, $par);
		}

	}

	private $blogInfo = array(
		'user_id' => 0,
		'nick' => '',
		
		'isBlogOwner' => false
	);
	
	function get($key = '')
	{
		if (isset($this->blogInfo[$key])) {
			return $this->blogInfo[$key];
		}
		return false;
	}
	
	function set($key = '', $value = '')
	{
		if (isset($this->blogInfo[$key])) {
			$this->blogInfo[$key] = $value;
			return true;
		}
		return false;
	}
	
	function isBlogOwner()
	{
		return $this->blogInfo['isBlogOwner'];
	}

	function userHasBlog()
	{
		return moon::user()->get_user_id();
	}
	
	function htmlRightColumn()
	{
		$p = &moon::page();
		$u = &moon::user();
		$tpl = &$this->load_template();
		$loc = &moon::locale();
		
		$m = array(
			'isBlogOwner' => $this->isBlogOwner(),
			'userHasBlog' => $this->userHasBlog(),
			'url.newPost' => $this->linkas('posts_edit#edit'),
			
			'archive' => '',
			'tags' => ''
		);
		
		$m['archive'] = $this->htmlArchiveBox($p->get_local('year'), $p->get_local('month'));

		/*
		// TAGS
		$userPosts = $this->db->array_query_assoc('
			SELECT tags, created_on
			FROM ' . $this->table('Posts') . '
			WHERE is_hidden = 0'
		);
		
		$tagsWeights = array();
		foreach ($userPosts as $r) {
			if($r['tags'] == '') continue;
			$tags = explode(',', $r['tags']);
			foreach ($tags as $tag) {
				$tag = ucfirst(trim($tag));
				if (isset($tagsWeights[$tag])) {
					$tagsWeights[$tag]++;
				} else {
					$tagsWeights[$tag] = 1;
				}
			}
		}
		if (!empty($tagsWeights)) {
			$minWeight = min(array_values($tagsWeights));
			$maxWeight = max(array_values($tagsWeights));
			$minFontSize = 14;
			$maxFontSize = 30;
			$spread = $maxWeight - $minWeight;
		        if ($spread == 0) $spread = 1;
			$step = ($maxFontSize - $minFontSize) / ($spread);
			
			$t = array();
			foreach ($tagsWeights as $tag=>$weight) {
				$size = round($minFontSize + (($weight - $minWeight) * $step));
				
				$t1 = array();
				$t1['name'] = htmlspecialchars($tag);
				$t1['url.tag'] =  $this->linkas('posts#tag/' . urlencode($tag));
				$t1['fontSize'] = $size;
				$t[] .= trim($tpl->parse('tagItems', $t1));
			}
			//shuffle($t);
			$tags = implode(' ', $t);
			
			$m['tags'] = $tpl->parse('tags', array('tagItems'=>$tags));
		}
		*/
		return $tpl->parse('rightColumn', $m);
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
		return $tpl->parse('archive', array('years:items' => $yearsList, 'noBlogControls' => !$this->userHasBlog()));
	}

	function getArchiveData()
	{
		$now = floor(moon::locale()->now() / 300) * 300;
		$sql = '
			SELECT FROM_UNIXTIME(a.created_on, \'%Y-%M\') as short_date, FROM_UNIXTIME(a.created_on, \'%Y\') as year, FROM_UNIXTIME(a.created_on, \'%m\') as month, count(*) as cnt
			FROM ' . $this->table('Posts') . ' a 
			WHERE 
				a.created_on < ' . $now . ' AND
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

}

?>