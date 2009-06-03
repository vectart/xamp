<?php
	include_once 'class.jevix.php';
	cleanup();
	
	include_once 'config.php';
	
	include_once 'class.dbcon.php';
	include_once 'class.mail.php';
	include_once 'class.xfiles.php';
	include_once 'class.recaptcha.php';
	
	include_once 'class.xparse.php';
	include_once 'class.xgen.php';
	
	header ("Content-type: text/". (XML_SOURCE == true ? 'xml' : 'html')."; charset=utf-8");

	session_start();
	
	$xgen = new xgen (REQUEST_URL);
	$xgen -> start();
	unset($xgen -> dbcon);
	
	$result = '';
	$is_xsl = is_file($xgen -> xsl);
	if (XML_SOURCE == true || !$is_xsl)
	{
		header ("Content-type: text/xml; charset=utf-8");
		$result = $xgen -> xml -> saveXML ();
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
			$proc -> importStyleSheet ($xgen->load( $xgen -> xsl ));
		}
		$result = $proc -> transformToXML ($xgen -> xml);
		if (isset($_SERVER['HTTP_X_REQUESTED_WITH'])) $result = preg_replace('#^\s*\<!DOCTYPE[^\>]+\>#', '', $result);
	}
	echo $result;

	unset($xgen);
	unset($proc);





	function cleanup()
	{
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

