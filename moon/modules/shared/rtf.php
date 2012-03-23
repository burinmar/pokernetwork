<?php
class rtf extends moon_com {

//nustatymai
function instances($instance)
{
	/*$cfg=array(
		'tableObjects' => '', //table for storing attachments
		'tableSource' => '', //table for storing original source
		'fileDir' => '', //writable directory, where to put files on disk
		'fileSRC' => '', //file uri for "href" or "src"
		'fileExt' => 'jpg,jpeg,gif,png', //allowed file extensions
		'fileMaxSize' => '5MB', // pvz. 1024, 1KB, 1MB    (taciau <=10MB )
		'imgOrigWH' => '1024x1000', // width x height, resize uploaded images if larger  (>=200x200)
		'imgWH' => '524x1000', // width x height, max image size in page   (>=50x50)
		'features' => '-code,-smiles', // supported features
        'attachments' => '', // supported attachments: file, image, video, html
		'parserFeatures'=>array(), //kokius defaultinius nustatymus perrasyti  shared_text
		'canEdit' => '', //komponento pavadinimas, kuris turi metoda canEdit(), pvz. news.full
	);*/

	include(MOON_MODULES .$this->my('location') . 'rtf.instances.php');
	return $cfg;
}

function setInstance($instance)
{
	$this->instance=base64_encode($instance);// echo "<br>$instance $this->instance [".']' ;
	//echo  $instance.'*';
	$this->parentZone=0;
	if (strpos($instance,':')) {
		list($instance,$this->parentZone) = explode(':',$instance);
		$instance=trim($instance);
		$this->parentZone=intval(trim($this->parentZone));
	}

	$cfg=$this->instances($instance);


	$this->myTable=$cfg['tableObjects'];
	$this->myTableSource=$cfg['tableSource'];

	$s=strtolower($cfg['fileMaxSize']);
	$MB=1024*1024;
	$this->maxFileSize=(int) $cfg['fileMaxSize'];
	if (strpos($s,'kb')) $this->maxFileSize=$this->maxFileSize*1024;
	elseif (strpos($s,'mb')) $this->maxFileSize=$this->maxFileSize*$MB;
	if ($this->maxFileSize<1024 || $this->maxFileSize>10*$MB) $this->maxFileSize=10*$MB;

	$wh=explode('x',$cfg['imgOrigWH']);
	$this->whO = count($wh)<2 || $wh[0]<200 || $wh[1]<200 ? array(200,200):array((int)$wh[0],(int)$wh[1]);

	$wh=explode('x',$cfg['imgWH']);
	$this->whT = count($wh)<2 || $wh[0]<50 || $wh[1]<50 ? array(50,50):array((int)$wh[0],(int)$wh[1]);

	$this->fileExt=$cfg['fileExt'];
	$this->fileDir=$cfg['fileDir'];
	$this->fileSrc=$cfg['fileSRC'];
	$this->features=$cfg['features'];
	$this->attachments=$cfg['attachments'];
	$this->parserFeatures=$cfg['parserFeatures'];
	$this->canEdit=$cfg['canEdit'];
	
	return $this;
}

//___________________________________________________________________________
//Prasideda komponentas
var $objType = array(
		'img' => 0,
		'video' => 1,
		'html' => 2,
		'file' => 3
	);

//___________________________________________________________________________
function onload()
{
	$this->form = &$this->form();
	$this->form->names('id','parent_id', 'content_type', 'comment','url','options');
	$this->form->fill(array('options'=>''));

	if (isset($_GET['wid'])) $instanceID = base64_decode($_GET['wid']);
	elseif (isset($_POST['wid'])) $instanceID = base64_decode($_POST['wid']);
	else $instanceID=$this->get_var('rtf');

	if (isset($_POST['text_id'])) {
		$this->setInstance($instanceID . '~' . $_POST['text_id']);
	}
	else {
		$this->setInstance($instanceID);
	}
}

//___________________________________________________________________________
function events($event, $par)
{

	if (isset($_POST['parent_id'])) $parentID = intval($_POST['parent_id']);
	else $parentID = isset($par[0]) && is_numeric($par[0]) ? $par[0] : 0;

	if($this->canEdit && is_callable($this->canEdit)) {
		if (!call_user_func($this->canEdit,$parentID)) {
			header('HTTP/1.0 404 Not Found');
			die();
		}
	}
	//$p=&moon::page();
	$this->forget();
	$this->use_page('');

	$this->set_var('parent_id', $parentID);

	switch($event)
	{
	case 'preview':
		$this->set_var('view','preview');
		break;
	case 'add':
	case 'add-img':
		$this->set_var('view', 'form');
		$this->form->fill(array('content_type'=>$this->objType['img'],'parent_id'=>$parentID));
		break;
	case 'add-video':
		$this->set_var('view', 'form');
		$this->form->fill(array('content_type'=>$this->objType['video'],'parent_id'=>$parentID));
		break;
	case 'add-html':
		$this->set_var('view', 'form');
		$this->form->fill(array('content_type'=>$this->objType['html'],'parent_id'=>$parentID));
		break;
	case 'add-file':
		$this->set_var('view', 'form');
		$this->form->fill(array('content_type'=>$this->objType['file'],'parent_id'=>$parentID));
		break;
	case 'edit':  //sleep(5);
		$id=isset($par[1]) ? intval($par[1]) : 0;
		$this->set_var('view', 'form');
		if(count($d = $this->admGetItem($id))) $this->form->fill($d,true);
		break;
	case 'delete':
		$id=isset($par[1]) ? intval($par[1]):0;
		if(count($is = $this->admGetItem($id))) {
			$this->admSaveFile($is['id'],false,$err);
			$this->deleteItem($par[1]);
		}
		break;
	case 'insert':
		$id=$this->admSaveItem($err);
		if ($err) $this->set_var('view', 'form');
		else $this->set_var('view', 'return');
		break;

	default:
		if (isset($_GET['thumb'])) {
			$this->admImgShowPreview($_GET['thumb']);
		}
	}
}

//___________________________________________________________________________
function properties()
{
	return array(
		'view' => '',
		'parent_id' => 0,
		'msg' => ''
	);
}


//___________________________________________________________________________
function main($vars=array())
{
	$p=&moon::page();
	$p->set_local('output', 'modal');
	$p->js('/img/rtf/rtf.js');


	$t=&$this->load_template();
	$info=$t->parse_array('info');

	$msg='';
	if ($vars['msg']) {
		// Grazinam kad padarem
		$i=$vars['msg'];
		$msg= isset($info[$i]) ? $info[$i]:'Ok: '.$i;
	}

	switch ($vars['view'])
	{
	case 'link':  // urlo iterpimui
		$res=$t->parse('form_link',array('instanceID'=>$this->instance));
		break;

	case 'form': //Forma
		$a=array('form'=>'','instanceID'=>$this->instance);
		$a += $this->form->html_values();
		switch ($a['content_type']) {
			case 3:
				$tpl='file';
				$a['extensions'] = '.' . str_replace(',',', .',$this->fileExt);
				break;
			case 2: $tpl='html'; break;
			case 1: $tpl='video'; break;
			default: $tpl='img';
				if ($is=$this->form->get('thumbnail')) {
					$f= & moon :: file();
					$src=$this->fileSrc;
					$b=$f->info_unpack($is);
					//if(count($b=$f->info_unpack($is)))
					//	$a['img_src'] = $this->fileSrc.$b['name_saved'];
					list($w,$h)=explode('x',$b['wh']);
                    if ($h && $w) {
                    	$k = 100 / ($h>$w ? $h : $w);
						if ($k > 1) {
							$k = 1;
						}
                        $a['w'] = round($w * $k);
                        $h = $a['h'] = round($h * $k);
						$a['padding'] = floor((100-$h)/2);
						//$a['img_src'] = $this->fileSrc.$b['name_saved'];
						$a['img_src'] = $this->linkas('#',false,array('wid'=>$this->instance, 'thumb'=>$b['name_saved']));
					}
				};
				//options
				if ($this->form->get('options')==='') {
					$this->form->fill(array('options'=>''));
				}
				$a['align-left'] = $this->form->checked('options','left');
				$a['align-center'] = $this->form->checked('options','center');
				$a['align-right'] = $this->form->checked('options','right');
				$a['align-default'] = $this->form->checked('options','');
				if (strrpos($this->attachments, 'image+')!==FALSE && empty($a['id'])) {
					$a['swfupload']=1;
					$p->js('/js/swfupload/swfupload.js');
					$p->js('/js/swfupload/swfupload.queue.js');
					$p->js('/js/swfupload/swfupload.handlers.js');
					$p->js('/js/swfupload/swfupload.fileprogress.js');
					$p->js('/js/swfupload/swfupload.cookies.js');
				}
				//pick from gallery
				$a['galleryScript'] = ($gCat = $this->get_var('rtfAddGallery')) && is_numeric($gCat) ? '<script type="text/javascript">initMultiUpload(\'img\','.$gCat.');</script>' : '';

		}
		$a['i'] = uniqid('');
		$a['form'] = $t->parse('form_'.$tpl,$a);
		$a['!action']=$this->linkas('#');
		$a['event'] = $this->my('fullname').'#insert';
		$a['refresh'] = $p->refresh_field();
		$err=isset($vars['error']) ? $vars['error']:'';
		if ($err) $a['error']= isset($info[$err]) ? $info[$err] : 'Error: '.$err;
		else $a['error'] = '';
		$a['header'] = $a['id'] ? $info['edit-'.$tpl] : $info['new-'.$tpl];
		$res=$t->parse('forms',$a);
		break;

	case 'return':  // Grazinam kad padarem
		$a=array('instanceID'=>$this->instance);
		$i=$vars['msg'];
		$a['msg']= isset($info['msg-'.$i]) ? $info['msg-'.$i]:'Ok: '.$i;
		if (!empty($vars['insertedID'])) {
			$a['new_obj_tag'] = '{id:' . $vars['insertedID'] . '}';
		}
		$a['i'] = uniqid('');
		$res=$t->parse('return',$a);
		break;

	case 'preview': // Preview
		if(isset($_POST['body']) && $_POST['body']!='') {
			list(,$res) = $this->parseText($vars['parent_id'], $_POST['body']);
		} else {
			$res = $info['err.preview.nocontent'];
		}
		$txt = & moon :: shared('text');
		$res = $txt->check_timer($res);
		$pages = $txt->break_pages($res);
		if (is_array($pages) && count($pages)>1) {
			$a = array('navPreview' => '', 'pagesPreview'=>'');
			foreach ($pages as $k=>$v) {
				$d['id'] = $k;
				list($d['title'], $d['text']) = $v;
				$a['navPreview'] .= $t->parse('navPreview', $d);
				$d['size']= moon::file()->format_size(strlen(strip_tags($d['text'])));
				$a['pagesPreview'] .= $t->parse('pagesPreview', $d);
			}

		}
		else {
			$a = array('text' => $res );
		}
		$err = $txt->error(FALSE);
		if ($err !== FALSE) {
			$a['error'] = htmlspecialchars($err);
		}
		$res=$t->parse('preview', $a );
		moon_close();
		die($res);
		break;

	default: // ifframe browseris
		$parentID=(int)$vars['parent_id'];
		$p->set_local('output', 'popup');
		$a=array('instanceID'=>$this->instance, 'available'=>'');
		$list = $this->admGetList($parentID);
		$a['obj_cnt'] = count($list);
		$a['items'] = '';
		$a['msg']= $t->ready_js($msg);
		$f= & moon :: file();
		$src=$this->fileSrc;

		//features
		if ($this->attachments) {
			$d = explode(',',$this->attachments);
			foreach ($d as $v) {
				if ($v = trim($v)) {
					if ($a['available']!=='') $a['available'] .= ' | ';
					$a['available'] .= $t->parse('available', array('objtype'=>rtrim($v,'+')));
				}
			}
		}

		//kokias ico turim tipui file
		$tmp = explode(',', $info['fico_show']);
		$ficos = array('default' => $info['fico_default']);
		foreach ($tmp as $v) {
			$v = explode('|',$v);
			$ico = trim($v[0]);
			foreach ($v as $vv) $ficos[trim($vv)] = $ico;
		}
        $uniqID = uniqid('');
		foreach($list as $v) {
			$v['go_edit'] = $this->linkas('#edit', $parentID.'.'.$v['id'],array('wid'=>$this->instance));
			$v['go_delete'] = $this->linkas('#delete', $parentID.'.'.$v['id'],array('wid'=>$this->instance));
			$v['tag'] = '{id:'.$v['id'].'}';
			$v['comment'] = htmlspecialchars($v['comment']);
			$v['i'] = $uniqID;
			//jei file
			if ($v['content_type']==3) {
				$b=$f->info_unpack($v['file']);
				$v['fico'] = isset($ficos[$b['ext']]) ? $ficos[$b['ext']] : $ficos['default'];
				$v['fsize'] = $f->format_size($b['size']);
				$v['fname'] = $b['name_original'];
				$a['items'] .= $t->parse('itemFile', $v);
			} else {
				if ($v['thumbnail']) {
					$b=$f->info_unpack($v['thumbnail']);
                    list($w,$h)=explode('x',$b['wh']);
                    if ($h && $w) {
                    	$k = 100 / ($h>$w ? $h : $w);
						if ($k > 1) {
							$k = 1;
						}
                        $w = $v['w'] = round($w * $k);
                        $h = $v['h'] = round($h * $k);
						//$v['img_src'] = $src.$b['name_saved'];
						$v['img_src'] = $this->linkas('#',false,array('wid'=>$this->instance, 'thumb'=>$b['name_saved']));
						$v['padding'] = floor((100-$h)/2);
					}
				}
				$a['items'] .= $t->parse('obj_item', $v);
			}

		}
		$a['hasFiles']= count($list) ? 'true':'false';
		$a['msg_err'] = $p->get_global('wysiwyg_err');
		$a['msg_ok'] = $p->get_global('wysiwyg_ok');
		$a['goNew']=$this->linkas('#add');
		$a['urlVideo']=$this->linkas('#add-video',false,array('wid'=>$this->instance));
		$a['urlImg']=$this->linkas('#add-img',false,array('wid'=>$this->instance));
		$a['urlFile']=$this->linkas('#add-file',false,array('wid'=>$this->instance));
		$a['urlHtml']=$this->linkas('#add-html',false,array('wid'=>$this->instance));
		$a['urlLink']=$this->linkas('#add-link',false,array('wid'=>$this->instance));
		$a['i'] = $uniqID;
		$res = $t->parse('obj_browser', $a);
		moon_close();
		die($res);
	}
	return $res;
}

function admSaveItem(&$err) //updatina irasa
{
	$form=&$this->form;
	$form->fill($_POST);
	$d=$form->get_values();
	$id=intval($d['id']);
	$d['parent_obj']=$this->parentZone;
	$form->fill($d);

	$ins=$form->get_values('content_type', 'comment','parent_id','options');
	$ins['layer'] = $this->parentZone;

	//validacija
	$wasRefresh=$form->was_refresh();
	$isUpload=false;
	$stop=FALSE;
	$err='';
	//failams reik issaugoti originalu pavadinima
	$fNameOrig='';

	switch ($d['content_type'])
	{
	// Video
	case '1':
	case 'video':
	if ($d['comment']==='') $err='video.notext';
		break;

	// HMTL
	case '2':
	case 'html':
		if ($d['comment']==='') $err='html.notext';
		break;

	// File
	case '3':
	case 'file':
		if (!$wasRefresh) {
			$f= & moon :: file();
			$saveAs=$this->fileDir.uniqid('');
			//jei atejo failas
			if ($isUpload=$f->is_upload('file',$e)) {
				$fNameOrig = $f->file_name();
				if ($this->fileExt && !$f->has_extension($this->fileExt)) $err='file.extension';
				else $f->file_name(uniqid('').'.'.$f->file_ext());
			} else $err='file.nofile';
		}
		break;

	// Image
	case '0':
	case 'img':
	default:
		if (!empty($_POST['swfupload'])) {
			$f= & moon :: file();
			$saveAs=$this->fileDir.uniqid('');
			//jei atejo failas
			if ($isUpload=$f->is_upload('file',$errF)) {
				if (!$f->has_extension('jpg,jpeg,gif,png')) $err='img.extension';
				else $f->file_name(uniqid('').'.'.$f->file_ext());
			}
			else {
				$err = 'err.nofile';
			}
			$ins['content_type'] = 0;
			$stop = TRUE;
		}
		elseif (!$wasRefresh) {
			//if (!$id) {
				//paveiksliukas bus tik jeigu naujas objektas
				$f= & moon :: file();
				$saveAs=$this->fileDir.uniqid('');
				//jei atejo failas
				if ($isUpload=$f->is_upload('file',$errF)) {
					if (!$f->has_extension('jpg,jpeg,gif,png')) $err='img.extension';
					else $f->file_name(uniqid('').'.'.$f->file_ext());
				}
				elseif(isset($_POST['url']) && $_POST['url']) {
					$err = '';
					$saveAs=$this->fileDir.uniqid('').'.'.$f->file_ext($_POST['url']);
					if ($isUpload=$f->is_url_content ($_POST['url'],$saveAs)) {
						if (!$f->has_extension('jpg,jpeg,gif,png')) $err='img.extension';
					} else $err='img.404';
				}
				elseif(isset($_POST['gallery_file_name']) && isset($_POST['gallery_file_name']['img'])) {
					$err = '';
					$saveAs=$this->fileDir.uniqid('').'.'.$f->file_ext($_POST['gallery_file_name']['img']);
					if ($isUpload=$f->is_url_content ($_POST['gallery_file_name']['img'],$saveAs)) {
						if (!$f->has_extension('jpg,jpeg,gif,png')) $err='img.extension';
					} else $err='img.404';
				}
				elseif (!$id && !$err) {
					$err='img.nofile';
				}
			//}
			//dabar dar options
		}
	}

	if ($err) {
		$this->set_var('error',$err);
		return false;
	}
	if ($wasRefresh) return $id;

	//save to database

	//dabar failas
	if ($isUpload && is_array($files=$this->admSaveFile($id,$f,$err2,$fNameOrig))) {
		list($ins['file'],$ins['thumbnail'])=$files;
	}
	if ($id) {
		$ins['updated']=time();
		$this->db->update_query($ins,$this->myTable,array('id'=>$id));
		$this->set_var('msg','updated');
	} else {
		$u=&moon::user();
		$ins['user_id']=$u->get_user_id();
		$ins['created']=time();
		$id=$this->db->insert_query($ins,$this->myTable,'id');
		$this->set_var('msg','inserted');
		$this->set_var('insertedID',$id);
	}
	if ($stop) {
		moon_close();
		exit;
	}
	$this->set_var('parent_id',$ins['parent_id']);
	return $id;
}

function admSaveFile($id,$fileObj,&$err, $fNameOrig='') //insertina irasa
{
	$err=0;
	$isUpload = is_object($fileObj) ? true : false;

	$dir=$this->fileDir;
	$sql='SELECT file,thumbnail FROM '.$this->myTable.' WHERE id='.$id;
	$is= $id ? $this->db->single_query($sql):array();

	$f=$fileObj;

	if ($isUpload && $f->file_size()>$this->maxFileSize) {
		$err='file.toobig'; //per didelis failas
		return;
	}
	$newO=$curO=isset($is[0]) ? $is[0] : '';
	$newT=$curT=isset($is[1]) ? $is[1] : '';
	//sena trinti jei yra
	if ($curO) {
		$fDel= moon :: file();
		if ($fDel->is_info($dir,$curO)) {
			$fName=$fDel->strip_extension();
			$fExt=$fDel->file_ext();
			$fDel->delete();
			$newO=$newT=null;
			if ($fDel->is_file($dir.$fName.'_.'.$fExt)) $fDel->delete();
		}
	}
	if ($isUpload) { //isaugom faila
		$fName=$f->strip_extension();
		$fExt=$f->file_ext();
		$fileO=$dir.$f->file_name();
		$img = &moon::shared('img');
		if ($f->has_extension('jpg,jpeg,gif,png')) {
			list($w,$h)=$this->whO;
			if ($img->resize($f, $fileO, $w, $h) && $f->is_file($fileO)) $newO=$f->file_info();
			//thumbnail
			$fileT=$dir.$fName.'_.'.$fExt;
			list($w,$h)=$this->whT;
			if ($newO && $img->resize($f, $fileT, $w, $h) && $f->is_file($fileT)) $newT=$f->file_info();
		} elseif ($f->save_as($fileO)) {
			if ($fNameOrig!=='') $f->file_name($fNameOrig);
			$newO=$f->file_info();
		}
		else $err=3; //technine klaida
	}
	if ($newO==='') $newO=null;
	if ($newT==='') $newT=null;
	return array($newO,$newT);
}

function admImgShowPreview($fname) {
	$fname = $this->fileDir . $fname;
	$f = & moon :: file();
	if ($f->is_file($fname) && $f->has_extension('jpg,png,gif')) {
    	$fsource = $f->file_path();
		list($w,$h)=explode('x',$f->file_wh());
		if ($h && $w) {
			$k = 100 / ($h>$w ? $h : $w);
			$nw = round($w * $k);
			$nh = round($h * $k);
		}

		header('Expires: ' . gmdate('r', time() + 2764800), TRUE);
		header('Cache-Control: max-age=2764800');
		header('Pragma: public');
		header('Content-Type: '.$f->content_type($f->file_ext()));
		if ($f->fileTime>0) {
			header("Last-Modified: ".date('r',$f->fileTime));
		}
		header('Content-Disposition: inline; filename="p_'.$f->file_name().'"');
		if ($k > 1) {
			// nedidinsim, rodysim koks yra
			readfile($f->file_path());
		}
		else {
	        $ext = $f->file_ext();
			switch ($ext) {
		    case 'jpg': $img=imagecreatefromjpeg($fsource); break;
			case 'png': $img=imagecreatefrompng($fsource); break;
			case 'gif': $img=imagecreatefromgif($fsource); break;
			}
			$mini = imagecreatetruecolor($nw,$nh);
			imagecopyresampled($mini,$img,0,0,0,0,$nw,$nh,$w,$h);
		    switch ($ext) {
		    case 'jpg': imagejpeg($mini,NULL,90); break;
			case 'png': imagepng($mini); break;
			case 'gif': imagegif($mini); break;
			}
			imagedestroy($mini);
			imagedestroy($img);
		}
		exit;
	}
	header("HTTP/1.0 404 Not Found", TRUE, 404);
	//moon_close();
	exit;
}

//___________________________________________________________________________
// sarasas
function admGetList($parent_id='')
{
	if (!$this->myTable) return array();
	$u=&moon::user();
	$uid=$u->get_user_id();
	$parent_id = intval($parent_id);
	$q  = 'SELECT * FROM '.$this->myTable.'
			WHERE layer='.$this->parentZone.' AND
			(
				(parent_id=0 AND user_id='.$uid.')';
	if($parent_id) {
		$q .= '  OR parent_id='.$parent_id;
	}
	$q .= ' ) ORDER BY created DESC';
	return $this->db->array_query_assoc($q);
}



//___________________________________________________________________________
// saraso irasas
function admGetItem($id)
{
	return $this->db->single_query_assoc(
		'SELECT * FROM '.$this->myTable.' WHERE id='.intval($id)
		);
}


//___________________________________________________________________________
// iraso pasalinimas
function deleteItem($id)
{
	$q  = 'DELETE FROM '.$this->myTable.' WHERE id='.intval($id);
	$this->db->query($q);
	return $this->db->affected_rows();
}


//***************************************
//           --- EXTERNAL ---
//***************************************

function toolbar($textAreaName,$parentID)
{
	$p=&moon::page();
	//$p->css('/css/article.css');
	$p->js('/js/modal.window.js');
	$p->js('/img/rtf/rtf.js');
	$p->css('/img/rtf/rtf.css');


	$a = array();
	$a['name'] = $this->my('name').$this->parentZone;
	$a['buttons'] = $this->features;
	$a['attachments'] = $this->attachments;
	$a['instance'] = $this->instance;
	$a['fld'] = $textAreaName;
	$a['go_preview'] = $this->url('#preview', $parentID);
	$a['goObjList'] = $this->attachments ? $this->url('#', $parentID) : '';
	$t=&$this->load_template();
	return $t->parse('toolbar', $a);
}


// nepriskirtu isoriniu objektu priskyrimas objektui
function assignObjects($objID,$source=false)
{
	$u=&moon::user();
	if (($uid=$u->get_user_id()) && $this->myTable) {
		$this->db->query(
			'UPDATE '.$this->myTable.'
			SET parent_id='.intval($objID).'
			WHERE parent_id=0 AND layer='.$this->parentZone.' AND user_id='.$uid
		);
    }

	//issaugom originalu teksta
	if (is_string($source) && $this->myTableSource) {
		$ins = array(
			'parent_id' => intval($objID),
			'layer' => $this->parentZone,
			'content' => $source,
			'updated' => time()
		);
		$this->db->replace($ins,$this->myTableSource);
	}
}


//objektui priskirtu failu sarasiukas
function getObjects($parentIDs, $showNew=false)
{
	if (is_array($parentIDs)) {
		$ids=array();
		foreach ($parentIDs as $id) $ids[]=intval($id);
	} else $ids=array(intval($parentIDs));
	if (!count($ids) || !$this->myTable) return array();
	$sql='SELECT id,file,thumbnail,parent_id,comment,content_type,options, content_type as obj_type
	FROM '.$this->myTable.'
	WHERE layer='.$this->parentZone.' AND (parent_id IN ('.implode(',',$ids).')';
	if ($showNew) {
		$u=&moon::user();
		$uid=intval($u->get_user_id());
		$sql.=' OR (parent_id=0 AND user_id='.$uid.')';
	}
	$sql.=') ORDER BY id ASC';
	return $this->db->array_query_assoc($sql);
}


//___________________________________________________________________________
// originalaus teksto gavimas
function getSource($obj_id)
{
	$res = $this->db->single_query(
		'SELECT content FROM '.$this->myTableSource. '
		WHERE parent_id='.intval($obj_id).'
		AND layer='.$this->parentZone
	);
	return (isset($res[0]) ? $res[0] : '');
}


function removeObjects($parentIDs,$removeSource=true)
{
	$dat=$this->getObjects($parentIDs,false);
	foreach ($dat as $d) {
		$this->admSaveFile($d['id'], NULL, $err);
		$this->deleteItem($d['id']);
	}
	if ($removeSource) {
		if (is_array($parentIDs)) {
			$ids=array();
			foreach ($parentIDs as $id) $ids[]=intval($id);
		} else $ids=array(intval($parentIDs));
		if (count($ids) && $this->myTableSource) {
			$this->query(
				'DELETE FROM '.$this->myTableSource.'
				WHERE (layer='.$this->parentZone.'
					AND parent_id IN ('.implode(',',$ids).'))'
			);
		}
	}
}

//___________________________________________________________________________
	// teksto apdorojimas
function parseText($parent_id, $source, $alertErrors=FALSE)
{
	$txt = &moon::shared('text');
	$txt->features = array_merge($txt->features,$this->parserFeatures);
	//print_a($txt->features);
	//die();
	if ($parent_id !== FALSE) {
		$objects = $this->getObjects($parent_id,true);
		$txt->objects( array($this->fileDir, $this->fileSrc), $objects);
	}
	$res = $txt->article($source);
	if ($alertErrors) {
		$txt->error();
	}
	return array($txt->preview($source),$res);
}

function parseTextTypeImg($parent_id, $source, $alertErrors=FALSE, $attachJs = true)
{
	$txt = &moon::shared('text');
	$txt->features = array_merge($txt->features,$this->parserFeatures);
	if ($parent_id !== FALSE) {
		$objects = $this->getObjects($parent_id,true);
		//$objects = array_reverse($objects);
		$objectsImg = array();
		$objectsOther = array();
		
		$images = array();
		$startPos = null;
		$startIdx = null;
		
		$tpl = $this->load_template();
		$m = array(
			'items:images' => '',
			'items:tabs' => '',
			'attachJs' => $attachJs
		);
		$nr = 0;
		$images = array();
		// split images and other objects
		foreach ($objects as $k=>$o) {
			if ($o['content_type'] == 0) { // image
				$pos = strpos($source, '{id:'.$o['id'].'}');
				if ($pos !== FALSE) {
					$objectsImg[$k] = $o;
					$a = explode('|',$o['file']);
					if (!empty($a[3])) {
						$vars = array(
							'nr' => ++$nr,
							'hide' => $nr != 1 ? 1 : 0,
							'imgSrc' => str_replace('adm/', '', $this->linkas('articles.img#'.$a[3], '550.400')),
							'thumbSrc' => str_replace('adm/', '', $this->linkas('articles.img#'.$a[3], '120.90')),
							'url.img' => '/files/cnt/'.$a[3],
							'description' => $o['comment']
						);
						$images[$pos] = $vars;
						//$m['items:images'] .= $tpl->parse('items:images', $vars);
						//$m['items:tabs'] .= $tpl->parse('items:tabs', $vars);
					}
					
					if ($startPos == null || $pos < $startPos) {
						$startPos = $pos;
						$startIdx = $k;
					}
				}
			} else {
				$objectsOther[] = $o;
			}
		}
		
		ksort($images);
		foreach($images as $img) {
			$m['items:images'] .= $tpl->parse('items:images', $img);
			$m['items:tabs'] .= $tpl->parse('items:tabs', $img);
		}
		
		if (isset($objectsImg[$startIdx])) {
			$htmlCode = $tpl->parse('article_img_slideshow', $m);
			// assign code as html attachment to first image id
			$attImg = array(
				'id' => $objectsImg[$startIdx]['id'],
				'file' => null,
				'thumbnail' => null,
				'parent_id' => $objectsImg[$startIdx]['parent_id'],
				'comment' => $htmlCode,
				'content_type' => 2,
				'options' => null,
				'obj_type' => 2
			);
			unset($objectsImg[$startIdx]);
			$objectsOther[] = $attImg;
			
			// assign other objects
			$txt->objects(array($this->fileDir, $this->fileSrc), $objectsOther);
			// remove left image ids from source
			foreach ($objectsImg as $v) {
				$source = str_replace('{id:'.$v['id'].'}', '', $source);
			}
		} else {
			$txt->objects(array($this->fileDir, $this->fileSrc), $objects);
		}
	}
	$res = $txt->article($source);
	if ($alertErrors) {
		$txt->error();
	}
	return array($txt->preview($source),$res);
}

}
?>