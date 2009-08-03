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
		$speedAnalyze,
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

	public function __construct ($request = '/', $speedAnalyze)
	{
		speedAnalyzer('Начинаем работу');
		$this -> speedAnalyze = $speedAnalyze;
		speedAnalyzer('Подключаемся к базе');
		if(DB_USER)
		{
			$this -> dbcon = new dbcon (DB_USER, DB_PASS, DB_NAME, DB_HOST, DB_PORT, true);
			$tables = $this -> dbcon -> query ("show table status") -> fetch_assoc_all();
			foreach($tables as $table) $this -> tableStatus[$table['Name']] = $table['Update_time'];
		}
		
		speedAnalyzer('Считаем $request');
		$s = split('/', $request);
		foreach ($s as $p) if (!empty ($p)) $this -> path[] = $p;

		$this -> request = $request;
		
		speedAnalyzer('Создаем DOM');
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
		speedAnalyzer('Ищем XML и page');
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
						$pageFounded = $page;
						break; 
					}
				}
			}
			if(!$pageFounded && $pages -> length != 0)
			{
				$pageFounded = $pages -> item(0);
			}
			unset($this -> config);
			if(!$pageFounded)
			{
				$error = $this -> simpleError('error', '510');
				$error -> setAttribute('description', 'Config not declared');
				$this -> dom -> appendChild($error);
			}
			else
			{
				$this -> startParse($pageFounded);
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
			speedAnalyzer('XSLT-трасформация');
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
		speedAnalyzer('Финиш');
	}

	private function startParse($page)
	{
		$page = $this -> dom -> appendChild( $this -> dom -> importNode($page, true) );
		if (isset($_SERVER['HTTP_X_REQUESTED_WITH']))
		{
			$page -> setAttribute('ajax', 'true'); 
		}
		$this -> parse($page);
	}
					
	private function load ($path)
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
	
	private function makeNode ($nodeName, $text = '') {
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
	
	private function simpleError ($nodeName, $text = '') {
		return $this -> makeNode ($nodeName, $text);
	}
	
	private function multiError ($errors) {
		$error = $this -> makeNode ('error');
			if (count ($errors))
				foreach ($errors as $err) $error -> appendChild ($err['node'] -> nodeName, isset ($err['text']) ? $err['text'] : '');
		return $error;		
	}
	
	private function parse ($node)
	{
		if(!empty($node -> attributes) && !$node -> hasAttribute ('static'))
		{
			foreach ($node -> attributes as $attr)
			{
				$this -> attr($attr);
			}
		}
		if($node -> hasChildNodes())
		{
			$l = $node -> childNodes -> length;
			for($i = 0; $i < $l; $i++)
			{
				$child = $node -> childNodes -> item($i);
				if($child -> nodeType == XML_ELEMENT_NODE && !$child -> hasAttribute ('static'))
				{
					$mname = 'parse'.ucfirst($child -> nodeName);
					if(method_exists($this, $mname))
					{
						$node -> replaceChild($this->{$mname}($child), $child);
					}
					else
					{
						$this -> parse($child);
					}
				}
			}
		}
	}
	
	private function attr ($attr)
	{
		$value = htmlspecialchars($this -> value ($attr -> nodeValue));
		$attr -> parentNode -> setAttribute($attr -> nodeName, $value);
		return $value;
	}
	
	private function parseSpeedAnalyzer ($node)
	{
		$result = $this -> dom -> createElement ('speedAnalyzer');
		$res = speedAnalyzer($node -> getAttribute('name'), $this -> speedAnalyze);
		$result -> setAttribute('prev', $res[0]);
		$result -> setAttribute('name', $res[1]);
		$result -> setAttribute('time', $res[2]);
		$result -> setAttribute('diff', $res[3]);
		$result -> setAttribute('ms', $res[4]);
		return $result;
	}
	
	private function value($value, $setup = false)
	{
		if (!strlen(trim($value))) return '';
		$result = preg_replace_callback('/'.$this->registered.'\:([a-zA-Z0-9\_]+)/', array(&$this, 'insertData'), $value);
		$result = preg_replace_callback("/xpath\:([\/\ \[\]a-z\'A-Z0-9\(\)\@\:\!\=\>\<\_\-]+)/", array(&$this, 'insertXML'), $result);
		return $result;
	}
	private function insertData($matches)
	{
		$type = $matches[1];
		$value = $matches[2];
		
		if($type === 'post' && isset($_POST[$value]))
			return (is_array($_POST[$value])) ? '\''.join('\',\'', $_POST[$value]).'\'' : $_POST[$value];

		if($type === 'get' && isset($_GET[$value]))
			return (is_array($_GET[$value])) ? '\''.join('\',\'', $_GET[$value]).'\'' : $_GET[$value];

		if($type === 'session' && isset($_SESSION[$value]))
			return $_SESSION[$value];

		if($type === 'server' && isset($_SERVER[$value]))
			return $_SERVER[$value];

		if($type === 'cookie' && isset($_COOKIE[$value]))
			return $_COOKIE[$value];

		if($type === 'globals' && array_key_exists($value, $this->globals))
			return $this->globals[$value];

		if($type === 'path' && isset($this->path[$value-1]))
			return $this->path[$value-1];

		if($type === 'path' && $value == 0)
			return REQUEST_SOURCE;

		return null;
	}
	private function insertXML($matches)
	{
		$result = $this -> xpath -> query ($matches[1]);
		if ($result -> length == 0)
		{
			$value = null;
		}
		elseif ($result -> length == 1)
		{
			$value = $result -> item(0) -> nodeValue;
		}
		else
		{
			$tmp = array();
			foreach ($result as $n) $tmp[] = "'".$n -> firstChild -> nodeValue."'";
			$value = join(',', $tmp);
		}

		return $value;
	}
	
	private function validateAction ($node)
	{
		if (!$node -> hasAttribute ('action'))	return true;
		$action = $this -> value ($node -> getAttribute ('action'));
		if (!empty ($action)) return true;
		return false;
	}
	
	
	private function isXmlNode ($node)
	{
		if ($node -> nodeType == XML_ELEMENT_NODE) return true;
		return false;
	}

	private function cacheGet($name)
	{
		return apc_fetch($name);
	}
	
	private function cacheSet($name, $content = '')
	{
		apc_store($name, $content, 15);
	}
?>
