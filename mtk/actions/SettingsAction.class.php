<?php

require 'HTML/QuickForm.php';

/**
 * Выдаёт форму для редактирования настроек блога 
 * (константы в файле conf/settings.inc.php)
 */
class SettingsAction extends AbstractAction {
	
	var $returnable = false;
	var $template = 'editor';
	
	var $form;
	
	function SettingsAction($_params) {
		// Конструктор абстрактного класса
		$this->AbstractAction($_params);
		// Создаём форму
		$this->form = new HTML_QuickForm('settings_form', 'post', 
			getenv("SCRIPT_NAME"), false, false, true);
	}
	
	function paramsOk() {
		// Action может получать параметры двух типов: 
		// либо это post_id и больше ничего (запрос формы)
		$firstLaunch = count($this->params) == 0;
		
		// либо это данные из формы (добавление или обновление поста)
		$formVars = array('title', 'subtitle', 'abstract', 'posts_per_page', 
			'popular_tags_count', 'current_theme', 'lang');
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
		// Код конструирования формы выделен в отдельный метод
		// для разгрузки $this->execute()
		$this->form->addElement('hidden', 'action', substr(basename(__FILE__), 0, -16)); // 'settings'
		
		$this->form->addElement('text', 'title', 'Blog title:', 
			array('size' => 50, 'maxlength' => 100, 
				'tabindex' => 1, 'class' => 'thin'));
		
		$this->form->addElement('text', 'subtitle', 'Blog subtitle:', 
			array('size' => 50, 'maxlength' => 100, 
				'tabindex' => 2, 'class' => 'thin'));
		
		$this->form->addElement('textarea', 'abstract', 'Abstract:', 
			array('tabindex' => 3, 'maxlength' => 1024, 
				'style' => 'width:40em; height:8cm;', 'class' => 'thin'));
		
		$this->form->addElement('checkbox', 'check_nl2br', '', 
			'Use nl2br text filter by defaut', array('tabindex' => 4));
		
		$this->form->addElement('checkbox', 'check_typo', '', 
			'Use typography correction by defaut', array('tabindex' => 5));
		
		$this->form->addElement('checkbox', 'check_markdown', '', 
			'Use <a href="http://daringfireball.net/projects/markdown/syntax">markdown</a>', 
			array('tabindex' => 6));
		
		$this->form->addElement('text', 'posts_per_page', 'Posts per page number:', 
			array('tabindex' => 7));
		
		$this->form->addElement('text', 'popular_tags_count', 'Popular tags count:', 
			array('tabindex' => 8));
		
		$themes = array(); // array('default' => 'Default');
		
		function readThemeTitle($_theme) {
			$themeInfoFile = DIR_MTK.'templates/'.$_theme.'/info.php';
			if(!file_exists($themeInfoFile)) return $_theme;
			if(isset($themeTitle)) unset($themeTitle);
			require $themeInfoFile;
			return isset($themeTitle)?$themeTitle:$_theme;
		}
		
		// Считываем информацию о темах и заполняем ей список
		$dh = opendir(DIR_MTK.'templates');
		while(($nextFile = readdir($dh)) !== false) {
			if($nextFile == '.' || $nextFile == '..') continue;
			$title = readThemeTitle($nextFile);
			if($title) $themes[$nextFile] = $title;
		}
		closedir($dh);
		
		$this->form->addElement('select', 'current_theme', 'Themes:', $themes, 
			array('tabindex' => 9, 'style' => 'width:28em;'));
		
		$langs = array('default' => 'English (default)');
		$this->form->addElement('select', 'lang', 'Interface language:', $langs, 
			array('tabindex' => 10, 'style' => 'width:28em;'));
		
		$this->form->addElement('checkbox', 'stat_enabled', '', 
			'Enable user statistics', array('tabindex' => 11));
		
		$this->form->addElement('text', 'max_feed_length', 'Feeds length', 
			array('tabindex' => 12));
		
		$this->form->addElement('static', '', '', '<small class="silver">(Maximum post numbers for XML exporting)</small>');
		
		$this->form->addElement('checkbox', 'complete_xml_export', '', 
			'Complete post export', array('tabindex' => 13));
		
		$this->form->addElement('static', '', '', '<small class="silver">(Enable or disable cuts in the XML feeds)</small>');
		
		$this->form->addElement('checkbox', 'email_notification', '', 
			'Email notification', array('tabindex' => 14));
		
		$this->form->addElement('static', '', '', '<small class="silver">(Enable or disable email notification performing for new comments)</small>');
		
		$buttons = array();
		
		// Кнопка сабмита формы
		$buttons[] = &HTML_QuickForm::createElement('submit', '', 'Save settings', 
			array('class' => 'green_submit', 'tabindex' => 15));
		
		// Кнопка отмены (JS редирект на предыдущую страницу)
		$buttons[] = &HTML_QuickForm::createElement('button', '', 'Cancel', 
			array(
					'class' => 'gray_submit',
					'onclick' => 'javascript:window.location.href = \''.$_SESSION['ref'].'\';',
					'tabindex' => 16
			));
		
		$this->form->addGroup($buttons);
		
		// Добавляем правила проверки данных формы
		$msg = 'Blog title is empty.';
		$this->form->addRule('title', $msg, 'required', '', 'client');
		
		$msg = 'Blog subtitle is empty.';
		$this->form->addRule('subtitle', $msg, 'required', '', 'client');
		
		$msg = 'Blog subtitle maximum length limit exceeded.';
		$this->form->addRule('subtitle', $msg, 'maxlength', 100, 'client');
		
		$msg = 'Blog subtitle maximum length limit exceeded.';
		$this->form->addRule('subtitle', $msg, 'maxlength', 100, 'client');
		
		$msg = 'Posts per page number must be specified.';
		$this->form->addRule('posts_per_page', $msg, 'required', '', 'client');
		
		$msg = 'Posts per page number must be specified.';
		$this->form->addRule('posts_per_page', $msg, 'required', '', 'client');
		
		$msg = 'Popular tags count must be specified.';
		$this->form->addRule('popular_tags_count', $msg, 'required', '', 'client');
		
		$msg = 'Posts per page value must be numeric.';
		$this->form->addRule('posts_per_page', $msg, 'numeric', '', 'client');
		
		$msg = 'Popular tags value must be numeric.';
		$this->form->addRule('popular_tags_count', $msg, 'numeric', '', 'client');
		
		// Регистрируем правило для проверки попадания целочисленного 
		// значения в заданный интервал
		$this->form->registerRule('in_range', 'function', 'in_range');
		
		$msg = 'Posts per page value must be in interval from 1 to 100.';
		$this->form->addRule('posts_per_page', $msg, 'in_range', "1,100", 'server');
		
		$msg = 'Popular tags value must be in interval from 1 to 50.';
		$this->form->addRule('popular_tags_count', $msg, 'in_range', "1,50", 'server');
		
		$msg = 'Maximum feed length value must be numeric.';
		$this->form->addRule('max_feed_length', $msg, 'numeric', '', 'client');
		
		// Фильтры
		$this->form->applyFilter('title', 'trim');
		$this->form->applyFilter('subtitle', 'trim');
		$this->form->applyFilter('abstract', 'trim');
		$this->form->applyFilter('posts_per_page', 'trim');
		$this->form->applyFilter('popular_tags_count', 'trim');
		
		// Заполняем фому стандартными значенимя, соответствующими 
		// текущим настройкам
		$this->form->setDefaults(array(
				'title' => BLOG_TITLE,
				'subtitle' => BLOG_SUBTITLE,
				'abstract' => BLOG_ABSTRACT,
				'posts_per_page' => (int)POSTS_PER_PAGE,
				'popular_tags_count' => (int)POPULAR_TAGS_COUNT,
				'check_nl2br' => (bool)DEF_USE_FILTER_NL2BR,
				'check_typo' => (bool)DEF_USE_FILTER_TYPO,
				'check_markdown' => (bool)DEF_USE_FILTER_MARKDOWN,
				'current_theme' => CURRENT_THEME,
				'lang' => LANG,
				'stat_enabled' => (bool)STAT_ENABLED,
				'max_feed_length' => (int)MAX_FEED_LENGTH,
				'complete_xml_export' => (bool)COMPLETE_XML_EXPORT,
				'email_notification' => (bool)EMAIL_NOTIFICATION
			));
		
	}
	
	function execute() {
		global $BDB;
		
		// Проверяем право пользователя управлять натройками блога
		// (это разрешено только супервизорам)
		if(!$BDB->userAccessLevel) {
			$this->resType = 'error';
			$this->resDAta = array('error_msg' => 'User have no access rights to manage blog settings.');
			return;
		}
		
		// Конструируем форму
		$this->buildForm();
		
		if($this->form->validate()) {
			// Пришли корректные данные из формы
			$values = $this->form->getSubmitValues();
			
			$settings = array(
					'BLOG_TITLE' => $values['title'],
					'BLOG_SUBTITLE' => $values['subtitle'],
					'BLOG_ABSTRACT' => $values['abstract'],
					'BLOG_ABSTRACT_CACHED' => $BDB->FilterText($values['abstract'], FILTERS_DESC),
					'POSTS_PER_PAGE' => (int)$values['posts_per_page'],
					'POPULAR_TAGS_COUNT' => (int)$values['popular_tags_count'],
					'DEF_USE_FILTER_NL2BR' => isset($values['check_nl2br'])?((int)$values['check_nl2br']):0,
					'DEF_USE_FILTER_TYPO' => isset($values['check_typo'])?((int)$values['check_typo']):0,
					'DEF_USE_FILTER_MARKDOWN' => isset($values['check_markdown'])?((int)$values['check_markdown']):0,
					'CURRENT_THEME' => $values['current_theme'],
					'LANG' => $values['lang'],
					'STAT_ENABLED' => $values['stat_enabled'],
					'MAX_FEED_LENGTH' => (int)$values['max_feed_length'],
					'COMPLETE_XML_EXPORT' => isset($values['complete_xml_export'])?((int)$values['complete_xml_export']):0,
					'EMAIL_NOTIFICATION' => isset($values['email_notification'])?((int)$values['email_notification']):0
				);
			
			$result = generateConf(DIR_CONF.'settings.inc.php', $settings);
			if(!$result) {
				$this->resType = 'error';
				$this->resData = array('error_msg' => 'User have no access rights to manage blog settings.');
				return;
			}
			
			// Выполняем редирект на главную страницу блога
			$this->resType = 'redirect';
			$this->resData['to'] = URL_ROOT;
			$this->resData['system_message'] = 'Blog settings saved successfully. You may correct it <a href="'.
				URL_ROOT.'settings">here</a> anytime.';
			
		} else {
			// Запрос на форму (первый запуск action'а)
			$this->resType = 'html';
			$this->resData = array(
					'cats' => $BDB->GetCats('title'),
					'popular_tags' => $BDB->GetPopularTags(POPULAR_TAGS_COUNT),
					'form' => $this->form->toHtml(),
					'form_title' => 'Blog settings'
				);
			
		}
		
	}

}

function in_range($_element, $_value, $_range) {
	
	// Вытаскиваем минимальное и максимальное значение из параметра $_range
	list($minValue, $maxValue) = explode(',', $_range, 2);
	$minValue = (int)trim($minValue);
	$maxValue = (int)trim($maxValue);
	
	return ((int)$_value >= $minValue) && ((int)$_value <= $maxValue);
	
}

?>