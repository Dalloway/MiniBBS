<?php

/**
 * A multidimensional array of dashboard options, available from $_SESSION['settings'].
 * Each option is an array with the following possible keys:
 * 'default': The default value for users with no custom settings.
 *            Should always be a string, regardless of its actual type, in order to mirror the DB.
 *            Booleans should be either '0' or '1'.
 * 'type': 'int' or 'bool', for validation purposes. 'string' is assumed.
 * 'max_length': The maximum length in characters of the setting value.
 */
 
$default_dashboard = array (
	'memorable_name' => array (
		'default' => '',
		'max_length' => 100
	),
	'memorable_password' => array (
		'default' => '',
	),
	'email' => array (
		'default' => '',
		'max_length' => 100
	),
	'custom_menu' => array (
		'default' => '',
		'max_length' => 600
	),
	'topics_mode' => array (
		'default' => '0',
		'type' => 'bool'
	),
	'spoiler_mode' => array (
		'default' => '0',
		'type' => 'bool'
	),
	'ostrich_mode' => array (
		'default' => '0',
		'type' => 'bool'
	),
	'celebrity_mode' => array (
		'default' => '0',
		'type' => 'bool'
	),
	'text_mode' => array (
		'default' => '0',
		'type' => 'bool'
	),
	'custom_style' => array (
		'default' => '0',
		'type' => 'bool'
	),
	'snippet_length' => array (
		'default' => '80',
		'type' => 'int'
	),
	'posts_per_page' => array (
		'default' => POSTS_PER_PAGE_DEFAULT,
		'type' => 'int'
	),
	'style' => array (
		'default' => DEFAULT_STYLESHEET
	),
);
 
?>