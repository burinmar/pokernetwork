<?php
class patch extends moon_com {
	function events($event, $par) {
		switch ($event) {
			case 'audrius' :
				$this->audrius();
				break;
			case 'nikita' :
				$this->nikita();
				break;
			default :
				if(method_exists($this, $event)) $this->$event($par);
		}
		echo ".";
		moon_close();
		exit;
	}

	function clrcronlog() {
		$r = $this->db->query('DELETE FROM sys_cron_log WHERE (task_id NOT IN (SELECT id FROM sys_cron_tasks))');
		echo $this->db->affected_rows();
	}
	function clr404() {
		$r = $this->db->query('TRUNCATE TABLE sys_404_errors');
		echo ($r ? 'OK' : 'Error ' . $this->db->error());
	}


	function testmail() {
		print_r($_SERVER);
		$to = $_GET['m'];
		$msg = isset($_GET['msg'])
			? htmlspecialchars($_GET['msg'])
			: 'the message';
		$headers= "From: My site<noreply@ibusmedia.lt>\r\n";
		$headers .= "Reply-To: $to\r\n";
		$headers .= "Return-Path: $to\r\n";
		$headers .= "X-Mailer: Test\n";
		$headers .= 'MIME-Version: 1.0' . "\n";
		$headers .= 'Content-type: text/html; charset=utf-8' . "\r\n";
		if($to) {echo $to . '<br />';var_dump(mail($to, 'the subject', $msg, $headers));}
	}




	function attachments() {
        $sqlArr = array();

		/*$m = $this->db->single_query("show tables like 'sys_attachments'");
		if (empty($m)) {
			$sqlArr[] = "CREATE TABLE `sys_attachments` (
  `user_id` INT(11) NOT NULL,
  `updated` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `attachments` TEXT NOT NULL,
  PRIMARY KEY (`user_id`)
)";
		}*/

		/* Patikrinam, ar dar nebuvo patchinta */
		$table = 'reporting_ng_sub_posts';
		/*$m = $this->db->single_query("SHOW create TABLE ". $table);
		$sql = isset ($m[1]) ? $m[1] : '';
		$doImport = FALSE;
		if (!strpos($sql, 'attachments')) {
			$sqlArr[] = "ALTER TABLE `$table` ADD COLUMN `attachments` TEXT NULL AFTER `recompile`";
			$doImport = TRUE;
		}*/

		/* do patch */
		$s = '';
		foreach ($sqlArr as $k => $v) {
			$r = $this->db->query($v);
			$s .= "SQL#" . $k . ' - ' . ($r ? 'OK' : 'Error ' . mysql_error()) . ' rows:'.$this->db->affected_rows(). "\n";
		}
		echo $s;

		$doImport = TRUE;
		if ($doImport) {
			//$this->db->query("UPDATE $table SET attachments=NULL");
			$r = $this->db->query("SELECT * FROM reporting_ng_attachments ORDER BY parent_id");
			$parentID = 0;
			$a = array();
			$types = array(0=>'img', 1=>'video', 2=>'html', 3=>'file');
			$file = moon::file();
			$count = $count1 = 0;
			while($row = $this->db->fetch_row_assoc($r)) {
				if ($row['parent_id'] != $parentID) {
					//reikia issaugoti
					if (count($a)) {
						//do insert
						$this->db->update(array('attachments' => serialize($a)), $table, (int)$parentID);
						$count++;
					}
					$parentID = $row['parent_id'];
					$a = array();
				}
				$ins = array();
				$ins['content_type'] = $types[$row['content_type']];
				$ins['comment'] = $row['comment'];
				$ins['options'] = $row['options'];
				$ins['layer'] = $row['layer'];
				if ($row['file'] && is_array($inf = $file->info_unpack($row['file']))) {
					$ins['file'] = $inf['name_saved'];
					$ins['wh'] = $inf['wh'];
				}
				$ins['updated'] = $row['updated'];
				$ins['user_id'] = $row['user_id'];
				$ins['created'] = $row['created'];
				foreach ($ins as $k=>$v) {
					if (empty($v)) {
						unset($ins[$k]);
					}
				}
				$a[$row['id']] = $ins;
				$count1++;
			}
			if (count($a)) {
				//do insert
				$this->db->update(array('attachments' => serialize($a)), $table, (int)$parentID);
				$count++;
			}
			$this->db->free_result($r);
			echo "$count1 attachments in $count items\n";
		}
	}

	function cleandb() {
		//i6valo db nuo wtrink

    	$m = $this->db->array_query("show tables like 'wtrink\_%'");
		foreach ($m as $d) {
			$table = $d[0];
			$this->db->query("DROP TABLE $table");
		}
		echo 'Dropped: ' . count($m) . "\n";
	}

	function cleancache() {
		moon::cache()->clean('*');
		echo 'Done';
	}

	/********************
	/ Special tools for audrius
	/ **************/

	function checkLog($par=''){
		$funcFormat = create_function('$bytes','
		$format = \'%01d %s\';
		$bytes = max(0, (int) $bytes);
		$units = array("B", "KB", "MB", "GB", "TB", "PB");
		$power = $bytes > 0 ? floor(log($bytes, 1024)) : 0;
		return sprintf($format, $bytes / pow(1024, $power), $units[$power]);
	');
		$f = "tmp/error.log";
		print "["._SITE_ID_."] ";
		if(file_exists($f)) {
//			print " ".gmdate("Y-m-d H:i:s", filemtime($f))." GMT";
			$size = filesize ($f);
			print $funcFormat($size);
			if (!empty($par) && $par[0]=='delete') {
				$nf=substr($f,0,-3).date('Ymd.His').'.log';
				rename($f,$nf);
				chmod($nf,0666);
			}
		}

		$f = "tmp/m-notsent-smtp.log";
		if(file_exists($f)) {
//			print " ".gmdate("Y-m-d H:i:s", filemtime($f))." GMT";
			$size = filesize ($f);
			print '(smtp: '.$funcFormat($size).')';
			if (!empty($par) && $par[0]=='delete') {
				unlink($f);
			}
		}

		$f = "tmp/m-notsent.log";
		if(file_exists($f)) {
//			print " ".gmdate("Y-m-d H:i:s", filemtime($f))." GMT";
			$size = filesize ($f);
			print '(mail: '.$funcFormat($size).')';
			if (!empty($par) && $par[0]=='delete') {
				unlink($f);
			}
		}
		die("\n");
	}

	function checkInfo($par=''){
		echo str_pad("["._SITE_ID_."]",9);
		$db= & moon::db();
		echo 'PHP: '.phpversion().'  MySQL: '.mysql_get_server_info().' (client '. mysql_get_client_info().')';
		die("\n");
	}

	function checkDNS($par=''){
		echo str_pad("["._SITE_ID_."]",9);
		if (function_exists('curl_init')) {
			$url='http://blogs.pokernews.com/robots.txt';
			$ch = curl_init($url);
			curl_setopt($ch, CURLOPT_HEADER, 0);
			curl_setopt($ch, CURLOPT_FAILONERROR, 1);
			curl_setopt($ch, CURLOPT_USERAGENT, 'Moon');
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			$s=curl_exec($ch);
			$err=curl_errno($ch);
			//print_r(curl_getinfo($ch));
			if ($err) echo "error $err :".curl_error($ch);
			else echo 'ok';
			curl_close($ch);
		} else {
			echo 'error: CURL not supported';
		}
		die("\n");
	}

   
}
