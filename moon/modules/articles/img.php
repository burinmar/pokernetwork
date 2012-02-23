<?php
class img extends moon_com {

	function events($event, $par) {
		$name = $event;
		$width = isset($par[0]) ? intval($par[0]) : null;
		$height = isset($par[1]) ? intval($par[1]) : null;
		
		$dirImgOrig = 'w/articles/att/';
		$dirImg = 'w/articles/slideshow/';
		$fname = $fnameOrig = $dirImgOrig.$name;
		
		$f = & moon :: file();
		$ok = false;
		//$resized = false;
		if ($f->is_file($fname)) {
			$ok = true;
			if ($width && $height) {
				$fname = $dirImg.$name;
				$fname = substr_replace($fname, '_'.$width.'x'.$height, -4, 0);
				if (!$f->is_file($fname)) {
					$img = & moon :: shared('img');
					if ($width == 120 && $img->resize_exact($f, $fname, $width, $height) && $f->is_file($fname)) {
						//$resized = true;
					} elseif ($width != 120 && $img->resize($f, $fname, $width, $height) && $f->is_file($fname)) {
						//$resized = true;
					} else {
						$ok = false;
					}
				}
			}
		}
		
		if ($ok) {
			header('Expires: ' . gmdate('r', time() + 2764800), TRUE);
			header('Cache-Control: max-age=2764800');
			header('Pragma: public');
			$f->show_image();
		} else {
			header("HTTP/1.0 404 Not Found");
		}
		moon_close();
		exit;
	}

}
?>