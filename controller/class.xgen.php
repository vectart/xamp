<?php

	class xgen extends xparse{
		private	$ret_xml;
		
		public  $dom,
			$xpath,
			$config,
			$is_ajax,
			$path,
			$dir,		
			$xsl;	
		
		public function __construct ($request, $path)
		{
			parent::__construct ();

			$this -> request = $request;
			$this -> path = $path;
			
			$imp = new DOMImplementation;		
			$dtd = $imp -> createDocumentType('page', '', VIEW_PATH.'entities.dtd');
			
			if (isset ($_GET['xml']))
				$dtd = $imp -> createDocumentType ('page', '-//W3C//DTD HTML 4.01 Transitional//EN', 'http://www.w3.org/TR/html4/loose.dtd');
					
			$this -> dom = $imp -> createDocument("", "", $dtd);				
			$this -> dom -> encoding = 'UTF-8';

			$this -> xpath = new DOMXpath ($this -> dom);
		}
		
		public function start()	
		{
			$script_name = empty($this -> path) ? 'index' : $this -> path[0];
			$this -> config = $this -> load (MODEL_PATH.$script_name.'.xml');
			$pages = $this -> config -> getElementsByTagName ('page');

			foreach ($pages as $page) {
				
				if (strlen ($match = $page -> getAttribute ('match')))

					if (preg_match ('#^'.$match.'$#', $this -> request, $matches)) {
						if (isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
							$page -> setAttribute('ajax', 'true'); 
						}
						$this -> parsePage ($page);
						break; 
					}	
			}
				
			$this -> xml = $this -> dom;
			$this -> xsl = VIEW_PATH.$script_name.'.xsl';	
		}
						
		public function load ($path)
		{
			$dom = new DOMDocument('1.0', 'utf-8');
			$dom -> resolveExternals = true;
			$dom -> substituteEntities = true;
			$dom -> preserveWhiteSpace = false;
			$dom -> load ($path, LIBXML_NOBLANKS|LIBXML_COMPACT|LIBXML_DTDLOAD);			
			$dom -> xinclude ();									
			$dom -> normalizeDocument ();
			return $dom;
		}		
		
		public function makeNode ($nodeName, $text = '') {
			if( gettype($text) == 'object')
			{
				$tmp = $this -> dom -> createElement($nodeName);
				return $tmp -> appendChild($text);
			}
			else
			{
				return $this -> dom -> createElement($nodeName, $text);
			}
		}
		
		public function simpleError ($nodeName, $text = '') {
			return $this -> makeNode ($nodeName, $text);
		}
		
		public function multiError ($errors) {
			$error = $this -> makeNode ('error');
				if (count ($errors))
					foreach ($errors as $err) $error -> appendChild ($err['node'] -> nodeName, isset ($err['text']) ? $err['text'] : '');
			return $error;		
		}
					
	}

?>
