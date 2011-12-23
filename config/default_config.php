<?php

/**
 * UNLESS YOU'RE ADDING A NEW CONFIGURATION OPTION, DO NOT EDIT THIS FILE!
 * Your board's configuration is stored in the database and config.php, not here.
 * This file is only used to record the installation defaults. If you _ARE_ adding
 * a new configuration option to MiniBBS, you will need to add it here, insert it
 * into your "config" table, and put a form for it on admin_dashboard.php.
 * Remember to make all values in the below array 'strings' to mirror the database.
 * Booleans should be either '1' or '0'.
 */

$config_defaults = array
(
	'SITE_TITLE' => '',
	'MAILER_ADDRESS' => 'noreply@minibbs.org',
	'DEFAULT_MENU' => 'Bumps New_topic Watchlist Activity Stuff You',
	'POSTS_PER_PAGE_DEFAULT' => '0',
	'RECAPTCHA_ENABLE' => '1',
	'RECAPTCHA_PUBLIC_KEY' => '',
	'RECAPTCHA_PRIVATE_KEY' => '',
	'RECAPTCHA_MAX_UIDS_PER_HOUR' => '10',
	'RECAPTCHA_MAX_SEARCHES_PER_MIN' => '3',
	'ALLOW_IMAGES' => '1',
	'MAX_IMAGE_SIZE' => '6242880',
	'MAX_IMAGE_DIMENSIONS' => '240',
	'MAX_GIF_DIMENSIONS' => '200',
	'IMAGEMAGICK' => '0',
	'FANCY_IMAGE' => '0',
	'EMBED_VIDEOS' => '1',
	'DEFAULT_STYLESHEET' => 'Gmail Cloudy',
	'SALT' => '',
	'STRETCH' => '15',
	'USE_SHA256' => '1',
	'TRIP_SEED' => '',
	'MOD_GZIP' => '1',
	'ALLOW_BAN_APPEALS' => '1',
	'ALLOW_BAN_READING' => '1',
	'ITEMS_PER_PAGE' => '50',
	'MAX_LENGTH_HEADLINE' => '100',
	'MIN_LENGTH_HEADLINE' => '3',
	'MAX_LENGTH_BODY' => '30000',
	'MIN_LENGTH_BODY' => '3',
	'MAX_LINES' => '450',
	'MEMORABLE_TOPICS' => '1250',
	'REQUIRED_LURK_TIME_REPLY' => '10',
	'REQUIRED_LURK_TIME_TOPIC' => '10',
	'FLOOD_CONTROL_REPLY' => '10',
	'FLOOD_CONTROL_TOPIC' => '45',
	'POSTS_TO_DEFY_SEARCH_DISABLED' => '5',
	'POSTS_TO_DEFY_DEFCON_3' => '5',
	'ALLOW_USER_PM' => '1',
	'POSTS_FOR_USER_PM' => '5',
	'FLOOD_CONTROL_PM' => '20',
	'MAX_GLOBAL_PM' => '35',
	'MIN_BULLETIN_POSTS' => '50',
	'FLOOD_CONTROL_BULLETINS' => '600',
	'BULLETINS_ON_INDEX' => '2',
	'AUTOLOCK' => '0',
	'IMGUR_KEY' => '',
	'SIGNATURES' => '1',
	'FORCED_ANON' => '0'
);

?>