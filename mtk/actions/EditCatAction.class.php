<?php

require 'HTML/QuickForm.php';

/**
 * Редактирует категории и создаёт новые. При первой загрузке выдаёт 
 * форму (при создании категории, форма пустая, а при редактировании 
 * существующей - заполненная её данными), после сабмита делает редирект 
 * на список категорий.
 * Параметры: cat_id (int) - ID редактируемой категории
 */
class EditCatAction extends AbstractAction {
	
	var $returnable = false;
	var $template = 'editor';
	
	var $form;
	
	function EditCatAction($_params) {
		// Конструктор абстрактного класса
		$this->AbstractAction($_params);
		// Создаём форму
		$this->form = new HTML_QuickForm('edit_cat_form', 'post', 
			getenv("SCRIPT_NAME"), false, false, true);
	}
	
	function paramsOk() {
		// Action может получать параметры двух типов: 
		// либо это cat_id и больше ничего (запрос формы)
		$firstLaunch = count($this->params) == 1 && (isset($this->params['cat_id']) && 
			is_numeric($this->params['cat_id']));
		
		// либо это данные из формы
		$formVars = array('cat_id', 'shortcut', 'title', 'description');
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
		$catId = $this->params['cat_id'];
		$this->form->addElement('hidden', 'action', substr(basename(__FILE__), 0, -16)); // 'edit_cat'
		$this->form->addElement('hidden', 'cat_id', $catId);
		$this->form->addElement('text', 'title', 'Title:', 
			array('size' => 15, 'maxlength' => MAX_CAT_TITLE_LENGTH, 'tabindex' => 1, 
				'style' => 'width:16em;', 'class' => 'thin'));
		$this->form->addElement('text', 'shortcut', 'Shortcut:', 
			array('size' => 15, 'maxlength' => MAX_SHORTCUT_LENGTH, 'tabindex' => 2, 
				'style' => 'width:16em;', 'class' => 'thin'));
		$this->form->addElement('static', '', '', '<a href="#" onclick="javascript:document.getElementById(\'msg1\').className = \'shown_messagebox\';" class="hint">What is a category shortcut?</a><br><div title="Click to close" class="hidden_messagebox" onclick="javascript:document.getElementById(\'msg1\').className = \'hidden_messagebox\';" id="msg1">Shortcut is a alternative name of the category that meant for using in the URLs. For example http://example.com/<strong>category_shortcut</strong>. Shortcut can consist of latin letters, digits, dash and the underscore character.</div>');
		$this->form->addElement('textarea', 'description', 'Description:', 
			array('tabindex' => 5, 'maxlength' => MAX_CAT_DESC_LENGTH, 
				'style' => 'width:32em; height:4cm;'));
		
		$buttons = array();
		$buttons[] = &HTML_QuickForm::createElement('submit', '', 'Save category', 
			array('class' => 'green_submit'));
		$buttons[] = &HTML_QuickForm::createElement('button', '', 'Delete category', 
			array('class' => 'red_submit', 
				'onclick' => 'javascript:window.location.href = \''.URL_ROOT.'delete-cat/'.$catId.'\';'));
		$buttons[] = &HTML_QuickForm::createElement('button', '', 'Cancel', 
			array('class' => 'gray_submit', 
				'onclick' => 'javascript:window.location.href = \''.$_SESSION['ref'].'\';'));
		$this->form->addGroup($buttons);
		
		// Фильтры
		$this->form->applyFilter('title', 'trim');
		$this->form->applyFilter('shortcut', 'trim');
		$this->form->applyFilter('description', 'trim');
		
		// Рулесы
		$msg = 'Category title field is required.';
		$this->form->addRule('title', $msg, 'required', '', 'client');
		
		$msg = 'Category title maximum length limit exceeded.';
		$this->form->addRule('title', $msg, 'maxlength', MAX_CAT_TITLE_LENGTH, 'client');
		
		$msg = 'Incorrect shortcut syntax.';
		$this->form->addRule('shortcut', $msg, 'regex', REGEXP_SHORTCUT_PREG, 'server');
		
		$msg = 'Category description maximum length limit exceeded.';
		$this->form->addRule('description', $msg, 'maxlength', MAX_CAT_DESC_LENGTH, 'client');
		
	}
	
	function execute() {
		global $BDB;
		
		$catId = $this->params['cat_id'];
		$paramCnt = count($this->params);
		
		// Проверяем право пользователя редактировать категории
		if(!$BDB->CanManageCats()) {
			$this->resType = 'error';
			$this->resData = array('error_msg' => 'User have no access rights to edit categories.');
			return;
		}
		
		// Конструируем форму
		$this->buildForm();
		
		if($this->form->validate()) {
			// Пришли данные из формы и они корректны
			
			if($catId == -1) {
				// Создаётся новая категория
				
				$result = $BDB->AddCat(
						$this->params['shortcut'],
						$this->params['title'],
						$this->params['description']
					);
				
				if($result) {
					// Если всё хорошо, делаем редирект на список категорий
					$this->resType = 'redirect';
					$this->resData = array(
							'to' => URL_ROOT.'cats',
							'system_message' => 'Category created successfully. You may '.
								'see it <a href="'.URL_ROOT.$this->params['shortcut'].
								'">here</a> or <a href="'.URL_ROOT.'cid/'.$catId.
								'">there</a>.'
						);
					
					return;
					
				}
				
			} else {
				// Обновляем данные существующей категории и перенаправляем 
				// юзера на список категорий
				
				$catData = array(
						'title' => $this->params['title'],
						'shortcut' => $this->params['shortcut'],
						'description' => $this->params['description']
					);
				
				$result = $BDB->ChangeCat($catId, $catData);
				
				if($result) {
					// Если всё хорошо, делаем редирект на первую страницу категории
					$this->resType = 'redirect';
					$this->resData = array(
							'to' => URL_ROOT.'cats',
							'system_message' => 'Category data saved successfully. You may '.
								'see category contents <a href="'.URL_ROOT.$this->params['shortcut'].
								'">here</a> or <a href="'.URL_ROOT.'cid/'.$cat['cat_id'].
								'">there</a>.'
						);
					
					return;
					
				}
				
			}
			
			// Если произошла ошибка при выполнении BlogDB::AddCat или BlogDB::ChangeCat,
			// выводим форму повторно с сообщением об ошибке
			$this->resType = 'html';
			$this->resData = array(
					'cats' => $BDB->GetCats('title'),
					'popular_tags' => $BDB->GetPopularTags(POPULAR_TAGS_COUNT),
					'form' => $this->form->toHtml(),
					'error_msg' => $BDB->errorMsg,
					'page_title' => 'Category editor',
					'where_am_i' => 'Here you may edit a category.'
				);
			
		} else {
			// Пришли некрректные данные из формы или выполнен первый 
			// запуск action'а - запрос на форму
			
			if($paramCnt == 1) {
				
				if($catId == -1) {
					// Создаётся новая категория
					$formTitle = 'New category';
				} else {
					// Редактируется существующая категория
					$formTitle = 'Edit category';
					
					// Необходимо считать категорию, а заодно проверить её существование 
					$cat = $BDB->GetCat($catId);
					
					if(!$cat) {
						$this->resType = 'error';
						$this->resData = array('error_msg' => 'Required category doesn`t exists.');
						return;
					}
					
					// Заполняем форму данными из базы
					$this->form->setDefaults(array(
							'title' => $cat['title'],
							'shortcut' => $cat['shortcut'],
							'description' => $cat['description']
						));
					
				}
				
			}
			
			$this->resType = 'html';
			$this->resData = array(
					'form_title' => $formTitle,
					'cats' => $BDB->GetCats('title'),
					'popular_tags' => $BDB->GetPopularTags(POPULAR_TAGS_COUNT),
					'form' => $this->form->toHtml(),
					'page_title' => 'Category editor',
					'where_am_i' => 'Here you may edit a category.'
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