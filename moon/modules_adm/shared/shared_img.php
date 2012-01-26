<?php

class shared_img{

//var $imgDir='';

function shared_img()
{
}

function init()
{
	//$this->imgDir='';
}

//***** PAGRINDINES FUNKCIJOS *****
function resize($fileObj,$ftarget,$nw,$nh)
{ //resaizina paveiksliuka. Jei pavyko, grazina true, kitu atveju false
	$palaiko=array('jpg'=>IMG_JPG, 'png'=>IMG_PNG, 'gif'=>IMG_GIF);
	$types=array('jpg'=>IMAGETYPE_JPEG, 'png'=>IMAGETYPE_PNG, 'gif'=>IMAGETYPE_GIF);
	$ext=$fileObj->file_ext();
	if ($fileObj->file_ext($ftarget)!==$ext) return false;
	if ($ext==='jpeg') $ext='jpg';
    
    $fsource = $fileObj->file_path();
    $img_info = getimagesize($fsource);

	if (!isset($palaiko[$ext]) || !($tipai=imagetypes() & $palaiko[$ext])) return false;
    if(!is_array($img_info) || $img_info[2]!=$types[$ext]) return false;
	
	list($x,$y)=explode('x',$fileObj->file_wh());
	$nw = $nw==='auto' ? $x : intval($nw);
	$nh = $nh==='auto' ? $y : intval($nh);
	if ($x*$y==0 || $nw*$nh==0) return false;
	$k=min($nw/$x , $nh/$y);//didinimo koeficientas
	if ($k>0.99) { //resaizinti nereikia
		if ($ftarget==$fsource) return $fileObj;
		if (file_exists($ftarget)) unlink($ftarget);
		copy($fsource,$ftarget);
		chmod($ftarget,0666);
		return true;
	}
	$h=round($k*$y);
	$w=round($k*$x);

	switch ($ext) {
    case 'jpg': $img=imagecreatefromjpeg($fsource); break;
	case 'png': $img=imagecreatefrompng($fsource); break;
	case 'gif': $img=imagecreatefromgif($fsource); break;
	default: return false;
	}
	$mini = imagecreatetruecolor($w,$h);
	imagecopyresampled($mini,$img,0,0,0,0,$w,$h,$x,$y);
	if (file_exists($ftarget)) unlink($ftarget);
    switch ($ext) {
    case 'jpg': imagejpeg($mini,$ftarget,90); break;
	case 'png': imagepng($mini,$ftarget); break;
	case 'gif': imagegif($mini,$ftarget); break;
	}
	imagedestroy($mini);
	imagedestroy($img);
    chmod($ftarget,0666);
	return true;
}

function crop($fileObj, $ftarget, $nw, $nh='auto', $left='auto', $top='auto')
{
    $palaiko=array('jpg'=>IMG_JPG, 'png'=>IMG_PNG, 'gif'=>IMG_GIF);
	$ext=$fileObj->file_ext();
	if ($fileObj->file_ext($ftarget)!==$ext) return false;
	if ($ext==='jpeg') $ext='jpg';
	if (!isset($palaiko[$ext]) || !($tipai=imagetypes() & $palaiko[$ext])) return false;
	$fsource=$fileObj->file_path();
	list($x,$y)=explode('x',$fileObj->file_wh());
	$nw = $nw==='auto' ? $x : intval($nw);
	$nh = $nh==='auto' ? $y : intval($nh);
	if ($x*$y==0 || $nw*$nh==0) return false;

    if ($left==='auto') $left=floor(($x-$nw)/2);
	if ($top==='auto') $top=floor(($y-$nh)/2);

    switch($ext) {
        case 'gif':  $img = imagecreatefromgif($fsource);    break;
        case 'jpg':  $img = imagecreatefromjpeg($fsource);   break;
        case 'png':  $img = imagecreatefrompng($fsource);    break;
		default: return false;
    }
    $dimg = imagecreatetruecolor($nw, $nh);
    imagecopyresampled($dimg,$img,0,0,$left,$top,$nw,$nh,$nw,$nh);
    //$sharpenMatrix = array(-1,-1,-1,-1,16,-1,-1,-1,-1);
	//$divisor = 8;
	//$offset = 0;
	//imageconvolution($dimg, $sharpenMatrix, $divisor, $offset);
    if (file_exists($ftarget)) unlink($ftarget);
    switch (strtolower($ext)) {
		case 'jpg': imagejpeg($dimg, $ftarget, 90); break;
		case 'png': imagepng($dimg, $ftarget); break;
		case 'gif': imagegif($dimg, $ftarget); break;
	}
    imagedestroy($img);
	imagedestroy($dimg);
    chmod($ftarget,0666);
	return true;
}

function thumbnail(&$fileObj,$saveAs,$width)
{
    $name=basename($saveAs);
	$tmpFile=dirname($saveAs).'/'.'crop_'.$name;
	list($x,$y)=explode('x',$fileObj->file_wh());
	$nw=min($x,$y);
	if ($this->crop($fileObj,$tmpFile,$nw,$nw,'auto','auto') && $fileObj->is_file($tmpFile)){
	   	$ok=$this->resize($fileObj,$saveAs,$width,$width);
	   	unlink($tmpFile);
		return $ok;
	}
    return false;
}

function resize_exact(&$fileObj, $ftarget, $nw, $nh) {
    $palaiko=array('jpg'=>IMG_JPG, 'png'=>IMG_PNG, 'gif'=>IMG_GIF);
	$ext=$fileObj->file_ext();
	if ($fileObj->file_ext($ftarget)!==$ext) return false;
	if ($ext==='jpeg') $ext='jpg';
	if (!isset($palaiko[$ext]) || !($tipai=imagetypes() & $palaiko[$ext])) return false;
	$fsource=$fileObj->file_path();
	list($w,$h)=explode('x',$fileObj->file_wh());

    switch(strtolower($ext)) {
        case 'gif':  $img = imagecreatefromgif($fsource);    break;
        case 'jpg':  $img = imagecreatefromjpeg($fsource);   break;
        case 'png':  $img = imagecreatefrompng($fsource);    break;
    }

    $curr_w = $w;
    $curr_h = $h;

    $w = $nw;
    $h = $nh;
    
	// if new width and height are smaller than current - do not resize
	if (($curr_w <= $w) AND ($curr_h <= $h)) {
		if ($ftarget==$fsource) return $fileObj;
		if (file_exists($ftarget)) unlink($ftarget);
		copy($fsource,$ftarget);
		chmod($ftarget,0666);
		return true;
	}

    if($w && ($curr_w<$curr_h)) {
        $w = ($h / $curr_h) * $curr_w;
    }
    else {
        $h = ($w / $curr_w) * $curr_h;
    }

    if($w<$nw) {
        $c = ($nw / $w);
        $w = $c * $w;
        $h = $c * $h;
    }
    if($h<$nh) {
        $c = ($nh / $h);
        $w = $c * $w;
        $h = $c * $h;
    }

    $data = array();
    $data['src_x'] = 0;
    $data['src_y'] = 0;
    $data['src_w'] = $curr_w;
    $data['src_h'] = $curr_h;

    $data['dst_x'] = 0;
    $data['dst_y'] = 0;
    $data['dst_w'] = $w;
    $data['dst_h'] = $h;

    $gd_resource_tmp = imagecreatetruecolor($data['dst_w'], $data['dst_h']);

    /*
    $image_resized = &$gd_resource_tmp;
    $image = &$img;

    if (strtolower($ext) == 'gif' || strtolower($ext) == 'png') {
      $trnprt_indx = imagecolortransparent($image);
      if ($trnprt_indx >= 0) {
        $trnprt_color    = imagecolorsforindex($image, $trnprt_indx);
        $trnprt_indx    = imagecolorallocate($image_resized, $trnprt_color['red'], $trnprt_color['green'], $trnprt_color['blue']);
        imagefill($image_resized, 0, 0, $trnprt_indx);
        imagecolortransparent($image_resized, $trnprt_indx);
      }
      elseif (strtolower($ext) == 'png') {
        imagealphablending($image_resized, false);
        $color = imagecolorallocatealpha($image_resized, 0, 0, 0, 127);
        imagefill($image_resized, 0, 0, $color);
        imagesavealpha($image_resized, true);
      }
    }

    $gd_resource_tmp = &$image_resized;

    foreach ($data as $k=>$v) {
    	$data[$k] = round($v);
    }
    imagecopyresampled($gd_resource_tmp, $img, $data['dst_x'], $data['dst_y'], $data['src_x'], $data['src_y'], $data['dst_w'], $data['dst_h'], $data['src_w'], $data['src_h']);
    $dimg = &$gd_resource_tmp;
    */

    imagecopyresampled($gd_resource_tmp, $img, $data['dst_x'], $data['dst_y'], $data['src_x'], $data['src_y'], $data['dst_w'], $data['dst_h'], $data['src_w'], $data['src_h']);
    $dimg = $gd_resource_tmp;

    $w = $nw;
    $h = $nh;

    $curr_w = imagesx($dimg);
    $curr_h = imagesy($dimg);

    $data['src_x'] = floor(($curr_w - $w + 1) / 2);
    $data['src_y'] = floor(($curr_h - $h + 1) / 2);
    $data['src_w'] = $data['dst_w'] = $w;
    $data['src_h'] = $data['dst_h'] = $h;

    $data['dst_x'] = 0;
    $data['dst_y'] = 0;

    $gd_resource_tmp = imagecreatetruecolor($data['dst_w'], $data['dst_h']);
    imagecopyresampled($gd_resource_tmp, $dimg, $data['dst_x'], $data['dst_y'], $data['src_x'], $data['src_y'], $data['dst_w'], $data['dst_h'], $data['src_w'], $data['src_h']);
    $dimg = $gd_resource_tmp;

    if (file_exists($ftarget)) unlink($ftarget);
    switch (strtolower($ext)) {
		case 'jpg': imagejpeg($dimg, $ftarget, 90); break;
		case 'png': imagepng($dimg, $ftarget); break;
		case 'gif': imagegif($dimg, $ftarget); break;
	}
    imagedestroy($img);
	imagedestroy($dimg);
    chmod($ftarget,0666);
	return true;
}

}
?>