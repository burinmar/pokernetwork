<?php

class shared_rpc
{
	function __construct()
	{
		include_class('twitter');
	}

	function getInstance($account, $config = [])
	{
		if (null === ($config = $this->getKeys($account)))
			return ;

		$api = new TwitterOAuth($config[1], $config[2], $config[3], $config[4]);
		$api->host = $config[0];
		$api->format = 'raw?uniq=ZLipid31qqE=';
		$api->useragent = 'Api from pn.' . _SITE_ID_;
		return new oauth_proxy($api);
	}

	private function getKeys($account, $depth = 0)
	{
		switch (strtolower($account)) {
		case 'pnw-imgsrv-dev':
			return ['http://imgsrv.pokernews.dev/api/', 'zzhGFHG271AbDyOJu', 'H3tOSffloh0THRsg88WQPYXRy9u7XeBFh5t9kJvJ', '1351884437-DFvmEBoSiOkZJ0ZMf', 'x1lDgj81qtkRcqvRVSwxgpQ5JQOJPAYnCk0TE9U4'];
		case 'pnw-imgsrv-prod':
			return ['http://imgsrv.pokernews.com/api/', 'nKJg9zi28Rg7Hti2H', 'L0mtkZ13BwNvYFTIpaRYsU7jbe1Ew4lh5HLpGNsi', '1875725619-bsAmtI4RrMMwZ30Vv', '4rsuNmOU2qJtuNEWn1p56gwS33R63NC8e4C2rrXu'];
		default:
			if ($depth == 0)
				return $this->getKeys($account . (is_dev() ? '-dev' : '-prod'), $depth + 1);
			else
				return ;
		}
	}
}

class oauth_proxy
{
	private $api;
	function __construct($api)
	{
		$this->api = $api;
	}

	function send($event, $data, &$result)
	{
		$result = $this->api->post('api', [
			'event' => $event,
			'data' => serialize($data)
		]);

		$probably_ok = ($this->api->http_code == 200);

		if ($this->api->http_info['content_type'] === 'application/vnd.php.serialized') {
			if ('b:0;' === $result)
				$result = false;
			else if (false === ($result_unserialized = @unserialize($result)))
				$probably_ok = false;
			else
				$result = $result_unserialized;
		}

		if (!$probably_ok)
			$result = [$this->api->http_code, $result];

		return $probably_ok;
	}
}