<?php
$script_start = microtime(true);
define('SITE_ROOT', realpath(dirname(__FILE__) . '/..'));

if( ! file_exists(SITE_ROOT . '/config/config.php')) {
	exit('MiniBBS is not (properly) installed.');
}

/* Globally required files */
require SITE_ROOT . '/config/config.php';
require SITE_ROOT . '/includes/functions.php';

/* Lazy load classes */
spl_autoload_register('load_class');

/* Error handling */
set_error_handler(array('error', 'error_handler'));
set_exception_handler(array('error', 'exception_handler'));

/* Globally required classes. Do not re-order. */
$template = new Template($script_start);
$db       = new Database($db_info['username'], $db_info['password'], $db_info['server'], $db_info['database']);
$lang     = new Language();
$perm     = new Permission();

/* Define configuration values */
$config = cache::fetch('config');
if($config === false) {
	$res = $db->q('SELECT name, value FROM config');
	$config = array_map('reset', array_map('reset', $res->fetchAll(PDO::FETCH_GROUP|PDO::FETCH_ASSOC)));
	cache::set('config', $config);
}
foreach($config as $name => $value) {
	define($name, $value);
}
unset($config);

/* If the calling file defines MINIMAL_BOOTSTRAP as true, we'll skip a few checks. */
defined('MINIMAL_BOOTSTRAP') or define('MINIMAL_BOOTSTRAP', false);

/* Initialize the environment */
if( ! MINIMAL_BOOTSTRAP) {
	date_default_timezone_set('UTC');
	define('MOBILE_MODE', check_user_agent('mobile'));
	header('Content-Type: text/html; charset=UTF-8');
	session_cache_limiter('nocache');
}
session_name('SID');
session_start();

/* Disable magic quotes. */
get_magic_quotes_runtime() and ini_set('magic_quotes_runtime', 0);
if(get_magic_quotes_gpc()) {
	stripslashes_from_array($_GET);
	stripslashes_from_array($_POST);
}

/* Are we using the hostname defined in config? */
if(HOSTNAME != getenv('HTTP_HOST')) {
	header('Location: ' . URL);
	exit();
}

/* Fetch DEFCON setting. */
$defcon = cache::fetch('defcon');
if($defcon === false) {
	$res = $db->q("SELECT value FROM flood_control WHERE setting = 'defcon'");
	$defcon = (int) $res->fetchColumn();
	cache::set('defcon', $defcon);
}
define('DEFCON', $defcon);

/* If necessary, assign the client a new ID. */
if(empty($_COOKIE['UID']) && ! $perm->is_banned($_SERVER['REMOTE_ADDR'])) {
	create_id();
} else if( ! empty($_COOKIE['password']) && ! isset($_SESSION['ID_activated'])) {
	/* Log in those who have just began their session. */
	if( ! activate_id($_COOKIE['UID'], $_COOKIE['password'])) {
		/* The password was incorrect. */
		create_id();
	}
}

/* Load the user's settings. */
if( ! isset($_SESSION['settings'])) {
	load_settings();
}

/* Set permissions for the current user */
$perm->set_group();

if(DEFCON < 2 && ! $perm->is_admin()) {
	exit(m('Lockdown mode'));
}

/* None of the following block is necessary for new visitors. */
if($_SESSION['ID_activated'] && ! MINIMAL_BOOTSTRAP) {
	/**
	 * If we're currently reading a PM, delete any notices for it.
	 * This is in the bootstrap rather than pm.php to ensure the accuracy of our next check. 
	 */
	if(isset($reading_pm) && ctype_digit($_GET['id'])) {
		$db->q('DELETE FROM `pm_notifications` WHERE `uid` = ? AND `parent_id` = ?', $_SESSION['UID'], $_GET['id']);
	}

	/* Check for unread PMs. */
	$res = $db->q('SELECT COUNT(*), parent_id, pm_id FROM pm_notifications WHERE uid = ? ORDER BY pm_id ASC', $_SESSION['UID']);
	list($notifications['pms'], $new_parent, $new_pm) = $res->fetch();
	if($notifications['pms'] > 0) {
		$_SESSION['notice'] = m('Notice: New PM', $new_parent. ($new_pm != $new_parent ? '#reply_'.$new_pm : ''), number_format($notifications['pms']));
		if($notifications['pms'] > 2) {
			$_SESSION['notice'] .= m('Notice: New PM clear');
		}
	}
	
	/* Get most recent actions to see if there's anything new. */
	$res = $db->q('SELECT feature, time FROM last_actions');
	$last_actions = array();
	while($row = $res->fetch(PDO::FETCH_ASSOC)) {
		$last_actions[$row['feature']] = $row['time'];
	}

	/* Check for replies to our replies. */
	$res = $db->q('SELECT COUNT(*) FROM citations WHERE uid = ?', $_SESSION['UID']);
	$notifications['citations'] = $res->fetchColumn();
	
	/* Check for replies to our watched topics. */
	$res = $db->q('SELECT COUNT(*) FROM watchlists WHERE uid = ? AND new_replies = 1', $_SESSION['UID']);
	$notifications['watchlist'] = $res->fetchColumn();
	
	/* Check for reports */
	if($perm->get('handle_reports')) {
		$res = $db->q('SELECT COUNT(*) FROM reports');
		$notifications['reports'] = $res->fetchColumn();
	}
}

/* Now that we've checked PMs, we can shut out banned users unless the calling file defines REPRIEVE_BAN. */
if( ! ALLOW_BAN_READING && ! defined('REPRIEVE_BAN')) {
	$perm->die_on_ban();
}

/* We use this to help cache custom stylesheets. */
if($_SESSION['settings']['custom_style'] && ! isset($_SESSION['style_last_modified'])) {
	$_SESSION['style_last_modified'] = $_SERVER['REQUEST_TIME'];
}

?>