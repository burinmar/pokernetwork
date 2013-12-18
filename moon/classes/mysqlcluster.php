<?php
include_class('mysql');
class mysqlcluster extends mysql {

var $wasWrite = FALSE;
private $roLink = FALSE;

protected function handshake() {
		$this->ready = TRUE;
		$this->connectInfo['read-only-host'] = FALSE;
		$vars = $this->connectInfo;
		$check = array('server', 'user', 'password', 'database');
		foreach ($check as $v) {
			if (!isset ($vars[$v])) {
				$this->dblink = FALSE;
				return;
			}
		}
		// write link
		$this->dblink = $this->connect($vars['server'], $vars['user'], $vars['password'], $vars['database']);
		if (!$this->dblink) {
			// duodam dar viena sansa
			sleep(1);
			$this->dblink = $this->connect($vars['server'], $vars['user'], $vars['password'], $vars['database']);
			if (!$this->dblink) {
				$this->_error(1, 'F', array('server' => $vars['server'], 'error' => mysqli_connect_error()));
			}
			else {
				$this->_error(1, 'N', array('server' => $vars['server'], 'error' => 'Second chance!'));
			}
			//if (class_exists('moon_user')) {
			//	$u = & moon :: user();
			//	$u->logout();
			//}
		}
		//dabar surandam read-only linka
		$this->roLink = FALSE;
		if (!empty ($vars['read-only-hosts'])) {
			$slaves = explode(';', $vars['read-only-hosts']);
			// tegul ir master padirba kaip RO
			if ($this->dblink) {
				$slaves[] = $vars['server'];
			}
			// pasirinkim randomu
			shuffle($slaves);
			foreach ($slaves as $host) {
				$host = trim($host);
				if ($host) {
					$link = $this->connect($host, $vars['user'], $vars['password'], $vars['database']);
					//prisijungti pavyko?
					if ($link) {
						$this->roLink = $link;
						$this->connectInfo['read-only-host'] = $host;
						break;
					}
					else {
						$this->_error(1, 'N', array('server' => $host, 'error' => 'Offline!'));
					}
				}
			}
			if (!$this->roLink) {
				$this->_error(1, 'W', array('server' => $vars['read-only-hosts'], 'error' => 'All read hosts are down!'));
			}
		}
		else {
			$this->roLink = $this->dblink;
			$this->connectInfo['read-only-host'] = $vars['server'];
		}

		if (!$this->roLink) {
			//503 header
			$show = '';
			if (isset ($vars['page503'])) {
				if ($vars['page503'] == 0 || strtolower($vars['page503']) == 'false') {
					return;
				}
				else {
					$show = $vars['page503'];
				}
			}
			header('HTTP/1.1 503 Service Temporarily Unavailable', true, 503);
			header('Status: 503 Service Temporarily Unavailable');
			//header('Retry-After: 3600');
			if (function_exists('moon_close')) {
				moon_close();
			}
			if ($show && file_exists($show)) {
				require ($show);
			}
			exit;
		}
		//papildomas queris gal reikalingas nustatyti charset, collation arba/ir time_zone
		$set = array();
		if (!empty ($vars['charset'])) {
			$m = explode(':', $vars['charset']);
			$collate = isset ($m[1]) ? trim($m[1]) : '';
			$set[] = " NAMES '" . $this->escape(trim($m[0])) . "'" . ($collate != '' ? " COLLATE '" . $this->escape($collate) . "'" : '');
		}
		if (!empty ($vars['timezone'])) {
			$set[] = " time_zone='" . $this->escape($vars['timezone']) . "'";
		}
		if (isset ($set[0])) {
			$sql = 'SET ' . implode(', ', $set);
			if ($this->dblink) {
				mysqli_query($this->dblink, $sql);
			}
			if ($this->roLink) {
				mysqli_query($this->roLink, $sql);
			}
		}
	}

	// Variable to track and prevent invalid or duplicate master/regular mode switching
	private $oomState = 0;

	function operateOnMaster()
	{
		if ($this->oomState > 0) {
			return false;
		}
		$this->oomState++;

		$this->roLinkBackup = $this->roLink;
		$this->roLink = FALSE;
		return true;
	}

	function operateNormally()
	{
		if ($this->oomState == 0) {
			return false;
		}
		$this->oomState--;

		$this->roLink = $this->roLinkBackup;
		$this->roLinkBackup = FALSE;
		return true;
	}

	/*function connect($server, $user, $password, $dbname) {
		$r = @ mysql_connect($server, $user, $password, $this->newLink);
		if ($r && $dbname) {
			if (!@ mysql_select_db($dbname, $r)) {
				$r = FALSE;
				$this->_error(2, 'F', array('database' => $dbname, 'error' => mysql_error()));
			}
		}
		return $r;
	}*/


	function select_db($dbname) {
		return true;
	}


	function query($sql, $unbuffered = FALSE) {
		$this->ready || $this->handshake();
		/*static $master;
		if (is_null($master)) {
		$master = 0;
		$u = & sys :: user();
		if ($u->i_admin()) {
		$master = 1;
		}
		}
		!$master &&
		*/
		global $_profiler;
		if ($_profiler != NULL) {
			$_profiler->startTimer(Profiler::MySQL);
		}
		$qType = strtoupper(substr(ltrim($sql," \t\n\r("), 0, 6));
		$this->wasWrite = FALSE;
		$this->error = FALSE;
		if ($this->roLink && $qType == 'SELECT' && !$this->wasWrite) {
			//slave
			$result = mysqli_query($this->roLink, $sql);
			$result = $unbuffered ? mysqli_query($this->roLink, $sql, MYSQLI_USE_RESULT) : mysqli_query($this->roLink, $sql );
			if (!$result) {
				$this->error = mysqli_errno($this->roLink) . ': ' . mysqli_error($this->roLink);
				if ($this->throwExceptions) {
					throw new Exception('Bad query: ' . $sql . ' Error: ' . $this->error);
				}
				else {
					$this->_error(3, 'F', array('sql' => $sql, 'error' =>'[slave] ' . $this->error));
				}
			}
		}
		elseif ($this->dblink) {
			$result = $unbuffered ? mysqli_query($this->dblink, $sql, MYSQLI_USE_RESULT) : mysqli_query($this->dblink, $sql );
			if (!$result) {
				$this->error = mysqli_errno($this->dblink) . ': ' . mysqli_error($this->dblink);
				if ($this->throwExceptions) {
					throw new Exception('Bad query: ' . $sql . ' Error: ' . $this->error);
				}
				else {
					$this->_error(3, 'F', array('sql' => $sql, 'error' =>$this->error));
				}
			}
			$this->wasWrite = in_array($qType, array('INSERT', 'UPDATE', 'REPLAC'));
		}
		else {
			$this->error = 'Connection does not exist';
			$result = FALSE;
		}

		if ($_profiler != NULL) {
			$_profiler->stopTimer(Profiler::MySQL, $sql);
		}

		return $result;
	}


	function ping() {
		if ($this->dblink && !mysqli_ping($this->dblink)) {
			$this->handshake();
		}
		elseif ($this->roLink && !mysqli_ping($this->roLink)) {
			$this->handshake();
		}
	}


	function close() {
		$this->connectInfo['read-only-host'] = FALSE;
		if (!empty($this->roLink) && is_object($this->roLink)) {
			mysqli_close($this->roLink);
			if (!empty($this->dblink) && $this->dblink===$this->roLink) {
				 $this->dblink = FALSE;
			}
			$this->roLink = FALSE;
		}
		if (!empty($this->dblink) && is_object($this->dblink)) {
			mysqli_close($this->dblink);
		}
		$this->dblink = $this->roLink = FALSE;
	}

	/*function array_query($sql, $indexField = FALSE)
{
	$a = $this->active;
	$this->active = $this->roLink;
	$r = parent::array_query($sql, $indexField);
	$this->active = $a;
	return $r;

}

function array_query_assoc($sql, $indexField = FALSE )
{
    $a = $this->active;
	$this->active = $this->roLink;
	$r = parent::array_query_assoc($sql, $indexField);
	$this->active = $a;
	return $r;
}*/


//function fetch_row_assoc($result)
//{
//	return ($this->roLink ? mysql_fetch_assoc($result) : false);
//}

function num_rows($result)
{
	return ($this->roLink ? mysqli_num_rows($result) : 0);
}


}
