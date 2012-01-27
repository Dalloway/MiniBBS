<?php

/**
 * Eventually, this class will be used to retrieve any readable text for MiniBBS's
 * interface, so the text can be overriden by other languages (not yet implemented) or
 * custom strings (see the message manager). Default strings are stored in /lang/en.php;
 * custom strings are stored in the "messages" database table. (In most cases, we'll
 * need neither, loading both from the already merged 'lang' cache.)
 *
 * To retrieve a string, use: m('Message key', $param1, $param2);
 * The message syntax is inspired by MediaWiki: Parameters are given as $1, $2, etc.
 * {{DIR}} translates to our DIR constant (relative location of the forum).
 * {{URL}} translates to the URL constant (full URL of forum).
 * {{PLURAL:$1|chicken|chickens}} translates to "chicken" if $1 (the first parameter)
 * is one, or "chickens" otherwise.
 */
 
class Language {
	/* An array of strings for the interface */
	private $messages = array();
	
	/* The board's configured language as an ISO 639-1 code. */
	private $lang_code;
			
	/**
	 * Fetch the default English messages and override them with custom strings or translations where available.
	 * @param  string  $lang_code  A language code (ISO 639-1) corresponding to a file in the /lang/ directory.
	 */
	public function __construct($lang_code = 'en') {
		$this->lang_code = $lang_code;
		$messages = cache::fetch('lang');
		
		if($messages === false) {
			global $db;
			
			/* Fetch default messages (strings from en.php, overridden by translations where available) */
			$messages = $this->get_default_messages();
			
			/* Fetch customized messages and overwrite the defaults */
			$res = $db->q('SELECT `key`, `message` FROM messages');
			$custom_messages = array_map('reset', array_map('reset', $res->fetchAll(PDO::FETCH_GROUP|PDO::FETCH_ASSOC)));
			$messages = array_merge($messages, $custom_messages);
			
			cache::set('lang', $messages);
		}
		
		$this->messages = $messages;
	}
	
	/* Returns an array of all default messages (strings from en.php, overridden by translations where available). */
	public function get_default_messages() {
		/* Contains $messages (English defaults) */
		require SITE_ROOT . '/lang/en.php';
			
		/* If configured to use a different language, overwrite the English messages where translations exist. */
		if($this->lang_code !== 'en' && file_exists(SITE_ROOT . '/lang/' . $this->lang_code . '.php')) {
			$english = $messages;
			require SITE_ROOT . '/lang/' . $this->lang_code . '.php';
			$messages = array_merge($english, $messages);
			unset($english);
		}
		
		return $messages;
	}
	
	/* Returns the message with $key, parsed for $params and other syntax. Access with m() for convenience. */
	public function get($key, $params = array()) {
		if( ! isset($this->messages[$key])) {
			/* No message with that key exists. */
			return $key;
		}
		
		$message = $this->messages[$key];
		
		/* Replace $1, $2, etc. in the message with our params */
		if( ! empty($params)) {
			$param_markers = array();
			foreach($params as $i => $key) {
				$param_markers['$' . ($i + 1)] = $key;
			}
			/* Faster than str_replace when the needles ($1) are of equal length? */
			$message = strtr($message, $param_markers);
		}
		
		/* Replace {{DIR}} and {{PLURAL}} magic */
		if(strpos($message, '{') !== false) {
			$message = str_replace(array('{{DIR}}', '{{URL}}'), array(DIR, URL), $message);
			
			if(strpos($message, 'PLURAL') !== false && preg_match('/{{PLURAL:([0-9,]+)\|(.*?)\|(.*?)}}/s', $message, $match)) {
				$message = str_replace($match[0], ($match[1] == 1 ? $match[2] : $match[3]), $message);
			}
		}
		
		return $message;
	}
	
	/* Returns the message with $key, unparsed. */
	public function get_raw($key) {
		if( ! isset($this->messages[$key])) {
			return false;
		}

		return $this->messages[$key];
	}
	
	/* Returns an array of available languages (ISO 639-1 codes). */
	public function get_languages() {
		$langs = glob(SITE_ROOT . '/lang/*.php');
		foreach($langs as $key => $path) {
			$langs[$key] = basename($path, '.php');
		}
		return $langs;
	}
}

/* Retrieves a string from the language file -- a shortcut into the $lang object. m stands for message. */
function m(/* $key [, $param ...] */) {
	global $lang;

	$params = func_get_args();
	$key = array_shift($params);
	
	return $lang->get($key, $params);
}

/**
 * Retrieves a string from the language file with no parameters, caching the result (for this request only).
 * This is meant to optimize repeated requests for static strings that appear in a loop, such as the "Quote"
 * or "Cite" links in post menus. mc stands for message cache.
 */
function mc($key) {
	global $lang;
	
	static $message_cache = array();
	
	if(isset($message_cache[$key])) {
		return $message_cache[$key];
	}
	
	$res = $lang->get($key);
	$message_cache[$key] = $res;
	
	return $res;
}