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

class ADODB2_mssqlpo extends ADODB_DataDict {

	var $dbtype = 'mssqlpo';
	var $dropIndex = 'DROP INDEX %2$s.%1$s';
	var $renameTable = "EXEC sp_rename '%s','%s'";
	var $renameColumn = "EXEC sp_rename '%s.%s','%s'";

	var $typeX = 'TEXT';  ## Alternatively, set it to VARCHAR(4000)
	var $typeXL = 'TEXT';
	var $metaTablesSQL="select name,case when type='U' then 'T' else 'V' end from sysobjects where (type='U' or type='V') and (name not in ('sysallocations','syscolumns','syscomments','sysdepends','sysfilegroups','sysfiles','sysfiles1','sysforeignkeys','sysfulltextcatalogs','sysindexes','sysindexkeys','sysmembers','sysobjects','syspermissions','sysprotects','sysreferences','systypes','sysusers','sysalternates','sysconstraints','syssegments','REFERENTIAL_CONSTRAINTS','CHECK_CONSTRAINTS','CONSTRAINT_TABLE_USAGE','CONSTRAINT_COLUMN_USAGE','VIEWS','VIEW_TABLE_USAGE','VIEW_COLUMN_USAGE','SCHEMATA','TABLES','TABLE_CONSTRAINTS','TABLE_PRIVILEGES','COLUMNS','COLUMN_DOMAIN_USAGE','COLUMN_PRIVILEGES','DOMAINS','DOMAIN_CONSTRAINTS','KEY_COLUMN_USAGE','dtproperties'))";
	var $metaColumnsSQL = # xtype==61 is datetime
"select c.name, t.name, c.length,
(case when c.xusertype=61 then 0 else c.xprec end),
(case when c.xusertype=61 then 0 else c.xscale end),
c.isnullable, m.text, sign(c.status & 0x80),
sign(ISNULL(r.status, 0) & 16), sign(ISNULL(k.keyno, 0))
FROM syscolumns c
JOIN systypes t ON (t.xusertype=c.xusertype)
JOIN sysobjects o on (o.id=c.id)
LEFT OUTER JOIN sysconstraints r ON ((r.id=o.id) AND (r.colid=c.colid))
LEFT OUTER JOIN syscomments m ON (m.id = c.cdefault)
LEFT OUTER JOIN sysobjects p ON ((p.parent_obj=o.id) AND (p.xtype='PK'))
LEFT OUTER JOIN sysindexes i ON ((i.id=p.parent_obj) AND (i.name=p.name))
LEFT OUTER JOIN sysindexkeys k ON ((k.id=i.id) AND (k.indid=i.indid) AND (k.colid=c.colid))
WHERE o.name='%s'";

 	function ActualType($meta)
	{
		switch(strtoupper($meta)) {

		case 'C': return 'VARCHAR';
		case 'XL': return (isset($this)) ? $this->typeXL : 'TEXT';
		case 'X': return (isset($this)) ? $this->typeX : 'TEXT'; ## could be varchar(8000), but we want compat with oracle
		case 'C2': return 'NVARCHAR';
		case 'X2': return 'NTEXT';
		
		case 'B': return 'IMAGE';
			
		case 'D': return 'DATETIME';
		case 'T': return 'DATETIME';
		case 'L': return 'BIT';
		
		case 'R':		
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

	function DropColumnSQL($tabname, $flds)
	{
		$tabname = $this->TableName ($tabname);
		if (!is_array($flds))
			$flds = explode(',',$flds);
		$f = array();
		$s = 'ALTER TABLE ' . $tabname;
		foreach($flds as $v) {
			$f[] = "\n$this->dropCol ".$this->NameQuote($v);
		}
		$s .= implode(', ',$f);
		$sql[] = $s;
		return $sql;
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

	function MetaType($t,$len=-1,$fieldobj=false)
	{
		if (is_object($t)) {
			$fieldobj = $t;
			$t = $fieldobj->type;
			$len = $fieldobj->max_length;
		}
		
		$len = -1; // mysql max_length is not accurate
		switch (strtoupper($t)) {
		case 'R':
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

	function _CreateSuffix($fname,$ftype,$fnotnull,$fdefault,$fautoinc,$fconstraint)
	{	
		$suffix = '';
		if (strlen($fdefault)) $suffix .= " DEFAULT $fdefault";
		if ($fautoinc) $suffix .= ' IDENTITY(1,1)';
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

	function _GetSize($ftype, $ty, $fsize, $fprec)
	{
		switch ($ftype) {
		case 'INT':
		case 'SMALLINT':
		case 'TINYINT':
		case 'BIGINT':
			return $ftype;
		}
    	if ($ty == 'T') return $ftype;
    	return parent::_GetSize($ftype, $ty, $fsize, $fprec);    
	}

//	function &MetaTables($ttype=false,$showSchema=false,$mask=false) 
//	{
//		global $ADODB_FETCH_MODE;
//	}

	function MetaColumns($tab, $upper=true, $schema=false)
	{
		global $ADODB_FETCH_MODE;

		$false = false;

		if (!empty($this->metaColumnsSQL)) {
			$schema = false;
			$this->_findschema($table,$schema);

			$save = $ADODB_FETCH_MODE;
			$ADODB_FETCH_MODE = ADODB_FETCH_NUM;
			if ($this->fetchMode !== false) $savem = $this->SetFetchMode(false);
			$rs = $this->Execute(sprintf($this->metaColumnsSQL,($upper)?strtoupper($table):$table));
			if (isset($savem)) $this->SetFetchMode($savem);
			$ADODB_FETCH_MODE = $save;
			if ($rs === false || $rs->EOF) return $false;

			$retarr = array();
			while (!$rs->EOF){
				$fld = new ADOFieldObject();
				$fld->name = $rs->fields[0];
				$fld->type = $rs->fields[1];
				if (isset($rs->fields[3]) && $rs->fields[3]) {
					if ($rs->fields[3] > 0) $fld->max_length = $rs->fields[3];
					$fld->scale = $rs->fields[4];
					if ($fld->scale > 0) $fld->max_length += 1;
				} else
				$fld->max_length = $rs->fields[2];

				$fld->not_null = ($rs->fields[5] == '0');
				$fld->primary_key = ($rs->fields[9] == '1');
				$fld->auto_increment = ($rs->fields[7] == '1');
				$fld->has_default = ($rs->fields[8] == '1');
				if ($fld->has_default) {
					$d = $rs->fields[6];
					$meta = $rs->MetaType($fld->type);
					$trim = (($meta == 'C') || ($meta == 'X')) ? 2 : 1;
					$fld->default_value = substr($d, $trim, -$trim); // d is "(default)" or "('default')"
				}

				if ($ADODB_FETCH_MODE == ADODB_FETCH_NUM) $retarr[] = $fld;
				else $retarr[strtoupper($fld->name)] = $fld;
				$rs->MoveNext();
			}
		}
	}

//	function MetaPrimaryKeys($table, $owner=false)
//	{
//	}

//     function &MetaIndexes($table, $primary = false, $owner = false)
//     {
//     }

}

?>