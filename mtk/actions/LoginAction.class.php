<?php

require 'HTML/QuickForm.php';

class LoginAction extends AbstractAction {
	
	var $returnable = false;
	var $template = 'form';
	
	var $form;
	
	function LoginAction($_params) {
		// Конструктор абстрактного класса
		$this->AbstractAction($_params);
		// Создаём форму
		$this->form = new HTML_QuickForm('login_form', 'post', 
			getenv("SCRIPT_NAME"), false, false, true);
	}
	
	function paramsOk() {
		// возможны два варианта запуска экшена:
		// 1. запуск без параметров, когда запрашивается формы логина
		// 2. запуск с параметрами login и password 
		// для обработки данных из формы
		if(count($this->params) == 0 || 
			(isset($this->params['login']) && isset($this->params['password']))) {
			
			return true;
		} else {
			$this->errorMsg = "Bad action params.";
			return false;
		}
	}
	
	function buildForm() {
		// Код конструирования формы выделен в отдельный метод
		// для разгрузки $this->execute()
		$this->form->addElement('hidden', 'action', substr(basename(__FILE__), 0, -16)); // 'login'
		$userField = &$this->form->addElement('text', 'login', 'User&nbsp;name:', 
			array('size' => 15, 'maxlength' => MAX_USER_LOGIN_LENGTH, 'tabindex' => 1, 'class' => 'biginput'));
		$pwdField = &$this->form->addElement('password', 'password', 'Password:', 
			array('size' => 15, 'maxlength' => MAX_USER_PWD_LENGTH, 'tabindex' => 2, 'class' => 'biginput'));
		
		$buttons = array();
		$buttons[] = &HTML_QuickForm::createElement('submit', '', 'Log in!', array('class' => 'green_submit'));
		$buttons[] = &HTML_QuickForm::createElement('button', '', 'Cancel',
			array('onclick' => 'javascript:window.location.href = \''.getenv('HTTP_REFERER').'\';', 'class' => 'gray_submit'));
		$this->form->addGroup($buttons);
		
		// Правила заполнения формы: ограничение максимальной длины текса,
		// определение обоих полей, как обязательных.
		$msg = 'Maximum username length is '.MAX_USER_LOGIN_LENGTH.' characters';
		$this->form->addRule('login', $msg, 'maxlength', MAX_USER_LOGIN_LENGTH, 'client');
		
		$msg = 'Username is required';
		$this->form->addRule('login', $msg, 'required', '', 'client');
		
		$msg = 'Maximum password length '.MAX_USER_PWD_LENGTH.' characters';
		$this->form->addRule('password', $msg, 'maxlength', MAX_USER_PWD_LENGTH, 'client');
		
		$msg = 'Password is required';
		$this->form->addRule('password', $msg, 'required', '', 'client');
	}
	
	function execute() {
		global $BDB;
		
		$this->buildForm();
		
		if($this->form->validate()) {
			// Пришли корректные данные из формы
			
			$login = $this->form->exportValue('login');
			$password = $this->form->exportValue('password');
			
			$userData = $BDB->CheckUser($login, $password);
			
			if($userData === false) {
				// Возможные варианты:
				//   - Пользователь не существует 
				//   - Пароль задан неверно
				//   - Произошла ошибка при работе с БД
				$errorMsg = DEBUG_MODE?
					$BDB->errorMsg:"Incorrect username or password.";
				
			} else {
				// Имя пользователя и пароль корректны, можно логинить
				
				// Сохраняем в сессии ID пользователя
				$_SESSION['user_id'] = $userData['user_id'];
				
				// Если имя пользователя не определено, поприветствуем его по логину
				$name = (isset($userData['name']) && $userData['name'])?$userData['name']:$login;
				
				// Логин успещно выполнен. Делаем редирект на страницу, 
				// с которой была запрошена форма логина.
				$this->resType = 'redirect';
				$this->resData = array(
						'to' => 'back',
						'system_message' => 'Welcome back, '.$name."!"
					);
				
				return;
				
			}
		}
		
		// Если дошли до этой точки, значит была запрошена форма, 
		// или пришли некорректные данные. Либо было неверно задано имя
		// пользователя или пароль. Требуется отдать форму.
		
		$this->resType = 'html';
		$this->resData = array(
				'form' => str_replace('valign="top"', 'valign="middle"', $this->form->toHtml()),
				'form_title' => 'Login',
				'form_desc' => 'Type your username and password to log in.'
			);
		
		if(isset($errorMsg)) $this->resData['error_msg'] = $errorMsg;
		
	}

}

?>