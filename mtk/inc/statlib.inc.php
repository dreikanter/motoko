<?php

define('ST_TODAY', 0);
define('ST_HOSTS', 1);
define('ST_HITS', 2);
define('ST_HOURS', 3);
define('ST_IPS', 4);
define('ST_FROM', 5);
define('ST_TIMING', 6);
define('ST_TIMING_URLS', 7);
define('ST_TIMING_ACTIONS', 8);

define('ST_TOTAL_SINCE', 100);
define('ST_TOTAL_HOSTS', 101);
define('ST_TOTAL_HITS', 102);
define('ST_TOTAL_HOURS', 103);

function f_lock($_fileName) {
	
}

function f_unlock($_fileName) {
	
}

function readData($_fileName) {
	f_lock($_fileName);
	$fh = @fopen($_fileName, 'r');
	if(!$fh) return false;
	$data = @unserialize(fread($fh, filesize($_fileName)));
	fclose($fh);
	f_unlock($_fileName);
	return $data;
}

function writeData($_fileName, $_data) {
	f_lock($_fileName);
	$fh = fopen($_fileName, 'w');
	if(!$fh) return false;
	$result = @fwrite($fh, serialize($_data));
	if(!$result) return false;
	fclose($fh);
	f_unlock($_fileName);
	return true;
}

?>