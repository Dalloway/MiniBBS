<?php
/* Check that PHP has write-permission for needed directories, and die if not. */
$unwritable_dirs = array
(
	'img', 'thumbs',
	'cache', 'config'
);

foreach($unwritable_dirs as $key => $dir) {
	if(is_writable(realpath(dirname(__FILE__)) . '/' . $dir)) {
		unset($unwritable_dirs[$key]);
	}
}

if( ! empty($unwritable_dirs)):
?>

<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8" />
	<title>Permission error</title>
	
	<style type="text/css">
	body {
		padding:3% 4%;
		background-color:#FFBCCD;
		color:#000;
		font-family: Arial;
	}
	
	#wrapper {
		max-width: 1000px;
		padding:.5% 2em;
		margin:auto;
		background-color:#fff;
		border-radius:1em;
	}
	
	h1 {
		text-align: center;
		font-family:georgia;
	}

	li {
		color:#B75461;
	}
	</style>
</head>

<body>
<div id="wrapper">
	<h1>Permission required</h1>
	
	<p>To install MiniBBS, PHP must have write-access to the following directories:</p>
	<ul>
	<?php foreach($unwritable_dirs as $dir): ?>
		<li>/<?php echo $dir ?>/</li>
	<?php endforeach ?>
	</ul>
	
	<p>Before installation can continue, you must chmod each of these directories to <kbd>777</kbd> (<kbd>0777</kbd>) — using either your FTP client (Google for help) or the <kbd>chmod</kbd> shell command.</p>
</div>
</body>
</html>

<?php
	exit();
endif;

/* Schema */
$tables = array();

$tables['activity'] = "CREATE TABLE IF NOT EXISTS `activity` (
  `uid` char(23) NOT NULL,
  `time` int(10) unsigned NOT NULL,
  `action_name` varchar(60) NOT NULL,
  `action_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`uid`),
  KEY `time` (`time`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;";

$tables['bans'] = "CREATE TABLE IF NOT EXISTS `bans` (
  `target` varchar(39) NOT NULL,
  `type` VARCHAR(9) NOT NULL, 
  `appealed` tinyint(1) unsigned NOT NULL,
  PRIMARY KEY (`target`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;";

$tables['bulletins'] = "CREATE TABLE IF NOT EXISTS `bulletins` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `message` text NOT NULL,
  `time` int(11) unsigned NOT NULL,
  `author` char(23) NOT NULL,
  `name` varchar(60) DEFAULT NULL,
  `trip` varchar(12) DEFAULT NULL,
  `ip` varchar(39) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ip` (`ip`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;";

$tables['citations'] = "CREATE TABLE IF NOT EXISTS `citations` (
  `uid` char(23) NOT NULL,
  `topic` int(11) unsigned NOT NULL,
  `reply` int(11) unsigned NOT NULL,
  KEY `uid` (`uid`),
  KEY `reply` (`reply`),
  KEY `topic` (`topic`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;";

$tables['config'] = "CREATE TABLE IF NOT EXISTS `config` (
  `name` varchar(38) NOT NULL,
  `value` text NOT NULL,
  PRIMARY KEY (`name`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;";

$tables['failed_postings'] = "CREATE TABLE IF NOT EXISTS `failed_postings` (
  `uid` char(23) NOT NULL,
  `time` int(10) unsigned NOT NULL,
  `reason` text NOT NULL,
  `headline` varchar(100) NOT NULL,
  `body` text NOT NULL,
  KEY `time` (`time`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;";

$tables['flood_control'] = "CREATE TABLE IF NOT EXISTS `flood_control` (
  `setting` varchar(50) NOT NULL,
  `value` varchar(50) NOT NULL,
  PRIMARY KEY (`setting`),
  KEY `value` (`value`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;";

$tables['groups'] = "CREATE TABLE IF NOT EXISTS `groups` (
  `id` int(5) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(32) NOT NULL,
  `link` varchar(32) NOT NULL,
  `edit_limit` int(11) unsigned NOT NULL,
  `post_reply` tinyint(1) unsigned NOT NULL,
  `post_topic` tinyint(1) unsigned NOT NULL DEFAULT '1',
  `post_image` tinyint(1) unsigned NOT NULL DEFAULT '1',
  `post_link` tinyint(1) unsigned NOT NULL DEFAULT '1',
  `pm_users` tinyint(1) unsigned NOT NULL,
  `pm_mods` tinyint(1) unsigned NOT NULL,
  `read_mod_pms` tinyint(1) unsigned NOT NULL,
  `read_admin_pms` tinyint(1) unsigned NOT NULL,
  `report` tinyint(1) unsigned NOT NULL,
  `handle_reports` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `delete` tinyint(1) unsigned NOT NULL,
  `undelete` tinyint(1) unsigned NOT NULL,
  `edit` tinyint(1) unsigned NOT NULL DEFAULT '1',
  `edit_others` tinyint(1) unsigned NOT NULL,
  `view_profile` tinyint(1) unsigned NOT NULL,
  `limit_ip` TINYINT( 1 ) UNSIGNED NOT NULL DEFAULT '1',
  `limit_ip_max` INT( 7 ) UNSIGNED NOT NULL DEFAULT '0',
  `ban` tinyint(1) unsigned NOT NULL,
  `stick` tinyint(1) unsigned NOT NULL,
  `lock` tinyint(1) unsigned NOT NULL,
  `delete_ip_ids` tinyint(1) unsigned NOT NULL,
  `nuke_id` tinyint(1) unsigned NOT NULL,
  `nuke_ip` tinyint(1) unsigned NOT NULL,
  `exterminate` tinyint(1) unsigned NOT NULL,
  `cms` tinyint(1) unsigned NOT NULL,
  `bulletin` tinyint(1) unsigned NOT NULL,
  `defcon` tinyint(1) unsigned NOT NULL,
  `defcon_all` tinyint(1) unsigned NOT NULL,
  `delete_all_pms` tinyint(1) unsigned NOT NULL,
  `admin_dashboard` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `manage_permissions` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `merge` TINYINT(1) UNSIGNED NOT NULL DEFAULT '0',
  `manage_messages` TINYINT( 1 ) UNSIGNED NOT NULL DEFAULT '0',
  `hide_log` TINYINT( 1 ) UNSIGNED NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;";

$tables['group_users'] = "CREATE TABLE IF NOT EXISTS `group_users` (
  `uid` char(23) NOT NULL,
  `group_id` int(5) unsigned NOT NULL,
  `log_name` varchar(60) NOT NULL,
  PRIMARY KEY (`uid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;";

$tables['ignore_lists'] = "CREATE TABLE IF NOT EXISTS `ignore_lists` (
  `uid` char(23) NOT NULL,
  `ignored_phrases` text NOT NULL,
  PRIMARY KEY (`uid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;";

$tables['images'] = "CREATE TABLE IF NOT EXISTS `images` (
  `file_name` varchar(80) NOT NULL,
  `original_name` varchar(80) NOT NULL,
  `md5` varchar(32) NOT NULL,
  `topic_id` int(10) unsigned DEFAULT NULL,
  `reply_id` int(10) unsigned DEFAULT NULL,
  `deleted` TINYINT( 1 ) UNSIGNED NOT NULL DEFAULT '0',
  UNIQUE KEY `reply_id` (`reply_id`),
  UNIQUE KEY `topic_id` (`topic_id`),
  KEY `md5` (`md5`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;";

$tables['last_actions'] = "CREATE TABLE IF NOT EXISTS `last_actions` (
  `feature` varchar(30) NOT NULL,
  `time` int(11) unsigned NOT NULL,
  PRIMARY KEY (`feature`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;";

$tables['messages'] = "CREATE TABLE IF NOT EXISTS `messages` (
  `key` VARCHAR( 255 ) NOT NULL ,
  `message` TEXT NOT NULL ,
  PRIMARY KEY ( `key` )
) ENGINE = MYISAM DEFAULT CHARSET=utf8;";

$tables['mod_actions'] = "CREATE TABLE IF NOT EXISTS `mod_actions` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT, 
  `action` varchar(255) NOT NULL,
  `type` varchar(20) NOT NULL,
  `target` text NOT NULL,
  `mod_uid` char(23) NOT NULL,
  `mod_ip` varchar(100) NOT NULL,
  `time` int(10) unsigned NOT NULL,
  `reason` text NOT NULL,
  `param` text NOT NULL,
  `hidden` TINYINT( 1 ) UNSIGNED NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `action` (`action`),
  KEY `type` (`type`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;";

$tables['notepad'] = "CREATE TABLE IF NOT EXISTS `notepad` (
  `uid` char(23) NOT NULL,
  `notepad_content` text NOT NULL,
  PRIMARY KEY (`uid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;";

$tables['pages'] = "CREATE TABLE IF NOT EXISTS `pages` (
  `id` int(6) unsigned NOT NULL AUTO_INCREMENT,
  `url` varchar(100) NOT NULL,
  `page_title` varchar(200) NOT NULL,
  `content` text NOT NULL,
  `markup` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `deleted` TINYINT(1) UNSIGNED NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `url` (`url`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;";

$tables['pm_ignorelist'] = "CREATE TABLE IF NOT EXISTS `pm_ignorelist` (
  `uid` char(23) NOT NULL,
  `ignored_uid` char(23) NOT NULL,
  KEY `uid` (`uid`,`ignored_uid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;";

$tables['pm_notifications'] = "CREATE TABLE IF NOT EXISTS `pm_notifications` (
  `uid` char(23) NOT NULL,
  `pm_id` int(11) unsigned NOT NULL,
  `parent_id` int(11) unsigned DEFAULT '0',
  KEY `uid` (`uid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;";

$tables['poll_options'] = "CREATE TABLE IF NOT EXISTS `poll_options` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `parent_id` int(11) unsigned NOT NULL,
  `option` text NOT NULL,
  `votes` int(11) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `parent_id` (`parent_id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;";

$tables['poll_votes'] = "CREATE TABLE IF NOT EXISTS `poll_votes` (
  `uid` char(23) NOT NULL,
  `ip` varchar(100) NOT NULL,
  `parent_id` int(11) unsigned NOT NULL,
  `option_id` int(11) unsigned DEFAULT NULL,
  KEY `uid` (`uid`,`parent_id`),
  KEY `ip` (`ip`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;";

$tables['private_messages'] = "CREATE TABLE IF NOT EXISTS `private_messages` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `parent` int(11) unsigned NOT NULL,
  `source` char(23) NOT NULL,
  `destination` char(23) NOT NULL,
  `contents` text NOT NULL,
  `time` int(11) unsigned NOT NULL,
  `name` varchar(60) DEFAULT NULL,
  `trip` varchar(12) DEFAULT NULL,
  `ignored` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `topic` int(11) unsigned DEFAULT NULL,
  `reply` int(11) unsigned NULL,
  PRIMARY KEY (`id`),
  KEY `source` (`source`),
  KEY `destination` (`destination`),
  KEY `parent` (`parent`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;";

$tables['replies'] = "CREATE TABLE IF NOT EXISTS `replies` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `parent_id` bigint(10) unsigned NOT NULL,
  `namefag` varchar(60) DEFAULT NULL,
  `tripfag` varchar(12) DEFAULT NULL,
  `link` varchar(60) DEFAULT NULL,
  `author` char(23) NOT NULL,
  `author_ip` varchar(100) NOT NULL,
  `time` int(10) unsigned NOT NULL,
  `body` text NOT NULL,
  `edit_time` int(10) unsigned DEFAULT NULL,
  `edit_mod` tinyint(1) unsigned DEFAULT NULL,
  `deleted` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `imgur` varchar(10) DEFAULT NULL,
  `original_parent` INT(11) UNSIGNED NULL,
  PRIMARY KEY (`id`),
  KEY `author` (`author`),
  KEY `parent_id` (`parent_id`),
  KEY `author_ip` (`author_ip`),
  KEY `time` (`time`),
  KEY `parent_time` (`parent_id`, `time`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;";

$tables['reports'] = "CREATE TABLE IF NOT EXISTS `reports` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `type` varchar(6) NOT NULL,
  `post_id` int(11) unsigned NOT NULL,
  `reason` text,
  `reporter` char(23) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `reporter` (`reporter`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;";

$tables['search_log'] = "CREATE TABLE IF NOT EXISTS `search_log` (
  `ip_address` varchar(50) NOT NULL,
  `time` int(15) unsigned NOT NULL,
  KEY `time` (`time`),
  KEY `ip_address` (`ip_address`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;";

$tables['topics'] = "CREATE TABLE IF NOT EXISTS `topics` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `time` int(10) unsigned NOT NULL,
  `author` char(23) NOT NULL,
  `namefag` varchar(60) DEFAULT NULL,
  `tripfag` varchar(12) COLLATE utf8_general_ci DEFAULT NULL,
  `link` varchar(60) DEFAULT NULL,
  `author_ip` varchar(100) NOT NULL,
  `replies` int(10) unsigned NOT NULL DEFAULT '0',
  `last_post` int(10) unsigned NOT NULL,
  `visits` int(10) unsigned NOT NULL DEFAULT '0',
  `headline` varchar(100) NOT NULL,
  `body` text NOT NULL,
  `edit_time` int(10) unsigned DEFAULT NULL,
  `edit_mod` tinyint(1) unsigned DEFAULT NULL,
  `deleted` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `sticky` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `locked` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `poll` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `poll_hide` tinyint(1) unsigned DEFAULT '0',
  `imgur` varchar(10) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `author` (`author`),
  KEY `author_ip` (`author_ip`),
  KEY `last_post` (`last_post`),
  KEY `time` (`time`),
  KEY `sticky_bump` (`sticky`, `last_post`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;";

$tables['users'] = "CREATE TABLE IF NOT EXISTS `users` (
  `uid` char(23) NOT NULL,
  `password` varchar(32) NOT NULL,
  `first_seen` int(10) unsigned NOT NULL,
  `last_seen` int(10) unsigned NOT NULL,
  `topic_visits` text NOT NULL,
  `ip_address` varchar(39) NOT NULL,
  `namefag` text NOT NULL,
  `post_count` INT(7) UNSIGNED NOT NULL DEFAULT '0',
  PRIMARY KEY (`uid`),
  KEY `ip_address` (`ip_address`,`first_seen`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;";

$tables['user_settings'] = "CREATE TABLE IF NOT EXISTS `user_settings` (
  `uid` char(23) NOT NULL,
  `memorable_name` varchar(100) DEFAULT NULL,
  `memorable_password` varchar(128) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `spoiler_mode` tinyint(1) DEFAULT NULL,
  `snippet_length` smallint(3) unsigned DEFAULT NULL,
  `posts_per_page` int(5) unsigned DEFAULT NULL,
  `topics_mode` tinyint(1) unsigned DEFAULT NULL,
  `ostrich_mode` tinyint(1) unsigned DEFAULT NULL,
  `style` varchar(18) DEFAULT NULL,
  `ajax_mode` tinyint(1) unsigned DEFAULT NULL,
  `celebrity_mode` smallint(1) unsigned DEFAULT NULL,
  `text_mode` smallint(1) unsigned DEFAULT NULL,
  `custom_style` INT( 8 ) UNSIGNED DEFAULT NULL,
  `custom_menu` text DEFAULT NULL,
  PRIMARY KEY (`uid`),
  KEY `memorable_name` (`memorable_name`),
  KEY `email` (`email`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;";

$tables['user_styles'] = "CREATE TABLE IF NOT EXISTS `user_styles` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `uid` char(23) NOT NULL,
  `md5` CHAR( 32 ) NOT NULL,
  `original` INT NOT NULL DEFAULT '0',  
  `style` text NOT NULL,
  `public` TINYINT( 1 ) UNSIGNED NOT NULL DEFAULT '0',
  `name` VARCHAR( 32 ) NULL DEFAULT NULL ,
  `trip` VARCHAR( 12 ) NULL DEFAULT NULL ,
  `color` VARCHAR( 25 ) NULL DEFAULT NULL ,
  `modified` INT( 10 ) UNSIGNED NOT NULL ,
  `title` VARCHAR( 100 ) NOT NULL,
  `basis` VARCHAR( 32 ) NULL,
  PRIMARY KEY (`id`),
  KEY `md5` (`md5`),
  KEY `original` (`original`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;";

$tables['watchlists'] = "CREATE TABLE IF NOT EXISTS `watchlists` (
  `uid` char(23) NOT NULL,
  `topic_id` int(10) unsigned NOT NULL,
  `new_replies` tinyint(1) unsigned NOT NULL DEFAULT '0',
  KEY `uid` (`uid`),
  KEY `topic_id` (`topic_id`),
  KEY `uid_2` (`uid`,`new_replies`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;";

$tables['whitelist'] = "CREATE TABLE IF NOT EXISTS `whitelist` (
  `uid` char(23) NOT NULL,
  UNIQUE KEY `uid` (`uid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;";

$tables['revisions'] = "CREATE TABLE IF NOT EXISTS `revisions` (
  `id` INT( 11 ) UNSIGNED NOT NULL AUTO_INCREMENT ,
  `type` VARCHAR( 20 ) NOT NULL ,
  `foreign_key` INT( 11 ) UNSIGNED NOT NULL ,
  `text` TEXT NOT NULL ,
  PRIMARY KEY ( `id` )
) ENGINE = MYISAM DEFAULT CHARSET=utf8;";

/* Set up the environment */
define('SITE_ROOT', realpath(dirname(__FILE__)));
require SITE_ROOT . '/includes/functions.php';
spl_autoload_register('load_class');
get_magic_quotes_runtime() and ini_set('magic_quotes_runtime', 0);
@set_time_limit(0);
if(get_magic_quotes_gpc()) {
	stripslashes_from_array($_GET);
	stripslashes_from_array($_POST);
}

/* Make sure we can install */
if(file_exists(SITE_ROOT . '/config/config.php')) {
	exit('Judging by the existence of config.php, MiniBBS is already installed.');
}
if( ! file_exists(SITE_ROOT . '/config/config_preview.php')) {
	exit('Unable to find /config/config_preview.php.');
}
if(phpversion() < 5.2) {
	exit('MiniBBS requires PHP 5.2 or greater; you appear to be running ' . phpversion() . '.');
}
if( ! extension_loaded('PDO')) {
	exit('You do not appear to have <a href="">PDO</a> installed.');
}
if( ! extension_loaded('pdo_mysql')) {
	exit('Your installation of PDO does not appear to have the MySQL driver.');
}

/* Set defaults */
$input = array
(
	'db_username' => '',
	'db_password' => '',
	'db_server' => 'localhost',
	'db_name' => '',
	'hostname' => getenv('HTTP_HOST'),
	'directory' => str_replace('//', '/', dirname($_SERVER['SCRIPT_NAME']) . '/'),
	'board_name' => '',
	'log_name' => '',
	'captcha_public' => '',
	'captcha_private' => ''
);

if(isset($_POST['form_sent'])) {
	$input = array_map('trim', $_POST['form']);
	$input['hostname'] = rtrim($input['hostname'], '/');
	
	/* Prepare config.php */
	$config_template = file_get_contents(SITE_ROOT . '/config/config_preview.php');
	$hard_config = array
	(
		'%%DB_USERNAME%%' => $input['db_username'],
		'%%DB_PASSWORD%%' => $input['db_password'],
		'%%DB_SERVER%%'   => $input['db_server'],
		'%%DB_NAME%%'     => $input['db_name'],
		'%%HOSTNAME%%'    => $input['hostname'],
		'%%DIRECTORY%%'   => $input['directory'],
		'%%FOUNDED%%'     => $_SERVER['REQUEST_TIME']
	);
	
	foreach($hard_config as $find => $replace) {
		$replace = addcslashes($replace, "'");
		$config_template = str_replace($find, $replace, $config_template);
		
		if($replace !== '' && strpos($config_template, $replace) === false) {
			error::add('Unable to add ' . $find . ' to config file.');
		}
	}
	
	/* Try setting up the database*/
	try {
		$db = new Database($input['db_username'], $input['db_password'], $input['db_server'], $input['db_name']);
	} catch(DatabaseConnectionException $e) {
		error::add('Unable to connect to the database; recheck your settings. Error message: ' . $e->getMessage());
	}
		
	if(error::valid()) {
		/* Create tables */
		foreach($tables as $table => $query) {
			$db->query($query) or error::add('Failed to create table "'.$table.'".');
		}
		
		$user_id = uniqid('', true);
		$password = generate_password();
		
		/* Create admin account */
		$db->query
		(
			"INSERT INTO `users` 
			(`uid`, `password`, `first_seen`, `last_seen`, `topic_visits`, `ip_address`, `namefag`) VALUES
			(".$db->quote($user_id).", ".$db->quote($password).", ".$_SERVER['REQUEST_TIME'].", ".$_SERVER['REQUEST_TIME'].", '', ".$db->quote($_SERVER['REMOTE_ADDR']).", '')"
		) or error::add('Failed to create first user.');
		/* Create basic user groups */
		$db->query
		(
			"INSERT IGNORE INTO `groups` 
			(`id`, `name`, `link`, `edit_limit`, `post_reply`, `post_topic`, `post_image`, `post_link`, `pm_users`, `pm_mods`, `read_mod_pms`, `read_admin_pms`, `report`, `handle_reports`, `delete`, `undelete`, `edit`, `edit_others`, `view_profile`, `ban`, `stick`, `lock`, `delete_ip_ids`, `nuke_id`, `nuke_ip`, `exterminate`, `cms`, `bulletin`, `defcon`, `defcon_all`, `delete_all_pms`, `admin_dashboard`, `manage_permissions`, `merge`, `limit_ip`, `limit_ip_max`, `manage_messages`, `hide_log`) VALUES
			(" . Permission::USER_GROUP . ", 'user', '', 600, 1, 1, 1, 1, 1, 1, 0, 0, 1, 0, 0, 0, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, 0, 0, 0, 0, 0, 0, 1, 0, 0, 0),
			(" . Permission::MOD_GROUP . ", 'mod', 'mod', 0, 1, 1, 1, 1, 1, 1, 1, 0, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 0, 1, 1, 0, 1, 1, 1, 0, 1, 0, 0, 1, 1, 35, 0, 1),
			(" . Permission::ADMIN_GROUP . ", 'admin', 'admin', 0, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 0, 0, 1, 1)"
		) or error::add('Failed to create user groups.');
		/* Set admin privs */
		$db->query
		(
			"INSERT INTO `group_users` 
			(`uid`, `group_id`, `log_name`) VALUES 
			(".$db->quote($user_id).", 3, ".$db->quote($input['log_name']).")"
		) or error::add('Failed to set admin privs for first user.');
		/* Legacy crap */
		$db->query("INSERT IGNORE INTO `flood_control` (`setting`, `value`) VALUES ('defcon', '5'), ('search_disabled', '0')") or error::add('Failed flood control insert.');
		/* Last actions */
		$db->query("INSERT IGNORE INTO `last_actions` (`feature`, `time`) VALUES ('last_bump', ".$_SERVER['REQUEST_TIME']."), ('last_topic', ".$_SERVER['REQUEST_TIME'].")");
		/* Mark-up page */
		$db->query("INSERT IGNORE INTO `pages` (`id`, `url`, `page_title`, `content`, `markup`) VALUES
(1, 'markup_syntax', 'Markup syntax', '<table>\r\n<thead>\r\n<tr>\r\n<th class=\"minimal\">Output</th>\r\n<th>Input</th>\r\n</tr>\r\n</thead>\r\n<tbody>\r\n\r\n<tr class=\"odd\">\r\n<td class=\"minimal\"><em>Italic</em></td>\r\n<td><kbd>''''Italic''''</kbd></td>\r\n</tr>\r\n\r\n<tr>\r\n<td class=\"minimal\"><strong>Bold</strong></td>\r\n<td><kbd>''''''Bold''''''</kbd></td>\r\n</tr>\r\n\r\n<tr class=\"odd\"><td class=\"minimal\"><span class=\"spoiler\">Spoiler</span> ? <span class=\"unimportant\">Hover over me!</span></td>\r\n<td><kbd>**Spoiler**</kbd></td>\r\n</tr>\r\n\r\n<tr><td class=\"minimal\"><u>Underline</u></td>\r\n<td><kbd>[u]Underline[/u]</kbd></td>\r\n</tr>\r\n\r\n<tr class=\"odd\"><td class=\"minimal\"><s>Strikethrough</s></td>\r\n<td><kbd>[s]Strikethrough[/s]</kbd></td>\r\n</tr>\r\n\r\n<tr><td class=\"minimal\"><span class=\"highlight\">Highlights</span></td>\r\n<td><kbd>[hl]Highlights[/hl]</kbd></td>\r\n</tr>\r\n\r\n<tr class=\"odd\">\r\n<td class=\"minimal\"><h4 class=\"user\">Header</h4></td>\r\n<td><kbd>==Header==</kbd></td>\r\n</tr>\r\n\r\n<tr>\r\n<td class=\"minimal\"><span class=\"quote\"><strong>></strong> Quote</span></td>\r\n<td><kbd>> Quote</kbd></td>\r\n</tr>\r\n\r\n<tr class=\"odd\">\r\n<td class=\"minimal\"><a href=\"http://example.com/\">Link text</a></td>\r\n<td><kbd>[http://example.com/ Link text]</kbd></td>\r\n</tr>\r\n\r\n<tr>\r\n<td class=\"minimal\"><span class=\"quote\"><strong>></strong> Block</span><br /><span class=\"quote\"><strong>></strong> quote</span></td>\r\n<td><kbd>[quote]Block<br />quote[/quote]</kbd></td>\r\n</tr>\r\n\r\n<tr class=\"odd\"><td class=\"minimal\"><div class=\"border\">Bordered text</div></td>\r\n<td><kbd>[border]Bordered text[/border]</kbd> - <span class=\"unimportant\">Use this when quoting from external sources.</span></td></tr>\r\n\r\n<tr><td class=\"minimal\"><pre>Code</pre></td>\r\n<td><kbd>[code]Code[/code]</kbd> - <span class=\"unimportant\">Use this when pasting code or ASCII art</span></td></tr>\r\n\r\n<tr class=\"odd\"><td class=\"minimal\"><pre style=\"font-family:IPAMonaPGothic,Mona,''MS PGothic'';font-size:16px;\">Shift JIS</pre></td>\r\n<td><kbd>[aa]Shift JIS[/aa]</kbd> - <span class=\"unimportant\">Use for Shift JIS ASCII art</span></td></tr>\r\n\r\n<tr><td class=\"minimal\"><div class=\"php\" style=\"background-color:#F0F0F0;border:#E1E1E1;padding:0.5em\"><code><span style=\"color: #000000\">\r\n<span style=\"color: #007700\">echo </span><span style=\"color: #DD0000\">''lorem ipsum''</span><span style=\"color: #007700\">;</span></span></div></td>\r\n<td><kbd>[php]echo ''lorem ipsum'';[/php]</kbd> - <span class=\"unimportant\">Use to highlight PHP</span></td></tr>\r\n\r\n<tr class=\"odd\">\r\n<td class=\"minimal\">[noparse]''''''not [s]parsed[/s]''''''[/noparse]</td>\r\n<td><kbd>''''''not [s]parsed[/s]''''''</kbd></td>\r\n</tr>\r\n\r\n<tr>\r\n<td class=\"minimal\">\r\n\r\n<table width=\"1%\">\r\n	<thead>\r\n		<tr>\r\n			<th class=\"minimal\">Blah </th>\r\n			<th class=\"minimal\">Blah </th>\r\n			<th>Main row </th>\r\n			<th class=\"minimal\">Blah</th>\r\n\r\n		</tr>\r\n	</thead>\r\n	<tbody>\r\n		<tr>\r\n			<td class=\"minimal\">asdf </td>\r\n			<td class=\"minimal\">dsfafa </td>\r\n			<td>fsadds fds </td>\r\n\r\n			<td class=\"minimal\">sdf fdsfds</td>\r\n		</tr>\r\n		<tr class=\"odd\">\r\n			<td class=\"minimal\">sdafds s </td>\r\n			<td class=\"minimal\">asdf </td>\r\n			<td>s </td>\r\n			<td class=\"minimal\">ssdf</td>\r\n\r\n		</tr>\r\n		<tr>\r\n			<td class=\"minimal\">scdf </td>\r\n			<td class=\"minimal\">vcxvxc </td>\r\n			<td>er aaaaaa</td>\r\n			<td class=\"minimal\">a</td>\r\n		</tr>\r\n\r\n	</tbody>\r\n</table>\r\n\r\n</td>\r\n\r\n<td>\r\n||Blah ||Blah ||!Main row ||Blah<br />\r\n|asdf |dsfafa |fsadds fds |sdf fdsfds<br />\r\n|sdafds s |asdf |s |ssdf<br />\r\n|scdf |vcxvxc |er aaaaaa|a\r\n</td>\r\n\r\n</tbody>\r\n</table>', 0)") or error::add('Failed to insert pages.');

		/* Load default config */
		require SITE_ROOT . '/config/default_config.php';
		
		/* Replace a few values with our own settings */
		$config_defaults['SITE_TITLE']            = $input['board_name'];
		$config_defaults['RECAPTCHA_PUBLIC_KEY']  = $input['captcha_public'];
		$config_defaults['RECAPTCHA_PRIVATE_KEY'] = $input['captcha_private'];
		$config_defaults['SALT']                  = generate_password();
		$config_defaults['TRIP_SEED']             = generate_password();
		
		/* Build the SQL query */
		$config_values = '';
		foreach($config_defaults as $key => $value) {
			$config_values .= '(' . $db->quote($key) . ', ' . $db->quote($value) . '), ';
		}
		$config_values = rtrim($config_values, ' ,');

		/* Insert config */
		$db->query
		(
			"INSERT IGNORE INTO `config` (`name`, `value`) VALUES " . $config_values
		) or error::add('Failed to insert config');
		
		/* Now create our config file */
		if(error::valid()) {
			if( ! @file_put_contents(SITE_ROOT . '/config/config.php', $config_template)) {
				error::add('Unable to create config.php.');
			} else {
				header('Location: http://' . $input['hostname'] . $input['directory'] . 'restore_ID/' . $user_id . '/' . $password);
				exit();
			}
		}
	}	
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8" />
	<title>MiniBBS installation</title>
	
	<style type="text/css">
	body {
		padding:3% 4%;
		background-color:#E0EBF9;
		color:#000;
		font-family: Arial;
	}
	
	#wrapper {
		max-width: 1000px;
		padding:.5% 2em;
		margin:auto;
		background-color:#fff;
	}
	
	h1 {
		text-align: center;
		font-family:georgia;
	}

	legend {
		font-weight:bold;
	}
	
	label {
		font-style:italic;
		float:left;
		padding-right:.6em;
		text-align:right;
		width:11em;
	}
	
	input[type=text] {
		padding:.3em;
		border:1px solid #9FCECE;
	}

	input:focus {
		background-color:#F7FCFF;
	}

	p.caption {
		margin-top:.1em;
		margin-left:11.5em;
	}

	div.row {
		margin-bottom:1em;
	}
	
		
	#error {
		background-color:#FFD8D8;
		padding:.3em;
		margin-bottom:0;
		font-size:100%;
		color:#990000;
	}

	.body {
		margin-top:0;
		border:1px solid #FFD8D8;
		border-top:none;
		padding:.7em;
		list-style:none;
	}
	</style>
</head>
<body>
<div id="wrapper">

<h1>MiniBBS installation</h1>
<p>Welcome to MiniBBS! If you have any problem installing, please let <a href="http://minibbs.org/">the developers</a> know.</p>

<?php error::output() ?>

<form action="" method="post">
	<fieldset>
		<legend>Database</legend>
		
		<div class="row">
			<label for="db_username">Database username</label>
			<input type="text" id="db_username" name="form[db_username]" value="<?php echo htmlspecialchars($input['db_username']) ?>" />
			<p class="caption">The username provided by your host to connect to your database.</p>
		</div>
		
		<div class="row">
			<label for="db_password">Database password</label>
			<input type="text" id="db_password" name="form[db_password]" value="<?php echo htmlspecialchars($input['db_password']) ?>" />
			<p class="caption">The password provided by your host to connect to your database.</p>
		</div>
		
		<div class="row">
			<label for="db_server">Database server</label>
			<input type="text" id="db_server" name="form[db_server]" value="<?php echo htmlspecialchars($input['db_server']) ?>" />
			<p class="caption">The hostname of your database server; often "localhost", but not always. Unsure? Check your host's FAQ.</p>
		</div>
		
		<div class="row">
			<label for="db_name">Database name</label>
			<input type="text" id="db_name" name="form[db_name]" value="<?php echo htmlspecialchars($input['db_name']) ?>" />
			<p class="caption">The name of the database you created for MiniBBS. Your host should provide an interface for creating databases.</p>
		</div>
	</fieldset>
	
	<fieldset>
		<legend>URL</legend>
		
		<div class="row">
			<label for="hostname">Hostname</label>
			<input type="text" id="hostname" name="form[hostname]" value="<?php echo htmlspecialchars($input['hostname']) ?>" />
			<p class="caption">The hostname (domain name with subdomain) of your new board, <em>not</em> including the directory or any slashes. For example, the hostname of <kbd>http://example.com/</kbd> would be <kbd>example.com</kbd>; the hostname of <kbd>http://forum.example.com/</kbd> would be <kbd>forum.example.com</kbd></p>
		</div>
		
		<div class="row">
			<label for="hostname">Directory</label>
			<input type="text" id="directory" name="form[directory]" value="<?php echo htmlspecialchars($input['directory']) ?>" />
			<p class="caption">The directory in which your board will reside, <em>including</em> the opening and (if applicable) closing slash. For example, the directory of <kbd>http://example.com/</kbd> would be simply <kbd>/</kbd>. The directory of <kbd>http://example.com/forum/</kbd> would be <kbd>/forum/</kbd>.</p>
		</div>
	</fieldset>
	
	<fieldset>
		<legend>Basic settings</legend>
		
		<p>You can reconfigure these options later from the admin dashboard.</p>
		
		<div class="row">
			<label for="board_name">Board name</label>
			<input type="text" id="board_name" name="form[board_name]" value="<?php echo htmlspecialchars($input['board_name']) ?>" />
			<p class="caption">The name of your board/site.</p>
		</div>
		
		<div class="row">
			<label for="log_name">Your screenname</label>
			<input type="text" id="log_name" name="form[log_name]" value="<?php echo htmlspecialchars($input['log_name']) ?>" />
			<p class="caption">Your personal screenname. This will appear in the mod logs for actions by your account.</p>
		</div>
		
		<div>
			<label for="captcha_public">reCAPTCHA public key</label>
			<input type="text" id="captcha_public" name="form[captcha_public]" value="<?php echo htmlspecialchars($input['captcha_public']) ?>" size="35" />
		</div>

		<div class="row">
			<label for="captcha_private">reCAPTCHA private key</label>
			<input type="text" id="board_name" name="form[captcha_private]" value="<?php echo htmlspecialchars($input['captcha_private']) ?>" size="35" />
			<p class="caption">In order for MiniBBS to properly deal with bots, you'll need to <a href="https://www.google.com/recaptcha/admin/create">generate these keys</a> using Google's free reCAPTCHA service (check "Enable this key on all domains"). If necessary, you can leave these blank and fill them in from the admin dashboard later.</p>
		</div>
	</fieldset>
	
	<p>That's it. Once installed, you can further configure your board from the admin dashboard linked on the "Stuff" page. Remember not to clear your cookies before setting a memorable name and password; you'll be logged in as an admin immediately. You should also delete this file (install.php) after installation.</p>
	
	<input type="submit" name="form_sent" value="Install" />
	
</form>

</div>
</body>
</html>