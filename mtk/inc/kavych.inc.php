<?php



function kavych ($contents)
{


// Copyright (c) Spectator.ru
// Код модифицирован для работы с UTF-8 (&copy; motoko.ru)


// замена кавычек в html-тэгах на символ "¬"
$contents=preg_replace ( "/<([^>]*)>/esu", "'<'.str_replace ('\\\"', '¬','\\1').'>'", $contents); 

// замена кавычек внутри <code> на символ "¬"
 $contents=preg_replace ( "/<code>(.*?)<\/code>/esu", "'<code>'.str_replace ('\\\"', '¬','\\1').'</code>'", $contents); 

// расстановка кавычек: кавычка, перед которой идет ( или > или пробел = начало слова,
// кавычка, после которой не идет пробел = это конец слова.

$contents=preg_replace ( "/([>(\s])(\")([^\"]*)([^\s\"(])(\")/u", "\\1«\\3\\4»", $contents); 

// что, остались в тексте нераставленные кавычки? значит есть вложенные!
if (stristr ($contents, '"')):

// расставляем оставшиеся кавычки (еще раз).
$contents=preg_replace ( "/([>(\s])(\")([^\"]*)([^\s\"(])(\")/u", "\\1«\\3\\4»", $contents); 

// расставляем вложенные кавычки
// видим: комбинация из идущих двух подряд открывающихся кавычек без закрывающей
// значит, вторая кавычка - вложенная. меняем ее и идущую после нее, на вложенную (132 и 147)
 while (preg_match ("/(«)([^»]*)(«)/u", $contents)) $contents=preg_replace ( "/(«)([^»]*)(«)([^»]*)(»)/u", "\\1\\2&#132;\\4&#147;", $contents);

// конец вложенным кавычкам
endif;

// кавычки снаружу
$contents = preg_replace("/\<a\s+href([^>]*)\>\s*\«([^<^«^»]*)\»\s*\<\/a\>/u", "&#171;<a href\\1>\\2</a>&#187;", $contents); 

// расстанавливаем правильные коды, тире и многоточия

$contents = str_replace ('«','&laquo;', $contents); 
$contents = str_replace ('»','&raquo;', $contents); 
$contents = str_replace (' - ','&nbsp;&#151; ', $contents); 
$contents = str_replace ('...','&hellip;', $contents); 


// тире в начале строки (диалоги)
$contents = preg_replace ('/([>|\s])- /u',"\\1&#151; ", $contents); 

// меняем  "¬" обратно на кавычки
$contents = str_replace ('¬','"', $contents); 

// предлоги вместе со словом (слово не переносится на другую строку отдельно от предлога)
 $contents = preg_replace("/ (.) (.)/u", " \\1&nbsp;\\2", $contents); 

// дефисы
// $contents = preg_replace("/(\s[^- >]*)-([^ - >]*\s)/", "<nobr>\\1-\\2</nobr>", $contents); 


return $contents;

}

?>