<?php

class BackupsAction extends AbstractAction {
	
	var $returnable = true;
	var $template = 'backups';
	
	function paramsOk() {
		// Параметров быть не должно
		if(count($this->params) != 0) {
			$this->errorMsg = "Bad action params.";
			return false;
		}
		
		return true;
		
	}
	
	function execute() {
		global $BDB;
		
		// Проверяем право пользователя на просмотр списка бэкапов
		if(!$BDB->CanManageBackups()) {
			$this->resType = 'error';
			$this->resData = array('error_msg' => 
				'User have no access rights to perform database backup.');
			return;
		}
		
		$backups = $BDB->GetBackupList();
		
		$this->resType = 'html';
		$this->resData = array(
				'popular_tags' => $BDB->GetPopularTags(POPULAR_TAGS_COUNT),
				'cats' => $BDB->GetCats('title'),
				'backups' => $backups,
				'backup_count' => count($backups),
				'page_title' => 'Database backup',
				'where_am_i' => 'This is a complete enumeration of database backups which may '.
					'be restored or downloaded as text files.'
			);
	}

}

?>