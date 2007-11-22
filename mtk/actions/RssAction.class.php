<?php

/**
 * Выдаёт RSS ленты для общей ленты блога, для каждой категории или для выборки постов по набору тагов.
 */

require DIR_MTK.'classes/RssSpawner.class.php';

class RssAction extends AbstractAction {
	
	var $returnable = false;
	
	function paramsOk() {
		show($this->params);
		$paramCnt = count($this->params);
		if(($paramCnt > 2) ||
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
		
		$catId = false;
		$catShortcut = false;
		$catTitle = '';
		$catDesc = '';
		$cat = array();
		
		// Определяем необходимость ограничения выборки постов категорией и считываем 
		// данные категории
		if(isset($this->params['cat_id'])) {
			$catId = $this->params['cat_id'];
			
			// Считываем категории
			$cats = $BDB->GetCats('title');
			
			foreach($cats as $cat) {
				if($cat['cat_id'] == $catId) {
					$catTitle = $cat['title'];
					$catDesc = $cat['description'];
					break;
				}
			}
		} elseif(isset($this->params['cat_shortcut'])) {
			$catShortcut = $this->params['cat_shortcut'];
			
			// Считываем категории
			$cats = $BDB->GetCats('title');
			
			// Необходимо найти cat_id категории с заданным shortcut
			foreach($cats as $cat) {
				if($cat['shortcut'] == $catShortcut) {
					$catId = $cat['cat_id'];
					$catTitle = $cat['title'];
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
		
		// Считываем посты из базы (true означает, что нужны только посты со 
		// статусом public)
		$posts = $BDB->GetPostsPage(MAX_FEED_LENGTH, 0, $catId, $tags, true);
		
		if(!is_array($posts)) {
			$this->resType = 'error';
			$this->resData = array('error_msg' => $BDB->errorMsg);
			return;
		}
		
		// Генерируем RSS на основе считанного из БД контента
		$homeUrl = URL_HOST.URL_ROOT;
		if($catId) {
			$homeUrl .= '/cid/'.$catId; 
		} elseif($catShortcut) {
			$homeUrl .= '/'.$catShortcut;
		} elseif($tags) {
			$homeUrl .= '/tags/'.$tags;
		}
		
		$rss = new RssSpawner(BLOG_TITLE, $homeUrl, BLOG_SUBTITLE);
		$rss->setChannelOption('pubDate', time());
		$rss->setChannelOption('lastBuildDate', time());
		$rss->setChannelOption('generator', SOFTWARE_TITLE.'/'.SOFTWARE_VERSION.' ('.SOFTWARE_HP.')');
		$rss->setChannelOption('docs', 'http://blogs.law.harvard.edu/tech/rss');
		
		show($posts);
		
		foreach($posts as $post) {
			$postUrl = URL_HOST.URL_ROOT.'posts/'.$post['post_id'];
			$content = $post['cached_content'];
			
			if(!COMPLETE_XML_EXPORT) {
				// Убираем текст под катами из постов в RSS ленте, если задана соответствующая опция
				$content = hideCuts($content, $postUrl);
			}
			
			$item = array(
					'guid' => $postUrl, 
					'guid.isPermaLink' => 'true', 
					'pubDate' => $post['ctime'], 
					'title' => $post['title'],
					'link' => $postUrl,
					'description' => $content,
					'author' => $post['user_name']?$post['user_name']:$post['user_login'],
					'category' => $catTitle,
					'category.domain' => URL_HOST.URL_ROOT.'cid/'.$post['cat_id'],
					'comments' => $postUrl
				);
			
			$rss->addItem($item);
		}
		
		$this->resType = 'xml';
		$this->resData = array(
				'content-type' => 'application/rss+xml',
				'xml' => $rss->getRss()
			);
		
	}
	
}

?>