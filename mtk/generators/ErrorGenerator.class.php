<?php

require DIR_MTK.'lib/template_lite/class.template.php';

class ErrorGenerator extends AbstractGenerator {
	
	function ErrorGenerator(&$_performedAction) {
		
		$this->hdr = array(
				"HTTP/1.0 200 Ok", 
				"Status: 200 Ok", 
				"Content-Type: text/html; charset=utf-8"
			);
		
		$this->returnable = $_performedAction->returnable;
		
		$tpl = new Template_Lite;
		
		// Где что лежит
		$tpl->template_dir = DIR_MTK."templates/".CURRENT_THEME;
		$tpl->config_dir = DIR_MTK."templates/".CURRENT_THEME."/";
		$tpl->compile_dir = DIR_DATA."templates-cache/";
		
		// Popup debug console for Template Lite
		// $tpl->debugging = DEBUG_MODE;
		
		$tpl->force_compile = true;
		$tpl->compile_check = true;
		$tpl->cache = false;
		$tpl->cache_lifetime = 3600;
		$tpl->config_overwrite = false;
		
		$resData = is_array($_performedAction->resData)?$_performedAction->resData:array();
		
		// Переносим в темплейт данные из action-а
		foreach($resData as $name => $value) { 
			$tpl->assign($name, $value);
		}
		
		// Дополнительные переменные - данные сессии и список переменных 
		// темплейта. Нужно только при отладке.
		if(DEBUG_MODE) {
			$tpl->assign("_session", "<pre>".print_r($_SESSION, true)."</pre>");
			$tpl->assign("_items", "<pre>".
				print_r(array_keys($_performedAction->resData), true)."</pre>");
		}
		
		// Единые для всех страниц значения
		$tpl->assign('_root_url', URL_ROOT);
		$tpl->assign('_tpl_url', URL_ROOT.'mtk-tpl/'.CURRENT_THEME.'/');
		
		/*$tpl->assign('system_message', $_SESSION['system_message']);
		$_SESSION['system_message'] = '';*/
		
		// Подключаем файл с PHP функциями темы, если он существует
		$funcFile = $tpl->template_dir.'/functions.php';
		if(file_exists($funcFile)) require($funcFile);
		
		// Стандартное имя Темплейта для сообщений об ошибках
		$this->result = $tpl->fetch('error.tpl.html');
		
	}
	
}

?>