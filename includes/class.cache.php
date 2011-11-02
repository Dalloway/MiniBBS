<?php

/* Caches a variable in either memory (if APC is available) or the /cache/ dir. Accessed :: statically. */
class cache {
	/* If APC isn't available, we'll write variables to this directory. The file name will be the key. */
	const CACHE_DIR = '/cache/';

	/* Get a variable from the store */
	public static function fetch($key) {
		if(ENABLE_CACHING) {
			if(function_exists('apc_fetch')) {
				return apc_fetch($key);
			}
			
			if(file_exists(SITE_ROOT . self::CACHE_DIR . $key . '.cache.php')) {
				require SITE_ROOT . self::CACHE_DIR . $key . '.cache.php';

				if(isset($cached_var)) {
					/* $cached_var is set in our cache file ($path) -- if not, the file must be corrupt */
					return $cached_var;
				}
			}
		}
		
		return false;
	}
	
	/* Overwrites or creates a cache record */
	public static function set($key, $value) {
		if(ENABLE_CACHING) {
			if(function_exists('apc_store')) {
				return apc_store($key, $value);
			}
			
			$res = @file_put_contents(SITE_ROOT . self::CACHE_DIR . $key . '.cache.php', '<?php $cached_var = ' . var_export($value, true) . ' ?>', LOCK_EX);
			if( ! $res) {
				trigger_error('Unable to cache ' . htmlspecialchars($key) . ' as a file. Please check that PHP has write access to ' . self::CACHE_DIR, E_USER_WARNING);
			}
			return $res;
		}
		return false;
	}
	
	/* Removes a variable from the cache store */
	public static function clear($key) {
		if(function_exists('apc_delete')) {
			return apc_delete($key);
		}
		
		if(file_exists(SITE_ROOT . self::CACHE_DIR . $key . '.cache.php')) {
			unlink(SITE_ROOT . self::CACHE_DIR . $key . '.cache.php');
		}
	}
}

?>