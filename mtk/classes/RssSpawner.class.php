<?php

/*
http://blogs.law.harvard.edu/tech/rss

В ходе генерации RSS выполняется проверка соответствия структуры XML документа стандарту RSS2. Проверка корректности данных не осуществляется. Это означает, что, напрмиер, корректность дат записей в ленте необходимо обеспечивать вне класса.

*/

class RssSpawner {
	
	/**
	 * @var string $rssTitle Заголовок RSS ленты (элемент rss/channel/title)
	 */
	var $rssTitle = false;
	
	/**
	 * @var string $rssLink URL ленты (элемент rss/channel/link)
	 */
	var $rssLink = false;
	
	/**
	 * @var string $rssDescription Описание ленты (элемент rss/channel/description)
	 */
	var $rssDescription = false;
	
	/**
	 * @var array $itemElements Массив, содержащий элементы контейнера channel. С его помощью 
	 * выполняется проверка имён вложенных XML-элементов на соответствие спецификации RSS.
	 */
	var $channelElements = array(
			'language' => false, 
			'copyright' => false, 
			'managingEditor' => false, 
			'webMaster' => false, 
			'pubDate' => false, 
			'lastBuildDate' => false, 
			'category' => false, 
			'category.domain' => false, 
			'generator' => false, 
			'docs' => false, 
			'cloud.domain' => false, 
			'cloud.port' => false, 
			'cloud.path' => false, 
			'cloud.registerProcedure' => false, 
			'cloud.protocol' => false, 
			'ttl' => false, 
			'image/url' => false, // RQ
			'image/title' => false, //RQ
			'image/link' => false, // RQ
			'image/width' => false,
			'image/height' => false,
			'image/description' => false, 
			'rating' => false, 
			'textInput/title' => false, // RQ 
			'textInput/description' => false, // RQ 
			'textInput/name' => false, // RQ 
			'textInput/link' => false, // RQ 
			'skipHours' => false, 
			'skipDays' => false
		);
	
	/**
	 * @var array $itemElements Массив, содержащий болванку контейнера item. С его помощью 
	 * выполняется проверка имён вложенных XML-элементов на соответствие спецификации RSS.
	 */
	var $itemElements = array(
			'title' => false, 
			'link' => false, 
			'description' => false, 
			'author' => false, 
			'category' => false, 
			'category.domain' => false, 
			'comments' => false, 
			'enclosure.url' => false, 
			'enclosure.length' => false, 
			'enclosure.type' => false, 
			'guid' => false, 
			'guid.isPermaLink' => false, 
			'pubDate' => false, 
			'source' => false, 
			'source.url' => false
		);
	
	/**
	 * @var array $items ассоциативный массив для хранения контента контейнеров item
	 */
	var $items = array();
	
	/**
	 * @var string $contentChanged Флаг, определяющий необходимость повторной генерации RSS. 
	 * Сбрасывается после каждого вызова методов setChannelOption и AddItem. Изначально 
	 * так же сброшен.
	 */
	var $contentChanged = true;
	
	/**
	 * @var string $rss Буффер для хранения результата работы метода 
	 * getRss (сгенерированный код RSS ленты)
	 */
	var $rss;
	
	/**
	 * Конструктор класса. При создании нового объекта, задаются значения 
	 * обязательных элементов контейнера chanel. При необходимости, эти значения 
	 * можно откорректировать позже, через переменные класса $rssTitle, $rssLink 
	 * и $rssDescription. Необязательные элементы можно задавать с помощью 
	 * метода setChannelOption.
	 *
	 * @param string $_title Заголовок RSS ленты (элемент rss/channel/title)
	 * @param string $_link URL ленты (элемент rss/channel/link)
	 * @param string $_desc Описание ленты (элемент rss/channel/description)
	 */
	function RssSpawner($_title, $_link, $_desc) {
		$this->rssTitle = $_title;
		$this->rssLink = $_link;
		$this->rssDescription = $_desc;
	}
	
	/**
	 * Добавляет значения опциональных элементов контейнера channel. При 
	 * повторном определении, значения элементов переопределяется.
	 *
	 * Примечание: значения полей pubDate и lastBuildDate необходимо задавать 
	 * в формате unixtime. В ходе генерации XML они будут автоматически преобразованы 
	 * к нужному формату.
	 *
	 * @param string $_name Имя вложенного аргумента контейнера chanel
	 * @param string $_value Значение элемента (в зависимости от типа, 
	 * это может быть значение элемента-контейнера, вложенного контейнера 
	 * или одного из его атрибутов)
	 * @return bool Возвращает true или false соответственно при успешном 
	 * срабатывании метода или при возникновении какой дибо ошибки (если 
	 * задано некорректное имя вложенного элемента контейнера chanel)
	 */
	function setChannelOption($_name, $_value) {
		
		// Имя устанавливаемого элемента должно соответствовать возсожным вариантам
		if(!isset($this->channelElements[$_name])) return false;
		
		$this->channelElements[$_name] = $_value;
		
		// Вызов метода изменил данные RSS, поэтому потребуется повторная (или первичная) 
		// генерация кода ленты.
		$this->contentChanged = true;
		
		return true;
		
	}
	
	/**
	 * Добавляет в channel новый контейнер item.
	 *
	 * Примечание: значение поля pubDate необходимо задавать в формате unixtime. В ходе 
	 * генерации XML оно будет автоматически преобразовано к нужному формату.
	 *
	 * @param array $_item Ассоциативный массив, содержащий значения вложенных 
	 * элементов контейнера item. В ходе работы метода, имена этих элементов 
	 * проверяются на соответствие стандарту RSS, и добавление некорректных элементов 
	 * не происходит. В массиве должен быть задан как минимум один элемент (согласно 
	 * спецификации, все элементы необязательны, но контейнер item не должен быть пустым)
	 * @return bool В случае, если не задан ни один элемент контейнера item, 
	 * или все элементы некорректны, возвращает false. При успешном добавлении
	 * записи в ленту, возвращает true. 
	 */
	function AddItem($_item) {
		
		if(!is_array($_item)) return false;
		
		$item = array();
		
		// Проверяем валидность имён заданных элементов для контейнера item
		foreach($_item as $element => $value) {
			if(!isset($this->itemElements[$element])) continue;
			$item[$element] = $value;
		}
		
		// Все вложенные элементы контейнера item необязательны. Тем не менее, 
		// должен быть задан как минимум один из них
		if(!count($item)) return false;
		
		// Добавляем новую запись
		$this->items[] = $item;
		
		// Вызов метода изменил данные RSS, поэтому потребуется повторная (или первичная) 
		// генерация кода ленты.
		$this->contentChanged = true;
		
		return true;
		
	}
	
	/**
	 * Сортирует записи в ленте согласно значению элемента pubDate. Для корректной 
	 * сортировки, в каждой записи должно быть задано это значение.
	 */
	function sortItems() {
		
		// Вызов метода изменил данные RSS, поэтому потребуется повторная (или первичная) 
		// генерация кода ленты.
		$this->contentChanged = true;
		
		return true;
		
	}
	
	/**
	 * Генерирует и возвращает RSS на основании заданных данных
	 *
	 * @param bool $_f Опциональный параметр, определяющий 
	 * необходимость форматировать генерируемый методом XML код.
	 */
	function getRss($_f = true) {
		
		// В случае, если код ленты уже был сгенерирован и контент 
		// не изменился с момента последнего вызова метода, отдаём 
		// уже готовый XML код, вместо того, чтобы генерировать его заново.
		if(!$this->contentChanged) return $this->rss;
		
		// Функция выдаёт последовательность из символа переноса строки 
		// и серии пробельных символов ($this->sep) заданной длины
		function cr($_indentLength, $_enabled = true) {
			if(!$_enabled) return '';
			return "\n".str_repeat("\t", $_indentLength);
		}
		
		$rss = '<?xml version="1.0"?>'.cr(0, $_f).'<rss version="2.0">'.
			cr(1, $_f).'<channel>';
		
		$rss .= cr(2, $_f).'<title>'.$this->rssTitle.'</title>';
		$rss .= cr(2, $_f).'<link>'.$this->rssLink.'</link>';
		$rss .= cr(2, $_f).'<description>'.$this->rssDescription.'</description>';
		
		if($this->channelElements['language'])
			$rss .= cr(2, $_f).'<language>'.
				$this->channelElements['language'].'</language>';
		
		if($this->channelElements['copyright'])
			$rss .= cr(2, $_f).'<copyright>'.
				$this->channelElements['copyright'].'</copyright>';
		
		if($this->channelElements['managingEditor'])
			$rss .= cr(2, $_f).'<managingEditor>'.
				$this->channelElements['managingEditor'].'</managingEditor>';
		
		if($this->channelElements['webMaster'])
			$rss .= cr(2, $_f).'<webMaster>'.
				$this->channelElements['webMaster'].'</webMaster>';
		
		if($this->channelElements['pubDate']) {
			$d = $this->channelElements['pubDate'];
			// Дату можно задавать в формате unixtime. В XML она 
			// заносится в отформатированном виде
			if(is_numeric($d)) $d = date('r', $d);
			$rss .= cr(2, $_f).'<pubDate>'.$d.'</pubDate>';
		}
		
		if($this->channelElements['lastBuildDate']) {
			$d = $this->channelElements['lastBuildDate'];
			if(is_numeric($d)) $d = date('r', $d);
			$rss .= cr(2, $_f).'<lastBuildDate>'.$d.'</lastBuildDate>';
		}
		
		if($this->channelElements['category']) {
			$rss .= cr(2, $_f).'<category';
			$rss .= isset($item['category.domain'])?
				(' domain="'.$item['category.domain'].'"'):'';
			$rss .= '>'.$this->channelElements['category'].'</category>';
		}
		
		if($this->channelElements['generator'])
			$rss .= cr(2, $_f).'<generator>'.
				$this->channelElements['generator'].'</generator>';
		
		if($this->channelElements['docs'])
			$rss .= cr(2, $_f).'<docs>'.
				$this->channelElements['docs'].'</docs>';
		
		if($this->channelElements['ttl'])
			$rss .= cr(2, $_f).'<ttl>'.
				$this->channelElements['ttl'].'</ttl>';
		
		if($this->channelElements['rating'])
			$rss .= cr(2, $_f).'<rating>'.
				$this->channelElements['rating'].'</rating>';
		
		if($this->channelElements['skipHours'])
			$rss .= cr(2, $_f).'<skipHours>'.
				$this->channelElements['skipHours'];
		
		if($this->channelElements['skipDays'])
			$rss .= cr(2, $_f).'<skipDays>'.
				$this->channelElements['skipDays'].'</skipDays>';
		
		// <cloud>
		if($this->channelElements['cloud.domain']) {
			$rss .= cr(2, $_f).'<cloud domain="'.$this->channelElements['cloud.domain'].
				'" port="'.$this->channelElements['cloud.port'].
				'" path="'.$this->channelElements['cloud.path'].
				'" registerProcedure="'.$this->channelElements['cloud.registerProcedure'].
				'" protocol="'.$this->channelElements['cloud.protocol'].'" />';
		}
		
		// <image>
		if($this->channelElements['image/url']) {
			// Обязательные вложенные элементы
			$rss .= cr(2, $_f).'<image>';
			$rss .= cr(3, $_f).'<url>'.
				$this->channelElements['image/url'].'</url>';
			$rss .= cr(3, $_f).'<title>'.
				$this->channelElements['image/title'].'</title>';
			$rss .= cr(3, $_f).'<link>'.
				$this->channelElements['image/link'].'</link>';
			
			// Опциональные вложенные элементы
			if($this->channelElements['image/width'])
				$rss .= cr(3, $_f).'<width>'.
					$this->channelElements['image/width'].'</width>';
			
			if($this->channelElements['image/height'])
				$rss .= cr(3, $_f).'<height>'.
					$this->channelElements['image/height'].'</height>';
			
			if($this->channelElements['image/description'])
				$rss .= cr(3, $_f).'<description>'.
					$this->channelElements['image/description'].'</description>';
			
			$rss .= cr(2, $_f).'</image>';
		}
		
		// <textInput>
		if($this->channelElements['textInput/title']) {
			$rss .= cr(2, $_f).'<textInput>';
			// Обязательные вложенные элементы
			$rss .= cr(3, $_f).'<title>'.
				$this->channelElements['textInput/title'].'</title>';
			$rss .= cr(3, $_f).'<description>'.
				$this->channelElements['textInput/description'].'</description>';
			$rss .= cr(3, $_f).'<name>'.
				$this->channelElements['textInput/name'].'</name>';
			$rss .= cr(3, $_f).'<link>'.
				$this->channelElements['textInput/link'].'</link>';
			
			$rss .= cr(2, $_f).'</textInput>';
		}
		
		// Генерируем контейнеры item
		foreach($this->items as $item) {
			$rss .= cr(2, $_f).'<item>';
			
			if(isset($item['title']))
				$rss .= cr(3, $_f).'<title>'.$item['title'].'</title>';
			
			if(isset($item['link']))
				$rss .= cr(3, $_f).'<link>'.$item['link'].'</link>';
			
			if(isset($item['description']))
				$rss .= cr(3, $_f).'<description><![CDATA['.
					$item['description'].']]></description>';
			
			if(isset($item['author']))
				$rss .= cr(3, $_f).'<author>'.
					$item['author'].'</author>';
			
			if(isset($item['category'])) {
				$rss .= cr(3, $_f).'<category';
				// Необязательный аттрибут category.domain
				$rss .= isset($item['category.domain'])?
					(' domain="'.$item['category.domain'].'"'):'';
				$rss .= '>'.$item['category'].'</category>';
			}
			
			if(isset($item['enclosure.url'])) {
				$rss .= cr(3, $_f).'<enclosure url="'.
					$item['enclosure.url'].'" domain="'.$item['enclosure.length'].'"'.
					'  type="'.$item['enclosure.type'].'" />';
			}
			
			if(isset($item['comments']))
				$rss .= cr(3, $_f).'<comments>'.
					$item['comments'].'</comments>';
			
			if(isset($item['guid'])) {
				$rss .= cr(3, $_f).'<guid';
				$rss .= isset($item['guid.isPermaLink'])?
					(' isPermaLink="'.$item['guid.isPermaLink'].'"'):'';
				$rss .= '>'.$item['guid'].'</guid>';
			}
			
			if(isset($item['pubDate']))
				$rss .= cr(3, $_f).'<pubDate>'.
					date('r', $item['pubDate']).'</pubDate>';
			
			if(isset($item['source'])) {
				$rss .= cr(3, $_f).'<source';
				$rss .= isset($item['source.url'])?(' url="'.$item['source.url'].'"'):'';
				$rss .= '>'.$item['source'].'</source>';
			}
			
			$rss .= cr(2, $_f).'</item>';
		}
		
		$rss .= cr(1, $_f).'</channel>'.cr(0, $_f).'</rss>';
		
		$this->rss = $rss;
		$this->contentChanged = false;
		
		return $rss;
		str_repeat($this->sep);
	}
	
}

?>