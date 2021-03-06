<?php

class moon_vb_relay
{
	public static function getInstance()
	{
		static $instance;
		if (!$instance) {
			$vbCwd = getcwd();
			if (strpos($vbCwd, DIRECTORY_SEPARATOR . 'forums') === false)
				$vbCwd .= DIRECTORY_SEPARATOR . 'forums';
			$instance = new moon_vb_relay($vbCwd);
		}

		return $instance;
	}

	const BANNED_USERGROUP = 8; // typical default for banned = 19
	const NOACTIVATION_USERGROUP = 3; // typical default for user awaiting activation

	private $vbCwd = '.';
	private $storedCwd = '.';
	private $loginSearchpattern = "/[^a-zA-Z0-9]+/";
	private function __construct($vbCwd)
	{
		$this->vbCwd = $vbCwd;

		define('NOPMPOPUP', 1);
		define('NONOTICES', 1);
		define('NOHEADER', 1);
		define('THIS_SCRIPT', 'MOON_VB_RELAY');
		define('CSRF_PROTECTION', false); // we suck
		// define('NOCOOKIES', 1);
		// define('NOSHUTDOWNFUNC', 1);
		// define('LOCATION_BYPASS', 1);
		// define('NOCHECKSTATE', 1);
		// define('SKIP_SESSIONCREATE', 1);

		// define('CSRF_SKIP_LIST', '');
		// define('VB_ENTRY', 'forum.php');
	}

	private function vbulletin()
	{
		global $vbulletin;
		return $vbulletin;		
	}

	public function logout()
	{
		$this->envVbStart();
		require_once(CWD . '/includes/functions_login.php');
		process_logout();
		$this->envVbEnd();
	}

	public function login($username, $password)
	{
		$this->envVbStart();
		
		$vbulletin = $this->vbulletin();

		// user exists
		if (false == ($userInfo = $this->fetchUserinfoFromUsername($username))) {
			$this->envVbEnd();
			return null;
		}
		// password is correct
		if (md5(md5($password) . $userInfo['salt']) != $userInfo['password']) {
			$this->envVbEnd();
			return null;
		}
		// not marked for death
		if (in_array($userInfo['usergroupid'], array(self::NOACTIVATION_USERGROUP, self::BANNED_USERGROUP))) {
			$this->envVbEnd();
			return null;
		}

		$vbulletin->userinfo = $userInfo;

		require_once(CWD . '/includes/functions_login.php');
		($hook = vBulletinHook::fetch_hook('login_verify_success')) ? eval($hook) : false;
		exec_unstrike_user($username);
		$logintype = ''; // fbauto
		$cookieuser = false;
		$cssprefs = '';
		process_new_login($logintype, $cookieuser, $cssprefs);

		$vbulletin->session->save();
		$this->envVbEnd();

		return $userInfo;
	}

	public function loggedIn()
	{
		$this->envVbStart();
		$userInfo = $this->vbulletin()->userinfo;
		$this->envVbEnd();

		return $userInfo['userid'] != 0
			? $userInfo
			: null;
	}

	private function fetchUserinfoFromUsername($username, $option=0, $languageid=0)
	{
		$vbulletin = $this->vbulletin();
		$useridq = $vbulletin->db->query_first('
			SELECT *
			FROM ' . TABLE_PREFIX . 'user
			WHERE username="' . $vbulletin->db->escape_string($username) . '"
		');
		if (!$useridq)
			return false; // $useridq
		$userid = $useridq['userid'];
		return fetch_userinfo($userid, $option, $languageid);
	}

	private function envVbStart()
	{
		$this->storedCwd = getcwd();
		chdir($this->vbCwd);
		require_once('./global.php');
	}

	private function envVbEnd()
	{
		chdir($this->storedCwd);
		$this->storedCwd = '.';
	}
}