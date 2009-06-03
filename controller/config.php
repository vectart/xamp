<?php
	ini_set ('default_charset', 'utf-8');

	// Primary XAMP settings
	define ('SITE_URL', 'http://localhost/');
	define ('REQUEST_URL_PREFIX', '');
	define ('REQUEST_URL', preg_replace('#(^'.REQUEST_URL_PREFIX.'|\?'.$_SERVER['QUERY_STRING'].'$)#', '', $_SERVER['REQUEST_URI']));
		// Also you can setup value of REQUEST_URL
		// define ('REQUEST_URL', $_GET['request']);
	define ('XML_SOURCE', $_GET['xml']);
	define ('XSL_CACHE', false);
	
	// MySQL database connection setttings
	define ('DB_HOST', 'localhost');
	define ('DB_PORT', '3306');
	define ('DB_USER', '');
	define ('DB_PASS', '');
	define ('DB_NAME', '');

	// Paths in filesystem
	define ('SITE_PATH', '../');
	define ('VIEW_PATH', SITE_PATH.'view/');
	define ('MODEL_PATH', SITE_PATH.'model/');
	define ('CONTROLLER_PATH', SITE_PATH.'controller/');
	define ('TMP_FILE_DIR', '/tmp/');
	
	// Recaptcha setup
	define ('RECAPTCHA_PUBLIC_KEY', '');
	define ('RECAPTCHA_PRIVATE_KEY', '');	
	
?>
