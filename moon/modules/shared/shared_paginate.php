<?php
include_class('moon_paginate');

class shared_paginate extends moon_paginate{

var $tpl;//sablonu klase

function shared_paginate($path)
{
  $moon=&moon::engine();
  $this->tpl=&$moon->load_template($path.'shared_paginate.htm'/*,$moon->ini('dir.multilang').'shared.txt'*/);
  $this->init();
}

function init()
{
	$this->moon_paginate();//construktorius
    $this->skinID = "default";
}

//***** PAGRINDINES FUNKCIJOS *****

function show_nav($skin='') {
    $m=array();
	$psl=$this->get_info();
	if ($psl['countPages']<2) {
		return '';
	}

    $cfg = &moon::cfg();
    if($cfg->has('adm')) {
    	$tpl_block_prefix = '_adm';
	} else {
		$tpl_block_prefix = '';
		return $this->paginatePokernews();
		/*switch ($skin) {
		case 'strategy':
		case 'forum':
        	$tpl_block_prefix = '_strategy';
			break;
		case 'facebook':
        	$tpl_block_prefix = '_facebook';
			break;
		default:
        	$tpl_block_prefix = '';
			return $this->paginatePokernews();
		}*/
	}
	//$tpl_block_prefix = '_adm';
    $this->skinID = 'default' . $tpl_block_prefix;

	$pslPerPage = $psl['curPage']>99 ? 8 : 11;
	$m['puslapiai'] = $psl['countPages']>1 ? $this->paginate($pslPerPage) : '';

	$tmp = array(
		'from' => $psl['from'],
		'to' => $psl['to'],
		'atall' => $this->countItems
	);
	$t = $this->tpl->parse_array('default' . $tpl_block_prefix, $tmp);
	$m['irasai'] = $t['nuo_iki'];

	$res = $this->tpl->parse('nav_block'.$tpl_block_prefix,$m);
	return $res;
}


function paginatePokernews() //generuoja sarasa
{
	if ($this->countItems<1) return '';//irasu nera
	$this->skinID = 'default';

	$inf=$this->get_info();
	$po = $pslPerPage = $inf['curPage']>99 ? 8 : 11;

	$halfPo=ceil($po/2)-1;
    $start=max($inf['curPage']-$halfPo,1);
	$end=min($start+$po-1, $inf['countPages']);
	$start=max($end-$po+1,1);

    //$start=floor(($inf['curPage']-1)/$po)*$po+1;
	//$end=min($start+$po-1, $inf['countPages']);
		//pradedam konstruoti
    $t=$this->_get_template($this->url);
	$t1=$this->_get_template($this->urlFirst);
	$res='';
	for ($i=$start;$i<=$end;$i++){//klijuojam puslapius
		$a = $i==1 ? $t1['active'] : $t['active'];
		$ct=($i==$inf['curPage']) ? $t['inactive']:$a;//jei puslapis pazymetas, jis negyvas
		$res.=str_replace( array('{pgv}','{pg}'), $i,$ct) .' ';
	}
    if ($end<$this->countPages){
        if ($this->countPages==$end+1)
			$res.=str_replace(array('{pgv}','{pg}'),$end+1,$t['active']);
		else {
			//$res.=str_replace(array('{pgv}','{pg}'),array('...',$end+1),$t['active']);
			$res.=' &hellip; ' . str_replace( array('{pgv}','{pg}'), $this->countPages,$t['active']);
		}
	}
    if ($start>1){
		$r1=str_replace( array('{pgv}','{pg}'), 1,$t1['active']);
		if ($start>2) {
			//$r1.=str_replace( array('{pgv}','{pg}'), array('...',$start-1),$t['active']);
			$r1 .=  ' &hellip; ';
		}
        $res=$r1.$res;
	}

	$m = array();
	//dabar pridedam start ir end
    if ($inf['curPage']>1){
		/*$r1=str_replace('{pg}',1,$t1['active']);
		$res=str_replace('{pgv}',$t['left'],$r1).$res;*/
		$url = $inf['curPage'] == 2 ? $this->urlFirst : $this->url;
		$m['goPrev'] = str_replace('{pg}',$inf['curPage']-1,$url);
	}
	if ($inf['curPage']<$this->countPages){
		/*$r1=str_replace('{pg}',$this->countPages,$t['active']);
		$res.=str_replace('{pgv}',$t['right'],$r1); */
        $m['goNext'] = str_replace('{pg}',$inf['curPage']+1,$this->url);
	}

	$m['puslapiai']= $res;//$this->_add_navigation($res,$inf['curPage'],$inf['curPage']);
	$res = $this->tpl->parse('nav_block',$m);
	return $res;
}



function sql_limit() {
	$psl=$this->get_info();
	return $psl['sqllimit'];
}

}
?>