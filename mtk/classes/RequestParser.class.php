<?php

class RequestParser {
	
	var $action = false;
	var $params = array();
	
	function RequestParser() {
		
		$url = parse_url($_SERVER["REQUEST_URI"]);
		$urlPath = $url['path'];
		
		if(substr($_SERVER["SCRIPT_FILENAME"], -strlen($urlPath)) == $urlPath) {
			// было выполнено прямое обращение к скрипту
			// (скорее всего передаются данные из формы)
			
			$params = array_merge($_POST, $_GET);
			
			/* <section title="новый вариант обработки данных из форм"> */
			// Условие того, что переданы данные из формы одного из action'ов:
			//   - Определён POST или GET параметр action
			//   - У него значение имеет вид /[a-zA-Z1-9_]+/
			//   - Соответствующий action существует 
			if(isset($params['action']) && 
				preg_match("/[a-zA-Z1-9_]+/", $params['action']) && 
				file_exists(DIR_MTK.'actions/'.$params['action'].'Action.class.php')) {
				
				$this->action = $params['action'].'Action';
				unset($params['action']);
				$this->params = $params;
				
			} else {
				// Если заданный action не существует, или значение параметра 
				// action не задано или некорректно, выводим сообщение об ошибке
				$this->action = 'ErrorAction';
				$this->params = array();
			}
			/* </section> */
			
			return;
			
		}
		// был выполнен редирект на скрипт (ErrorDocument)
		// необходимо распознать REQUEST_URL
		
		//$rq = substr(getenv("REQUEST_URI"), strlen(URL_ROOT));
		$rq = substr($urlPath, strlen(URL_ROOT));
		$drq = rawurldecode($rq);
		$m = array();
		
		// <section title="Опциональная проверка существования файла-страницы соответствующего заданному URL">
		if(CHECK_PAGES) {
			$result = $this->checkPage($drq);
			if(is_array($result)) {
				// PageAction - базовый action, генерирующий вебстраницу из заданного файла
				$this->action = 'PageAction';
				$this->params = array('path' => $result['path'], 'vpath' => $result['vpath']);
				return;
			}
		}
		// </section>
		
		// <section title="Распознавание action'ов, специфичных для блога">
		if($rq == "") {
			// Корневая страница (первая страница постов)
			// параметры: page_num
			$this->action = 'BlogpageAction';
			$this->params = array('page_num' => 1);
			
		} elseif($rq == "cats") {
			// список категорий
			// параметры: - 
			$this->action = 'CatsAction';
			$this->params = array();
			
		} elseif($rq == "tags") {
			// список тагов
			// параметры: type (способ представления тагов. В данном случае - облако)
			$this->action = 'TagsAction';
			$this->params = array('type' => 'cloud');
			
		} elseif($rq == "rss") {
			// Общий RSS фид ленты постов
			// параметры: -
			$this->action = 'RssAction';
			$this->params = array();
			
		} elseif($rq == "login") {
			// login (запрос формы)
			// параметры: -
			$this->action = 'LoginAction';
			$this->params = array();
			
		} elseif($rq == "logout") {
			// logout
			// параметры: -
			$this->action = 'LogoutAction';
			$this->params = array();
			
		} elseif($rq == "post") {
			// Редактор нового поста (запрос формы)
			// параметры: - (post_id == -1)
			$this->action = 'PostEditAction';
			$this->params = array('post_id' => -1);
			
			
		} elseif($rq == "tags-list") {
			// Список тагов для редактирования
			// параметры: type (способ отображения тагов. В данном случае - список)
			$this->action = 'TagsAction';
			$this->params = array('type' => 'list');
			
		} elseif($rq == "users") {
			// список пользователей
			// параметры: - 
			$this->action = 'UsersAction';
			$this->params = array();
			
		} elseif($rq == "add-user") {
			// Запрос формы для добавления нового пользователя
			// параметры: -
			$this->action = 'EditUserAction';
			$this->params = array('user_id' => -1);
			
		} elseif($rq == "add-cat") {
			// Запрос формы для добавления новой категории
			// параметры: -
			$this->action = 'EditCatAction';
			$this->params = array('cat_id' => -1);
			
		} elseif($rq == "backups") {
			// Запрос списка бэкапов
			// параметры: -
			$this->action = 'BackupsAction';
			$this->params = array();
			
		} elseif($rq == "settings") {
			// Настройки блога
			// параметры: -
			$this->action = 'SettingsAction';
			$this->params = array();
			
			
		} elseif($rq == "backup") {
			// Бэкап данных
			// параметры: -
			$this->action = 'BackupAction';
			$this->params = array('confirmed' => 0);
			
		} elseif(preg_match("/^(\d{4})$/", $rq, $m)) {
			// список постов за год
			// параметры: год
			$this->action = 'YearAction';
			$this->params = array('year' => $m[1]);
			
		} elseif(preg_match("/^page\/(\d+)$/", $rq, $m)) {
			// Страница главной ленты блога
			// параметры: номер страницы
			$this->action = 'BlogpageAction';
			$this->params = array('page_num' => $m[1]);
			
		} elseif(preg_match("/^edit-user\/(\d+)$/", $rq, $m)) {
			// Запрос формы редактирования пользовательских данных
			// параметры: user_id
			$this->action = 'EditUserAction';
			$this->params = array('user_id' => $m[1]);
			
		} elseif(preg_match("/^delete-user\/(\d+)$/", $rq, $m)) {
			// Запрос формы подтверждения удаления пользовательского профиля
			// параметры: user_id
			$this->action = 'DeleteUserAction';
			$this->params = array('user_id' => $m[1]);
			
		} elseif(preg_match("/^approve-comment\/(\d+)$/", $rq, $m)) {
			// Модерирование комментария (присвоения статуса moderated)
			// параметры: comment_id
			$this->action = 'ApproveCommentAction';
			$this->params = array('comment_id' => $m[1]);
			
		} elseif(preg_match("/^restore\/(\d+)$/", $rq, $m)) {
			// Восстановление содержимого таблиц БД из бэкапа
			// параметры: timestamp
			$this->action = 'RestoreAction';
			$this->params = array('timestamp' => $m[1], 'confirmed' => 0);
			
		} elseif(preg_match("/^delete-backup\/(\d+)$/", $rq, $m)) {
			// Удаление бэкапа
			// параметры: timestamp
			$this->action = 'DeleteBackupAction';
			$this->params = array('timestamp' => $m[1], 'confirmed' => 0);
			
		} elseif(preg_match("/^tags\/([\w -_\+]+)\/rss$/", $rq, $m)) {
			// RSS фид для заданного набора тагов
			// параметры: массив тагов
			$this->action = 'RssAction';
			$this->params = array('tags' => $this->urlToTags($m[1]));
			
		} elseif(preg_match("/^tags\/([\w -_\+]+)\/(\d+)$/", $rq, $m)) {
			// Заданная страница ленты постов, ассоциированных с одним или несколькими 
			// заданными тагами. Если тагов несколько, они перечисялются через "+".
			// параметры: номер страницы, массив тагов
			$this->action = 'BlogpageAction';
			$this->params = array('page_num' => $m[2], 'tags' => $this->urlToTags($m[1]));
			
		} elseif(preg_match("/^tags\/([\w -_\+]+)$/", $rq, $m)) {
			// Первая страница ленты постов, ассоциированных с одним или несколькими 
			// заданными тагами. Если тагов несколько, они перечисялются через "+".
			// параметры: номер страницы (1), массив тагов
			$this->action = 'BlogpageAction';
			$this->params = array('page_num' => 1, 'tags' => $this->urlToTags($m[1]));
			
		} elseif(preg_match("/^cid\/(\d+)\/(\d+)$/", $rq, $m)) {
			// Страница ленты постов, относящихся к заданной по cat_id категории
			// параметры: page_num, cat_id
			$this->action = 'BlogpageAction';
			$this->params = array('page_num' => $m[2], 'cat_id' => $m[1]);
			
		} elseif(preg_match("/^cid\/(\d+)$/", $rq, $m)) {
			// Первая страница ленты постов, относящихся к заданной по cat_id категории
			// параметры: page_num, cat_id
			$this->action = 'BlogpageAction';
			$this->params = array('page_num' => 1, 'cat_id' => $m[1]);
			
		} elseif(preg_match("/^cid\/(\d+)\/rss$/", $rq, $m)) {
			// RSS фид для категории, заданной по cat_id
			// параметры: cat_id
			$this->action = 'RssAction';
			$this->params = array('cat_id' => $m[1]);
			
		} elseif(preg_match("/^posts\/(\d+)$/", $rq, $m)) {
			// полный текст поста
			// параметры: post id
			$this->action = 'PostAction';
			$this->params = array('post_id' => $m[1]);
			
		} elseif(preg_match("/^edit-post\/(\d+)$/", $rq, $m)) {
			// Редактор заданного поста (запрос формы)
			// параметры: post_id
			$this->action = 'PostEditAction';
			$this->params = array('post_id' => $m[1]);
			
		} elseif(preg_match("/^delete-post\/(\d+)$/", $rq, $m)) {
			// Удаление поста (запрос формы подтверждения удаления поста навеки)
			// параметры: post_id, confirmed
			$this->action = 'DeletePostAction';
			$this->params = array('post_id' => $m[1], 'confirmed' => 0);
			
		} elseif(preg_match("/^edit-comment\/(\d+)$/", $rq, $m)) {
			// Редактор заданного коммента (запрос формы)
			// параметры: comment_id
			$this->action = 'CommentEditAction';
			$this->params = array('comment_id' => $m[1]);
			
		} elseif(preg_match("/^delete-comment\/(\d+)$/", $rq, $m)) {
			// Удаление коммента (запрос формы подтверждения удаления)
			// параметры: comment_id, confirmed
			$this->action = 'DeleteCommentAction';
			$this->params = array('comment_id' => $m[1], 'confirmed' => 0);
			
		} elseif(preg_match("/^edit-cat\/(\d+)$/", $rq, $m)) {
			// Редактор заданной категории (запрос формы)
			// параметры: cat_id
			$this->action = 'EditCatAction';
			$this->params = array('cat_id' => $m[1]);
			
		} elseif(preg_match("/^delete-cat\/(\d+)$/", $rq, $m)) {
			// Удаление категории (запрос формы подтверждения удаления)
			// (в форме задаётся, будут ли посты из удаляемой категории удалены 
			// или перенесены в новую категорию. В последнем случае так же 
			// определяется cat_id категории для переноса)
			// параметры: cat_id
			$this->action = 'DeleteCatAction';
			$this->params = array('cat_id' => $m[1]);
			
		} elseif(preg_match("/^uid\/(\d+)$/", $rq, $m)) {
			// Редирект на страницу пользовательского профиля
			// параметры: user_id
			$this->action = 'UserRedirectAction';
			$this->params = array('user_id' => $m[1]);
			
		} elseif(preg_match("/^users\/([\d\w-_]+)$/", $rq, $m)) {
			// Страница пользовательского профиля
			// параметры: login
			$this->action = 'UserProfileAction';
			$this->params = array('login' => $m[1]);
			
		} elseif(preg_match("/^edit-tag\/(.+)$/", $rq, $m)) {
			// Редактирование тага (запрос формы для переименования 
			// существующего тага)
			// параметры: tag
			$this->action = 'EditTagAction';
			$this->params = array('tag' => $m[1]);
			
		} elseif(preg_match("/^delete-tag\/(.+)$/", $rq, $m)) {
			// Удаление тага (запрос формы для подтверждения удаления тага)
			// параметры: tag
			$this->action = 'DeleteTagAction';
			$this->params = array('tag' => urldecode($m[1]));
			
		} elseif(preg_match("/^(\d{4})\/(\d{1,2})$/", $rq, $m)) {
			// список постов за определённый месяц
			// параметры: год, месяц
			$this->action = 'MonthAction';
			$this->params = array('year' => $m[1], 'month' => $m[2]);
			
		} elseif(preg_match("/^(\d{4})\/(\d{1,2})\/(\d{1,2})$/", $rq, $m)) {
			// список постов за определённый день
			// параметры: год, месяц, день
			$this->action = 'DayAction';
			$this->params = array('year' => $m[1], 'month' => $m[2], 
				'day' => $m[3]);
			
		} elseif(preg_match("/^(\d{4})\/(\d{1,2})\/(\d{1,2})\/(\d+)$/", 
			$rq, $m)) {
			// индивидуальная страница поста
			// параметры: год, месяц, день, номер поста за день
			$this->action = 'PostAction';
			$this->params = array('year' => $m[1], 'month' => $m[2], 
				'day' => $m[3], 'number' => $m[4]);
			
		} elseif(mb_eregi("^([^+\`~\@\%\&\(\)\[\]\{\}\/]{0,".MAX_SHORTCUT_LENGTH."})\/(\d+)$", $drq, $m)) {
			// Cтраница ленты постов, относящихся к одной категории, заданной по shortcut.
			// Можно использовать неанглоязычные shortcut'ы.
			// параметры: page_num, cat_shortcut
			$this->action = 'BlogpageAction';
			$this->params = array('page_num' => $m[2], 'cat_shortcut' => urldecode($m[1]));
			
		} elseif(mb_eregi("^([^+\`~\@\%\&\(\)\[\]\{\}\/]{0,".MAX_SHORTCUT_LENGTH."})$", $drq, $m)) {
			// Первая страница ленты постов, относящихся к одной категории, заданной по shortcut.
			// Можно использовать неанглоязычные shortcut'ы.
			// параметры: page_num, cat_shortcut
			$this->action = 'BlogpageAction';
			$this->params = array('page_num' => 1, 'cat_shortcut' => urldecode($m[1]));
			
		} elseif(mb_eregi("^([^+\`~\@\%\&\(\)\[\]\{\}\/]{0,".MAX_SHORTCUT_LENGTH."})\/rss$", $drq, $m)) {
			// RSS фид для категории, заданной по cat_shortcut
			// параметры: cat_shortcut
			$this->action = 'RssAction';
			$this->params = array('page_num' => 1, 'cat_shortcut' => urldecode($m[1]));
			
		}
		// </section>
		
		// В случае, если action не был опознан, запрошен некорректный URL.
		// Выводим сообщение об ошибке
		if(!$this->action) {
			$this->action = 'ErrorAction';
			$this->params['message'] = 'Incorrect request.';
		}
		
	}
	
	/**
	 * Преобразует фрагмент URL, в котором таги перечислены через "+", 
	 * в "очищенный" массив тагов. Используется в конструкторе класса.
	 *
	 * @param string $_rawTags Таги, перечисленные через "+". Строка может 
	 * быть в URL-encoded формате.
	 * @return array Массив декодированных тагов
	 * @access private
	 */
	function urlToTags($_rawTags) {
		// Декодируем
		$tags = trim(rawurldecode($_rawTags), '/');
		$tags = explode('+', $tags);
		foreach($tags as $i => $_) $tags[$i] = trim($_);
		$tags = array_unique($tags);
		sort($tags);
		if(!$tags[0]) unset($tags[0]);
		return $tags;
	}
	
	/**
	 * Метод используется для определения по запрошенному URI соттветствующего 
	 * ему файла страницы (если таковой существует) и виртуальной части URL 
	 * (если virtual path обабатываетс данной страницей). Механизм запросов файловых 
	 * страниц используется опционально, если конфигурационной константе CHECK_PAGES 
	 * задано значениt true.
	 * Метод предназначен только для внутриклассового использования и вызывается 
	 * из конструктора.
	 *
	 * 
	 *
	 * @param string $_rq запрошенный URI (без "корневой" части)
	 * @return mixed В случае успешного нахождения страницы, соответствующей запросу, 
	 * возвращает массив из двух элементов:
	 * 1. Путь к реальному файлу запрошенной страницы;
	 * 2. Остаточная ("виртуальная") часть пути, воспринимаемая как дополнительные параметры, 
	 * которые могут быть обработаны кодом страницы.
	 * В случае, если страница, соответствующая URI не существует или существует, но 
	 * не обрабатывает virtual path, при том, что он задан, метод возвращает значение false.
	 * @access private
	 */
	function checkPage($_rq) {
		$parts = explode('/', $_rq);
		$vPath = false;
		$path = false;
		
		for($i = count($parts); $i >= 0; $i--) {
			// Наличие в имени одной из составляющих пути первого символа '_' говорит о том, 
			// что ему не должна соответствовать страница. Даже если файл с таким именем 
			// существует, эта часть пути будет отнесена к virtual path
			if(!isset($parts[$i - 1][0]) || $parts[$i - 1][0] != '_') {
				
				$path = implode('/', array_slice($parts, 0, $i));
				if($this->pageFileExists($path, $vPath))  {
					return array('path' => $path, 'vpath' => $vPath);
				}
				
				// Отдельная проверка индексного файла, имя которого может не указываться в запросе
				$path = $path.($path?'/':'').'index';
				if($this->pageFileExists($path, $vPath)) {
					return array('path' => $path, 'vpath' => $vPath);
				}
				
			}
			
			$vPath = (isset($parts[$i - 1])?$parts[$i - 1]:'').($vPath?('/'.$vPath):'');
			
		}
		
		return $this->pageFileExists($path, $vPath)?array('path' => $path, 'vpath' => $vPath):false;
		
	}
		
	/**
	 * Проверяет существование страницы по заданному пути и виртуальному пути.
	 * Страница существует, если выполнены следующие условия:
	 * 1. Существует соответствующий ей текстовый файл
	 * 2. Виртуальный путь в запросе либо отсутствует, либо существует файл с кодом, 
	 * для обработки виртуальных путей этой страницы. Такие файлы имеют аналогичное 
	 * странице имя и суффикс .vpath.php
	 * МЕтод предназначен только для внутриклассового использования (используется внутри checkPage).
	 * @return bool true/falsе, в зависимости от того, существует ли данная страница или нет
	 * @access private
	 */
	function pageFileExists($_path, $_vPath) {
		return file_exists(DIR_PAGES.$_path.'.txt') && (!strlen($_vPath) || file_exists(DIR_PAGES.$_path.'.vpath.php'));
	}
	
}

?>