<?php
	private function parsePost ($node)
	{
		return $this->xmlDump('post', $_POST);
	}
	
	private function parseGet ($node) {
		return $this->xmlDump('get', $_GET);
	}
	
	private function parseCookie ($node) {
		return $this->xmlDump('cookie', $_COOKIE);
	}

	private function parseSession ($node) {
		return $this->xmlDump('session', $_SESSION);
	}
	
	private function xmlDump($name, $arr)
	{
		$result = $this -> makeNode ($name);
			if (!empty ($arr))
			{
				foreach ($arr as $key => $pst)
				{
					if (strlen ($pst))
					{
						if(!is_array($pst))
						{
							$pst = array($pst);
						}
						foreach($pst as $val)
						{
							$result -> appendChild ($this -> makeNode ($key, $val));
						}
					}
				}	
			}
		return $result;
	}

	private function parsePath ($node) {
		$path = $this -> dom -> createElement('path');
		$req = $this -> dom -> createAttribute('request');
		$req -> nodeValue = htmlspecialchars($this -> request);
		$path -> appendChild( $req );
		if (count($this -> path))
		foreach( $this -> path as $p )
		{
			$path -> appendChild( $this -> dom -> createElement('dir', $p) );
		}
		return $path;
	}
		
	private function parseVar ($node)
	{
		if (!$this -> validateAction ($node)) return  $this -> simpleError ($node -> nodeName, 'invalid action');		
		if (!$node -> hasAttribute ('name')) return $this -> simpleError ('var', '@name is not defined');
		$var_name = $node -> getAttribute ('name');
		preg_match('/'.$this->registered.'\:([a-zA-Z0-9\_]+)/', $var_name, $result);
		$type = $result[1];
		$name = $result[2];
		
		if ($node -> hasAttribute ('value')) 
		{
			$var_value = $node -> getAttribute ('value');
			$value = $this -> value ($var_value);
		}

		if($node -> hasAttribute ('remove'))
		{
			$this -> removeValue($type, $name);
		}
		
		if(isset($value))
		{
			$var = $this -> appendValue($type, $name, $value);
		}
		else
		{
			$value = $this -> extractValue($type, $name);
			$var = $this -> dom -> createElement ('var', $value | '');
			$var -> setAttribute ('name', $name);
			$var -> setAttribute ('type', $type);
		}

		if($node -> hasChildNodes())
		{
			if(is_array($value))
			{
				$values = $value;
			}
			else
			{
				$values = split('(^\'|\',\'|\'$)', $value);
			}
			foreach($values as $val)
			{
				if(strlen($val))
				{
					$tmp = $var -> appendChild($this -> appendValue($type, $name, $val));
					foreach($node -> childNodes as $child)
					{
						$clone = $tmp -> appendChild($child -> cloneNode(true));
					}
					$this -> parse($tmp);
				}
			}
		}
		
		return $var;
	}
	
	private function appendValue($type, $name, $value)
	{
		if($type === 'post') $_POST[$name] = $value;
		if($type === 'get') $_GET[$name] = $value;
		if($type === 'session') $_SESSION[$name] = $value;
		if($type === 'cookie')
		{
			$_COOKIE[$name] = $value;
			setcookie($name, $value, time()+(60*60*24*90), '/');
		}
		if($type === 'globals') $this->globals[$name] = $value;
		$var = $this -> dom -> createElement ('var', $value);
		$var -> setAttribute ('name', $name);
		$var -> setAttribute ('type', $type);
		return $var;
	}
	private function removeValue($type, $name)
	{
		if($type === 'post') $_POST[$name] = null;
		if($type === 'get') $_GET[$name] = null;
		if($type === 'session') unset($_SESSION[$name]);
		if($type === 'cookie')
		{
			$_COOKIE[$name] = null;
			setcookie($name, '', time()+(60*60*24*90), '/');
		}
		if($type === 'globals') unset($this->globals[$name]);
	}
	private function extractValue($type, $name)
	{
		if($type === 'post') return $_POST[$name];
		if($type === 'get') return $_GET[$name];
		if($type === 'session') return $_SESSION[$name];
		if($type === 'cookie') return $_COOKIE[$name];
		if($type === 'globals') return $this->globals[$name];
		if($type === 'server') return $_SERVER[$name];
	}
?>
