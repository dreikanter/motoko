<?php

/**
 * Отдаёт текущее время в виде значения типа float <unixtime>.<milliseconds>
 */
function getMicroTime() { 
	list($usec, $sec) = explode(" ",microtime()); 
	return ((float)$usec + (float)$sec); 
}

function show($_data, $_quotes = false) {
	echo "<pre>".($_quotes?'"':'');
	print_r($_data);
	echo ($_quotes?'"':'')."</pre>";
}

function showi($_data, $_quotes = false) {
	echo "<code>".($_quotes?'"':'');
	print_r($_data);
	echo ($_quotes?'"':'')."</code>";
}

function writeToFile($_fn, $_content) {
	$fh = fopen($_fn, 'w');
	fwrite($fh, $_content);
	fclose($fh);
}

function getExt($_file) {
	$path = pathinfo($_file);
	return $path["extension"];
}

// returns file name w/o path and extension
function getName($_file) {
	$path = pathinfo($_file);
	return trim(substr($path["basename"], 0, -strlen($path["extension"])), ".");
}

function getPath($_file) {
	$path = pathinfo($_file);
	return $path["dirname"].'/';
}

/**
 * Форматирует число-размер файла
 * @param integer размер файла
 * @param bool При форматировании использовать неразрывные пробелы или обычные
 * (по-умолчанию)
 * @return string Отформатированное число с указанием единиц измерения 
 * (b, Kb, Mb)
 */
function formatWeight($_num, $_nbsp = false) {
	$space = $_nbsp?'&nbsp;':' ';
	if($_num < 1024) {
		return number_format($_num, 0, ',', ' ').$space.'b';
	} elseif($_num < 1048576) {
		return number_format((float)$_num / 1024, 1, ',', ' ').$space.'Kb';
	} else {
		return number_format((float)$_num / 1048576, 1, ',', ' ').$space.'Mb';
	}
}

function getImageType($_fileName) {
	$parts = pathinfo($_fileName);
	$ext = strtolower($parts['extension']);
	return ($ext == 'jpg')?'jpeg':$ext;
}

function makePage($_tplFile, $_content) {
	if(!file_exists($_tplFile) || !is_array($_content)) {
		return false;
	}
	
	$content = array();
	
	foreach($_content as $k => $v) {
		$content['%'.$k.'%'] = $v;
	}
	
	return strtr(file_get_contents($_tplFile), $content);
}

function yyyymmddToUnixtime($_ymd) {
	$m = array();
	if(!preg_match("/^(\d\d\d\d)(\d\d)(\d\d)$/", $_ymd, $m)) {
		return false;
	}
	return mktime(0, 0, 0, $m[2], $m[3], $m[1]);
}

function killDir($_dir) {
	
	// Проверяем существование директории
	if(!is_dir($_dir)) return false;
	
	foreach(glob($_dir.'/*', GLOB_NOSORT) as $fn) {
		if(is_dir($fn)) {
			killDir($fn);
		} else {
			if(!@unlink($fn)) {
				return false;
			}
		}
	}
	
	if(!@rmdir($_dir)) {
		return false;
	}
	
	return true;
}

function dirSize($_dir) {
	
	// Проверяем существование директории
	if(!is_dir($_dir)) return false;
	
	$weight = 0;
	
	foreach(glob($_dir.'/*', GLOB_NOSORT) as $fn) {
		if(is_dir($fn)) {
			$weight += dirSize($fn);
		} else {
			$weight += filesize($fn);
		}
	}
	
	return $weight;
	
}

// Преобразует строку русского текста в транслит
function translit($_text) {
	$cyrAlphabet = array('а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 
		'д' => 'd', 'е' => 'e', 'ё' => 'yo', 'ж' => 'zh', 'з' => 'z', 
		'и' => 'i', 'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm', 
		'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 
		'т' => 't', 'у' => 'u', 'ф' => 'f', 'х' => 'h', 'ц' => 'cz', 
		'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sch', 'ъ' => '\'', 'ы' => 'y', 
		'ь' => '\'', 'э' => 'e', 'ю' => 'yu', 'я' => 'ya', 'А' => 'A', 
		'Б' => 'B', 'В' => 'V', 'Г' => 'G', 'Д' => 'D', 'Е' => 'E', 
		'Ё' => 'YO', 'Ж' => 'ZH', 'З' => 'Z', 'И' => 'I', 'Й' => 'Y', 
		'К' => 'K', 'Л' => 'L', 'М' => 'M', 'Н' => 'N', 'О' => 'O', 
		'П' => 'P', 'Р' => 'R', 'С' => 'S', 'Т' => 'T', 'У' => 'U', 
		'Ф' => 'F', 'Х' => 'H', 'Ц' => 'CZ', 'Ч' => 'CH', 'Ш' => 'SH', 
		'Щ' => 'SCH', 'Ъ' => '\'', 'Ы' => 'Y', 'Ь' => '\'', 'Э' => 'E', 
		'Ю' => 'YU', 'Я' => 'YA');
	return strtr($_text, $cyrAlphabet);
}

// Преобразует текст в шорткат, заменяя пробелы андерскорами 
// и убирая лишние символы
function shortcut($_text) {
	return preg_replace(array("/\s+/", "/[^a-zA-Z\d-_]+/"), 
		array("_", ""), $_text);
}

function utf2translit($title) {
	$utf2en = array(
			"Ґ" => "G", "Ё" => "YO", "Є" => "E", "Ї" => "YI", "І" => "I", 
			"і" => "i", "ґ" => "g", "ё" => "yo", "№" => "#", "є" => "e", 
			"ї" => "yi", "А" => "A", "Б" => "B", "В" => "V", "Г" => "G", 
			"Д" => "D", "Е" => "E", "Ж" => "ZH", "З" => "Z", "И" => "I", 
			"Й" => "Y", "К" => "K", "Л" => "L", "М" => "M", "Н" => "N", 
			"О" => "O", "П" => "P", "Р" => "R", "С" => "S", "Т" => "T", 
			"У" => "U", "Ф" => "F", "Х" => "H", "Ц" => "TS", "Ч" => "CH", 
			"Ш" => "SH", "Щ" => "SCH", "Ъ" => "'", "Ы" => "YI", "Ь" => "", 
			"Э" => "E", "Ю" => "YU", "Я" => "YA", "а" => "a", "б" => "b", 
			"в" => "v", "г" => "g", "д" => "d", "е" => "e", "ж" => "zh", 
			"з" => "z", "и" => "i", "й" => "y", "к" => "k", "л" => "l", 
			"м" => "m", "н" => "n", "о" => "o", "п" => "p", "р" => "r", 
			"с" => "s", "т" => "t", "у" => "u", "ф" => "f", "х" => "h", 
			"ц" => "ts", "ч" => "ch", "ш" => "sh", "щ" => "sch", "ъ" => "'", 
			"ы" => "yi", "ь" => "", "э" => "e", "ю" => "yu", "я" => "ya"
		);
	return strtr($title, $utf2en);
}

/**
 * Функция сортирует двумерные массивы, сравнивая их элементы по полю 
 * с заданным именем и типом. Для сравнения строк используется ыункция strcmp.
 */
function sort2d($_array, $_field, $_type = 'string', $_field2 = false, $_type2 = 'string') {
	global $sort2dArray_field;
	global $sort2dArray_type;
	global $sort2dArray_field2;
	global $sort2dArray_type2;
	
	$sort2dArray_field = $_field;
	$sort2dArray_type = $_type;
	$sort2dArray_field2 = $_field2;
	$sort2dArray_type2 = $_type2;
	
	$data = $_array;
	usort($data, 'sort2dCmp');
	
	return $data;
}

/**
 * Функция используется только из функции sort2d и предназначена 
 * для сревнения элементов двумерных массивов. Управляется через 
 * глобальные переменные $sort2dArray_field и $sort2dArray_type
 */
function sort2dCmp($_a, $_b) {
	global $sort2dArray_field;
	global $sort2dArray_type;
	global $sort2dArray_field2;
	global $sort2dArray_type2;
	
	$a1 = strtolower($_a[$sort2dArray_field]);
	$b1 = strtolower($_b[$sort2dArray_field]);
	
	if($a1 === $b1) {
		// Случай, когда вторичный кретерий не задан
		if($sort2dArray_field2 === false) return 0;
		
		// Проверка по вторичному кретерию
		$a2 = strtolower($_a[$sort2dArray_field2]);
		$b2 = strtolower($_b[$sort2dArray_field2]);
		
		if($a2 === $b2) return 0;
		
		return ($sort2dArray_type2 == 'string')?strcmp($a2, $b2):($b2 - $a2);
		
	} else {
		// Проверка по первичному кретерию
		return ($sort2dArray_type == 'string')?strcmp($a1, $b1):($b1 - $a1);
		
	}
}

/**
 * Функция генерирует PHP файл с определёнными константами.
 *
 * @param string $_fileName Полное имя файла, который будет 
 * сгенерирован функцией.
 * @param array $_values Ассоциативный массив с именами и значениями 
 * констант, которые необходимо определить.
 */
function generateConf($_fileName, $_values) {
	
	$script = "<?php\n\n";
	
	foreach($_values as $name => $value) {
		$v = is_numeric($value)?$value:('"'.str_replace("\'", "'", addslashes($value)).'"');
		$script .= 'define("'.$name.'", '.$v.');'."\n";
	}
	
	$script .= "\n\n?>";
	
	$fh = fopen($_fileName, 'w');
	if(!$fh) return false;
	
	fwrite($fh, $script);
	fclose($fh);
	
	return true;
	
}

function hideCuts($_text, $_url) {
	
	$sections = explode("<!--cut", $_text);
	if(count($sections) < 2) {
		return $_text;
	}
	
	$shown = $sections[0];
	
	for($i = 1; $i < count($sections); $i++) {
		$section = $sections[$i];
		// Вытаскиваем из фрагмента текст ссылки "Read more", если он задан
		if($section[0] == ':') {
			$parts = explode("-->", $section, 2);
			$more = $parts[0];
			if(strlen($more) > 50) $more = mb_substr($more, 0, MAX_CUT_TEXT_LENGTH).'...';
			$more = substr($more, 1);
		} else {
			$more = 'Read more...';
		}
		
		$shown .= '<a href="'.$_url.'">'.$more.'</a>';
		
		// Ищем таг окончания скрытой секции
		if(strpos($section, '<!--/cut-->') !== false) {
			$parts = explode('<!--/cut-->', $section, 2);
			$shown .= $parts[1];
		}
	}
	
	return $shown;
	
}

?>