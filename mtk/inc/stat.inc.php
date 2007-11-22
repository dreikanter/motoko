<?php

require DIR_MTK.'inc/statlib.inc.php';

$statDir = DIR_DATA.'stat/';

if(!is_dir($statDir)) mkdir($statDir);
if(!is_dir($statDir.'archive')) mkdir($statDir.'archive');

// $today - массив, в котором хранится статистика по посетителям за текущий день
$today = readData($statDir.'today.txt');
if(!is_array($today) || ($dayPassed = $today[ST_TODAY] != date('Ymd'))) {
	
	// Если изменилась дата, перенести файл с дневной статистикой в архив
	if($dayPassed) {
		// Сохраняем в архив показания счётчиков за прошедший день
		writeData($statDir.'archive/'.$today[ST_TODAY].'.txt', $today);
	}
	
	// Сбрасываем счётчики текущего дня, если 
	// - изменилась дата или;
	// - данные повреждены;
	// - первый запуск
	$today = array(
			ST_TODAY => date('Ymd'), 
			ST_HOSTS => 0, 
			ST_HITS => 0, 
			ST_HOURS => array(),
			ST_IPS => array(),
			ST_FROM => time(),
			ST_TIMING => array(),
			ST_TIMING_URLS => array(),
			ST_TIMING_ACTIONS => array()
		);
}

// Массив со статистикой за период работы со времени сброса данных
$total = readData($statDir.'total.txt');

// Если данные повреждены или файл не сущетвует
if(!$total) {
	$total = array(
			ST_TOTAL_SINCE => time(),
			ST_TOTAL_HOSTS => 0, 
			ST_TOTAL_HITS => 0,
			ST_TOTAL_HOURS => array(),
		);
}

$ip = getenv('REMOTE_ADDR');
$hr = date('H');

// Распределение хостов и хитов по часам за сегодняшний день
if(!isset($today[ST_HOURS][$hr])) 
	$today[ST_HOURS][$hr] = array(ST_HOSTS => 0, ST_HITS => 0);

// Распределение хостов и хитов по часам за всё время работы статистики
if(!isset($total[ST_TOTAL_HOURS][$hr])) 
	$total[ST_TOTAL_HOURS][$hr] = array(ST_HOSTS => 0, ST_HITS => 0);

// Считаем хиты
if(!in_array($ip, $today[ST_IPS])) {
	$today[ST_IPS][] = $ip;
	$today[ST_HOSTS]++;
	$today[ST_HOURS][$hr][ST_HOSTS]++;
	
	$total[ST_TOTAL_HOSTS]++;
	$total[ST_TOTAL_HOURS][$hr][ST_HOSTS]++;
}

// Считаем хосты
$today[ST_HITS]++;
$today[ST_HOURS][$hr][ST_HITS]++;

$total[ST_TOTAL_HITS]++;
$total[ST_TOTAL_HOURS][$hr][ST_HITS]++;

// Тайминг

// URL запрошенной страницы
$url = substr(getenv('REQUEST_URI'), strlen(URL_ROOT));

// Обрезаем get параметр session id, если он есть
$po = strpos($url, '?PHPSESSID=');
if($po !== false) $url = substr($po, 0, $po);

// Массив для расчёта среднего значения времени генеации страниц
// (для каждого URL сохраняется array(0 => суммарное время работы, 
// 1 => количество обращений))
if(in_array($url, $today[ST_TIMING_URLS])) {
	$today[ST_TIMING_URLS][(string)$url][0] += STAT_GT;
	$today[ST_TIMING_URLS][(string)$url][1]++; 
} else {
	$today[ST_TIMING_URLS][$url] = array(STAT_GT, 1);
}

// Массив для расчёта среднего значения времени работы action'ов
// (для каждого action сохраняется array(0 => суммарное время работы, 
// 1 => количество обращений))
if(in_array(STAT_ACTION, $today[ST_TIMING_ACTIONS])) {
	$today[ST_TIMING_ACTIONS][(string)STAT_ACTION ][0] += STAT_GT;
	$today[ST_TIMING_ACTIONS][(string)STAT_ACTION][1]++; 
} else {
	$today[ST_TIMING_ACTIONS][STAT_ACTION] = array(STAT_GT, 1);
}

// Полный лог запрошенных страниц, включающий имена action-ов
$today[ST_TIMING][] = array(time(), STAT_ACTION, (string)$url, STAT_GT);

writeData($statDir.'today.txt', $today);
writeData($statDir.'total.txt', $total);

?>