<?php

require 'HTML/QuickForm.php';

/**
 * Удаление категории. action выдаёт форму для выбора между переносом постов удаляемой 
 * из категории в одну из существующих, или для удаления постов насовсем.
 * Параметры: cat_id (int) - ID удаляемой категории; new_cat_id (int) - ID категории,
 * в которую будут перенесены посты из удаляемой категории (принимает значение -1, если 
 * требуется удалить посты навеки).
 */
class DeleteCatAction extends AbstractAction {
	
	var $returnable = false;
	var $template = 'form';
	
	var $catsList = array();
	
	var $form;
	
	function DeleteCatAction($_params) {
		// Конструктор абстрактного класса
		$this->AbstractAction($_params);
		// Создаём форму
		$this->form = new HTML_QuickForm('delete_cat_form', 'post', 
			getenv("SCRIPT_NAME"), false, false, true);
	}
	
	function paramsOk() {
		// Параметров может быть 1 или 2
		$paramCnt = count($this->params);
		if(($paramCnt != 1 && $paramCnt != 3) ||
			!isset($this->params['cat_id']) || !is_numeric($this->params['cat_id']) ||
			(isset($this->params['new_cat_id']) && !is_numeric($this->params['new_cat_id']))) {
			
			$this->errorMsg = 'Bad parameters specified.';
			return false;
		}
		
		return true;
		
	}
	
	function buildForm() {
		// Код конструирования формы выделен в отдельный метод
		// для разгрузки $this->execute()
		$catId = $this->params['cat_id'];
		$this->form->addElement('hidden', 'action', substr(basename(__FILE__), 0, -16)); // 'delete_cat'
		$this->form->addElement('hidden', 'cat_id', $catId);
		$this->form->addElement('static', '', '', 
			'<img src="" width="100" height="1" alt="" style="width:21.5em;">');
		
		$this->form->addElement('select', 'new_cat_id', '', $this->catsList, 
			array('tabindex' => 1, 'style' => 'width:7cm; margin-bottom:1mm;'));
		
		$buttons = array();
		// Кнопка сабмита формы
		$buttons[] = &HTML_QuickForm::createElement('submit', '', 'Delete category', 
			array('tabindex' => 2, 'class' => 'red_submit'));
		$buttons[] = &HTML_QuickForm::createElement('button', '', 'Cancel', 
			array('tabindex' => 3, 'class' => 'gray_submit', 
				'onclick' => 'javascript:window.location.href = \''.$_SESSION['ref'].'\';'));
		$this->form->addGroup($buttons);
		
		$this->form->setDefaults(array('new_cat_id' => 0, 'what_to_do' => 1));
		
	}
	
	function execute() {
		global $BDB;
		
		$catId = $this->params['cat_id'];
		$newCatId = isset($this->params['new_cat_id'])?$this->params['new_cat_id']:-1;
		$paramCnt = count($this->params);
		
		// Категорию uncategorized (cat_id == 0) удалить нельзя
		if($catId == 0) {
			$this->resType = 'error';
			$this->resData = array('error_msg' => 'This is a special category that can not be removed.');
			return;
		}
		
		// Проверяем право пользователя редактировать категории
		if(!$BDB->CanManageCats()) {
			$this->resType = 'error';
			$this->resData = array('error_msg' => 'User have no access rights to delete categories.');
			return;
		}
		
		// Считываем категории для того, чтобы определить существование 
		// удаляемой и составить список для формы
		$cats = $BDB->GetCats('title', 0, false);
		
		// МАссив для пра\оверки в\существования категорий с заданными ID
		$catIds = array();
		
		foreach($cats as $num => $cat) {
			$catIds[] = $cat['cat_id'];
			if($cat['cat_id'] == 0) {
				$uncatTitle = $cat['title'];
			} else {
				$this->catsList[$cat['cat_id']] = $cat['title'];
			}
		}
		
		// Uncategorized - последняя категория в списке
		$this->catsList[0] = $uncatTitle;
		
		// Удаляемая категория не должна присутствовать в списке
		unset($this->catsList[$catId]);
		
		// Проверка существования удаляемой категории
		if(!in_array($catId, $catIds)) {
			$this->resType = 'error';
			$this->resData = array('error_msg' => "Required category doesn't exists.");
			return;
		}
		
		// How it works:
		// Если задан один параметр, выполнен запрос формы
		if($paramCnt == 1) {
			
			// Конструируем форму
			$this->buildForm();
			
			$this->resType = 'html';
			$this->resData = array(
					'form_title' => 'Delete confirmation',
					'form_desc' => 'Please select a category where posts from the deleted one will be stored.',
					'form' => $this->form->toHtml()
				);
		}
		
		// Если пришло более одного параметра, знасит получены данные из формы.
		else {
			$result = $BDB->DeleteCat($catId, $newCatId);
			
			if($result) {
				// Если всё хорошо, делаем редирект
				$this->resType = 'redirect';
				$this->resData = array(
						'to' => URL_ROOT.'cats',
						'system_message' => 'Category deleted successfully. All posts have been moved to '.
							'"<a href="'.URL_ROOT.'cid/'.$newCatId.'">'.$this->catsList[$newCatId].'</a>" category.'
					);
				
			} else {
				// Если данные из формы некорректны, выдаём форму повторно
				$this->resType = 'html';
				$this->resData = array(
						'form' => $this->form->toHtml(),
						'error_msg' => $BDB->errorMsg
					);
				
			}
			
		}
		
	}

}

?>