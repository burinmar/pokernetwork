<?php

$srcDir = 'forums/';
$upgrSrcDir = '../httpdocsbeta/forums_upgr2';
$dstDir = 'forums/';
$ftpGroup = 2225;
set_time_limit(1800);
// $srcDb = 'pokerne2043';
// $dstDb = 'pokerne2043_forum';

###

// $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($srcDir), RecursiveIteratorIterator::SELF_FIRST);
// foreach ($files as $file)
// {
// 	$relFile = str_replace($srcDir, '', $file);
// 	if (is_dir($file) === true) {
// 		mkdir($dstDir . $relFile);
// 		chmod($dstDir . $relFile, 0775);
// 	} else if (is_file($file) === true) {
// 		copy($file, $dstDir . $relFile);
// 		chmod($dstDir . $relFile, 0664);
// 	}
// 	chgrp($dstDir . $file, $ftpGroup);
// }

###

// mysql_connect('ussql-6:3306', $srcDb, 'Qkxi8ZP4');
// mysql_select_db($dstDb);
// $result = mysql_query('SHOW TABLES FROM ' . $srcDb . ' LIKE "vb%"');
// $row = mysql_fetch_array($result, MYSQL_NUM);
// while ($row = mysql_fetch_array($result, MYSQL_NUM)) {
// 	$tableName = $row[0];
// 	$result2 = mysql_query('SHOW CREATE TABLE ' . $srcDb . '.`' . $tableName . '`');
// 	$tableCreate = mysql_fetch_array($result2, MYSQL_NUM);
// 	$tableCreate = $tableCreate[1];
// 	mysql_query('DROP TABLE IF EXISTS ' . $tableName);
// 	$tableCreate = preg_replace('~AUTO_INCREMENT=[0-9]+\s+~', '', $tableCreate);
// 	mysql_query($tableCreate);
// 	mysql_query('INSERT INTO ' . $tableName . ' SELECT * FROM ' . $srcDb . '.`' . $tableName . '`');
// }

// mysql_query('UPDATE vb_user SET usergroupid=6 WHERE userid=19701');
// mysql_query('UPDATE vb_setting SET VALUE=0 WHERE varname="gzipoutput"');
// mysql_query('UPDATE vb_datastore SET DATA = REPLACE( DATA , \':"gzipoutput";i:1\', \':"gzipoutput";i:0\' ) WHERE title="options"');

###

$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($upgrSrcDir), RecursiveIteratorIterator::SELF_FIRST);
foreach ($files as $file)
{
	$relFile = str_replace($upgrSrcDir, '', $file);
	if (is_dir($file) === true) {
		mkdir($dstDir . $relFile);
		chmod($dstDir . $relFile, 0775);
	} else if (is_file($file) === true) {
		copy($file, $dstDir . $relFile);
		chmod($dstDir . $relFile, 0664);
	}
	chgrp($dstDir . $relFile, $ftpGroup);
}
