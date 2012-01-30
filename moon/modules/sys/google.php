<?php
class google extends moon_com {

	function events($event, $par) {

		$page = moon::page();

		switch ($event) {

			default:
				$file = ltrim($page->uri_segments(0), '/');
				if (strpos($file, 'google-')===0) {
					$file = substr($file,7);
				}
				$filename = _W_DIR_ . 'google/' . $file;
				if (file_exists($filename)) {
					header('Content-Type: text/xml');
					if ($size = filesize($filename) ) {
						header("Content-Length: ".$size);
					}
					if ($time = filemtime($filename)) {
						header('Last-Modified: '.date('r', $time));
					}
					readfile($filename);
					moon_close();
					exit;
				}
		}

		$page = moon::page();
		$page->page404();
	}


}
?>