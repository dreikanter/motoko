<?php

require DIR_MTK.'inc/statlib.inc.php';

$statDir = DIR_DATA.'stat/';

if(!is_dir($statDir)) mkdir($statDir);
if(!is_dir($statDir.'archive')) mkdir($statDir.'archive');

// $today - ������, � ������� �������� ���������� �� ����������� �� ������� ����
$today = readData($statDir.'today.txt');
if(!is_array($today) || ($dayPassed = $today[ST_TODAY] != date('Ymd'))) {
	
	// ���� ���������� ����, ��������� ���� � ������� ����������� � �����
	if($dayPassed) {
		// ��������� � ����� ��������� ��������� �� ��������� ����
		writeData($statDir.'archive/'.$today[ST_TODAY].'.txt', $today);
	}
	
	// ���������� �������� �������� ���, ���� 
	// - ���������� ���� ���;
	// - ������ ����������;
	// - ������ ������
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

// ������ �� ����������� �� ������ ������ �� ������� ������ ������
$total = readData($statDir.'total.txt');

// ���� ������ ���������� ��� ���� �� ���������
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

// ������������� ������ � ����� �� ����� �� ����������� ����
if(!isset($today[ST_HOURS][$hr])) 
	$today[ST_HOURS][$hr] = array(ST_HOSTS => 0, ST_HITS => 0);

// ������������� ������ � ����� �� ����� �� �� ����� ������ ����������
if(!isset($total[ST_TOTAL_HOURS][$hr])) 
	$total[ST_TOTAL_HOURS][$hr] = array(ST_HOSTS => 0, ST_HITS => 0);

// ������� ����
if(!in_array($ip, $today[ST_IPS])) {
	$today[ST_IPS][] = $ip;
	$today[ST_HOSTS]++;
	$today[ST_HOURS][$hr][ST_HOSTS]++;
	
	$total[ST_TOTAL_HOSTS]++;
	$total[ST_TOTAL_HOURS][$hr][ST_HOSTS]++;
}

// ������� �����
$today[ST_HITS]++;
$today[ST_HOURS][$hr][ST_HITS]++;

$total[ST_TOTAL_HITS]++;
$total[ST_TOTAL_HOURS][$hr][ST_HITS]++;

// �������

// URL ����������� ��������
$url = substr(getenv('REQUEST_URI'), strlen(URL_ROOT));

// �������� get �������� session id, ���� �� ����
$po = strpos($url, '?PHPSESSID=');
if($po !== false) $url = substr($po, 0, $po);

// ������ ��� ������� �������� �������� ������� �������� �������
// (��� ������� URL ����������� array(0 => ��������� ����� ������, 
// 1 => ���������� ���������))
if(in_array($url, $today[ST_TIMING_URLS])) {
	$today[ST_TIMING_URLS][(string)$url][0] += STAT_GT;
	$today[ST_TIMING_URLS][(string)$url][1]++; 
} else {
	$today[ST_TIMING_URLS][$url] = array(STAT_GT, 1);
}

// ������ ��� ������� �������� �������� ������� ������ action'��
// (��� ������� action ����������� array(0 => ��������� ����� ������, 
// 1 => ���������� ���������))
if(in_array(STAT_ACTION, $today[ST_TIMING_ACTIONS])) {
	$today[ST_TIMING_ACTIONS][(string)STAT_ACTION ][0] += STAT_GT;
	$today[ST_TIMING_ACTIONS][(string)STAT_ACTION][1]++; 
} else {
	$today[ST_TIMING_ACTIONS][STAT_ACTION] = array(STAT_GT, 1);
}

// ������ ��� ����������� �������, ���������� ����� action-��
$today[ST_TIMING][] = array(time(), STAT_ACTION, (string)$url, STAT_GT);

writeData($statDir.'today.txt', $today);
writeData($statDir.'total.txt', $total);

?>