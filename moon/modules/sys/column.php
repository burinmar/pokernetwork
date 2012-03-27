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

		$navi = moon::shared('sitemap');
		$breadcrumb = $navi->breadcrumb();
		$pID = isset ($breadcrumb[0]['page_id']) ? $breadcrumb[0]['page_id'] : 0;
		$active = isset ($breadcrumb[1]['id']) ? $breadcrumb[1]['id'] : 0;
		if ('rules' == $pID) {
			$tplArgv['menu'] = '';
			$menu = $navi->listLevel(2);
			foreach ($menu as $id => $d) {
				$d['classActive'] = $active == $id ? ' class="active"' : '';
				$d['title'] = htmlspecialchars($d['title']);
				$tplArgv['menu'] .= $tpl->parse('menu', $d);
			}
		}

		$oRooms = $this->object('reviews.rooms_box');
		$tplArgv['rooms-box'] = $oRooms->main(array());

		return $tpl->parse('main',$tplArgv);
	}
}
