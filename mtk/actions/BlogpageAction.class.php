<?php

// Максимальная длина текста ссылки "Read more..." 
// Используется для ограничения длины при неправильном 
// синтаксисе ката
define("MAX_CUT_TEXT_LENGTH", 50);

/**
 * Выводит страницу ленты постов.
 * Параметры: page_num - номер страницы (начиная с 1); 
 * cat_id (опциональный) - в случае, если параметр задан, 
 * посты в ленте будут ограничены этой категорией; 
 * cat_shortcut (опциональный) - работает так же, 
 * как cat_id, но содержит нге ID, а shortcut нужной 
 * категории
 */
class BlogpageAction extends AbstractAction {
	
	var $returnable = true;
	var $template = "blogpage";
	
	function paramsOk() {
		// Должен быть задан 1 параметр с именем number
		// и числовым значением (0 соответствует первой странице)
		$paramCnt = count($this->params);
		if(($paramCnt < 1 || $paramCnt > 2) ||
			(!isset($this->params['page_num']) || !is_numeric($this->params['page_num'])) || 
			(isset($this->params['cat_id']) && !is_numeric($this->params['cat_id'])) || 
			(isset($this->params['cat_shortcut']) && !$this->params['cat_shortcut']) || 
			(isset($this->params['tags']) && (!is_array($this->params['tags']) || !count($this->params['tags'])))) {
			
			$this->errorMsg = "Bad action params.";
			return false;
		}
		
		return true;
		
	}
	
	
	function execute() {
		
		global $BDB;
		
		$pageNum = $this->params['page_num'];
		
		if($pageNum == 0) {
			$this->resType = 'error';
			$this->resData = array('error_msg' => 'Incorrect page number. What are you, programmer!?');
			return;
		}
		
		$catId = false;
		$catShortcut = false;
		$catDesc = '';
		$cat = array();
		
		// Считываем категории
		$cats = $BDB->GetCats('title');
		
		// Определяем необходимость ограничения выборки постов категорией и считываем 
		// данные категории
		if(isset($this->params['cat_id'])) {
			$catId = $this->params['cat_id'];
			foreach($cats as $cat) {
				if($cat['cat_id'] == $catId) {
					$catDesc = $cat['description'];
					break;
				}
			}
		} elseif(isset($this->params['cat_shortcut'])) {
			$catShortcut = $this->params['cat_shortcut'];
			// Необходимо найти cat_id категории с заданным shortcut
			foreach($cats as $cat) {
				if($cat['shortcut'] == $catShortcut) {
					$catId = $cat['cat_id'];
					$catDesc = $cat['description'];
					break;
				}
			}
			
			// Проверяем существование категории. Если запрашиваемая категория 
			// не существует, будет выдано сообщение об ошибке
			if($catId === false) {
				$this->resType = 'error';
				$this->resData = array('error_msg' => "Required category doesn't exists");
				return;
			}
		}
		
		$tags = false;
		$relTags = false;
		
		// Определяем необходимость ограничения выборки постов по тагам
		if(isset($this->params['tags'])) {
			$tags = $this->params['tags'];
			$relTags = $BDB->GetRelativeTags($tags);
			if(!is_array($relTags)) {
				$this->resType = 'error';
				$this->resData = array('error_msg' => $BDB->errorMsg);
				return;
			}
		}
		
		// Считываем посты из базы
		$posts = $BDB->GetPostsPage(POSTS_PER_PAGE, $pageNum, $catId, $tags);
		
		if(!is_array($posts)) {
			$this->resType = 'error';
			$this->resData = array('error_msg' => $BDB->errorMsg);
			return;
		}
		
		$prevPageNum = $BDB->lastPage?'':($pageNum + 1);
		$nextPageNum = ($pageNum == 1)?'':($pageNum - 1);
		
		if($catShortcut) {
			$nextPageUrl = $prevPageUrl = URL_ROOT.$catShortcut;
		} elseif($catId) {
			$nextPageUrl = $prevPageUrl = URL_ROOT.'cid/'.$catId;
		} elseif(is_array($tags)) {
			$nextPageUrl = $prevPageUrl = URL_ROOT.'tags/'.implode('+', $tags);
		} else {
			$nextPageUrl = URL_ROOT.(($nextPageNum > 1)?'page':'');
			$prevPageUrl = URL_ROOT.'page';
		}
		
		if($prevPageNum) $prevPageUrl .= '/'.$prevPageNum;
		if($nextPageNum > 1) $nextPageUrl .= '/'.$nextPageNum;
		
		global $STATUSES;
		
		// Обрабатываем каждый считанный пост
		foreach($posts as $num => $post) {
			// Убираем под каты текст
			$posts[$num]['cached_content'] = 
				hideCuts($posts[$num]['cached_content'], URL_ROOT.'posts/'.$post['post_id']);
			// Флаги для генерации линков под постами
			$posts[$num]['can_change'] = (int)$BDB->CanChangePost($post['user_id'], $post['status']);
			$posts[$num]['can_delete'] = (int)$BDB->CanDeletePost($post['user_id'], $post['status']);
			$posts[$num]['status_desc'] = $STATUSES[$posts[$num]['status']];
		}
		
		if($pageNum == 1) {
			$pageTitle = BLOG_TITLE;
			$whereAmI = 'This is a main page of the blog where most recent posts are located.';
		} else {
			$pageTitle = BLOG_TITLE.' (page '.$pageNum.')';
			$whereAmI = 'This is a blog page number '.$pageNum;
		}
		
		$this->resType = 'html';
		$this->resData = array(
				'page_number' => $pageNum,
				'last_page' => $BDB->lastPage?1:0,
				'next_page_num' => $nextPageNum,
				'prev_page_num' => $prevPageNum,
				'next_page_url' => $nextPageUrl,
				'prev_page_url' => $prevPageUrl,
				'cats' => $cats,
				'popular_tags' => $BDB->GetPopularTags(POPULAR_TAGS_COUNT),
				'posts' => $posts,
				'current_cat_id' => ($catId === false)?-1:$catId,
				'current_cat_desc' => $catDesc,
				'page_title' => $pageTitle,
				'where_am_i' => $whereAmI
			);
		
		if($tags) {
			
			$actualTags = array();
			
			// Генерируем масисв actual tags для темплейта
			foreach($tags as $num => $tag) {
				$remove = $tags;
				unset($remove[$num]);
				$actualTags[] = array('tag' => $tag, 'remove' => implode('+', $remove));
			}
			
			$this->resData['actual_tags'] = $actualTags;
			
			// Добавляем в темплейт массив родственных тагов
			$relativeTags = array();
			
			// Генерируем масисв relative tags для темплейта
			foreach($relTags as $tag) {
				$relativeTags[] = array('tag' => $tag, 'add' => implode('+', array_merge($tags, array($tag))));
			}
			
			$this->resData['rel_tags'] = $relativeTags;
			
		} else {
			$this->resData['actual_tags'] = false;
		}
		
		// По наличию или отсутствию переменной cat_shortcut,  атак же по наличию 
		// или отсутствию значения переменной current_cat_id, в темплейте можно определить 
		// необходимость ограничения категорией выборки постов в ленте. А так же тип ссылок 
		// на соседние страницы ленты (простая ссылка на страницу - /page/<page_num>, ссылка 
		// на страницу постов в категории - /cid/<cat_id>/<page_num> или /<cat_shortcut>/<page_num>)
		if(isset($this->params['cat_shortcut'])) 
			$this->resData['current_cat_shortcut'] = $this->params['cat_shortcut'];
		
	}

}

?>