<?php

class ErrorAction extends AbstractAction {
	
	function execute() {
		global $BDB;
		
		$this->resType = 'error';
		$this->resData = array('error_msg' => 'Something strange unexpectedly occured.');
		
	}
	
}

?>