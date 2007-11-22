<?php

/**
 * Выполняет переадресацию пользователя с заданным user_id на страницу его профиля
 * /uid/<user_id> -> /users/<user_login>. Если пользователь не существует в базе, выдаёт 
 * сообщение.
 * Параметры: user_id (int) - user_id пользователя
 */
class UserRedirectAction extends AbstractAction {
	
	var $returnable = true;
	var $template = 'user_profile';
	
	function paramsOk() {
		// Должен быть задан один параметр - login пользователя
		if(count($this->params) != 1 || !isset($this->params['user_id']) || 
			 !is_numeric($this->params['user_id'])) {
			 	 
			$this->errorMsg = "Bad action params.";
			return false;
		}
		
		return true;
	}
	
	function execute() {
		global $BDB;
		
		// Считываем из базы login пользователя с заданным user_id
		$sqlRequest = 'SELECT login FROM '.$BDB->tblUsers.
			' WHERE user_id = '.$this->params['user_id'];
		$result = $BDB->Execute($sqlRequest, 
			'Считываем из базы login пользователя с заданным user_id');
		if(!$result) {
			$this->resType = 'error';
			$this->resData['error_msg'] = "Database error: ".
				$BDB->errorMsg;
			return;
		}
		
		if(!$result->RecordCount()) {
			// Пользователь не существует
			$this->resType = 'error';
			$this->resData = array('error_msg' => "User doesn't exists.");
			return;
		}
		
		$userLogin = $result->Fields(0);
		
		// Выполняем переадресацию на страницу профиля пользователя
		$this->resType = 'redirect';
		$this->resData = array('to' => URL_ROOT.'users/'.$userLogin);
		
	}

}

?>