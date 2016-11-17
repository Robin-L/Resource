<?php 
date_default_timezone_set('Asia/shanghai');

function log_result($word, $filename)
{
	if (!preg_match('/^win/i', PHP_OS)) {
		$filename = '/home/wwwlog/'. $filename;
	} else {
		$filename = 'D:/wwwlog/'.$filename;
	}
	if (is_array($word)) {
		$word = tostring($word);
	}
	$fp = @fopen($filename, "a");
	if (!$fp) {
		createFolder(dirname($filename));
		$fp = @fopen($filename, 'a');
	}
	if ($fp) {
		flock($fp, LOCK_EX);
		fwrite($fp, "[" . date('Y-m-d H:i:s') . ']' . $word . "\r\n");
		flock($fp, LOCK_UN);
		fclose($fp);
	}
}

function tostring($word)
{
	$string = '';
	foreach ($word as $k => $v) {
		$string  .= "$k : $v;";
	}
	return $string;
}

function createFolder($path, $mod = 0770)
{
	if (!file_exists($path)) {
		return createFolder(dirname($path), $mod) && mkdir($path, $mod);
	}
	return true;
}