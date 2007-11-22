<?php

require 'HTML/QuickForm.php';

/**
 * Выполняет сохранение всего содержимого БД в файлы.
 * Перед запуском требует от пользователя подтверждения.
 * Параметры: confirmed (значение 1 говорит о том, 
 * что подтверждение получено)
 */
class BackupAction extends AbstractAction {
	
	var $returnable = false;
	var $template = 'form';
	
	var $form;
	
	function BackupAction($_params) {
		// Конструктор абстрактного класса
		$this->AbstractAction($_params);
		// Создаём форму
		$this->form = new HTML_QuickForm('backup_cfm_form', 'post', 
			getenv("SCRIPT_NAME"), false, false, true);
	}
	
	function paramsOk() {
		
		if(isset($this->params['confirmed']) && !is_numeric($this->params['confirmed'])) {
			return false;
		}
		
		return true;
		
	}
	
	function buildForm() {
		// Код конструирования формы выделен в отдельный метод
		// для разгрузки $this->execute()
		$this->form->addElement('hidden', 'action', substr(basename(__FILE__), 0, -16)); // 'backup'
		$this->form->addElement('hidden', 'confirmed', 1);
		
		$buttons = array();
		// Кнопка сабмита формы
		$buttons[] = &HTML_QuickForm::createElement('submit', '', 'Perform DB backup', 
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
				'User have no access rights to perform database backup.');
			return;
		}
		
		$this->buildForm();
		
		if($this->form->validate()) {
			
			if((bool)$this->params['confirmed']) {
				
				// Если подтверждение получено, выполняем бэкап
				$result = $BDB->Backup();
				if(!$result) {
					$this->resType = 'error';
					$this->resData = $BDB->errorMsg;
				}
				
				// Если всё хорошо, делаем редирект
				$this->resType = 'redirect';
				$this->resData = array(
						'to' => URL_ROOT.'backups',
						'system_message' => 'Database backup performed successfully.'
					);
				
			}
			
		} else {
			
			// Выдаём форму для подтвержения
			$this->resType = 'html';
			$this->resData = array(
					'form_title' => 'Backup confirmation',
					'form_desc' => 'Database backup is resource-intensive action '.
						'and can take several minutes. Are you sure to proceed?',
					'form' => $this->form->toHtml()
				);
			
		}
		
	}

}

?>