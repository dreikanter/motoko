<?php

require 'HTML/QuickForm.php';

/**
 * Редактирует таг
 * Параметры: tag (string) - редактируемый таг.
 */
class EditTagAction extends AbstractAction {
	
	var $returnable = false;
	var $template = 'form';
	
	var $form;
	
	function EditTagAction($_params) {
		// Конструктор абстрактного класса
		$this->AbstractAction($_params);
		// Создаём форму
		$this->form = new HTML_QuickForm('edit_tag_form', 'post', 
			getenv("SCRIPT_NAME"), false, false, true);
	}
	
	function paramsOk() {
		$paramCnt = count($this->params);
		if(($paramCnt !== 1 && $paramCnt !== 3) || !isset($this->params['tag']) || 
			(isset($this->params['new_tag']) && !$BDB->TagSyntaxOk($this->params['new_tag']))) {
			$this->errorMsg = 'Bad parameters specified.';
			return false;
		}
		
		return true;
		
	}
	
	function buildForm() {
		$tag = urldecode($this->params['tag']);
		$this->form->addElement('hidden', 'action', substr(basename(__FILE__), 0, -16)); // 'edit_tag'
		$this->form->addElement('hidden', 'tag', $tag);
		$this->form->addElement('static', '', '', 
			'<img src="" width="100" height="1" alt="" style="width:21.5em;"><br>New tag name');
		
		$this->form->addElement('text', 'new_tag', '', 
			array('tabindex' => 1, 'style' => 'width:20em;margin-bottom:1mm;', 'class' => 'thin'));
		
		$buttons = array();
		// Кнопка сабмита формы
		$buttons[] = &HTML_QuickForm::createElement('submit', '', 'Save tag', 'class="green_submit"');
		$buttons[] = &HTML_QuickForm::createElement('button', '', 'Cancel', 
			array('class' => 'gray_submit', 'onclick' => 'javascript:window.location.href = \''.$_SESSION['ref'].'\';'));
		$this->form->addGroup($buttons);
		
		// Фильтр
		$this->form->applyFilter('text', 'trim');
		
		// Рулесы
		$msg = 'New tag must be specified.';
		$this->form->addRule('new_tag', $msg, 'required', '', 'client');
		
		$msg = 'Incorrect tag syntax specified.';
		$this->form->addRule('new_tag', $msg, 'regex', REGEXP_TAG, 'server');
		
		$this->form->setDefaults(array('new_tag' => $tag));
		
	}
	
	function execute() {
		global $BDB;
		
		$tag = urldecode($this->params['tag']);
		$newTag = isset($this->params['new_tag'])?$this->params['new_tag']:false;
		$paramCnt = count($this->params);
		
		// Проверяем право пользователя редактировать таги
		if(!$BDB->CanChangeTags()) {
			$this->resType = 'error';
			$this->resData = array('error_msg' => 'User have no access rights to edit tags.');
			return;
		}
		
		// How it works:
		// Если задан один параметр, выполнен запрос формы
		if($paramCnt == 1) {
			
			// Проверяем существование переименовываемого тага
			if(!$BDB->TagExists($tag)) {
				$this->resType = 'error';
				$this->resData = array('error_msg' => "Required tag doesn't exists.");
				return;
			}
			
			// Конструируем форму
			$this->buildForm();
			
			$this->resType = 'html';
			$this->resData = array(
					'form_title' => 'Rename tag',
					'form_desc' => 'Please specify the new name for the tag "'.$tag.'".',
					'form' => $this->form->toHtml()
				);
		}
		
		// Если пришло более одного параметра, знасит получены данные из формы
		else {
			
			if(!$this->form->validate()) {
				// Если данные из формы некорректны, повторно выдаём форму с сообщением об ошибке
				// (может быть задан таг с неправильным синтаксисом)
				$this->resType = 'html';
				$this->resData = array(
						'form_title' => 'Rename tag',
						'form_desc' => 'Please specify the new name for the tag "'.$tag.'".',
						'form' => $this->form->toHtml(),
						'error_msg' => 'Some error occured.'
					);
				return;
			}
			
			// Правим таг
			$result = $BDB->RenameTag($tag, $newTag);
			
			if(!$result) {
				// Если произошла ошибка при работе с БД, выдаём сообщение об ошибке
				$this->resType = 'error';
				$this->resData = array('error_msg' => $BDB->errorMsg);
				return;
			}
			
			// Если всё хорошо, делаем редирект
			$this->resType = 'redirect';
			$this->resData = array(
					'to' => 'back',
					'system_message' => 'Tag "'.$tag.'" successfully renamed to "'.$newTag.'".'
				);
			
		}
		
	}

}

?>