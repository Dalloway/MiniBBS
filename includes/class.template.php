<?php

/* Prepares and fetches data for the template. */
class Template {
	/* The current page's title. */
	public $title = '';
	
	/* Any additional HTML for the <head> */
	public $head = '';
	
	/* JavaScript to be run on load of document */
	public $onload = '';
	
	/* The buffered content of the page. PRIVATE! For render() only! */
	private $content = '';
	
	/* The name of a theme to override the default. */
	public $style_override = false;
	
	/* All "standard" links for the main menu; some of these will be unset by get_default_menu() */
	public $menu_options = array 
	(
		'Hot'       => 'hot_topics',
		'Topics'    => 'topics',
		'Bumps'     => 'bumps',
		'Replies'   => 'replies',
		'New topic' => 'new_topic',
		'Watchlist' => 'watchlist',
		'Bulletins' => 'bulletins',
		'Activity'  => 'activity',
		'Search'    => 'search',
		'Stuff'     => 'stuff',
		'You'       => 'history'
	);
	
	/* A multidimensional array of submenus, including custom submenus via $this->compile_menu(). */
	public $menu_children = array
	(
		'You' => array 
		(
			'Dashboard'  => 'dashboard',
			'Inbox'      => 'private_messages',
			'Restore ID' => 'restore_ID'
		)
	);
	
	/* Unix timestamp at script start */
	private $start_time;
		
	/* Begin buffering for the template. */
	public function __construct($start_time = null) {
		$this->start_time = (is_null($start_time) ? microtime(true) : $start_time);
		ob_start();
	}
	
	/* Finish buffering and render the template. Never pass user input to $template. */
	public function render($template = 'default') {
		if( ! empty($this->content)) {
			/* The template is already rendering. */
			exit();
		}
	
		$this->content = ob_get_contents();
		ob_end_clean();
		
		if(isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest') {
			$template = 'ajax';
		}
		
		/* Compress the page if enabled. */
		if(defined('MOD_ZIP') && MOD_GZIP) {
			ob_start('ob_gzhandler');
		}
		
		if($template === false) {
			echo $this->content;
		} else {
			require SITE_ROOT . '/includes/template.' . $template . '.php';
		}
		
		exit();
	}
	
	/* Should we use the default stylesheet or the user's setting? */
	public function get_stylesheet() {
		if( $this->style_override !== false) {
			return $this->style_override;
		}
		
		if( ! isset($_SESSION['settings']['style']) || ! file_exists(SITE_ROOT . '/style/themes/' . $_SESSION['settings']['style'] . '.css')) {
			return DEFAULT_STYLESHEET;
		}
		
		return $_SESSION['settings']['style'];
	}
	
	/* Return the default main menu. */
	public function get_default_menu() {
		return $this->compile_menu(DEFAULT_MENU);
	}
	
	/* Return the current user's main menu (custom or not). */
	public function get_user_menu() {
		global $notifications;
	
		/* Parse the user's custom menu string. */
		if( ! empty($_SESSION['settings']['custom_menu'])) {
			$main_menu = $this->compile_menu($_SESSION['settings']['custom_menu']);
		}
		
		/* The user does not have a valid custom menu. Use the default. */
		if(empty($main_menu)) {
			$main_menu = $this->get_default_menu();
		}
		
		/* If there are new reports, prepend a "Reports" link on the menu */
		if( ! empty($notifications['reports'])) {
			$main_menu = array_merge(array('Reports' => 'reports'), $main_menu);
		}
		
		return $main_menu;
	}
	
	/**
	 * Returns an array of menu links based on the user's custom menu string, recursing
	 * when a submenu {} block is found. Submenus are appended to $this->menu_children.
	 * This might look convoluted, but it's actually very fast (especially compared to regex.)
	 */
	private function compile_menu($string, $recurse = true) {
		$string = htmlspecialchars($string);
		$custom_url = false;
		$custom_text = false;

		/* Split the string apart at its spaces. */
		$token = strtok($string, ' ');
		while($token !== false) {
			/* Stupid hack to remove underscore */
			if($token == 'New_topic') {
				$token = 'New topic';
			}
		
			if(isset($this->menu_options[$token])) {
				/* This is something simple like "Bumps". */
				$menu[$token] = $this->menu_options[$token];
			} else if($token[0] == '[') {
				/* This is the beginning of a custom link. */
				if($custom_text !== false) {
					/* Reset the custom text -- done here in case a previous [] block never closed. */
					$custom_text = false;
				}
				$custom_url = ltrim($token, '[/');
			} else if($custom_url !== false) {
				/* We're already inside a custom link block. */
				if($custom_text !== false) {
					/* Restore the space removed by strtok(), since it's part of the link text. */
					$token = ' ' . $token;
				}
				$token_trim = rtrim($token, ']');
				if($token_trim !== $token) {
					/* We're at the end of a custom link block; add it to the menu and reset. */
					$menu[$custom_text . $token_trim] = $custom_url;
					$custom_url = false;
				} else {
					$custom_text .= $token;
				}
			} else if($token[0] == '{' && $recurse && count($menu) > 0) {
				/* This is the beginning of a submenu. We'll recurse afterward to avoid clobbering strtok() */
				$submenu = ltrim($token, '{') . ' ' . strtok('}');
				end($menu);
				$submenus[key($menu)] = $submenu;
			}
			$token = strtok(' ');
		}
		/* Now, compile any submenus. */
		if(isset($submenus)) {
			foreach($submenus as $parent => $submenu) {
				$this->menu_children[$parent] = $this->compile_menu($submenu, false);
			}
		}

		return $menu;
	}
	
	/* Transforms $text if $path has new items. */
	public function mark_new($text, $path) {
		global $last_actions, $notifications;
		
		switch($path) {
			case 'bumps':
			case 'topics':
			case 'bulletins':
				$cookie_name = 'last_' . rtrim($path, 's');
				if(isset($_COOKIE[$cookie_name]) && $_COOKIE[$cookie_name] < $last_actions[$cookie_name]) {
					$text = '<span class="new_items">' . $text . '<em>!</em></span>';
				}
			break;
			
			case 'history':
				if($notifications['citations']) {
					$text = '<span class="new_items">' . $text . ' <em><a href="'.DIR.'citations" class="help" title="'.$notifications['citations'].' new repl' . ($notifications['citations'] > 1 ? 'ies' : 'y') . ' to your replies">('.number_format($notifications['citations']).')</a></em></span>';
				}
			break;
			
			case 'watchlist':
				if($notifications['watchlist']) {
					$text = '<span class="new_items">' . $text . ' <em>('.number_format($notifications['watchlist']).')</em></span>';
				}
			break;
			
			case 'reports':
				if($notifications['reports']) {
					$text = '<span class="new_items">' . $text . ' <em>('.number_format($notifications['reports']).')</em></span>';
				}
			break;
			
			case 'private_messages':
				if($notifications['pms']) {
					$text = '<span class="new_items">' . $text . ' <em>('.number_format($notifications['pms']).')</em></span>';
				}
			break;
		}
		return $text;
	}
	
	/* Returns the (current) total execution time, SQL execution time and SQL query count. */
	public function get_stats() {
		global $db;
		
		$stats['total_time'] = round(microtime(true) - $this->start_time, 3);
		$stats['query_time'] = $db->query_time;
		$stats['query_count'] = $db->query_count;
		$stats['query_percent'] = round($stats['query_time'] * 100 / $stats['total_time']);
		
		return $stats;
	}
}

?>