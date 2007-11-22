<?php

class XmlGenerator extends AbstractGenerator {
	
	function XmlGenerator(&$_performedAction) {
		
		// HTTP Content-Type, ���������� ��-��������� - text/xml. ��� ����� 
		// �������������� � ������� �������� content-type, ������������� 
		// �� ������������� action-�
		$contentType = isset($_performedAction->resData['content-type'])?
			$_performedAction->resData['content-type']:'text/xml';
		
		$this->hdr = array(
				"HTTP/1.0 200 Ok", 
				"Status: 200 Ok", 
				"Content-Type: ".$contentType."; charset=utf-8"
			);
		
		$this->result = $_performedAction->resData['xml'];
		
	}
	
}

?>