<?php

/**
 * Выводит страницу с данными о заданном по логину пользователе.
 */
class UserProfileAction extends AbstractAction {
	
	var $returnable = true;
	var $template = 'user_profile';
	
	function paramsOk() {
		// Должен быть задан один параметр - login пользователя
		if(count($this->params) != 1 || !isset($this->params['login'])) {
			$this->errorMsg = "Bad action params.";
			return false;
		}
		
		return true;
	}
	
	function execute() {
		global $BDB;
		
		$userLogin = $this->params['login'];
		
		// Считываем пользовательские данные
		$user = $BDB->GetUser((string)$userLogin);
		
		if(!$user) {
			$this->resType = 'error';
			$this->resData = array('error_msg' => $BDB->errorMsg);
			return;
		}
		
		// Флаг для отключения пункта меню User profile (чтобы в сайдбаре 
		// не было ссылки на текущую страницу)
		$currentUser = (int)($BDB->userLogin === $userLogin);
		$accessLevel = $user['access_level'];
		
		$this->resType = 'html';
		$this->resData = array(
				'user_id' => $user['user_id'],
				'user_name' => $user['name'],
				'user_login' => $userLogin,
				'user_hp' => $user['hp'],
				'user_access_level' => $user['access_level'],
				'user_description' => $user['cached_description'],
				'user_icq' => isset($user['custom_data']['icq'])?$user['custom_data']['icq']:'',
				'user_posts_cnt' => $user['posts_cnt'],
				'user_com_p_cnt' => $user['com_p_cnt'],
				'user_com_r_cnt' => $user['com_r_cnt'],
				'user_ar_reading_c' => ($accessLevel & AR_READING_COMMUNITY),
				'user_ar_posting' => ($accessLevel & AR_POSTING),
				'user_ar_commenting' => ($accessLevel & AR_COMMENTING),
				'user_ar_moderation' => ($accessLevel & AR_MODERATION),
				'user_ar_supervising' => ($accessLevel & AR_SUPERVISING),
				'current_user_profile' => $currentUser,
				'cats' => $BDB->GetCats('title'),
				'popular_tags' => $BDB->GetPopularTags(POPULAR_TAGS_COUNT),
				'page_title' => $user['name']?$user['name']:$userLogin,
				'where_am_i' => 'This is a user profile page of <strong>'.$userLogin.'</strong>.'
			);
		
		if($BDB->CanManageUsers() || $BDB->userLogin == $user['login']) {
			$this->resData['user_email'] = isset($user['email'])?$user['email']:'';
		}
		
	}

}

?>