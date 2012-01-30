<?php

class shared_imgtool {
	var $tpl;


	function shared_imgtool($path)
	{
		$moon = & moon :: engine();
		$this->tpl = & $moon->load_template(
			$path . 'shared_imgtool.htm'
			/*,$moon->ini('dir.multilang').'shared/shared_paginate.txt'*/
		);
		$this->init();
	}


	function init() {}


	function show($vars = "")
	{
		$id = isset ($vars['id']) ? intval($vars['id']) : 0;
		$src = $vars['src'];
		$err = 0;
		if (empty ($src)) {
			if ($id) {
				$err = 2;
			}
			$this->close($err);
		}
		$page = & moon :: page();
		$m = array(
			'home_url' => $page->home_url(),
			'!action' => $page->uri_segments(0),
			'refresh' => $page->refresh_field(),
			'event' => $page->requested_event(),
			'id' => $id,
			'imgUrl' => $src
		);
		list($m['min_width'], $m['min_height']) = explode('x', $vars['minWH']);
		$m['fixed_props'] = $vars['fixedProportions'] ? 'true' : 'false';
		$res = $this->tpl->parse('main', $m);
		moon_close();
		die($res);
	}


	function close($err = 0)
	{
		moon_close();
		die($this->tpl->parse('close', array('error' => $err)));
	}


}

?>