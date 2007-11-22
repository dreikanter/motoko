<?php

require 'HTML/QuickForm.php';

/**
 * Выдаёт форму для написания нового поста, обрабатывает её данные и сохраняет в базу.
 * Параметры: post_id - если параметру задано значение >= 0, action откроет заданный пост 
 * для редактирования. Если задано знаечние -1 - откроет пустую форму для создания нового 
 * поста.
 */

class PostEditAction extends AbstractAction {
	
	var $returnable = false;
	var $template = 'editor';
	
	var $form;
	
	function PostEditAction($_params) {
		// Конструктор абстрактного класса
		$this->AbstractAction($_params);
		// Создаём форму
		$this->form = new HTML_QuickForm('post_editor_form', 'post', 
			getenv("SCRIPT_NAME"), false, false, true);
	}
	
	function paramsOk() {
		// Action может получать параметры двух типов: 
		// либо это post_id и больше ничего (запрос формы)
		$firstLaunch = count($this->params) == 1 && (isset($this->params['post_id']) && 
			(is_numeric($this->params['post_id']) || $this->params['post_id'] == -1));
		
		// либо это данные из формы (добавление или обновление поста)
		$formVars = array('post_id', 'title', 'text', 'status', 'cat_shortcut', 
			'tags', 'shortcut', 'ctime');
		// В списке переменных, полученных из формы могут не присутствовать чекбоксы.
		// Особенность работы чекбоксов
		// new_cat_shortcut - не присутствует в списке, т.к. он опционален, и может 
		// приходить из формы только если пользователь имеет право создавать новые категории
		
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
		// Код конструирования формы выделен в отдельный метод
		// для разгрузки $this->execute()
		
		// Форма: 
		// + title - заголовок поста (сабж), 
		// + text - текст поста, 
		// + cat_shortcut - shortcut категории (выбирается из списка или задаётся руками), 
		// + new_cat_shortcut - shortcut для новой категории, которая будет автоматически создана
		//   отобрадается только для пользователей, обладающих правом создавать новые категории
		// + tags - список тагов (задаётся через запятую), 
		// + status - статус поста (задаётся из списка), 
		// + shortcut - shortcut поста (опционально), 
		// + дата создания поста, 
		// + используемые фильтры для текста (nl2br, typografy corrector).
		$postId = $this->params['post_id'];
		
		$statusList = array(
				PS_PUBLIC => "Public (visible for all)",
				PS_PRIVATE => "Private (visible only for author)",
				PS_COMMUNITY => "Community (registered users only)",
				PS_DRAFT => "Draft (author only)"
			);
		
		$cats = $BDB->GetCats('title', 0, false);
		$catList = array();
		foreach($cats as $cat) {
			$catList[$cat['shortcut']] = $cat['title'];
		}
		
		// Переменная будет использована для определения категории, 
		// задаваемой по-умолчанию для новых постов
		$catUncat = $cats[count($cats)]['shortcut'];
		
		$this->form->addElement('hidden', 'action', substr(basename(__FILE__), 0, -16)); // 'post'
		$this->form->addElement('hidden', 'post_id', $postId);
		
		$this->form->addElement('text', 'title', 'Title:', 
			array('size' => 50, 'maxlength' => MAX_POST_TITLE_LENGTH, 
				'tabindex' => 1, 'class' => 'thin'));
		$this->form->addElement('textarea', 'text', 'Content:', 
			array('tabindex' => 2, 'maxlength' => MAX_POST_LENGTH, 
				'style' => 'width:40em; height:8cm;', 'class' => 'thin'));
		
		$filterNl2brField = &HTML_QuickForm::createElement('checkbox', 'check_nl2br', '', 
			'Use nl2br');
		$filterTypoField = &HTML_QuickForm::createElement('checkbox', 'check_typo', '', 
			'Use typographic corrector', array('style' => 'margin-left:5mm;'));
		$filterMarkdownField = &HTML_QuickForm::createElement('checkbox', 'check_markdown', '', 
			'Use <a href="http://daringfireball.net/projects/markdown/syntax">markdown</a>', 
			array('style' => 'margin-left:5mm;'));
		$filters = array($filterNl2brField, $filterTypoField, $filterMarkdownField);
		$this->form->addGroup($filters, null, 'Text filters:');
		
		$this->form->addElement('static', '', '', '<br>');
		
		$this->form->addElement('static', '', '', '<strong><big>Posting parameters:</big></strong>');
		$this->form->addElement('select', 'status', 'Post status:', $statusList, 
			array('tabindex' => 3, 'style' => 'width:28em;'));
		$this->form->addElement('select', 'cat_shortcut', 'Category:', $catList, 
			array('tabindex' => 4, 'style' => 'width:28em;'));
		
		if($BDB->CanManageCats()) {
		$this->form->addElement('static', '', '', '<small>If you want to place this '.
			'post into the new category, specify it`s shortcut <br>in the field below and it '.
			'will be automatically created:</small>');
			// Создавать новые категории могут только те, кто имеет на это право
			$this->form->addElement('text', 'new_cat_shortcut', 'New category:', 
				array('size' => 50, 'tabindex' => 5, 'style' => 'width:28em;', 'class' => 'thin'));
		$this->form->addElement('static', '', '', '<br>');
		}
		
		$this->form->addElement('text', 'tags', 'Tags:', 
			array('size' => 50, 'maxlength' => 100, 'tabindex' => 6, 'style' => 'width:28em;', 
				'class' => 'thin'));
		$this->form->addElement('static', '', '', '<small>(comma-separated enumeration of tags )</small>');
		$this->form->addElement('text', 'shortcut', 'Shortcut:', 
			array('size' => 50, 'maxlength' => 100, 'tabindex' => 7, 'style' => 'width:28em;', 
				'class' => 'thin'));
		$this->form->addElement('static', '', '', 
			'<small>(specify a shortcut if you want to associate a short URL with this post)</small>');
		$options = array('language' => 'en', 'format' => 'd / M / Y @ H : i : s', 'minYear' => 1970);
		$this->form->addElement('date', 'ctime', 'Timestamp:', $options);
		
		$this->form->addElement('static', '', '', '<br>');
		
		$buttons = array();
		// Кнопка сабмита формы
		$buttons[] = &HTML_QuickForm::createElement('submit', '', 'Save post', 
			array('class' => 'green_submit'));
		
		// Кнопка редиректа на DeletePostAction с post_id текущего поста
		if($this->params['post_id'] != -1) {
			// Кнопка стирания поста не нужна на форме добавления нового поста
			$buttons[] = &HTML_QuickForm::createElement('button', '', 'Delete post', 
				array('class' => 'red_submit', 
					'onclick' => 'javascript:window.location.href = \''.URL_ROOT.'delete-post/'.$postId.'\';'));
		}
		
		$buttons[] = &HTML_QuickForm::createElement('button', '', 'Cancel', 
			array('class' => 'gray_submit',
				'onclick' => 'javascript:window.location.href = \''.$_SESSION['ref'].'\';'));
		
		$this->form->addGroup($buttons);
		
		// Добавляем правила проверки данных формы
		// title, text, check_nl2br, check_typo, check_markdown, cat_shortcut, [new_cat_shortcut], tags, shortcut, ctime
		$msg = 'Post title is empty.';
		$this->form->addRule('title', $msg, 'required', '', 'client');
		
		$msg = 'Post title maximum length limit exceeded.';
		$this->form->addRule('title', $msg, 'maxlength', MAX_POST_TITLE_LENGTH, 'client');
		
		$msg = 'Post content is empty.';
		$this->form->addRule('text', $msg, 'required', '', 'client');
		
		$msg = 'Post content maximum length limit exceeded.';
		$this->form->addRule('text', $msg, 'maxlength', MAX_POST_LENGTH, 'client');
		
		
		if($BDB->CanManageCats()) {
			$msg = 'New category shortcut maximum length limit exceeded.';
			$this->form->addRule('new_cat_shortcut', $msg, 'maxlength', MAX_SHORTCUT_LENGTH, 'client');
		}
		
		$msg = 'Tags list maximum length limit exceeded.';
		$this->form->addRule('tags', $msg, 'maxlength', MAX_TAG_LENGTH * 10, 'client');
		
		// Фильтры
		$this->form->applyFilter('title', 'trim');
		$this->form->applyFilter('text', 'trim');
		$this->form->applyFilter('cat_shortcut', 'trim');
		
		if($BDB->CanManageCats()) {
			$this->form->applyFilter('new_cat_shortcut', 'trim');
		}
		
		$this->form->applyFilter('tags', 'trim');
		$this->form->applyFilter('shortcut', 'trim');
		
		$this->form->setDefaults(array("cat_shortcut" => $catUncat));
		
	}
	
	function execute() {
		global $BDB;
		
		$postId = $this->params['post_id'];
		$paramCnt = count($this->params);
		
		// Конструируем форму
		$this->buildForm();
		
		if($this->form->validate()) {
			// Пришли корректные данные из формы
			if($postId == -1) {
				// Получены данные для создания нового поста
				
				// Выполняем проверку прав текущего пользователя на написание нового поста
				if(!$BDB->CanAddPost()) {
					$this->resType = "error";
					$this->resData = array("error_msg" => "User have no access rights to write new posts.");
					return;
				}
				
				// Преобразуем полученные из формы данные для добавления в БД
				$values = $this->form->exportValues();
				
				$title = $values['title'];
				$content = $values['text'];
				$status = $values['status'];
				$shortcut = $values['shortcut'];
				$ctime = $values['ctime'];
				$ctime = mktime($ctime['H'], $ctime['i'], $ctime['s'], $ctime['M'], $ctime['d'], $ctime['Y']);
				$catShortcut = ($values['new_cat_shortcut']?
					$values['new_cat_shortcut']:$values['cat_shortcut']);
				$catShortcut = $catShortcut?$catShortcut:false;
				
				$tags = trim($values['tags']);
				if($tags) {
					$tags = explode(",", $tags);
					foreach($tags as $i => $tag) $tags[$i] = trim($tag);
				} else {
					$tags = array();
				}
				
				$filters = 0;
				if($values['check_nl2br']) $filters |= FILTER_NL2BR;
				if($values['check_typo']) $filters |= FILTER_TYPO;
				if($values['check_markdown']) $filters |= FILTER_MARKDOWN;
				
				// Делаем новый пост
				$result = $BDB->AddPost($title, $content, $status, $shortcut,
						$ctime, $catShortcut, $tags, $filters);
				
			} else {
				// Получены данные для правки существующего поста
				
				// Считываем пост
				$post = $BDB->GetPost($postId);
				
				// Проверяем его существование
				if($post === false) {
					// Если пост не считался, значит он либо не существет, либо у пользователя 
					// нет прав на его чтение (а значит и на правку). Отдаём сообщение 
					// о произошедшей ощибке.
					$this->resType = "error";
					$this->resData = array("error_msg" => $BDB->errorMsg);
					return;
				}
				
				// Пост успешно считался. Необходимо проверить право текущего пользователя 
				// на правку этого поста.
				if(!$BDB->CanChangePost($post['user_id'], $post['status'])) {
					$this->resType = "error";
					$this->resData = array("error_msg" => "User have no access rights to edit posts.");
					return;
				}
				
				// Преобразуем полученные из формы данные для добавления в БД
				$values = $this->form->exportValues();
				
				$ctime = $values['ctime'];
				$ctime = mktime($ctime['H'], $ctime['i'], $ctime['s'], $ctime['M'], $ctime['d'], $ctime['Y']);
				$catShortcut = ($values['new_cat_shortcut']?
					$values['new_cat_shortcut']:$values['cat_shortcut']);
				$catShortcut = $catShortcut?$catShortcut:false;
				
				$tags = trim($values['tags']);
				if($tags) {
					$tags = explode(",", $tags);
					foreach($tags as $i => $tag) $tags[$i] = trim($tag);
				} else {
					$tags = array();
				}
				
				$filters = 0;
				if(isset($values['check_nl2br']) && $values['check_nl2br']) $filters |= FILTER_NL2BR;
				if(isset($values['check_typo']) && $values['check_typo']) $filters |= FILTER_TYPO;
				if(isset($values['check_markdown']) && $values['check_markdown']) $filters |= FILTER_MARKDOWN;
				
				$newValues = array(
						'title' => $values['title'],
						'content' => $values['text'],
						'status' => $values['status'],
						'shortcut' => $values['shortcut'],
						'ctime' => $ctime,
						'mtime' => time(),
						'cat_shortcut' => $catShortcut,
						'tags' => $tags,
						'filters' => $filters
					);
				
				// Обновляем пост
				$result = $BDB->ChangePost($postId, $newValues);
				
			}
			
			// Переменная $result содержит результат, возвращённый методом BlogDB::ChangePost или BlogDB::AddPost. 
			// Если это ID созданного или изменённого поста, значит соответствующий метод отработал нормально, 
			// и требуется выполнить редирект на страницу поста. Иначе повторно выдаётся форма редактирования поста 
			// с сообщением об ошибке
			if(is_numeric($result)) {
				// Если всё в порядке, редиректим юзера на реферера с сообщением о том, что всё хорошо
				$this->resType = 'redirect';
				$this->resData = array(
						'to' => URL_ROOT.'posts/'.$result,
						'system_message' => 'Post saved successfully.'
					);
				
			} else {
				// Если произошла ошибка при занесении данных в базу,
				// повторно выдаём заполненную форму с сообщением об ошибке
				
				// Заполняем форму полученными данными
				// Почему-то (!) форма и так уже заполнена чем надо
				// Видимо, особенность работы HTML_Quckform
				
				$this->resType = 'html';
				$this->resData = array(
						'post_id' => $postId,
						'cats' => $BDB->GetCats('title'),
						'popular_tags' => $BDB->GetPopularTags(POPULAR_TAGS_COUNT),
						'form' => $this->form->toHtml(),
						'error_msg' => $BDB->errorMsg,
						'page_title' => 'Post editor',
						'where_am_i' => 'From this page you may edit a post or create a new one.'
					);
				
			}
			
		} else {
			// Выполняется запрос формы редактирования поста или создания нового поста
			
			if($postId == -1) {
				// Выполняется запрос формы на создание нового поста
				
				// Выполняем проверку прав текущего пользователя на написание нового поста
				if(!$BDB->CanAddPost()) {
					$this->resType = "error";
					$this->resData = array("error_msg" => "User have no access rights to write new posts.");
					return;
				}
				
				// Заполняем форму стандартными значениями для написания нового поста
				$this->form->setDefaults(array(
						"ctime" => time(), 
						"check_nl2br" => (int)DEF_USE_FILTER_NL2BR, 
						"check_typo" => (int)DEF_USE_FILTER_TYPO,
						"check_markdown" => (int)DEF_USE_FILTER_MARKDOWN)
					);
				
				$pageTitle = 'New post';
				
			} else {
				// Запрос формы для редактирования существующего поста
				
				// Считываем пост
				$post = $BDB->GetPost($postId);
				
				// Проверяем его существование
				if($post === false) {
					// Если пост не считался, значит он либо не существет, либо у пользователя 
					// нет прав на его чтение (а значит и на правку). Отдаём сообщение 
					// о произошедшей ощибке.
					$this->resType = "error";
					$this->resData = array("error_msg" => $BDB->errorMsg);
					return;
				}
				
				// Пост успешно считался. Необходимо проверить право текущего пользователя 
				// на правку этого поста.
				if(!$BDB->CanChangePost($post['user_id'], $post['status'])) {
					$this->resType = "error";
					$this->resData = array("error_msg" => "User have no access rights to edit posts.");
					return;
				}
				
				// Заполняем форму данными из БД, для правки существующего поста
				$this->form->setDefaults(array(
						"post_id" => $post['post_id'],
						"title" => $post['title'],
						"text" => $post['content'],
						"check_nl2br" => ($post['filters'] & FILTER_NL2BR)?1:0,
						"check_typo" => ($post['filters'] & FILTER_TYPO)?1:0,
						"check_markdown" => ($post['filters'] & FILTER_MARKDOWN)?1:0,
						"cat_shortcut" => $post['cat_shortcut'],
						"new_cat_shortcut" => '',
						"tags" => implode(", ", $post['cached_tags']),
						"shortcut" => $post['shortcut'],
						"ctime" => $post['ctime'],
						"status" => $post['status']
					));
				
				$pageTitle = 'Post editor';
				
			}
			
			// Отдаём форму
			$this->resType = 'html';
			$this->resData = array(
					'post_id' => $postId,
					'cats' => $BDB->GetCats('title'),
					'popular_tags' => $BDB->GetPopularTags(POPULAR_TAGS_COUNT),
					'form' => $this->form->toHtml(),
					'form_title' => ($postId == -1)?'New post':'Edit post',
					'page_title' => $pageTitle,
					'where_am_i' => 'From this page you may edit a post or create a new one.'
				);
			
		}
		
	}

}

?>