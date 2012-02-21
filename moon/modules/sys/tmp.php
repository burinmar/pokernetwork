<?php
class tmp extends moon_com {

	function main($vars) {
		if(!is_dev()) {
			return '';
		}
		
		$page = & moon :: page();
		$page->css('/css/article.css');
		$page->css('/css/landing.css');
		//$page->set_local('nobanners', 1);
		

		$navi = & moon :: shared('sitemap');
		$navi->on('none');

		$tpl = & $this->load_template();
		
		$toolbar = '';
		if (is_object( $rtf = $this->object('MoonShared.rtf') )) {
			$rtf->setInstance($this->get_var('rtf'));
			$toolbar = $rtf->toolbar('_commentEditor', '');
		}
		
		$res = $tpl->parse('main', array(
			'toolbar' => $toolbar
		));
		return $res;
	}
	
	function events() {
		$this->use_page('tmp');
	}

	//***************************************
	//           --- DB AND OTHER ---
	//***************************************

}

?>