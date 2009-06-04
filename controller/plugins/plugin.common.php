<?php
	public  $dom,
		$xpath,
		$config,
		$is_ajax,
		$path,
		$dir,		
		$xsl,
		$xml,
		$page,
		$tableStatus = array(),
		$dbcon,
		$cache,
		$globals = array(),
		$checkAtrribs = array (
				 	'is'	=> '=#value#',
				 	'in'	=> ' in (#value#)',
				 	'like'	=> ' LIKE #value#',
				 	'not'	=> '<> #value#',
				 	'ne'	=> '<> #value#',
					'lt'	=> '< #value#',
					'gt'	=> '> #value#',
					'ltis'	=> '<= #value#',
					'gtis'	=> '>= #value#',
					'value'	=> ''
				);		

	private $ret_xml,
		$sql,
		$join = '',
		$from,
		$where = '',								
		$table, 
		$logger,
		$logger_start,
		$registered = '(get|post|session|cookie|server|path|globals|server)';

	public function __construct ($request = '/')
	{
		if(DB_USER)
		{
			$this -> dbcon = new dbcon (DB_USER, DB_PASS, DB_NAME, DB_HOST, DB_PORT, true);
			$this -> dbcon -> query ("SET NAMES utf8 COLLATE 'utf8_general_ci'");
			$tables = $this -> dbcon -> query ("show table status") -> fetch_assoc_all();
			foreach($tables as $table) $this -> tableStatus[$table['Name']] = $table['Update_time'];
		}
		
		$s = split('/', $request);
		foreach ($s as $p) if (!empty ($p)) $this -> path[] = $p;

		$this -> request = $request;
		
		$imp = new DOMImplementation;		
		$dtd = $imp -> createDocumentType('page', '', VIEW_PATH.'entities.dtd');
		
		if (isset ($_GET['xml']))
			$dtd = $imp -> createDocumentType ('page', '-//W3C//DTD HTML 4.01 Transitional//EN', 'http://www.w3.org/TR/html4/loose.dtd');
				
		$this -> dom = $imp -> createDocument("", "", $dtd);				
		$this -> dom -> encoding = 'UTF-8';

		$this -> xpath = new DOMXpath ($this -> dom);
		$this -> start();
		unset($this -> dbcon);
		
		$this -> finish();
	}
	
	public function start()	
	{
		$script_name = empty($this -> path) ? 'index' : $this -> path[0];
		$script_xml = MODEL_PATH.$script_name.'.xml';
		if(is_file($script_xml))
		{
			$this -> config = $this -> load ($script_xml);
			$pages = $this -> config -> getElementsByTagName ('page');
			$pageFounded = false;

			foreach ($pages as $page)
			{
				if (strlen ($match = $page -> getAttribute ('match')))
				{
					if (preg_match ('#^'.$match.'$#', $this -> request, $matches))
					{
						$this -> startParse($page);
						$pageFounded = true;
						break; 
					}
				}
			}
			if(!$pageFounded && $pages -> length != 0)
			{
				$this -> startParse($pages -> item(0));
				$pageFounded = true;
			}
			if(!$pageFounded)
			{
				$error = $this -> simpleError('error', '510');
				$error -> setAttribute('description', 'Config not declared');
				$this -> dom -> appendChild($error);
			}
		}
		else
		{
			header("HTTP/1.0 404 Not Found");
			$error = $this -> simpleError('error', '404');
			$error -> setAttribute('description', 'Not found');
			$this -> dom -> appendChild($error);
		}
			
		$this -> xml = $this -> dom;
		$this -> xsl = VIEW_PATH.$script_name.'.xsl';	
	}
	
	public function finish()	
	{
		$result = '';
		$is_xsl = is_file($this -> xsl);
		if (XML_SOURCE == true || !$is_xsl)
		{
			header ("Content-type: text/xml; charset=utf-8");
			$result = $this -> xml -> saveXML ();
		}
		else if($is_xsl)
		{
			if(XSL_CACHE)
			{
				$proc = new xsltCache ();
				$proc -> importStyleSheet ($xgen -> xsl);
			}
			else
			{
				$proc = new XSLTProcessor;
				$proc -> importStyleSheet ($this->load( $this -> xsl ));
			}
			$result = $proc -> transformToXML ($this -> xml);
			if (isset($_SERVER['HTTP_X_REQUESTED_WITH'])) $result = preg_replace('#^\s*\<!DOCTYPE[^\>]+\>#', '', $result);
		}
		echo $result;

		unset($xgen);
		unset($proc);
	}

	private function startParse($page)
	{
		if (isset($_SERVER['HTTP_X_REQUESTED_WITH']))
		{
			$page -> setAttribute('ajax', 'true'); 
		}
		$this -> parsePage ($page);
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
	
	public function __call ($name, $arg) {
		$node = $arg[0];

		switch ($node -> nodeType) {

			case XML_ATTRIBUTE_NODE:	
				return $this -> attr ($node);				
				break;
			case XML_ELEMENT_NODE:				
				return $this -> parse ($node);
				break;
			case XML_TEXT_NODE:	
				return $this -> dom -> createTextNode ($this -> value ($node -> nodeValue));
				break;
			default:
				return $node;
				break;			
		}
			
	}
	
	private function parseAttributes ($node, $result) {
		if (!empty ($node -> attributes))
			foreach ($node -> attributes as $attr)
				if (strlen ($attr -> nodeName))
					$result -> appendChild ($this -> {'attr'.ucfirst ($attr -> nodeName)} ($attr));
	}
	
	private function parseTags ($node, $result) {
		if ($node -> hasChildNodes ())
		
			foreach ($node -> childNodes as $child) {
				if($child -> nodeType == XML_ELEMENT_NODE && $child -> hasAttribute ('static'))
				{
					$result -> appendChild ($this -> dom -> importNode ($child, true));
				}
				elseif ($child -> nodeType != XML_COMMENT_NODE)
				{
					$result -> appendChild ($this -> {'parse'.ucfirst ($child -> nodeName)} ($child));
				}
			}		

	}
	
	public function parse ($node) {
		$result = $this -> dom -> createElement ($node -> nodeName);
		$this -> parseAttributes ($node, $result);
		$this -> parseTags ($node, $result);		
		return $result;
	}			
	
	public function parsePage ($node) {
		$this -> page = $this -> dom -> createElement ('page');
		$this -> dom -> appendChild ($this -> page);
		$this -> parseAttributes ($node, $this -> page);
		$this -> parseTags ($node, $this -> page);
	}
	
	
	private function attr ($node) {
		$node = $this -> dom -> importNode ($node, true);
		$node -> nodeValue = htmlspecialchars($this -> value ($node -> nodeValue));
		return $node;
	}

	public function value($value, $setup = false)
	{
		if (!strlen(trim($value))) return '';

		$result = preg_replace_callback('/'.$this->registered.'\:([a-zA-Z0-9\_]+)/', 'insertData', $value);
		$result = preg_replace_callback("/xpath\:([\/\ \[\]a-z\'A-Z0-9\(\)\@\:\!\=\>\<\_\-]+)/", 'insertXML', $result);
		
		return $result;
	}
	
	private function validateAction ($node) {
		if (!$node -> hasAttribute ('action'))	return true;
		$action = $this -> value ($node -> getAttribute ('action'));
		if (!empty ($action)) return true;
		else return false;
	}
	
	
	private function isXmlNode ($node) {
		if ($node -> nodeType == XML_ELEMENT_NODE) return true;
		else return false;
	}
?>
