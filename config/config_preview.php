<?php
/**
 *            ! DO NOT EDIT BEFORE INSTALLING (to install: visit install.php) !
 * This file defines very basic configuration, such as your site's URL, the credentials for
 * the database, and how errors and caching should be handled. All other configuration is
 * stored in the database 'config' table and manipulated from the admin dashboard. (Because
 * DB configuration is cached, manually changing it in the database will probably affect
 * nothing -- the cache must be cleared afterwards.)
 *
 */

/* Automatically filled in by install.php */
$db_info = array
(
		'server'   => '%%DB_SERVER%%',
		'username' => '%%DB_USERNAME%%',
		'password' => '%%DB_PASSWORD%%',
		'database' => '%%DB_NAME%%'
);
	
define('HOSTNAME', '%%HOSTNAME%%');
define('DIR', '%%DIRECTORY%%');
define('URL', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] && $_SERVER['HTTPS'] != 'off' ? 'https://' : 'http://') . HOSTNAME . DIR);
define('SITE_FOUNDED', %%FOUNDED%%);

/* Should details of any PHP or database errors be shown to non-administrators? (Administrators will only see if the error occurs after authentication). This does not apply to E_PARSE or E_ERROR, just warnings. */
define('PHP_ERROR_SHOW', true);
/* If detailed PHP errors are disabled, this message will be shown instead. */
define('PHP_ERROR_MESSAGE', 'A PHP error occured.'); 
/* If detailed PHP errors are disabled, this will be shown on SQL syntax errors (etc.) */
define('DB_ERROR_MESSAGE', 'A database error occured.'); 

/**
 * Enables caching of certain variables, often from the database, using either APC (memory) or
 * the file system (/cache/ directory) via our cache class. MiniBBS isn't designed to run without
 * caching -- the performance hit will be relatively serious -- so this should only be disabled
 * for temporary debugging.
 */
define('ENABLE_CACHING', true); 