<?php

class shared_img {

	function init() {
	}

	/***** PAGRINDINES FUNKCIJOS *****/
	// resize image to fit into window $nw x $nh
	function resize($oFile, $ftarget, $nw, $nh) {
		list($sw, $sh, $iType) = $this->_checkSize($oFile);
		$fsource = $oFile->file_path();
		//resize
		$sx = $sy = $dx = $dy = 0;
		$nw = $nw === 'auto' ? $sw:intval($nw);
		$nh = $nh === 'auto' ? $sh:intval($nh);
		if ($nw == 0 || $nh == 0) {
			return FALSE;
		}
		$k = min($nw / $sw, $nh / $sh);
		if ($k > 0.99) {
			//resaizinti nereikia
			return $this->_justCopy($fsource, $ftarget);
		}
		$dh = round($k * $sh);
		$dw = round($k * $sw);
		return $this->_transform($oFile, $ftarget, $dx, $dy, $sx, $sy, $dw, $dh, $sw, $sh);
	}

	function crop($oFile, $ftarget, $nw, $nh = 'auto', $left = 'auto', $top = 'auto') {
		list($sw, $sh, $iType) = $this->_checkSize($oFile);
		$fsource = $oFile->file_path();
		//crop
		$sx = $sy = $dx = $dy = 0;
		$nw = $nw === 'auto' ? $sw:intval($nw);
		$nh = $nh === 'auto' ? $sh:intval($nh);
		if ($nw == 0 || $nh == 0 || $sw == 0 || $sh == 0) {
			return FALSE;
		}
		if ($left === 'auto') {
			$left = floor(($sw - $nw) / 2);
		}
		if ($top === 'auto') {
			$top = floor(($sh - $nh) / 2);
		}
		$sx = $left;
		$sy = $top;
		$dh = $sh = $nh;
		$dw = $sw = $nw;
		return $this->_transform($oFile, $ftarget, $dx, $dy, $sx, $sy, $dw, $dh, $sw, $sh);
	}

	function thumbnail($oFile, $saveAs, $width) {
		return $this->resize_exact($oFile, $saveAs, $width, $width);
	}

	// resize image to be exactly $nw x $nh
	function resize_exact($oFile, $ftarget, $nw, $nh) {
		list($sw, $sh, $iType) = $this->_checkSize($oFile);
		$fsource = $oFile->file_path();
		//resize exact
		if ($nw == 0 || $nh == 0) {
			return FALSE;
		}
		$sx = $sy = $dx = $dy = 0;
		$k = min($sw / $nw, $sh / $nh);
		if ($k < 1) {
			//resaizinti nereikia
			//return $this->_justCopy($fsource, $ftarget);
		}
		$h = round($k * $nh);
		$w = round($k * $nw);
		if ($h < $sh) {
			//crop virsu
			$sy = floor(($sh - $h) / 2);
			$sh -= 2 * $sy;
		}
		else {
			$sx = floor(($sw - $w) / 2);
			$sw -= 2 * $sx;
		}
		$dh = $nh;
		$dw = $nw;
		return $this->_transform($oFile, $ftarget, $dx, $dy, $sx, $sy, $dw, $dh, $sw, $sh);
	}

	function _checkSize($oFile) {
		$fsource = $oFile->file_path();
		$i = file_exists($fsource) ? getimagesize($fsource):FALSE;
		if ($i === FALSE || empty ($i[0]) || empty ($i[1]) || empty ($i[2]) || !($i[2] & imagetypes())) {
			//tai ne paveiksliukas, arba nepalaikomas tipas
			return array(0, 0, 0);
		}
		return $i;
	}

	function _justCopy($fsource, $ftarget) {
		//resaizinti nereikia
		if ($ftarget == $fsource) {
			return TRUE;
		}
		if (file_exists($ftarget)) {
			unlink($ftarget);
		}
		if (copy($fsource, $ftarget)) {
			$oldumask = umask(0);
			chmod($ftarget, 0666);
			umask($oldumask);
			return TRUE;
		}
		return FALSE;
	}

	function _transform($oFile, $ftarget, $dx, $dy, $sx, $sy, $dw, $dh, $sw, $sh) {
		$fsource = $oFile->file_path();
		list(,, $iType) = getimagesize($oFile->file_path());
		if (str_replace('jpg', 'jpeg', $oFile->file_ext($ftarget)) !== image_type_to_extension($iType, FALSE)) {
			// target pletinys neatitinka imgtype
			return FALSE;
		}
		switch ($iType) {

			case IMAGETYPE_JPEG:
				$img = imagecreatefromjpeg($fsource);
				break;

			case IMAGETYPE_PNG:
				$img = imagecreatefrompng($fsource);
				break;

			case IMAGETYPE_GIF:
				$img = imagecreatefromgif($fsource);
				break;

			default:
				return FALSE;
		}
		$newImg = imagecreatetruecolor($dw, $dh);
		//transparentumas
		if ($iType == IMAGETYPE_GIF || $iType == IMAGETYPE_PNG) {
			$trnprtIndx = imagecolortransparent($img);
			if ($trnprtIndx >= 0) {
				$trnprtColor = imagecolorsforindex($img, $trnprtIndx);
				$trnprtIndx = imagecolorallocate($newImg, $trnprtColor['red'], $trnprtColor['green'], $trnprtColor['blue']);
				imagefill($newImg, 0, 0, $trnprtIndx);
				imagecolortransparent($newImg, $trnprtIndx);
			}
			elseif ($iType == IMAGETYPE_PNG) {
				imagealphablending($newImg, false);
				imagesavealpha($newImg, true);
				$transparent = imagecolorallocatealpha($newImg, 255, 255, 255, 127);
				imagefilledrectangle($newImg, 0, 0, $dw, $dh, $transparent);
			}
		}
		//end transparentumas
		imagecopyresampled($newImg, $img, $dx, $dy, $sx, $sy, $dw, $dh, $sw, $sh);
		if (file_exists($ftarget)) {
			unlink($ftarget);
		}
		switch ($iType) {

			case IMAGETYPE_JPEG:
				imagejpeg($newImg, $ftarget, 90);
				break;

			case IMAGETYPE_PNG:
				imagepng($newImg, $ftarget);
				break;

			case IMAGETYPE_GIF:
				imagegif($newImg, $ftarget);
				break;
		}
		imagedestroy($newImg);
		imagedestroy($img);
		$oldumask = umask(0);
		chmod($ftarget, 0666);
		umask($oldumask);
		return true;
	}

}

?>