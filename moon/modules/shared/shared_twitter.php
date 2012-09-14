<?php

class shared_twitter
{
	function shared_twitter()
	{
		include_class('twitter');
	}

	function getInstance($account)
	{
		if (null == ($auth = $this->getKeys($account))) {
			moon::error('Twitter config error!');
			$auth = array('', '', '', '');
		}
		$twitter = new TwitterOAuth($auth[0], $auth[1], $auth[2], $auth[3]);
		$twitter->host = 'https://api.twitter.com/1.1/';
		$twitter->useragent = 'TwitterOAuth PN';
		return $twitter;
	}

	private function getKeys($account)
	{
		switch (strtolower($account)) {
		}
	}
}