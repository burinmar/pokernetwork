<?php
class dictionary extends moon_com 
{
	function events($event, $par)
	{
		if (isset($par[0]) && $par[0]) {
			$term = urldecode($par[0]);
			if (!isset($par[1])) { // old format uri
				$term = preg_replace('~-[^-]+-~', '', $term);
				$term = str_replace(' ', '-', trim($term));
			}
			$this->set_var('term', $term);
		}

		switch($event) {
			case 'ajax-suggest': 
				$this->forget();
				if(!(isset($_GET['s']) || $_GET['s']=trim($_GET['s']))) {
					moon_close();
					exit;
				}
				$suggestions = $this->findSuggestion($_GET['s']);
				$items = '';
				foreach($suggestions as $k=>$v) {
					$uri = $this->linkas('#',$v['uri']);
					$items .= '<li><a class="item" href="'.$uri.'">'.$v['name'].'</a></li>';
				}
				echo '<ul class="suggestion-list">'.$items.'</ul>';
				moon_close(); 
				exit;
			default:
				break;
		}

		$this->use_page('Dictionary');
	}

	function properties()
	{
		return array(
			'term' => ''
		);
	}

	function main($vars)
	{
		$page = moon::page();
		$tpl  = $this->load_template();
		$page->js('/js/ajaxSuggestions.js');
		$page->meta('robots', 'index,follow');

		$sitemap  = moon :: shared('sitemap');
		$pageInfo = $sitemap->getPage();
		$pageInfo['ajax_uri'] = explode('/', $pageInfo['uri']);
		foreach ($pageInfo['ajax_uri'] as $k=>$v)
			$pageInfo['ajax_uri'][$k] = urlencode($v);

		$main = array();
		$main['title'] = $pageInfo['meta_title'];
		$main['uri'] = $pageInfo['uri'];
		$main['ajaxuri'] = implode('/', $pageInfo['ajax_uri']);

		if ($vars['term']) {
			$term = $this->getItemByUri($vars['term']);
			if ($term) {
				$main['title'] = $term['name'];
				$main['info'] = $tpl->parse('term_info', array(
					'description' => ($term['description_html'])?$term['description_html']:'',
				));

				$page->title($term['name']. ' | ' .$sitemap->getTitle());
				$sitemap->breadcrumb(array(''=>$term['name']));
			} else {
				//not found
				$main['info'] = 'not found';
			}
		} else {
			$main['info'] = $pageInfo['content_html'];
		}
		
		$main['abc'] = '';
		$main['boxItems'] = '';

		$termsArr   = $this->getAllTermsList();	//crazy isn't it O_o
		$currLetter = strtoupper(substr($termsArr[0]['name'],0,1));
		$tItems     = '';
		while (1) {
			if (NULL === ($term = array_shift($termsArr)) 
			  || $currLetter != strtoupper(substr($term['name'],0,1))) 
			{
				$main['abc'] .= $tpl->parse('letter', array('letter'=> strtoupper($currLetter)));
				$main['boxItems'] .= $tpl->parse('term_box', array(
					'letter' => $currLetter,
					'items' => $tItems
				));

				$tItems = '';
				$currLetter = strtoupper(substr($term['name'], 0, 1));

				if ($term === NULL)
					break;
			}
			$tItems .= $tpl->parse('term_item', array(
				'title' => $term['name'],
				'goTerm'=> $this->linkas('#').$term['uri'].'.htm'
			));
		}

		return $tpl->parse('main', $main);
	}

	private function getItemByUri($uri)
	{
		$sql = 'SELECT name, description_html
			FROM ' . $this->table('Dictionary') . '
			WHERE uri = "' . $this->db->escape($uri) . '"';
		$result = $this->db->single_query_assoc($sql);
		return !empty($result)
			? $result 
			: FALSE;
	}

	private function getAllTermsList() 
	{
		$sql = 'SELECT name, uri
			FROM ' . $this->table('Dictionary') . '
			ORDER BY name ASC';
		return $this->db->array_query_assoc($sql);
	}

	private function findSuggestion($s)
	{
		$sql = 'SELECT name, uri
			FROM ' . $this->table('Dictionary') . '
			WHERE name LIKE ("%'. $this->db->escape($s).'%") ORDER BY name ASC LIMIT 0,12';
		return $this->db->array_query_assoc($sql);
	}
}