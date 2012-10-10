<?php
/**
 * @package livereporting
 */
/**
 * @package livereporting
 */
class reporting_news extends moon_com 
{
	function main()
	{
		$tpl = $this->load_template();
		moon::page()->js('/js/modules/lrslider.js');

		$mainArgv = array(
			'list.entries' => ''
		);
		$imgDir = $this->get_dir('web:SliderImgs');
		$imgDef = $this->get_dir('web:SliderDefimg');
		$entries = $this->getEntries();
		if (0 == count($entries)) {
			return '';
		}
		foreach ($entries as $nr => $entry) {
			$mainArgv['list.entries'] .= $tpl->parse('entries.' . $entry['type'], array(
				'url' => htmlspecialchars($entry['url']),
				'title' => htmlspecialchars($entry['title']),
				'image' => !empty($entry['image'])
					? $imgDir . $entry['image']
					: $imgDef,
				'initiallyVisible' => $nr < 4
			)) . "\n";
		}
		return $tpl->parse('main', $mainArgv);
	}
	
	private function getEntries()
	{
		if ($this->sliderForcedHidden()) {
			return array();
		}
		return $this->db->array_query_assoc('
			SELECT type, url, title, image FROM ' . $this->table('Slider') . '
			WHERE is_hidden=0
			ORDER BY sort_ord DESC
			LIMIT 60
		');
	}

	private function sliderForcedHidden()
	{
		$hidden = $this->db->single_query_assoc('
			SELECT 1 FROM ' . $this->table('Constants') . '
			WHERE name="repo_slider_hide" AND value="1"
		');
		return !empty($hidden);
	}	
}
