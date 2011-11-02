<?php

/* Handles user input and PHP errors. Accessed :: statically. */
class error {
	/* An array of error message strings. */
	public static $errors = array();
	
	private static $levels = array
	(
		E_ERROR            => 'Error',
		E_WARNING          => 'Warning',
		E_PARSE            => 'Parsing error',
		E_NOTICE           => 'Notice',
		E_CORE_ERROR       => 'Core error',
		E_CORE_WARNING     => 'Core warning',
		E_COMPILE_ERROR    => 'Compile error',
		E_COMPILE_WARNING  => 'Compile warning',
		E_USER_ERROR       => 'User error',
		E_USER_WARNING     => 'User warning',
		E_USER_NOTICE      => 'User notice',
		E_STRICT           => 'Runtime notice'
	);
	
	/* Handles errors issued by PHP or trigger_error() */
	public static function handler($level, $message, $file, $line) {
		global $perm;
		
		if($level == E_NOTICE || $level == E_STRICT) {
			return;
		}

		if( PHP_ERROR_SHOW || (is_object($perm) && $perm->is_admin()) ) {
			$message = '<strong>' . self::$levels[$level] . ' (' . $level . '):</strong> ' . htmlspecialchars(strip_tags($message)) . ' in <strong>' . htmlspecialchars($file) . '</strong> on line <strong>' . $line . '</strong>';
			$message = str_replace(SITE_ROOT, '', $message);
		} else {
			$message = PHP_ERROR_MESSAGE;
		}
		
		self::fatal($message);
	}
	
	/* Adds an error message, which may be print()ed later */
	public static function add($message) {
		self::$errors[] = $message;
	}
	
	/* Prints an error message and kills the script */
	public static function fatal($message) {
		global $template;
	
		self::add($message);
		self::output();
		
		if(is_object($template)) {
			$template->title = 'Fatal error';
			$template->render();
		}
		exit();
	}
	
	/* Returns true if no errors have been issued */
	public static function valid() {
		return (bool) ! count(self::$errors);
	}
	
	/* If any errors are registered, print them. */
	public static function output() {
		if(count(self::$errors) == 0) {
			return;
		}
?>
<h3 id="error">Error</h3>
<ul class="body standalone">
<?php
	foreach(self::$errors as $error_message):
?>
	<li><?php echo $error_message?></li>
<?php
	endforeach;
?>
</ul>
<?php
	}
}
?>