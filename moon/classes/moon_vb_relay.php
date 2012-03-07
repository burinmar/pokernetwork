<?php

class moon_vb_relay
{
	public function getInstance()
	{
		static $instance;
		if (!$instance)
			$instance = new moon_vb_relay(getcwd());

		return $instance;
	}

	private function __construct($cwd)
	{
		$this->moonCwd = $cwd;

		define('NOPMPOPUP', 1);
		define('NONOTICES', 1);
		define('NOHEADER', 1);
		define('THIS_SCRIPT', 'MOON_VB_RELAY');
		define('CSRF_PROTECTION', true);
		// define('NOCOOKIES', 1);
		// define('NOSHUTDOWNFUNC', 1);
		// define('LOCATION_BYPASS', 1);
		// define('NOCHECKSTATE', 1);
		// define('SKIP_SESSIONCREATE', 1);

		// define('CSRF_SKIP_LIST', '');
		// define('VB_ENTRY', 'forum.php');
	}

	public function logout()
	{
		$this->envVbStart();
		require_once(CWD . '/includes/functions_login.php');
		process_logout();
		$this->envVbEnd();
	}

	private $moonCwd = '.';
	private function envVbStart()
	{
		chdir($this->moonCwd . DIRECTORY_SEPARATOR . 'forums');
		require_once('./global.php');
	}

	private function envVbEnd()
	{
		chdir($this->moonCwd);
	}
}