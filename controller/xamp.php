<?php
	prepare();
	$xamp = new xamp(REQUEST_URL);




	function prepare()
	{
		include_once 'config.php';
		
	 	cleanup();
		header ("Content-type: text/". (XML_SOURCE == true ? 'xml' : 'html')."; charset=utf-8");
		session_start();

		include_once 'class.dbcon.php';
		include_once 'class.mail.php';
		include_once 'class.xfiles.php';
		include_once 'class.recaptcha.php';
  		if(!XAMP_REBUILD) include_once 'class.xamp.php';
	  	if(!class_exists('xamp') || XAMP_REBUILD)
	  	{
			if ($handle = opendir(PLUGIN_PATH))
			{
				while (false !== ($file = readdir($handle)))
				{ 
					if ($file != "." && $file != "..")
					{ 
						$result .= " \n \n \n// $file \n ".preg_replace('#(^\s*\<\?(php)?|\?\>\s*$|\t)#', '', file_get_contents(PLUGIN_PATH."/$file"));
					} 
				}
				closedir($handle); 
			}
	  		$result = "<?php \n class xamp \n { \n $result";
			$result = "$result \n } \n ?>";
			file_put_contents('class.xamp.php', $result);
			require_once 'class.xamp.php';
	  	}
	}

	function cleanup()
	{
		include_once 'class.jevix.php';
		$jevix = new jevix();
		$jevix->cfgAllowTags(array('a', 'img', 'i', 'b', 'u', 'em', 'strong', 'sup', 'br'));
		$jevix->cfgSetTagShort(array('br','img'));
		$jevix->cfgSetTagCutWithContent(array('script', 'object', 'iframe', 'style'));
		$jevix->cfgAllowTagParams('a', array('title', 'href'));
		$jevix->cfgAllowTagParams('img', array('src', 'alt' => '#text'));
		$jevix->cfgSetTagParamsRequired('img', 'src');
		$jevix->cfgSetTagParamsRequired('a', 'href');
		$jevix->cfgSetTagParamsAutoAdd('a', array('target' => '_blank'));
		$jevix->cfgSetAutoReplace(array('+/-', '(c)', '(r)', '&'), array('±', '©', '®', '&amp;'));
		$jevix->cfgSetXHTMLMode(true);
		$jevix->cfgSetAutoBrMode(true);
		$jevix->cfgSetAutoLinkMode(true);
		$errors = null;
		if (isset ($_POST)) foreach ($_POST as $key => $value) if(!is_array($value)) $_POST[$key] = $jevix -> parse ($value, $errors);
		if (isset ($_GET)) foreach ($_GET as $key => $value) $_GET[$key] = $jevix -> parse ($value, $errors);
		if (isset ($_COOKIE)) foreach ($_COOKIE as $key => $value) $_COOKIE[$key] = $jevix -> parse ($value, $errors);
		unset($jevix);
	}
	
	function insertData($matches)
	{
		global $xgen;
		
		$type = $matches[1];
		$value = $matches[2];
		
		if($type === 'post' && isset($_POST[$value]))
			return (is_array($_POST[$value])) ? join(',', $_POST[$value]) : $_POST[$value];

		if($type === 'get' && isset($_GET[$value]))
			return (is_array($_GET[$value])) ? join(',', $_GET[$value]) : $_GET[$value];

		if($type === 'session' && isset($_SESSION[$value]))
			return $_SESSION[$value];

		if($type === 'server' && isset($_SERVER[$value]))
			return $_SERVER[$value];

		if($type === 'cookie' && isset($_COOKIE[$value]))
			return $_COOKIE[$value];

		if($type === 'globals' && array_key_exists($value, $xgen->globals))
			return $xgen->globals[$value];

		if($type === 'path' && isset($xgen->path[$value-1]))
			return $xgen->path[$value-1];

		return null;
	}

	function insertXML($matches)
	{
		global $xgen;

		$result = $xgen -> xpath -> query ($matches[1]);
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

?>

