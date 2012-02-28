<?php
class TinyURL {
	var $apiURL = 'http://tinyurl.com/api-create.php?url=';
	var $error = '';

	function get($url) {
		$url = $this->apiURL . urlencode($url);
		$ch = curl_init($url);
		$response = '';
		if ($ch) {
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
			curl_setopt($ch, CURLOPT_TIMEOUT, 5);
			$response = curl_exec($ch);
			if (FALSE === strpos($response, 'http://')) {
				$this->error = $response;
				return FALSE;
			}
			curl_close($ch);
		} else {
			$this->error = 'No response';
			return FALSE;
		}
		return $response;
	}
}
?>