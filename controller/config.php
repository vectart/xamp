<?php
	ini_set('default_charset', 'utf-8');

	// Primary XAMP settings
	define('XML_SOURCE', isset($_GET['xml']));
	define('REQUEST_SOURCE', $_SERVER['REQUEST_URI']);
	define('REQUEST_URL_PREFIX', '');
	define('REQUEST_URL', preg_replace('#(^'.REQUEST_URL_PREFIX.'|\?'.$_SERVER['QUERY_STRING'].'$)#', '', REQUEST_SOURCE));
	define('REQUEST_INDEX', 1);

	// MySQL database connection setttings
	define('DB_HOST', 'localhost');
	define('DB_PORT', '3306');
	define('DB_USER', '');
	define('DB_PASS', '');
	define('DB_NAME', '');

	// Paths in filesystem
	define('SITE_PATH', '../');
	define('VIEW_PATH', SITE_PATH.'view/');
	define('MODEL_PATH', SITE_PATH.'model/');
	define('CONTROLLER_PATH', SITE_PATH.'controller/');
	define('PLUGIN_PATH', CONTROLLER_PATH.'plugins/');
	define('CLASS_PATH', CONTROLLER_PATH.'classes/');
	define('TMP_FILE_DIR', SITE_PATH.'tmp/');
	
	// Recaptcha setup
	define('RECAPTCHA_PUBLIC_KEY', '');
	define('RECAPTCHA_PRIVATE_KEY', '');	

	// Cache setup
	define('XAMP_REBUILD', false);
	define('APC_CACHE', false);
	define('MEM_CACHE', false);
	define('TTL_SECONDS', 15);
	define('XML_CACHE', false);
	define('XSL_CACHE', false);

?>
