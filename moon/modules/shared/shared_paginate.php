<?php
include_class('moon_paginate');


class shared_paginate extends moon_paginate {
	var $tpl;


	//sablonu klase
	function shared_paginate($path) {
		$moon = & moon :: engine();
		$this->tpl = & $moon->load_template($path . 'shared_paginate.htm', $moon->ini('dir.multilang') . 'shared.txt');
		$this->init();
	}


	function init() {
		$this->moon_paginate();
		//construktorius
		$this->skinID = "default";
	}


	//***** PAGRINDINES FUNKCIJOS *****
	function show_nav($skin = '') {
		$m = array();
		$psl = $this->get_info();
		if ($psl['countPages'] < 2) {
			return '';
		}
		$cfg = & moon :: cfg();
		if ($cfg->has('adm')) {
			$tpl_block_prefix = '_adm';
		}
		else {
			return $this->paginatePokerWorks();
		}
		//$tpl_block_prefix = '_adm';
		$this->skinID = 'default' . $tpl_block_prefix;
		$pslPerPage = $psl['curPage'] > 99 ? 8 : 11;
		$m['puslapiai'] = $psl['countPages'] > 1 ? $this->paginate($pslPerPage) : '';
		$tmp = array('from' => $psl['from'], 'to' => $psl['to'], 'atall' => $this->countItems);
		$t = $this->tpl->parse_array('default' . $tpl_block_prefix, $tmp);
		$m['irasai'] = $t['nuo_iki'];
		$res = $this->tpl->parse('nav_block' . $tpl_block_prefix, $m);
		return $res;
	}


	function paginatePokerWorks() {
		if ($this->countItems < 1)
			return '';
		//irasu nera
		$this->skinID = 'default';
		$inf = $this->get_info();
		$po = $inf['curPage'] > 99 ? 8 : 11;
		$halfPo = ceil($po / 2) - 1;
		$start = max($inf['curPage'] - $halfPo, 1);
		$end = min($start + $po - 1, $inf['countPages']);
		$start = max($end - $po + 1, 1);
		//pradedam konstruoti
		$t = $this->_get_template($this->url);
		$t1 = $this->_get_template($this->urlFirst);
		$res = '';
		for ($i = $start; $i <= $end; $i++) {
			//klijuojam puslapius
			$a = $i == 1 ? $t1['active'] : $t['active'];
			$ct = ($i == $inf['curPage']) ? $t['inactive'] : $a;
			//jei puslapis pazymetas, jis negyvas
			$res .= str_replace(array('{pgv}', '{pg}'), $i, $ct) . '';
		}
		if ($end < $this->countPages) {
			if ($this->countPages == $end + 1) {
				$res .= str_replace(array('{pgv}', '{pg}'), $end + 1, $t['active']);
			}
			else {
				$res .= ' &hellip; ' . str_replace(array('{pgv}', '{pg}'), $this->countPages, $t['active']);
			}
		}
		if ($start > 1) {
			$r1 = str_replace(array('{pgv}', '{pg}'), 1, $t1['active']);
			if ($start > 2) {
				$r1 .= ' &hellip; ';
			}
			$res = $r1 . $res;
		}
		$m = array();
		//dabar pridedam start ir end
		if ($inf['curPage'] > 1) {
			$url = $inf['curPage'] == 2 ? $this->urlFirst : $this->url;
			$m['goPrev'] = str_replace('{pg}', $inf['curPage'] - 1, $url);
		}
		if ($inf['curPage'] < $this->countPages) {
			$m['goNext'] = str_replace('{pg}', $inf['curPage'] + 1, $this->url);
		}
		$m['puslapiai'] = $res;
		$res = $this->tpl->parse('nav_block', $m);
		return $res;
	}


	function sql_limit() {
		$psl = $this->get_info();
		return $psl['sqllimit'];
	}


}

?>