<?php
	define ('DB_HOST', 'localhost');
	define ('DB_PORT', '3306');
	define ('DB_USER', 'user');
	define ('DB_PASS', 'password');
	define ('DB_NAME', 'database');

	define ('SITE_PATH', '/var/www/');
	define ('VIEW_PATH', SITE_PATH.'view/');
	define ('MODEL_PATH', SITE_PATH.'model/');
	define ('CONTROLLER_PATH', SITE_PATH.'controller/');
	define ('CORE_PATH', CONTROLLER_PATH);
	define ("DOCTYPE", "<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Strict//EN\" \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd\">\n");			
	define ('SITE_URL', 'http://localhost/');
	
	define ('RECAPTCHA_PUBLIC_KEY', '');
	define ('RECAPTCHA_PRIVATE_KEY', '');

	define ('TMP_FILE_DIR', '/tmp/');

	if (strpos (strtolower ($_SERVER['SERVER_SOFTWARE']), 'win'))
		ini_set ('include_path', CONTROLLER_PATH.';'.SITE_PATH.';');
	else
		ini_set ('include_path', CONTROLLER_PATH.':'.SITE_PATH.':');
?>
