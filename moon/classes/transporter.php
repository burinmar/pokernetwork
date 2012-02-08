<?php

class transporter {

	var $conf = array(
		'timeout' => 20,
	);

	var $keys = array();
	var $events = array();
	var $curl_options = array();
	var $trans_res = array();
	var $response_arr = FALSE;
	var $transporterTag = '';
	var $encryptionMethod = '2';
	var $serializeMethod = 'json';
	var $version = '5.2';
	var $postField = '_p';

	// konstruktorius
	function transporter() {
		//
	}

	// slaptazodzio nurodymas duomenu uzsifravimui
	// gavejas (recipient) gali nurodyti kelis slaptazodzius duomenu atsifravimui
	// jei gavejas nurodo kelis slaptazodzius - duomenu uzsifravimui naudojamas tas, kuris tiko atsifruojant duomenis
	function set_key($value) {
		$this->keys[] = $value;
	}

	// slaptazodzio nurodymas duomenu uzsifravimui
	function set_keys() {
		$this->keys = func_get_args();
	}

	// siuntejo (sender) metodas
	// uzklausos atsakymo laukimo laikas sekundemis
	function set_timeout($value) {
		$this->set_conf('timeout', $value);
	}

	// siuntejo (sender) metodas
	// ivykiu, kuriuos turi atlikti gavejas pridejimas
	function add_event($event, $vars) {
		$this->events[] = array(
			0 => $event,
			1 => $vars
		);
	}

	// siuntejo (sender) metodas
	// uzklausos siuntimas nurodytu adresu
	function send($url) {
		$packed_info = $this->pack_info($this->events, TRUE, $checkSum);
		$curl_res= $this->send_curl($url, $packed_info, $checkSum);
		return $this->was_error();
	}

	// siuntejo (sender) metodas
	// atsakymo i prasoma ivyki gavimas - nurodant ivykio numeri, jei yra keli
	function get_event_answer($event_id=1) {
		$response_arr = $this->response_arr;
		if($response_arr===FALSE) {
			$response = $this->trans_response();
			$this->response_arr = $response_arr = $this->unpack_info($response);
		}
		$event_id--;
		return (isset($response_arr[$event_id]) ? $response_arr[$event_id] : "");
	}

	// gavejo (recipient) metodas
	// gavejo uzklausos laukimo metodas
	// parametru nurodoma funkcija apdorojanti ivykius
	// nurodytai funkcijai per parametra perduodamas masyvas - ivykis ir parametrai array('event' => , 'vars' => )
	// nurodyta funkcija turi grazinti rezultata
	// metodas grazina sukauptus ir paruostus atsakymui rezultatus
	function answer($user_func, $user_obj=null) {
		$res = array();
		if (array_key_exists('5f2ef48e6530f6cb099a4f1bc623e087', $_POST)) $this->postField = '5f2ef48e6530f6cb099a4f1bc623e087';
		$post_fld = $this->postField;
		if(isset($_POST[$post_fld])) {
			if (get_magic_quotes_gpc()) $_POST[$post_fld] = stripslashes($_POST[$post_fld]);
			if (!in_array('', $this->keys)) $this->keys[] = '';
			if ($this->version>4 && array_key_exists('_v', $_GET) && $_GET['_v']>4) {
				$checkSum = (array_key_exists('_c', $_GET) ? $_GET['_c'] : '');
				$checkSumValid = FALSE;
				$ver = array_key_exists('_v', $_GET) ? $_GET['_v'] : $this->version;
				foreach($this->keys as $k => $v) {
					$c = $this->createChecksum(strlen($_POST[$post_fld]), md5($v), intval($ver));
					if($checkSum==$c) {
						$checkSumValid = TRUE;
						break;
					}
				}
				if (!$checkSumValid) {
					header('HTTP/1.0 404 Not Found');
					die();
				}
			}
			$events = $this->unpack_info($_POST[$post_fld], TRUE);
			if(is_array($events)) {
				if(is_object($user_obj)) $user_func = array($user_obj, $user_func);
				if(is_callable($user_func)) {
					foreach($events as $k => $v) {
						if (array_key_exists(0, $v)) $v['event'] = $v[0];
						if (array_key_exists(1, $v)) $v['vars'] = $v[1];
						$res[$k] = call_user_func($user_func, $v);
					}
				}
				else {
					$this->add_log($user_func, 5, "not valid user function", "");
				}
			}
			else {
				$this->add_log($events, 4, "POST data is not array or corrupted", "");
			}
		}
		else {
			$this->add_log($post_fld, 3, "No POST was found", "");
		}
		return $this->pack_info($res, FALSE, $checkSum);
	}

	// uzklausos siuntimas 'curl' metodu
	function send_curl($url, $data, $checkSum='') {
		$res = false;

		$post_fld = $this->postField;
		$data = array(
			$post_fld => $data
		);

		if(!function_exists('curl_init')) {
			$this->add_log("", 0, "Function curlint() does not exists", "");
			return $res;
		}

		if (function_exists('json_encode')) {
			$url .= strpos($url, '?') === FALSE ? '?_j' : '&_j';
		}
		$url .= strpos($url, '?') === FALSE ? '?_v=' . $this->version : '&_v=' . $this->version;
		$url .= strpos($url, '?') === FALSE ? '?_c=' . urlencode($checkSum) : '&_c=' . urlencode($checkSum);

		$ch = @curl_init($url);
		if($ch) {
			$version = curl_version();

			$res = true;
			curl_setopt($ch, CURLOPT_HEADER, 0);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1);
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->get_conf('timeout'));
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
			if ($version['version'] >= '7.10') {
				curl_setopt($ch, CURLOPT_ENCODING, 'gzip');
			}

			foreach($this->curl_options as $k => $v) {
				curl_setopt($ch, $k, $v);
			}

			$response = curl_exec($ch);
			$info = curl_getinfo($ch);
			$errno = curl_errno($ch);
			$error = curl_error($ch);

			$this->add_log($info, $errno, $error, $response);

			if(isset($info['http_code']) && $info['http_code']!="200") {
				$this->add_log($info, 2, "Response header code not valid: ".$info['http_code'], $response);
			}

			@curl_close($ch);
		}
		else {
			$this->add_log("", 1, "Function curlint() returned empty handle", "");
		}
		return $res;
	}

	// 'curl' metodo specifines asvybes
	function set_curl_option($option, $value) {
		$this->curl_options[$option] = $value;
	}

	// duomenu masyvo supakavimas ir paruosimas transportavimui
	function pack_info($arr, $encode = FALSE, &$checkSum) {
		if (!is_scalar($arr)) {
			if ($this->serializeMethod == 'json' && function_exists('json_encode')) {
				$res = @json_encode($arr);
			}
			else {
				$res = @serialize($arr);
			}
		}
		$secret_key = (array_key_exists(0, $this->keys) ? $this->keys[0] : "");
		if ($this->encryptionMethod == 2 || $this->encryptionMethod == 3) {
			if (array_key_exists('_v', $_GET) && $_GET['_v']>='5.2') $res = $this->replaceCommonStrings($res);
			if ($secret_key) {
				if ($this->encryptionMethod == 3) {
					$res = $this->encrypt3($res, $secret_key);
				}
				if ($this->encryptionMethod == 2) {
					$res = $this->encrypt2($res, $secret_key);
				}
			}
			$res = gzdeflate($res, 9);
			if ($encode) {
				$res = base64_encode($res);
			}
		}
		else {
			$this->encryptionMethod = 1;
			if ($secret_key) {
				$res = $this->encrypt($res, $secret_key);
			}
		}
		if ($this->transporterTag) {
			$res = $this->insert_into_tag($this->transporterTag, $res);
		}
		$checkSum = $this->createChecksum(strlen($res), md5($secret_key), intval($this->version));
		return $res;
	}

	// duomenu masyvo atpakavimas
	function unpack_info($packed, $decode = FALSE) {
		$res = '';

		if (strpos($packed, '<transporter:data>') !== FALSE && strpos($packed, '</transporter:data>') !== FALSE) {
			$this->transporterTag = 'transporter:data';
		}
		if (strpos($packed, '<t>') !== FALSE && strpos($packed, '</t>') !== FALSE) {
			$this->transporterTag = 't';
		}
		if ($this->transporterTag) {
			$packed = $this->extract_tag_data($this->transporterTag, $packed);
		}
		$secret_key = (array_key_exists(0, $this->keys) ? $this->keys[0] : "");
		$jsonFuncExists = function_exists('json_encode');
		if (!in_array('', $this->keys)) $this->keys[] = '';

		foreach($this->keys as $k => $v) {
			$decrypted = $packed;
			if ($decode) {
				$decrypted = base64_decode($packed);
			}

			$decrypted = @gzinflate($decrypted);
			if ($v) {
				$decrypted = $this->decrypt2($decrypted, $v);
			}
			$decrypted = $this->replaceCommonStrings($decrypted, TRUE);
			$unserializeMethod = '';
			if ($jsonFuncExists) {
				$unserializeMethod = 'json';
				$res = @json_decode($decrypted, TRUE);
			}
			if (!is_array($res)) {
				$unserializeMethod = '';
				$res = @unserialize($decrypted);
			}
			if(is_array($res) || $k>=10) {
				$this->encryptionMethod = 2;
				$this->serializeMethod = $unserializeMethod;
				$this->keys[0] = $v;
				break;
			}
		}

		if(!is_array($res) && !is_object($res)) {
			foreach($this->keys as $k => $v) {
				$decrypted = $packed;
				if ($decode) {
					$decrypted = base64_decode($packed);
				}

				$decrypted = @gzinflate($decrypted);
				if ($v) {
					$decrypted = $this->decrypt3($decrypted, $v);
				}
				$decrypted = $this->replaceCommonStrings($decrypted, TRUE);
				$unserializeMethod = '';
				if ($jsonFuncExists) {
					$unserializeMethod = 'json';
					$res = @json_decode($decrypted, TRUE);
				}
				if (!is_array($res)) {
					$unserializeMethod = '';
					$res = @unserialize($decrypted);
				}
				if(is_array($res) || $k>=10) {
					$res = $this->replaceCommonStrings($res, TRUE);
					$this->encryptionMethod = 3;
					$this->serializeMethod = $unserializeMethod;
					$this->keys[0] = $v;
					break;
				}
			}
		}

		if(!is_array($res) && !is_object($res)) {
			foreach($this->keys as $k => $v) {
				if ($v) {
					$decrypted = $this->decrypt($packed, $v);
				}
				$unserializeMethod = '';
				if ($jsonFuncExists) {
					$unserializeMethod = 'json';
					$res = @json_decode($decrypted, TRUE);
				}
				if (!is_array($res)) {
					$unserializeMethod = '';
					$res = @unserialize($decrypted);
				}
				if(is_array($res) || $k>=10) {
					$this->encryptionMethod = 1;
					$this->serializeMethod = $unserializeMethod;
					$this->keys[0] = $v;
					break;
				}
			}
		}
		return $res;
	}

	function encrypt3($plain_text, $secret_key) {
		if(!$secret_key || !$plain_text) return $plain_text;
		$res = "";
		$s = $plain_text;
		$k = md5($secret_key);
		$sl = strlen($s);
		$kl = strlen($k);
		if ($sl<$kl) {$k = substr($k,0,2);$kl = strlen($k);}
		if ($sl==1) return $s;
		$l = ceil($sl / $kl);
		if (!$l) $l = 1;
		$b=$l*$kl;
		if ($b>$sl)	$s=str_pad($s,$b,'_');

		$chunks = str_split($s, $l);
		$arr = array();
		$j = $i = 0;
		while(array_key_exists($i, $chunks)) {
			if (!isset($k[$j])) $j = 0;
			$key = $k[$j].$i;
			$arr[$key] = $chunks[$i];
			$j++;$i++;
		}
		ksort($arr, SORT_STRING);
		$res = implode('',$arr);
		return $res;
	}

	function decrypt3($plain_text, $secret_key) {
		if(!$secret_key || !$plain_text) return $plain_text;
		$res = "";
		$s = $plain_text;
		$k = md5($secret_key);
		$sl = strlen($s);
		$kl = strlen($k);
		if ($sl<$kl) {$k = substr($k,0,2);$kl = strlen($k);}
		if ($sl==1) return $s;
		$l = ceil($sl / $kl);
		if (!$l) $l = 1;
		$b=$l*$kl;
		if ($b>$sl)	$s=str_pad($s,$b,'_');
		$chunks = str_split($s, $l);
		$kChunks = str_split($k, 1);

		$arr = array();
		$j = $i = 0;
		$arr = array();
		$kChunksSorted = array();
		while(array_key_exists($i, $chunks)) {
			if (!isset($kChunks[$j])) $j = 0;
			$key = $kChunks[$j].$i;
			$kChunksSorted[] = $key;
			$j++;$i++;
		}

		$kChunks = $kChunksSorted;
		sort($kChunksSorted, SORT_STRING);
		foreach ($kChunksSorted as $key => $item) {
			$arr[$item] = $chunks[$key];
		}

		$arr2 = array();
		foreach ($kChunks as $key => $item) {
			$arr2[$item] = $arr[$item];
		}
		$res = trim(implode('',$arr2), ' _');
		return $res;
	}

	// eilutes uzsifravimas nurodytu raktu
	function encrypt2($plain_text, $secret_key) {
		if(!$secret_key) return $plain_text;
		$res = "";
		$s = $plain_text;
		$k = md5($secret_key);
		$i = 0;
		$j = 0;
		while(isset($s[$i])) {
			if(!isset($k[$j])) $j = 0;
			$ch_o = $s[$i];
			$ch_k = (isset($k[$j]) ? $k[$j] : "");
			$ch_e = $ch_o ^ $ch_k;
			$res .= $ch_e;
			$i++;
			$j++;
		}
		return $res;
	}

	// eilutes atsifravimas nurodytu raktu
	function decrypt2($enc_text, $secret_key) {
		if(!$secret_key) return $enc_text;
		$res = "";
		$s = $enc_text;
		$k = md5($secret_key);
		$i = 0;
		$j = 0;
		while(isset($s[$i])) {
			if(!isset($k[$j])) $j = 0;
			$ch_o = $s[$i];
			$ch_k = (isset($k[$j]) ? $k[$j] : "");
			$ch_e = $ch_o ^ $ch_k;
			$res .= $ch_e;
			$i++;
			$j++;
		}
		return $res;
	}

	// eilutes uzsifravimas nurodytu raktu
	function encrypt($plain_text, $secret_key) {
		$res = $plain_text;
		if($secret_key) {
			$res = "";
			$s = base64_encode($plain_text);
			$k = md5($secret_key);
			$i = 0;
			$j = 0;
			while(isset($s[$i])) {
				if(!isset($k[$j])) $j = 0;
				$ch_o = $s[$i];
				$ch_k = (isset($k[$j]) ? $k[$j] : "");
				$ch_e = $ch_o ^ $ch_k;
				$res .= $ch_e;
				$i++;
				$j++;
			}
			$res = base64_encode($res);
		}
		return $res;
	}

	// eilutes atsifravimas nurodytu raktu
	function decrypt($enc_text, $secret_key) {
		$res = $enc_text;
		if($secret_key) {
			$res = "";
			$s = base64_decode($enc_text);
			$k = md5($secret_key);
			$i = 0;
			$j = 0;
			while(isset($s[$i])) {
				if(!isset($k[$j])) $j = 0;
				$ch_o = $s[$i];
				$ch_k = (isset($k[$j]) ? $k[$j] : "");
				$ch_e = $ch_o ^ $ch_k;
				$res .= $ch_e;
				$i++;
				$j++;
			}
			$res = base64_decode($res);
		}
		return $res;
	}

	// ideda eilute i tag'a
	function extract_tag_data($tag, $str) {
		$res = "";
		$pattern = $this->insert_into_tag($tag, "__tmp");
		$pattern = preg_quote($pattern, "/");
		$pattern = "/". str_replace("__tmp", "(.*)", $pattern) ."/";
		$match_res = @preg_match($pattern, $str, $matches);
		if($match_res && isset($matches[1])) $res = $matches[1];
		return $res;
	}

	// istraukia tag'o reiksme
	function insert_into_tag($tag, $str) {
		return $res = "<".$tag.">".$str."</".$tag.">";
	}

	// grazina konfiguracinio elemento reiksme
	function get_conf($name, $default="") {
		$res = (isset($this->conf[$name]) ? $this->conf[$name] : "");
		$res = (!$res ? $default : $res);
		return $res;
	}

	// konfiguracinio elemento reiksmes nustatymas
	function set_conf($name, $value) {
		if(isset($this->conf[$name])) {
			$this->conf[$name] = $value;
		}
	}

	// irasu apie ivykius kaupimas
	function add_log($info, $errno, $error, $response) {
		$this->trans_res['info'] = $info;
		$this->trans_res['errno'] = $errno;
		$this->trans_res['error'] = $error;
		$this->trans_res['response'] = $response;
	}

	// grazina 'true', jei ivyko klaida
	function was_error() {
		return ($this->errno() ? true : false);
	}

	// grazina klaidos numeri
	function errno() {
		return (isset($this->trans_res['errno']) ? $this->trans_res['errno'] : false);
	}

	// grazina klaidos teksta
	function errtext() {
		return (isset($this->trans_res['error']) ? $this->trans_res['error'] : false);
	}

	// grazina masyva su informacija apie 'curl' transakcijos vykdyma
	function trans_info() {
		return (isset($this->trans_res['info']) ? $this->trans_res['info'] : false);
	}

	// grazina 'curl' transakcijos rezultata
	function trans_response() {
		return (isset($this->trans_res['response']) ? $this->trans_res['response'] : false);
	}

	function createChecksum($p1, $p2, $p3) {
		return abs(sprintf('%u', crc32($p1 . $p2 . $p3 . "h[j,/u:a'c32")));
	}

	function log($data) {
		return;
		if (!function_exists('is_local_version')) return;
		$wdir = is_local_version() ? 'w/' : 'w/';
		$rResult = '';
		$filename = $wdir . 't.php';
		$fp = @fopen($filename, 'r');
		if ($fp) {
			$rResult = @fread($fp, @filesize($filename));
			@fclose($fp);
		}
		$fp = @fopen($filename, 'w');
		if ($fp) {
			$rResult = !$rResult ? '<?php die();?>' : $rResult;
			$e = str_replace(array("\n","\r","  "),array("","",""),var_export($data, TRUE));
			$l = strlen($e);
			$wResult = @fwrite($fp, $rResult . date('YmdHis') . ' ' . $l . ' ' . $e . "\r\n");
			@fclose($fp);
		}
	}
	
	function replaceCommonStrings($str, $decode = FALSE) {
		$a = array ( 'nick' => '^a', 'password' => '^b', 'email' => '^c', 'name' => '^d', 'status' => '^e', 'timezone' => '^f', 'avatar' => '^g', 'forum_signature' => '^h', 'forum_paging' => '^i', 'restrictions' => '^j', 'birthdate' => '^k', 'homepage' => '^l', 'show_public' => '^m', 'show_friends' => '^n', 'pm_mailnotify' => '^o', 'created_on' => '^p', 'access' => '^q', 'logo' => '^r', 'logo_alt' => '^s', 'favicon' => '^t', 'logo_big' => '^u', 'updated_on' => '^v', 'tournaments_xml_name' => '^w', 'software_os' => '^x', 'currency' => '^y', 'min_deposit' => '^z', 'bonus_int' => '^A', 'bonus_percent' => '^B', 'us_friendly' => '^C', 'editors_rating' => '^D', 'established' => '^E', 'auditor' => '^F', 'network' => '^G', 'is_deleted' => '^H', 'alias' => '^I', 'bonus_code' => '^J', 'hide' => '^K', 'old_id' => '^L', 'local_ico' => '^M', 'marketing_code' => '^N', 'is_hidden' => '^O', 'http://adm.pokernews.com/w/rooms/' => '^P', 'poker' => '^R', 'Poker' => '^S', 'rooms' => '^T', 'adm.pokernews.com' => '^U', );
		foreach ($a as $k => $v) {$codes[] = $v; $words[] = $k;}
		$this->log(array($str,$decode));
		if ($decode) {
			$res = str_replace($codes, $words, $str);
		}
		else {
			$res = str_replace($words, $codes, $str);
		}
		$this->log(array($res,$decode));
		return $res;
	}
}

?>