<?php

class CatsAction extends AbstractAction {
	
	var $returnable = true;
	var $template = 'cats';
	
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
		
		// Отдаём в темплейт список категорий
		
		$cats = $BDB->GetCats('title');
		if(!$cats) {
			$this->resType = 'error';
			$this->resData = array('error_msg' => $BDB->errorMsg);
			return;
		}
		
		$this->resType = 'html';
		$this->resData = array(
				'popular_tags' => $BDB->GetPopularTags(POPULAR_TAGS_COUNT),
				'cats' => $cats,
				'can_manage_cats' => $BDB->CanManageCats()?1:0,
				'page_title' => 'Categories list',
				'where_am_i' => 'This is a complete enumeration of posts categories.'
			);
	}

}

?>