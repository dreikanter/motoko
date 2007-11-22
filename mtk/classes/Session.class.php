<?php

/**
 * Класс-хэндлер PHP сессий. Данные хранятся в базе данных через ADO DB.
 * @package idb
 * @version
 * @copyright
 */
class Session {
	
	/**
	 * Объект класса AdoConnection, используемый для доступа к БД.
	 * @var object
	 * @access private
	 */
	var $dbc = false;
	
	/**
	 * Имя таблицы БД, в которой хранятся данные сессий.
	 * @var string
	 * @access private
	 */
	var $tbl = false;
	
	/**
	 * Дата и время создания текущей сессии (unix timestamp).
	 * @var int
	 * @access private
	 */
	var $cTime = false;
	
	/**
	 * Конструктор
	 * @param object Объект AdoConnection
	 * @param string Имя таблицы БД, в которой будут храниться данные 
	 * сессий.
	 * @param int Время жизни сессий (по-умолчанию используется дефолтное 
	 * для PHP значение).
	 * @return void
	 * @access public
	 */
	function Session(&$_adoDb, $_table, $_expire = false) {
		$dbHandlerClass = get_class($_adoDb);
		if(substr($dbHandlerClass, 0, 5) !== "adodb" &&
			substr($dbHandlerClass, -13) !== "adoconnection") {
			trigger_error("Incorrect database handler.", E_USER_ERROR);
			return false;
		}
		$this->dbc = $_adoDb;
		$this->tbl = $_table;
		session_set_save_handler(
			array(&$this, "sesOpen"),
			array(&$this, "sesClose"),
			array(&$this, "sesRead"),
			array(&$this, "sesWrite"),
			array(&$this, "sesDestroy"),
			array(&$this, "sesGc"));
		
		if(is_numeric($_expire) && $_expire > 0) {
			session_cache_expire($_expire);
		}
	}
	
	/**
	 * Создаёт таблицу, для хранения данных сессий. Используется 
	 * при инсталяции скрипта.
	 * @return bool
	 * @access public
	 */
	function buildTable() {
		$this->dropTable();
		$sqlRequest = "CREATE TABLE IF NOT EXISTS `".$this->tbl."` (
			`sid` CHAR(32) NOT NULL, 
			`atime` TIMESTAMP NOT NULL,
			`ctime` TIMESTAMP NOT NULL,
			`data` TEXT NOT NULL,
			PRIMARY KEY (`sid`));";
		
		$res = $this->dbc->Execute($sqlRequest, 'Создаём таблицу для сессий');
		
		if(!$res) {
			trigger_error($this->dbc->ErrorMsg(), E_USER_ERROR);
			return false;
		}
		
		return true;
	}
	
	/**
	 * Безвозвратно уничтожает таблицу сессий.
	 * @return void
	 * @access public
	 */
	function dropTable() {
		$res = $this->dbc->Execute("DROP TABLE IF EXISTS `".$this->tbl."`", 'Убиваем таблицу сессий');
		
		if(!$res) {
			trigger_error($this->dbc->ErrorMsg(), E_USER_ERROR);
			return false;
		}
		
		return true;
	}
	
	/**
	 * Врапер
	 */
	function sesOpen($_sPath, $_sName) {
		return true;
	}
	
	/**
	 * Врапер
	 */
	function sesClose() {
		return true;
	}
	
	/**
	 * Врапер
	 */
	function sesRead($_sId) {
		// lifetime limitation
		$limit = time() - session_cache_expire() * 60;
		$sqlRequest = "SELECT * FROM `".$this->tbl.
			"` WHERE `sid` = '".$_sId.
			"' AND `atime` > FROM_UNIXTIME('".$limit."') LIMIT 1";
		$res = $this->dbc->Execute($sqlRequest, 'Считываем данные сессии');
		if(!$res) {
			trigger_error($this->dbc->ErrorMsg(), E_USER_ERROR);
			return false;
		}
		
		$res = $res->GetArray();
		
		return isset($res[0]['data'])?$res[0]['data']:false;
	}
	
	/**
	 * Врапер
	 */
	function sesWrite($_sId, $_sData) {
		$sqlRequest = "UPDATE `".$this->tbl."` SET `atime` = NOW(), `data` = (CONVERT('".
			addslashes($_sData)."' USING utf8)) WHERE `sid` = '".$_sId."' LIMIT 1 ;";
		$res = $this->dbc->Execute($sqlRequest);
		if(!$res) {
			trigger_error($this->dbc->ErrorMsg(), E_USER_ERROR);
			return false;
		}
		
		// session record was succesfully updated
		if($this->dbc->Affected_Rows()) {
			$this->firstTime = false;
			return true;
		}
		
		// new session starts
		$this->cTime = time();
		$sqlRequest = "INSERT INTO `".$this->tbl.
			"` (`sid`, `atime`, `ctime`, `data`) VALUES ('".
			$_sId."', FROM_UNIXTIME('".$this->cTime.
			"'), FROM_UNIXTIME('".$this->cTime.
			"'), '".addslashes($_sData)."') ON DUPLICATE KEY UPDATE `".$this->tbl."` SET `atime` = NOW(), `data` = '".
			addslashes($_sData)."' WHERE `sid` = '".$_sId."' LIMIT 1;";
		$res = $this->dbc->Execute($sqlRequest);
		if(!$res) {
			trigger_error($this->dbc->ErrorMsg(), E_USER_ERROR);
			return false;
		}
		
		$this->firstTime = true;
		return true;
		
		/*
		$sData = '"'.addslashes($_sData).'"';
		$this->cTime = time();
		$sqlRequest = "INSERT INTO ".$this->tbl." (sid, atime, ctime, data) VALUES ('".
			$_sId."', FROM_UNIXTIME('".$this->cTime."'), FROM_UNIXTIME('".$this->cTime.
			"'), ".$sData.") ON DUPLICATE KEY UPDATE ".$this->tbl." SET atime = NOW(), data = ".
			$sData." WHERE sid = '".$_sId."' LIMIT 1;";
		show($sqlRequest);
		$res = $this->dbc->Execute($sqlRequest, 'Схраняем данные сессии');
		if(!$res) {
			trigger_error($this->dbc->ErrorMsg(), E_USER_ERROR);
			return false;
		}
		
		return true;
		*/
	}
	
	/**
	 * Врапер
	 */
	function sesDestroy($_sId) {
		$sqlRequest = "DELETE FROM `".$this->tbl.
			"` WHERE `sid`='".$_sId."'";
		$res = $this->dbc->Execute($sqlRequest, 'Уничтожаем сессию');
		if(!$res) {
			trigger_error($this->dbc->ErrorMsg(), E_USER_ERROR);
			return false;
		}
		return true;
	}
	
	/**
	 * Врапер (сборщик мусора)
	 */
	function sesGc($_maxLifeTime) {
		error_log($_maxLifeTime, DIR_LOGS.'debug.log');
		$limit = time() - $_maxLifeTime * 60;
		$sqlRequest = "DELETE FROM `".$this->tbl.
			"` WHERE `atime` < FROM_UNIXTIME(".$limit.");";
		$res = $this->dbc->Execute($sqlRequest, 'Убираем мусор из таблицы сессий');
		if(!$res) {
			trigger_error($this->dbc->ErrorMsg(), E_USER_ERROR);
			return false;
		}
		return true;
	}
}

?>