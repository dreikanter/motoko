<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.0 Transitional//EN">

<html>
<head>
<title>Motoko install</title>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<style type="text/css">
code { font-size:4mm; }
span.green { font-weight:bold; color:#009900; background:transparent; }
span.red { font-weight:bold; color:#990000; background:transparent; }
</style>
</head>

<body>
<?php

require 'mtk-pathes.inc.php';
require DIR_CONF.'constants.inc.php';
require DIR_CONF.'settings.inc.php';

require_once DIR_MTK.'lib/adodb_lite/adodb.inc.php';
require_once DIR_MTK.'inc/tools.inc.php';
require_once DIR_MTK.'classes/Session.class.php';

// считываем и перевариваем SQL скрипт

function runSqlScript($_fn, &$_db) {
	$sql = @file_get_contents($_fn);
	
	$sql = str_replace("___", TBL_PREFIX, $sql);
	$sql = str_replace("ENGINE = MyISAM", "", $sql);
	
	if(!$sql) {
		echo "Install script isn`t available (".$_fn.").";
		return false;
	}
	
	$patterns = array("/\/\*[\w\W]*\*\//", "/[\s]+/");
	$replacements = array("", " ");
	$sql = preg_replace($patterns, $replacements, $sql);
	$sql = trim($sql, "\n\t ;");
	$sql = explode(';', $sql);
	foreach($sql as $num => $value) $sql[$num] = $value.';';
	
	// $sql - массив SQL запросов для разметки БД
	
	echo '<p>Running script: <strong>'.basename($_fn).'</strong>...</p><ul>';
	
	foreach($sql as $sqlRequest) {
		echo "<li><strong><code>".$sqlRequest.'</code></strong><br>';
		$result = $_db->Execute($sqlRequest);
		if(!$result) {
			echo '<span class="red">Fault</span>: '.$_db->ErrorMsg();
		} else {
			echo '<span class="green">Ok</span>';
		}
		echo "<br><br></li>";
	}
	
	echo '</ul><p>Script completed.</p>';
	
	
}

// Создаём ADOdb connection

$db = ADONewConnection("mysql");
$result = $db->Connect(DB_HOST, DB_USER, DB_PWD, DB_NAME);

if(!$result) {
	echo "Error connecting to the database.";
	show($db->ErrorMsg());
	exit();
}

// Запускаем скрипт разметки БД

runSqlScript(DIR_MTK."sql/install.sql", $db);

echo "<br><hr><br>";

// Скрипт первичного заполнения таблиц

runSqlScript(DIR_MTK."sql/fill.sql", $db);


/*
echo "<br><hr><br>";
// Таблица сессий создаётся отдельно

$ses = new Session($db, TBL_PREFIX.'sessions');
$res = $ses->buildTable();
if($res) {
	echo "Session table was built successfully.";
}
*/

?>
</body>
</html>