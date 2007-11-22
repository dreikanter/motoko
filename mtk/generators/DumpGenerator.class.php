<?php

class DumpGenerator extends AbstractGenerator {
	
	function DumpGenerator(&$_performedAction) {
		
		$this->hdr = array(
				"HTTP/1.0 200 Ok", 
				"Status: 200 Ok", 
				"Content-Type: text/html; charset=utf-8"
			);
		
		$res = $_performedAction;
		
		function fmt($_title, $_content) {
			return '<h4>'.$_title.':</h4>'.
				'<div style="background:#efefef;padding:1mm 2mm 5mm 5mm;">'.
				'<code>'.$_content.'</code></div>';
		}
		
		$this->result = fmt("Result type", $_performedAction->resType);
		$r = array();
		foreach($_performedAction->resData as $name => $value) { 
			$r[] = '"'.$name.'" == "'.(is_array($value)?var_export($value, true):$value).'"';
		}
		$this->result .= fmt("Items", '<ol><li>'.implode("</li><li>", $r)."</li></ol>");
		$this->result .= fmt("Session", "<pre>".print_r($_SESSION, true)."</pre>");
		$this->result .= fmt("Output", ($res->output?$res->output:'[no output]'));
	}
	
}

?>