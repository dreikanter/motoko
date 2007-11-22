<?php

// Класс-оболочка для запускания action'ов

class ActionFabric {
	
	var $actionClassName = false;
	
	var $resType = false;
	var $resData = array();
	var $output = false;
	
	var $returnable = false;
	var $action = false;
	
	var $actionName = false;
	
	
	/**
	 * Имя темплейта, необходимого для оформления данных, выдаваемых action-ом. 
	 * Переменная используется только при генерации HTML страниц (resType == 'html').
	 * Значение переменной копируется из исполняемого action-а.
	 */
	var $template = false;
	
	function ActionFabric($_request) {
		$this->actionClassName = $_request->action;
		
		$actionFile = DIR_MTK.'actions/'.
			$this->actionClassName.'.class.php';
		
		$this->actionName = strtolower(substr($this->actionClassName, 0, -6));
		
		$resType = false;
		$resData = array();
		
		// Проверка существования файла, соответствующего вызываемому экшену
		if(!file_exists($actionFile)) {
			$this->resType = 'error';
			$this->resData = array('error_msg' => 'Bad action.');
			return;
		}
		
		// Перенаправления всего stdout.
		// Применимо в основном для перекрытия вывода 
		// сообщений об ошибках парсера
		ob_start();
		
		// Подключаем файл, в котором определён экшн-класс
		require $actionFile;
		
		// Проверка существования экшен-класса
		if(!class_exists($this->actionClassName)) {
			// Для PHP5 в этом месте нужно делать class_exists(..., false),
			// на сколько я понял доки
			$this->resType = 'error';
			$this->resData = array('error_msg' => 'Action class doesn`t exists.');
			return;
		}
		
		// Создаём объект, испольхуя имя класса из переменной
		$this->action = new $this->actionClassName($_request->params);
		
		if(!$this->action->paramsOk()) {
			// Параметры заданы неверно
			$this->resType = 'error';
			$this->resData = array('error_msg' => $this->action->errorMsg);
			return;
		}
		
		// Параметры корректны, запускаем экшн
		$this->action->execute();
		
		// Копируем результат работы action-а во внутренние переменные ActionFabric
		$this->resType = $this->action->resType;
		$this->resData = $this->action->resData;
		
		// Если при работе action`а произошла ошибка, возвращаться на него нельзя. Если 
		// action в конце работы выполняет редирект, возвращаться на текущий URL тоже нельзя. 
		// Дополнительная проверка выполнена для увеличения безопасности работы редиректа 
		// на страницу назад, на тот случай, если внутри action'а неправильно задано 
		// значение returnable
		if($this->resType != 'error' && $this->resType != 'redirect')
			$this->returnable = $this->action->returnable;
		
		if($this->action->resType == 'html') $this->template = $this->action->template;
		
		// Сохраняем stdout (если он был) в переменной класса
		// и чистим буффер
		$this->output = ob_get_contents();
		ob_end_clean();
		
		return;
	}
	
}

?>