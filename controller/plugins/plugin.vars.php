<?php
	private function parsePost ($node) {
		$result = $this -> makeNode ('post');
			if (!empty ($_POST)) {
				foreach ($_POST as $key => $pst) {
					if (strlen ($pst))
						$result -> appendChild ($this -> makeNode ($key, $pst));
				}	
			}
		return $result;	
	}
	
	private function parseGet ($node) {
		$result = $this -> makeNode ('get');
			if (!empty ($_GET)) {
				foreach ($_GET as $key => $get) {
					$result -> appendChild ($this -> makeNode ($key, $get));
				}	
			}
		return $result;	
	}		

	public function parsePath ($node) {
		$path = $this -> dom -> createElement('path');
		$req = $this -> dom -> createAttribute('request');
		$req -> nodeValue = $this -> request;
		$path -> appendChild( $req );
		if (count($this -> path))
		foreach( $this -> path as $p )
		{
			$path -> appendChild( $this -> dom -> createElement('dir', $p) );
		}
		return $path;
	}
		
	private function parseVar ($node) {
		if (!$this -> validateAction ($node)) return  $this -> simpleError ($node -> nodeName, 'invalid action');		
		if (!$node -> hasAttribute ('name')) return $this -> simpleError ('var', '@name is not defined');
		if (!$node -> hasAttribute ('value')) return $this -> simpleError ('var', '@value is not defined');
		$var_name = $node -> getAttribute ('name');
		$var_value = $node -> getAttribute ('value');
		
		preg_match('/'.$this->registered.'\:([a-zA-Z0-9\_]+)/', $var_name, $result);
		$type = $result[1];
		$name = $result[2];
		$value = $this -> value ($var_value);
		
	if($type === 'post')
		$_POST[$name] = $value;

	if($type === 'get')
		$_GET[$name] = $value;

	if($type === 'session')
		$_SESSION[$name] = $value;

	if($type === 'cookie')
		$_COOKIE[$name] = $value;

	if($type === 'globals')
		$this->globals[$name] = $value;

		$var = $this -> dom -> createElement ('var', $value);
		$var -> setAttribute ('name', $name);
		$var -> setAttribute ('type', $type);

		return $var;
	}
?>
