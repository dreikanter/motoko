<?php

require 'HTML/QuickForm.php';

/**
 * Удаление поста с подтверждением через форму
 * 
 * Параметры: post_id - id удаляемого поста, confirmed - подтвержение 
 * необходимости удалить пост (определяет, удаляет ли action заданный 
 * пост при текущем вызове или выдаёт форму с подтверждением необходимости 
 * это сделать)
 */
class DeletePostAction extends AbstractAction {
	
	var $returnable = false;
	var $template = 'form';
	
	var $form;
	
	function DeletePostAction($_params) {
		// Конструктор абстрактного класса
		$this->AbstractAction($_params);
		// Создаём форму
		$this->form = new HTML_QuickForm('delete_post_form', 'post', 
			getenv("SCRIPT_NAME"), false, false, true);
	}
	
	function paramsOk() {
		// Параметров должно быть два
		if(!isset($this->params['post_id']) || !is_numeric($this->params['post_id']) || 
			!isset($this->params['confirmed']) || !is_numeric($this->params['confirmed'])) {
			
			$this->errorMsg = "Bad action params.";
			return false;
		}
		
		return true;
		
	}
	
	function buildForm() {
		// Код конструирования формы выделен в отдельный метод
		// для разгрузки $this->execute()
		$postId = $this->params['post_id'];
		$this->form->addElement('hidden', 'action', substr(basename(__FILE__), 0, -16)); // 'delete_post'
		$this->form->addElement('hidden', 'post_id', $postId);
		$this->form->addElement('hidden', 'confirmed', '1');
		
		$buttons = array();
		// Кнопка сабмита формы
		$buttons[] = &HTML_QuickForm::createElement('submit', '', 'Delete post', 
			array('class' => 'red_submit'));
		$buttons[] = &HTML_QuickForm::createElement('button', '', 'Cancel', 
			array('class' => 'gray_submit', 'onclick' => 'javascript:window.location.href = \''.$_SESSION['ref'].'\';'));
		$this->form->addGroup($buttons);
		
	}
	
	function execute() {
		global $BDB;
		
		$postId = $this->params['post_id'];
		$confirmed = (int)$this->params['confirmed'];
		
		// Проверить наличие прав у пользователя выполнять удаление постов
		// (дополнительная проверка нужна для снятия необходимости выпонять лишний запрос 
		// к базе, если у пользователя всё равно заведомо нет прав выполнять необходимое действие)
		if(!($BDB->userAccessLevel & (AR_POSTING | AR_MODERATION | AR_SUPERVISING))) {
			$this->resType = 'error';
			$this->resData['error_msg'] = "User has no access rights to delete posts.";
			return false;
		}
		
		// Считать пост с заданным post_id
		$post = $BDB->GetPost($postId);
		
		// Проверить его существование
		if(!$post) {
			$this->resType = 'error';
			$this->resData['error_msg'] = "Required post doesn`t exists.";
			return false;
		}
		
		// Проверить наличие прав у пользовтеля на удаление конкретно заданного поста
		if(!$BDB->CanDeletePost($post['user_id'], $post['status'])) {
			$this->resType = 'error';
			$this->resData['error_msg'] = "User has no access rights to delete this post.";
			return false;
		}
		
		
		// Определить, есть ли подтверждение удаления
		if($this->form->validate()) {
			if($this->params['confirmed']) {
				// Если есть - удалить и переадресовать пользователя 
				// на страницу в архиве, соответствующую дате удалённого поста
				$this->resType = 'redirect';
				$this->resData = array(
						// Редирект должен выполняться на страницу архива, 
						// соответствующюу дате удаленного поста
						//'to' => URL_ROOT.date('Y/m/d', $post['ctime']),

						'to' => URL_ROOT,
						'system_message' => 'Post deleted successfully.'
					);
				
				$BDB->DeletePost($postId);
				
				return true;
			}
			
		}
		
		// Если подтверждения нет - выдать форму для подтверждения
		$this->buildForm();
		
		$this->resType = 'html';
		$this->resData = array(
				'form' => $this->form->toHtml(),
				'form_title' => 'Delete confirmation',
				'form_desc' => 'This will permanently delete post from the database '.
					'and you will be not able to restore it later. Are you sure to proceed?'
			);
		
		return true;
		
	}

}

?>