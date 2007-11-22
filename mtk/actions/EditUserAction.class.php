<?php

require 'HTML/QuickForm.php';

/**
 * Редактирует пользовательские данные. При первой загрузке 
 * выдаёт форму, после сабмита делает редирект на список категорий.
 * Параметры: login (string) - login редактируемого пользователя
 */
class EditUserAction extends AbstractAction {
	
	var $returnable = false;
	var $template = 'editor';
	
	var $form;
	
	function EditUserAction($_params) {
		// Конструктор абстрактного класса
		$this->AbstractAction($_params);
		// Создаём форму
		$this->form = new HTML_QuickForm('edit_user_form', 'post', 
			getenv("SCRIPT_NAME"), false, false, true);
	}
	
	function paramsOk() {
		// Action может получать параметры двух типов: 
		// либо это login и больше ничего (запрос формы)
		$firstLaunch = (count($this->params) == 1) && isset($this->params['user_id']) && 
			is_numeric($this->params['user_id']);
		
		if($firstLaunch) return true;
		
		// либо это данные из формы
		$formVars = array('login', 'name', 'email', 'hp', 'pwd', 'pwd2', 'description');
		$formDataReceived = count(array_intersect(array_keys($this->params), 
			$formVars)) == count($formVars);
		
		if($formDataReceived) return true;
		
		$this->errorMsg = 'Incorrect action parameters.';
		return false;
		
	}
	
	function buildForm() {
		
		global $BDB;
		
		// Код конструирования формы выделен в отдельный метод
		// для разгрузки $this->execute()
		$userId = (int)$this->params['user_id'];
		
		$this->form->addElement('hidden', 'action', substr(basename(__FILE__), 0, -16)); // 'edit_user'
		$this->form->addElement('hidden', 'user_id', $userId);
		
		$this->form->addElement('text', 'login', 'Login:', 
			array('size' => 15, 'maxlength' => MAX_USER_LOGIN_LENGTH, 'tabindex' => 1, 
				'style' => 'width:16em;', 'class' => 'thin'));
		
		$this->form->addElement('text', 'name', 'Name:', 
			array('size' => 15, 'maxlength' => MAX_USER_NAME_LENGTH, 'tabindex' => 1, 
				'style' => 'width:16em;', 'class' => 'thin'));
		
		$this->form->addElement('text', 'email', 'Email:', 
			array('size' => 15, 'maxlength' => MAX_USER_EMAIL_LENGTH, 'tabindex' => 1, 
				'style' => 'width:16em;', 'class' => 'thin'));
		
		$this->form->addElement('text', 'hp', 'Homepage:', 
			array('size' => 15, 'maxlength' => MAX_USER_HP_LENGTH, 'tabindex' => 1, 
				'style' => 'width:16em;', 'class' => 'thin'));
		
		$this->form->addElement('text', 'icq', 'ICQ:', 
			array('size' => 15, 'maxlength' => 20, 'tabindex' => 1, 
				'style' => 'width:16em;', 'class' => 'thin'));
		
		$this->form->addElement('password', 'pwd', 'Passord:', 
			array('size' => 15, 'maxlength' => MAX_USER_PWD_LENGTH, 'tabindex' => 1, 
				'style' => 'width:16em;', 'class' => 'thin'));
		
		$this->form->addElement('password', 'pwd2', 'Password confirmation:', 
			array('size' => 15, 'maxlength' => MAX_USER_PWD_LENGTH, 'tabindex' => 1, 
				'style' => 'width:16em;', 'class' => 'thin'));
		
		$this->form->addElement('textarea', 'description', 'Description:', 
			array('size' => 15, 'maxlength' => MAX_USER_DESC_LENGTH, 'tabindex' => 1, 
				'style' => 'width:40em; height:8cm;', 'class' => 'thin'));
		
		if((int)$BDB->userAccessLevel & AR_SUPERVISING) {
			// Редактировать права доступа можно супервизору для всех пользователей, кроме 
			// главного админа (user_id == 0) - у него уровень всегда на максимуме. И любому 
			// другому зарегистрированному пользователю, но только для своего профиля
			
			if($userId == 0) {
				
				// У главного админа права доступа всегда на максимуме и функция 
				// их редактирования отключена
				$this->form->addElement('static', '', 'Access rights:', 
					'This user always have highest access level.');
				
			} else {
				
				$accessField = array();
				$accessField[] = &HTML_QuickForm::createElement('checkbox', 'check_reading_c', '', 'Community posts reading');
				$accessField[] = &HTML_QuickForm::createElement('checkbox', 'check_posting', '', 'Posting');
				$accessField[] = &HTML_QuickForm::createElement('checkbox', 'check_commenting', '', 'Commenting');
				$accessField[] = &HTML_QuickForm::createElement('checkbox', 'check_moderation', '', 'Moderation');
				$accessField[] = &HTML_QuickForm::createElement('checkbox', 'check_supervising', '', 'Supervising');
				$this->form->addGroup($accessField, '', 'Access rights:', '<br>');
				
			}
			
		}
		
		$buttons = array();
		$buttons[] = &HTML_QuickForm::createElement('submit', '', 'Save user', 
			array('class' => 'green_submit'));
		// Кнопка удаления пользователя добавляется только в том случае, если user_id > 0
		// (гланого админа нельзя удалить; создаваемого пользователя в процессе создавания 
		// тоже нельзя удалить, т.к. его ещё не существует)
		if($userId > 0) {
			$buttons[] = &HTML_QuickForm::createElement('button', '', 'Delete user', 
				array('class' => 'red_submit', 
					'onclick' => 'javascript:window.location.href = \''.URL_ROOT.'delete-user/'.$userId.'\';'));
		}
		
		$buttons[] = &HTML_QuickForm::createElement('button', '', 'Cancel', 
			array('class' => 'gray_submit', 
				'onclick' => 'javascript:window.location.href = \''.$_SESSION['ref'].'\';'));
		
		$this->form->addGroup($buttons);
		
		// Фильтры
		$this->form->applyFilter('login', 'trim');
		$this->form->applyFilter('name', 'trim');
		$this->form->applyFilter('email', 'trim');
		$this->form->applyFilter('hp', 'trim');
		$this->form->applyFilter('icq', 'trim');
		$this->form->applyFilter('pwd', 'trim');
		$this->form->applyFilter('pwd2', 'trim');
		$this->form->applyFilter('description', 'trim');
		$this->form->applyFilter('icq', 'trim');
		
		// Рулесы
		$msg = 'Login is required.';
		$this->form->addRule('login', $msg, 'required', '', 'client');
		
		$msg = 'User email is required,';
		$this->form->addRule('email', $msg, 'required', '', 'client');
		
		$msg = 'Incorrect email specified.';
		$this->form->addRule('email', $msg, 'email', '', 'client');
		
		$msg = 'Login maximum length limit exceeded.';
		$this->form->addRule('login', $msg, 'maxlength', MAX_USER_LOGIN_LENGTH, 'client');
		
		$msg = 'User name maximum length limit exceeded.';
		$this->form->addRule('name', $msg, 'maxlength', MAX_USER_NAME_LENGTH, 'client');
		
		$msg = 'User email maximum length limit exceeded.';
		$this->form->addRule('email', $msg, 'maxlength', MAX_USER_EMAIL_LENGTH, 'client');
		
		$msg = 'Homepage URL maximum length limit exceeded.';
		$this->form->addRule('hp', $msg, 'maxlength', MAX_USER_HP_LENGTH, 'client');
		
		// Максимальная длина паролей
		$msg = 'Password maximum length limit exceeded.';
		$this->form->addRule('pwd', $msg, 'maxlength', MAX_USER_PWD_LENGTH, 'client');
		$this->form->addRule('pwd2', $msg, 'maxlength', MAX_USER_PWD_LENGTH, 'client');
		
		// Минимальная длина паролей опредеяется только в том случае, если
		// создаётся новая пользовательская запись. В этом же случае пароль относится 
		// к обязательным для заполнения полям. Еси пользовательская запись редактируется, 
		// поле пароля необзязательно заполнять - в таком случае он останется прежним.
		if($userId == -1) {
			
			$msg = 'Password must contain at least '.MIN_USER_PWD_LENGTH.' characters.';
			$this->form->addRule('pwd', $msg, 'minlength', MIN_USER_PWD_LENGTH, 'client');
			$this->form->addRule('pwd2', $msg, 'minlength', MIN_USER_PWD_LENGTH, 'client');
			$msg = 'Password is required';
			$this->form->addRule('pwd', $msg, 'required', '', 'client');
			$msg = 'Password confirmation is required';
			$this->form->addRule('pwd2', $msg, 'required', '', 'client');
			
		}
		
		// Сравнение паролей в двух полях
		$this->form->addRule(array('pwd', 'pwd2'), 'The passwords do not match', 
			'compare', null, 'client');
		
		$msg = 'Description maximum length limit exceeded.';
		$this->form->addRule('description', $msg, 'maxlength', MAX_USER_DESC_LENGTH, 'client');
		
		$msg = 'Login syntax is incorrect.';
		$this->form->addRule('login', $msg, 'regex', REGEXP_LOGIN, 'client');
		
		$msg = 'ICQ maximum length limit exceeded.';
		$this->form->addRule('icq', $msg, 'maxlength', 20, 'client');
		
		$msg = 'Incorrect ICQ UIN specified.';
		$this->form->addRule('icq', $msg, 'numeric', 20, 'client');
		
	}
	
	function execute() {
		global $BDB;
		
		$userId = $this->params['user_id'];
		$paramCnt = count($this->params);
		
		// Проверяем право пользователя редактировать профиль. Для этого либо пользователь 
		// должен быть супервизором, либо профиль должен принадлежать пользователю (не-супервизоры 
		// могу редактировать только свой собственный профиль)
		if(!($BDB->CanManageUsers() || $BDB->userId > -1 && $BDB->userId == $userId)) {
			$this->resType = 'error';
			$this->resData = array('error_msg' => 'User have no access rights to manage profiles.');
			return;
		}
		
		// Конструируем форму
		$this->buildForm();
		
		if($this->form->validate()) {
			
			// Пришли данные из формы и они корректны.
			// Выполнякм предварительную обработку данных из формы.
			
			// Если пользователь не имеет админских прав доступа или редактируется профиль главного 
			// админа (user_id == 0), права доступа не могут быть изменены в любом случае. При нормальной 
			// работе скрипта, они не могут быть заданы в принципе, т.к. в сгенерированной форме будут 
			// отсутствовать необходимые для этого элементы. Проверка сделана на случай подделки данных.
			if($BDB->userAccessLevel & AR_SUPERVISING && $userId != 0) {
				
				$accessLevel = 
					((isset($this->params['check_reading_c']) && $this->params['check_reading_c'])?AR_READING_COMMUNITY:0) |
					((isset($this->params['check_posting']) && $this->params['check_posting'])?AR_POSTING:0) |
					((isset($this->params['check_commenting']) && $this->params['check_commenting'])?AR_COMMENTING:0) |
					((isset($this->params['check_moderation']) && $this->params['check_moderation'])?AR_MODERATION:0) |
					((isset($this->params['check_supervising']) && $this->params['check_supervising'])?AR_SUPERVISING:0);
				
			} else {
				
				$accessLevel = false;
				
			}
			
			$customData = isset($this->params['icq'])?array('icq' => $this->params['icq']):'';
			
			// Определяем, что необходимос сделать с данными
			if($userId == -1) {
				// Данные для нового профиля. Необходимос оздать пользователя
				
				$result = $BDB->AddUser(
						$this->params['login'],
						$this->params['pwd'],
						$this->params['email'],
						$accessLevel,
						$this->params['name'],
						$this->params['hp'],
						$this->params['description'],
						$customData
					);
				
				if(!$result) {
					// Если произошла ошибка при добавлении нового пользователя, 
					// выдаём сообщение и повторно выдаём форму
					$this->resType = 'html';
					$this->resData = array(
							'cats' => $BDB->GetCats('title'),
							'popular_tags' => $BDB->GetPopularTags(POPULAR_TAGS_COUNT),
							'form' => $this->form->toHtml(),
							'error_msg' => $BDB->errorMsg,
							'page_title' => 'User profile editor',
							'where_am_i' => 'Here you may edit a user profile.'
						);
					
					return;
					
				}
				
			} else {
				// Изменённые данные для уже существующего профиля
				$userData = array();
				
				if(isset($this->params['login']) && strlen($this->params['login'])) 
					$userData['login'] = $this->params['login'];
				
				if(isset($this->params['pwd']) && strlen($this->params['pwd'])) 
					$userData['pwd'] = $this->params['pwd'];
				
				if(isset($this->params['email']) && strlen($this->params['email'])) 
					$userData['email'] = $this->params['email'];
				
				if(isset($this->params['name'])) 
					$userData['name'] = $this->params['name'];
				
				if(isset($this->params['hp'])) 
					$userData['hp'] = $this->params['hp'];
				
				if(isset($this->params['description'])) 
					$userData['description'] = $this->params['description'];
				
				if($accessLevel) 
					$userData['access_level'] = $accessLevel;
				
				$userData['custom_data'] = $customData;
				
				$result = $BDB->ChangeUser($userId, $userData);
				
				if(!$result) {
					$this->resType = 'html';
					$this->resData = array(
							'cats' => $BDB->GetCats('title'),
							'popular_tags' => $BDB->GetPopularTags(POPULAR_TAGS_COUNT),
							'form' => $this->form->toHtml(),
							'error_msg' => $BDB->errorMsg,
							'page_title' => 'User profile editor',
							'where_am_i' => 'Here you may edit a user profile.'
						);
					return;
				}
				
			}
			
			// Необходимо проверить существование пользователя с заданным ID
			// записать данные в БД
			// Делаем редирект на реферера
			$this->resType = 'redirect';
			$this->resData = array(
					'to' => URL_ROOT.'users/'.$this->params['login'],
					'system_message' => 'User data saved successfully.'
				);
			
		} else {
			
			// Выполняется первый запуск action'а (запрос формы) или данные из формы не прокатили.
			
			if($paramCnt == 1) {
				// Выполнен первый запрос формы редактирования пользовательских данных.
				
				if($userId == -1) {
					// Создаётся профильнового пользователя
					$this->resData['form_title'] = 'New user profile';
					$this->form->setDefaults(array(
							'check_reading_c' => (ACCESS_DEFAULT & AR_READING_COMMUNITY),
							'check_posting' => (ACCESS_DEFAULT & AR_POSTING),
							'check_commenting' => (ACCESS_DEFAULT & AR_COMMENTING),
							'check_moderation' => (ACCESS_DEFAULT & AR_MODERATION),
							'check_supervising' => (ACCESS_DEFAULT & AR_SUPERVISING)
						));
				} else {
					// Редактируется профиль уже существующего пользователя
					$this->resData['form_title'] = 'User profile';
					
					// Необходимо считать из базы данные пользотваеля и заполнить ими форму.
					$user = $BDB->GetUser((int)$userId);
					
					if(!$user) {
						$this->resType = 'error';
						$this->resData = array('error_msg' => $BDB->errorMsg);
						return;
					}
					
					// Заполняются все поля кроме пароля (если из формы приходит пустой пароль, 
					// он не будет изменён)
					$this->form->setDefaults(array(
							'login' => $user['login'],
							'name' => $user['name'],
							'email' => $user['email'],
							'hp' => $user['hp'],
							'description' => $user['description'],
							'check_reading_c' => ($user['access_level'] & AR_READING_COMMUNITY),
							'check_posting' => ($user['access_level'] & AR_POSTING),
							'check_commenting' => ($user['access_level'] & AR_COMMENTING),
							'check_moderation' => ($user['access_level'] & AR_MODERATION),
							'check_supervising' => ($user['access_level'] & AR_SUPERVISING),
							'icq' => isset($user['custom_data']['icq'])?$user['custom_data']['icq']:''
						));
					
				}
				
			}
			
			$this->resType = 'html';
			$this->resData = array(
					'cats' => $BDB->GetCats('title'),
					'popular_tags' => $BDB->GetPopularTags(POPULAR_TAGS_COUNT),
					'form' => $this->form->toHtml(),
					'form_title' => 'User profile editor',
					'page_title' => 'User profile editor',
					'where_am_i' => 'This is a page where you may edit a user profile.'
				);
			
			if($paramCnt != 1) {
				// Если в action пришло более одного параметра, значит были присланы 
				// данные из формы. Форма выдаётся повторно с сообщением об ошибке.
				$resData['error_msg'] = 'Some errors occured.';
			}
			
		}
		
	}

}

?>