<?php

/**
 * Модерация комментариев. Выполняется за один запуск (без формы подтверждение. 
 * Подтверждение намерения модерировать коммент осуществляется с помощью JS 
 * (темплейт post).
 * 
 * Параметры: comment_id - id модерируемого комментария
 */
class ApproveCommentAction extends AbstractAction {
	
	var $returnable = false;
	
	function paramsOk() {
		// Параметров должно быть два
		if(!isset($this->params['comment_id']) || !is_numeric($this->params['comment_id'])) {
			$this->errorMsg = "Bad action params.";
			return false;
		}
		
		return true;
		
	}
	
	function execute() {
		global $BDB;
		
		$comentId = $this->params['comment_id'];
		
		// Проверить наличие прав у пользователя выполнять удаление других пользователей
		if(!$BDB->CanApproveComments()) {
			$this->resType = 'error';
			$this->resData = array('error_msg' => 'User has no access rights to moderate comments.');
			return false;
		}
		
		$result = $BDB->ApproveComment($comentId);
		if(!$result) {
			$this->resType = 'error';
			$this->resData = $BDB->errorMsg;
			return;
		}
		
		// Email notification
		if(EMAIL_NOTIFICATION) {
			// Послать сообщение комментатору о том, что коммент был отмодерирован и теперь виден на сайте
			
			$com = $BDB->GetComment($comentId);
			
			// To send HTML mail, you can set the Content-type header.
			$headers = 
				"MIME-Version: 1.0\r\n".
				"Content-type: text/plain; charset=utf-8\r\n".
				"From: ".MAIL_BOT."\r\n";
			
			// recipients
			$to  = $com['author_name']." <".$com['author_email'].">";
			
			// subject
			$subject = "Comment moderation notification";
			
			// message
			$message = "Your comment have been checked by moderator.\n".
				"- Post URL: ".URL_HOST.URL_ROOT."posts/".$com['post_id']."\n".
				"- Moderation time: ".date("Y/m/d @ H:i:s")."\n";
			
			$message .= "\n\n--\n".BLOG_TITLE." / ".URL_HOST.URL_ROOT."\n\n".
				"This is automatically created notification. Please do not reply to this message.";
			
			// and now mail it
			mail($to, $subject, $message, $headers);
			
			// Послать сообщение автору поста о том, что новый комментарий
			
			$post = $BDB->GetPost($com['post_id']);
			
			// To send HTML mail, you can set the Content-type header.
			
			// recipients
			$to  = $post['user_name']." <".$post['user_email'].">";
			
			// subject
			$subject = "New comment notification";
			
			// message
			$message = "You have received a new comment \n  from ".$com['author_name'].
				" (".$com['author_email'].").\n\n".
				"- Post title: ".$post['title']."\n".
				"- Comment publication time: ".date("Y/m/d @ H:i:s")."\n".
				"- Thread URL: ".URL_HOST.URL_ROOT."posts/".$com['post_id']."\n".
				($com['subj']?("- Comment title: ".$com['subj'])."\n\n":"\n").
				$com['content'];
				
			$message .= "\n\n--\n".BLOG_TITLE." / ".URL_HOST.URL_ROOT."\n\n".
				"This is automatically created notification. Please do not reply to this message.";
			
			// and now mail it
			mail($to, $subject, $message, $headers);
			
		}
		
		$this->resType = 'redirect';
		$this->resData = array(
				'to' => 'back',
				'system_message' => 'Comment approved. Now it may be seen <a href="'.URL_ROOT.
					'posts/">here</a> and <a href="'.URL_ROOT.'">here</a>.'
			);
		
		return true;
		
	}

}

?>