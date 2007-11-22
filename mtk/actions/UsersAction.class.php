<?php

/**
 * Отображает список зарегистрированных пользователей для редактирования и удаления записей.
 * Параметры: -
 */
class UsersAction extends AbstractAction {
	
	var $returnable = true;
	var $template = 'users';
	
	function paramsOk() {
		// Параметров быть не должно
		if(count($this->params)) {
			$this->errorMsg = "Bad action params.";
			return false;
		}
		
		return true;
		
	}
	
	function execute() {
		global $BDB;
		
		$this->resType = 'html';
		$this->resData = array(
				'popular_tags' => $BDB->GetPopularTags(POPULAR_TAGS_COUNT),
				'cats' => $BDB->GetCats('title'),
				'users' => $BDB->GetUsers(),
				'can_manage_users' => $BDB->CanManageUsers(),
				'page_title' => 'Users list',
				'where_am_i' => 'This is a complete enumeration of registered users.'
			);
		
	}

}

?>