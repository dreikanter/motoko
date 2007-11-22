<?php

require 'HTML/QuickForm.php';

/**
 * Удаление тага. action выдаёт форму для подтверждения удаления.
 * Параметры: tag (string) - удаляемый таг; confirmed (int) - флаг, определяющий подтверждённость намерения пользователы удалить таг (отличное от 0 значение означает, что подтверждение есть).
 */
class DeleteTagAction extends AbstractAction {
	
	var $returnable = false;
	var $template = 'form';
	
	var $catsList = array();
	
	var $form;
	
	function DeleteTagAction($_params) {
		// Конструктор абстрактного класса
		$this->AbstractAction($_params);
		// Создаём форму
		$this->form = new HTML_QuickForm('delete_tag_form', 'post', 
			getenv("SCRIPT_NAME"), false, false, true);
	}
	
	function paramsOk() {
		$paramCnt = count($this->params);
		
		if(($paramCnt !== 1 && $paramCnt !== 3) || !isset($this->params['tag']) ||
			(isset($this->params['confirmed']) && !is_numeric($this->params['confirmed']))) {
			
			return false;
		}
		
		return true;
		
	}
	
	function buildForm() {
		// Код конструирования формы выделен в отдельный метод
		// для разгрузки $this->execute()
		$tag = $this->params['tag'];
		$this->form->addElement('hidden', 'action', substr(basename(__FILE__), 0, -16)); // 'delete_tag'
		$this->form->addElement('hidden', 'tag', $tag);
		$this->form->addElement('hidden', 'confirmed', 1);
		
		$buttons = array();
		// Кнопка сабмита формы
		$buttons[] = &HTML_QuickForm::createElement('submit', '', 'Delete tag', 
			array('class' => 'red_submit'));
		$buttons[] = &HTML_QuickForm::createElement('button', '', 'Cancel', 
			array('class' => 'gray_submit', 'onclick' => 
				'javascript:window.location.href = \''.$_SESSION['ref'].'\';'));
		$this->form->addGroup($buttons);
		
	}
	
	function execute() {
		global $BDB;
		
		$tag = $this->params['tag'];
		$paramCnt = count($this->params);
		
		// Проверяем право пользователя удалять таги
		if(!$BDB->CanDeleteTags()) {
			$this->resType = 'error';
			$this->resData = array('error_msg' => 'User have no access rights to delete tags.');
			return;
		}
		// How it works:
		// Если задан один параметр, выполнен запрос формы
		if($paramCnt == 1) {
			
			// Проверка существования удаляемого тага
			if(!$BDB->TagExists($tag)) {
				$this->resType = 'error';
				$this->resData = array('error_msg' => "Required tag doesn't exists.");
				return;
			}
			
			// Конструируем форму
			$this->buildForm();
			
			$this->resType = 'html';
			$this->resData = array(
					'form_title' => 'Delete confirmation',
					'form_desc' => 'This action can not be undone. Are you sure to delete tag "'.$tag.'".',
					'form' => $this->form->toHtml()
				);
		}
		
		// Если пришло более одного параметра, знасит получены данные из формы
		elseif($this->params['confirmed']) {
			
			// Удаляем таг
			$result = $BDB->DeleteTag($tag);
			
			if($result) {
				// Если всё хорошо, делаем редирект
				$this->resType = 'redirect';
				$this->resData = array(
						'to' => 'back',
						'system_message' => 'Tag "'.$tag.'" deleted successfully.'
					);
			} else {
				// В случае ошибки, выводим сообщение
				$this->resType = 'error';
				$this->resData = array('error_msg' => $BDB->errorMsg);
			}
			
		} else {
			// Подстверждения не было (в этом случае пользователь должен быть перенаправлен)
			$this->resType = 'redirect';
			$this->resData = array(
					'to' => 'back',
					'system_message' => 'Tag "'.$tag.'" deleted successfully.'
				);
			
			return;
			
		}
		
		// Если данные из формы некорректны, выдаём форму повторно
		
	}

}

?>