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
		$memcon,
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
		$doctype = true,
		$mime = 'text/html',
		$convert = false,
		$charset = 'utf-8',
		$stop = false,
		$registered = '(get|post|session|cookie|server|path|globals|server)';

	public function __construct ($request = '/', $speedAnalyze)
	{
		speedAnalyzer('Начинаем работу');
		$this -> speedAnalyze = $speedAnalyze;
		if(MEM_CACHE)
		{
			$this -> memcon = memcache_connect('127.0.0.1', 11211);
		}
		
		speedAnalyzer('Считаем $request');
		preg_match('/^(.*?)(\?.*)?$/', $request, $request);
		$request = $request[1];
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
		$this -> finish();
	}
	
	public function start()	
	{
		speedAnalyzer('Ищем XML и page');
		if(empty($this -> path))
		{
			$script_name = 'index';
		}
		else
		{
			$script_name = array_slice($this -> path, 0, REQUEST_DEEP);
			if(!count($script_name) || count($script_name) < REQUEST_DEEP) $script_name[] = 'index';
			$script_name = join('/', $script_name);
		}
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
			header ('Content-type: text/xml; charset='.$this->charset);
			$result = $this -> xml -> saveXML ();
		}
		else if($is_xsl)
		{
			speedAnalyzer('XSLT-трасформация');
			if(XSL_CACHE)
			{
				$proc = new xsltCache;
				$proc -> importStyleSheet ($this -> xsl);
			}
			else
			{
				$proc = new XSLTProcessor;
				$proc -> importStyleSheet ($this->load( $this -> xsl ));
			}
			$result = $proc -> transformToXML ($this -> xml);
			if(!$this->doctype) $result = preg_replace('#^\s*\<!DOCTYPE[^\>]+\>\n?#', '', $result);
			if($this->convert)
			{
				$result = iconv($this->charset, $this->convert, $result);
				$this->charset = $this->convert;
			}
			header('Content-type: '.$this->mime.'; charset='.$this->charset);
		}
		echo $result;

		unset($xgen);
		unset($proc);
		if(DB_USER) unset($this -> dbcon);
		if(MEM_CACHE) memcache_close($this -> memcon);
		speedAnalyzer('Финиш');
	}

	private function startParse($page)
	{
		$page = $this -> dom -> appendChild( $this -> dom -> importNode($page, true) );
		if (isset($_SERVER['HTTP_X_REQUESTED_WITH']))
		{
			$page -> setAttribute('ajax', 'true'); 
			$this -> doctype = false;
		}
		if($page -> hasAttribute('mime'))
		{
			$this -> mime = $page -> getAttribute('mime');
			$this -> doctype = false;
		}
		if($page -> hasAttribute('charset'))
		{
			$this -> convert = $page -> getAttribute('charset');
		}
		speedAnalyzer('Подключаемся к базе');
		if(DB_USER)
		{
			$this -> dbcon = new dbcon (DB_USER, DB_PASS, DB_NAME, DB_HOST, DB_PORT, false);
			$tables = $this -> dbcon -> query ("show table status") -> fetch_assoc_all();
			foreach($tables as $table) $this -> tableStatus[$table['Name']] = $table['Update_time'];
		}
		$this -> parse($page);
	}
					
	private function load ($path)
	{
		$dom = new DOMDocument('1.0', 'utf-8');
		$dom -> resolveExternals = true;
		$dom -> substituteEntities = true;
		$dom -> preserveWhiteSpace = false;
		$xml = false;
		if(XML_CACHE && $xml = $this->cacheGet('xml_'.md5($path)))
		{
			$dom -> loadXML($xml);
		}
		else
		{
			$dom -> load ($path, LIBXML_NOBLANKS|LIBXML_COMPACT|LIBXML_DTDLOAD);
		}
		$dom -> xinclude ();
		$dom -> normalizeDocument ();
		if(!$xml) $this->cacheSet('xml_'.md5($path), $dom -> saveXML());
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
	
	private function parse($node)
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
						if($this -> stop === $node)
						{
							$newChild = $this -> dom -> createTextNode('');
						}
						else
						{
							$newChild = $this->{$mname}($child);
						}
						$node -> replaceChild($newChild, $child);
					}
					else
					{
						$this -> parse($child);
					}
				}
			}
		}
	}
	
	private function attr ($attr, $strip = false)
	{
		$value = $this -> value ($attr -> nodeValue);
		if($strip) $value = strip_tags($value);
		$value = htmlspecialchars($value);
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
		$result = preg_replace_callback("/xpath\:([\/\ \[\]a-z\'A-Z0-9\(\)\@\:\!\=\>\<\_\-\*\.]+)/", array(&$this, 'insertXML'), $result);
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
		if(!$node->hasAttribute('action') && !$node->hasAttribute('actionnot')) return true;
		if($node->hasAttribute('action'))
		{
			$action = $this -> value ($node -> getAttribute ('action'));
			if(!empty($action)) return true;
		}
		if($node->hasAttribute('actionnot'))
		{
			$action = $this -> value ($node -> getAttribute ('actionnot'));
			if(empty($action)) return true;
		}
		return false;
	}
	
	
	private function isXmlNode ($node)
	{
		if ($node -> nodeType == XML_ELEMENT_NODE) return true;
		return false;
	}

	private function cacheGet($name)
	{
		if(MEM_CACHE)
		{
			return $this->memcon->get($name);
		}
		else if(APC_CACHE)
		{
			return apc_fetch($name);
		}
	}
	
	private function cacheSet($name, $content = '')
	{
		if(MEM_CACHE)
		{
			$this->memcon->set($name, $content, true, TTL_SECONDS);
		}
		else if(APC_CACHE)
		{
			apc_store($name, $content, TTL_SECONDS);
		}
	}
?>
