<?php

// http://search.cpan.org/dist/LockFile-Simple/Simple.pm
abstract class SunLock
{
	private $lockName;
	private $ignoreLockAfter;
	private $isLocked = false;
	private $errorMsg;

	static function fileLock($lockname, $ignoreLockAfter = 3600)
	{
		return new SunLockfile($lockname, $ignoreLockAfter);
	}

	static function dbLock($db, $lockname, $ignoreLockAfter = 3600)
	{
		$lock = new SunLockdb($lockname, $ignoreLockAfter);
		$lock->setDb($db);
		return $lock;
	}

	final function __construct($lockname, $ignoreLockAfter)
	{
		$this->lockName = $this->normalizeLockName($lockname);
		$this->ignoreLockAfter = intval($ignoreLockAfter);
	}

	abstract protected function normalizeLockName($lockname);

	public final function tryLock()
	{
		if (true == $this->isLocked)
			return $this->error('already locked');

		$selfTime = time();
		$selfId = uniqid() . '.' . $selfTime;

		try {
			$lockId = $this->lockSaveLoad($this->lockName, $selfId);
			if ($lockId === $selfId) {
				$this->isLocked = true;
			} elseif ($this->ignoreLockAfter != 0) {
				// retry on lock existing for more than specified timeout
				$isLockStale = false;
				if (strpos($lockId, '.') === 13) {
					list(, $lockTime) = explode('.', $lockId);
					$isLockStale = ($selfTime - $lockTime) > $this->ignoreLockAfter;
				}
				if ($isLockStale) {
					$this->deleteExistingLock($this->lockName);
					$lockId = $this->lockSaveLoad($this->lockName, $selfId);
					if ($lockId === $selfId) {
						$this->isLocked = true;
					}
				}
			}
		} catch (SynLockOperationException $e) {
			return $this->error($e->getMessage());
		}

		if ($this->isLocked) {
			register_shutdown_function(array($this, '_emergencyBackupCleanup')); // rewrite as lambda when upgraded
			return $this->success();
		}

		return $this->error('failed to lock');
	}

	abstract protected function lockSaveLoad($lockname, $contents);

	function _emergencyBackupCleanup() // can't make this private beacause of how r_s_f works
	{
		if (false == $this->isLocked)
			return;

		$this->deleteExistingLock($this->lockName);
	}

	public final function unlock()
	{
		if (false == $this->isLocked)
			return $this->error('not locked');

		try {
			$this->deleteExistingLock($this->lockName);
			$this->isLocked = false;
			return $this->success();
		} catch (SynLockOperationException $e) {
			$this->error('failed to delete lockfile');
		}
	}

	abstract protected function deleteExistingLock($lockname);

	private function error($msg)
	{
		$this->errorMsg = $msg;
		return false;
	}

	private function success()
	{
		return true;
	}
}
class SynLockOperationException extends Exception{}

class SunLockfile extends SunLock
{
	function normalizeLockName($filename)
	{
		return realpath(dirname($filename)) . DIRECTORY_SEPARATOR . basename($filename);
	}

	protected function lockSaveLoad($filename, $lockContents)
	{
		// don't use chmod instead of umask despite documentation notice -- doesn't suit our needs
		$oldUmask = umask();
		umask(0277);
		$fp = @fopen($filename, 'w'); // umask is not necesserilly 0277 here. Non thread-safe yay!
		umask($oldUmask);
		@chmod($filename, 0400);// see above (it *must* be readonly), although it might actually be too late already if it didn't work in the first place
		if (false !== $fp) {
			$written = @fwrite($fp, $lockContents);
			fclose($fp);
		}

		if (false === ($fp = @fopen($filename, 'r')))
			throw new SynLockOperationException('failed to acquire lockfile (or)');

		$id = @fread($fp, 24);
		fclose($fp);
		if (false === $id)
			return '';

		return $id;
	}

	protected function deleteExistingLock($filename)
	{
		if (is_file($filename)) {
			if (false == @unlink($filename)) {
				// VM bug?
				@chmod($filename, 0600);
				if (false == @unlink($filename)) {
					throw new SynLockOperationException('failed to delete existing lockfile');
				}
			}
		}
	}
}

class SunLockdb extends SunLock
{
	private $db;
	function setDb($db)
	{
		$this->db = $db;
	}

	function normalizeLockName($rawLockname)
	{
		return $rawLockname;
	}

	protected function lockSaveLoad($lockName, $lockContents)
	{
		$this->db->ping();
		$lockName     = $this->db->escape($lockName);
		$lockContents = $this->db->escape($lockContents);

		$this->db->query('
			INSERT IGNORE INTO sys_locks (lockid, state)
			VALUES("' . $lockName . '", "rw");
		');

		$this->db->query('
			UPDATE sys_locks
			SET payload="' . $lockContents . '", state="ro"
			WHERE lockid="' . $lockName . '" AND state="rw"
		');

		$id = $this->db->single_query_assoc('
			SELECT payload FROM sys_locks
			WHERE lockid="' . $lockName . '"
		');
		if (!isset($id['payload']))
			return '';

		return $id['payload'];
	}

	protected function deleteExistingLock($lockName)
	{
		$this->db->ping();
		$lockName = $this->db->escape($lockName);
		$this->db->query('
			DELETE FROM sys_locks
			WHERE lockid="' . $lockName . '"
		');
	}
}

