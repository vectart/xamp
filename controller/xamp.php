<?php
	
	$speedAnalyze = getmicrotime();
	$speedAnalyzeLast = 0;
	prepare();
	$xamp = new xamp(REQUEST_URL, $speedAnalyze);

	function prepare()
	{
		require_once 'config.php';
		//file_put_contents(CONTROLLER_PATH.'xamp.log', "------------------------------------\n\n", FILE_APPEND);
		
		header ("Content-type: text/". (XML_SOURCE == true ? 'xml' : 'html')."; charset=utf-8");

		speedAnalyzer('Подключаем ядро');		
  		if(!XAMP_REBUILD) require_once 'kernel.php';
  		
	  	if(!class_exists('xamp') || XAMP_REBUILD)
	  	{
			speedAnalyzer('Пересобираем XAMP');
			$result .= rebuild(PLUGIN_PATH);
	  		$result = "<?php \n class xamp \n { \n $result  \n } \n ?>";
			$result .= rebuild(CLASS_PATH, false);
			file_put_contents('kernel.php', $result);
			require_once 'kernel.php';
	  	}

		speedAnalyzer('Чистим переменные');
	 	cleanup();
		session_start();
	}
	
	function rebuild($path, $removeTags = true)
	{
		$result = '';
		if ($handle = opendir($path))
		{
			while (false !== ($file = readdir($handle)))
			{ 
				if (preg_match('/\.php$/i', $file))
				{ 
					$result .= " \n \n \n// $file \n ".preg_replace('#(^\s*\<\?(php)?|\?\>\s*$|\t)#', '', file_get_contents($path."/$file"));
				} 
			}
			closedir($handle); 
		}
		if(!$removeTags) $result = "<?php $result ?>";
		return $result;
	}

	function cleanup()
	{
		//include_once 'class.jevix.php';
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
		$jevix->cfgSetAutoLinkMode(false);
		$errors = null;
		if (isset ($_POST)) foreach ($_POST as $key => $value) if(!is_array($value)) $_POST[$key] = $jevix -> parse ($value, $errors);
		if (isset ($_GET)) foreach ($_GET as $key => $value) $_GET[$key] = $jevix -> parse ($value, $errors);
		if (isset ($_COOKIE)) foreach ($_COOKIE as $key => $value) $_COOKIE[$key] = $jevix -> parse ($value, $errors);
		unset($jevix);
	}


	function speedAnalyzer($name = '', $start = false)
	{
		global $speedAnalyze, $speedAnalyzeLast;
		$current = getmicrotime();
		if(!$start) $start = $speedAnalyze;
		$diff = round(($current - $start)*1000);
		$result = array('<-'.($diff - $speedAnalyzeLast), $name, $current, $current - $start, $diff);
		$speedAnalyzeLast = $diff;
		//file_put_contents(CONTROLLER_PATH.'xamp.log', join("\t", $result)."\n", FILE_APPEND);
		return $result;
	}
	function getmicrotime() 
	{ 
	    list($usec, $sec) = explode(" ", microtime()); 
	    return ((float)$usec + (float)$sec); 
	} 


?>

