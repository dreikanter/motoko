<?php

require 'HTML/QuickForm.php';

/**
 * Выполняет восстановление БД из файлов.
 * Перед запуском требует от пользователя подтверждения.
 * Параметры: timestamp (int) дата создания нужного бэкапа в формате UNIX time, 
 * confirmed (значение 1 говорит о том, что подтверждение получено)
 */
class RestoreAction extends AbstractAction {
	
	var $returnable = false;
	var $template = 'form';
	
	var $form;
	
	function RestoreAction($_params) {
		// Конструктор абстрактного класса
		$this->AbstractAction($_params);
		// Создаём форму
		$this->form = new HTML_QuickForm('restore_cfm_form', 'post', 
			getenv("SCRIPT_NAME"), false, false, true);
	}
	
	function paramsOk() {
		
		if((isset($this->params['confirmed']) && !is_numeric($this->params['confirmed'])) ||
			(isset($this->params['timestamp']) && !is_numeric($this->params['timestamp']))) {
			return false;
		}
		
		return true;
		
	}
	
	function buildForm() {
		// Код конструирования формы выделен в отдельный метод
		// для разгрузки $this->execute()
		$this->form->addElement('hidden', 'action', substr(basename(__FILE__), 0, -16)); // 'restore'
		$this->form->addElement('hidden', 'confirmed', 1);
		$this->form->addElement('hidden', 'timestamp', $this->params['timestamp']);
		
		$buttons = array();
		// Кнопка сабмита формы
		$buttons[] = &HTML_QuickForm::createElement('submit', '', 'Perform DB restore', 
			array('class' => 'green_submit'));
		$buttons[] = &HTML_QuickForm::createElement('button', '', 'Cancel', 
			array('class' => 'gray_submit', 'onclick' => 
				'javascript:window.location.href = \''.$_SESSION['ref'].'\';'));
		$this->form->addGroup($buttons);
		
	}
	
	function execute() {
		global $BDB;
		
		// Проверяем право пользователя удалять таги
		if(!$BDB->CanManageBackups()) {
			$this->resType = 'error';
			$this->resData = array('error_msg' => 
				'User have no access rights to perform database restore from backup.');
			return;
		}
		
		$this->buildForm();
		
		if($this->form->validate()) {
			
			if((bool)$this->params['confirmed']) {
				
				// Выполняем предварительное резервирование данных, на случай сбоя 
				// при восстановлении из бэкапа
				$result = $BDB->Backup();
				if(!$result) {
					$this->resType = 'error';
					$this->resData = array('error_msg' => $BDB->errorMsg);
					return;
				}
				
				// Если подтверждение получено, выполняем восстановление данных
				$result = $BDB->Restore($this->params['timestamp']);
				if(!$result) {
					$this->resType = 'error';
					$this->resData = array('error_msg' => $BDB->errorMsg);
					return;
				}
				
				// Если всё хорошо, делаем редирект
				$systemMessage = 'Database successfully restored.';
				
			}
			
			// После восстанвления базы, пользователь редиректится в корень блога
			$this->resType = 'redirect';
			$this->resData = array('to' => URL_ROOT);
			
			// Если определено сообщение, передаём его в темплейт
			if(isset($systemMessage)) {
				$this->resData['system_message'] = $systemMessage;
			}
			
		} else {
			
			// Выдаём форму для подтвержения
			$this->resType = 'html';
			$this->resData = array(
					'form_title' => 'DB restore confirmation',
					'form_desc' => 'Database restoring is resource-intensive action '.
						'and can take several minutes. Are you sure to proceed?<br><small>'.
						'Note: current database content will be automatically stored into the new backup.</small>',
					'form' => $this->form->toHtml()
				);
			
		}
		
	}

}

?>