<?php
class Bitly {
	private $_login;
	private $_apiKey;
	public $error = '';

	public function __construct($login, $apiKey) {
		$this->_login = $login;
		$this->_apiKey = $apiKey;
	}

	public function shortenSingle($url) {
		$ch = curl_init('http://api.bit.ly/v3/shorten?longUrl=' . urlencode($url) . '&apiKey='.$this->_apiKey.'&login='.$this->_login . '&format=txt');
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
		curl_setopt($ch, CURLOPT_TIMEOUT, 5);
		$response = curl_exec($ch);
		$curlInfo = curl_getinfo($ch);
		$errNo = curl_errno($ch);
		curl_close($ch);
		if ($errNo) {
			$this->error = 'Curl error:' . $errNo . '. Response: ' . $response . '. ' . print_r($curlInfo, TRUE);
			return FALSE;
		}
		if (!$response) {
			$this->error = 'No response. Response: ' . $response . '. ' . print_r($curlInfo, TRUE);
			return FALSE;
		} 
		if (200 != $curlInfo['http_code']) {
			$this->error = 'http_code error. Response: ' . $response . '. ' . print_r($curlInfo, TRUE);
			return FALSE;
		} 
		if (0 !== strpos($response, 'http://bit.ly/')) {
			$this->error = 'No bit.ly link. Response: ' . $response . '. ' . print_r($curlInfo, TRUE);
			return FALSE;
		}
		return trim($response);
	}
}
?>