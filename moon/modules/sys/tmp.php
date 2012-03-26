<?php
class tmp extends moon_com {

	function main($vars) {
		if(!is_dev()) {
			return '';
		}
		
		$page = & moon :: page();
		$page->css('/css/hof.css');
		//$page->set_local('nobanners', 1);
		

		$navi = & moon :: shared('sitemap');
		$navi->on('none');

		$tpl = & $this->load_template();
		
		$toolbar = '';
		if (is_object( $rtf = $this->object('MoonShared.rtf') )) {
			$rtf->setInstance($this->get_var('rtf'));
			$toolbar = $rtf->toolbar('_commentEditor', '');
		}
		
		$tplBlock = $tpl->has_part($vars['view']) ? $vars['view'] : 'main';
		$res = $tpl->parse($tplBlock, array(
			'toolbar' => $toolbar
		));
		return $res;
	}
	
	function events($event) {
		$this->use_page('tmp');
		$this->set_var('view', $event);
	}

	function properties() {
		return array('view' => '');
	}

	//***************************************
	//           --- DB AND OTHER ---
	//***************************************

}

?>