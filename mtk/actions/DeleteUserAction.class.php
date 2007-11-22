<?php

require 'HTML/QuickForm.php';

/**
 * Удаления пользовательского профиля с подтверждением через форму.
 * 
 * Параметры: user_id - id удаляемого пользователя, confirmed - подтвержение 
 * необходимости удалить пользователя
 */
class DeleteUserAction extends AbstractAction {
	
	var $returnable = false;
	var $template = 'form';
	
	var $form;
	
	function DeleteUserAction($_params) {
		// Конструктор абстрактного класса
		$this->AbstractAction($_params);
		// Создаём форму
		$this->form = new HTML_QuickForm('delete_user_form', 'post', 
			getenv("SCRIPT_NAME"), false, false, true);
	}
	
	function paramsOk() {
		// Параметров должно быть два
		if(!isset($this->params['user_id']) || !is_numeric($this->params['user_id']) || 
			(isset($this->params['confirmed']) && !is_numeric($this->params['confirmed']))) {
			
			$this->errorMsg = "Bad action params.";
			return false;
		}
		
		// Главного админа удалить нельзя. Проверка сделана на случай подделки данных 
		// из формы, т.к. при нормальном функционировании скрипта главного админа удалить невозможно
		if($this->params['user_id'] == 0) {
			$this->errorMsg = "Blog master can not be deleted.";
			return false;
		}
		
		return true;
		
	}
	
	function buildForm() {
		// Код конструирования формы выделен в отдельный метод
		// для разгрузки $this->execute()
		$userId = $this->params['user_id'];
		$this->form->addElement('hidden', 'action', substr(basename(__FILE__), 0, -16)); // 'delete_user'
		$this->form->addElement('hidden', 'user_id', $userId);
		$this->form->addElement('hidden', 'confirmed', '1');
		
		$buttons = array();
		// Кнопка сабмита формы
		$buttons[] = &HTML_QuickForm::createElement('submit', '', 'Delete user', 
			array('class' => 'red_submit'));
		$buttons[] = &HTML_QuickForm::createElement('button', '', 'Cancel', 
			array('class' => 'gray_submit', 'onclick' => 'javascript:window.location.href = \''.$_SESSION['ref'].'\';'));
		$this->form->addGroup($buttons);
		
	}
	
	function execute() {
		global $BDB;
		
		$userId = $this->params['user_id'];
		$confirmed = (int)$this->params['confirmed'];
		
		// Проверить наличие прав у пользователя выполнять удаление других пользователей
		if(!$BDB->CanManageUsers()) {
			$this->resType = 'error';
			$this->resData = array('error_msg' => 'User has no access rights to manage other users profiles.');
			return false;
		}
		
		// Конструируем форму
		$this->buildForm();
		
		// Определить, есть ли подтверждение удаления
		if($this->form->validate()) {
			
			if($this->params['confirmed']) {
				
				// Удаляем пользователя
				$result = $BDB->DeleteUser($userId);
				
				if(!$result) {
					$this->resType = 'error';
					$this->resData = array('error_msg' => $BDB->errorMsg);
					return false;
				}
				
				// Переадресоватьetv пользователя на страницу со списком пользователей
				$this->resType = 'redirect';
				$this->resData = array(
						'to' => URL_ROOT.'users',
						'system_message' => 'User profile deleted successfully.'
					);
				
				return true;
				
			}
			
		}
		
		// Если подтверждения нет - выдать форму для подтверждения
		$this->resType = 'html';
		$this->resData = array(
				'form' => $this->form->toHtml(),
				'form_title' => 'Delete confirmation',
				'form_desc' => 'This will permanently delete user from the database. '.
					'Are you sure to proceed?'
			);
		
		return true;
		
	}

}

?>