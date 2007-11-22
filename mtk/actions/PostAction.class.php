<?php

require 'HTML/QuickForm.php';

/**
 * Выдаёт страницу поста с комментариями и формой для комментирования. Обрабатывает 
 * данные из формы комментирования.
 * Параметры:
 * Вариант вызова по post_id
 *  - post_id - выдаёт пост по соответствующему ID
 * Вариант вызова по дате поста и новмеру:
 *  - year - Год
 *  - month - Месяц
 *  - day - День
 *  - number - Номер поста за указанную дату
 */
class PostAction extends AbstractAction {
	
	var $returnable = true;
	var $template = 'post';
	var $form;
	
	function PostAction($_params) {
		// Конструктор абстрактного класса
		$this->AbstractAction($_params);
		// Создаём форму
		$this->form = new HTML_QuickForm('comment_form', 'post', 
			getenv("SCRIPT_NAME"), false, false, true);
	}
	
	function paramsOk() {
		// Action может получать параметры двух типов: 
		// либо это post_id и больше ничего (запрос формы)
		$firstLaunch = count($this->params) == 1 && (isset($this->params['post_id']) && 
			(is_numeric($this->params['post_id']) || $this->params['post_id'] == -1));
		
		// либо это данные из формы (добавление коммента к посту)
		$formVars = array('post_id', 'name', 'email', 'hp', 'subj', 'text');
		// В списке переменных, полученных из формы могут не присутствовать чекбоксы.
		// Особенность работы чекбоксов
		$formDataReceived = count(array_intersect(array_keys($this->params), 
			$formVars)) == count($formVars);
		
		if(!($firstLaunch || $formDataReceived)) {
			$this->errorMsg = "Bad action params.";
			return false;
		}
		
		return true;
		
	}
	
	function buildForm() {
		global $BDB;
		
		// Конструируем форму
		$this->form->addElement('hidden', 'action', substr(basename(__FILE__), 0, -16)); // 'comment'
		$this->form->addElement('hidden', 'post_id', $this->params['post_id']);
		
		if($BDB->userId != -1 && !strlen($BDB->userName)) {
			// Если пользователь зарегистрирован (форма заполняется его данными из БД) 
			// и его имя не задано, то поле формы Name отображаться не будет
			$nameField = $this->form->addElement('hidden', 'name', '');
		} else {
			$nameField = &$this->form->addElement('text', 'name', 'Your&nbsp;name:', 
				array('size' => 15, 'maxlength' => MAX_USER_NAME_LENGTH, 'tabindex' => 1, 
					'style' => 'width:16em;', 'class' => 'thin'));
		}
		
		$emailField = &$this->form->addElement('text', 'email', 'Email:', 
			array('size' => 15, 'maxlength' => MAX_USER_EMAIL_LENGTH, 'tabindex' => 2, 
				'style' => 'width:16em;', 'class' => 'thin'));
		
		if($BDB->userAccessLevel == -1) {
			$this->form->addElement('static', 'email_comments', '', 
				'<small class="silver">(Your address will not be shown on pages of this site or sold)</small>');
		}
		
		if($BDB->userId != -1 && !strlen($BDB->userHp)) {
			// Если пользователь зарегистрирован (форма заполняется его данными из БД) 
			// и URL его домашне страницы не задан, то поле формы Homepage отображаться не будет
			$hpField = $this->form->addElement('hidden', 'hp', '');
		} else {
			$hpField = &$this->form->addElement('text', 'hp', 'Homepage:', 
				array('size' => 15, 'maxlength' => MAX_USER_HP_LENGTH, 'tabindex' => 3, 
					'style' => 'width:16em;', 'class' => 'thin'));
		}
		
		if($BDB->userId != -1) {
			// Для зарегистрированных пользователей под заголовком формы отображается 
			// ссылка на редактирование пользователского профиля
			$this->form->addElement('static', 'edit_profile_comments', '', 
				'<small class="silver">(You may <a href="'.URL_ROOT.'edit-user/'.$BDB->userId.
				'">change your user info</a> if needed)</small>');
		}
		
		$this->form->addElement('text', 'subj', 'Subject:', 
			array('size' => 15, 'maxlength' => MAX_COMMENT_TITLE_LENGTH, 'tabindex' => 4, 
				'style' => 'width:16em;', 'class' => 'thin'));
		$this->form->addElement('textarea', 'text', 'Comment:', 
			array('maxlength' => MAX_COMMENT_LENGTH, 'tabindex' => 5, 'style' => 'width:32em; height:4cm;'));
		$this->form->addElement('checkbox', 'hidden', '', 'Private (hide this comment from everybody except post author)');
		
		$buttons = array();
		$buttons[] = &HTML_QuickForm::createElement('submit', '', 'Send comment', array('class' => 'green_submit'));
		$buttons[] = &HTML_QuickForm::createElement('reset', '', 'Clear form', array('class' => 'gray_submit'));
		$this->form->addGroup($buttons);
		
		// Фильтры
		$this->form->applyFilter('name', 'trim');
		$this->form->applyFilter('email', 'trim');
		$this->form->applyFilter('hp', 'trim');
		$this->form->applyFilter('subj', 'trim');
		$this->form->applyFilter('text', 'trim');
		
		// Рулесы
		
		$msg = 'Email field is required.';
		$this->form->addRule('email', $msg, 'required', '', 'client');
		
		$msg = 'Comment field is empty.';
		$this->form->addRule('text', $msg, 'required', '', 'client');
		
		$msg = 'Author name maximum length limit exceeded.';
		$this->form->addRule('name', $msg, 'maxlength', MAX_USER_NAME_LENGTH, 'client');
		
		$msg = 'Author email maximum length limit exceeded.';
		$this->form->addRule('email', $msg, 'maxlength', MAX_USER_EMAIL_LENGTH, 'client');
		
		$msg = 'Author homepage URL maximum length limit exceeded.';
		$this->form->addRule('hp', $msg, 'maxlength', MAX_USER_HP_LENGTH, 'client');
		
		$msg = 'Comment title maximum length limit exceeded.';
		$this->form->addRule('subj', $msg, 'maxlength', MAX_COMMENT_TITLE_LENGTH, 'client');
		
		$msg = 'Comment text maximum length limit exceeded.';
		$this->form->addRule('text', $msg, 'maxlength', MAX_COMMENT_LENGTH, 'client');
		
		$this->form->setDefaults(array(
				'name' => $BDB->userName,
				'email' => $BDB->userEmail,
				'hp' => $BDB->userHp
			));
		
		// Для зарегистрированных пользователей отключаем лишние поля формы
		if($BDB->userId != -1) {
			$nameField->freeze();
			$emailField->freeze();
			$hpField->freeze();
		}
	}
	
	function execute() {
		global $BDB;
		
		$postId = $this->params['post_id'];
		
		// Проверить первичное право пользователя на комментирование
		if(!($BDB->userAccessLevel & AR_COMMENTING)) {
			$this->resType = 'error';
			$this->resData = array('error_msg' => 'User has no access rights for commenting.');
			return;
		}
		
		// Считывем пост с комментами
		$post = $BDB->GetPost($postId, GP_COMMENTS | GP_NEIGHBOURS);
		if($post === false) {
			$this->resType = 'error';
			$this->resData['error_msg'] = "Error: ".$BDB->errorMsg;
			return;
		}
		
		// Проверить право пользователя на чтение этого поста
		if(!$BDB->CanAddComment($post['status'], $post['user_id'])) {
			$this->resType = 'error';
			$this->resData = array('error_msg' => 'User has no access rights to comment this post.');
			return;
		}
		
		// Конструируем форму
		$this->buildForm();
		
		// Обрабатываем данные из формы
		if($this->form->validate()) {
			
			$result = $BDB->AddComment(
					$this->params['post_id'],
					-1, // parrent comment_id
					$this->params['subj'],
					$this->params['text'],
					isset($this->params['hidden'])?((int)$this->params['hidden']):0,
					$this->params['name'],
					$this->params['email'],
					$this->params['hp']
				);
			
			//show($result);
			
			if(!$result) {
				$this->resType = 'error';
				$this->resData = array('error_msg' => $BDB->errorMsg);
				return;
			}
			
			// NIHAMG: $result - comment_id нового коммента
			
			// Email notification
			if(EMAIL_NOTIFICATION && $BDB->CanCommentImmediately()) {
				// Письмо автору поста отправляется сразу после сабмита нового коммента, 
				// только в том случае, если:
				// - Включена функция оповещения по почте о комментариях
				// - Комментатор обладает правами, достаточными для публикации 
				//   комментариев без предварительной модерации (в противном случае, 
				//   уведомление будет отправлено автору комментируемого поста только после 
				//   модерации)
				
				$post = $BDB->GetPost($this->params['post_id']);
				
				// To send HTML mail, you can set the Content-type header.
				$headers = 
					"MIME-Version: 1.0\r\n".
					"Content-type: text/plain; charset=utf-8\r\n".
					"From: ".MAIL_BOT."\r\n";
				
				// recipients
				$to  = $post['user_name']." <".$post['user_email'].">";
				
				// subject
				$subject = "New comment notification";
				
				// message
				$message = "You have received a new comment \n  from ".$this->params['name'].
					" (".$this->params['email'].").\n\n".
					"- Post title: ".$post['title']."\n".
					"- Comment publication time: ".date("Y/m/d @ H:i:s")."\n".
					"- Thread URL: ".URL_HOST.URL_ROOT."posts/".$this->params['post_id']."\n".
					($this->params['subj']?("- Comment title: ".$this->params['subj'])."\n\n":"\n").
					$this->params['text'];
				
				$message .= "\n\n--\n".BLOG_TITLE." / ".URL_HOST.URL_ROOT."\n\n".
					"This is automatically created notification. Please do not reply to this message.";
				
				// and now mail it
				mail($to, $subject, $message, $headers);
				
				show(array($to, $subject, $message, $headers));
				
			}
			
			$msg = 'Comment posted successfully. ';
			if($BDB->CanCommentImmediately()) {
				$msg .= 'You may found it the <a href="'.URL_ROOT.'posts/'.$this->params['post_id'].
				'">posts page</a> or by the <a href="'.URL_ROOT.'comments/'.$result.'">permanent link</a>.';
			} else {
				$msg .= 'It will became visible here after moderation '.
					'and you will be notified by email. ';
			}
			
			
			$this->resType = "redirect";
			$this->resData = array(
					'system_message' => $msg,
					'to' => 'back'
				);
			
			// Для анонимных пользователей сохраняем введённые ими данные в сессию.
			// При последующих вызовах action'а, форма комментирования будет заполнена 
			// этими данными. Для зарегистрированных пользователей такое сохранение 
			// не выполняется, т.к. их пользовательские данные и так известны системе 
			// и хранятся в БД (восстанавливаюся оттуда при каждом запуске ядра).
			if($BDB->userId == -1) {
				
				$_SESSION['user_name'] = $this->params['name'];
				$_SESSION['user_email'] = $this->params['email'];
				$_SESSION['user_hp'] = $this->params['hp'];
				
			}
			
			return;
			
		}
		
		// Генерируем страницу с постом
		$formHtml = ($BDB->userAccessLevel & AR_COMMENTING)?
			$this->form->toHtml():"";
		
		$this->resType = 'html';
		$this->resData = array(
				'cats' => $BDB->GetCats('title'),
				'popular_tags' => $BDB->GetPopularTags(POPULAR_TAGS_COUNT),
				'comment_form' => $this->form->toHtml(),
				'can_change' => (int)$BDB->CanChangePost($post['user_id'], $post['status']),
				'can_delete' => (int)$BDB->CanDeletePost($post['user_id'], $post['status']),
				'next_url' => is_numeric($post['next_post_id'])?(URL_ROOT.'posts/'.$post['next_post_id']):'',
				'prev_url' => is_numeric($post['prev_post_id'])?(URL_ROOT.'posts/'.$post['prev_post_id']):'',
				'real_com_cnt' => count($post['comments'])
			);
		
		global $STATUSES;
		
		$post['status_desc'] = $STATUSES[$post['status']];
		
		// Добавляем к комментам флаг возможности правки (и удаления)
		$comments = &$post['comments'];
		foreach($comments as $num => $comment) {
			$comments[$num]['can_change'] = $BDB->CanChangeComment($post['user_id'], $post['status'], 
				$comment['user_id'], $comment['hidden']);
			$comments[$num]['can_moderate'] = $BDB->CanModerateComment($post['status'], $comment['hidden']);
		}
		
		$this->resData['post'] = $post;
		$this->resData['page_title'] = $post['title'].' ('.($post['user_name']?$post['user_name']:$post['user_login']).')';
		$this->resData['where_am_i'] = 'This is an individual post page where its content and comments are located.';
	}

}

?>