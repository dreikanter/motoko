<?php

require 'HTML/QuickForm.php';

/**
 * Удаление комментария к посту с подтверждением через форму
 * 
 * Параметры: comment_id - id удаляемого комментария, confirmed - подтвержение 
 * необходимости удалить комментарий (определяет, удаляет ли action заданный 
 * комментраий при текущем вызове или выдаёт форму с подтверждением необходимости 
 * это сделать)
 */
class DeleteCommentAction extends AbstractAction {
	
	var $returnable = false;
	var $template = 'form';
	
	var $form;
	
	function DeleteCommentAction($_params) {
		// Конструктор абстрактного класса
		$this->AbstractAction($_params);
		// Создаём форму
		$this->form = new HTML_QuickForm('delete_comment_form', 'post', 
			getenv("SCRIPT_NAME"), false, false, true);
	}
	
	function paramsOk() {
		// Параметров должно быть два
		if(!isset($this->params['comment_id']) || !is_numeric($this->params['comment_id']) || 
			!isset($this->params['confirmed']) || !is_numeric($this->params['confirmed'])) {
			
			$this->errorMsg = "Bad action params.";
			return false;
		}
		
		return true;
		
	}
	
	function buildForm() {
		// Код конструирования формы выделен в отдельный метод
		// для разгрузки $this->execute()
		$postId = $this->params['comment_id'];
		$this->form->addElement('hidden', 'action', substr(basename(__FILE__), 0, -16)); // 'delete_comment'
		$this->form->addElement('hidden', 'comment_id', $postId);
		$this->form->addElement('hidden', 'confirmed', '1');
		
		$buttons = array();
		// Кнопка сабмита формы
		$buttons[] = &HTML_QuickForm::createElement('submit', '', 'Delete comment', 
			array('class' => 'red_submit'));
		$buttons[] = &HTML_QuickForm::createElement('button', '', 'Cancel', 
			array('class' => 'gray_submit', 'onclick' => 'javascript:window.location.href = \''.$_SESSION['ref'].'\';'));
		$this->form->addGroup($buttons);
		
	}
	
	function execute() {
		global $BDB;
		
		$commentId = $this->params['comment_id'];
		$confirmed = (int)$this->params['confirmed'];
		
		// Проверить наличие первичных прав у пользователя выполнять удаление комментов
		// (дополнительная проверка нужна для снятия необходимости выпонять лишний запрос 
		// к базе, если у пользователя всё равно заведомо нет прав выполнять необходимое действие)
		if(!($BDB->userAccessLevel & (AR_POSTING | AR_MODERATION | AR_SUPERVISING))) {
			$this->resType = 'error';
			$this->resData['error_msg'] = "User has no access rights to delete comments.";
			return false;
		}
		
		// Считать комментарий с заданным post_id
		$comment = $BDB->GetComment($commentId);
		
		// Проверить его существование
		if(!$comment) {
			$this->resType = 'error';
			$this->resData['error_msg'] = "Required comment doesn`t exists.";
			return false;
		}
		
		// Проверить наличие прав у пользовтеля на удаление конкретно заданного поста
		if(!$BDB->CanDeleteComment($comment['user_id'], $comment['post_author_id'], $comment['post_status'])) {
			$this->resType = 'error';
			$this->resData['error_msg'] = "User has no access rights to delete this post.";
			return false;
		}
		
		
		// Определить, есть ли подтверждение удаления
		if($this->form->validate()) {
			if($this->params['confirmed']) {
				// Если есть - удалить
				$result = $BDB->DeleteComment($commentId);
				
				if($result) {
					// При успшном удалении коммента, перенаправляем пользователя 
					// на страницу поста
					$this->resType = 'redirect';
					$this->resData = array(
							'to' => URL_ROOT.'posts/'.$comment['post_id'],
							'system_message' => 'Comment deleted successfully.'
						);
				} else {
					// Если произошла ошибка при удалении, выдаём сообщение об ошибке
					$this->resType = 'error';
					$this->resData = array('error_msg' => $BDB->errorMsg);
				}
				
				return;
				
			}
			
		}
		
		// Если подтверждения нет - выдать форму для подтверждения
		$this->buildForm();
		
		$this->resType = 'html';
		$this->resData = array(
				'form' => $this->form->toHtml(),
				'form_title' => 'Delete confirmation',
				'form_desc' => 'This will permanently delete selected comment from the database '.
					'and you will be not able to restore it later. Are you sure to proceed?'
			);
		
		return true;
		
	}

}

?>