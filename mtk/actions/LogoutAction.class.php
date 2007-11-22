<?php

class LogoutAction extends AbstractAction {
	
	var $returnable = false;
	
	function paramsOk() {
		// Параметров быть не должно
		if(count($this->params) == 0) {
			return true;
		} else {
			$this->errorMsg = "Bad action params.";
			return false;
		}
	}
	
	function execute() {
		global $BDB;
		
		$_SESSION['user_id'] = -1;
		unset($_SESSION['user_name']);
		unset($_SESSION['user_email']);
		unset($_SESSION['user_hp']);
		$this->resType = 'redirect';
		$this->resData = array(
				'to' => URL_ROOT,
				'system_message' => 'User logged out.'
			);
	}

}

?>