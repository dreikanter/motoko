<?php

// Motoko kernel

// Revision 1

// Константы, определяющие пути к основным директориям Motoko
require 'mtk-pathes.inc.php';

// Параметры блога
require DIR_CONF.'constants.inc.php';
require DIR_CONF.'settings.inc.php';

define('URL_HOST', 'http'.'://'.getenv('HTTP_HOST'));
define('URL_ROOT', substr($_SERVER['PHP_SELF'], 0, strrpos($_SERVER['PHP_SELF'], '/')).'/');

define('SOFTWARE_TITLE', 'Motoko Blog System');
define('SOFTWARE_VERSION', '0.9.5 beta');
define('SOFTWARE_HP', 'http://motoko.ru/');

// Параметры отчётности об ошибках
ini_set('error_reporting', ERROR_LEVEL);
ini_set('display_errors', DISPLAY_ERRORS);
ini_set('log_errors', LOG_ERRORS);
ini_set('error_log', DIR_LOGS.'errors.log');

// Отключаем magic quotes для данных из GET/POST/Cookies
ini_set('magic_quotes_gpc', false);

// Задаём время жизни сессии
ini_set('session.cache_expire', 2880);
ini_set("session.gc_maxlifetime", 172800);

// Базовые функции
require DIR_MTK.'inc/tools.inc.php';

// Обработка запросов CSS и графических файлов из темплейтов MTK
$rq = getenv('REQUEST_URI');
$tplvurl = URL_ROOT.'mtk-tpl/';
if(substr($rq, 0, strlen($tplvurl)) == $tplvurl) {
	// Преобразуем URL файла из темплейта в реальный путь к нему
	$subPath = substr($rq, strlen($tplvurl));
	$path = DIR_MTK.'templates/'.$subPath;
	
	// проверяем легальность этого пути и запрошенного файла
	// (файл должен находиться внутри директории с темплейтами и иметь одно 
	// из допустимых расширений)
	$ext = strtolower(getExt($path));
	if(in_array($ext, array('css', 'gif', 'jpg', 'jpeg', 'png')) && 
		(strpos($subPath, '..') === false) &&
		file_exists($path)) {
		
		switch($ext) {
			case 'css': $ct = 'text/css'; break;
			case 'png': $ct = 'image/png'; break;
			case 'gif': $ct = 'image/gif'; break;
			default: $ct = 'image/jpeg';
		}
		
		// Отдаём файл
		header('HTTP/1.0 200 Ok');
		header('Content-Type: '.$ct);
		header('Content-Length: '.filesize($path));
		
		if($ext = 'css') {
			// CSS файлы предварительно обрабатываем: возможно есть необходимость 
			// в подстановке актуального пути к графическим файлам, прописанным в стилях. 
			// Маркер %tpl_url% заменяется на этот путь.
			$fh = fopen($path, 'r');
			$content = fread($fh, filesize($path));
			fclose($fh);
			echo str_replace('%tpl_url%', URL_ROOT.'mtk-tpl/'.CURRENT_THEME.'/', $content);
		} else {
			readfile($path);
		}
		
		// После того, как файл отдан, дальнейшее исполнение кода 
		// прекращается. Подобные запросы не учитываются статистикой
		exit();
		
	}
}

// Начало отсчёта времени
$START_TIME = getMicroTime();

// Сепаратор для аргументов (используется, когда необходимо передавать 
// session id через GET параметр)
ini_set('arg_separator.output', '&amp;');

// Строка предназначена для избавления от бага PHP в работе с сесисями,
// описанного здесь: http://bugs.php.net/bug.php?id=25876
ini_set("session.save_handler", "files");

require DIR_MTK.'lib/adodb_lite/adodb.inc.php';
require DIR_MTK.'inc/utf8.inc.php';
require DIR_MTK.'inc/kavych.inc.php';

define("XML_HTMLSAX3", DIR_MTK."lib/safehtml/classes/");

require DIR_MTK.'lib/safehtml/classes/safehtml.php';
require DIR_MTK.'lib/php-markdown-extra/markdown.php';

// Подключаем PEAR::HTML_QuickForm
$sep = ((strpos($_SERVER['SERVER_SOFTWARE'], '(Unix)') !== false)?':':';');
set_include_path(get_include_path().$sep.
	DIR_MTK.'lib/pear'.$sep);

//require 'HTML/QuickForm.php';

// Основные классы
require DIR_MTK.'classes/Session.class.php';
require DIR_MTK.'classes/RequestParser.class.php';
require DIR_MTK.'classes/ActionFabric.class.php';
require DIR_MTK.'classes/AbstractAction.class.php';
require DIR_MTK.'classes/GeneratorFabric.class.php';
require DIR_MTK.'classes/AbstractGenerator.class.php';

// <blog>
// Класс для работы с таблицами БД, относящимися к блогу
require DIR_MTK.'classes/BlogDB.class.php';
// </blog>

// Подключаемся к БД
@$DB = ADONewConnection("mysql");
@$result = $DB->Connect(DB_HOST, DB_USER, DB_PWD, DB_NAME);
if(!$result) { echo "Error connecting to the database."; exit(); }

// Задаём кодировку UTF8 для БД
$sqlRequest = "SET NAMES 'utf8';";
$result = $DB->Execute($sqlRequest);
if(!$result) { echo "Database error: ".$DB->ErrorMsg(); exit(); }

// Сессии работают через объект Session
//$ses = new Session($DB, TBL_PREFIX.'sessions');
session_start();

if(!isset($_SESSION['cnt'])) $_SESSION['cnt'] = 1; else $_SESSION['cnt']++;

// Корректировка user_id (для анонимных пользователей, user_id == -1)
$userId = (isset($_SESSION['user_id']) && 
	is_numeric($_SESSION['user_id']))?$_SESSION['user_id']:-1;

// <blog>
// Создаём объект BlogDB
$BDB = new BlogDB(
   	$DB, 
   	$userId,
	TBL_PREFIX.'posts', 
	TBL_PREFIX.'comments', 
	TBL_PREFIX.'users', 
	TBL_PREFIX.'cats', 
	TBL_PREFIX.'tags_map', 
	TBL_PREFIX.'tags');
// </blog>

// Глобальные переменные, предназначенные для общего использования:
// $DB - объект ADOConnection через который работает BlogDB и сессии 
// (и вообще всё, что работает с БД)
// $BDB - объект BlogDB, через который выполняются все операции с БД 
// блога (через него работают все action'ы)

// Om Mani Padme Hum
$generated = new GeneratorFabric(new ActionFabric(new RequestParser()));

// Ссылка на реферер сохраняется в переменной сессии не всегда, а только в том случае, когда 
// это необходимо. Выбор определяет action, отработавший в ActionFabric на текущей итеррации
// работы CMS
if($generated->returnable) {
	$_SESSION['ref'] = getenv('REQUEST_URI');
} elseif(!isset($_SESSION['ref'])) {
	$_SESSION['ref'] = URL_ROOT;
}

// Отдаём HTTP header, полученный из генератора
$hdr = $generated->hdr;

if(is_array($hdr)) {
	// Если header многострочный и получен в виде массива, 
	// отдаём каждую строку последовательно
	foreach($hdr as $line) header($line);
} else {
	// Если header однострочный, отдоём его за один раз
	header($hdr);
}

// Отдаём контент
echo $generated->result;

// В случае, есил размер лога ошибок превысил максимально допустимое значение 
// ERRORS_LOG_MAX_LEN, переименовываем лог (добавляем между суффикс, соответствующий 
// текущей дате).
if(file_exists(DIR_LOGS.'errors.log') && filesize(DIR_LOGS.'errors.log') > ERRORS_LOG_MAX_LEN) {
	rename(DIR_LOGS.'errors.log', DIR_LOGS.'errors.'.date('Ymd').'.log');
}

// В режиме отладки выполняется вывод текста экшенов и генераторов.
// В нормальном режиме работы, весь текстовый вывод убивается
if(DEBUG_MODE) {
	
	if(strlen(trim($generated->actionOutput))) {
		echo '<div class="debug"><hr><h4>Action output</h4>'.
			$generated->actionOutput.'</div>';
	}
	
	if(strlen(trim($generated->generatorOutput))) {
		echo '<hr><div class="debug"><h4>Generator output</h4>'.
			$generated->generatorOutput.'</div>';
	}
	
}

// Опциональное подключение модуля статистики
if(STAT_ENABLED) {
	
	if(!file_exists(STAT_MOD)) {
		// Проверка существования модуля статистики
		trigger_error("User statistics enabled but required PHP file doesn`t exists ('".STAT_MOD."')", E_USER_WARNING);
	} else {
		define('STAT_ACTION', $generated->actionName);
		define('STAT_GT', round(getMicroTime() - $START_TIME, 2));
		define('STAT_SQLCNT', $BDB->rqCnt);
		require STAT_MOD;
	}
	
}

session_write_close();
$DB->close();

?>