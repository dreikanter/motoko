<?php

// Функции, используемые в теме

/**
 * Форматирует unix timestamp в соответствии с заданным 
 * форматом (врапер функции date()). 
 * Параметры: format (шаблон форматирования), time (юниксовое время),
 * linked (определяет необходимость сделать гиперлинки для значения года, 
 * месяца и числа).
 */
function insert_date($params, &$tpl) {
	if(isset($params['link']) && $params['link']) {
		// Необходимо линковать знчения года, месяца и дня 
		// к соответствующим архивным страницам
		$pattern = strtr($params['format'], array("d" => "%1%", "F" => "%2%", "j" => "%3%", 
			"m" => "%4%", "M" => "%5%", "n" => "%6%", "Y" => "%7%", "y" => "%8%"));
		$ts = $params['time'];
		$result = date($pattern, $ts);
		
		$yLink = URL_ROOT.date("Y", $ts);
		$mLink = $yLink.'/'.date("d", $ts);
		$dLink = $mLink.'/'.date("m", $ts);
		$yLink = '<a href="'.$yLink.'">';
		$mLink = '<a href="'.$mLink.'">';
		$dLink = '<a href="'.$dLink.'">';
		
		return strtr($result, array(
				"%1%" => $dLink.date("d", $ts).'</a>',
				"%2%" => $mLink.date("F", $ts).'</a>',
				"%3%" => $dLink.date("j", $ts).'</a>', 
				"%4%" => $mLink.date("m", $ts).'</a>', 
				"%5%" => $mLink.date("M", $ts).'</a>', 
				"%6%" => $mLink.date("n", $ts).'</a>', 
				"%7%" => $yLink.date("Y", $ts).'</a>', 
				"%8%" => $yLink.date("y", $ts).'</a>'
			));
		
	}
	return date($params['format'], $params['time']);
}

/**
 * Врапер функции formatWeight (форматирует объём данных).
 * Параметры: weight (int) Размер файла, который необходимо 
 * представить в читаемой форме.
 */
function insert_format_weight($params, &$tpl) {
	return formatWeight($params['weight'], 1);
}

/**
 * Преобразует массив тагов в линкованный список через 
 * запятую (строку).
 * Параметры: tags (массив с названиями тагов)
 */
function insert_tag_list($params, &$tpl) {
	$tags = $params['tags'];
	if(is_array($params['atags']) && count($params['atags'])) {
		$actTags = array();
		foreach($params['atags'] as $tag) $actTags[] = $tag['tag'];
	} else {
		$actTags = false;
	}
	
	if(!count($tags)) return '<span class="silver">none</span>';
	
	foreach($tags as $num => $tag) {
		$act = ($actTags && in_array($tag, $actTags))?' class="actual_tag"':'';
		$tags[$num] = '<a href="'.URL_ROOT.'tags/'.$tag.'"'.$act.'>'.$tag.'</a>';
	}
	
	return implode(", ", $tags);
}

/**
 * Generation time
 */
function insert_gt($params, &$tpl) {
	global $START_TIME;
	return round(getMicroTime() - $START_TIME, 2);
}

// last.fm weekly chart update
define("LASTFM_USER_NAME", "dreikanter");

define("LASTFM_URL_CHART", "http://ws.audioscrobbler.com/1.0/user/".LASTFM_USER_NAME."/weeklyalbumchart.xml");
define("LASTFM_URL_ALBUM", "http://ws.audioscrobbler.com/1.0/album/%title%/%album%/info.xml");

define("LASTFM_CHART_CACHE_DIR", DIR_DATA."/last.fm/");
define("LASTFM_CHART_CACHE", LASTFM_CHART_CACHE_DIR.LASTFM_USER_NAME.".txt");
define("LASTFM_CHART_CACHE_HTML", LASTFM_CHART_CACHE_DIR.LASTFM_USER_NAME.".html");
define("LASTFM_CACHE_LIFETIME", 43200); // seconds

// General purpose XML parsing function

class XmlElement {
  var $name;
  var $attributes;
  var $content;
  var $children;
};

function xml_to_object($xml) {
  $parser = xml_parser_create();
  xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, 0);
  xml_parser_set_option($parser, XML_OPTION_SKIP_WHITE, 1);
  xml_parse_into_struct($parser, $xml, $tags);
  xml_parser_free($parser);

  $elements = array();  // the currently filling [child] XmlElement array
  $stack = array();
  foreach ($tags as $tag) {
    $index = count($elements);
    if ($tag['type'] == "complete" || $tag['type'] == "open") {
      $elements[$index] = new XmlElement;
      $elements[$index]->name = $tag['tag'];
      $elements[$index]->attributes = $tag['attributes'];
      $elements[$index]->content = $tag['value'];
      if ($tag['type'] == "open") {  // push
        $elements[$index]->children = array();
        $stack[count($stack)] = &$elements;
        $elements = &$elements[$index]->children;
      }
    }
    if ($tag['type'] == "close") {  // pop
      $elements = &$stack[count($stack) - 1];
      unset($stack[count($stack) - 1]);
    }
  }
  return $elements[0];  // the single top-level element
}

// last.fm chart cache updater
function updateLastfmChart() {
	if(file_exists(LASTFM_CHART_CACHE) && abs(time() - filemtime(LASTFM_CHART_CACHE)) < LASTFM_CACHE_LIFETIME) return true;
	if(!is_dir(LASTFM_CHART_CACHE_DIR)) mkdir(LASTFM_CHART_CACHE_DIR);
	
	@$xml = file_get_contents(LASTFM_URL_CHART);
	if(!$xml) return false;
	@$xml = xml_to_object($xml);
	if(!$xml) return false;
	
	$albums = array();
	
	foreach($xml->children as $album) {
		$title = $album->children[0]->content;
		$title = str_replace('&', 'and', $title);
		$artist = $album->children[1]->content;
		$artist = str_replace('&', 'and', $artist);
		$album_url = $album->children[5]->content;
		
		$url = strtr(LASTFM_URL_ALBUM, array('%title%' => urlencode($title), '%album%' => urlencode($artist)));
		@$xml = file_get_contents($url);
		@$xml = xml_to_object($xml);
		if(!$xml) return false;
		
		$albums[] = array(
				'artist' => $artist, 
				'title' => $title, 
				'img_url0' => $xml->children[3]->children[0]->content, 
				'img_url1' => $xml->children[3]->children[1]->content, 
				'img_url2' => $xml->children[3]->children[2]->content, 
				'url' => $album_url
			);
	}
	
	$fh = fopen(LASTFM_CHART_CACHE, "w");
	fwrite($fh, serialize($albums));
	fclose($fh);
	
	$html = '';
	foreach($albums as $album) {
		$img_title = $album['artist'].' - '.$album['title'];
		$html .= '<a href="'.$album['url'].'" title="'.$img_title.'"><img src="'.$album['img_url0'].
			'" width="50" height="50" alt="'.$img_title.'" style="margin:2mm;font-size:2mm;font-family:sans-serif;border:0;"/></a> ';
	}
	
	$fh = fopen(LASTFM_CHART_CACHE_HTML, "w");
	fwrite($fh, $html);
	fclose($fh);
	
	return true;
}

$res = updateLastfmChart();
//if(!$res) trigger_error();

function insert_lastfm_chart($params, &$tpl) {
	if(!file_exists(LASTFM_CHART_CACHE_HTML) || !filesize(LASTFM_CHART_CACHE_HTML)) return '';
	return file_get_contents(LASTFM_CHART_CACHE_HTML);
}

?>