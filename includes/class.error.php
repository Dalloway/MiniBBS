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
	public static function error_handler($level, $message, $file, $line) {
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
	
	/* Handles exceptions (usually issued by the database) */
	public static function exception_handler($exception) {
		global $perm;
		
		$is_admin = is_object($perm) && $perm->is_admin();
		$detailed_errors = PHP_ERROR_SHOW || $is_admin;
		$trace = $exception->getTrace();
		
		switch(get_class($exception)) {			
			case 'DatabaseConnectionException':
				$message = 'Failed to establish a connection to the database.';
			break;
				
			case 'PDOException':
				$message = DB_ERROR_MESSAGE;
				if($detailed_errors) {
					foreach($trace as $key => $step) {
						/* q refers to Database::q() */
						if(isset($step['function']) && $step['function'] == 'q') {
							$message = 'A database error occurred on line <strong>' . $step['line']. '</strong> of <strong>' . $step['file'] . '</strong>.';
						}
					}
				}
			break;
				
			default:
				$message = PHP_ERROR_MESSAGE;
		}

		if($detailed_errors) {
			$message .= ' Message: ' . htmlspecialchars($exception->getMessage());
			
			/* Connection traces are excluded because they may contain the DB password.*/
			if($is_admin && ! $exception instanceof DatabaseConnectionException) {
				/* TODO: Output pretty trace here. */
			}
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
			
			/**
			 * If DEFAULT_MENU is defined, we're probably far enough to render the default template
			 * without errors; otherwise we fallback on a barebones, configless template.
			 */
			if(defined('DEFAULT_MENU')) {
				$template->render();
			} else {
				$template->render('fallback');
			}
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