<?php

/**
 * Класс выполняет вызов необходимого генератора для получаемых 
 * из ActionFabric данных
 */
class GeneratorFabric {
	
	var $hdr = false;
	var $result = false;
	
	/**
	 * @var strimg Содержит имя action'а, результат которого был обработан генератором.
	 * Имя action'а представляет собой фрагмент имени его класса в нижнем регистре, без 
	 * суффикса Action
	 */
	var $actionName = false;
	
	var $actionOutput = false;
	var $generatorOutput = false;
	
	/**
	 * @var strimg Флаг определяющий необходимость сохранения реферера. Передаёт 
	 * значение флага AbstractAction->returnable
	 */
	var $returnable;
	
	function GeneratorFabric(&$_performedAction) {
		// Сохраняем имя отработанного action'а
		$this->actionName = substr(get_class($_performedAction->action), 0, -6);
		
		switch($_performedAction->resType) {
			case 'html': $genClass = "HtmlGenerator"; break;
			case 'redirect': $genClass = "RedirectGenerator"; break;
			case 'binary': $genClass = "BinaryGenerator"; break;
			case 'xml': $genClass = "XmlGenerator"; break;
			case 'error': $genClass = "ErrorGenerator"; break;
			default:
				if(DEBUG_MODE) {
					// В режиме отладки для всех неопределённых типов данных будет 
					// выполнен вывод дампа всех данных, полученных из action
					$genClass = "DumpGenerator";
				} else {
					// При нормальном режиме работы выполняется вывод сообщения об ошибке
					$this->hdr = "HTTP/1.0 200 Ok\n".
						"Content-Type: text/html; charset=utf-8";
					$this->result = "<h1>Internal error<h1>Bag result type specified in the action.";
					return;
				}
		}
		
		$genFile = DIR_MTK.'generators/'.$genClass.'.class.php';
		
		// Проверка существования файла, соответствующего вызываемому генератору
		if(!file_exists($genFile)) {
			$this->hdr = "HTTP/1.0 200 Ok\n".
				"Content-Type: text/html; charset=utf-8";
			$this->result = "<h1>Internal error</h1><p>Bag content generator specified.</p>";
			return;
		}
		
		// Перенаправления всего stdout.
		// Применимо в основном для перекрытия вывода 
		// сообщений об ошибках парсера
		ob_start();
		
		// Подключаем файл, в котором определён экшн-класс
		require $genFile;
		
		// Проверка существования класса генератора
		if(!class_exists($genClass)) {
			// Для PHP5 в этом месте нужно делать class_exists(..., false),
			// на сколько я понял доки
			$this->hdr = "HTTP/1.0 200 Ok\n".
				"Content-Type: text/html; charset=utf-8";
			$this->result = "<h1>Internal error<h1>Generator class doesn`t exists.";
			return;
		}
		
		// Создаём объект, используя имя класса из переменной
		$gen = new $genClass($_performedAction);
		$this->hdr = $gen->hdr;
		$this->result = $gen->result;
		
		$this->returnable = $gen->returnable;
		
		$this->actionOutput = $_performedAction->output;
		// Сохраняем stdout (если он был) в переменной класса и чистим буффер
		$this->generatorOutput = ob_get_contents();
		ob_end_clean();
		
		return;
	}
	
}

?>