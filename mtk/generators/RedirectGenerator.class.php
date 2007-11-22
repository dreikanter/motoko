<?php

class RedirectGenerator extends AbstractGenerator {
	
	function RedirectGenerator(&$_performedAction) {
		$this->returnable = $_performedAction->returnable;
		
		$url = $_performedAction->resData['to'];
		$url = ($url == 'back')?$_SESSION['ref']:$url;
		
		if(isset($_performedAction->resData['system_message'])) {
			$_SESSION['system_message'] = $_performedAction->resData['system_message'];
		} else {
			$_SESSION['system_message'] = '';
		}
		
		if(DEBUG_MODE) {
			$this->hdr = array(
					"HTTP/1.0 200 Ok", 
					"Status: 200 Ok", 
					"Content-Type: text/html; charset=utf-8"
				);
				$this->result = '<a href="'.$url.'">Redirect &rarr;</a>';
		} else {
			// make sure this thing doesn't cache
			$this->hdr = array(
				//	"HTTP/1.0 200 Ok", 
				//	"Status: 200 Ok", 
					"Expires: Mon, 28 Nov 2004 05:00:00 GMT", 
					"Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT", 
					"Cache-Control: no-store, no-cache, must-revalidate", 
					"Cache-Control: post-check=0, pre-check=0", 
					"Pragma: no-cache", 
					"Location: ".$url
				);
		}
	}
	
}

?>