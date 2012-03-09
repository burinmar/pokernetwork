<?php

class column extends moon_com 
{
	function main($vars) 
	{
		$tpl  = $this->load_template();
		$page = moon::page();
		$tplArgv = array(
			'banner' => '',
		);
		// $navi = moon::shared('sitemap');
		// $breadcrumb = $navi->breadcrumb();
		// // $pID = $navi->on();
		// $isIndexPage = ($page->uri_segments(1) === '');

		// banneris
		// if (!$isIndexPage) {
		// 	// rooms sarase eina i gala
		// 	$pos = !empty($breadcrumb[0]['page_id']) && $breadcrumb[0]['page_id'] == 'rooms' 
		// 		? '2' 
		// 		: '';
		// 	$tplArgv['banner' . $pos] = $tpl->parse('banner');
		// }
		return $tpl->parse('main',$tplArgv);
	}
}
