<?php

/* naudojamas norint iskviesti is navigation.txt pagal id */
class page extends moon_com {

function events($ev, $par)
{
	switch ($ev) {
		case 'custom-page' :
			$page = &moon::page();
			$nav = & moon :: shared('sitemap');

			if (!isset($par[0]) AND intval($par[0]) != 0) {
				// no parameters. show default page
			}
            $sitemap = & moon :: shared('sitemap');
			$pageData = $sitemap->getPage($par[0]);
			if (empty($pageData)) {
				$page->page404();
			}
			$this->set_var('pageData', $pageData);
			
			$xmlPage = ($pageData['xml'] != '') ? $pageData['xml'] : 'Main';
			$activeMenu = ($pageData['page_id']) ? $pageData['page_id'] : 'home';
			$this->use_page($xmlPage);
			
			break;
		default :
			//naudoja html "{link:}"
			$id = empty ($par['params']) ? '' : $par['params'];
			if (!$id) {
				return '';
			}
			$nav = & moon :: shared('sitemap');
			$url = $nav->getLink($id);
			if ($url === FALSE) {
				$url = '/page/' . $id . '.htm';
			}
			return $url;
	}
}
function properties()
{
	$vars = array();
	$vars['pageData'] = array();
	return $vars;
}
function main($vars)
{
	if (empty($vars['pageData'])) {
		return '';
	}

	$nav = & moon :: shared('sitemap');
	$pageData = $vars['pageData'];


	$page = &moon::page();
	$tpl = &$this->load_template();

	$main = array();
	$breadcrumb = $nav->breadcrumb();
	if (count($breadcrumb)>1) {
		$main['title'] = htmlspecialchars($pageData['title']);
	}
	$main['content_html'] = $pageData['content_html'];
	$page->css('/css/article.css');
	if ($pageData['html']) {
		$tplName = 'main_html';
	}
	else {
		$tplName = 'main';
	}
	return $tpl->parse($tplName, $main);
}


}

?>