<?php

/**
 * Handles bans & gets the current user's permissions, as determined by their group membership. The default
 * group is ID #1, 'user'. Posters who belong to other groups are listed in the "group_users" table.
 * The "group_users" columns are separated from the "users" table to allow for caching ($_SESSION 
 * would not allow us to immediately demod someone.) The "groups" table stores group settings.
 */
class Permission {
	/**
	 * Banned IPs, UIDs, wildcard and CIDR ranges, each in their own array.
	 * 'uid'  : Banned UIDs, stored as values with a numerical index.
	 * 'ip'   : Specifically banned IPs, stored as values with a numerical index.
	 * 'range': Banned wildcard and CIDR ranges, with the ban (e.g., 127.0.0.1/24 or 127.0.*.*
	 *          as key. The value is an array with two elements: The range's minimum IP in long
	 *          form, and the maximum IP in long form (ip2long).
	 */
	private $bans = array
	(
		'uid'   => array(),
		'ip'    => array(),
		'range' => array()
	);
	
	/* Results of ban checks are cached here, to avoid repeating the check on the same request. */
	private $ban_checks = array();
	
	/* Groups and their settings */
	private $groups = array();
	
	/* Users who belong to a non-default group, or who did at one point */
	private $group_users = array();
	
	/* The group ID to which the current user belongs */
	private $current_group = 1;
	
	/* Fetches a list of groups, group users and bans from either the cache or the database */
	public function __construct() {
		global $db;

		$group_users = cache::fetch('group_users');
		if($group_users === false) {
			$res = $db->q('SELECT uid, group_id, log_name FROM group_users');
			$group_users = array_map('reset', $res->fetchAll(PDO::FETCH_GROUP|PDO::FETCH_ASSOC));
			cache::set('group_users', $group_users);
		}
		$this->group_users = $group_users;
		
		$groups = cache::fetch('groups');
		if($groups === false) {
			$res = $db->q('SELECT * FROM groups');
			$groups = array_map('reset', $res->fetchAll(PDO::FETCH_GROUP|PDO::FETCH_ASSOC));
			cache::set('groups', $groups);
		}
		$this->groups = $groups;
		
		/* Sanity check: The first row of the groups table should always be the basic user group, since it's default. */
		if($this->groups[1]['name'] != 'user') {
			trigger_error('The default user group (group ID #1) should be named "user" in the database (not "'.htmlspecialchars($this->groups[1]['name']).'").', E_USER_ERROR);
		}
		
		$bans = cache::fetch('bans');
		if($bans === false) {
			/* Fetch bans, grouped into arrays by type */
			$res = $db->q('SELECT `type`, `target` FROM bans');
			$bans = $res->fetchAll(PDO::FETCH_COLUMN|PDO::FETCH_GROUP);
			
			/* Make sure 'ip', 'uid' and 'range' are set */
			$bans = array_merge($this->bans, $bans);
			
			/* Merge CIDR/wildcard bans into one array and get their ranges */
			if(isset($bans['cidr'])) {
				foreach($bans['cidr'] as $cidr) {	
					list($subnet, $suffix) = explode('/', $cidr);
					$min_ip = ip2long($subnet);
					/* Convert suffix to netmask */
					$min_ip &= ~(( 1 << (32 - $suffix)) - 1);
					$max_ip = $min_ip + pow(2, 32 - $suffix) - 1;
					
					$bans['range'][$cidr] = array($min_ip, $max_ip);
				}
				
				unset($bans['cidr']);
			}
			
			if(isset($bans['wild'])) {
				foreach($bans['wild'] as $wildcard) {
					$min_ip = ip2long(str_replace('*', '0', $wildcard));
					$max_ip = ip2long(str_replace('*', '255', $wildcard));
					
					$bans['range'][$wildcard] = array($min_ip, $max_ip);
				}
				
				unset($bans['wild']);
			}
			
			cache::set('bans', $bans);
		}
		$this->bans = $bans;
	}
	
	/* Sets the group of the current user */
	public function set_group() {
		if(empty($_SESSION['UID']) || ! array_key_exists($_SESSION['UID'], $this->group_users)) {
			/* Default -- just a lowly user. */
			$this->current_group = 1;
		} else {
			$this->current_group = $this->group_users[$_SESSION['UID']]['group_id'];
		}
	}
	
	/* Returns the value of group $setting for a UID */
	public function get($setting, $uid = null) {
		if($uid == null) {
			$group = $this->current_group;
		} else {
			$group = (isset($this->group_users[$uid]) ? $this->group_users[$uid]['group_id'] : 1);
		}
		return $this->groups[$group][$setting];
	}
	
	/* Fetch the log_name of a UID */
	public function get_name($uid = null) {
		$uid = (is_null($uid) ? $_SESSION['UID'] : $uid);
		return $this->group_users[$uid]['log_name'];
	}
	
	/* Fetches the UID of user with $log_name */
	public function get_uid($log_name) {
		foreach($this->group_users as $uid => $properties) {
			if($properties['log_name'] == $log_name) {
				return $uid;
			}
		}
		
		return false;
	}
	
	/* Returns an array of UIDs in $group_users with $permission */
	public function users_with_permission($permission) {
		$users = array();
		foreach($this->group_users as $uid => $tmp) {
			if($this->get($permission, $uid)) {
				$users[] = $uid;
			}
		}
		return $users;
	}
	
	/* Returns an array of groups */
	public function get_groups() {
		return $this->groups;
	}
	
	/* Checks admin status for either $uid (if set) or the current user */
	public function is_admin($uid = null) {
		$uid = (is_null($uid) ? $_SESSION['UID'] : $uid);
		if(isset($this->group_users[$uid]) && $this->group_users[$uid]['group_id'] == 3) {
			return true;
		}
		return false;
	}
	
	/* Checks mod status for either $uid (if set) or the current user */
	public function is_mod($uid = null) {
		$uid = (is_null($uid) ? $_SESSION['UID'] : $uid);
		if(isset($this->group_users[$uid]) && $this->group_users[$uid]['group_id'] == 2) {
			return true;
		}
		return false;
	}
	
	/* Returns the ban target if $ip is banned or affected by a range ban, or false otherwise. */
	public function ip_banned($ip, $range_check = true) {
		if(isset($this->ban_checks[$ip])) {
			return $this->ban_checks[$ip];
		}
		
		$res = false;
		
		if(in_array($ip, $this->bans['ip'])) {
			$res = $ip;
		}
		
		if($range_check && ! empty($this->bans['range'])) {
			$long_ip = ip2long($ip);
			
			foreach($this->bans['range'] as $ban => $range) {
				if($long_ip >= $range[0] && $long_ip <= $range[1]) {
					$res = $ban;
				}
			}
		}
		
		$this->ban_checks[$ip] = $res;
		
		return $res;
	}
	
	/* Returns true if $uid is banned, or false otherwise. */
	public function uid_banned($uid) {
		if(isset($this->ban_checks[$uid])) {
			return $this->ban_checks[$uid];
		}
		
		$res = in_array($uid, $this->bans['uid']);
		$this->ban_checks[$uid] = $res;
		
		return $res;
	}
	
	/* Kills the script if the current user's IP or UID is banned */
	public function die_on_ban() {
		global $db;
	
		if($this->uid_banned($_SESSION['UID'])) {
			$ban_target = $_SESSION['UID'];
			$ban_message = 'Your UID is banned.';
		} else if($ban_target = $this->ip_banned($_SERVER['REMOTE_ADDR'])) {
			$ban_message = 'Your IP address ('.$_SERVER['REMOTE_ADDR'].') is banned.';
		} else {
			return false;
		}

		list($ban_reason, $ban_expiry, $ban_time) = $this->get_ban_log($ban_target);
		$ban_appealed = $this->get_ban_appeal($ban_target);
		
		if($ban_expiry !== '0' && $ban_expiry < $_SERVER['REQUEST_TIME']) {
			/* The ban expired. */
			$db->q('DELETE FROM bans WHERE target = ?', $ban_target);
			cache::clear('bans');
			$_SESSION['notice'] = 'Your ban expired! Welcome back.';
			
			return false;
		}
		
		if( ! empty($ban_reason)) {
			$ban_message .= ' Reason: "<strong>' . htmlspecialchars($ban_reason) . '</strong>". ';
		}
		
		$ban_message .= ' This ban was filed ' . age($ban_time) . ' ago and ';
		if ($ban_expiry > 0) {
			$ban_message .= 'will expire in <strong>' . age($ban_expiry) . '</strong>.';
		} else {
			$ban_message .= 'is not set to expire.';
		}
		
		if($ban_appealed) {
			$ban_message .= ' You have already appealed this ban.';
		} else if(ALLOW_BAN_APPEALS) {
			$ban_message .= ' You may <a href="'.DIR.'appeal_ban">appeal</a>.';
		}
		
		error::fatal($ban_message);
	}
	
	/* Fetches the ban reason and expiry from the mod logs */
	public function get_ban_log($target) {
		global $db;
		
		$res = $db->q
		(
			"SELECT reason, param, time
			FROM mod_actions
			WHERE target = ? AND (action = 'ban_ip' OR action = 'ban_uid' OR action = 'ban_cidr' OR action = 'ban_wild')
			ORDER BY time DESC
			LIMIT 1", 
			$target
		);
		$ban_log = $res->fetch(PDO::FETCH_NUM);
		
		return $ban_log;
		
	}
	
	/* Returns whether $target has appealed their ban (as '1' or '0') */
	public function get_ban_appeal($target) {
		global $db;
		
		$res = $db->q('SELECT appealed FROM bans WHERE target = ?', $target);
		return $res->fetchColumn();
	}
	
	/* Returns 'uid', 'ip', 'cidr' or 'wild' depending on the character of $target. Throws exception for invalid formats. */
	public function get_ban_type($target) {
		if(filter_var($target, FILTER_VALIDATE_IP)) {
			$type = 'ip';
		} else if(strpos($target, '*') !== false) {
			$type = 'wild';
			
			if( ! filter_var(str_replace('*', '0', $target), FILTER_VALIDATE_IP)) {
				throw new Exception(htmlspecialchars($target) . ' is not a valid wildcard ban.');
			}
		} else if(strpos($target, '/') !== false) {
			$type = 'cidr';
			
			list($subnet, $suffix) = explode('/', $target);
			
			if( ! ctype_digit($suffix) || $suffix > 32) {
				throw new Exception('/' . htmlspecialchars($suffix) . ' is not a valid CIDR suffix.');
			}
			
			if( ! filter_var($subnet, FILTER_VALIDATE_IP)) {
				throw new Exception(htmlspecialchars($subnet) . ' is not a valid IP address.');
			}
		} else if(id_exists($target)) {
			$type = 'uid';
		} else {
			throw new Exception(htmlspecialchars($target) . ' does not seem to be a valid IP, UID or range ban.');
		}
		
		return $type;
	}
}

?>