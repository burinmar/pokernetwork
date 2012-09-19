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
		case 'pokernetwork_socialimport':
			return is_dev() // .dev -> pntest1
				? array('oqY0t7uKsjyTN4Vb6nQ', 'XdyKYe9MyrfWE9247x5E4KQ49ClY6JUXbyzJvRZr30', '297837556-nlHyQvRt2Klc4E8DkS1XNYKQtAZp7PhYo2ZdFR5j', 'yQcn7FWiFtaK6eQu8JvP9nA5v98tvvitVOIJfCF1sU')
				: $this->getKeysSocialImport();
		}
	}

	private function getKeysSocialImport()
	{
		switch (_SITE_ID_) {
		case 'pnw:com':
			return array('stMnYtvpYBNnvM4yMf3uXA', 'SyGrGhYSRW2XHr9DTrgclBCtKF2jxHKg3IvM5sI498', '833107724-MCO98iQWnJcJnAZ3k5MMGRPmoJAErCAp6oPJpwAU', 'Bk6PoFG1nSPaefCHqPcvktN6CySroXOpD4fNbi3vM');
		}
	}
}
