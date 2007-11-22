<?php

require 'HTML/QuickForm.php';

/**
 * Удаление категории. action выдаёт форму для выбора между переносом постов удаляемой 
 * из категории в одну из существующих, или для удаления постов насовсем.
 * Параметры: cat_id (int) - ID удаляемой категории; new_cat_id (int) - ID категории,
 * в которую будут перенесены посты из удаляемой категории (принимает значение -1, если 
 * требуется удалить посты навеки).
 */
class DeleteBackupAction extends AbstractAction {
	
	var $returnable = false;
	var $template = 'form';
	
	var $catsList = array();
	
	var $form;
	
	function DeleteBackupAction($_params) {
		// Конструктор абстрактного класса
		$this->AbstractAction($_params);
		// Создаём форму
		$this->form = new HTML_QuickForm('delete_backup_cfm_form', 'post', 
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
		$ts = $this->params['timestamp'];
		$this->form->addElement('hidden', 'action', substr(basename(__FILE__), 0, -16)); // 'delete_backup'
		$this->form->addElement('hidden', 'timestamp', $ts);
		$this->form->addElement('hidden', 'confirmed', 1);
		
		$buttons = array();
		// Кнопка сабмита формы
		$buttons[] = &HTML_QuickForm::createElement('submit', '', 'Delete backup', 
			array('tabindex' => 2, 'class' => 'red_submit'));
		$buttons[] = &HTML_QuickForm::createElement('button', '', 'Cancel', 
			array('tabindex' => 3, 'class' => 'gray_submit', 
				'onclick' => 'javascript:window.location.href = \''.$_SESSION['ref'].'\';'));
		$this->form->addGroup($buttons);
		
	}
	
	function execute() {
		global $BDB;
		
		// Проверяем право пользователя на просмотр списка бэкапов
		if(!$BDB->CanManageBackups()) {
			$this->resType = 'error';
			$this->resData = array('error_msg' => 
				'User have no access rights to perform database backup.');
			return;
		}
		
		$this->buildForm();
		$ts = $this->params['timestamp'];
		
		if($this->form->validate()) {
			
			if((bool)$this->params['confirmed']) {
				
				// Подтвержение получено. Удаляем бэкап
				$result = $BDB->DeleteBackup($ts);
				if(!$result) {
					$this->resType = 'error';
					$this->resData = array('error_msg' => $BDB->errorMsg);
					return;
				}
				
				// Если всё хорошо, делаем редирект
				$this->resType = 'redirect';
				$this->resData = array(
						'to' => URL_ROOT.'backups',
						'system_message' => 'Backup deleted successfully.'
					);
				
			}
			
		} else {
			
			// Выдаём форму для подтвержения
			$this->resType = 'html';
			$this->resData = array(
					'form_title' => 'Backup deletion',
					'form_desc' => 'Database backup files will be deleted permanently. '.
						'Are you sure to proceed?',
					'form' => $this->form->toHtml()
				);
			
		}
		
	}

}

?>