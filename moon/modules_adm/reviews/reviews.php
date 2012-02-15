<?php
class reviews extends moon_com {

var $form, $formFilter;

function onload()
{
	//form of item
	$this->form = &$this->form();
	$this->form->names(	'id','name','alias', 'meta_title', 'meta_description', 'logo','bonus_text','bonus_terms','intro_text','bonus_int','bonus_percent','currency', 'is_hidden', 'review_summary', 'review','editors_rating','ratings');
	$this->form->fill( array('date'=>date('Y-m-d'), 'hide'=>1) );

	//form of filter
	$this->formFilter = & $this->form('f2');
	$this->formFilter->names('hidden','text');

	//main table
	$this->myTable=$this->table('Rooms');
}

function events($event,$par)
{
	switch ($event) {
	case 'edit':
        $id= isset($par[0]) ? intval($par[0]) : 0;
		if ($id) {
			if (count($values=$this->getItem($id))) {
				$this->form->fill($values);
				$this->set_var('view','form');
				break;
			}
			//else $this->set_var('error','404');
		}
		$this->redirect('#');

		break;
    case 'save':
		if ($id=$this->saveItem()) {
            if (isset($_POST['return']) ) $this->redirect('#edit',$id);
			else $this->redirect('#');
        } else $this->set_var('view','form');
		break;

	case 'filter':
		$filter = isset($_POST['filter']) ? $_POST['filter']:'';
		$this->set_var('filter',$filter);
		$this->set_var('psl',1);
        //forget reikia kai nuimti filtra
		$this->forget();
		break;

	case 'get-siteinfo':
		$r = $this->import_siteinfo();
		$p = & moon :: page();
		$p->set_local('cron', $r);
		return;
		moon_close();
		die($r);
	default:
		if (isset($_GET['ord'])) {
            $this->set_var('sort',(int)$_GET['ord']);
			$this->set_var('psl',1);
			$this->forget();
		}
		if (isset($_GET['page'])) $this->set_var('psl',(int)$_GET['page']);
	}
	$this->use_page('Common');
}

function properties()
{
	return array('psl'=>1, 'filter'=>'', 'sort'=>'', 'view'=>'list');
}

function main($vars)
{
	$p = &moon::page();
	$t = &$this->load_template();
	$info = $t->parse_array('info');
	$win = &moon::shared('admin');
	$win->active($this->my('fullname'));

	$a = array();
	if ($vars['view'] == 'form') {

        //******* FORM **********
		$err=(isset($vars['error'])) ? $vars['error']:0;
		//$p->css($t->parse('cssForm'));
		//$p->css('/i/adm/tabber.css');
		//$p->js('/js/tabber.js');

		$f = $this->form;
		$title= $f->get('id') ? $info['titleEdit'].' :: '.$f->get('name') : $info['titleNew'];
		$m = array(
			'error' => $err ? $info['error'.$err] : '',
			'event' => $this->my('fullname').'#save',
			'refresh' => $p->refresh_field(),
			'id' => ($id = $f->get('id')),
			'goBack' => $this->linkas('#'),

			'pageTitle' => $win->current_info('title'),
			'formTitle' => htmlspecialchars($title),
			'submenu' => $win->subMenu(array('*id*'=>$f->get('id'))),
			'toolbar' => '',
			//'hide' =>$f->checked('hide',1)
		) + $f->html_values();

        //$dirLogo=$this->get_dir('sponsor');
		//if ($m['logo']) $m['logo']=$dirLogo.$m['logo'];
		if ($m['logo']) $m['logo']=img('rw', $m['id'],$m['logo'].'?2');
		$m['bonus_int'] = $this->currency($m['bonus_int'],$m['currency']);
		for ($i=0;$i<3;$i++) {
			$m['review_type' . $i] = $f->checked('review_type',$i);
		}
		//paveiksliukas
		/*if ($m['img']) {
			$m['imgSrc'] = $this->get_var('imagesSrc').$m['img'];
			$m['imgSrcThumb'] = $this->get_var('imagesSrc').substr_replace($m['img'], '_',13,0);
		}*/


		//pridedam attachmentus ir toolbara
		/*if (is_object( $rtf = $this->object('rtf') )) {
			$rtf->setInstance( $this->get_var('rtf') );
			$m['toolbar'] = $rtf->toolbar('i_review',(int)$m['id']);
			$rtf->setInstance( $this->get_var('rtf') . '~1' );
			$m['toolbar1'] = $rtf->toolbar('i_review1',(int)$m['id']);
		}*/
		//$p->js('/js/jquery/autocomplete_1.0.2.js');
		//$p->css('/css/jquery/autocomplete_1.0.2.css');

		//pridedam attachmentus ir toolbara
		if (is_object( $rtf = $this->object('rtf') )) {
			$rtf->setInstance( $this->get_var('rtf') );
			$m['toolbar'] = $rtf->toolbar('i_review',(int)$m['id']);
		}

		/* rating */
		//$this->set_random_rating();
		$rating = $f->get('ratings');
		for ($i=1;$i<=5;$i++) {
			$r = substr($rating,-3);
			$rating = substr($rating,0,-3);
			$m['r' . $i] = number_format($r/10, 1);
		}
		$rating = (int) $f->get('editors_rating');
		$m['r_total'] = number_format($rating/10, 1);
		$res=$t->parse('viewForm',$m);

		//resave vars for list
		$save=array('psl'=>$vars['psl'],'sort'=>$vars['sort'],'filter'=>$vars['filter']);
		$this->save_vars($save);

	}
	else {

    	//******* LIST **********
		$m = array('items'=>'');
		$pn = & moon::shared('paginate');

		// rusiavimui
		$ord = & $pn->ordering();
		$ord->set_values(
				//laukai, ir ju defaultine kryptis
				array('name' => 1, 'sort_1'=>1) ,
				//antras parametras kuris lauko numeris defaultinis.
				2
			);
        //gauna linkus orderby{nr}
		$m += $ord->get_links(
			$this->linkas('#', '', array('ord' => '{pg}')),
			$vars['sort']
			);

		//Filtras
		$f = & $this->formFilter;
		$f->fill($vars['filter']);
		$filter = $f->get_values();
		$fm = array(
			//'fkat' => $f->options('kat',$kategorijos),
			'text' => $f->html_values('text'),
			'hidden' => $f->checked('hidden', 1),
			'goFilter' => $this->my('fullname').'#filter',
			'noFilter' => $this->linkas('#filter'),
			'isOn' => ''
			);
		foreach ($filter as $k=>$v) if ($v) {$fm['isOn'] = 1; break;}
		$fm['classIsOn'] = $fm['isOn'] ? ' filter-on':'';
		$m['filtras'] = $t->parse('filtras',$fm);

		$related = $this->whoHasCustomPage();
		$attachments = $this->whoHasAttachments();

		//generuojam sarasa
        if ($count=$this->getListCount()) {
			//puslapiavimui
			if (!isset($vars['psl'])) $vars['psl']=1;
			$pn->set_curent_all_limit($vars['psl'],$count,30);
			$pn->set_url( $this->linkas('#','',array('page'=>'{pg}')) );
			$m['puslapiai']=$pn->show_nav();
			$psl=$pn->get_info();

			$dat=$this->getList($psl['sqllimit'],$ord->sql_order());
            $goEdit=$this->linkas('#edit','{id}');
            $goRel=$this->linkas('pages#','{id}');
            $goPromo=$this->linkas('promo#','{id}');
			$t->save_parsed('items',array('goEdit'=>$goEdit, 'url.related'=>$goRel, 'url.promo'=>$goPromo));

			$loc = & moon::locale();
			$stats = $this->getStats();
			//$dirLogo = $this->get_dir('sponsor');
			foreach ($dat as $d) {
				$d['class'] = $d['is_hidden'] ? 'item-hidden' : '';
				$d['title'] = htmlspecialchars($d['name']);
				$d['alias']=htmlspecialchars($d['alias']);
                //$d['logo']=$d['logo'] ? $dirLogo.$d['logo']:'';
                $d['logo']=$d['logo'] ? img('rw', $d['id'],$d['logo'].'?2'):'';
				//$d['favicon']=$d['favicon'] ? $dirLogo.$d['favicon']:'';
				$d['favicon']=$d['favicon'] ? img('rw', $d['id'],$d['favicon']):'';
				if (in_array($d['id'], $related)) {
					$d['related'] = 1;
				}
				if (in_array($d['id'], $attachments)) {
					$d['attachment'] = 1;
				}
				if (isset($stats[$d['id']])) {
					$d['visits'] = $stats[$d['id']]['visits'];
					$d['downloads'] = $stats[$d['id']]['downloads'];
				}
				if(empty($d['visits'])) $d['visits'] = '&nbsp;';
				if(empty($d['downloads'])) $d['downloads'] = '&nbsp;';
				$m['items'] .= $t->parse('items',$d);
		    }
		} else {
            //filtras nerodomas kai tuscias sarasas
			if (!$fm['isOn']) $m['filtras'] = '';
		}

		$m['goNew']=$this->linkas('#edit');

		$title = $win->current_info('title');
        $m['title'] = htmlspecialchars($title);

		$res = $t->parse('viewList',$m);

		$save = array(
			'psl' => $vars['psl'],
			'sort' => (int)$vars['sort']
			);
		foreach ($filter as $k=>$v) if ($v!=='') {$save['filter']=$filter; break;}
		$this->save_vars($save);
	}

	$p->title($title);
	return $res;
}

//***************************************
//           --- DB AND OTHER ---
//***************************************

function set_random_rating() {
	$a = $this->db->array_query('SELECT id FROM '.$this->myTable);
	foreach ($a as $v) {
		$s = '';
		$t = 0;
		for ($i=0;$i<5;$i++) {
			$j = mt_rand(0,100);
			$t += $j;
			$s .= str_pad($j,3,'0',STR_PAD_LEFT);
		}
		$t = round($t/5);
		$this->db->update(array('editors_rating'=>$t, 'ratings'=>$s),$this->myTable, $v[0]);
	}

}

function getListCount()
{
	$sql='SELECT count(*) FROM '.$this->myTable.$this->_where();
	$m=$this->db->single_query($sql);
	return (count($m) ? $m[0]:0);
}

function getList($limit='',$order='')
{
	if ($order) $order=' ORDER BY '.$order;
	$sql='SELECT * FROM '.$this->myTable.$this->_where().$order.$limit;
	return $this->db->array_query_assoc($sql);
}

function getReviews($roomID)
{
	if (!$roomID) {
		return array();
	}
	$sql='
		SELECT meta_title, meta_description, content, page_id
		FROM '.$this->table('Reviews') . '
		WHERE room_id=' . (int)$roomID;
	return $this->db->array_query_assoc($sql, 'page_id');
}

function whoHasCustomPage() {
	$a = $this->db->array_query('
        SELECT DISTINCT room_id,room_id FROM ' . $this->table('Pages') . '
		WHERE hide<2
		', TRUE);
	return array_keys($a);
}
function whoHasAttachments() {
	$a = $this->db->array_query('
        SELECT DISTINCT parent_id,parent_id FROM rw2_attachments
		WHERE layer=0
		', TRUE);
	return array_keys($a);
}

function _where()
{
    if (isset($this->tmpWhere)) return $this->tmpWhere;
	$a=$this->formFilter->get_values();
	$w=array();
	//$w[] = 'category=' . $this->get_var('articleCategory');
	//$w[] = 'hide<2';
	if (empty($a['hidden'])) $w[] = "is_hidden=0";
	$v=addslashes($a['text']);
	if (strlen($v)) $w[] = "name like '%$v%'";
	$where = count($w) ? (' WHERE ' . implode(' AND ', $w)) : '';
	return ($this->tmpWhere=$where);
}

function getItem($id)
{
	$m = $this->db->single_query_assoc('
		SELECT * FROM ' . $this->myTable . ' WHERE
			id = ' . intval($id)
		);
		$sql='
		SELECT meta_title, meta_description, content as review
		FROM '.$this->table('Reviews') . '
		WHERE room_id=' . (int)$id . ' ORDER BY page_id LIMIT 1';
	$m += $this->db->single_query_assoc($sql);
	return $m;
}

function saveItem()
{
    $form=&$this->form;
	$form->fill($_POST);
    $d=$form->get_values();
	$id=intval($d['id']);

	//gautu duomenu apdorojimas
	//$d['hide'] = isset($_POST['hide']) && $_POST['hide'] ? 1:0;
	$s = '';
	$t = 0;
	for ($i=1;$i<=5;$i++) {
		$j = isset($_POST['r'.$i]) ? trim(str_replace(',', '.',$_POST['r'.$i])) : 0;
		$j = min(intval(floatval($j) * 10), 100);
		$t += $j;
		$s = str_pad($j,3,'0',STR_PAD_LEFT) . $s;
	}
	$t = round($t/5);
	$d['editors_rating'] = $t;
	$d['ratings'] = $s;
	$form->fill($d,false); //jei bus klaida

	//validacija
	$err=0;
	if (!is_object($rtf = $this->object('rtf'))) {
		$err=9;
	}
	//uri

    if ($err) {
		$this->set_var('error',$err);
		return false;
	}

	//jei refresh, nesivarginam
    if ($wasRefresh=$form->was_refresh()) return $id;

    //save to database
	$ins=$form->get_values(/*'name',*/ 'bonus_text',/*'bonus_terms',*/'intro_text', 'review_summary'/*, 'review_type'*/, 'editors_rating', 'ratings');
	$ins['review_type'] = 1;
	$ins['updated'] = time();
	$db=&$this->db();
	$db->update_query($ins, $this->myTable, array('id'=>$id));
	blame($this->my('fullname'), 'Updated', $id);

    //Review
	$ins = array(
		'room_id' => $id,
		'content' => $form->get('review'),
		'content_html' => '',
		'recompile' => 0,
		'updated' => time()
	);
	$ins += $form->get_values('meta_title', 'meta_description');
	//
	$rtf->setInstance( $this->get_var('rtf') );
	list(,$ins['content_html']) = $rtf->parseText($id, $ins['content'], TRUE);
	$db->replace($ins, $this->table('Reviews'));
	$rtf->assignObjects($id);
	return $id;
}

function currency($num, $currency)
	{
		$codes = array('USD' => '$', 'EUR' => '&euro;', 'GBP' => '&pound;');
		if (isset ($codes[$currency])) {
			return $codes[$currency] . ' ' . $num;
		}
		else {
			return $num . ' ' . $currency;
		}
	}


function import_siteinfo()
{
	//is adm.pokernews importuoja koki uri kokiam saite turi koks roomsas
    if (callPnEvent('adm','reviews.export#siteinfo',array(),&$answer,FALSE)) {
    	if (is_array($answer)) {
    		$this->db->query('TRUNCATE TABLE ' . $this->table('SiteInfo'));
    		$ins = array();
    		foreach ($answer as $v) {
    			$ins[] = "('" . $v['room_id'] . "','" . $v['site_id'] . "','" . $this->db->escape($v['uri']) . "')";
    		}
			if (isset($ins[0])) {
				$this->db->query('INSERT INTO ' . $this->table('SiteInfo') . ' VALUES ' . implode(', ', $ins));
			}
			return ('OK. ' . count($ins) . ' records updated!');
    	}
		else {
			return 'Error! Incorrect answer received!';
		}
	}
	else {
		return "Error! Can't connect to pokernews";
	}
}


function getStats()
{
	$sql='SELECT room_id, SUM(uri_count) as visits, SUM(uri_download_count) as downloads FROM '.$this->table('Stats') . " WHERE day>='".date('Y-m-01')."' GROUP BY room_id ORDER BY NULL";
	return $this->db->array_query_assoc($sql, 'room_id');
}

}
?>