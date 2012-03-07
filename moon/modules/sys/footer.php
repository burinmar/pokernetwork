<?php
class footer extends moon_com 
{
	function main($vars) 
	{
		$page = moon::page();
		$navi = moon :: shared('sitemap');
		$tpl  = $this->load_template();
		$mainMenu = $page->get_local('sys.footer:menu');
		$tplArgv = array(
			'fat_sections' => '',
			'thin_sections'=> '',
			'url_sitemap' => $navi->getLink('sitemap'),
			'url_privacy_policy' => $navi->getLink('privacy'),
			'url_disclaimer' => $navi->getLink('disclaimer'),
			'url_career' => $navi->getLink('career'),
		);

		foreach ($mainMenu as $item) {
			if (0 == count($item['children'])) {
				$tplArgv['thin_sections'] .= $tpl->parse('thin_sections', array(
					'title' => htmlspecialchars($item['title']),
					'url' => $item['url']
				));
			} else {
				$subsestions = '';
				foreach ($item['children'] as $child) {
					$subsestions .= $tpl->parse('sub_sections', array(
						'title' => htmlspecialchars($child['title']),
						'url' => $child['url']
					));
				}
				$tplArgv['fat_sections'] .= $tpl->parse('fat_sections', array(
					'title' => htmlspecialchars($item['title']),
					'url' => $item['url'],
					'sub_sections' => $subsestions
				));
			}
		}

		return $tpl->parse('main', $tplArgv);
	}
}
