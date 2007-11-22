<?php

require 'HTML/QuickForm.php';

class CommentEditAction extends AbstractAction {
	
	var $returnable = false;
	var $template = 'editor';
	
	var $form;
	
	function CommentEditAction($_params) {
		// Конструктор абстрактного класса
		$this->AbstractAction($_params);
		// Создаём форму
		$this->form = new HTML_QuickForm('comment_editor_form', 'post', 
			getenv("SCRIPT_NAME"), false, false, true);
	}
	
	function paramsOk() {
		// Action может получать параметры двух типов: 
		// либо это comment_id и больше ничего (запрос формы)
		$firstLaunch = count($this->params) == 1 && (isset($this->params['comment_id']) && 
			is_numeric($this->params['comment_id']));
		
		// либо это данные из формы
		$formVars = array('comment_id', 'name', 'email', 'hp', 'subj', 'text');
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
		// Код конструирования формы выделен в отдельный метод
		// для разгрузки $this->execute()
		$this->form->addElement('hidden', 'action', substr(basename(__FILE__), 0, -16)); // 'edit_comment'
		$this->form->addElement('hidden', 'comment_id', $this->params['comment_id']);
		$this->form->addElement('text', 'name', 'Your&nbsp;name:', 
			array('size' => 15, 'maxlength' => MAX_USER_NAME_LENGTH, 'tabindex' => 1, 
				'style' => 'width:16em;', 'class' => 'thin'));
		$this->form->addElement('text', 'email', 'Email:', 
			array('size' => 15, 'maxlength' => MAX_USER_EMAIL_LENGTH, 'tabindex' => 2, 
				'style' => 'width:16em;', 'class' => 'thin'));
		$this->form->addElement('text', 'hp', 'Homepage:', 
			array('size' => 15, 'maxlength' => MAX_USER_HP_LENGTH, 'tabindex' => 3, 
				'style' => 'width:16em;', 'class' => 'thin'));
		$this->form->addElement('text', 'subj', 'Subject:', 
			array('size' => 15, 'maxlength' => MAX_COMMENT_TITLE_LENGTH, 'tabindex' => 4, 
				'style' => 'width:16em;', 'class' => 'thin'));
		$this->form->addElement('textarea', 'text', 'Comment:', 
			array('tabindex' => 5, 'maxlength' => MAX_COMMENT_LENGTH, 
				'style' => 'width:32em; height:4cm;'));
		$this->form->addElement('checkbox', 'hidden', '', 
			'Private (hide this comment from everybody except post author)');
		
		$buttons = array();
		$buttons[] = &HTML_QuickForm::createElement('submit', '', 'Save comment', array('class' => 'green_submit'));
		$buttons[] = &HTML_QuickForm::createElement('button', '', 'Delete comment', array('class' => 'red_submit', 
			'onclick' => 'javascript:window.location.href = \''.URL_ROOT.'delete-comment/'.$this->params['comment_id'].'\';'));
		$buttons[] = &HTML_QuickForm::createElement('button', '', 'Cancel', array('class' => 'gray_submit', 
			'onclick' => 'javascript:window.location.href = \''.$_SESSION['ref'].'\';'));
		$this->form->addGroup($buttons);
		
		// Фильтры
		$this->form->applyFilter('name', 'trim');
		$this->form->applyFilter('email', 'trim');
		$this->form->applyFilter('hp', 'trim');
		$this->form->applyFilter('subj', 'trim');
		$this->form->applyFilter('text', 'trim');
		
		// Рулесы
		
		$msg = 'Name field is required.';
		$this->form->addRule('name', $msg, 'required', '', 'client');
		
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
		
	}
	
	function execute() {
		global $BDB;
		
		$comId = $this->params['comment_id'];
		$paramCnt = count($this->params);
		$userAccessLevel = $BDB->userAccessLevel;
		
		// Конструируем форму
		$this->buildForm();
		
		// Стандартный для большинства случаев набор данных для 
		// темплейта страницы
		$this->resData = array(
				'comment_id' => $comId
			);
		
		// Необходимо считать коммент, а заодно проверить его существование 
		// и наличие прав у текущего на правку коммента
		$comment = $BDB->GetComment($comId);
		
		// Выполняем проверку прав текущего пользователя на редактирование коммента
		if(!$BDB->CanChangeComment($comment['post_author_id'], $comment['post_status'], 
			$comment['user_id'], $comment['hidden'])) {
			$this->resType = "error";
			$this->resData = array("error_msg" => "User have no access rights to edit this comment.");
			return;
		}
		
		// Для зарегистрированных пользователей заполняем форму пользовательскими 
		// данными из базы в тех случаях, когда редактируется коммент их авторства
		if($BDB->userId != -1 && $BDB->userId == $comment['user_id']) {
			
			$this->form->setDefaults(array(
					'name' => $BDB->userName,
					'email' => $BDB->userEmail,
					'hp' => $BDB->userHp
				));
			
			$nameField = &$this->form->getElement('name');
			$emailField = &$this->form->getElement('email');
			$hpField = &$this->form->getElement('hp');
			
			$nameField->freeze();
			$emailField->freeze();
			$hpField->freeze();
			
		} else {
			// Для незарегистрированных пользователей, заполняем форму теми данными, 
			// которые пользователь уже вводил в аналогичную форму, и оставляем возможность 
			// редактирования этих данных
			$this->form->setDefaults(array(
					'name' => $comment['author_name'],
					'email' => $comment['author_email'],
					'hp' => $comment['author_hp']
				));
		}
		
		$this->form->setDefaults(array(
				'subj' => $comment['subj'],
				'text' => $comment['content'],
				'hidden' => $comment['hidden']
			));
		
		// How it works:
		// Если задан один параметр comment_id, значит выполнен запрос
		// формы редактирования коммента. Необходимо считать нужный коммент
		// и выдать форму, заполенную его контентом
		if($paramCnt == 1) {
			$this->resType = 'html';
			$this->resData = array(
					'cats' => $BDB->GetCats('title'),
					'popular_tags' => $BDB->GetPopularTags(POPULAR_TAGS_COUNT),
					'form' => $this->form->toHtml(),
					'form_title' => 'Edit comment',
					'page_title' => 'Comment editor',
					'where_am_i' => 'Here you may edit a comment.'
				);
		}
		
		// Если параметров много, значит пришли данные из формы и необходимо 
		// их обработать
		else {
			// Если данные из формы корректны, правим коммент в БД и перенаправляем 
			// юзера на страницу, с которой он пришёл
			$commentData = array(
					'hidden' => (isset($this->params['hidden']) && ($this->params['hidden'] == 1))?1:0,
					'subj' => $this->params['subj'],
					'content' => $this->params['text'],
					'author_name' => $this->params['name'],
					'author_email' => $this->params['email'],
					'author_hp' => $this->params['hp']				);
			
			$result = $BDB->ChangeComment($comId, $commentData);
			
			if($result) {
				// Если всё хорошо, делаем редирект на страницу поста, 
				// к оторому относился коммент
				$this->resType = 'redirect';
				$this->resData = array(
						'to' => URL_ROOT.'posts/'.$comment['post_id'],
						'system_message' => 'Comment saved successfully. You may see it on this page or at <a href="'.
							URL_ROOT.'comments/'.$comment['comment_id'].'">individual comment page</a>.'
					);
				
			} else {
				// Если данные из формы некорректны, выдаём форму повторно
				$this->resType = 'html';
				$this->resData = array(
						'cats' => $BDB->GetCats('title'),
						'popular_tags' => $BDB->GetPopularTags(POPULAR_TAGS_COUNT),
						'form' => $this->form->toHtml(),
						'form_title' => 'Edit comment',
						'error_msg' => $BDB->errorMsg,
						'page_title' => 'Comment editor',
						'where_am_i' => 'Here you may edit a comment.'
					);
				
			}
			
		}
		
	}

}

?>