<?php

class shared_storage {
	//read
	private $hostURL = 'http://pnimg.net/';
	private $hostDir = 'w/';
	//write
	private $password = '';
	private $uploadURL = 'http://pnimg.net/storage.php';
	//internal
	private $location = '';
	private $curl;

	function __construct($path = NULL) {
		if (FALSE && is_dev()) {
			$this->hostURL = 'http://pnimg.dev/';
			$this->uploadURL = 'http://pnimg.dev/storage.php';
		}
	}

	function __destruct() {
		if (!is_null($this->curl)) {
			curl_close($this->curl);
		}
	}

	function save($oFile) {
		$fName = $oFile->file_name();
		if ($fName{0} === '@' ) {
			//tai url
			$post = array('filename' => $this->hostDir . $this->location . '/' . basename($fName), 'url' => ltrim($fName,'@'),);
		}
		else {
			$post = array('filename' => $this->hostDir . $this->location . '/' . $fName, 'file' => '@' . $oFile->file_path(),);
		}
		$response = $this->_post($post);
		if (!is_null($response)) {
			$a = (array) json_decode($response);
			if (isset ($a['url'])) {
				$a['wh'] = isset($a['wh']) ? $a['wh'] : null;
				$a['size'] = isset($a['size']) ? $a['size'] : null;
				$a['fname'] = $fName;
				return array($a['url'],$a['wh'],$a['size'],$a['fname']);
			}
			elseif (isset ($a['error'])) {
				moon::error($a['error']);
			}
			else {
				moon::error('Invalid response: ' . $response);
			}
		}
		return NULL;
	}

	function url($fname, $size = null) {
		$size = is_null($size) ? '0':$size;
		$fname = ltrim(basename($fname), '@');
		$s = $this->hostURL . $this->hostDir . $this->location . '/' . $size . '/' . substr_replace($fname, '/', 3, 0);
		return $s;
	}

	function delete($fName) {
		$filename = $this->hostDir . $this->location . '/' . basename($fName);
		$post = array('filename'=>$filename, 'delete'=>1);
		$response = $this->_post($post);
		return $this;
	}

	function replace($fName, $crop = array()) {
		$fName = basename($fName);
		$filename = $this->hostDir . $this->location . '/' . basename($fName);
		$post = array('filename'=>$filename, 'crop'=>TRUE) + $crop;
		$response = $this->_post($post);
		if (!is_null($response)) {
			$a = (array) json_decode($response);
			if (isset ($a['url'])) {
				$a['wh'] = isset($a['wh']) ? $a['wh'] : null;
				$a['size'] = isset($a['size']) ? $a['size'] : null;
				$a['fname'] = $fName;
				return array($a['url'],$a['wh'],$a['size'],$a['fname']);
			}
			elseif (isset ($a['error'])) {
				moon::error($a['error']);
			}
			else {
				moon::error('Invalid response: ' . $response);
			}
		}
		return NULL;
	}

	function location($dirName) {
		$this->location  = trim($dirName, '/');
		return $this;
	}


	private function _post($post) {
		if (is_null($this->curl)) {
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_HEADER, 0);
			curl_setopt($ch, CURLOPT_VERBOSE, 0);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/4.0 (compatible;)");
			curl_setopt($ch, CURLOPT_URL, $this->uploadURL);
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
			$this->curl = $ch;
		}
		$post['password'] = $this->password;
		curl_setopt($this->curl, CURLOPT_POSTFIELDS, $post);
		$response = curl_exec($this->curl);
		if (curl_errno($this->curl)) {
			moon::error(curl_error($this->curl));
			return NULL;
		}
		return $response;
	}


}

?>