<?php
function load_class($class) {
	require SITE_ROOT . '/includes/class.' . strtolower($class) . '.php';
}

function stripslashes_from_array(&$array) {
	foreach ($array as $key => $value) {
		if (is_array($value)) {
			stripslashes_from_array($array[$key]);
		} else {
			$array[$key] = stripslashes($value);
		}
	}
}

function check_user_agent($type) {
	$user_agent = strtolower($_SERVER['HTTP_USER_AGENT']);
	if($type == 'bot') {
		// Matches popular bots
		if(preg_match('/googlebot|adsbot|yahooseeker|yahoobot|bingbot|watchmouse|pingdom\.com|feedfetcher-google/', $user_agent)) {
			return true;
		}
	} else if($type == 'mobile') {
		// Matches popular mobile devices that have small screens and/or touch inputs
		if (preg_match('/phone|iphone|itouch|ipod|symbian|android|htc_|htc-|palmos|blackberry|opera mini|mobi|windows ce|nokia|fennec|hiptop|kindle|mot |mot-|webos\/|samsung|sonyericsson|^sie-|nintendo|mobile/', $user_agent)) {
			return true;
		}
	}
	return false;
}

function check_proxy($ip) {
	$ip = implode('.', array_reverse( explode('.', $ip) ));
	return ( gethostbyname($ip . '.rbl.efnetrbl.org') == '127.0.0.1' || gethostbyname($ip . '.niku.2ch.net') == '127.0.0.2' || gethostbyname($ip . '.80.208.77.188.166.ip-port.exitlist.torproject.org') == '127.0.0.2');
}

function hash_password($password) {
	for($i = 0; $i < STRETCH; ++$i) {
		if(USE_SHA256) {
			$password = hash('SHA256', SALT . $password);
		} else {
			$password = sha1(SALT . $password);
		}
	}
	return $password;
}

/* Converts 'user#tripcode' into array('user', '!3GqYIJ3Obs'). By AC. */
function tripcode($name_input) {
	$t = explode('#', $name_input);
	
	$name = $t[0];
	if (isset($t[1]) || isset($t[2])) {
		$trip = ((strlen($t[1]) > 0) ? $t[1] : $t[2]);
		if (function_exists ( 'mb_convert_encoding' )) {
			mb_substitute_character('none');
			$recoded_cap = mb_convert_encoding($trip, 'Shift_JIS', 'UTF-8');
		}
		$trip = ( ! empty($recoded_cap) ? $recoded_cap : $trip );
		$salt = substr($trip.'H.', 1, 2);
		$salt = preg_replace('/[^\.-z]/', '.', $salt);
		$salt = strtr($salt, ':;<=>?@[\]^_`', 'ABCDEFGabcdef');
		if(isset($t[2])) {
			// secure
			$trip = '!!' . substr(crypt($trip, TRIPSEED), (-1 * 10));
		} else {
			// insecure
			$trip = '!' . substr(crypt($trip, $salt), (-1 * 10));
		}
	}
	return array($name, $trip);
}

function create_id() {
	global $db;
	if(DEFCON < 5 || check_user_agent('bot')) {
		return false;
	}

	if(RECAPTCHA_ENABLE) {
		$res = $db->q('SELECT COUNT(*) FROM users WHERE ip_address = ? AND first_seen > (? - 3600)', $_SERVER['REMOTE_ADDR'], $_SERVER['REQUEST_TIME']);
		$uids_recent = $res->fetchColumn();
		if($uids_recent > RECAPTCHA_MAX_UIDS_PER_HOUR) { 
			show_captcha('Please enable cookies to use this site.');
		}
	}
		
	$user_id = uniqid('', true);
	$password = generate_password();
	
	$db->q
	(
		'INSERT INTO users 
		(uid, password, ip_address, first_seen, last_seen) VALUES 
		(?, ?, ?, ?, ?)', 
		$user_id, $password, $_SERVER['REMOTE_ADDR'], $_SERVER['REQUEST_TIME'], $_SERVER['REQUEST_TIME']
	);
	
	$_SESSION['first_seen'] = $_SERVER['REQUEST_TIME'];
	setcookie('UID', $user_id, $_SERVER['REQUEST_TIME'] + 315569260, '/');
	setcookie('password', $password, $_SERVER['REQUEST_TIME'] + 315569260, '/');
	$_SESSION['UID'] = $user_id;
	$_SESSION['topic_visits'] = array();
	$_SESSION['post_count'] = 0;
	$_SESSION['notice'] = 'Welcome to <strong>' . SITE_TITLE . '</strong>. An account has automatically been created and assigned to you. You don\'t have to register or <a href="'.DIR.'restore_ID">log in</a> to use the board, but don\'t clear your cookies unless you have <a href="'.DIR.'dashboard">set a memorable name and password</a>.';
}

function generate_password() {
	$characters = str_split('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789');
	$password = '';

	for($i = 0; $i < 32; ++$i) {
		$password .= $characters[array_rand($characters)];
	}
	return $password;
}

function activate_id($uid, $password) {
	global $db;
	if( ! empty($_SESSION['UID']) && $uid === $_SESSION['UID']) {
		// We're already logged in.
		$_SESSION['ID_activated'] = true;
		return true;
	}
	
	$res = $db->q('SELECT password, first_seen, topic_visits, namefag, post_count FROM users WHERE uid = ?', $uid);
	list($db_password, $first_seen, $topic_visits, $name, $post_count) = $res->fetch();
	
	if( ! empty($db_password) && $password === $db_password) {
		// The password is correct!
		$_SESSION['UID'] = $uid;
		// Our ID wasn't just created.
		$_SESSION['ID_activated'] = true;
		// For post.php
		$_SESSION['first_seen'] = $first_seen;
		// Our last name and tripcode
		$_SESSION['poster_name'] = $name;
		// Turn topic visits into an array
		$_SESSION['topic_visits'] = json_decode($topic_visits, true);
		$_SESSION['post_count'] = $post_count;
		
		// Set cookie
		if($_COOKIE['UID'] !== $_SESSION['UID']) {
			setcookie('UID', $_SESSION['UID'], $_SERVER['REQUEST_TIME'] + 315569260, '/');
			setcookie('password', $password, $_SERVER['REQUEST_TIME'] + 315569260, '/');
		}
		
		return true;
	}
	// If the password was wrong, create a new ID.
	return false;
}

function force_id() {
	global $db, $perm;
	
	if( ! $_SESSION['ID_activated']) {
		error::fatal('The page that you tried to access requires that you have a valid internal ID. This is supposed to be automatically created the first time you load a page here. Maybe you were linked directly to this page? Upon loading this page, assuming that you have cookies supported and enabled in your Web browser, you have been assigned a new ID. If you keep seeing this page, something is wrong with your setup; stop refusing/modifying/deleting cookies!');
	}
	
	if(ALLOW_BAN_READING && ! defined('REPRIEVE_BAN')) {
		$perm->die_on_ban();
	}
	
	if($_SESSION['post_count'] < 15 && ! $_SESSION['IP_checked']) {
		$res = $db->q("SELECT COUNT(*) FROM whitelist WHERE uid = ?", $_SESSION['UID']);
		$is_whitelisted = $res->fetchColumn();
		if( ! $is_whitelisted) {
			if(check_proxy($_SERVER['REMOTE_ADDR'])) {
				if( show_captcha('You appear to be using a proxy ('.htmlspecialchars($_SERVER['REMOTE_ADDR']).'). Please fill in the following CAPTCHA to whitelist your UID and continue. (If you already have a whitelisted UID, <a href="'.DIR.'restore_ID">restore it</a>.)') ) {
					$_SESSION['IP_checked'] = true;
					$db->q('INSERT INTO whitelist (uid) VALUES (?)', $_SESSION['UID']);
				}
			}
			else {
				$_SESSION['IP_checked'] = true;
			}
		
		}
		
	}
}

function load_settings() {
	global $db, $default_dashboard;
	
	if(isset($_SESSION['UID'])) {
		$res = $db->q('SELECT * FROM user_settings WHERE uid = ?', $_SESSION['UID']);
		$settings = $res->fetch(PDO::FETCH_ASSOC);
		if(isset($settings['uid'])) {
			unset($settings['uid']);
			$_SESSION['settings'] = $settings;
			$_SESSION['custom_settings'] = true;
			
			return true;
		}
	}
	
	/* No settings were in the database; load the defaults. */
	$settings = array();
	/* Contains $default_dashboard */
	require SITE_ROOT . '/includes/default_dashboard.php';
	
	foreach($default_dashboard as $option => $properties) {
		$settings[$option] = $properties['default'];
	}
	/* This is much faster than writing to $_SESSION in a loop. */
	$_SESSION['settings'] = $settings;
}

function show_captcha($message) {
	global $template;
	
	if(isset($_SESSION['is_human'])) {
		return true;
	}
	
	$template->title = 'CAPTCHA';
	require_once 'includes/recaptcha.php';
	
	if($_POST['recaptcha_response_field']) {
		$resp = recaptcha_check_answer(RECAPTCHA_PRIVATE_KEY, $_SERVER['REMOTE_ADDR'], $_POST['recaptcha_challenge_field'], $_POST['recaptcha_response_field']);

        if ($resp->is_valid) {
			$_SESSION['is_human'] = true;
			return true;
        } else {
			$error = $resp->error;
        }
	}
	if(empty($message)) {
		echo RECAPTCHA_NOTICE;
	} else {
		echo '<p>'.$message.'</p>';
	}
	echo '<form action="" method="post">';
	echo recaptcha_get_html(RECAPTCHA_PUBLIC_KEY, $error);
	echo '<input type="submit" value="Continue" />';
	foreach($_POST as $k => $v) { // Let the user resume as intended
		if($k == 'recaptcha_challenge_field' || $k == 'recaptcha_response_field') {
			continue;
		}
		if(is_array($v)) {
			foreach($v as $nk => $nv) {
				echo '<input type="hidden" name="'.htmlspecialchars($k).'['.htmlspecialchars($nk).']" value="'.htmlspecialchars($nv).'" />';
			}
		} else {
			echo '<input type="hidden" name="'.htmlspecialchars($k).'" value="'.htmlspecialchars($v).'" />';
		}
	}
	
	$template->render();
	exit();
}

function update_activity($action_name, $action_id = '') {
	global $db;
	
	if( ! isset($_SESSION['UID'])) {
		return false;
	}
	
	$db->q
	(
		'INSERT INTO activity 
		(time, uid, action_name, action_id) VALUES 
		(?, ?, ?, ?) ON DUPLICATE KEY UPDATE time = ?, action_name = ?, action_id = ?',
		$_SERVER['REQUEST_TIME'], $_SESSION['UID'], $action_name, $action_id, $_SERVER['REQUEST_TIME'], $action_name, $action_id
	);
}

function id_exists($id) {
	global $db;

	$uid_exists = $db->q('SELECT 1 FROM users WHERE uid = ?', $id);
	
	if($uid_exists->fetchColumn()) {
		return true;
	}
	return false;
}

function is_ignored(/* ... */) { 
	global $db;
	$fields = func_get_args();

	if($_SESSION['settings']['ostrich_mode']) {
		if( ! isset($_SESSION['ignored_phrases'])) {
			$fetch_ignore_list = $db->q('SELECT ignored_phrases FROM ignore_lists WHERE uid = ?', $_SESSION['UID']);
			$ignored_phrases = $fetch_ignore_list->fetchColumn();
			$ignored_phrases = array_filter(explode("\n", str_replace("\r", '', $ignored_phrases)));
			$_SESSION['ignored_phrases'] = $ignored_phrases;
		}
		
		// To make this work with Windows input, we need to strip out the return carriage.
		foreach($fields as $field) {
			foreach($_SESSION['ignored_phrases'] as $phrase) {
				if($phrase[0] == '/' && strlen($phrase < 28) && preg_match('|^/.+/$|', $phrase)) {
					if(preg_match($phrase, $field)) {
						return true;
					}
				}
				else if(stripos($field, $phrase) !== false) {
					return true;
				}
			}
		}
	}
	return false;
}

/* Removes whitespace bloat */
function super_trim($text) {
	static $nonprinting_characters = array
	(
		"\r",
		'­', //soft hyphen ( U+00AD)
		'﻿', // zero width no-break space ( U+FEFF)
		'​', // zero width space (U+200B)
		'‍', // zero width joiner (U+200D)
		'‌' // zero width non-joiner (U+200C)
	);
	/* Strip return carriage and non-printing characters. */
	$text = str_replace($nonprinting_characters, '', $text);
	 /* Trim and kill excessive newlines (maximum of 3). */
	return preg_replace( '/(\r?\n[ \t]*){3,}/', "\n\n\n", trim($text) );
}

/* Calculates the difference between two timestamps as a unit of time */
function age($timestamp, $comparison = null) {
	static $units = array
	(
		'second' => 60,
		'minute' => 60,
		'hour' => 24,
		'day' => 7,
		'week' => 4.25, 
		'month' => 12
	);
	if(is_null($comparison)) {
		$comparison = $_SERVER['REQUEST_TIME'];
	}
	$age_current_unit = abs($comparison - $timestamp);
	foreach($units as $unit => $max_current_unit) {
		$age_next_unit = $age_current_unit / $max_current_unit;
		if($age_next_unit < 1) { // Are there enough of the current unit to make one of the next unit?
			$age_current_unit = floor($age_current_unit);
			$formatted_age = $age_current_unit . ' ' . $unit;
			return $formatted_age . ($age_current_unit == 1 ? '' : 's');
		}
		$age_current_unit = $age_next_unit;
	}

	$age_current_unit = round($age_current_unit, 1);
	$formatted_age = $age_current_unit . ' year';
	return $formatted_age . (floor($age_current_unit) == 1 ? '' : 's');	
}

function format_date($timestamp) {
	return date('Y-m-d H:i:s \U\T\C — l \t\h\e jS \o\f F Y, g:i A', $timestamp);
}

function format_number($number) {
	if($number == 0) {
		return '-';
	}
	return number_format($number);
}

function format_name($name, $tripcode, $link = null, $poster_number = null) {
	if(empty($name) && empty($tripcode)) {
		$formatted_name = 'Anonymous';
		if(isset($poster_number)) {
			$formatted_name .= ' <strong>' . number_to_letter($poster_number) . '</strong>';
		}
	} else {
		$formatted_name = '<strong>' . htmlspecialchars(trim($name)) . '</strong>';
		if( ! empty($link)) {
			$formatted_name = '<a href="' . DIR . htmlspecialchars($link) . '">' . $formatted_name . '</a>';
		}
		$formatted_name .= ' ' . $tripcode;
	}
	
	return $formatted_name;
}

function number_to_letter($number) {
	$alphabet = range('A', 'Y');
	if($number < 24) {
		return $alphabet[$number];
	}
	$number = $number - 23;
	return 'Z-' . $number;
}

/* Remember to htmlspecialchars() the headline before passing it to this function. */
function format_headline($headline, $id, $reply_count, $poll, $locked, $sticky) {
	$headline = '<a href="'.DIR.'topic/' . $id . page($reply_count) . '"' . (isset($_SESSION['topic_visits'][$id]) ? ' class="visited"' : '') . '>' . $headline . '</a>';
		
	if($poll) {
		$headline .= ' <span class="poll_marker">(Poll)</span>';
	}
		
	if($_SESSION['settings']['posts_per_page'] && $reply_count > $_SESSION['settings']['posts_per_page']) {
		$headline .= ' <span class="headline_pages">[';
		for($i = 1, $comma = '', $pages = ceil($reply_count / $_SESSION['settings']['posts_per_page']); $i <= $pages; ++$i) {
			$headline .= $comma . '<a href="'.DIR.'topic/'.$id. '/' .$i.'">' . number_format($i) . '</a>';
			$comma = ', ';
		}
		$headline .= ']</span>';
	}
		
	$headline .= '<small class="topic_info">';
	if($locked) {
		$headline .= '[LOCKED]';
	}
	if($sticky) {
		$headline .= ' [STICKY]';
	}
	$headline .= '</small>';
	
	return $headline;
}

function replies($topic_id, $topic_replies) {
	$output = format_number($topic_replies);
	
	if( ! isset($_SESSION['topic_visits'][$topic_id])) {
		$output = '<strong>' . $output . '</strong>';
	} else if($_SESSION['topic_visits'][$topic_id] < $topic_replies) {
		$output .= ' <span class="new_replies">(<a href="' . DIR . 'topic/' . $topic_id . page($topic_replies, $_SESSION['topic_visits'][$topic_id] + 1) . '#new">';
		$new_replies = $topic_replies - $_SESSION['topic_visits'][$topic_id];
		if($new_replies != $topic_replies) {
			$output .= '<strong>' . $new_replies . '</strong> ';
		} else {
			$output .= 'all-';
		}
		$output .= 'new</a>)</span>';
	}
	
	return $output;
}

/**
 * Returns the part of a topic URL indicating the page.
 * @param int $total_replies The topic's total reply count.
 * @param int $reply_number  The reply number (not ID), counting from 1 at the beginning of the topic.
 */
function page($total_replies, $reply_number = null) {
	if( ! $_SESSION['settings']['posts_per_page'] || $total_replies <= $_SESSION['settings']['posts_per_page']) {
		/* Pagination is either disabled or unnecessary for this topic. */
		return '';
	}
	
	if( ! isset($reply_number)) {
		return '/1';
	}
	
	return '/' . ceil($reply_number / $_SESSION['settings']['posts_per_page']);
}

/* Prints a linked list of pages for the current topic. */
function topic_pages($reply_count) {
	if($_SESSION['settings']['posts_per_page'] && isset($_GET['page']) && $reply_count > $_SESSION['settings']['posts_per_page']) {
		$topic = (int) $_GET['id'];
		$pages = ceil($reply_count / $_SESSION['settings']['posts_per_page']);
		echo '<div class="topic_pages">';
		if($_GET['page'] > 1) {
			$prev = $_GET['page'] - 1;
			echo '<span class="topic_page"><a href="'.DIR.'topic/'.$topic.'/'.$prev.'">«</a></span>';
		}
		for($i = 1; $i <= $pages; ++$i) {
			if($i == $_GET['page']) {
				echo '<span class="topic_page current_page">' . number_format($i) . '</span> ';
			} else {
				echo '<span class="topic_page"><a href="'.DIR.'topic/'.$topic. '/' .$i.'">' . number_format($i) . '</a></span> ';
			}
		}
		if($_GET['page'] < $pages) {
			$next = $_GET['page'] + 1;
			echo '<span class="topic_page"><a href="'.DIR.'topic/'.$topic.'/'.$next.'">»</a></span>';
		}
		echo '<span class="topic_page all_pages"><a href="'.DIR.'topic/'.$topic.'">All</a></span></div>';
	}
}

function edited_message($original_time, $edit_time, $edit_mod) {
	if($edit_time) {
		echo '<p class="unimportant">(Edited ' . age($original_time, $edit_time) . ' later';
		if($edit_mod) {
			echo ' by a moderator';
		}
		echo '.)</p>';
	}
}

function encode_quote($body) {
	$body = trim(preg_replace('/^@([0-9,]+|OP)/m', '', $body));
	$body = preg_replace('/^/m', '> ', $body);
	return urlencode($body);
}

// To redirect to index, use redirect($notice, ''). To redirect back to referrer, 
// use redirect($notice). To redirect to /topic/1,  use redirect($notice, 'topic/1')
function redirect($notice = null, $location = null) {
	if( ! empty($notice)) {
		$_SESSION['notice'] = $notice;
	}
	
	if(is_null($location) && ! empty($_SERVER['HTTP_REFERER'])) {
		$location = $_SERVER['HTTP_REFERER'];
	}
	
	if(substr($location, 0, strlen(URL)) == URL){
		$location = substr($location, strlen(URL));
	}
	
	if(isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest') {
		$_SESSION['redirected_by_ajax'] = true;
	}
	
	header('Location: ' . URL . $location);
	exit;
}

function check_length($text, $name, $min_length, $max_length) {
	$text_length = strlen($text);

	if($min_length > 0 && empty($text)) {
		error::add('The ' . $name . ' can not be blank.');
	} else if($text_length > $max_length) {
		error::add('The ' . $name . ' was ' . number_format($text_length - $max_length) . ' characters over the limit (' . number_format($max_length) . ').');
	} else if($text_length < $min_length) {
		error::add('The ' . $name . ' was too short.');
	}
}

function csrf_token() { // Prevent cross-site redirection forgeries, create token.
	if( ! isset($_SESSION['token'])) {
		$_SESSION['token'] = md5(SALT . mt_rand());
	}
	echo '<input type="hidden" name="CSRF_token" value="' . $_SESSION['token'] . '" class="noscreen" />' . "\n";
}

function check_token() { // Prevent cross-site redirection forgeries, token check.
	if($_POST['CSRF_token'] !== $_SESSION['token']) {
		error::add(MESSAGE_TOKEN_ERROR);
		return false;
	}
	return true;
}

function thumbnail($source, $dest_name, $type) {
	$type = strtolower($type);
	switch($type) {
		case 'jpg':
			$image = imagecreatefromjpeg($source);
		break;
									
		case 'gif':
			$image = imagecreatefromgif($source);
		break;
									
		case 'png':
			$image = imagecreatefrompng($source);
		break;
	}
	$width = imagesx($image);
	$height = imagesy($image);
	$max_dimensions = ($type == 'gif' ? MAX_GIF_DIMENSIONS : MAX_IMAGE_DIMENSIONS);
	
	if($width > $max_dimensions || $height > $max_dimensions) {
		$percent = $max_dimensions / ( ($width > $height) ? $width : $height );
										
		$new_width = $width * $percent;
		$new_height = $height * $percent;
	} else {
		copy($source, 'thumbs/' . $dest_name);
		return true;
	}

	if(IMAGEMAGICK) {
		/* ImageMagick -- just use the CLI, it's much faster than PHP's extension */
		exec('convert ' . escapeshellarg($source) . ' -quality ' . ($type == 'gif' ? '75' : '90') . ' -resize ' . (int)$new_width. 'x' . (int)$new_height . ' ' . escapeshellarg(SITE_ROOT . '/thumbs/' . $dest_name) );
		return true;
	} else {
		/* GD */
		$thumbnail = imagecreatetruecolor($new_width, $new_height) ; 
		imagecopyresampled($thumbnail, $image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
		
		switch($type) {
			case 'jpg':
				imagejpeg($thumbnail, 'thumbs/' . $dest_name, 70);
			break;
									
			case 'gif':
				imagegif($thumbnail, 'thumbs/' . $dest_name);
			break;
									
			case 'png':
				imagepng($thumbnail, 'thumbs/' . $dest_name);
		}
				
		imagedestroy($thumbnail);
		if(gettype($image) == 'resource') {
			imagedestroy($image);
		}
	}
}

/* Soft delets a topic with logging and notification */
function delete_topic($id, $notify = true) {
	global $db, $perm;
	
	$res = $db->q('SELECT author, namefag, tripfag FROM topics WHERE id = ?', $id);
	list($author_id, $author_name, $author_trip) = $res->fetch();
	$author_name = trim($author_name . ' ' . $author_trip);
			
	if($perm->is_admin($author_id) && $_SESSION['UID'] != $author_id) {
		error::fatal(MESSAGE_ACCESS_DENIED);
	}
			
	/* Dump the image. */
	delete_image('topic', $id);
			
	/**
	 * Delete images of any replies. We keep the replies themselves for two reasons:
	 * - The topic author should be able to read non-deleted replies to their deleted topic.
	 * - It would be more difficult to reverse mod abuse otherwise.
	 */
	$reply_list = $db->q('SELECT id FROM replies WHERE parent_id = ?', $id);
	while($reply_id = $reply_list->fetchColumn()) {
		delete_image('reply', $reply_id);
	}

	$db->q("UPDATE topics SET deleted = '1' WHERE id = ?", $id);
	$db->q("DELETE FROM reports WHERE post_id = ? AND type = 'topic'", $id);
	$db->q("DELETE FROM citations WHERE topic = ?", $id);
	log_mod('delete_topic', $id, $author_name);
			
	if($author_id != $_SESSION['UID'] && $notify) {
		system_message($author_id, 'One of your topics was recently deleted. You can find its remains ['.URL.'topic/'.$id.' here].');
	}
}

/* Soft deletes a reply with logging and notification */
function delete_reply($id, $notify = true) {
	global $db, $perm;
	
	$res = $db->q('SELECT author, namefag, tripfag, time FROM replies WHERE id = ?', $id);
	list($author_id, $author_name, $author_trip, $reply_time) = $res->fetch();
	$author_name = trim($author_name . ' ' . $author_trip);
			
	if($perm->is_admin($author_id) && $_SESSION['UID'] != $author_id) {
		error::fatal(MESSAGE_ACCESS_DENIED);
	}
			
	$res = $db->q('SELECT parent_id, time FROM replies WHERE id = ?', $id);
	list($parent_id, $reply_time) = $res->fetch();

	if( ! $parent_id) {
		error::fatal('No such reply.');
	} else {
		delete_image('reply', $id);
	}
			
	$db->q("UPDATE replies SET deleted = '1' WHERE id = ?", $id);
	$db->q("DELETE FROM reports WHERE post_id = ? AND type = 'reply'", $id);
	$db->q("DELETE FROM citations WHERE reply = ?", $id);
						
	/* Reduce the parent's reply count. */
	$db->q('UPDATE topics SET replies = replies - 1 WHERE id = ?', $parent_id);
	
	/* Check if we need to fix the bump time */
	$res = $db->q('SELECT last_post, replies FROM topics WHERE id = ?', $parent_id);
	list($topic_bump, $topic_replies) = $res->fetch();
	if( ! $topic_replies) {
		$db->q('UPDATE topics SET last_post = time WHERE id = ?', $parent_id);
	} else if($topic_bump == $reply_time) {
		$db->q('UPDATE topics SET last_post = (SELECT time FROM replies WHERE parent_id = ? AND deleted = 0 ORDER BY time DESC LIMIT 1) WHERE id = ?', $parent_id, $parent_id);
	}
	
	log_mod('delete_reply', $id, $author_name);
			
	if($author_id != $_SESSION['UID'] && $notify) {
		system_message($author_id, 'One of your replies was recently deleted. You can find its remains ['.URL.'reply/'.$id.' here].');
	}
}

/* Removes an image from the database, and, if no other posts use it, from the file system */
function delete_image($mode = 'reply', $post_id) {
	global $db;
	
	if($mode != 'reply' && $mode != 'topic') {
		error::fatal('Invalid image deletion type.');
	}
	
	$img = $db->q('SELECT COUNT(*), file_name FROM images WHERE md5 IN (SELECT md5 FROM images WHERE '.$mode.'_id = ?)', $post_id);
	list($img_usages, $img_filename) = $img->fetch();
	if($img_filename) {
		if($img_usages == 1) {
			/* Only one post uses this image. Delete the file. */
			if(file_exists('img/'.$img_filename)) {
				unlink('img/'.$img_filename);
			}
			if(file_exists('thumbs/'.$img_filename)) {
				unlink('thumbs/'.$img_filename);
			}
		}
		$db->q('DELETE FROM images WHERE '.$mode.'_id = ? AND file_name = ? LIMIT 1', $post_id, $img_filename);
	}
}

/* Logs a moderator action. */
function log_mod($action, $target, $param = '', $reason = '') {
	global $db;
	
	switch ($action) {
		case 'delete_image':
		case 'delete_topic':
		case 'delete_reply':
		case 'delete_bulletin':
		case 'undelete_topic':
		case 'undelete_reply':
		case 'nuke_ip':
		case 'nuke_id':
			$type = 'delete';
		break;
			
		case 'edit_topic':
		case 'edit_reply':
			$type = 'edit';
		break;
		
		case 'ban_ip':
		case 'ban_uid':
		case 'unban_ip':
		case 'unban_uid':
			$type = 'ban';
		break;
			
		case 'stick_topic':
		case 'unstick_topic':
			$type = 'stick';
		break;
		
		case 'lock_topic':
		case 'unlock_topic':
			$type = 'lock';
		break;
		
		case 'cms_new':
		case 'cms_edit':
		case 'delete_page':
		case 'undelete_page':
			$type = 'cms';
		break;
		
		case 'merge':
		case 'unmerge':
			$type = 'merge';
		break;
		
		default:
			$type = $action;
	}
	
	$db->q
	(
		'INSERT INTO mod_actions 
		(action, type, target, mod_uid, mod_ip, reason, param, time) VALUES 
		(?, ?, ?, ?, ?, ?, ?, ?)',
		$action, $type, $target, $_SESSION['UID'], $_SERVER['REMOTE_ADDR'], $reason, $param, $_SERVER['REQUEST_TIME']
	);
}

/* Sends a PM from the board. */
function system_message($uid, $message) {
	global $db;

	$db->q
	(
			'INSERT INTO private_messages 
			(source, destination, contents, parent, time) VALUES 
			(?, ?, ?, ?, ?)',
			'system', $uid, $message, '0', $_SERVER['REQUEST_TIME']
	);
	if($new_id = $db->lastInsertId()) {
		$db->q('UPDATE private_messages SET parent = ? WHERE id = ?', $new_id, $new_id);
		$db->q('INSERT INTO pm_notifications (uid, pm_id, parent_id) VALUES (?, ?, ?)', $uid, $new_id, $new_id);
		
		return true;
	}
	
	return false;
}

/* Gets an array of stylesheet names */
function get_styles() {
	$styles =  glob(SITE_ROOT . '/style/themes/*.css');
	foreach($styles as $key => $path) {
		$styles[$key] = basename($path, '.css');
	}
	return $styles;
}
?>