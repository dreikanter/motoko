<?php

class TagsAction extends AbstractAction {
	
	var $returnable = true;
	var $template = 'tags';
	
	function paramsOk() {
		// Параметров быть не должно
		if(count($this->params) == 1 && isset($this->params['type'])) {
			return true;
		} else {
			$this->errorMsg = "Bad action params.";
			return false;
		}
	}
	
	function execute() {
		global $BDB;
		
		// Расчитываем относительную популярность тагов, преобразовуя количество
		// ассоциированных с ними постов в число от 1 до 10. На основании этого числа 
		// определяется CSS класс, задающий размер тага в облаке
		
		$tags = $BDB->GetTags();
		if($tags === false) {
			$this->resType = 'error';
			$this->resData = array('error_msg' => $BDB->errorMsg);
			return;
		}
		
		if(count($tags)) {
			
			$maxCnt = 0;
			$minCnt = 0;
			
			// Определяем минимальное и максимальное значение счётчиков тагов
			foreach($tags as $tag) {
				$cnt = $tag['count'];
				if($cnt > $maxCnt) $maxCnt = $cnt;
				if(!$minCnt || $cnt < $minCnt) $minCnt = $cnt;
			}
			
			// Добавляем к каждому тагу в массиве поле rating с оценкой 
			// его относительно популярности по 10-бальной шкале.
			// Расчитанное значение будет использовано в темплейте для стилевого 
			// оформления тагов в облаке
			
			$delta = $maxCnt - $minCnt;
			
			foreach($tags as $num => $tag) {
				$tags[$num]['tag'] = $tags[$num]['tag'];
				$tags[$num]['rating'] = $delta?floor(($tags[$num]['count'] - $minCnt) / $delta * 9):5;
			}
			
		}
		
		$renderType = $this->params['type'];
		
		$this->resType = 'html';
		$this->resData = array(
				'type' => $renderType,
				'tags' => $tags,
				'cats' => $BDB->GetCats('title'),
				'tags_count' => count($tags),
				'page_title' => (($renderType == 'cloud')?'Tags cloud':'Tags list'),
				'where_am_i' => 'Here you may see a full enumeration of used tags presented as a cloud or simple list.'
			);
	}

}

?>