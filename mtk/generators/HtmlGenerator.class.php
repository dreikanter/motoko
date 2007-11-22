<?php

require DIR_MTK.'lib/template_lite/class.template.php';

class HtmlGenerator extends AbstractGenerator {
	
	function HtmlGenerator(&$_performedAction) {
		
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
		
		$tpl->force_compile = false;
		$tpl->compile_check = true;
		$tpl->cache = false;
		$tpl->cache_lifetime = 3600;
		$tpl->config_overwrite = false;
		
		// Переносим в темплейт данные из action-а
		foreach($_performedAction->resData as $name => $value) { 
			$tpl->assign($name, $value);
		}
		
		// Дополнительные переменные - данные сессии и список переменных 
		// темплейта. Нужно только при отладке.
		if(DEBUG_MODE) {
			$tpl->assign("_session", "<pre>".print_r($_SESSION, true)."</pre>");
			$tpl->assign("_items", "<pre>".print_r(array_keys($_performedAction->resData), true)."</pre>");
		}
		
		// Подключаем информационный файл темы
		$infoFile = $tpl->template_dir.'/info.php';
		if(file_exists($infoFile)) require($infoFile);
		
		// Значения по-умолчанию для информационных переменных темы, если
		// таковые не заданы
		if(!isset($themeTitle)) $themeTitle = CURRENT_THEME;
		if(!isset($themeDescription)) $themeDescription = '';
		if(!isset($themeAuthor)) $themeAuthor = '';
		if(!isset($themeAuthorEmail)) $themeAuthorEmail = '';
		if(!isset($themeHomepage)) $themeHomepage = '';
		
		// Подключаем файл с PHP функциями темы, если он существует
		$funcFile = $tpl->template_dir.'/theme.php';
		if(file_exists($funcFile)) require($funcFile);
		
		global $BDB;
		
		//$usrAL = $_performedAction->action->db->userAccessLevel;
		$usrAL = $BDB->userAccessLevel;
		
		// Единые для всех страниц значения (имена начинаятся с _underscore)
		$tpl->assign('_root_url', URL_ROOT);
		$tpl->assign('_action', $_performedAction->actionName);
		$tpl->assign('_tpl_url', URL_ROOT.'mtk-tpl/'.CURRENT_THEME.'/');
		$tpl->assign('_blog_title', BLOG_TITLE);
		$tpl->assign('_blog_subtitle', BLOG_SUBTITLE);
		
//		$tpl->assign('_stat_sql_requests', $_performedAction->action->db->rqCnt);
//		$tpl->assign('_user_id', $_performedAction->action->db->userId);
//		$tpl->assign('_user_login', $_performedAction->action->db->userLogin);
//		$tpl->assign('_user_name', $_performedAction->action->db->userName);
//		$tpl->assign('_user_authorized', ($_performedAction->action->db->userId > -1)?1:0);
		
		$tpl->assign('_stat_sql_requests', $BDB->rqCnt);
		$tpl->assign('_user_id', $BDB->userId);
		$tpl->assign('_user_login', $BDB->userLogin);
		$tpl->assign('_user_name', $BDB->userName);
		$tpl->assign('_user_authorized', ($BDB->userId > -1)?1:0);
		
		$tpl->assign('_user_supervisor', ($usrAL & AR_SUPERVISING)?1:0);
		$tpl->assign('_user_moderator', ($usrAL & AR_MODERATION)?1:0);
		$tpl->assign('_user_poster', ($usrAL & AR_POSTING)?1:0);
		$tpl->assign('_user_community', ($usrAL & AR_READING_COMMUNITY)?1:0);
		$tpl->assign('_user_commenting', ($usrAL & AR_COMMENTING)?1:0);
		$tpl->assign('_system_message', isset($_SESSION['system_message'])?$_SESSION['system_message']:'');
		$tpl->assign('_software', SOFTWARE_TITLE);
		$tpl->assign('_version', SOFTWARE_VERSION);
		$tpl->assign('_software_hp', SOFTWARE_HP);
		$tpl->assign('_ts', date('H:i:s, d-m-Y'));
		$tpl->assign('_theme_title', $themeTitle);
		$tpl->assign('_theme_desc', $themeDescription);
		$tpl->assign('_theme_author', $themeAuthor);
		$tpl->assign('_theme_email', $themeAuthorEmail);
		$tpl->assign('_theme_hp', $themeHomepage);
		$tpl->assign('_blog_abstract', BLOG_ABSTRACT_CACHED);
		
		$_SESSION['system_message'] = '';
		
		$this->result = $tpl->fetch($_performedAction->template.'.tpl.html');
		
	}
	
}

?>