<?php

// Выозможные варианты статуса поста
define("PS_PUBLIC", 0);
define("PS_PRIVATE", 1);
define("PS_COMMUNITY", 2);
define("PS_DRAFT", 3);

// Текстовые названия статусов постов
$STATUSES = array(
		PS_PUBLIC => 'public',
		PS_PRIVATE => 'private',
		PS_COMMUNITY => 'community',
		PS_DRAFT => 'draft'
	);

// Статус поста, принимаемый по-умолчанию
define("PS_DEFAULT", PS_PRIVATE);

define("COMMENT_MODERATION_NEEDED", true);

// Константы AR_ используются дл определения и проверки прав доступа пользователя

define("AR_READING_COMMUNITY", 2); // Возможность читать посты, со статусом "только для своих"
define("AR_POSTING", 4); // Возможность писать посты в ленту, а так же редактировать и удалять свои посты
define("AR_MODERATION", 8); // Возможность редактировать и удалять посты других пользователей со статусом public. Комментарии к постам, оставляемые модераторами не нуждаются в модерации и становятся видны сразу
define("AR_SUPERVISING", 16); // Возможность редактировать и удалять посты других пользователей, вне зависимости от их статуса. Авторитаризм, да
define("AR_COMMENTING", 32); // Возможность писать комментарии к постам

define("ACCESS_GUEST", AR_COMMENTING); // Права доступа для незарегистрированного пользователя
define("ACCESS_GOD", AR_READING_COMMUNITY | AR_POSTING | AR_MODERATION | AR_SUPERVISING | AR_COMMENTING);
define("ACCESS_DEFAULT", AR_READING_COMMUNITY | AR_POSTING | AR_COMMENTING);
define("GUEST_NAME", "User Anonymous"); // Дефолтное имя анонимного пользователя

// Флаги, с помощью которых декодируется поле filters таблицы posta 
// (определяет набор фильтров текста, применяемых к контенту постов)
define("FILTER_NL2BR", 1);
define("FILTER_TYPO", 2);
define("FILTER_MARKDOWN", 4);

// Наборы флагов, которые используются в стандартных случаях
define("FILTERS_DEF", (DEF_USE_FILTER_NL2BR?FILTER_NL2BR:0) | 
	(DEF_USE_FILTER_TYPO?FILTER_TYPO:0) | (DEF_USE_FILTER_MARKDOWN?FILTER_MARKDOWN:0));
define("FILTERS_DESC", FILTER_TYPO | FILTER_MARKDOWN);
define("FILTERS_COMMENT", FILTER_NL2BR | FILTER_TYPO);

// Предельный значения
define("MAX_POSTS_PER_PAGE", 100);
define("MAX_SHORTCUT_LENGTH", 100);
define("MAX_POST_TITLE_LENGTH", 200);
define("MAX_POST_LENGTH", 102400);
define("MAX_COMMENT_TITLE_LENGTH", 200);
define("MAX_COMMENT_LENGTH", 4096);
define("MAX_USER_LOGIN_LENGTH", 50);
define("MAX_USER_PWD_LENGTH", 50);
define("MIN_USER_PWD_LENGTH", 3);
define("MAX_USER_NAME_LENGTH", 100);
define("MAX_USER_EMAIL_LENGTH", 100);
define("MAX_USER_HP_LENGTH", 100);
define("MAX_USER_DESC_LENGTH", 2048);
define("MAX_CAT_TITLE_LENGTH", 100);
define("MAX_CAT_DESC_LENGTH", 1024);
define("MAX_TAG_LENGTH", 50);
define("MAX_USER_CUSTOM_DATA_LENGTH", 2048);

// Опции вызова метода GetPost
define("GP_COMMENTS", 1); // Считать комментарии к посту
define("GP_NEIGHBOURS", 2); // Считать post_id ближайших к заданному по хронологии (ctime) постов

// регекспы для preg_match
define("REGEXP_LOGIN", "/^[a-zA-Z0-9_\-\.]{1,".MAX_USER_LOGIN_LENGTH."}$/");
define("REGEXP_EMAIL", "/^([a-zA-Z0-9_\-\.]+)@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.)|".
	"(([a-zA-Z0-9\-]+\.)+))([a-zA-Z]{2,4}|[0-9]{1,3})(\]?)$/");

// регекспы для mb_eregi
define("REGEXP_SHORTCUT", "^[^+\`~\@\%\&\(\)\[\]\{\}\/]{0,".MAX_SHORTCUT_LENGTH."}$");
define("REGEXP_TAG", "^[^\+\`~\@\%\&\(\)\[\]\{\}\/]{1,".MAX_TAG_LENGTH."}$");

define("REGEXP_SHORTCUT_PREG", "/".REGEXP_SHORTCUT."/");

// Parablog Database class

class BlogDB {
	
	var $db = false;
	var $userId = false;
	var $userAccessLevel = false;
	var $userLogin = false;
	var $userName = false;
	var $userEmail = false;
	var $userHp = false;
	
	var $tblPosts = false;
	var $tblComments = false;
	var $tblUsers = false;
	var $tblCats = false;
	var $tblTagsMap = false;
	var $tblTags = false;
	
	/**
	 * Переменная, значение которой обновляется после срабатывания метода GetPostsPage. 
	 * Принимает значение true, если была запрошена последняя страница с постами и страниц 
	 * с бОльшими номерами нет.
	 */
	var $lastPage = false;
	
	/**
	 * Счётчик SQL запросов
	 */
	var $rqCnt = 0;
	
	var $errorMsg = false;
	
	/**
	 * Конструктор класса
	 *
	 * @param object $_adoDbConnection объект ADODbConnection,
	 * пдключённый к БД
	 * @param array _currentUserData Данные текущего пользователя,
	 * залогиненного в систему. В первую очередь актуален его уровень 
	 * доступа и user_id. Если текущий пользователь - аноним, 
	 * то значение этого параметра необходимо определять стандартными 
	 * для него значениями
	 * @param int $_userId user_id текущего пользователя. Если это аноним, 
	 * то значением этого параметра должно быть false
	 * @param int $_userAccessLevel Уровень доступа текущего пользователя. 
	 * Для зарегистрированных пользователей это значение берётся из базы 
	 * данных, а для анонимов (user_id == -1) задаётся общее значение 
	 * константы ACCESS_GUEST, определённой в конфиге (в последнем случае 
	 * значение параметра игнорируется)
	 * @param string $_userLogin Логин пользователя (для анонимов указывать false)
	 * @param string $_userName Имя пользователя (для анонимов указывать false)
	 * @param string $_userEmail email пользователя (для анонимов указывать false)
	 * @param string $_userHp URL домашней страницы пользователя (для анонимов 
	 * указывать false)
	 * @param string $_tblPosts Имя таблицы постов
	 * @param string $_tblComments Имя таблицы комментариев
	 * @param string $_tblUsers Имя таблицы пользователей
	 * @param string $_tblCats Имя таблицы категорий
	 * @param string $_tblTagsMap Имя таблицы с ассоциативными связями тагов
	 * @param string $_tblTags Имя таблицы тагов
	 */
	function BlogDB(
		$_adoDbConnection, 
		$_userId,
		$_tblPosts, 
		$_tblComments, 
		$_tblUsers, 
		$_tblCats, 
		$_tblTagsMap, 
		$_tblTags) {
		
		$this->db = $_adoDbConnection;
		$this->userId = (int)$_userId;
		
		$this->tblPosts = $_tblPosts;
		$this->tblComments = $_tblComments;
		$this->tblUsers = $_tblUsers;
		$this->tblCats = $_tblCats;
		$this->tblTagsMap = $_tblTagsMap;
		$this->tblTags = $_tblTags;
		
		if($_userId == -1) {
			// Пользователь - аноним
			
			// userAccessLevel и userName задаются всегда
			$this->userAccessLevel = ACCESS_GUEST;
			
			// userName может быть восстановлен из сессии, если он был задан
			$this->userName = (isset($_SESSION['user_name']) && $_SESSION['user_name'])?
				$_SESSION['user_name']:GUEST_NAME;
			
			// userEmail и userHp задаются опционально (только если пользователь один раз их задал)
			if(isset($_SESSION['user_email']) && $_SESSION['user_email']) $this->userEmail = $_SESSION['user_email'];
			if(isset($_SESSION['user_hp']) && $_SESSION['user_hp']) $this->userHp = $_SESSION['user_hp'];
			
		} else {
			
			// Пользователь зарегистрирован
			// Необходимо считать его данные из базы
			$sqlRequest = "SELECT * FROM ".$this->tblUsers." WHERE user_id = ".$_userId;
			$result = $this->Execute($sqlRequest);
			if(!$result) return false;
			
			if(!$result->RecordCount()) {
				
				// Пользователь не найден (запись могла быть стёрта, либо данные в сессии 
				// по какой-то причине оказались некорректны). Регистрируем юзера как анонима
				$this->userId = -1;
				$this->userAccessLevel = ACCESS_GUEST;
				
 			} else {
 				
 				// Пользователь найден. Копируем считанные из БД данные во внутренние 
 				// переменные класса
				$result = $result->GetArray();
				$user = $result[0];
				
				$this->userName = (string)$user['name'];
				$this->userLogin = (string)$user['login'];
				$this->userEmail = (string)$user['email'];
				$this->userHp = (string)$user['hp'];
				$this->userAccessLevel = (int)$user['access_level'];
				
			}
			
		}
		
		//$sqlRequest = "SET NAMES 'utf8';";
		//$this->Execute($sqlRequest);
		
		// Set internal character encoding to UTF-8
		// PHP 4 >= 4.0.6
		mb_internal_encoding("UTF-8");
		
	}
	
	/**
	 * SQL request executor (ADO db function wrapper)
	 *
	 * @paran string $_sql SQL запрос, который необходимо выполнить
	 * @paran string $_debugMsg Сообщение, которое будет выведено, если DEBUG_MODE
	 */
	function Execute($_sql, $_debugMsg = false) {
		if(DEBUG_MODE && $_debugMsg) {
			echo '<ul><li><p style="color:gray;">'.($_debugMsg).'</p>'.
				'<p ctyle="margin-top:5mm;"><code>'.print_r($_sql, 1).'</code></p></li></ul>';
		}
		$result = $this->db->Execute($_sql);
		if(!$result) {
			$this->errorMsg = $this->db->ErrorMsg();
		}
		$this->rqCnt++;
		return $result;
	}
	
	/**
	 * Проверяет корректность синтаксиса одного или нескольких тагов. 
	 * Для проверки синтаксиса используется регулярное выражение, определённое 
	 * константой REGEXP_TAG
	 *
	 * @param mixed $_tag Проверяемый таг (string) или массив тагов
	 * @return bool true или false, в зависимости от результата.
	 */
	function TagSyntaxOk($_tag) {
		// Note: mb_eregi function is supported in PHP 4.2.0 or higher.
		if(is_string($_tag)) {
			
			return (bool)mb_eregi(REGEXP_TAG, $_tag);
			
		} elseif(is_array($_tag)) {
			
			foreach($_tag as $tag) {
				if(!mb_eregi(REGEXP_TAG, $tag)) return false;
			}
			
			return true;
			
		}
		
		return false;
		
	}
	
	/**
	 * Добавление нового поста (автоматическое добавление новых 
	 * категорий и тагов, обновление значения счётчиков категорий 
	 * и тагов). В качестве ID пользователя-автора создаваемого поста 
	 * берётся значение, указанное при создании объекта
	 *
	 * @param string $_tite Заголовок поста (сабж)
	 * @param string $_content Контент поста
	 * @param string $_postStatus Статус поста (см. константы
	 * PS_*). По-умолчанию используется статус
	 * PS_DEFAULT
	 * @param string $_shortcut Уникальный короткий путь к посту. если
	 * не требуется, можно не указывать или задать значение 
	 * false - в таком случае он сгенерируется автоматически 
	 * на основании заголовка поста.
	 * @param string $_ctime Дата и ремя создания поста в формате 
	 * unixtime. По-умолчанию используется текущее значение.
	 * @param string $_catName Имя категории, в которой будет 
	 * размещён пост. По-умолчанию используется категория <unsorted>
	 * @param array $_tags Массив с именами тагов, с которыми будет
	 * ассоциирован пост.
	 * @return mixed ID созданного поста или false в случае ошибки
	 */
	function AddPost(
			$_title, 
			$_content, 
			$_postStatus = false, 
			$_shortcut = false, 
			$_ctime = false, 
			$_catShortcut = false,
			$_tags = false,
			$_filters = FILTERS_DEF
		) {
		
		// Проверка первичного условия возможности добавления 
		// поста - наличия у текущего пользователя прав
		if(!$this->CanAddPost()) {
			$this->errorMsg = "Current user have no access level to write posts.";
			return false;
		}
		
		// Проверить допустимость значений всех заданных параметров
		// Переменные с префиксом $post будут содержать обработанные значения
		// параметров для добавления в таблицу
		
		$postTitle = trim($_title);
		if(mb_strlen($postTitle, "utf-8") > MAX_POST_TITLE_LENGTH) {
			$this->errorMsg = 'Post title length limit exceeded.';
			return false;
		}
		
		$postContent = trim($_content);
		if(mb_strlen($postContent, "utf-8") > MAX_POST_LENGTH) {
			$this->errorMsg = 'Post content length limit exceeded.';
			return false;
		}
		
		$postStatus = $_postStatus?$_postStatus:0;
		if(!is_numeric($postStatus)) {
			$this->errorMsg = 'Post status bad value.';
			return false;
		}
		
		$postFilters = 0;
		if(is_numeric($_filters)) {
			$postFilters = $_filters;
		} elseif($_filters !== false) {
			$this->errorMsg = 'Content filters bad value.';
			return false;
		}
		
		if($_shortcut) {
			$postShortcut = $_shortcut;
			
			// Shortcut может состоять из латинских букв, цифр, символов тире и подчёркивания.
			if(!mb_eregi(REGEXP_SHORTCUT, $postShortcut)) {
				$this->errorMsg = 'Bad post shortcut specified (length limit'.
					' exceeded or inappropriate characters used).';
				return false;
			}
		} else {
			// Либо может быть не задан вообще
			$postShortcut = "";
		}
		
		$postCtime = date("YmdHis", is_numeric($_ctime)?$_ctime:time());
		
		// на основании заданного $_catShortcut будет найдет необходимый cat_id
		// если $_catShortcut не определено, будет использоваться категория
		// по-умолчанию (unsorted с cat_id == 0)
		if($_catShortcut !== false && !mb_eregi(REGEXP_SHORTCUT, $_catShortcut)) {
			
			$this->errorMsg = 'Bad category shortcut specified (length limit'.
				' exceeded or inappropriate characters used).';
			return false;
		}
		
		// Корректность определённых для поста тагов проверяется внутри метода AssociateTags
		
		// Валидация параметров выполнена
		
		// NIHAMG: $this->userId, $postTitle, $postContent, $postStatus, 
		// $postShortcut, $postCtime, $_catShortcut
		
		// Проверить существование заданной категории
		if($_catShortcut) {
			$sqlRequest = "SELECT * FROM ".$this->tblCats.
				" WHERE shortcut = '".$_catShortcut."'";
			$result = $this->Execute($sqlRequest, 'Проверяем существование заданной категории');
			if(!$result) return false;
			
			if(!$result->RecordCount()) {
				
				// Категория не существует. Необходимо создать её, если у пользователя 
				// есть на это права, или выдать сообщение об ошибке в противном случае
				if($this->CanManageCats()) {
					$postCatId = $this->AddCat($_catShortcut, $_catShortcut, "");
				} else {
					$this->errorMsg = "Required category doesn't exists. User have no access '.
						'rights to create new categories.";
					return false;
				}
				
			} else {
				// Сохраняем ID найденной категории
				$postCatId = $result->Fields(0);
			}
		} else {
			// Категория не задана. Используется дефолтная категория
			// uncategorized (cat_id == 0)
			$postCatId = 0;
		}
		
		// NIHAMG: $postCatId - идентификатор существующей категории, 
		// в которую будет добавлен пост
		
		// Определяем индекс для нового поста
		$sqlRequest = "SELECT MAX(post_id) FROM ".$this->tblPosts;
		$result = $this->Execute($sqlRequest, 'Определяем индекс для нового поста');
		if(!$result) return false;
		
		$postId = (int)$result->Fields(0) + 1;
		
		// NIHAMG: $postId, $postCatId, $this->userId, $postTitle, $postContent, 
		// $postStatus, $postShortcut, $postCtime
		// всё есть и проверено
		
		// отфильтровать контент поста
		$postCachedContent = $this->FilterText($postContent, $postFilters);
		$postCachedAuthor = addslashes(serialize(array(
				"user_id" => $this->userId,
				"access_level" => $this->userAccessLevel,
				"login" => $this->userLogin,
				"name" => $this->userName,
				"email" => $this->userEmail,
				"hp" => $this->userHp
			)));
		
		// Добавляем пост в таблицу постов
		$sqlRequest = "INSERT INTO ".$this->tblPosts." (post_id, cat_id, user_id, 
			status, ctime, mtime, filters, shortcut, title, content, cached_tags, cached_com_cnt, 
			cached_content, cached_author) VALUES (".
			$postId.", ".$postCatId.", ".$this->userId.", ".$postStatus.", ".$postCtime.", ".
			$postCtime.", ".$postFilters.", '".$postShortcut."', '".addslashes($postTitle)."', '".
			addslashes($postContent)."', '', 0, '".addslashes($postCachedContent)."', '".
			$postCachedAuthor."')";
		$result = $this->Execute($sqlRequest, "Добавляем пост в таблицу постов");
		if(!$result) return false;
		
		// Обновляем счётчики постов в категории $postCatId
		$result = $this->UpdateCatCount($postCatId);
		if(!$result) return false;
		
		// Обновляем счётчики постов в таблице пользователей
		$result = $this->UpdateUserPostCounters($this->userId);
		if(!$result) return false;
		
		// NIHAMG: $postId, $_tags (array)
		
		// Ассоциируем пост с тагами
		$result = $this->AssociateTags($postId, $_tags, $postStatus);
		if(!$result) return false;
		
		return $postId;
	}
	
	/**
	 * Метод определяет право текущего пользователя на постинг. Используется внутри 
	 * метода AddPost, перед выполнением постинга, а так же внутри action-ов, 
	 * где требуется проверка возможности пользователя добавлять посты (например, EditPost)
	 * @return bool true/false, в зависимости от того, позволено ли 
	 * пользователю писать новые посты или нет.
	 */
	function CanAddPost() {
		return ($this->userAccessLevel & AR_POSTING)?true:false;
	}
	
	/**
	 * Добавление комментария к заданному посту. Обновление счётчика 
	 * комментариев для заданного поста. Комментарий добавляется от лица юзера, 
	 * которому принадлежит instamce класса (текущего пользователя). В этом 
	 * случае обязательно определение значения параметра $_authorName
	 *
	 * @param int $_postId ID поста, к которому добавлятся комментарий
	 * @param mixed $_parentId ID коммента, ответом на который будет 
	 * добавляемый коммент (если коммент не яавляется ответом на другой 
	 * коммент, нужно указать false)
	 * @param string $_subj тема комментария (можно использовать пустое
	 * значение)
	 * @param string $_content содержание комментария (пустое значение
	 * использовать нельзя)
	 * @param bool $_hidden Определяет, будет ли коммент виден только автору поста, 
	 * или всем, кто имеет доступ к посту. По-умолчанию коммент виден всем, кому 
	 * виден пост
	 * @param string $_authorName имя комментатора. Параметр задаётся только 
	 * в том случае, если юзер не зарегистрирован и указал информацию о себе 
	 * только для добавления комментария. В противном случае параметр можно опустить, 
	 * использовав значение false. То же касается параметров $_authorEmail и $_authorHp.
	 * @param string $_authorEmail email комментатора. 
	 * @param string $_authorHp Адрес домашней страницы комментатора. 
	 * @return mixed ID добавленного комментария или false в случае ошибки
	 */
	function AddComment(
		$_postId, 
		$_parentId,
		$_subj, 
		$_content,
		$_hidden = false, 
		$_authorName = false, 
		$_authorEmail = false, 
		$_authorHp = false) {
		
		// Проверка первичного условия возможности написания 
		// комментария - наличия у текущего пользователя прав на комментирование
		if(!($this->userAccessLevel & AR_COMMENTING)) {
			$this->errorMsg("Current user have no access level to write comments.");
			return false;
		}
		
		// Проверка корректности параметров
		if(!is_numeric($_postId) || !(is_numeric($_parentId) || 
			is_bool($_parentId)) || !(is_bool($_hidden) || 
			is_numeric($_hidden))) {
			$this->errorMsg = "Incorrect parameters specified.";
			return false;
		}
		
		// Для комментов, которые не являются ответами на другие комменты,
		// parent_id должно быть равно -1
		$parentId = ($_parentId === false)?-1:$_parentId;
		
		// Имя пользователя берётся из базы данных в том случае, если 
		// он зарегистрирован. Если комментарий оставляет незарегистрированный 
		// пользователь (user_id == -1), его имя должно быть указано в параметре.
		if($this->userId !== -1) {
			$authorName = $this->userName;
			$authorEmail = $this->userEmail;
			$authorHp = $this->userHp;
		} else {
			$authorName = $_authorName;
			$authorEmail = $_authorEmail;
			$authorHp = $_authorHp;
		}
		
		// Проверяем корректность заданных параметров только в том 
		// случае, если это необходимо
		
		// Преобразуем значения параметров для использования внутри SQL запросв
		$authorName = addslashes($authorName);
		if(!mb_strlen($authorName, "utf-8") > MAX_USER_NAME_LENGTH) {
			$this->errorMsg = "Author name maximum length exceeded.";
			return false;
		}
		
		if(!preg_match(REGEXP_EMAIL, $authorEmail)) {
			$this->errorMsg = "Incorrect email specified.";
			return false;
		}
		$authorEmail = addslashes($authorEmail);
		if(!mb_strlen($authorEmail, "utf-8") > MAX_USER_EMAIL_LENGTH) {
			$this->errorMsg = "Author email maximum length exceeded.";
			return false;
		}
		
		$authorHp = addslashes($authorHp);
		if(!mb_strlen($authorHp, "utf-8") > MAX_USER_HP_LENGTH) {
			$this->errorMsg = "Homepage URL maximum length exceeded.";
			return false;
		}
		
		$subj = addslashes($_subj);
		if(mb_strlen($subj, "utf-8") > MAX_COMMENT_TITLE_LENGTH) {
			$this->errorMsg = "Maximum length exceeded for comment`s subject.";
			return false;
		}
		
		$comContent = trim($_content);
		$comCachedContent = $this->FilterText($comContent, FILTERS_COMMENT);
		if(mb_strlen($comCachedContent, "utf-8") > MAX_COMMENT_LENGTH) {
			$this->errorMsg = "Maximum length exceeded for comment`s text.";
			return false;
		}
		
		// Проверить наличие поста
		$sqlRequest = "SELECT status,user_id FROM ".$this->tblPosts.
			" WHERE post_id = ".$_postId;
		$result = $this->Execute($sqlRequest, 
			"Проверяем наличие комментируемого поста в базе.");
		
		// если поста не существует, выход return false
		if(!$result->RecordCount()) {
			$this->errorMsg = "Commented post doesn`t exists.";
			return false;
		}
		
		// Значение статуса поста сохранено в поле номер 0 самой первой записи из выборки
		$postStatus = $result->Fields(0);
		$postAuthorId = $result->Fields(1);
		
		// Проверка вторичного условия допустимости писать комментариий 
		if(!$this->CanAddComment($postStatus, $postAuthorId)) {
			$this->errorMsg = "User has no access rights to comment this post.";
			return false;
		}
		
		// Определяем comment_id для нового комментария
		$sqlRequest = "SELECT MAX(comment_id) FROM ".$this->tblComments;
		$result = $this->Execute($sqlRequest, 'Определяем comment_id для нового комментария');
		if(!$result) return false;
		$commentId = (int)$result->Fields(0) + 1;
		
		// Статус нового комментария определяется в зависимости от типа пользователя, 
		// который его оставил и необходимсти модерировать посты (системной опции)
		$moderated = $this->CanCommentImmediately()?1:0;
		
		// Флаг, определяющий скрытость комментария от всех кроме автора поста
		$hidden = $_hidden?1:0;
		// Дата создания комментария
		$ctime = date("YmdHis", time());
		
		// Добавляем комментарий в базу
		$sqlRequest = "INSERT INTO ".$this->tblComments.
			" (comment_id, parent_id, post_id, user_id, moderated, hidden, ctime, ".
			"author_name, author_email, author_hp, subj, content, cached_content) ".
			"VALUES (".$commentId.", ".$parentId.", ".$_postId.", ".$this->userId.", ".
			$moderated.", ".$hidden.", ".$ctime.", '".$authorName."', '".
			$authorEmail."', '".$authorHp."', '".$subj."', '".addslashes($comContent)."', '".
			addslashes($comCachedContent)."')";
		$this->Execute($sqlRequest, "Добавляем комментарий");
		
		// Обновляем счётчик комментов поста
		$this->UpdateCommentsCount($_postId);
		if(!$result) return false;
		
		// Обновляем счётчики комментариев в таблице пользователей
		// для автора комментария и автора комментируемого поста
		$result = $this->UpdateUserComCounters($this->userId);
		if(!$result) return false;
		
		$result = $this->UpdateUserComCounters($postAuthorId);
		if(!$result) return false;
		
		return $commentId;
	}
	
	/**
	 * Метод проверяет возможность текущего пользователя написать коммент к заданному посту
	 * @param int $_postStatus Статус поста
	 * @param int $_postAuthorId user_id автора поста
	 * @return bool true/false, в зависимости от того, можно ли пользователю комментировать 
	 * пост или нет
	 */
	function CanAddComment($_postStatus, $_postAuthorId) {
		// (хотя бы одно из них должно быть выполнено):
		//   - Пост имеет статус PS_PUBLIC
		//   - Пост имеет статус PS_COMMUNITY, пользователь зарегистрирован в базе
		//   - Пользователь имеет статус Большого Брата (AR_SUPERVISING). 
		//     При этом статус поста значения не имеет.
		return ($this->userAccessLevel & AR_COMMENTING) &&
			$this->CanGetPost($_postStatus, $_postAuthorId);
	}
	
	/**
	 * Добавление новой категории
	 *
	 * @param string $_shortcut короткое имя категории, которое будет 
	 * использоваться для формирования её URL. можно использовать 
	 * латинские буквы, цифры и символы [-_.]
	 * @param string $_title полное название категории (необязательный 
	 * параметр)
	 * @param string $_desc описание категории (необязательный 
	 * параметр)
	 * @return mixed ID созданной категории или false, в случае ошибки
	 */
	function AddCat(
		$_shortcut, 
		$_title = false, 
		$_desc = false) {
		
		/*
		// Проверка первичного условия возможности добавления 
		// поста - наличия у текущего пользователя прав
		if(!($this->userAccessLevel & AR_POSTING)) {
			$this->errorMsg = "Current user have no access level to create categories.";
			return false;
		}
		*/
		
		// Проверк аналичия прав у пользователя для добавления  БД новой категории
		if(!$this->CanManageCats()) {
			$this->errorMsg = "User have no access level to create categories.";
			return false;
		}
		
		// Проверка допустимости значений параметров
		$shortcut = $_shortcut;
		if(!mb_eregi(REGEXP_SHORTCUT, $shortcut)) {
			$this->errorMsg = "Bad shortcut specified (inappropriate characters used or maximum length limit exceeded).";
			return false;
		}
		
		$catTitle = addslashes($_title);
		if(mb_strlen($catTitle, "utf-8") > MAX_CAT_TITLE_LENGTH) {
			$this->errorMsg = "Maximum length exceeded for category title.";
			return false;
		}
		
		$catDescription = addslashes($_desc);
		$catCachedDescription = addslashes($this->FilterText($_desc, FILTERS_DESC));
		if(mb_strlen($catCachedDescription, "utf-8") > MAX_CAT_DESC_LENGTH) {
			$this->errorMsg = "Maximum length exceeded for category description.";
			return false;
		}
		
		// Проверка наличия натегории с заданным именем
		$sqlRequest = "SELECT COUNT(*) FROM ".
			$this->tblCats." WHERE shortcut = '".$shortcut."'";
		$result = $this->Execute($sqlRequest, 'Проверяем наличие натегории с заданным именем');
		if(!$result) return false;
		
		if($result->Fields(0)) {
			// категория с заданным коротким имененм уже существует
			$this->errorMsg = "Category with the same shortcut already exists.";
			return false;
		}
		
		// сформировать SQL запрос для добавления новой категории
		
		// Если title не определён, в это поде копируется значение shortcut
		$catTitle = $_title?$catTitle:$shortcut;
		
		// определяем индекс новой категории
		$sqlRequest = "SELECT MAX(cat_id) FROM ".$this->tblCats;
		$result = $this->Execute($sqlRequest, "Определяем индекс новой категории");
		if(!$result) return false;
		
		$catId = (int)$result->Fields(0) + 1;
		
		// запрос на добавление новой категории
		$sqlRequest = "INSERT INTO ".$this->tblCats.
			" (cat_id, shortcut, title, description, cached_cnt, cached_cnt_reg, ".
			"cached_cnt_total, cached_description) VALUES (".$catId.", '".$shortcut.
			"', '".$catTitle."', '".$catDescription."', 0, 0, 0, '".$catCachedDescription."')";
		$result = $this->Execute($sqlRequest, "Добавляем новую категорию - ".$_shortcut);
		if(!$result) return false;
		
		return $catId;
		
	}
	
	/**
	 * Проверяет наличие прав у пользователя создавать новые категории.
	 * Эта возможность есть у модераторов и супервизоров.
	 *
	 * @return bool true/flase, в зависимости от того, можно или нет
	 */
	/*
	function CanAddCat() {
		
		return $this->userAccessLevel & (AR_SUPERVISING | AR_MODERATION);
		
	}
	*/
	
	/**
	 * Выборка одного поста по его post_id
	 *
	 * @param int $_postId ID нужного поста
	 * @param int $_options Перечень опций работы метода. Определяет перечень связанных 
	 * с постом данных, которые будут возвращены в результате работы метода. Параметр 
	 * главным образом используется для экономии на количестве выполняемых SQL запросов. 
	 * Значение параметра формируется с помощью констант GP_*, перечисляемых через 
	 * бинарное ИЛИ. Подробности о возможных параметрах вызова метода см. в описании 
	 * констант GP_*.
	 * @return mixed ассоциативный массив с полным содержанием поста, 
	 * тагами и именем категории. Если задан несуществующий пост, false.
	 */
	function GetPost($_postId, $_options = 0) {
		
		// Проверка корректности параметра
		if(!is_numeric($_postId)) {
			$this->errorMsg = "Incorrect post ID specified.";
			return false;
		}
		
		// сформировать и выполнить запрос
		$sqlRequest = "SELECT * FROM ".$this->tblPosts." WHERE post_id = ".$_postId;
		$result = $this->Execute($sqlRequest, 'Считываем пост');
		$result = $result->GetArray();
		
		// Проверка существованиеи поста
		if(!count($result)) {
			return false;
		}
		
		$post = $result[0];
		
		// Условие: Пользователь может видеть пост
		$postVisible = $this->CanGetPost($post["status"], $post["user_id"]);
		
		if(!$postVisible) {
			$this->errorMsg = "Post doesn`t exists or user have no access rights to see it.";
			return false;
		}
		
		// Выбрасываем дубликаты
		foreach($post as $field => $value) {
			if(is_numeric($field)) unset($post[$field]);
		}
		
		// Конвертируем время из формата ьазы данных в unixtime
		$post['ctime'] = strtotime($post['ctime']);
		$post['mtime'] = strtotime($post['mtime']);
		
		$post['post_id'] = (int)$post['post_id'];
		$post['cat_id'] = (int)$post['cat_id'];
		$post['user_id'] = (int)$post['user_id'];
		
		// Оставляем только одно значение счётчика комемнтариев, акутальное для текущего пользователя
		// Поле всегда будет назваться cached_com_cnt
		if($this->userAccessLevel & (AR_MODERATION | AR_SUPERVISING)) {
			// Елси пользователь модератор или супервизор, он может видеть полное количество комментариев 
			// к посту, включая скрытые и неотмодерированные
			$post['cached_com_cnt'] = $post['cached_com_cnt_total'];
		}
		
		// Убираем из массива лишнее поле
		unset($post['cached_com_cnt_total']);
		
		// Если значение поля cached_tags устарело, обновляем его
		if($post['cached_tags'] == ',,') {
			$post['cached_tags'] = $this->UpdateCachedTags($_postId);
		} else {
			// Если поле cached_tags не пустое, преобразуем его в массив
			$post['cached_tags'] = trim($post['cached_tags'])?
				explode(",", $post['cached_tags']):array();
		}
		
		// Считываем информацию о категории поста
		$sqlRequest = "SELECT shortcut, title, cached_description FROM ".$this->tblCats.
			" WHERE cat_id = ".$post['cat_id'];
		$result = $this->Execute($sqlRequest, 'Считываем информацию о категории поста');
		$result = $result->GetArray();
		$result = $result[0];
		
		// Добавляем данные из таблицы категорий к результату
		$post = array_merge($post, array(
				"cat_shortcut" => $result["shortcut"],
				"cat_title" => $result["title"],
				"cat_description" => $result["cached_description"]
			));
		
		// Расшифровываем инфу об авторе поста (десериализуем поле БД cached_author)
		// и добавляем в результат как отдельные элементы массива
		$author = unserialize($post['cached_author']);
		unset($post['cached_author']);
		$post = array_merge($post, array(
				"user_login" => $author['login'],
				"user_name" => $author['name'],
				"user_email" => $author['email'],
				"user_hp" => $author['hp']
			));
		
		// Определяем следующий и предыдущий пост относительно текущего, 
		// в хронологическом порядке
		if($_options & GP_NEIGHBOURS) {
			
			// Формируем условие видимости постов, определяющее выборку постов 
			// в последовательности, на основе которой будет определяться следующий 
			// и предыдущий посты, относительно текущего
			
			// Условие точно такое же, как и в методе GetPostsPage
			
			// В зависимости от прав доступа текущего пользователя, 
			// выбирается одно из условий видимости постов для выборки
			// - Для супервизора видно всё
			// - Для всех "status = public"
			// - Для userAccessLevel & AR_READ_COMMUNITY - " OR status = community"
			// - Для зарегистрированных пользователей (userId >= 0) - " OR user_id = <user_id>"
			if(!($this->userAccessLevel & AR_SUPERVISING)) {
				$visCond = "(status = ".PS_PUBLIC;
				if($this->userAccessLevel & AR_READING_COMMUNITY) {
					$visCond .= " OR status = ".PS_COMMUNITY;
				}
				if($this->userId >= 0) {
					$visCond .= " OR user_id = ".$this->userId;
				}
				$visCond .= ')';
			} else {
				$visCond = "";
			}
			
			if($visCond) $visCond .= " AND ";
			
			$sqlTime = date('YmdHis', $post['ctime']);
			$sqlRequest = 'SELECT post_id, ctime, title FROM '.$this->tblPosts.
				' WHERE '.$visCond.'((ctime > '.$sqlTime.') OR (ctime = '.$sqlTime.
				' AND post_id > '.$_postId.')) ORDER BY ctime ASC, post_id ASC LIMIT 1';
			$result = $this->Execute($sqlRequest, 'Ищем post_id следующего поста по хронологии');
			if(!$result) return false;
			
			if($result->RecordCount()) {
				// post_id следующего поста найден
				$post['next_post_id'] = $result->Fields(0);
			} else {
				// Следующий пост не существует. Текущий пост - последний в базе
				$post['next_post_id'] = false;
			}
			
			$sqlRequest = 'SELECT post_id, ctime, title FROM '.$this->tblPosts.
				' WHERE '.$visCond.'((ctime < '.$sqlTime.') OR (ctime = '.$sqlTime.' AND post_id < '.$_postId.'))'.
				' ORDER BY ctime DESC, post_id DESC LIMIT 1';
			$result = $this->Execute($sqlRequest, 'Ищем post_id предыдущего поста');
			if(!$result) return false;
			
			if($result->RecordCount()) {
				// post_id предыдущего поста найден
				$post['prev_post_id'] = $result->Fields(0);
			} else {
				// Предыдущий пост не существует. Текущий пост - первый в базе
				$post['prev_post_id'] = false;
			}
			
		}
		
		if(!($_options & GP_COMMENTS)) {
			return $post;
		}
		
		// Считываем комментарии к посту
		
		// Условие видимости комментариев для текущего пользователя
		$where = 'post_id = '.$_postId;
		
		// Неотмодерированные посты могут быть видны только
		// модераторам, супервизорам и автору поста
		if(!(($this->userAccessLevel & (AR_MODERATION | AR_SUPERVISING)) || 
			($this->userId == $post['user_id']))) {
			
			$where .= ' AND moderated = 1';
		}
		
		// Скрытые посты могут быть видимы только авторам
		// и больше вообще никому
		if($this->userId != $post['user_id']) {
			$where .= ' AND hidden = 0';
		}
		
		// Формируесм SQL запрос на ситыванние комментов
		$sqlRequest = "SELECT * FROM ".$this->tblComments.
			" WHERE ".$where." ORDER BY ctime";
		$result = $this->Execute($sqlRequest, "Считываем комменты к посту.");
		if(!$result) return false;
		
		if(!$result->RecordCount()) {
			// Комментов нет
			$comments = array();
		} else {
			// Комментов есть
			$result = $result->GetArray();
			$comments = array();
			
			foreach($result as $comment) {
				// Проверяем права доступа пользователя, чтобы определть видимость 
				// каждого комментария:
				$commentVisible = 
					// Коммент отмодерирован и не спрятан
					(($comment['moderated'] && !$comment['hidden']) ||
					// Коммент написан текущим пользователем, и этот пользователь 
					// зарегистрирован (не гость)
					(($comment["user_id"] == $this->userId) && ($this->userId >= 0)) ||
					// Пост, к которому относится коммент, написан текущим пользователем
					(($post["user_id"] == $this->userId) && ($this->userId >= 0)));
				
				if(!$commentVisible) {
					// Пропускаем невидимые для текущего пользователя комменты
					continue;
				}
				
				// Убираем дубликаты
				foreach($comment as $field => $value) {
					if(is_numeric($field)) unset($comment[$field]);
				}
				
				// Преобразуем время создания коммента к UNIX time
				$comment['ctime'] = strtotime($comment['ctime']);
				
				$comments[] = $comment;
			}
		}
		
		// Добавляем к результату массив комментов. Если комментов нет, 
		// массив будет пустым
		$post['comments'] = $comments;
		return $post;
		
	}
	
	/**
	 * Метод определяет право текущего пользователя читать 
	 * пост с заданными параметрами
	 * @param int $_postStatus Статус поста
	 * @param int $_postAuthorId user_id автора поста
	 * @return bool true/false, в зависимости от того, можно ли пользователю видеть пост или нет
	 */
	function CanGetPost($_postStatus, $_postAuthorId) {
		return 
			// Пост имеет статус public
			($_postStatus == PS_PUBLIC) ||
			// Пост имеет статус community, пользователь имеет право читать посты со статусом community
			(($_postStatus == PS_COMMUNITY) && ($this->userAccessLevel & AR_READING_COMMUNITY)) ||
			// Пользователь зарегистрирован и является автором поста
			(($_postAuthorId == $this->userId) && ($this->userId >= 0)) || 
			// Пользователь - супервизор и ему можно всё
			($this->userAccessLevel & AR_SUPERVISING);
	}
	
	/**
	 * Выборка одного комментария по его comment_id
	 *
	 * @param int $_commentId ID требуемого комментария
	 * @return mixed ассоциативный массив с полным содержанием 
	 * нужного комментария. если задан несуществующий комменарий, 
	 * возвращает false
	 */
	function GetComment($_commentId) {
		
		// Поверить допустимость значения параметра
		if(!is_numeric($_commentId)) {
			$this->errorMsg = "Incorrect comment ID specified.";
			return false;
		}
		
		// Считать коммент из базы
		$sqlRequest = "SELECT * FROM ".$this->tblComments.
			" WHERE comment_id = ".$_commentId." LIMIT 1";
		$result = $this->Execute($sqlRequest, 'Считываем коммент');
		
		// Если коммент не существует, отдать false
		if(!$result->RecordCount()) {
			$this->errorMsg = "Comment doesn`t exists.";
			return false;
		}
		
		$result = $result->GetArray();
		$comment = $result[0];
		
		$postId = $comment["post_id"];
		
		// Проверяем существование поста
		$sqlRequest = "SELECT user_id, status FROM ".$this->tblPosts.
			" WHERE post_id = ".$postId." LIMIT 1";
		$result = $this->Execute($sqlRequest, 'Проверяем существование поста.');
		
		// Если пост, к которому относится коммент не существует, отдать false
		if(!$result->RecordCount()) {
			$this->errorMsg = "Commented post doesn`t exists.";
			return false;
		}
		
		$postAuthorId = $result->Fields(0);
		$postStatus = $result->Fields(1);
		
		// NIHAMG: $comment, $postAuthorId, $postStatus
		
		// Пользователь может прочитать комментарий, если:
		// - Пользователь может видеть пост:
		//   - Пользователь имеет право AR_READING_COMMUNITY, пост имеет статус PS_COMMUNITY
		//   - Пользователь является автором поста. При этом он не аноним (user_id > 0)
		//   - Пользователь имеет право AR_SUPERVISING
		// - Выполнено одно из условий:
		//   - Коммент отмодерирован и не скрыт
		//   - Коммент написан текущим пользователем, при этом пользователь не аноним
		//   - Пользователь - автор текущего поста
		
		// Условие: Пользователь может видеть пост
		$postVisible = 
			($postStatus == PS_PUBLIC) || 
			(($postStatus == PS_COMMUNITY) && ($this->userAccessLevel & AR_READING_COMMUNITY)) ||
			(($postAuthorId == $this->userId) && ($this->userId >= 0)) ||
			($this->userAccessLevel == AR_SUPERVISING);
		
		$commentVisible = $postVisible && 
			(($comment['moderated'] && !$comment['hidden']) ||
			(($comment["user_id"] == $this->userId) && ($this->userId >= 0)) ||
			(($postAuthorId == $this->userId) && ($this->userId >= 0)));
		
		if(!$commentVisible) {
			$this->errorMsg = "Commented post doesn`t exists or user have no access rights to see it.";
			return false;
		}
		
		// Преобразовать данные к удобному формату и отдать их
		foreach($comment as $field => $value) {
			if(is_numeric($field)) unset($comment[$field]);
		}
		
		// Конвертируем время из формата БД в unixtime
		$comment['ctime'] = strtotime($comment['ctime']);
		
		// Дополнительные данные (user_id автора и status поста, к которому относится коммент)
		// Необходимость возникает в CommentEditAction, для проверки уровня доступа пользователя.
		$comment['post_author_id'] = $postAuthorId;
		$comment['post_status'] = $postStatus;
		
		return $comment;
		
	}
	
	/**
	 * Выборка заданной страницы ленты блога, видимой пользователю 
	 * с заданным уровнем доступа.
	 * Примечание: здусь и во всех остальных методах, возвращающих количество комментов 
	 * к посту в числе других данных, возвращается количество отмодерированных и нескрытых постов.
	 *
	 * @param int $_postsPerPage количество постов на одной странице
	 * @param int $_pageNumber (опциональный) номер страницы. Если параметр не указан,
	 * будет выведена первая страница с самыми свежими постами. Количество постов 
	 * на предыдущих страницах определяет параметр $_postsPerPage.
	 * @param mixed $_catId (опциональный) cat_id категории, посты из которой необходимо 
	 * считать. Если параметр не указан, будут считаны посты из всех категорий
	 * @param mixed $_tags (опциональный) массив тагов, которые будут использованы для выборки постов
	 * @param bool $_publicOnly (опциональный) В случае необходимости выдавать только посты со 
	 * статусом public, параметру необходимо задать значение true. Такая необходимость возникает 
	 * при генерации RSS лент. По-умолчанию параметр имеет значение false и метод выдаёт все посты, 
	 * видимые текущему пользователю.
	 * @return mixed массив с полным содержимым заказанных постов. 
	 * если задана несущесвующая страница, возвращает false
	 */
	function GetPostsPage($_postsPerPage, $_pageNumber = 0, $_catId = false, $_tags = false, $_publicOnly = false) {
		
		// Проверка допустимости значений параметров
		if(!is_numeric($_postsPerPage) || !is_numeric($_pageNumber) || $_postsPerPage < 1 || 
			!($_catId === false || is_numeric($_catId)) || !($_tags === false || is_array($_tags))) {
			
			$this->errorMsg = "Incorrect parameters specified.";
			return false;
		}
		
		// Ограничение максимального количества постов на странице
		$postsPerPage = ($_postsPerPage > MAX_POSTS_PER_PAGE)?
			MAX_POSTS_PER_PAGE:$_postsPerPage;
		
		$pageNumber = ($_pageNumber > 0)?($_pageNumber - 1):0;
		
		// В зависимости от прав доступа текущего пользователя, 
		// выбирается одно из условий видимости постов для выборки
		// - Для супервизора видно всё
		// - Для всех "status = public"
		// - Для userAccessLevel & AR_READ_COMMUNITY - " OR status = community"
		// - Для зарегистрированных пользователей (userId >= 0) - " OR user_id = <user_id>"
		// Если задан параметр $_publicOnly, пользовательские права доступа игнорируются 
		// и считываются только посты со статусом public (используется для экспорта контента в RSS)
		if($_publicOnly) {
			$where = "(status = ".PS_PUBLIC.")";
		} elseif($this->userAccessLevel & AR_SUPERVISING) {
			$where = "";
		} else {
			$where = "(status = ".PS_PUBLIC;
			if($this->userAccessLevel & AR_READING_COMMUNITY) {
				$where .= " OR status = ".PS_COMMUNITY;
			}
			if($this->userId >= 0) {
				$where .= " OR user_id = ".$this->userId;
			}
			$where .= ")";
		}
		
		// Дополнительное условие принадлежности к категории
		if(is_numeric($_catId)) {
			$where = ($where?($where." AND "):"")."(cat_id = ".$_catId.")";
		}
		
		// Дополнительное условие асооциированности постов с набором тагов
		if(is_array($_tags)) {
			// Проверка синтаксиса тагов
			if(!$this->TagSyntaxOk($_tags)) {
				$this->errorMsg = "Incorrect tags syntax.";
				return false;
			}
			
			$sqlRequest = "SELECT ".$this->tblPosts.".post_id, COUNT(*) AS tag_cnt FROM ".
				$this->tblPosts." JOIN ".$this->tblTagsMap.
				" USING (post_id) WHERE tag_id IN (SELECT tag_id FROM ".$this->tblTags.
				" WHERE tag IN ('".implode("', '", $_tags)."')) GROUP BY post_id HAVING tag_cnt = ".count($_tags);
			$result = $this->Execute($sqlRequest, 
				'Считываем post_id всех постов, ассоциированных с заданным набором тагов.');
			if(!$result) return false;
			
			$postIds = array();
			
			foreach($result->GetArray() as $r) $postIds[] = $r[0];
			
			// Отслеживаем случай, когда фильтр заведомо не пропустит ни одного поста
			if(!count($postIds)) {
				return array();
			}
			
			$where = ($where?($where." AND "):"")."post_id IN (".implode(", ", $postIds).")";
			
			// Выборка постов, ассоциированных с каждым тагом, заданным 
			// в параметрах вызова метода
		}
		
		if($where) $where = " WHERE ".$where;
		
		// Сформировать и выполнить SQL запрос
		$sqlRequest = "SELECT * FROM ".$this->tblPosts.
			$where." ORDER BY ctime DESC, post_id DESC LIMIT ".($postsPerPage + 1).
			" OFFSET ".($pageNumber * $postsPerPage);
		$result = $this->Execute($sqlRequest, "Считываем посты");
		if(!$result) return false;
		$result = $result->GetArray();
		
		// Проверка, есть ли ещё посты после запрошенного количества постов
		$this->lastPage = (count($result) <= $postsPerPage);
		
		// Проверить наличие постов, соответствующих запрошенной странице
		if(!count($result)) {
			return array();
		}
		
		if(!$this->lastPage) {
			// Посты есть (страница не последняя). Выбрасываем последний 
			// индикаторный пост из полученной выборки
			array_pop($result);
		}
		
		// Если посты есть, то преобразовать их к удобной форме
		
		$posts = array();
		$catIds = array();
		
		// Удаляем ненужные данные из выборки постов и собираем ID их категорий
		foreach($result as $nextPost) {
			
			// Выбрасываем дубликаты
			foreach($nextPost as $field => $value) {
				if(is_numeric($field)) unset($nextPost[$field]);
			}
			
			// Преобразуем время из формата БД в unixtime
			$nextPost['ctime'] = strtotime($nextPost['ctime']);
			$nextPost['mtime'] = strtotime($nextPost['mtime']);
			
			$nextPost['post_id'] = (int)$nextPost['post_id'];
			$nextPost['cat_id'] = (int)$nextPost['cat_id'];
			$nextPost['user_id'] = (int)$nextPost['user_id'];
			
			$cachedTags = trim($nextPost['cached_tags']);
			
			// Если значение поля cached_tags устарело, обновляем его
			if($cachedTags == ',,') {
				$cachedTags = $this->UpdateCachedTags($nextPost['post_id']);
			} else {
				// Если поле cached_tags не пустое, преобразуем его в массив
				$cachedTags = $cachedTags?explode(",", $cachedTags):array();
			}
			
			$nextPost['cached_tags'] = $cachedTags;
			
			$posts[$nextPost['post_id']] = $nextPost;
			$catIds[] = $nextPost["cat_id"];
		}
		
		// Считываем данные о всех категориях постов
		$sqlRequest = "SELECT cat_id, shortcut, title FROM ".$this->tblCats.
			" WHERE cat_id IN (".implode(",", array_unique($catIds)).")";
		$result = $this->Execute($sqlRequest, 'Считываем данные о всех категориях постов');
		$result = $result->GetArray();
		
		$cats = array();
		
		// Преобразуем массив категорий к удобной фориме
		foreach($result as $nextCat) {
			$cats[$nextCat['cat_id']] = array(
					'shortcut' => $nextCat['shortcut'],
					'title' => $nextCat['title']
				);
		}
		
		foreach($posts as $postId => $post) {
			// Добавляем данные о категориях в массив постов
			$post["cat_shortcut"] = $cats[$post['cat_id']]['shortcut'];
			$post["cat_title"] = $cats[$post['cat_id']]['title'];
			
			// Оставляем только одно значение счётчика комемнтариев, акутальное для текущего пользователя
			// Поле всегда будет назваться cached_com_cnt
			if($this->userAccessLevel & (AR_MODERATION | AR_SUPERVISING)) {
				// Елси пользователь модератор или супервизор, он может видеть полное количество комментариев 
				// к посту, включая скрытые и неотмодерированные
				$post['cached_com_cnt'] = $post['cached_com_cnt_total'];
			}
			
			// Убираем из массива лишнее поле
			unset($post['cached_com_cnt_total']);
			
			// Расшифровываем инфу об авторе поста (десериализуем поле БД cached_author)
			// и добавляем в результат как отдельные элементы массива
			$author = unserialize($post['cached_author']);
			unset($post['cached_author']);
			
			
			$post["user_login"] = $author['login'];
			$post["user_name"] = $author['name'];
			$post["user_email"] = $author['email'];
			$post["user_hp"] = $author['hp'];
			
			$posts[$postId] = $post;
		}
		
		return $posts;
	}
	
	/**
	 * Выборка постов, относящихся к определённой дате
	 *
	 * @param int $_year год
	 * @param int $_month месяц
	 * @param int $_day день
	 * @return mixed массив с полным содержание постов за указанный день. 
	 * если указана неверная дата, возвращает false.
	 */
	function GetPostsForDay($_year, $_month, $_day) {
		
		// Проверить корректность параметров
		if(!is_numeric($_year) || $_year > 9999 || $_year < 0 ||
			!is_numeric($_month) || $_month > 12 || $_month < 1 ||
			!is_numeric($_day) || $_day > 31 || $_day < 1) {
			
			$this->errorMsg = "Incorrect date value specified.";
			return false;
		}
		
		// Сформировать запрос
		$timeBegin = date("Y-m-d", mktime(0, 0, 0, $_month, $_day, $_year));
		$timeEnd = $timeBegin." 23:59:59";
		$timeBegin .= " 00:00:00";
		
		$sqlRequest = "SELECT * FROM ".$this->tblPosts.
			" WHERE ctime >= '".$timeBegin."' AND  ctime <= '".$timeEnd."'";
		$result = $this->Execute($sqlRequest, "Считываем посты за один день");
		
		return $this->ExpandPostsData($result->GetArray());
		
	}
	
	/**
	 * Выборка заголовков постов, за определённый месяц
	 *
	 * @param int $_year год
	 * @param int $_month месяц
	 * @return mixed массив с заголовками постов за указанный 
	 * в параметрах месяц. заголовок поста - ассоциативный массив, 
	 * в котором содержится всё содержание поста кроме его текста.
	 * если указана неправильная дата, возвращает false.
	 */
	function GetHeadersForMonth($_year, $_month) {
		
		// Проверить корректность параметров
		if(!is_numeric($_year) || $_year > 9998 || $_year < 0 ||
			!is_numeric($_month) || $_month > 12 || $_month < 1) {
			
			$this->errorMsg = "Incorrect date value specified.";
			return false;
		}
		
		// Сформировать запрос
		$timeBegin = date("Y-m-d", mktime(0, 0, 0, $_month, 1, $_year))." 00:00:00";
		
		if($_month < 12) {
			$month2 = $_month + 1;
			$year2 = $_year;
		} else {
			$month2 = 1;
			$year2 = $_year + 1;
		}
		
		$timeEnd = date("Y-m-d", mktime(0, 0, 0, $month2, 1, $year2))." 23:59:59";
		
		$sqlRequest = "SELECT post_id, cat_id, user_id, status, ctime, mtime, shortcut, ".
			"title, cached_tags, cached_com_cnt, cached_author FROM ".$this->tblPosts.
			" WHERE ctime >= '".$timeBegin."' AND  ctime <= '".$timeEnd."'";
		// Преобразовать результат к дуобной форме и вернуть
		$result = $this->Execute($sqlRequest, "Считываем заголовки постов за месяц");
		
		return $this->ExpandPostsData($result->GetArray());
		
	}
	
	/**
	 * Выборка заголовков постов, за определённый год
	 *
	 * @param int $_year год
	 * @return mixed массив с заголовками постов за указанный 
	 * год. если указана неправильная дата, возвращает false.
	 */
	function GetHeadersForYear($_year) {
		
		// Проверить корректность параметров
		if(!is_numeric($_year) || $_year > 9998 || $_year < 0) {
			$this->errorMsg = "Incorrect date value specified.";
			return false;
		}
		
		// Сформировать запрос
		$timeBegin = $_year."-01-01 00:00:00";
		$timeEnd = $_year."-12-31 23:59:59";
		
		$sqlRequest = "SELECT post_id, cat_id, user_id, status, ctime, mtime, shortcut, ".
			"title, cached_tags, cached_com_cnt, cached_author FROM ".$this->tblPosts.
			" WHERE ctime >= '".$timeBegin."' AND  ctime <= '".$timeEnd."'";
		// Преобразовать результат к дуобной форме и вернуть
		$result = $this->Execute($sqlRequest, "Считываем заголовки постов за год");
		
		return $this->ExpandPostsData($result->GetArray());
		
	}
	
	/**
	 * Выборка полного списка тагов со счётчиками
	 *
	 * @param string $_orderBy (опциональный параметр) Критерий сортировки тагов. 
	 * Возможные значения: tag (по-умолчанию, таги сортируются в алфавитном порядке),
	 * count (выбор между cached_cnt, cached_cnt_reg и cached_cnt_total осуществляется по контексту, 
	 * в зависимости от уровня доступа текущего пользователя)
	 * @param int $_limit (опциональный параметр) Максимальное количество тагов в выборке 
	 * (остчитываются от начала). Параметр используется методом GetPopularTags().
	 * @return array массив тагов со значениями 
	 * счётчиков постов. array(array(<tagN>, <tagN_count>), ...)
	 */
	function GetTags($_orderBy = 'tag', $_limit = 0) {
		// проверяем корректность параметров
		if(!in_array($_orderBy, array("tag", "count")) || !is_numeric($_limit)) {
			$this->errorMsg = "Incorrect parameters";
			return false;
		}
		
		// Сформировать запрос
		$sqlRequest = "SELECT * FROM ".$this->tblTags; //." ORDER BY ".$orderBy.$limit;
		$result = $this->Execute($sqlRequest, 'Считываем таги');
		$result = $result->GetArray();
		
		if(!count($result)) return array();
		
		$tags = array();
		
		// Преобразовать результат к удобной форме
		foreach($result as $nextTag) {
			// Определение актуального для текущего пользователя 
			// значения счётчика постов
			if($this->userAccessLevel & AR_SUPERVISING) {
				// Для супервизоров счётчик отображает значение общего 
				// количества постов, включая черновики и скрытые посты
				$tagCount = $nextTag["cached_cnt_total"];
			} elseif($this->userId == -1) {
				// Для бесправных незарегистрированных пользователей
				// счётчик отобрадает количество постов со статусом PUBLIC
				$tagCount = $nextTag["cached_cnt"];
			} else {
				// Для зарегистрированных пользователей
				$tagCount = $nextTag["cached_cnt_reg"];
			}
			
			if(!$tagCount) continue;
			
			$tags[] = array(
					"tag_id" => $nextTag["tag_id"], 
					"count" => $tagCount, 
					"tag" => $nextTag['tag']
				);
		}
		
		// Сортируем массив тагов по заданному критерию: вначале выполняется 
		// сортировка по имени, а после этого - сортировка по счётчику (если необходима)
		if($_orderBy == 'count') {
			$tags = sort2d($tags, 'count', 'int', 'tag', 'string');
		} else {
			$tags = sort2d($tags, 'tag');
		}
		
		// NIHAMG: $tags - полный отсортированный массив тагов
		
		// Если задано ограничение массива, оставляем только первые $_limit элементов
		if($_limit) $tags = array_slice($tags, 0, $_limit);
		
		return $tags;
		
	}
	
	/**
	 * Метод возвращает родственные таги для заданного массива тагов.
	 * - Методу задаётся массив тагов. 
	 * - На основе этого метода определяется выборка ассоциированных постов. 
	 * - Посты в этой выборке могут быть ассоциированы ещё и с другими тагами, 
	 *   помимо заданных.
	 * - Дополнительное множество таких тагов называется родственными 
	 *   тагами для тех тагов, которые были заданы в параметре метода. 
	 *
	 * @param array $_tags Массив тагов, для которых нужно найти родственников
	 * @return mixed Массив родственных тагов или false в случае ошибки при работе
	 */
	function GetRelativeTags($_tags) {
		
		// Проверяем корректность синтаксиса тагов
		if(!$this->TagSyntaxOk($_tags)) {
			$this->errorMsg = "Incorrect tags specified.";
			return false;
		}
		
		// Считываем все post_id, ассоциированне с данным набором тагов
		$sqlRequest = "SELECT post_id,COUNT(*) AS tag_cnt FROM ".$this->tblTags." INNER JOIN ".$this->tblTagsMap.
			" USING (tag_id) WHERE tag IN ('".implode("','", $_tags)."') GROUP BY post_id HAVING tag_cnt >= ".count($_tags);
		$result = $this->Execute($sqlRequest, 'Считываем все post_id, ассоциированне с данным набором тагов');
		if(!$result) return false;
		
		if(!$result->RecordCount()) return array();
		
		$postIds = array();
		foreach($result->GetArray() as $post) $postIds[] = $post[0];
		
		// Если пользователь не супервизор, необходимо выполнить дополнительную проверку видимости постов
		if(!($this->userAccessLevel & AR_SUPERVISING)) {
			// Выполняем проверку видимости постов текущему пользователю 
			// (отфильтровываем невидимы посты из $postIds)
			
			// Условие видимости поста текущему пользователю
			$where = "status = ".PS_PUBLIC;
			if($this->userAccessLevel & AR_READING_COMMUNITY) {
				$where .= " OR status = ".PS_COMMUNITY;
			}
			
			if($this->userId >= 0) {
				$where .= " OR user_id = ".$this->userId;
			}
			
			if($where) $where = " AND (".$where.")";
			
			// Считываем все post_id, ассоциированне с данным набором тагов
			$sqlRequest = "SELECT post_id FROM ".$this->tblPosts.
				" WHERE post_id IN (".implode(', ', $postIds).")".$where;
			$result = $this->Execute($sqlRequest, 'Считываем все post_id, ассоциированне с данным набором тагов');
			if(!$result) return false;
			
			if(!$result->RecordCount()) return array();
			
			$postIds = array();
			foreach($result->GetArray() as $post) $postIds[] = $post[0];
			
		}
		
		// Считываем родственные таги
		$sqlRequest = "SELECT tag,COUNT(*) FROM ".$this->tblTags." INNER JOIN ".$this->tblTagsMap.
			" USING (tag_id) WHERE post_id IN (".implode(", ", $postIds).") GROUP BY tag";
		$result = $this->Execute($sqlRequest, 'Считываем родственные таги');
		if(!$result) return false;
		
		if(!$result->RecordCount()) return array();
		
		$relTags = array();
		foreach($result->GetArray() as $tag) $relTags[] = $tag[0];
		
		// Выбрасываем из массива родственных тагов те таги, для которых выполнялся 
		// поиск. Функция array_diff возвращает элементы первого массив, которых нет 
		// среди элементов второго.
		return array_diff($relTags, $_tags);
		
	}
	
	/**
	 * Выборка полного списка категорий со счётчиками постов
	 *
	 * @param string $_orderBy (опциональный параметр) Критерий сортировки категорий. 
	 * Возможные значения: shortcut, title, count (выбор между cached_cnt, 
	 * cached_cnt_reg и cached_cnt_total осуществляется по контексту, в зависимости
	 * от уровня доступа текущего пользователя) По-умолчанию, категории сортируются
	 * по title, в алфавитном порядке
	 * @param int $_limit Ограничение кличества категорий. Если задано значение 
	 * больше 0, метод отдаст первые N категорий из общего списка, отсортированного 
	 * по заданному критерию. Параметр идля выделения самых популярных категорий.
	 * @param bool $_readCnt Определяет необходимость выдавать количество постов 
	 * в категориях. При работе с зарегистрированными пользователями, у которых 
	 * нет прав супервизора, метод при каждом обращении заново пересчитывает количество 
	 * видимых текущему пользователю постов для каждой категории. Если задать этому 
	 * параметру значение false, можно сократить количество выполняемых методом SQL 
	 * запросов и повысить его быстродействие.
	 * @return array массив категорий со значениями 
	 * счётчиков постов. array(<cat_id> => array(cat_id => ..., 
	 * shortcut => ..., title => ..., description => ..., count => ...), ...)
	 */
	function GetCats($_orderBy = "shortcut", $_limit = 0, $_readCnt = true) {
		// проверяем корректность параметров
		if(!in_array($_orderBy, array("shortcut", "title", "count")) 
			|| !is_numeric($_limit) || !is_bool($_readCnt)) {
			
			$this->errorMsg = "Incorrect parameters";
			return false;
		}
		
		$sqlRequest = "SELECT * FROM ".$this->tblCats;
		$result = $this->Execute($sqlRequest, 'Считываем категории');
		if(!$result) return false;
		
		$result = $result->GetArray();
		
		if(!count($result)) return array();
		
		$cats = array();
		
		// Преобразовать результат к удобной форме
		foreach($result as $nextCat) {
			$nextCatId = (int)$nextCat['cat_id'];
			
			if($_readCnt) {
				// Определение актуального для текущего пользователя 
				// значения счётчика постов
				if($this->userAccessLevel & AR_SUPERVISING) {
					// Для супервизоров счётчик отображает значение общего 
					// количества постов, включая черновики и скрытые посты
					$catCount = $nextCat["cached_cnt_total"];
				} elseif($this->userId == -1) {
					// Для бесправных незарегистрированных пользователей
					// счётчик отобрадает количество постов со статусом PUBLIC
					$catCount = $nextCat["cached_cnt"];
				} else {
					// Для зарегистрированных пользователей
					$catCount = $nextCat["cached_cnt_reg"];
				}
			} else {
				$catCount = false;
			}
			
			$cats[] = array(
					"cat_id" => $nextCatId,
					"shortcut" => $nextCat["shortcut"],
					"title" => $nextCat["title"],
					"description" => $nextCat["description"],
					"cached_description" => $nextCat["cached_description"],
					"description_nohtml" => strip_tags($nextCat["cached_description"]),
					"count" => $catCount
				);
			
		}
		
		// Сортируем категории по заданному критерию
		if($_orderBy == 'title' || $_orderBy == 'shortcut') {
			$cats = sort2d($cats, $_orderBy, 'string', 'count', 'int');
		} else {
			// Сортировка по счётчику с предварительной сортировкой по title
			$cats = sort2d($cats, 'count', 'int', 'title', 'string');
		}
		
		// Категория uncategorized (cat_id == 0) в любом случае должна быть в конце списка
		$cat = false;
		foreach($cats as $num => $cat) {
			if($cat['cat_id'] == 0) {
				unset($cats[$num]);
				break;
			}
		}
		
		array_push($cats, $cat);
		
		// Обрезаем первые $_limit элементов, если нужно
		if($_limit) $cats = array_slice($cats, 0, $_limit);
		
		// Вернуть результат
		return $cats;
		
	}
	
	/**
	 * Выборка из N наиболее популярных тагов со счётчиками постов
	 *
	 * @param int $_count (опциональный параметр) Критерий сортировки тагов. 
	 * Возможные значения: tag (таги сортируются в алфавитном порядке),
	 * count (по-умолчанию, таги сортируются по значению соответствующего текущему 
	 * пользователю счётчика)
	 * @param int $_limit (опциональный параметр) Максимальное количество тагов в выборке 
	 * (остчитываются от начала). Параметр используется методом GetPopularTags().
	 * @return array массив из указанного числа тагов со значениями 
	 * счётчиков постов. array(array(<tagN>, <tagN_count>), ...)
	 */
	function GetPopularTags($_count) {
		// Обёртка метода GetTags, для более удобного его применения 
		// в частном случае
		return $this->GetTags("count", $_count);
	}
	
	/**
	 * Выборка из N наиболее популярных категорий со счётчиками постов
	 *
	 * @param int $_count
	 * @return array массив из указанного числа категорий со значениями 
	 * счётчиков постов. array(array(<catN>, <catN_count>), ...)
	 */
	function GetPopularCats($_count) {
		return $this->GetCats("count", $_count);
	}
	
	/**
	 * Добавляем в массив постов дополнительную информацию 
	 * об авторе и категории каждого из них
	 *
	 * @access private
	 * @param array $_posts Результат выполнения метода ADORecordSet::GetArray
	 * @return array Массив с расширенными данными постов, каждый элемент которого 
	 * включает информацию о категории поста и авторе, загруженную из соответствующих таблиц
	 */
	function ExpandPostsData($_posts) {
		// Если постов нет, вернуть пустой массив
		if(!count($_posts)) {
			return array();
		}
		
		$postHdrs = array();
		$catIds = array();
		//$userIds = array();
		
		foreach($_posts as $nextHdr) {
			// Выбрасываем дубликаты с числовыми индексами
			foreach($nextHdr as $field => $value) {
				if(is_numeric($field)) unset($nextHdr[$field]);
			}
			
			$postHdrs[$nextHdr["post_id"]] = $nextHdr;
			
			// Отдельно сохраняем массив cat_id считанных постов, 
			// для последующей выборки инфы по категроиям для каждого из них
			$catIds[] = $nextHdr["cat_id"];
			//$userIds[] = $nextHdr["user_id"];
		}
		
		$catIds = array_unique($catIds);
		//$userIds = array_unique($userIds);
		
		// Считываем инфу о категориях, к которым относятся посты
		$sqlRequest = "SELECT cat_id, shortcut, title FROM ".$this->tblCats.
			" WHERE cat_id IN (".implode(",", $catIds).")";
		$result = $this->Execute($sqlRequest, 'Считываем инфу о категориях, к которым относятся посты');
		$result = $result->GetArray();
		
		$cats = array();
		
		// Преобразуем считанную из базы информацию о категориях к удобной форме
		foreach($result as $nextCat) {
			$cats[$nextCat["cat_id"]] = array(
					"cat_shortcut" => $nextCat["shortcut"],
					"cat_title" => $nextCat["title"]
				);
		}
		
		// Преобразовать результат к удобной форме (массив с заголовками постов)
		foreach($postHdrs as $postId => $post) {
			// Добавляем в массив дополнительные данные о категории
			//$post = array_merge($post, $users[$post['user_id']]);
			$post = array_merge($post, $cats[$post['cat_id']]);
			
			// Преобразуем время к формату unixtime
			$post["ctime"] = strtotime($post["ctime"]);
			$post["mtime"] = strtotime($post["mtime"]);
			
			// Преобразуем поле с кэшированными тагами в массив
			
			// Если поле cached_tags для поста имеет значение ",,", значит это значение
			// необходимо сгенерировать заново (при последней операцией с тагами оно было 
			// изменено). Для этого запись в базе данных, соответствующая текущему посту 
			// будет обработана методом UpdateCachedTags, который обновит поле cached_tags 
			// и вернёт массив связанных с постом тагов
			$cachedTags = trim($post['cached_tags']);
			if($cachedTags == ',,') {
				$cachedTags = $this->UpdateCachedTags($postId);
			} else {
				$cachedTags = $cachedTags?explode(',', $cachedTags):array();
			}
			
			// Расшифровываем инфу об авторе поста (десериализуем поле БД cached_author)
			// и добавляем в результат как отдельные элементы массива
			if(isset($post['cached_author'])) {
				$author = unserialize($post['cached_author']);
				unset($post['cached_author']);
				
				$post["user_login"] = $author['login'];
				$post["user_name"] = $author['name'];
				$post["user_email"] = $author['email'];
				$post["user_hp"] = $author['hp'];
			}
			
			$postHdrs[$postId] = $post;
		}
		
		return $postHdrs;
		
	}
	
	/**
	 * Выборка списка постов, относящихся к определённому тагу
	 *
	 * @param string $_tag таг, для которого требуется произвести 
	 * выборку ассоциированных постов
	 * @return mixed массив заголовков ассоциированных постов. 
	 * если таг не существует, возвращает false
	 */
	function GetHeadersByTag($_tag) {
		
		// Проверить корректность параметра
		if(!TagSyntaxOk($_tag)) {
			$this->errorMsg = "Incorrect tag specified.";
			return false;
		}
		
		// Найти tag_id
		$sqlRequest = "SELECT tag_id FROM ".$this->tblTags." WHERE tag = '".$_tag."'";
		$result = $this->Execute($sqlRequest, 'Считываем таг');
		$result = $result->GetArray();
		
		// Если тага нет, вернуть false
		if(!count($result)) {
			return false;
		}
		
		$tagId = $result[0][0];
		
		// Выборка заголовков всех связанных с тагом постов.
		$sqlRequest = "SELECT post_id, cat_id, user_id, status, ctime, mtime, shortcut, ".
			"title, cached_tags, cached_com_cnt, cached_author FROM ".$this->tblPosts.
			" WHERE post_id IN (SELECT post_id FROM ".$this->tblTagsMap.
			" WHERE tag_id = ".$tagId.")";
		$result = $this->Execute($sqlRequest, 'Выборка заголовков всех связанных с тагом постов.');
		
		return $this->ExpandPostsData($result->GetArray());
		
	}
	
	/**
	 * Выборка списка постов, относящихся к определённой категории
	 *
	 * @param int $_catId ID категории, для которого требуется произвести 
	 * выборку ассоциированных постов
	 * @return mixed массив заголовков ассоциированных постов. если задано 
	 * несуществующее значение ID, возвращает false
	 */
	function GetHeadersByCat($_catId) {
		
		// Проверить корректность параметра
		if(!is_numeric($_catId)) {
			$this->errorMsg = "Incorrect category ID specified.";
			return false;
		}
		
		// Выборка заголовков всех постов из категории
		$sqlRequest = "SELECT post_id, cat_id, user_id, status, ctime, mtime, shortcut, ".
			"title, cached_tags, cached_com_cnt, cached_author FROM ".$this->tblPosts.
			" WHERE cat_id = ".$_catId;
		$result = $this->Execute($sqlRequest, 'Выборка заголовков всех постов из категории');
		
		return $this->ExpandPostsData($result->GetArray());
		
	}
	
	/**
	 * Выборка списка постов, относящихся к определённой категории, 
	 * поизводимая по текстовому имени категории
	 *
	 * @param string $_catName имя категории, для которого требуется произвести 
	 * выборку ассоциированных постов
	 * @return mixed массив заголовков ассоциированных постов. если задано 
	 * несуществующее имя, возвращает false
	 */
	function GetHeadersByCatShortcut($_catShortcut) {
		
		// Проверить корректность параметра
		if(!mb_eregi(REGEXP_SHORTCUT, $_catShortcut)) {
			$this->errorMsg = "Bad shortcut specified (inappropriate characters used or maximum length limit exceeded).";
			return false;
		}
		
		// Выборка заголовков всех постов из категории
		$sqlRequest = "SELECT post_id, cat_id, user_id, status, ctime, mtime, shortcut, ".
			"title, cached_tags, cached_com_cnt, cached_author FROM ".$this->tblPosts.
			" WHERE shortcut = '".$_catName."'";
		$result = $this->Execute($sqlRequest, 'Выборка заголовков всех постов из категории');
		
		return $this->ExpandPostsData($result->GetArray());
		
	}
	
	/**
	 * Удаляет пост. Автоматически обновляет значения счётчиков категории 
	 * поста и тагов. При необходимости таги с обнулёнными счётчиками 
	 * автоматически удаляются. Пустые категории автоматически не удаляются.
	 *
	 * @param int $_postId ID удаляемого поста
	 * @return bool true при успешном выполнении удаления. false - в случае 
	 * если задано несуществующее значение ID
	 */
	function DeletePost($_postId) {
		
		// Проверка корректности параметра
		if(!is_numeric($_postId)) {
			$this->errorMsg = 'Incorrect post_id value specified.';
			return false;
		}
		
		// Проверка первичного условия возможности удаления поста
		if(!($this->userAccessLevel & AR_POSTING || 
			$this->userAccessLevel & AR_MODERATION || 
			$this->userAccessLevel & AR_SUPERVISING)) {
			
			$this->errorMsg = "Current user have no access level to delete posts.";
			return false;
		}
		
		// Считываем пост
		$sqlRequest = "SELECT status,user_id,cat_id FROM ".$this->tblPosts." WHERE post_id = ".$_postId;
		$result = $this->Execute($sqlRequest, "Считываем пост");
		if(!$result) return false;
		
		// Проверяем наличие поста
		if(!$result->RecordCount()) {
			$this->errorMsg = "Required post doesn`t exists.";
			return false;
		}
		
		$postStatus = $result->Fields(0);
		$postAuthorId = $result->Fields(1);
		$postCatId = $result->Fields(2);
		
		// Условия возможности удаления поста:
		if(!$this->CanDeletePost($postAuthorId, $postStatus)) {
			$this->errorMsg = "User has no access rights to delete this post.";
			return false;
		}
		
		// Удаляем пост из таблицы постов
		$sqlRequest = "DELETE FROM ".$this->tblPosts." WHERE post_id = ".$_postId;
		$result = $this->Execute($sqlRequest, "Удаляем пост из таблицы постов");
		if(!$result) return false;
		
		// Удаляем комменты к посту
		$sqlRequest = "DELETE FROM ".$this->tblComments." WHERE post_id = ".$_postId;
		$result = $this->Execute($sqlRequest, "Удаляем комменты к посту");
		if(!$result) return false;
		
		// Считываем ID всех тагов, ассоциированных с постом
		$sqlRequest = "SELECT tag_id FROM ".$this->tblTagsMap." WHERE post_id = ".$_postId;
		$result = $this->Execute($sqlRequest,
			"Считываем ID всех тагов, ассоциированных с постом");
		if(!$result) return false;
		
		if($result->RecordCount()) {
			// Если пост ассоциирован с тагами
			
			$tags = $result->GetArray();
			
			// Удаляем из таблицы tags_map все упоминания ID удалённого поста
			$sqlRequest = "DELETE FROM ".$this->tblTagsMap." WHERE post_id = ".$_postId;
			$result = $this->Execute($sqlRequest, 
				"Удаляем из таблицы tags_map все упоминания ID удалённого поста");
			if(!$result) return false;
			
			// Обновляем значения счётчиков каждого из ассоциированных тагов
			foreach($tags as $nextTag) {
				$nextTagId = $nextTag['tag_id'];
				
				$sqlRequest = "UPDATE ".$this->tblTags.
				" SET cached_cnt = (SELECT COUNT(*) FROM ".$this->tblPosts.
				" WHERE status = ".PS_PUBLIC." AND post_id IN (SELECT post_id FROM ".
				$this->tblTagsMap." WHERE tag_id = ".$nextTagId.
				")), cached_cnt_reg = (SELECT COUNT(*) FROM ".$this->tblPosts.
				" WHERE (status = ".PS_PUBLIC." OR status = ".PS_COMMUNITY.
				") AND post_id IN (SELECT post_id FROM ".$this->tblTagsMap.
				" WHERE tag_id = ".$nextTagId.
				")), cached_cnt_total = (SELECT COUNT(*) FROM ".
				$this->tblTagsMap." WHERE tag_id = ".$nextTagId.
				") WHERE tag_id = ".$nextTagId;
				
				$result = $this->Execute($sqlRequest, 
					"Пересчитываем значения счётчика для тага с ID = ".$nextTagId);
				
				if(!$result) return false;
			}
			
			// Удаляем таги с обнулёнными счётчиками
			$sqlRequest = "DELETE FROM ".$this->tblTags." WHERE cached_cnt_total = 0";
			$result = $this->Execute($sqlRequest, 
				"Удаляем таги с обнулёнными счётчиками");
			if(!$result) return false;
		}
		
		// Обновляем счётчики постов в категории $postCatId
		$result = $this->UpdateCatCount($postCatId);
		if(!$result) return false;
		
		// Обновляем счётчики постов в таблице пользователей
		$result = $this->UpdateUserPostCounters($this->userId);
		if(!$result) return false;
		
		// Обновляем счётчики комментариев для пользователя, чей пост удалён
		$result = $this->UpdateUserComCounters($postAuthorId);
		if(!$result) return false;
		
		// Счётчики комментариев для тех пользователей, которые оставляли 
		// комментарии, будут обновлены позже
		
		return true;
		
	}
	
	/**
	 * Проверяет возможность удаления поста с задаными параметрами текущим поьзователем.
	 *
	 * @param int $_postAuthorId
	 * @param int $_postStatus
	 */
	function CanDeletePost($_postAuthorId, $_postStatus) {
		// Условия возможности удаления поста:
		
		return 
			// - Юзер - зарегистрированный юзер с правом постинга и автор поста
			(($this->userId >= 0) && ($this->userAccessLevel & AR_POSTING) && ($this->userId == $_postAuthorId)) ||
			// - Юзер - модератор, пост имеет статус public или community
			($this->userAccessLevel & AR_MODERATION && ($_postStatus == PS_PUBLIC || $_postStatus == PS_COMMUNITY)) ||
			// - Юзер - супервизор
			($this->userAccessLevel & AR_SUPERVISING);
	}
	
	/**
	 * Удаление комментария с заданным comment_id (обновление 
	 * счётчика комментариев поста)
	 *
	 * @param int $_commentId ID комментария
	 * @return bool true при успешном выполнении метода, или false 
	 * в потивном случае
	 */
	function DeleteComment($_commentId) {
		// Проверка первичного условия возможности удаления комментария
		if(!($this->userAccessLevel & AR_POSTING || 
			$this->userAccessLevel & AR_MODERATION || 
			$this->userAccessLevel & AR_SUPERVISING)) {
			
			$this->errorMsg = "Current user have no access level to delete a comment.";
			return false;
		}
		
		// Проверка корректности параметра
		if(!is_numeric($_commentId)) {
			$this->errorMsg = "Incorrect comment ID specified.";
			return false;
		}
		
		// Считывае6м данные о комменте и посте, к которому он относится 
		// для проверки наличия у текущего пользователя прав на удаление этого коммента
		
		// Считываем ID поста, к которому относится коммент, 
		// и user_id автора коммента
		$sqlRequest = "SELECT post_id,user_id FROM ".$this->tblComments.
			" WHERE comment_id = ".$_commentId;
		$result = $this->Execute($sqlRequest, 
			"Считываем ID поста, к которому относится коммент, и user_id автора коммента");
		if(!$result) return false;
		
		if(!$result->RecordCount()) {
			// Коммент с заданным ID не существует
			$this->errorMsg = "Required comment doesn`t exists.";
			return false;
		}
		
		$postId = $result->Fields(0);
		$comAuthorId = $result->Fields(1);
		
		// Считываем статус поста и ID его автора
		$sqlRequest = "SELECT user_id,status FROM ".$this->tblPosts.
			" WHERE post_id = ".$postId;
		$result = $this->Execute($sqlRequest, "Считываем статус поста и ID его автора");
		if(!$result) return false;
		
		if(!$result->RecordCount()) {
			// Странный случай, когда коммент есть, а поста, которому он коммент, 
			// нет. При нормальной работе этой ситуации быть не недолжно вообще.
			
			// Просто удаляем
			
			$this->errorMsg = "Required post doesn`t exists.";
			return false;
		}
		
		$postAuthorId = $result->Fields(0);
		$postStatus = $result->Fields(1);
		
		// NIHAMG: $postId, $comAuthorId, $postAuthorId, $postStatus
		
		// Проверяем право пользователя на редактирование коммента
		if(!$this->CanDeleteComment($comAuthorId, $postAuthorId, $postStatus)) {
			$this->errorMsg = "User has no access rights to delete this comment.";
			return false;
		}
		
		// Удаляем коммент
		$sqlRequest = "DELETE FROM ".$this->tblComments.
			" WHERE comment_id = ".$_commentId;
		$result = $this->Execute($sqlRequest, "Удаляем коммент");
		if(!$result) return false;
		
		// Изменяем значение parent_id на -1 у весх комментов, которые 
		// были ответами на этот коммент
		$sqlRequest = "UPDATE ".$this->tblComments." SET parent_id = -1 ".
			" WHERE parent_id = ".$_commentId;
		$result = $this->Execute($sqlRequest, 
			"Изменяем значение parent_id на -1 у весх комментов, которые ".
			"были ответами на этот коммент");
		if(!$result) return false;
		
		// Обновялем значение счётчиков комментов для поста
		$this->UpdateCommentsCount($postId);
		if(!$result) return false;
		
		// Обновляем счётчики комментариев в таблице пользователей
		// для автора комментария и автора комментируемого поста
		$result = $this->UpdateUserComCounters($this->userId);
		if(!$result) return false;
		
		$result = $this->UpdateUserComCounters($postAuthorId);
		if(!$result) return false;
		
		return true;
		
	}
	
	/**
	 * Метод проверяет возможность текущего пользователя удалить комментарий
	 * с заданными параметрами.
	 * Метод не взаимодействует с БД, данные о текущем пользовтеле берутся 
	 * из внутренних переменных класса, а необходимые сведения о проверяемом 
	 * посте - из параметров.
	 * Метод используется внутри метода DeleteComment, перед выполнением удаления 
	 * поста, а тек же в DeleteCommentAction и других action-ах, где требуется 
	 * проверка возможности править тот или иной пост.
	 * @param int $_comAuthorId user_id автора комментария (если комментарий 
	 * оставлен анонимным пользователем, то user_id == -1)
	 * @param int $_postAuthorId user_id автора поста
	 * @param int $_postStatus Статус поста
	 */
	function CanDeleteComment($_comAuthorId, $_postAuthorId, $_postStatus) {
		return 
			// Пользователь - супервизор
			($this->userAccessLevel & AR_SUPERVISING) ||
			// Пользователь зарегистрирован и является автором поста или коммента
			($this->userId >= 0 && ($this->userId == $_postAuthorId || 
				$this->userId == $_comAuthorId)) ||
			// Пост имеет статус public или community, а пользователь - модератор
			(($this->userAccessLevel & AR_MODERATION) && ($_postStatus == PS_PUBLIC || 
				$_postStatus == PS_COMMUNITY));
	}
	
	/**
	 * Изменение поста с заданным post_id (автоматическое обновления 
	 * счётчиков категорий и тагов, удаление "пустых" категорий 
	 * и тагов, создание новых категорий и тагов)
	 *
	 * @param int $_postId ID редактируемого поста
	 * @param array $_newValues массив, содержащий новые значения полей поста. 
	 * массив может содержать произвольное количество элементов, имеющих 
	 * индексы, соответствующие предопределённым значениям. неопределённые 
	 * в массиве поля останутся неизменными.
	 *  - user_id   - новое значение ID автора поста
	 *  - title	  - новое значение заголовка поста
	 *  - content   - новое значение текста поста
	 *  - status	- новое значение статуса поста
	 *  - shortcut  - новое значение короткого адреса поста
	 *  - ctime	 - новое значение времени создания поста
	 *  - mtime	 - новое значение времени модификации поста
	 *  - cat_shortcut  - новое значение имени категории (будет преобразовано в ID)
	 *  - cat_id	- новое значение ID категории (если это значение определено 
	 *			  одновременно с cat_name, приобритет будет за cat_id)
	 *  - tags	  - массив с новым набором тагов для поста 
	 *			  (использоваться должны текстовые значения)
	 *  - filters - новое значение для поля filters
	 * @return mixed (int) post_id отредактированного поста при успешном выполнении метода, 
	 * или (bool) false в потивном случае
	 */
	function ChangePost(
		$_postId,
		$_newValues) {
		
		// Считываем пост
		$post = $this->GetPost($_postId);
		
		// Проверка существования поста
		if(!$post) {
			$this->errorMsg = "Specified post doesn`t exist.";
			return false;
		}
		
		// Проверка прав пользователя на правку поста.
		if(!$this->CanChangePost($post['user_id'], $post['status'])) {
			$this->errorMsg = "Current user has no access rights to edit this post.";
			return false;
		}
		
		
		// Проверяем корректность параметров
		
		if(!is_numeric($_postId)) {
			$this->errorMsg = "Bad post ID specified.";
			return false;
		}
		
		// Добавляем определённые параметры в массив и поверяем корректность значений
		// (копирование необходимо для исключения junk fields, которые могут присутствовать 
		// в массиве)
		$sqlValues = array();
		
		if(!count($_newValues)) {
			$this->errorMsg = "Parameters not defined.";
			return false;
		}
		
		// title - заголовок поста
		if(isset($_newValues["title"])) {
			if(mb_strlen($_newValues["title"], "utf-8") > MAX_POST_TITLE_LENGTH) {
				$this->errorMsg = 'Post title length limit exceeded.';
				return false;
			} else {
				$sqlValues[] = 'title = "'.addslashes($_newValues["title"]).'"';
			}
		}
		
		// filters - флаги используемых фильтров
		if(isset($_newValues["filters"])) {
			if(!is_numeric($_newValues["filters"])) {
				$this->errorMsg = "Incorrect filters value specified.";
				return false;
			} else {
				$sqlValues[] = 'filters = '.$_newValues["filters"];
				// Сохраняем новое значение флаговой переменной, определяющей, какими фильтрами 
				// будет обработан текст поста
				$actualFilters = $_newValues["filters"];
			}
		} else {
			// Если новое значение поля фильтров не задано, будем использовать 
			// старое значение
			$actualFilters = $post['filters'];
		}
		
		// content - текст поста
		if(isset($_newValues["content"])) {
			if(mb_strlen($_newValues["content"], "utf-8") > MAX_POST_LENGTH) {
				$this->errorMsg = 'Post content length limit exceeded.';
				return false;
			} else {
				$sqlValues[] = 'content = "'.addslashes($_newValues["content"]).'"';
				// Фильтруем контент, добавляем новое поле
				$sqlValues[] = 'cached_content = "'.
					addslashes($this->FilterText($_newValues["content"], $actualFilters)).'"';
			}
		}
		
		// status - статус поста
		if(isset($_newValues["status"])) {
			if(!is_numeric($_newValues["status"])) {
				$this->errorMsg = "Wrong post status specified.";
				return false;
			} else {
				$sqlValues[] = 'status = '.$_newValues["status"];
			}
		}
		
		// shortcut поста
		if(isset($_newValues["shortcut"])) {
			if(!mb_eregi(REGEXP_SHORTCUT, $_newValues["shortcut"])) {
				$this->errorMsg = 'Bad shortcut specified (inappropriate characters used or maximum length limit exceeded).';
				return false;
			} else {
				$sqlValues[] = 'shortcut = "'.$_newValues["shortcut"].'"';
			}
		}
		
		// ctime - дата создания поста (её можно изменить)
		if(isset($_newValues["ctime"])) {
			if(!is_numeric($_newValues["ctime"])) {
				$this->errorMsg = "Wrong post creation time specified.";
				return false;
			} else {
				$sqlValues[] = 'ctime = '.date("YmdHis", $_newValues["ctime"]);
			}
		}
		
		// mtime - дата создания поста (её можно изменить)
		if(isset($_newValues["mtime"])) {
			if(!is_numeric($_newValues["mtime"])) {
				$this->errorMsg = "Wrong post modification time specified.";
				return false;
			} else {
				$sqlValues[] = 'mtime = '.date("YmdHis", $_newValues["mtime"]);
			}
		}
		
		// Переменная, которая будет хранить новое значение cat_id поста
		$postCatId = false;
		
		// cat_shortcut - shortcut категории
		// Если он задан, необходимо определить cat_id. При этом, cat_id в таком случае не обрабатывается.
		// Если категория а заданным shortcut не существует, она буде создана
		if(isset($_newValues["cat_shortcut"])) {
			// Проверка корректности шортката
			if(!mb_eregi(REGEXP_SHORTCUT, $_newValues["cat_shortcut"])) {
				$this->errorMsg = 'Bad category shortcut specified (length limit'.
					' exceeded or inappropriate characters used).';
				return false;
			}
			
			// Определяем cat_id по значению $_newValues["cat_shortcut"]
			$sqlRequest = "SELECT cat_id FROM ".$this->tblCats.
				" WHERE shortcut = '".$_newValues["cat_shortcut"]."'";
			$result = $this->Execute($sqlRequest, 'Определяем cat_id по значению shortcut');
			if(!$result) return false;
			
			// Проверяем, существует ли заданная категория
			if(!$result->RecordCount()) {
				
				// Категория не существует. Необходимо создать её, если у пользователя 
				// есть на это права, или выдать сообщение об вошибке в противном случае
				if($this->CanManageCats()) {
					$postCatId = $this->AddCat($_newValues["cat_shortcut"],
						$_newValues["cat_shortcut"], "");
				} else {
					$this->errorMsg = "Required category doesn't exists. User have no ".
						"access rights to create new categories.";
					return false;
				}
				
			} else {
				// Сохраняем ID найденной категории
				$postCatId = $result->Fields(0);
			}
			
			// Добавляем в список изменяемых полей ID найденной или свежеиспечённой категории
			$sqlValues[] = 'cat_id = '.$postCatId;
			
		} elseif(isset($_newValues["cat_id"])) {
			// Проверяем наличте cat_id в списке параметров только в том случае,
			// если не задан shortcut категории
			
			$postCatId = $_newValues["cat_id"];
			
			// Перенос поста в новую категорию с указанием cat_id отличается от переноса 
			// в категорию с указанием её имени (шортката) тем, что в последнем случае новая 
			// категория может быть автоматически создана (если она не существует), а в первом 
			// случае операция правки поста не будет выполнена, если заданная категория не существует
			if(!is_numeric($postCatId)) {
				$this->errorMsg = "Bad category ID specified.";
				return false;
			}
			
			// Проверяем существование категории с заданным cat_id
			$sqlRequest = "SELECT * FROM ".$this->tblCats.
				" WHERE cat_id = ".$postCatId;
			$result = $this->Execute($sqlRequest, 'Проверяем существование категории с заданным cat_id');
			if(!$result) return false;
			
			if(!$result->RecordCount()) {
				// Категория не существует.
				$this->errorMsg = "Destination category with specified ID doesn`t exist.";
				return false;
			}
			
			// Существование категории проверено
			$sqlValues[] = 'cat_id = '.$postCatId;
		}
		
		// NYHAMG: $postCatId - cat_id найденной или созданной категории
		
		// Поле cached_tags обновляется отдельно, с помощью специального метода AssociateTags.
		
		// NYHAMG: Проверка корректности новых значений полей редактируемого поста выполнена. 
		// Если пост переносится в новую категорию, существование этой категории проверено.
		
		// Формируем SQL запрос на корректировку записи поста
		$sqlRequest = "UPDATE ".$this->tblPosts." SET ".
			implode(',', $sqlValues)." WHERE post_id = ".$_postId;
		$result = $this->Execute($sqlRequest, 
			"Корректировка записи поста");
		if(!$result) return false;
		
		// Ассоциируем пост с тагами, если они были определены
		if(isset($_newValues["tags"])) {
			$result = $this->AssociateTags($_postId, $_newValues["tags"]);
			if(!$result) return false;
		}
		
		$oldCatId = $post['cat_id'];
		
		// Если категория задана и изменена, обновляем счётчики старой 
		// и новой категории
		if($postCatId !== false && $oldCatId !== $postCatId) {
			
			// Обновляем счётчики старой категории
			$result = $this->UpdateCatCount($oldCatId);
			if(!$result) return false;
			
			// Обновляем счётчики новой категории
			$result = $this->UpdateCatCount($postCatId);
			if(!$result) return false;
			
		}
		
		// Обновляем счётчики постов в таблице пользователей
		$result = $this->UpdateUserPostCounters($this->userId);
		if(!$result) return false;
		
		return $_postId;
		
	}
	
	/**
	 * Метод проверяет возможность текущего пользователя править пост
	 * с заданными в параметрах значениями user_id автора и статуса.
	 * Метод не взаимодействует с БД, данные о текущем пользовтеле берутся 
	 * из внутренних переменных класса, а необходимые сведения о проверяемом 
	 * посте - из параметров.
	 * Метод используется внутри метода ChangePost, перед выполнением изменения поста, 
	 * а тек же в PostEditaAction и других action-ах, где требуется проверка возможности 
	 * править тот или иной пост.
	 * @param int $_postAuthorId user_id автора поста
	 * @param int $_postStatus Статус проверяемого поста
	 */
	function CanChangePost($_postAuthorId, $_postStatus) {
		// Юзер может изменить пост, если 
		return 
			// - Юзер - автор поста
			($this->userId === $_postAuthorId) ||
			// - Юзер - супервизор
			($this->userAccessLevel & AR_SUPERVISING) ||
			// - Юзер - модератор, а пост имеет статус public или community
			(($this->userAccessLevel & AR_MODERATION) && 
			($_postStatus == PS_PUBLIC || $_postStatus == PS_COMMUNITY));
	}
	
	/**
	 * Задаёт ассоциативные связи указанного поста с тагами. Если некоторых 
	 * из указанных тагов нет в базе, таги создаются. Значения счётчиков тагов 
	 * определяются в зависимости от статуса поста.
	 *
	 * @param int $_postId ID поста, для которого задаются таги
	 * @param array $_tags Массив тагов
	 * @return bool true или false, в зависимости от успешности выполнения действия
	 */
	function AssociateTags($_postId, $_tags) {
		// Проверяем корректность параметров
		if(!is_numeric($_postId)) {
			$this->errorMsg = "Wrong post ID specified.";
			return false;
		}
		
		if(!is_array($_tags)) {
			// Таги должны быть заданы в виде массива
			$this->errorMsg = "Bad tags specified.";
			return false;
		}
		
		$tags = array();
		
		// Проверяем синтаксис тагов
		if(!$this->TagSyntaxOk($_tags)) {
			$this->errorMsg = 'Bad tags specified (length limit exceeded '.
				'or inappropriate characters used).';
			return false;
		}
		
		// Выбрасываем дубли, если таковые есть
		$tags = array_unique($_tags);
		
		// Проверка корректности параметров выполнена
		
		// Определяем статус поста для того, чтобы можно было проверить право 
		// текущего пользователя на его (поста) корректировку. Заодно проверяем 
		// существоване поста
		$sqlRequest = "SELECT status, user_id FROM ".$this->tblPosts.
			" WHERE post_id = ".$_postId;
		$result = $this->Execute($sqlRequest, "Считываем статус поста.");
		if(!$result) return false;
		if(!$result->RecordCount()) {
			$this->errorMsg = "Required post doesn`t exists.";
			return false;
		}
		
		$postStatus = (int)$result->Fields(0);
		$postUserId = (int)$result->Fields(1);
		
		// Проверяем право пользователя править пост
		if(!$this->CanChangePost($postUserId, $postStatus)) {
			$this->errorMsg = "Current user has no access rights to edit this post.";
			return false;
		}
		
		// Считываем и запоминаем ID всех тагов, уже ассоциированных с постом.
		// Позже для этих тагов будут обновлены значения счётчиков
		$sqlRequest = "SELECT tag_id FROM ".$this->tblTagsMap.
			" WHERE post_id = ".$_postId;
		
		$result = $this->Execute($sqlRequest, 
			"Selecting old associated tag IDs already associated with post.");
		
		if(!$result) return false;
		
		$oldTagIds = array();
		foreach($result->GetArray() as $nextTagId) {
			$oldTagIds[] = $nextTagId[0];
		}
		
		// Удаляем все ассоциации, если таковые есть.
		if(count($oldTagIds)) {
			$sqlRequest = "DELETE FROM ".$this->tblTagsMap.
				" WHERE post_id = ".$_postId;
			$result = $this->Execute($sqlRequest, 
				"Удаляем все ассоциации поста с тагами, если таковые есть");
			if(!$result) return false;
		}
		
		$existingTags = array();
		
		// Если пост необходимо ассоциировать с тагами
		if(count($_tags)) {
			// Считываем все существующие таги из множества заданных
			$sqlRequest = "SELECT tag_id, tag FROM ".$this->tblTags.
				" WHERE tag IN ('".implode("', '", $_tags)."')";
			$result = $this->Execute($sqlRequest, "Считываем все существующие таги");
			if(!$result) return false;
			
			// Преобразуем результат запроса в нужную форму
			foreach($result->GetArray() as $nextTag) {
				$existingTags[$nextTag['tag_id']] = $nextTag['tag'];
			}
			
			// Определяем множество тагов, которые надо добавить, отсеивая их от существующих
			$tagsToAdd = array();
			foreach($_tags as $nextTag) {
			 	if(!in_array($nextTag, $existingTags)) $tagsToAdd[] = $nextTag;
			}
			
			// Функция array_diff_assoc() работает в PHP 4.3.0+.
			// Вместо цикла можно сделать так:
			// $tagsToAdd = array_diff_assoc($existingTags, $_tags);
			
			// Массив для сбора ID всех ассоциированных с постом тагов
			$associatedTagsIds = array_keys($existingTags);
			
			// Если нужно добавить таги
			if(count($tagsToAdd)) {
				
				// Определяем ID для новых тагов
				$sqlRequest = "SELECT MAX(tag_id) FROM ".$this->tblTags;
				$result = $this->Execute($sqlRequest, 'Определяем ID для новых тагов');
				if(!$result) return false;
				
				$tagId = (int)$result->Fields(0);
				
				$sqlTags = array();
				
				// Задаём новым тагам ID, формируем SQL запрос дл массового добавления
				foreach($tagsToAdd as $newTag) {
					
					$tagId++;
					$associatedTagsIds[] = $tagId;
					
					// У каждого тага в базе данных есть три значения счётчика: 
					//   - cached_cnt (значение, отображаемое для всех незарегистрированных 
					//     или неавторизованных пользователей - количество постов со статусом 
					//     PS_PUBLIC), 
					//   - cached_cnt_reg (значение для зарешистрированных пользователей - суммарное 
					//     количество постов со значениями статуса PS_PUBLIC и PS_COMMUNITY), 
					//   - cached_cnt_total (реальное полное значение количества всех постов, 
					//     ассоциированных с тагом)
					$cnt = ($postStatus == PS_PUBLIC)?1:0;
					$cntReg = ($postStatus == PS_PUBLIC || $postStatus == PS_COMMUNITY)?1:0;
					
					$sqlTags[] = "(".$tagId.",'".$newTag."',".$cnt.",".$cntReg.",1)";
					
				}
				
				$sqlTags = implode(",\n", $sqlTags);
				
				// Добавляем новые таги
				$sqlRequest = "INSERT INTO ".$this->tblTags.
					" (tag_id, tag, cached_cnt, cached_cnt_reg, cached_cnt_total) VALUES ".$sqlTags;
				$result = $this->Execute($sqlRequest, "Добавляем новые таги: ".$sqlTags);
				if(!$result) return false;
				
			}
			
			// Добавляем ассоциации поста с тагами
			$sqlTags = array();
			foreach($associatedTagsIds as $nextTagId) {
				$sqlTags[] = '('.$nextTagId.','.$_postId.')';
			}
			
			$sqlRequest = "INSERT INTO ".$this->tblTagsMap." (tag_id, post_id) ".
				" VALUES ".implode(',', $sqlTags);
			$result = $this->Execute($sqlRequest, "Добавляем ассоциации поста с тагами");
			if(!$result) return false;
			
		}
			
		// Если с постом ассоциированы уже существовавшие ранее таги
		$tagsToUpdate = array_unique(array_merge($oldTagIds, array_keys($existingTags)));
		if(count($tagsToUpdate)) {
			foreach($tagsToUpdate as $nextTagId) {
				// Обновляем их счётчики
				$sqlRequest = "UPDATE ".$this->tblTags.
				" SET cached_cnt = (SELECT COUNT(*) FROM ".$this->tblPosts.
				" WHERE status = ".PS_PUBLIC." AND post_id IN (SELECT post_id FROM ".
				$this->tblTagsMap." WHERE tag_id = ".$nextTagId.
				")), cached_cnt_reg = (SELECT COUNT(*) FROM ".$this->tblPosts.
				" WHERE (status = ".PS_PUBLIC." OR status = ".PS_COMMUNITY.
				") AND post_id IN (SELECT post_id FROM ".$this->tblTagsMap.
				" WHERE tag_id = ".$nextTagId.")), cached_cnt_total = (SELECT COUNT(*) FROM ".
				$this->tblTagsMap." WHERE tag_id = ".$nextTagId.") WHERE tag_id = ".$nextTagId;
				
				$result = $this->Execute($sqlRequest, 
					"Пересчитываем значения счётчика для тага с ID = ".$nextTagId);
				
				if(!$result) return false;
			}
			
			// Удаляем таги с обнулившимся счётчиком
			$sqlRequest = "DELETE FROM ".$this->tblTags." WHERE cached_cnt_total = 0";
			$result = $this->Execute($sqlRequest, 
				"Удаляем все таги с обнулёнными счётчиками");
			if(!$result) return false;
		}
		
		// Обновляем запись поста в базе (значение cached_tags)
		$cachedTags = implode(",", $tags);
		$sqlRequest = "UPDATE ".$this->tblPosts." SET cached_tags = '".$cachedTags.
			"' WHERE post_id = ".$_postId;
		$result = $this->Execute($sqlRequest, 
			"Обновляем запись поста в базе: cached_tags = '".$cachedTags."'");
		if(!$result) return false;
		
		return true;
		
	}
	
	/**
	 * Изменение комментария с заданным comment_id. У комментария можно 
	 * изменить тему, текст, флаг модерации, флаг скрытости и информацию 
	 * об авторе. Изменить ID автора, а так же parent_id коммента этим 
	 * методом нельзя.
	 *
	 * @param int $_commentId ID редактируемого комментрия
	 * @param array $_newValues массив, содержащий новые значения полей 
	 * комментария. Может содержать произвольное количество элементов, имеющих 
	 * индексы, соответствующие предопределённым значениям. Неопределённые 
	 * в массиве поля останутся неизменными.
	 *  - moderated		- новое значение флага модерирования
	 *  - hidden 		- флаг скрытости
	 *  - subj			- тема комментария
	 *  - content		- текст комментария
	 *  - author_name	- имя автора
	 *  - author_email	- email автора
	 *  - author_hp		- сайт автора
	 * @return bool true при успешном выполнении метода, или false 
	 * в потивном случае
	 */
	function ChangeComment(
		$_commentId,
		$_newValues) {
		
		// Проверка корректности параметров
		
		if(!is_numeric($_commentId)) {
			$this->errorMsg = "Incorrect comment ID value.";
			return false;
		}
		
		$newValues = array();
		
		// Проверяем корректност содержимого массива 
		// $_newValues с новыми значениями полей БД, заодно генерим SQL код
		if(isset($_newValues["moderated"])) {
			// Дополнительное условие, необходимое для модерации коммента
			if(!$this->CanApproveComments()) {
				$this->errorMsg = "Current user has no access rightd to moderate this post.";
				return false;
			}
			
			if(!(is_bool($_newValues["moderated"]) || is_numeric($_newValues["moderated"]))) {
				$this->errorMsg = "Incorrect parameter value.";
				return false;
			}
			
			$newValues["moderated"] = "moderated = ".($_newValues["moderated"]?1:0);
		}
		
		if(isset($_newValues["hidden"])) {
			if(!(is_bool($_newValues["hidden"]) || is_numeric($_newValues["hidden"]))) {
				$this->errorMsg = "Incorrect parameter value.";
				return false;
			}
			
			$newValues["hidden"] = "hidden = ".($_newValues["hidden"]?1:0);
		}
		
		if(isset($_newValues["subj"])) {
			$subj = addslashes($_newValues["subj"]);
			if(mb_strlen($subj, "utf-8") > MAX_COMMENT_TITLE_LENGTH) {
				$this->errorMsg = "Maximum length exceeded for comment`s subject.";
				return false;
			}
			
			$newValues["subj"] = "subj = '".$subj."'";
		}
		
		if(isset($_newValues["content"])) {
			$content = addslashes($_newValues["content"]);
			$cachedContent = addslashes($this->FilterText($_newValues["content"], FILTERS_COMMENT));
			if(mb_strlen($cachedContent, "utf-8") > MAX_COMMENT_LENGTH) {
				$this->errorMsg = "Maximum length exceeded for comment`s text.";
				return false;
			}
			
			$newValues["content"] = "content = '".$content."'";
			$newValues["cached_content"] = "cached_content = '".
				$cachedContent."'";
		}
		
		if(isset($_newValues["author_name"])) {
			$authorName = addslashes($_newValues["author_name"]);
			if(mb_strlen($authorName, "utf-8") > MAX_USER_NAME_LENGTH) {
				$this->errorMsg = "Maximum length exceeded for author`s name field.";
				return false;
			}
			
			$newValues["author_name"] = "author_name = '".$authorName."'";
		}
		
		if(isset($_newValues["author_email"])) {
			if(!preg_match("/^([a-zA-Z0-9_\-\.]+)@((\[[0-9]{1,3}\.[0-9]{1,3}".
				"\.[0-9]{1,3}\.)|(([a-zA-Z0-9\-]+\.)+))([a-zA-Z]{2,4}|[0-9]{1,3})(\]?)$/",
				$_newValues["author_email"])) {
				$this->errorMsg = "Incorrect email specified.";
				return false;
			}
			
			$authorEmail = addslashes($_newValues["author_email"]);
			if(mb_strlen($authorEmail, "utf-8") > MAX_USER_EMAIL_LENGTH) {
				$this->errorMsg = "Maximum length exceeded for author`s email field.";
				return false;
			}
			$newValues["author_email"] = "author_email = '".$authorEmail."'";
		}
		
		if(isset($_newValues["author_hp"])) {
			$authorHp = addslashes($_newValues["author_hp"]);
			if(mb_strlen($authorHp, "utf-8") > MAX_USER_HP_LENGTH) {
				$this->errorMsg = "Homepage URL maximum length exceeded.";
				return false;
			}
			
			$newValues["author_hp"] = "author_hp = '".$authorHp."'";
		}
		
		// NIHAMG: Параметры корректны
		
		// Необходимо проверить право пользователя на редактирование поста
		
		// Считываем ID поста, к которому относится коммент, 
		// и user_id автора коммента
		$sqlRequest = "SELECT post_id, user_id, hidden FROM ".$this->tblComments.
			" WHERE comment_id = ".$_commentId;
		$result = $this->Execute($sqlRequest, 
			'Считываем ID поста, к которому относится коммент, и user_id автора коммента');
		if(!$result) return false;
		
		if(!$result->RecordCount()) {
			// Коммент с заданным ID не существует
			$this->errorMsg = "Required comment doesn`t exists.";
			return false;
		}
		
		$postId = (int)$result->Fields(0);
		$comAuthorId = (int)$result->Fields(1);
		$comHidden = (int)$result->Fields(2);
		
		// Считываем статус поста
		$sqlRequest = "SELECT user_id,status FROM ".$this->tblPosts.
			" WHERE post_id = ".$postId;
		$result = $this->Execute($sqlRequest, 'Считываем статус поста');
		if(!$result) return false;
		
		if(!$result->RecordCount()) {
			// Странный случай, когда коммент есть, а поста, которому он коммент, 
			// нет. При нормальной работе этой ситуации быть не недолжно вообще.
			$this->errorMsg = "Required post doesn`t exists.";
			return false;
		}
		
		$postAuthorId = $result->Fields(0);
		$postStatus = $result->Fields(1);
		
		// Проверяем право пользователя на редактирование коммента
		if(!$this->CanChangeComment($postAuthorId, $postStatus, $comAuthorId, $comHidden)) {
			$this->errorMsg = "User has no access rights to edit this comment.";
			return false;
		}
		
		// Обновляем данные коммента в БД
		$sqlRequest = "UPDATE ".$this->tblComments." SET ".implode(",", $newValues).
			" WHERE comment_id = ".$_commentId;
		$result = $this->Execute($sqlRequest, 'Обновляем комментарий');
		if(!$result) return false;
		
		// Обновляем счётчики комментов поста
		$this->UpdateCommentsCount($postId);
		if(!$result) return false;
		
		// Обновляем счётчики комментариев в таблице пользователей
		// для автора комментария и автора комментируемого поста
		$result = $this->UpdateUserComCounters($postAuthorId);
		if(!$result) return false;
		
		$result = $this->UpdateUserComCounters($comAuthorId);
		if(!$result) return false;
		
		return true;
		
	}
	
	/**
	 * Метод определяет наличие прав у текущего пользователя 
	 * редактировать заданный коммент. Вызывается из ChangeComment и 
	 * из action`ов
	 * @param $_postAuthorId
	 * @param $_postStatus
	 * @param $_comAuthorId
	 * @param $_comHidden
	 * @return bool true/false, в зависимости от того, можно или не можно
	 */
	function CanChangeComment($_postAuthorId, $_postStatus, $_comAuthorId, $_comHidden) {
		return 
			// Пользователь - супервизор
			(($this->userAccessLevel & AR_SUPERVISING) ||
			// Пользователь зарегистрирован и является автором поста или коммента
			($this->userId > -1 && ($this->userId == $_postAuthorId || $this->userId == $_comAuthorId)) ||
			// Пост имеет статус public или community, коммент не скрыт, пользователь - модератор
			(($_postStatus == PS_PUBLIC || $_postStatus == PS_COMMUNITY) && 
			($this->userAccessLevel & AR_MODERATION) && $_comHidden == 0));
	}
	
	/**
	 * Метод определяет возможность текущего пользователя модерировать коммент.
	 * Модерация подразумевает возможность удаления коммента и смены значений его 
	 * поля moderated с 0 на 1.
	 * @param $_postStatus
	 * @param $_comHidden
	 * @return bool true/false, в зависимости от того, можно или не можно
	 */
	function CanModerateComment($_postStatus, $_comHidden) {
		return 
			// Пользователь обладаем правом модерирования
			($this->userAccessLevel & AR_MODERATION) && 
			// Пост не скрыт
			(($_postStatus == PS_PUBLIC) || ($_postStatus == PS_COMMUNITY)) && 
			// Коммент не скрыт
			!$_comHidden;
	}
	
	/**
	 * Изменение категории (переименование и коррекция описания, 
	 * опциональное слияние с существующей категорией, коррекция 
	 * счётчиков).
	 *
	 * @param mixed $_catId ID редактируемой категории. В зависимости от его 
	 * типа будет выполнена выборка категории по её ID (int) или по shortcut (string).
	 * @param array $_newValues массив, содержащий новые значения полей 
	 * категории. может содержать произвольное количество элементов, имеющих 
	 * индексы, соответствующие предопределённым значениям. неопределённые 
	 * в массиве поля останутся неизменными.
	 *  - shortcut		- новое значение короткого имени категории
	 *  - title			- новое значение заголовка категории
	 *  - description	- новое значение описания категории
	 * @return bool true при успешном выполнении метода, или false 
	 * в потивном случае
	 */
	function ChangeCat(
		$_catId,
		$_newValues) {
		
		// Проверка первичного условия возможности чтения поста
		if(!($this->userAccessLevel & AR_POSTING ||
			$this->userAccessLevel & AR_SUPERVISING)) {
			$this->errorMsg = "Current user have no access level to edit category data.";
			return false;
		}
		
		// Проверка корректности параметров
		
		// Массив, в  котором будут формироваться фрагменты SQL 
		// запроса на обновление категории
		$sqlFields = array();
		
		// Первым параметром должен быть либо ID редактируемой категори,
		// либо её shortcut.
		if(!(is_numeric($_catId) || (is_string($_catId)) && 
			mb_eregi(REGEXP_SHORTCUT, $_newValues["shortcut"]))) {
			$this->errorMsg = 'Incorrect category ID value specified.';
			return false;
		}
		
		if(isset($_newValues["shortcut"])) {
			$catShortcut = trim($_newValues["shortcut"]);
			if(!mb_eregi(REGEXP_SHORTCUT, $_newValues["shortcut"])) {
				$this->errorMsg = "Bad shortcut value specified or length limit exceeded.";
				return false;
			}
			$sqlFields[] = "shortcut = '".$catShortcut."'";
		}
		
		if(isset($_newValues["title"])) {
			$catTitle = addslashes(trim($_newValues["title"]));
			if(mb_strlen($catTitle, "utf-8") > MAX_CAT_TITLE_LENGTH) {
				$this->errorMsg = "Category title maximum length limit exceeded.";
				return false;
			}
			$sqlFields[] = "title = '".$catTitle."'";
		}
		
		if(isset($_newValues["description"])) {
			$catDescription = addslashes($_newValues["description"]);
			$catCachedDescription = addslashes($this->FilterText($_newValues["description"], FILTERS_DESC));
			if(mb_strlen($catCachedDescription, "utf-8") > MAX_CAT_DESC_LENGTH) {
				$this->errorMsg = "Category description maximum length limit exceeded.";
				return false;
			}
			$sqlFields[] = "description = '".$catDescription."'";
			$sqlFields[] = "cached_description = '".$catCachedDescription."'";
		}
		
		// Проверка корректности параметров выполнена
		
		// Считываем заданную категорию
		$sqlRequest = "SELECT * FROM ".$this->tblCats." WHERE ";
		if(is_numeric($_catId)) {
			$sqlRequest .= "cat_id = ".$_catId;
		} else {
			$sqlRequest .= "shortcut = '".$_catId."'";
		}
		$result = $this->Execute($sqlRequest, 'Считываем заданную категорию');
		if(!$result) return false;
		
		if(!$result->RecordCount()) {
			$this->errorMsg = "Specified category doesn`t exists.";
			return false;
		}
		
		$result = $result->GetArray();
		$srcCat = $result[0];
		
		$catId = $srcCat['cat_id'];
		
		if(isset($catShortcut)) {
			// Задано новое значение shortcut
			// Необходимо проверить существование в базе категории с таким именем
			$sqlRequest = "SELECT * FROM ".$this->tblCats.
				" WHERE shortcut = '".$catShortcut."' AND cat_id != ".$catId;
			$result = $this->Execute($sqlRequest, 
				'Проверяем существование в базе категории с заданным shortcut');
			if(!$result) return false;
			
			if($result->RecordCount()) {
				// Такая категория уже существует
				// Необходимо слить воедино старую и новую категории
				$result = $result->GetArray();
				$destCat = $result[0];
				
				$newCatId = $destCat['cat_id'];
				
				// NIHAMG: Есть два ID сливаемых категорий - $catId и $newCatId.
				// После слияния останется одна категория с ID == $newCatId
				
				if($catId == 0) {
					// Необходимо сохранить ID старой категории, т.к. она служебная
					// (используется для постов uncategorized). Для этого ID категорий 
					// меняются местами
					$catId = $newCatId;
					$newCatId = 0;
				}
				
				// Перемещаем все посты из старой категории в новую
				$sqlRequest = "UPDATE ".$this->tblPosts." SET cat_id = ".
					$newCatId." WHERE cat_id = ".$catId;
				$this->Execute($sqlRequest, 'Перемещаем все посты из старой категории в новую');
				if(!$result) return false;
				
				// Удаляем старую категорию
				$sqlRequest = "DELETE FROM ".$this->tblCats.
					" WHERE cat_id = ".$catId;
				$this->Execute($sqlRequest, 'Удаляем старую категорию');
				if(!$result) return false;
				
				// Обновляем счётчики категории
				$sqlFields[] = "cached_cnt = (SELECT COUNT(*) FROM ".$this->tblPosts.
					" WHERE (cat_id = ".$newCatId." OR cat_id = ".$catId.
					") AND status = ".PS_PUBLIC.")";
				$sqlFields[] = "cached_cnt_reg = (SELECT COUNT(*) FROM ".$this->tblPosts.
					" WHERE (cat_id = ".$newCatId." OR cat_id = ".$catId.
					") AND (status = ".PS_PUBLIC." OR status = ".PS_COMMUNITY."))";
				$sqlFields[] = "cached_cnt_total = (SELECT COUNT(*) FROM ".$this->tblPosts.
					" WHERE cat_id = ".$newCatId." OR cat_id = ".$catId.")";
				
				// Меняем значение ID, чтобы корректно сработал приведённый ниже SQL запрос
				$catId = $newCatId;
			}
		}
		
		// NIHAMG: Могут быть три варианта:
		//   - новое значение shortcut не было задано
		//   - категория с новым shortcut не существует (слияние категорий не требуется)
		//   - новая категория существует и слита с изменяемой (если одна 
		//     из них - uncategorized, результирующая категория имеет ID == 0)
		// В любом случае массив $sqlFields содержит поля, которые необходимо обновить.
		
		// Обновляем данные категории
		$sqlRequest = "UPDATE ".$this->tblCats." SET ".implode(",", $sqlFields).
			"WHERE cat_id = ".$catId;
		$result = $this->Execute($sqlRequest, 'Обновляем данные категории');
		if(!$result) return false;
		
		return true;
		
	}
	
	/**
	 * Проверяет наличес права у текущего пользователя на правку категорий.
	 * Это право определяется один раз для всех категорий.
	 *
	 * @return bool true при успешном выполнении метода, или false 
	 * в потивном случае
	 */
	/*
	function CanChangeCats() {
		return $this->userAccessLevel & (AR_SUPERVISING | AR_MODERATION);
	}
	*/
	
	/**
	 * Переименование тага (переименование, опциональное слияние 
	 * с существующим тагом, автокоррекция счётчиков)
	 *
	 * @param string $_tag имя редактируемого тага
	 * @param string $_nuTag новое имя редактируемого тага
	 * @return bool true при успешном выполнении метода, или false 
	 * в потивном случае
	 */
	function RenameTag($_tag, $_nuTag) {
		
		// Проверка первичного условия возможности чтения поста
		if(!($this->userAccessLevel & AR_MODERATION)) {
			$this->errorMsg = "Current user have no access level to edit tags.";
			return false;
		}
		
		// Глупый случай, когда таг переименовывается сам в себя
		if($_tag == $_nuTag) return true;
		
		// Проверка корректности заданных значений параметров
		if(!$this->TagSyntaxOk($_tag) || !$this->TagSyntaxOk($_nuTag)) {
			$this->errorMsg = "Incorrect tag syntax.";
			return false;
		}
		
		// Считываем таг
		$sqlRequest = "SELECT * FROM ".$this->tblTags." WHERE tag = '".$_tag."'";
		$result = $this->Execute($sqlRequest, "Считываем таг.");
		if(!$result) return false;
		
		// Проверка существования переименовываемого тага
		if(!$result->RecordCount()) {
			$this->errorMsg = "Source tag doesn't exists.";
			return false;
		}
		
		$tagId = $result->Fields(0);
		$tag = $result->Fields(1);
		
		// Сбрасываем значение полей cached_tags в таблице posts, для записей, 
		// ассоциированных с редактируемым тагом
		$sqlRequest = "UPDATE ".$this->tblPosts." SET cached_tags = ',,' WHERE post_id IN ".
			"(SELECT post_id FROM ".$this->tblTagsMap." WHERE tag_id = ".$tagId.")";
		$result = $this->Execute($sqlRequest, 
			"Сбрасываем значение cached_tags для асосциированных с тагом постов.");
		if(!$result) return false;
		
		// Проверяем существование тага с новым заданным именем
		$sqlRequest = "SELECT * FROM ".$this->tblTags." WHERE tag = '".$_nuTag."' AND tag_id != ".$tagId;
		$result = $this->Execute($sqlRequest,
			"Проверяем существование тага с новым заданным именем");
		if(!$result) return false;
		
		// Если таг с новым именем уже существует, требуется слить их со старым тагом
		if($result->RecordCount()) {
			$nuTagId = $result->Fields(0);
			
			// Заменяем ассоциации постов с ID старого тага на ассоциации с новым ID
			// Для этого:
			
			// Считываем post_id всех постов, ассоциированных со старым и новым тагами
			$sqlRequest = "SELECT * FROM ".$this->tblTagsMap." WHERE tag_id = ".
				$tagId." OR tag_id = ".$nuTagId;
			$result = $this->Execute($sqlRequest,
				"Считываем post_id всех постов, ассоциированных со старым и новым тагами");
			if(!$result) return false;
			
			// Выбрасываем дубликаты
			$postIds = array();
			foreach($result->GetArray() as $post) {
				$postIds[$post['post_id']] = $post['post_id'];
			}
			
			foreach($postIds as $postId => $value) {
				$postIds[$postId] = "(".$nuTagId.",".$postId.")";
			}
			
			// Стираем все ассоциации постов со старым и новым тагами
			$sqlRequest = "DELETE FROM ".$this->tblTagsMap." WHERE tag_id = ".
				$tagId." OR tag_id = ".$nuTagId;
			$result = $this->Execute($sqlRequest,
				"Стираем все ассоциации постов со старым и новым тагами");
			if(!$result) return false;
			
			if(count($postIds)) {
				// Добавляем обновлённое множество ассоциаций с новым тагом
				$sqlValues = implode(",", $postIds);
				$sqlRequest = "INSERT INTO ".$this->tblTagsMap." (tag_id, post_id) VALUES ".$sqlValues;
				$result = $this->Execute($sqlRequest, 
					"Добавляем обновлённое множество ассоциаций с новым тагом");
				if(!$result) return false;
			}
			
			// Обновляем счётчики нового тага
			$sqlRequest = "UPDATE ".$this->tblTags.
			" SET cached_cnt = (SELECT COUNT(*) FROM ".$this->tblPosts.
			" WHERE status = ".PS_PUBLIC." AND post_id IN (SELECT post_id FROM ".
			$this->tblTagsMap." WHERE tag_id = ".$nuTagId.")), cached_cnt_reg = (SELECT COUNT(*) FROM ".
			$this->tblPosts." WHERE (status = ".PS_PUBLIC." OR status = ".PS_COMMUNITY.
			") AND post_id IN (SELECT post_id FROM ".$this->tblTagsMap." WHERE tag_id = ".$nuTagId.
			")), cached_cnt_total = (SELECT COUNT(*) FROM ".$this->tblTagsMap." WHERE tag_id = ".$nuTagId.
			") WHERE tag_id = ".$nuTagId;
			
			$result = $this->Execute($sqlRequest, 
				"Пересчитываем значения счётчика для тага с ID = ".$nuTagId);
			
			if(!$result) return false;
			
			// Убиваем старый таг в таблице тагов
			$sqlRequest = "DELETE FROM ".$this->tblTags." WHERE tag_id = ".$tagId;
			$result = $this->db->Execute($sqlRequest, 
				"Убиваем старый таг в таблице тагов");
			if(!$result) return false;
			
		} else {
			// Необходимо просто переименовать старый таг
			$sqlRequest = "UPDATE ".$this->tblTags." SET tag = '".$_nuTag.
				"' WHERE tag_id = ".$tagId;
			$result = $this->Execute($sqlRequest, 
				"Переименоваем старый таг.");
			if(!$result) return false;
		}
		
		return true;
		
	}
	
	/**
	 * Метод, фильтрующий контент постов
	 *
	 * @param string $_text Текст для обработки
	 * @param int $_filters Определяет необходимые фильтры (см. константы FILTER_*). 
	 * SafeHTML используется всегда
	 * @return string Обработанный текст
	 */
	function FilterText($_text, $_filters) {
		$safehtml =& new safehtml();
        
        $filtered = strtr($_text, array('<!--' => '[[[[!--', '-->' => '--]]]]'));
        $filtered = $safehtml->parse($filtered);
        $filtered = strtr($filtered, array('[[[[!--' => '<!--', '--]]]]' => '-->'));
        
        if($_filters & FILTER_NL2BR) {
        	$filtered = nl2br($filtered);
        }
        
        if($_filters & FILTER_MARKDOWN) {
        	$filtered = Markdown($filtered);
        }
        
        if($_filters & FILTER_TYPO) {
        	$filtered = kavych($filtered);
        }
        
		return $filtered;
	}
	
	/**
	 * Метод обновляет значение поля cached_tags для поста с заданным ID
	 *
	 * @param int $_postId post_id поста, для которого необходимо бновить поле cached_tags
	 * @return string Акутальный список тагов (array), соответствующий строковому значению 
	 * поля cached_tags в БД или false, в случае ошибки при выполнении
	 */
	function UpdateCachedTags($_postId) {
		// Проверка корректности параметра
		if(!is_numeric($_postId)) {
			$this->errorMsg = "Bad post ID value specified.";
			return false;
		}
		
		// Считываем массив тагов, связанных с постом
		$sqlRequest = "SELECT tag FROM ".$this->tblTags." INNER JOIN ".$this->tblTagsMap.
			" USING (tag_id) WHERE post_id = ".$_postId." GROUP BY tag ORDER BY tag";
		$result = $this->Execute($sqlRequest, 'Считываем массив тагов, связанных с постом');
		if(!$result) return false;
		
		$tags = array();
		
		// Если с постом не ассоциированы таги
		if($result->RecordCount()) {
			// Преобразуем результат запроса в удобную форму
			foreach($result->GetArray() as $tag) $tags[] = $tag[0];
		}
		
		// Обновляем поле cached_tags для заданного поста
		$sqlRequest = "UPDATE ".$this->tblPosts.
			" SET cached_tags = '".implode(',', $tags)."' WHERE post_id = ".$_postId;
		$result = $this->Execute($sqlRequest, 'Обновляем поле cached_tags для заданного поста');
		if(!$result) return false;
		
		return $tags;
		
	}
	
	/**
	 * Удаляет категорию. При необходимости можно задать ID категории, в которую будут перенесены 
	 * посты из удаляемой категории. Если новой категории не задано, посты переносятся 
	 * в категорию Uncategorized (cat_id = 0). Категорию uncategorized удалить нельзя.
	 *
	 * @param int $_catId ID удаляемой категории
	 * @result bool true/false, в зависимости от упешности выполнения
	 */
	function DeleteCat($_catId, $_newCatForPosts = 0) {
		// Проверка корректности параметра
		if(!is_numeric($_catId) || !is_numeric($_newCatForPosts) || $_catId == $_newCatForPosts) {
			$this->errorMsg = "Incorrect category ID value specified.";
			return false;
		}
		
		// Проверка вторичного условия допустимости писать комментариий 
		if(!$this->CanManageCats()) {
			$this->errorMsg = "User has no access rights to delete categories.";
			return false;
		}
		
		if($_catId == 0) {
			$this->errorMsg = "'Uncategorized' category (cat_id = 0) can`t be deleted.";
			return false;
		}
		
		// Проверка существования удаляемой категории
		$sqlRequest = "SELECT * FROM ".$this->tblCats." WHERE cat_id = ".$_catId;
		$result = $this->Execute($sqlRequest, "Проверка существования удаляемой категории");
		if(!$result) return false;
		
		if(!$result->RecordCount()) {
			$this->errorMsg = "Specified category doesn`t exists.";
			return false;
		}
		
		// Проверка существования новой категории
		$sqlRequest = "SELECT * FROM ".$this->tblCats." WHERE cat_id = ".$_newCatForPosts;
		$result = $this->Execute($sqlRequest, "Проверка существования новой категории");
		if(!$result) return false;
		
		if(!$result->RecordCount()) {
			$this->errorMsg = "Specified category doesn`t exists.";
			return false;
		}
		
		// Удаление категории
		$sqlRequest = "DELETE FROM ".$this->tblCats." WHERE cat_id = ".$_catId;
		$result = $this->Execute($sqlRequest, 
			"Удаляем категорию с cat_id = ".$_catId);
		if(!$result) return false;
		
		// Перемещение постов в категорию $_newCatForPosts
		$sqlRequest = "UPDATE ".$this->tblPosts." SET cat_id = ".$_newCatForPosts.
			" WHERE cat_id = ".$_catId;
		$result = $this->Execute($sqlRequest, 
			"Перемещение постов в категорию с cat_id = ".$_newCatForPosts);
		if(!$result) return false;
		
		// Обновление счётчика категории
		$result = $this->UpdateCatCount($_newCatForPosts);
		if(!$result) return false;
		
		return true;
		
	}
	
	/**
	 * Поверяет наличие прав у текущего пользователя на удаление категорий.
	 * Используется в методе DeleteCat, для проверки прав перед удалением 
	 * и в action'е DeleteCatAction.
	 * @return bool true/false, в зависимости от того, можно или нет.
	 */
	/*
	function CanDeleteCats() {
		return (bool)($this->userAccessLevel & (AR_MODERATION | AR_SUPERVISING));
	}
	*/
	
	/**
	 * Поверяет наличие прав у текущего пользователя на управление категориями. 
	 * Используется везде, где требуется проверка наличия прав на добавление, 
	 * удаление или редактирование категории: методы AddCat, ChangeCat, DeleteCat 
	 * и связанные action'ы.
	 *
	 * @return bool true/false, в зависимости от того, можно или нет.
	 */
	function CanManageCats() {
		return (bool)($this->userAccessLevel & (AR_MODERATION | AR_SUPERVISING));
	}
	
	/**
	 * Метод определяет возможность пользователя с заданным уровнем 
	 * доступа писать комментарии без модерации.
	 * @return bool true/false, в зависимости от того, можно или нет
	 */
	function CanCommentImmediately() {
		if(COMMENT_MODERATION_NEEDED) return (bool)($this->userAccessLevel & (AR_MODERATION | AR_SUPERVISING));
		return true;
	}
	
	/**
	 * Метод считывает и возвращает категорию с заданным cat_id или shortcut.
	 *
	 * @param mixed $_catId Значение cat_id или shortcut нужной категории
	 * @param bool $_byShortcut Флаг, определяющий интерпретацию значения 
	 * аргумента $_catId. По-умолчанию, $_catId будет вопринят как cat_id. 
	 * Если же аргументу $_byShortcut задано значение true, $_catId будет 
	 * интерпретирован как shortcut категории. В каждом случае поиск категории
	 * выполняется по соответствующему полю.
	 * @return mixed Массив с данными категории или false, если катгория 
	 * не существует или произошла ошибка
	 */
	function GetCat($_catId, $_byShortcut = false) {
		if(!(is_numeric($_catId) || is_string($_catId)) || 
			$_catId === '' || !is_bool($_byShortcut)) {
			
			$this->errorMsg = 'Incorrect category id specified.';
			return false;
		}
		
		// Определяем кретерий поиска
		$field = $_byShortcut?'shortcut':'cat_id';
		
		$sqlRequest = 'SELECT * FROM '.$this->tblCats.' WHERE '.$field.' = '.$_catId.' LIMIT 1';
		$result = $this->Execute($sqlRequest);
		if(!$result) return false;
		
		if(!$result->RecordCount()) {
			// Категория не существует
			$this->errorMsg = 'Category doesn`t exists.';
			return false;
		}
		
		$result = $result->GetArray();
		$cat = $result[0];
		
		// Выбрасываем дубликаты
		foreach($cat as $field => $value) {
			if(is_numeric($field)) unset($cat[$field]);
		}
		
		return $cat;
		
	}
	
	/**
	 * Проверяет возможность удаления текущим пользователем тага. Метод является 
	 * алиасом CanChangeTag, т.к. для удаления и редактирования тагов необходимы 
	 * одни и те же права доступа
	 * @return bool true/false, в зависимости от того, можно или нет
	 */
	function CanDeleteTags() {
		return $this->CanChangeTags();
	}
	
	/**
	 * Удаляет таг.
	 * @param string $_tag Таг, который нужно удалить
	 * @return bool true/false, в зависимости от успешности выполнения
	 */
	function DeleteTag($_tag) {
		
		// Проверяем корректность синтаксиса тага
		if(!$this->TagSyntaxOk($_tag)) {
			$this->errorMsg = 'Incorrect tag syntax specified.';
			return false;
		}
		
		// Считать tag_id по имени тага, проверить его существование
		$sqlRequest = "SELECT tag_id FROM ".$this->tblTags." WHERE tag = '".$_tag."'";
		$result = $this->Execute($sqlRequest, 
			"Считать tag_id по имени тага, проверить его существование");
		if(!$result) return false;
		
		if(!$result->RecordCount()) {
			$this->errorMsg = "Required tag doesn't exists.";
			return false;
		}
		
		$tagId = $result->Fields(0);
		
		// Считать все post_id, ассоциированные с тагом
		$sqlRequest = "SELECT post_id FROM ".$this->tblTagsMap." WHERE tag_id = ".$tagId;
		$result = $this->Execute($sqlRequest, 
			"Считать все post_id, ассоциированные с тагом");
		if(!$result) return false;
		
		// Если ассоциации с постами есть (а они должны быть, т.к. пустые таги 
		// автоматически удаляются)
		if($result->RecordCount()) {
			$postIds = array();
			foreach($result->GetArray() as $r) $postIds[] = $r[0];
			
			// Сбрасываем значение полей cached_tags во всех ассоциированных постах, присвоив им значения ",,"
			$sqlRequest = "UPDATE ".$this->tblPosts." SET cached_tags = ',,' WHERE post_id IN (".implode(',', $postIds).")";
			$result = $this->Execute($sqlRequest, 
				'Сбрасываем значение полей cached_tags во всех ассоциированных постах, присвоив им значения ",,"');
			if(!$result) return false;
			
			// Удяляем все ассоциации тага с постами
			$sqlRequest = "DELETE FROM ".$this->tblTagsMap." WHERE tag_id = ".$tagId;
			$result = $this->Execute($sqlRequest, 
				'Удяляем все ассоциации тага с постами');
			if(!$result) return false;
		}
		
		// Удалить запись с tag_id в tags
		$sqlRequest = "DELETE FROM ".$this->tblTags." WHERE tag_id = ".$tagId;
		$result = $this->Execute($sqlRequest, 
			'Удалить запись с tag_id в tags');
		if(!$result) return false;
		
		return true;
	}
	
	/**
	 * Проверяет возможность редактирования текущим пользователем тага. У метода есть 
	 * алиас CanDeleteTag
	 * @return bool true/false, в зависимости от того, можно или нет
	 */
	function CanChangeTags() {
		// Для редактирования и удаления тагов, у пользователя доложны быть 
		// права доступа модератора или супервизора
		return ($this->userAccessLevel & (AR_MODERATION | AR_SUPERVISING));
	}
	
	/**
	 * Метод определяет существование тага
	 * @param string $_tag Искомый таг
	 * @return bool true/false, в зависимоти от существования тага
	 */
	function TagExists($_tag) {
		// Проверка синтаксиса тага
		if(!$this->TagSyntaxOk($_tag)) {
			$this->errorMsg = 'Incorrect tag syntax specified.';
			return false;
		}
		
		$sqlRequest = 'SELECT tag_id FROM '.$this->tblTags.' WHERE tag = "'.$_tag.'"';
		$result = $this->Execute($sqlRequest, "Считываем таг для проверки его существования.");
		if(!$result) return false;
		
		return (bool)$result->RecordCount();
		
	}
	
	/**
	 * Метод проверяет соответствие имени пользователя и его пароля записи в базе.
	 * @param string $_userName Логин пользователя
	 * @param string $_password Пароль пользователя
	 * @return mixed user_id (int) - в случае, если пользователь с заданным 
	 * именем существует и пароль верный. false (bool) - в случае, пользователь не существует, 
	 * задан неверный пароль или произошла обишибка приработее с БД. Если метод возвращает false, 
	 * во внутренней переменной класса errorMsg сохраняется сообщение о том, что именно 
	 * неправильно - логин или пароль.
	 */
	function CheckUser($_login, $_password) {
		// Проверка синтаксиса логина и пароля
		if(!preg_match(REGEXP_LOGIN, $_login)) {
			$this->errorMsg = "Incorrect login syntax.";
			return false;
		}
		
		if(!strlen($_password) > MAX_USER_PWD_LENGTH) {
			$this->errorMsg = "Pasword length limit exceeded.";
			return false;
		}
		
		// Считываем пользовательский пароль из базы
		$sqlRequest = "SELECT * FROM ".$this->tblUsers." WHERE login = '".addslashes($_login)."'";
		$result = $this->Execute($sqlRequest);
		if(!$result) return false;
		
		if(!$result->RecordCount()) {
			// Пользователь с заданным логином не найден
			$this->errorMsg = "User doesn't exists.";
			return false;
		}
		
		$userData = $result->GetArray();
		$userData = $userData[0];
		
		if($userData['pwd'] !== md5($_password)) {
			// Пароль не совпадает
			$this->errorMsg = "Wrong password.";
			return false;
		}
		
		return array(
					'user_id' => $userData['user_id'],
					'name' => stripslashes($userData['name']),
					'email' => stripslashes($userData['email']),
					'hp' => stripslashes($userData['hp']),
					'access_level' => $userData['access_level']
				);
		
	}
	
	/**
	 * Выполняет проверку наличия прав у текущего пользователя 
	 * для управления пользовательскими профилями (создание, удаление, 
	 * редактирование). Используется в методах AddUser, ChangeUser и DeletUser, 
	 * а так же в action'ах, связанных с управлением пользователями.
	 * @return true/false, в зависимости от того, можно или нет
	 */
	function CanManageUsers() {
		return (bool)($this->userAccessLevel & AR_SUPERVISING);
	}
	
	/**
	 * Добавляет в базу нового пользователя.
	 * @param string $_login Логин нового пользователя
	 * @param string $_password Пароль
	 * @param string $_email email
	 * @param int $_accessLevel Уровень доступа
	 * @param string $_name (опциональный параметр) Имя
	 * @param string $_hp URL (опциональный параметр) домашней страницы
	 * @return mixed Возвращает значение user_id для нового пользоателя, или false 
	 * в случае возникновения ошибки при работе (в том числе, если пользователь 
	 * с заданным логином уже существует)
	 */
	function AddUser(
			$_login,
			$_password,
			$_email,
			$_accessLevel = false,
			$_name = false,
			$_hp = false,
			$_description = false,
			$_customData = false
		) {
		
		// Проверяем наличие прав у текущего пользователя для выполнения действия
		if(!$this->CanManageUsers()) {
			$this->errorMsg = "User have no access rights to add new users.";
			return false;
		}
		
		// Проверка синтаксиса параметров
		if(!preg_match(REGEXP_LOGIN, $_login)) {
			$this->errorMsg = "Incorrect login syntax.";
			return false;
		}
		
		if(strlen($_name) > MAX_USER_NAME_LENGTH) {
			$this->errorMsg = "User name length limit exceeded.";
			return false;
		}
		
		if(!preg_match(REGEXP_EMAIL, $_email)) {
			$this->errorMsg = "Incorrect email syntax.";
			return false;
		}
		
		if(strlen($_hp) > MAX_USER_HP_LENGTH) {
			$this->errorMsg = "User name length limit exceeded.";
			return false;
		}
		
		if(!(($_accessLevel === false) || is_numeric($_accessLevel))) {
			$this->errorMsg = "Incorrect access rights specified.";
			return false;
		}
		
		if(strlen($_password) > MAX_USER_PWD_LENGTH) {
			$this->errorMsg = "Pasword length limit exceeded.";
			return false;
		}
		
		if(strlen($_password) < MIN_USER_PWD_LENGTH) {
			$this->errorMsg = "Password must contain at least ".MIN_USER_PWD_LENGTH." characters.";
			return false;
		}
		
		if(strlen($_description) > MAX_USER_DESC_LENGTH) {
			$this->errorMsg = "User description maximum length limit exceeded.";
			return false;
		}
		
		$customData = serialize($_customData);
		
		if(strlen($customData) > MAX_USER_CUSTOM_DATA_LENGTH) {
			$this->errorMsg = "User custom data maximum size limit exceeded.";
			return false;
		}
		
		// Параметры проверены
		
		// Проверяем наличие пользователя с заданным логином в базе
		$sqlRequest = "SELECT user_id FROM ".$this->tblUsers." WHERE login = '".$_login."'";
		$result = $this->Execute($sqlRequest, 'Проверяем наличие пользователя с заданным логином в базе');
		if(!$result) return false;
		
		if($result->RecordCount()) {
			// Пользователь с заданным логином уже зарегистрироан
			$this->errorMsg = "Another user with the same login already registered.";
			return false;
		}
		
		// Определяем индекс для нового юзера
		$sqlRequest = "SELECT MAX(user_id) FROM ".$this->tblUsers;
		$result = $this->Execute($sqlRequest, 'Определяем индекс для нового юзера');
		if(!$result) return false;
		
		$newUserId = $result->Fields(0) + 1;
		
		// Формируем массив с добавляемыми в таблицу значениями
		$sqlValues = array();
		$sqlValues['user_id'] = $newUserId;
		$sqlValues['login'] = '"'.addslashes($_login).'"';
		$sqlValues['email'] = '"'.addslashes($_email).'"';
		$sqlValues['access_level'] = $_accessLevel?$_accessLevel:ACCESS_DEFAULT;
		$sqlValues['pwd'] = '"'.md5($_password).'"';
		
		if($_hp) $sqlValues['hp'] = '"'.addslashes($_hp).'"';
		if($_name) $sqlValues['name'] = '"'.addslashes($_name).'"';
		if($_description) {
			$sqlValues['description'] = '"'.addslashes($_description).'"';
			$sqlValues['cached_description'] = '"'.
				addslashes($this->FilterText($_description, FILTERS_DESC)).'"';
		}
		if($_customData) $sqlValues['custom_data'] = '"'.addslashes($customData).'"';
		
		// Добавляем пользователя в базу
		$sqlRequest = "INSERT INTO ".$this->tblUsers.
			" (".implode(", ", array_keys($sqlValues)).") VALUES (".implode(", ", $sqlValues).")";
		$result = $this->Execute($sqlRequest, 'Добавляем пользователя в базу');
		if(!$result) return false;
		
		return $newUserId;
		
	}
	
	/**
	 * Выполняет редактирование пользовательской информации.
	 * 
	 * @param int $_userId user_id редактируемого пользователя
	 * @param array $_newValues Ассоциативный массив с данными, 
	 * которые необходимо изменить в пользовательском профиле. 
	 * Возможные значения индексов: login, name, email, pwd, access_level, 
	 * description, access_level, custom_data
	 */
	function ChangeUser($_userId, $_newValues) {
		
		// Проверяем наличие прав у текущего пользователя для выполнения действия
		if(!($this->CanManageUsers() || $this->userId == $_userId)) {
			$this->errorMsg = "User have no access rights to edit user profiles.";
			return false;
		}
		
		// Проверка корректности параметров
		if(!is_numeric($_userId)) {
			$this->errorMsg = "Incorrect user ID specified.";
			return false;
		}
		
		// Проверяем существование редактируемого профил, считывая его login.
		// login изначальное значение пользовательского логина понадобится при проверке 
		// возможности задать ему новое значение (логины должня быть уникальными)
		$sqlRequest = 'SELECT login, name, email, hp, pwd, description, custom_data, access_level FROM '.
			$this->tblUsers.' WHERE user_id = '.$_userId;
		$result = $this->Execute($sqlRequest, 
			'Определяем login пользователя с user_id = '.$_userId);
		if(!$result) return false;
		
		// Проверка существования пользователя
		if(!$result->RecordCount()) {
			$this->errorMsg = "User with specified ID does't exists.";
			return false;
		}
		
		$result = $result->GetArray();
		list($userLogin, $userName, $userEmail, $userHp, $userPwd, 
			$userDescription, $userCustomData, $userAccessLevel) = $result[0];
		
		$sqlValues = array();
		
		// Далее выполняется проверка заданных значений полей для обновления таблицы. 
		// Для каждого поля проверяется определённость в массиве $_newValues и отличие 
		// от текущего значения в БД. В случае если задано новое значение, добавляются 
		// соответствующие инструкции в формируемый SQL запрос
		
		// Логин
		// Корректировка пользовательского логина требует проверки существования
		// других пользовательских записей с аналогичными значениями логина. Проверка 
		// будет выполнена только в том случае, если задано новое значение логина. 
		// В противном случае логин вообще не добавляется в SQL запрос и его значение 
		// остаётся прежним
		if(isset($_newValues['login']) && strlen($_newValues['login']) && 
			$_newValues['login'] !== $userLogin) {
			
			$userLogin = $_newValues['login'];
			
			if(!preg_match(REGEXP_LOGIN, $userLogin)) {
				$this->errorMsg = "Incorrect login syntax.";
				return false;
			}
			
			// Проверка существования полльзователя с заданным логином
			$sqlRequest = "SELECT user_id FROM ".$this->tblUsers." WHERE login = '".$userLogin."'";
			$result = $this->Execute($sqlRequest, 
				'Проверка существования полльзователя с заданным логином');
			if(!$result) return false;
			
			if($result->RecordCount()) {
				// Пользователь с заданным логином уже зарегистрироан
				$this->errorMsg = "Another user with specified login already registered.";
				return false;
			}
			
			$sqlValues['login'] = 'login = "'.addslashes($userLogin).'"';
			
		}
		
		// Пароль
		// Для пароля сравнивается хэш
		if(isset($_newValues['pwd']) && strlen($_newValues['pwd']) && 
			$_newValues['pwd'] != $userPwd) {
			
			$pwd = $_newValues['pwd'];
			
			if(strlen($pwd) < MIN_USER_PWD_LENGTH) {
				$this->errorMsg = "Password must contain at least ".
					MIN_USER_PWD_LENGTH." characters.";
				return false;
			}
			
			if(strlen($pwd) > MAX_USER_PWD_LENGTH) {
				$this->errorMsg = "Password maximum length exceeded.";
				return false;
			}
			
			$sqlValues['pwd'] = 'pwd = "'.md5($pwd).'"';
		}
		
		// Имя
		if(isset($_newValues['name']) && strlen($_newValues['name']) && 
			$_newValues['name'] != $userName) {
			
			$userName = $_newValues['name'];
			
			if(strlen($name) > MAX_USER_NAME_LENGTH) {
				$this->errorMsg = "User name maximum length limit exceeded.";
				return false;
			}
			
			$sqlValues['name'] = 'name = "'.addslashes($userName).'"';
			
		}
		
		// Email
		if(isset($_newValues['email']) && strlen($_newValues['email']) && 
			$_newValues['email'] != $userEmail) {
			
			$userEmail = $_newValues['email'];
			
			if(!preg_match(REGEXP_EMAIL, $userEmail)) {
				$this->errorMsg = "Incorrect email specified";
				return false;
			}
			
			$sqlValues['email'] = 'email = "'.addslashes($userEmail).'"';
			
		}
		
		// Homepage
		if(isset($_newValues['hp']) && strlen($_newValues['hp']) && 
			$_newValues['hp'] != $userHp) {
			
			// Если URL задан в сокращённом виде, в начало будет добавлено http://
			$userHp = $this->CorrectUrl($_newValues['hp']);
			
			if(!strlen($userHp) > MAX_USER_HP_LENGTH) {
				$this->errorMsg = "Homepage URL maximum length exceeded.";
				return false;
			}
			
			$sqlValues['hp'] = 'hp = "'.addslashes($userHp).'"';
			
		}
		
		// Access level
		// Уровень доступа может быть изменён только теми пользователями, кому разрешено 
		// управлять пользовательскими аккаунтами. Обычный зарегистрированный пользователь 
		// может редактировать все свои пользовательские данные, кроме уровня доступа.
		if($this->CanManageUsers() && isset($_newValues['access_level']) && 
			$_newValues['access_level'] != $userAccessLevel) {
			
			// Управлять правами доступа может только супервизор (пользователь, обладающий 
			// правом полного контроля над профилями)
			if($_userId == 0) {
				// Права главного администратора не могут быть изменены, даже если
				// они определены в параметрах метода
				
				$accessLevel = ACCESS_GOD;
				
			} else {
				
				$accessLevel = $_newValues['access_level'];
				
				// Проверка корректности значения access_level
				if(!is_numeric($accessLevel)) {
					$this->errorMsg = "Incorrect access level specified.";
					return false;
				}
				
			}
			
			$sqlValues['access_level'] = 'access_level = '.$accessLevel;
		}
		
		// Description
		if(isset($_newValues['description']) && strlen($_newValues['description']) && 
			$_newValues['description'] != $userDescription) {
			
			$description = $_newValues['description'];
			$cachedDescription = addslashes($this->FilterText($description, FILTERS_DESC));
			
			// Длина текстового описания проверяется после обработки фильтрами 
			// и функцией addslashes
			if(strlen($cachedDescription) > MAX_USER_DESC_LENGTH) {
				$this->errorMsg = "User description maximum length limit exceeded.";
				return false;
			}
			
			// В базе сохраняется обработанная фильтрами копия описания пользователя.
			// Это неободимо для того, чтобы не приходилось повторно обрабатывать текс
			// при каждом запросе (кэш)
			$sqlValues['description'] = 'description = "'.addslashes($description).'"';
			$sqlValues['cached_description'] = 'cached_description = "'.$cachedDescription.'"';
			
		}
		
		// Custom data
		// Дополнительные пользовательские данные хранятся в БД в виде сериализованного 
		// массива. $_newValues['custom_data'] - обычный массив. Новое и старое значения 
		// custom_data сравниваются в сериализованном виде, как строки
		if(isset($_newValues['custom_data'])) {
			
			$customData = serialize($_newValues['custom_data']);
			
			if($customData != $userCustomData) {
				
				$customData = addslashes($customData);
				
				if(strlen($customData) > MAX_USER_CUSTOM_DATA_LENGTH) {
					$this->errorMsg = "User custom data maximum size limit exceeded.";
					return false;
				}
				
				$sqlValues['custom_data'] = "custom_data = '".$customData."'";
				
			}
		}
		
		// Проверка параметров выполнена. Пользователь с новым логином 
		// (если он задан) не существует
		
		// Если заданы новыезначения для каких-либо полей
		if(count($sqlValues)) {
			// Обновляем пользовательские данные в таблице users
			$sqlRequest = "UPDATE ".$this->tblUsers." SET ".
				implode(', ', $sqlValues)." WHERE user_id = ".$_userId;
			$result = $this->Execute($sqlRequest, 'Обновляем пользовательские данные');
			if(!$result) return false;
		}
		
		// Обновляем пользовательские данные в таблицах постов и комемнтариев
		$result = $this->UpdateCachedUserData($_userId, $userLogin, $userName, 
			$userEmail, $userHp);
		if(!$result) return false;
		
		return true;
		
	}
	
	/**
	 * Выполняет удаление пользовательского профиля. После удаления пользователя, 
	 * для всех его постов в таблице posts, поле user_id принмает значение -1. Данные 
	 * о пользователе сохраняются в записи каждого поста в виде сериализованного 
	 * ассоциативного массива, хранимого в поле cached_author. В таблице comments 
	 * для всех комментариев удаляемого пользователя поле user_id принимает значение -1, 
	 * а поля author_* заполняются актуальными на момент удаления профиля пользовательсими 
	 * данными.
	 * 
	 * @param int $_userId user_id удаляемого пользователя
	 */
	function DeleteUser($_userId) {
		
		// Проверяем наличие прав у текущего пользователя для выполнения действия
		if(!$this->CanManageUsers()) {
			$this->errorMsg = "User have no access rights to delete user profiles.";
			return false;
		}
		
		// Проверяем параметр
		if(!is_numeric($_userId)) {
			$this->errorMsg = "Incorrect user ID specified.";
			return false;
		}
		
		// Считываем пользовательские данные
		$sqlRequest = "SELECT * FROM ".$this->tblUsers." WHERE user_id = ".$_userId;
		$result = $this->Execute($sqlRequest);
		if(!$result) return false;
		
		// Проверяем существование удаляемого пользователя
		if(!$result->RecordCount()) {
			$this->errorMsg = "User doesn't exists.";
			return false;
		}
		
		$result = $result->GetArray();
		$user = $result[0];
		
		// Обновляем пользовательские данные в таблицах постов и комемнтариев
		$result = $this->UpdateCachedUserData($_userId, $user['login'],  $user['name'], 
			$user['email'], $user['hp'], true);
		if(!$result) return false;
		
		// Удаляем пользователя с заданным ID
		$sqlRequest = "DELETE FROM ".$this->tblUsers." WHERE user_id = ".$_userId;
		$result = $this->Execute($sqlRequest);
		if(!$result) return false;
		
		return true;
		
	}
	
	/**
	 * Обновляет значения полей cached_author в таблице posts и author_* в таблице 
	 * comments на актуальные. posts.cached_author содержит сериализованный ассоциативный 
	 * массив с пользовательскими данными. comments.author_* - пользвоательские данные, 
	 * хранимые в виде отдельных полей.
	 * Метод предназначен только для использования внутри класса (применяется при работе 
	 * методов ChangeUser и DeleteUser)
	 *
	 * @param int $_userId user_id пользователя, чьи посты и комментарии обновляются
	 * @param string $_userName Новое значение имени пользователяя
	 * @param string $_userEmailНовое значение email'а пользователя
	 * @param string $_userHp Новое значение URL домашней страницы пользователя
	 * @param bool $_resetUserId (опциональный параметр) Если присвоить этому параметру 
	 * значение true, user_id во всех записях будут сброшены (им будет присвоено значение -1). 
	 * Это действие применяется при удалении пользовательского профиля из БД.
	 * @return bool true/false, в зависимости от успешности выполняемого действию
	 * @access private
	 */
	function UpdateCachedUserData($_userId, $_userLogin, $_userName, $_userEmail, $_userHp, $_resetUserId = false) {
		// Массив с сокращёнными пользовательскими данными для обновления записей 
		// таблицы posts
		$cachedAuthor = addslashes(serialize(array(
				'login' => $_userLogin,	
				'name' => $_userName,
				'email' => $_userEmail,
				'hp' => $_userHp
			)));
		
		// Опциональное сбрасывание значений user_id для постов и комментариев
		$sqlUserId = $_resetUserId?'user_id = -1, ':'';
		
		// Обновляем таблицу постов: во всех записях, где user_id == ID удаляемого пользователя, 
		// заменяем это значение на -1 и обновляем поле cached_author на актуальное значение 
		// пользовательских данных на момент удаления его профиля (сериализованный ассоциативный 
		// массив с полями name, email и hp)
		$sqlRequest = "UPDATE ".$this->tblPosts." SET ".$sqlUserId.
			" cached_author = '".$cachedAuthor."' WHERE user_id = ".$_userId;
		$result = $this->Execute($sqlRequest, 'Обновляем таблицу постов');
		if(!$result) return false;
		
		// Обновляем таблицу комментариев: во всех записях, где user_id == ID удаляемого 
		// пользователя, user_id присваивается значение -1, а полям author_* - значение 
		// соответствующих пользовательских данных, актуальных на момент удаления профиля
		$sqlRequest = "UPDATE ".$this->tblComments." SET ".$sqlUserId.
			" author_name = '".addslashes($_userName)."', author_email = '".
			addslashes($_userEmail)."', author_hp = '".addslashes($_userHp).
			"' WHERE user_id = ".$_userId;
		$result = $this->Execute($sqlRequest, 'Обновляем таблицу комментариев');
		if(!$result) return false;
		
		return true;
		
	}
	
	/**
	 * Выдаёт список всех пользователей (все данные, кроме паролей)
	 * @param string $_orderBy Кретерий сортировки пользователей. Возможные 
	 * значения: login (используется по-умолчанию), name.
	 * @return mixed Массив с пользовательскими данными или false, в случае ошибки
	 */
	function GetUsers($_orderBy = 'login') {
		
		// Определение кретерия сортировки
		if(in_array($_orderBy, array('login', 'name'))) {
			$orderBy = " ORDER BY ".$_orderBy." DESC";
		} else {
			$orderBy = '';
		}
		
		// Считываем пользовательские данные.
		$sqlRequest = "SELECT * FROM ".$this->tblUsers.$orderBy;
		$result = $this->Execute($sqlRequest, "Считываем пользовательские данные.");
		if(!$result) return false;
		
		$users = array();
		
		foreach($result->GetArray() as $nextUser) {
			$user = array(
					'user_id' => $nextUser['user_id'],
					'login' => stripslashes($nextUser['login']),
					'name' => stripslashes($nextUser['name']),
					'email' => stripslashes($nextUser['email']),
					'hp' => stripslashes($nextUser['hp']),
					'access_level' => $nextUser['access_level'],
					'posts_cnt' => '0',
					'com_p_cnt' => '0',
					'com_r_cnt' => '0'
				);
			
			// Оставляем актуальные значения счётчиков
			if($this->userAccessLevel & AR_SUPERVISING || $this->userId == $nextUser['user_id']) {
				
				// Для супервизора отображается полное количество постов. 
				// Для зарегистрированных пользователе отображается полное 
				// количество постов только для своего собственного аккаунта
				$user['posts_cnt'] = $nextUser['cached_post_cnt_total'];
				$user['com_p_cnt'] = $nextUser['cached_com_p_cnt_total'];
				$user['com_r_cnt'] = $nextUser['cached_com_r_cnt_total'];
				
			} else {
				
				if($this->userId > -1) {
					$user['posts_cnt'] = $nextUser['cached_post_cnt_reg'];
				} else {
					$user['posts_cnt'] = $nextUser['cached_post_cnt'];
				}
				
				$user['com_p_cnt'] = $nextUser['cached_com_p_cnt'];
				$user['com_r_cnt'] = $nextUser['cached_com_r_cnt'];
				
			}
			
			$users[] = $user;
			
		}
		
		return $users;
		
	}
	
	/**
	 * Метод обновляет значения счётчиков постов для заданной категории. 
	 * Счётчики постов хранятся в категориях для того, чтобы не приходилось каждый 
	 * раз заново расчитывать их значения, когда в них возникает необходимость 
	 * (кэширование данных).
	 * В базе данных существует три поля, в которых хранится количество постов, 
	 * видимое для пользователей с разным уровнем доступа: cached_cnt - количество 
	 * постов, видимых для анонимных пользоватлеей; cached_cnt_reg - количество 
	 * постов, видимых для зарегистрированных пользователей после аутентификации, 
	 * cached_cnt_total - реальное (полное) количество постов в категории, которое 
	 * отображается для пользователей с правами супервизора.
	 * Методы, отдающие данные категорий, всегда выдают только одно значение счётчика 
	 * постов, которое актуально для текущего пользователя.
	 * Метод предназначен для использования только внутри класса. Автоматически вызывает 
	 * после выполнения операций над постами, которые могут повлиять на изменение 
	 * значений счётчиков.
	 *
	 * @access private
	 * @param int $_catId cat_id категории, для которой необходимо заново расчитать 
	 * значения счётчиков.
	 * @return bool true/false, в зависимости от успешности выполнения действия
	 */
	function UpdateCatCount($_catId) {
		$sqlRequest = "UPDATE ".$this->tblCats." SET ".
			"cached_cnt = (SELECT COUNT(*) FROM ".$this->tblPosts." WHERE (cat_id = ".$_catId.
			") AND (status = ".PS_PUBLIC.")), cached_cnt_reg = (SELECT COUNT(*) FROM ".
			$this->tblPosts." WHERE (cat_id = ".$_catId.") AND (status = ".PS_PUBLIC.
			" OR status = ".PS_COMMUNITY.")), cached_cnt_total = (SELECT COUNT(*) FROM ".
			$this->tblPosts." WHERE cat_id = ".$_catId.") WHERE cat_id = ".$_catId;
		$result = $this->Execute($sqlRequest, "Обновляем счётчики постов в категории ".$_catId);
		if(!$result) return false;
		
		return true;
		
	}
	
	/**
	 * Метод обновляет значения счётчиков комментариев для заданного поста.
	 * У каждого поста есть два счётчика комментариев. Им соответстьвуют поля БД 
	 * cached_com_cnt и сached_com_cnt_total. В первом поле хранится количество постов, 
	 * которые видня всем (а том числе анонимным) пользователям. Во втором поле хранится 
	 * полное количество комментариев, включая скрытые (hidden = 1) и непроверенные 
	 * модератором (moderated = 0). Значение второго счётчика отображается дл япользователей, 
	 * у которых есть права модератора и/или супервизора. Для всех остальных (анонимов 
	 * и зарегистрирвоанных пользователй без перечисленных выше прав) отображается значение 
	 * счётчика cached_com_cnt.
	 * Метод предназначен только для внутреннего использования в BlogDB
	 *
	 * @access private
	 * @param int $_postId post_id поста, для которого необходимо обновить значения 
	 * счётчиков комментариев
	 * @return bool true/false, в зависимости от успешности выполнения действия
	 */
	function UpdateCommentsCount($_postId) {
		
		// Проверка корректности параметра
		if(!is_numeric($_postId)) {
			$this->errorMsg = 'Incorrect user ID specified.';
			return false;
		}
		
		// Обновляем счётчик комментов в посте
		$sqlRequest = "UPDATE ".$this->tblPosts." SET cached_com_cnt = ".
			"(SELECT COUNT(*) FROM ".$this->tblComments." WHERE (post_id = ".
			$_postId.") AND (hidden = 0) AND (moderated = 1)), cached_com_cnt_total = ".
			"(SELECT COUNT(*) FROM ".$this->tblComments." WHERE post_id = ".$_postId.
			") WHERE post_id = ".$_postId." LIMIT 1";
		$result = $this->Execute($sqlRequest, "Обновляем счётчик комментов в посте");
		if(!$result) return false;
		
		return true;
		
	}
	
	/**
	 * Возвращает данные заданного пользователя.
	 * 
	 * @param mixed $_userId user_id или login нужного поьзователя. По типу этого 
	 * параметра будет определён критерий выборки пльзователя из БД. Если необходимо 
	 * по логину считать данные пользователя, у которого логин состоит только из цифр, 
	 * необходимо явно указать тип параметра.
	 * @return mixed Возвращает ассоциативный массив с пользовательскими данными 
	 * или (bool)false, в случае ошибки
	 */
	function GetUser($_userId) {
		
		// Проверка корректности параметра
		
		if(!(is_numeric($_userId) || preg_match(REGEXP_LOGIN, $_userId))) {
			$this->errorMsg = "Incorrect method parameter specified.";
			return false;
		}
		
		// Считываем юзера
		if(is_string($_userId)) {
			$where = 'login = "'.addslashes($_userId).'"';
		} else {
			$where = 'user_id = '.$_userId;
		}
		
		$sqlRequest = 'SELECT * FROM '.$this->tblUsers.' WHERE '.$where.' LIMIT 1';
		$result = $this->Execute($sqlRequest, "Считываем юзера");
		if(!$result) return false;
		
		// Проверяем существование нужного пользователя
		if(!$result->RecordCount()) {
			$this->errorMsg = "User doesn't exists.";
			return false;
		}
		
		$result = $result->GetArray();
		$user = $result[0];
		
		// Убираем лишние данные
		foreach($user as $field => $value) {
			if(is_numeric($field)) {
				unset($user[$field]);
			} else {
				$user[$field] = stripslashes($value);
			}
		}
		
		// Значение переменная определяет то, что текущий профиль 
		// принадлежит текущему пользователю
		$cur = ($this->userId === $_userId) || 
			(is_string($_userId) && $this->userLogin === $_userId);
		
		// Пароль другого пользователя может видеть только пользователь 
		// с правами супервизора. Свой собственный пароль может видеть любой 
		// зарегистрированный пользователь. То же касается email'а
		if(!(((int)$this->userAccessLevel & AR_SUPERVISING) || $cur)) {
			
			// Убираем из данных пароль
			unset($user['pwd']);
			unset($user['email']);
			
		}
		
		// Оставляем актуальные значения счётчиков
		if($this->userAccessLevel & AR_SUPERVISING || $cur) {
			
			$user['posts_cnt'] = $user['cached_post_cnt_total'];
			$user['com_p_cnt'] = $user['cached_com_p_cnt_total'];
			$user['com_r_cnt'] = $user['cached_com_r_cnt_total'];
			
		} else {
			
			if($this->userId > -1) {
				$user['posts_cnt'] = $user['cached_post_cnt_reg'];
			} else {
				$user['posts_cnt'] = $user['cached_post_cnt'];
			}
			
			$user['com_p_cnt'] = $user['cached_com_p_cnt'];
			$user['com_r_cnt'] = $user['cached_com_r_cnt'];
			
		}
		
		// Убираем лишние данные
		unset($user['cached_post_cnt_total']);
		unset($user['cached_com_p_cnt_total']);
		unset($user['cached_com_r_cnt_total']);
		
		$user['custom_data'] = @unserialize($user['custom_data']);
		
		return $user;
		
	}
	
	/**
	 * выполняет корректировку URL: если адрес задан в сокращённом виде, 
	 * в начало добавляется "http://"
	 *
	 * @param string $_url URL для корректировки
	 * @param string Откорректированный URL
	 */
	function CorrectUrl($_url) {
		
		if(!$_url) return '';
		$url = trim($_url);
		$parts = parse_url($url);
		return (!isset($parts['scheme']) || !$parts['scheme'])?("http://".$url):$url;
		
	}
	
	/**
	 * Определяет право текущего пользователя модерировать комментарии.
	 *
	 * @return bool true/false, в зависимости от того, можно или нет
	 */
	function CanApproveComments() {
		return (bool)($this->userAccessLevel & AR_MODERATION);
	}
	
	/**
	 * Устанавливает комментарию с заданным ID статус moderated.
	 *
	 * @param int $_commentId comment_id модерируемого комментария.
	 * @return bool tru/false, в зависимости от успешности 
	 * выполненного действия
	 */
	function ApproveComment($_commentId) {
		
		// Проверяем права пользователя на модерирование коммента
		if(!$this->CanApproveComments()) {
			$this->errorMsg = 'User have no access rights to moderate comments.';
			return false;
		}
		
		// Считываем коммент для того, чтобы узнать проверить его существование и post_id
		$com = $this->GetComment($_commentId);
		if(!$com) return false;
		// (сообщение об ошибке, при возникновении таковой, генерируемое методом 
		// GetComment, остаётся акуальным и при работе текущего метода, поэтому менять 
		// его не требуется)
		
		// Обновляем данные коммента в БД
		$sqlRequest = "UPDATE ".$this->tblComments." SET moderated = 1".
			" WHERE comment_id = ".$_commentId;
		$result = $this->Execute($sqlRequest, 'Обновляем статус moderated для комментария');
		if(!$result) return false;
		
		// Обновляем счётчики комментов поста
		$this->UpdateCommentsCount($com['post_id']);
		if(!$result) return false;
		
		
		// Обновляем счётчики комментариев в таблице пользователей
		// для автора комментария и автора комментируемого поста
		$result = $this->UpdateUserComCounters($com['user_id']);
		if(!$result) return false;
		
		$result = $this->UpdateUserComCounters($com['post_author_id']);
		if(!$result) return false;
		
		return true;
		
	}
	
	/**
	 * Метод определяет право пользователя на работу с резервными копиями 
	 * данных из базы (создание и удаление, восстановление данных).
	 */
	function CanManageBackups() {
		return (bool)($this->userAccessLevel & AR_SUPERVISING);
	}
	
	/**
	 * Контролирует, чтобы файловый путь заканчивался слешем.
	 *
	 * @param string $_path Путь
	 */
	function SafePath($_path) {
		$path = trim($_path);
		return ($path[strlen($path) - 1] == '/')?$path:($path.'/');
	}
	
	/**
	 * Сохраняет таблицу в файл c аналогичным именем.
	 *
	 * @param string $_table Имя таблицы, которую необходимо сохранить в файл.
	 * @param string $_path Имя директории, в которой будет сохранён файл.
	 * @return bool true/false, в зависимости от успешности выполнения действия
	 * @access private
	 */
	function TableToFile($_table, $_path) {
		// Проверяем уровень доступа пользователя
		if(!$this->CanManageBackups()) {
			$this->errorMsg = 'User have no access rights to manage backups.';
			return false;
		}
		
		// Считываем все данные из таблицы
		$sqlRequest = "SELECT * FROM ".addslashes($_table);
		$result = $this->Execute($sqlRequest);
		$result = $result->GetArray();
		
		// Удаляем лишние данные
		foreach($result as $num => $record) {
			
			foreach($record as $field => $value) 
				if(is_numeric($field)) unset($record[$field]);
			
			$result[$num] = $record;
			
		}
		
		// Сохраняем данные в файл
		$fileName = $this->SafePath($_path).$_table.'.txt';
		$fh = fopen($fileName, 'w');
		if(!$fh) {
			$this->errorMsg = 'Error opening file: '.$fileName;
			return false;
		}
		
		$data = serialize($result);
		
		fwrite($fh, $data);
		fclose($fh);
		
		return strlen($data);
		
	}
	
	/**
	 * Сохраняет каждую запись заданной таблицы в отдельный файл. Файлам задаются 
	 * имена, соответствующие значениям заданного поля (для этого может использоваться 
	 * любое ключевое поле).
	 * 
	 * @param string $_table Имя таблицы, которую необходимо сохранить в файлы.
	 * @param string $_path Имя директории, в которой будет сохранены данные (внутри 
	 * этой директории будет создана поддиректория с именем таблицы, в которой будут 
	 * хранится файлы-записи).
	 * @param string $_field (опциональный параметр) Имя поля, значения которого будут 
	 * использоватся для задания имён файлов. Если параметр не задан, используется 
	 * первое поле.
	 * @return bool true/false, в зависимости от успешности выполнения действия
	 * @access private
	 */
	function TableToFiles($_table, $_path, $_field) {
		// Проверяем уровень доступа пользователя
		if(!$this->CanManageBackups()) {
			$this->errorMsg = 'User have no access rights to manage backups.';
			return false;
		}
		
		// Считываем все данные из таблицы
		$sqlRequest = "SELECT * FROM ".addslashes($_table);
		$result = $this->Execute($sqlRequest);
		$result = $result->GetArray();
		
		// Создаём директорию
		$path = $this->SafePath($_path).$_table;
		$r = mkdir($path);
		if(!$r) {
			$this->errorMsg = 'Error creating directory: '.$path;
			return false;
		}
		
		$weight = 0;
		
		// Сохраняем данные в файлы
		foreach($result as $record) {
			
			// Удаляем лишние данные
			foreach($record as $field => $value) 
				if(is_numeric($field)) unset($record[$field]);
			
			// Проверка существования ключевого поля
			if(!isset($record[$_field])) {
				$this->errorMsg = 'Incorrect field specified.';
				return false;
			}
			
			$fileName = $path.'/'.$record[$_field].'.txt';
			$fh = fopen($fileName, 'w');
			if(!$fh) {
				$this->errorMsg = 'Error opening file: '.$fileName;
				return false;
			}
			
			$data = serialize($record);
			$weight += strlen($data);
			
			fwrite($fh, $data);
			fclose($fh);
		}
		
		return $weight;
		
	}
	
	/**
	 * Создаёт бэкап - директорию с имемем DIR_DATA/backup/<timestamp> 
	 * (<timestamp> - дата и время создания бэкапа в формате YYYY-MM-DD-HH-MM-SS). 
	 * В директории сохранчяется содержимое всех таблиц БД. Посты сохраняются в виде 
	 * отдельных файлов, все остальные данные - как одинарные файлов.
	 * 
	 * @return bool true/false, в зависимости от успешности выполнения действия
	 */
	function Backup() {
		// Проверяем уровень доступа пользователя
		if(!$this->CanManageBackups()) {
			$this->errorMsg = 'User have no access rights to manage backups.';
			return false;
		}
		
		// Создаём директорию для нвого бэкапа
		$ts = time();
		$backupPath = DIR_DATA.'backup/'.date("Y-m-d-H-i-s", $ts);
		$r = mkdir($backupPath);
		if(!$r) {
			$this->errorMsg = 'Error creating directory: '.$backupPath;
			return false;
		}
		
		// Массив таблиц, которые будут сохранены в виде единых файлов
		$tables = array($this->tblUsers, $this->tblCats, $this->tblTags, 
			$this->tblTagsMap, $this->tblComments);
		
		// Счётчик общего объёма бэкапа
		$weight = 0;
		
		// Последовательно сохраняем данные всех таблиц в эту директорию
		foreach($tables as $table) {
			$result = $this->TableToFile($table, $backupPath);
			if($result === false) return false;
			$weight += $result;
		}
		
		// Данные таблицы posts сохраняются в виде отдельных файлов
		$result = $this->TableToFiles($this->tblPosts, $backupPath, 'post_id');
		if($result === false) return false;
		$weight += $result;
		
		// Обновляем значение индекса бэкапов
		$result = $this->UpdateBackupIndex();
		if(!$result) return false;
		
		return true;
		
	}
	
	/**
	 * Очищает всё содержимое таблиц БД и заполняет их данными из заданного бэкапа.
	 *
	 * @param int $_time Дата бэкапа, из которого необходимо восстановить 
	 * содержимое БД.
	 * @return bool true/false, в зависимости от успешности выполнения действия
	 */
	function Restore($_time) {
		// Проверяем уровень доступа пользователя
		if(!$this->CanManageBackups()) {
			$this->errorMsg = 'User have no access rights to manage backups.';
			return false;
		}
		
		$backupPath = DIR_DATA.'backup/'.date("Y-m-d-H-i-s", $_time);
		
		// Проверяем существование бэкапа с заданным именем
		if(!is_dir($backupPath)) {
			$this->errorMsg = "Required backup doesn't exists.";
			return false;
		}
		
		// Массив таблиц, которые будут восстановлены из единых файлов
		$tables = array($this->tblUsers, $this->tblCats, $this->tblTags, 
			$this->tblTagsMap, $this->tblComments);
		
		$result = true;
		
		// Очищаем таблицы и заполняем их восстановленными данными
		foreach($tables as $table) {
			// Очищаем таблицу
			$sqlRequest = "DELETE FROM ".$table;
			$result = $this->Execute($sqlRequest);
			if(!$result) return false;
			
			// Считываем данные из быкапа
			$data = unserialize(file_get_contents($backupPath.'/'.$table.'.txt'));
			
			// Добавляем данные в базу
			foreach($data as $record) {
				$sqlRequest = $this->ArrayToSqlInsert($table, $record);
				$result = $this->Execute($sqlRequest, "Добавляем запись в таблицу");
				if(!$result) return false;
				
			}
			
		}
		
		// Очищаем таблицу постов
		$sqlRequest = "DELETE FROM ".$this->tblPosts;
		$result = $this->Execute($sqlRequest);
		if(!$result) return false;
			
		// Восстанавливаем посты из отдельных файлов
		$postsPath = $backupPath.'/'.$this->tblPosts;
		$dh = opendir($postsPath);
		foreach(glob($postsPath.'/*.txt') as $nextFile) {
			$record = unserialize(file_get_contents($nextFile));
			// Формируем SQL запрос
			$sqlRequest = $this->ArrayToSqlInsert($this->tblPosts, $record);
			$result = $this->Execute($sqlRequest, "Добавляем запись в таблицу");
			if(!$result) return false;
			
		}
		
		closedir($dh);
		
		return true;
		
	}
	
	/**
	 * Генерирует INSERT SQL запрос. Метод предназначен ТОЛЬКО для 
	 * внутреннего использования в классе. Используется методом Restore.
	 *
	 * @param string $_table Имя таблицы, в которую добавляются данные
	 * @param array $_values Ассоциативный массив с названиями полей 
	 * и соответствующими им значениями (<field> => <value>)
	 * @return string Сгенерированный SQL запрос
	 * @access private
	 */
	function ArrayToSqlInsert($_table, $_values) {
		
		// Проверка корректности параметров
		if(!strlen($_table) || !count($_values)) return false;
		
		// Формируем SQL запрос
		$values = array();
		// Заключаем в кавычки строковые значения полей
		foreach($_values as $value) {
			$values[] = (is_numeric($value))?$value:('"'.addslashes($value).'"');
		}
		
		// Добавляем запись в таблицу
		$sqlRequest = "INSERT INTO ".$_table." (".implode(", ", array_keys($_values)).") VALUES (".
			implode(", ", $values).")";
		
		return $sqlRequest;
	}
	
	/**
	 * Удяляет заданный бэкап (стирает все относящиеся к нему файлы).
	 *
	 * @param int $_time Дата бэкапа, который необходимо удалить.
	 * @return bool true/false, в зависимости от успешности выполнения действия
	 */
	function DeleteBackup($_time) {
		// Проверяем уровень доступа пользователя
		if(!$this->CanManageBackups()) {
			$this->errorMsg = 'User have no access rights to manage backups.';
			return false;
		}
		
		// Удаляем все файлы и директорию бэкапа
		$result = killDir(DIR_DATA.'backup/'.date("Y-m-d-H-i-s", $_time));
		
		if(!$result) {
			$this->errorMsg = "Error deleting backup files. Probably backup doesn't exists.";
			return false;
		}
		
		// Обновляем значение индекса бэкапов
		$result = $this->UpdateBackupIndex();
		if(!$result) return false;
		
		return true;
		
	}
	
	/**
	 * Возвращает список существующих бэкапов.
	 *
	 * @return array Массив существующих на момент вызова метода бэкапов,
	 * в формате <timestamp> => <weight>, где <timestamp> - дата генерации бэкапа,
	 * а <weight> - объём данных
	 */
	function GetBackupList() {
		$index = @unserialize(file_get_contents(DIR_DATA.'backup/index.txt'));
		return is_array($index)?$index:array();
	}
	
	/**
	 * Обновляет индексный файл бэкапов, к котором хранится сериализованный массив
	 * в формате <timestamp> => <weight>, где <timestamp> - дата генерации бэкапа,
	 * а <weight> - объём данных.
	 * Метод предназначен ТОЛЬКО для внутреннего использования в классе. Используется 
	 * методами Backup и DeleteBackup.
	 *
	 * @return bool true/false, в зависимости от того, получилось или нет
	 * @access private
	 */
	function UpdateBackupIndex() {
		
		$backups = array();
		
		// Обновляем значение индекса бэкапов
		foreach(glob(DIR_DATA.'backup/*') as $backupDir) {
			$ts = array();
			if(!is_dir($backupDir) || 
				!preg_match("/(\d\d\d\d)-(\d\d)-(\d\d)-(\d\d)-(\d\d)-(\d\d)/", $backupDir, $ts)) continue;
			
			$ts = mktime($ts[4], $ts[5], $ts[6], $ts[2], $ts[3], $ts[1]);
			$backups[$ts] = dirSize($backupDir);
		}
		
		// Сохраняем списк бэкапов в файл
		$fh = fopen(DIR_DATA.'backup/index.txt', 'w');
		if(!$fh) {
			$this->errorMsg = 'Error openeing backup index file.';
			return false;
		}
		
		fwrite($fh, serialize($backups));
		if(!$fh) {
			$this->errorMsg = 'Error saving backup index.';
			return false;
		}
		
		fclose($fh);
		
		return true;
		
	}
	
	/**
	 * Метод расчитывает значение счётчиков постов для записи в таблице пользователей.
	 * Существует три счётчика постов: cached_post_cnt, cached_post_cnt_reg, cached_post_cnt_total. 
	 * Каждое значение видимо соответственно анонимам, зарегистрированным пользователям 
	 * и супервизорам. Первое значение образует количество всех постов со статусом public, 
	 * второе - со статусом public и community, третье - общее количество пстов в базе.
	 * Метод предназначен только для внутреннего использования в классе. Вызывается из методов 
	 * AddPost, DeletePost, ChangePost.
	 *
	 * @param int $_userId user_id пользователя, для которого необходимо расчитать значения 
	 * счётчиков постов
	 * @return bool true/false, в зависимости от успешности выполнение действия
	 * @access private
	 */
	function UpdateUserPostCounters($_userId) {
		
		// В случае, если метод вызван для анонимного пользователя, данные обновляться 
		// не будут, т.к. пользователь абстрактный и ему не соответствует запись в базе
		if($_userId == -1) return true;
		
		// Проверка корректности параметра
		if(!is_numeric($_userId)) {
			// Для отрицательных чисел is_numeric() тоже возвращает true
			$this->errorMsg = 'Incorrect user ID specified.';
			return false;
		}
		
		// Обновляем счётчики постов для заданного пользователя
		$sqlRequest = "UPDATE ".$this->tblUsers." SET cached_post_cnt = (SELECT COUNT(*) FROM ".
			$this->tblPosts." WHERE (user_id = ".$_userId.") AND (status = ".PS_PUBLIC.
			")), cached_post_cnt_reg = (SELECT COUNT(*) FROM ".$this->tblPosts.
			" WHERE (user_id = ".$_userId.") AND (status = ".PS_PUBLIC." OR status = ".
			PS_COMMUNITY.")), cached_post_cnt_total = (SELECT COUNT(*) FROM ".$this->tblPosts.
			" WHERE user_id = ".$_userId.") WHERE user_id = ".$_userId;
		$result = $this->Execute($sqlRequest, 
			"Обновляем счётчики постов для пользователя с user_id = ".$_userId);
		if(!$result) return false;
		
		return true;
		
	}
	
	/**
	 * Метод обновляет значения счётчиков комментариев в таблице пользователей для заданной 
	 * записи. Существует четыре счётчика комментариев: cached_com_p_cnt, cached_com_p_total, 
	 * cached_com_r_cnt и cached_com_r_total. Они хранят количество количество написанных 
	 * комментариев (поля с суффиксом _p_) и полученных комментариев (поля с суффиксом _r_).
	 * В полях с суффиксом _total хранятся полные значения количества комментариев, в то время 
	 * как в полях без этого суффикса - количество только тех из них, которые проверены 
	 * модератором и не являются скрытыми.
	 * Метод предназначен только для внутреннего использования в классе. Вызывается из методов 
	 * AddComment, DeleteComment, ApproveComment, ChangeComment, DeletePost.
	 *
	 * @param int $_userId user_id пользователя, для которого необходимо расчитать значения 
	 * счётчиков комментариев
	 * @return bool true/false, в зависимости от успешности выполнение действия
	 * @access private
	 */
	function UpdateUserComCounters($_userId) {
		
		// В случае, если метод вызван для анонимного пользователя, данные обновляться 
		// не будут, т.к. пользователь абстрактный и ему не соответствует запись в базе
		if($_userId == -1) return true;
		
		// Проверка корректности параметра
		if(!is_numeric($_userId)) {
			$this->errorMsg = 'Incorrect user ID specified.';
			return false;
		}
		
		// Обновляем счётчики комментариев для заданного пользователя
		$sqlRequest = "UPDATE ".$this->tblUsers." SET cached_com_r_cnt = ".
			"(SELECT COUNT(*) FROM ".$this->tblComments." WHERE comment_id IN ".
			"(SELECT comment_id FROM ".$this->tblComments." INNER JOIN ".
			$this->tblPosts." USING (post_id) WHERE ".$this->tblPosts.".user_id = ".
			$_userId." AND ".$this->tblComments.".moderated = 1 AND ".
			$this->tblComments.".hidden = 0)), cached_com_r_cnt_total = ".
			"(SELECT COUNT(*) FROM ".$this->tblComments." WHERE comment_id IN ".
			"(SELECT comment_id FROM ".$this->tblComments." INNER JOIN ".$this->tblPosts.
			" USING (post_id) WHERE ".$this->tblPosts.".user_id = ".$_userId.
			")), cached_com_p_cnt = "."(SELECT COUNT(*) FROM ".$this->tblComments.
			" WHERE user_id = ".$_userId." AND moderated = 1 AND hidden = 0), ".
			"cached_com_p_cnt_total = (SELECT COUNT(*) FROM ".$this->tblComments.
			" WHERE user_id = ".$_userId.") ".
			"WHERE user_id = ".$_userId;
		$result = $this->Execute($sqlRequest, 
			"Обновляем счётчики комментариев для пользователя с user_id = ".$_userId);
		if(!$result) return false;
		
		return true;
		
	}
	
}

//class Dummy {}

?>