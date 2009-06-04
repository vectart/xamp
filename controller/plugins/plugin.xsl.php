<?php
	private function parseXsl ($node) {
		if (!$node -> hasAttribute ('file')) 
			return $this -> simpleError ($node -> nodeName, '@file is not defined');
		if (!file_exists (VIEW_PATH.$node -> getAttribute ('file'))) 
			return $this -> simpleError ($node -> nodeName, 'xsl file not found');
		$xsl = $this -> load (VIEW_PATH.$node -> getAttribute ('file'));
		$proc = new XSLTProcessor;
		$proc -> importStyleSheet ($xsl);
		return $proc -> transformToXML ($this -> dom);
	}
?>
