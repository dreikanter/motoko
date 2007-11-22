<?php
/**
  V4.65 22 July 2005  (c) 2000-2005 John Lim (jlim@natsoft.com.my). All rights reserved.
  Released under both BSD license and Lesser GPL library license. 
  Whenever there is any discrepancy between the two licenses, 
  the BSD license will take precedence.
	
  Set tabs to 4 for best viewing.
  
  Modified 28 August, 2005 for use with ADOdb Lite by Mark Dickenson
  
*/

// security - hide paths
if (!defined('ADODB_DIR')) die();

class ADODB2_sybase extends ADODB_DataDict {

	var $dbtype = 'sybase';
	var $dropIndex = 'DROP INDEX %2$s.%1$s';
	var $seqField = false;
  	var $metaTablesSQL="select name from sysobjects where type='U' or type='V'";
	// see http://sybooks.sybase.com/onlinebooks/group-aw/awg0800e/dbrfen8/@ebt-link;pt=5981;uf=0?target=0;window=new;showtoc=true;book=dbrfen8
	var $metaColumnsSQL = "SELECT c.column_name, c.column_type, c.width FROM syscolumn c, systable t WHERE t.table_name='%s' AND c.table_id=t.table_id AND t.table_type='BASE'";

 	function ActualType($meta)
	{
		switch(strtoupper($meta)) {
		case 'C': return 'VARCHAR';
		case 'XL':
		case 'X': return 'TEXT';
		
		case 'C2': return 'NVARCHAR';
		case 'X2': return 'NTEXT';
		
		case 'B': return 'IMAGE';
			
		case 'D': return 'DATETIME';
		case 'T': return 'DATETIME';
		case 'L': return 'BIT';
		
		case 'I': return 'INT'; 
		case 'I1': return 'TINYINT';
		case 'I2': return 'SMALLINT';
		case 'I4': return 'INT';
		case 'I8': return 'BIGINT';
		
		case 'F': return 'REAL';
		case 'N': return 'NUMERIC';
		default:
			return $meta;
		}
	}

	function AddColumnSQL($tabname, $flds)
	{
		$tabname = $this->TableName ($tabname);
		$f = array();
		list($lines,$pkey) = $this->_GenFields($flds);
		$s = "ALTER TABLE $tabname $this->addCol";
		foreach($lines as $v) {
			$f[] = "\n $v";
		}
		$s .= implode(', ',$f);
		$sql[] = $s;
		return $sql;
	}

	function AlterColumnSQL($tabname, $flds)
	{
		$tabname = $this->TableName ($tabname);
		$sql = array();
		list($lines,$pkey) = $this->_GenFields($flds);
		foreach($lines as $v) {
			$sql[] = "ALTER TABLE $tabname $this->alterCol $v";
		}
		return $sql;
	}

	function DropColumnSQL($tabname, $flds)
	{
		$tabname = $this->TableName($tabname);
		if (!is_array($flds)) $flds = explode(',',$flds);
		$f = array();
		$s = "ALTER TABLE $tabname";
		foreach($flds as $v) {
			$f[] = "\n$this->dropCol ".$this->NameQuote($v);
		}
		$s .= implode(', ',$f);
		$sql[] = $s;
		return $sql;
	}

	function _CreateSuffix($fname,$ftype,$fnotnull,$fdefault,$fautoinc,$fconstraint)
	{	
		$suffix = '';
		if (strlen($fdefault)) $suffix .= " DEFAULT $fdefault";
		if ($fautoinc) $suffix .= ' DEFAULT AUTOINCREMENT';
		if ($fnotnull) $suffix .= ' NOT NULL';
		else if ($suffix == '') $suffix .= ' NULL';
		if ($fconstraint) $suffix .= ' '.$fconstraint;
		return $suffix;
	}

	function _IndexSQL($idxname, $tabname, $flds, $idxoptions)
	{
		$sql = array();
		if ( isset($idxoptions['REPLACE']) || isset($idxoptions['DROP']) ) {
			$sql[] = sprintf ($this->dropIndex, $idxname, $tabname);
			if ( isset($idxoptions['DROP']) )
				return $sql;
		}
		if ( empty ($flds) ) {
			return $sql;
		}
		$unique = isset($idxoptions['UNIQUE']) ? ' UNIQUE' : '';
		$clustered = isset($idxoptions['CLUSTERED']) ? ' CLUSTERED' : '';
		if ( is_array($flds) )
			$flds = implode(', ',$flds);
		$s = 'CREATE' . $unique . $clustered . ' INDEX ' . $idxname . ' ON ' . $tabname . ' (' . $flds . ')';
		if ( isset($idxoptions[$this->upperName]) )
			$s .= $idxoptions[$this->upperName];
		$sql[] = $s;
		return $sql;
	}

	function MetaType($t,$len=-1,$fieldobj=false)
	{
		if (is_object($t)) {
			$fieldobj = $t;
			$t = $fieldobj->type;
			$len = $fieldobj->max_length;
		}
		$len = -1; // mysql max_length is not accurate
		switch (strtoupper($t)) {
			case 'INT': 
			case 'INTEGER': return  'I';
			case 'BIT':
			case 'TINYINT': return  'I1';
			case 'SMALLINT': return 'I2';
			case 'BIGINT':  return  'I8';
			case 'REAL':
			case 'FLOAT': return 'F';
			default: return parent::MetaType($t,$len,$fieldobj);
		}
	}

//	function &MetaTables($ttype=false,$showSchema=false,$mask=false) 
//	{
//		global $ADODB_FETCH_MODE;
//	}

//	function &MetaColumns($table,$upper=true) 
//	{
//		global $ADODB_FETCH_MODE;
//	}

//	function MetaPrimaryKeys($table, $owner=false)
//	{
//	}

//     function &MetaIndexes($table, $primary = false, $owner = false)
//     {
//     }

}

?>