<?php
	include_once 'config.php';
	
	include_once 'class.dbcon.php';
	include_once 'class.jevix.php';
	include_once 'class.mail.php';
	include_once 'class.xfiles.php';
	include_once 'class.recaptcha.php';
	
	include_once 'class.xparse.php';
	include_once 'class.xgen.php';

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

		return '';
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
	
	header ("Content-type: text/". (isset ($_GET['xml']) && $_GET['xml'] == true ? 'xml' : 'html')."; charset=utf-8");

	if (array_key_exists('login', $_POST))
	{
		session_set_cookie_params(array_key_exists('loginForever', $_POST) ? 2592000 : 0, '/');
		session_start();
		session_regenerate_id(true);
	}
	else
	{
		session_start();
	}
		
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

	$request = (isset ($_GET['request']) && !empty ($_GET['request']) ? $_GET['request'] : '/');
	$s = split('/', $request);
	foreach ($s as $p) if (!empty ($p)) $path[] = $p;

	$db = new dbcon (DB_USER, DB_PASS, DB_NAME, DB_HOST, DB_PORT, true);
	
	$xgen = new xgen ($request, $path);
	$xgen -> start();
	unset($db);
	
	$_POST = array();
	
	if(class_exists('xsltCache'))
	{
		$proc = new xsltCache ();
		$proc -> importStyleSheet ($xgen -> xsl);
	}
	else
	{
		$proc = new XSLTProcessor;
		$proc -> importStyleSheet ($xgen->load( $xgen -> xsl ));
	}

	if (isset ($_GET['xml']) && $_GET['xml'] == true) {
		echo $xgen -> xml -> saveXML ();
	}else{	
		if (!isset($_SERVER['HTTP_X_REQUESTED_WITH'])) echo DOCTYPE;
		echo $proc -> transformToXML ($xgen -> xml);
	}
	unset($xgen);
	unset($proc);
?>

