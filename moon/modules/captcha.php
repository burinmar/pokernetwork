<?php

class captcha extends moon_com {

	function events($event, $par) {
		switch ($event) {
			default:
			case 'captcha':
				$page = &moon::page();
				$code = substr(md5(uniqid()),0,4);
				$code = str_replace(array('0', '1', 'o', 'O', 'l', 'L'), 'k', $code);
				$page->set_global('captcha', $code);
				$w = 103;
				$h = 28;
				$image = imagecreate($w, $h) or die('Cannot Initialize new GD image stream');
				$background = imagecolorallocate($image, 121, 197, 235);
				// generate random dots
				for($i=0;$i<3;$i++) {
					imagefilledellipse($image, mt_rand(0,$w), mt_rand(0,$h), 1, 1, 255);
				}
				$color = imagecolorallocate($image, 255, 255, 255);
				imagestring($image, 5, 20, 5, $code[0].' '.$code[1].' '.$code[2].' '.$code[3], $color);
				header('Content-type: image/png');
				imagepng($image);
				imagedestroy($image);
				$this->forget();
				moon_close();
				exit;
				break;
		}
	}
}

?>