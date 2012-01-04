<?php
require './includes/bootstrap.php';
force_id();
$template->title = 'Ban user';

if( ! $perm->get('ban')) {
	error::fatal(m('Error: Access denied'));
}

if ( ! empty($_POST['target'])) {
	check_token();
	
	$target = trim($_POST['target']);
	
	/* Get type (ip, uid, wild or cidr) and validate as type. */
	try {
		$type = $perm->get_ban_type($target);
	} catch(Exception $e) {
		error::add($e->getMessage());
	}
	
	if ($_POST['length'] == 'indefinite' || $_POST['length'] == 'infinite' || $_POST['length'] === '0') {
		$ban_expiry = 0;
	} else if (strtotime($_POST['length']) > $_SERVER['REQUEST_TIME']) {
		$ban_expiry = strtotime($_POST['length']);
	} else {
		error::add('Invalid ban length.');
	}
		
	if($type == 'uid' && $perm->is_admin($target)) {
		error::add('You cannot ban an administrator.');
	}
	
	if($type == 'cidr') {
		list($subnet, $suffix) = explode('/', $target);
		
		if($suffix < 16) {
			error::add('You cannot ban a CIDR suffix less than 16.');
		}
	}
	
	if($type == 'wild' && ! preg_match('!^[0-9]+\.[0-9]+\.([0-9]+|\*)\.([0-9]+|\*)$!', $target)) {
		error::add('Invalid wildcard format. The first two parts of the IP cannot be treated as wildcards. The last two parts can contain either an octet or a wildcard -- not both.');
	}
		
	if(error::valid()) {
		$db->q('INSERT IGNORE INTO bans (`target`, `type`) VALUES (?, ?)', $target, $type);
		log_mod('ban_'.$type, $target, $ban_expiry, $_POST['reason']);
		
		/* Ban last IP of UID */
		if($type == 'uid' && isset($_POST['autoban_ip'])) {
			/* Check that we have permission to view this ID's IP. */
			$res = $db->q('SELECT post_count, first_seen FROM users WHERE uid = ?', $target);
			$uid = $res->fetchObject();
			
			if( ! $perm->get('limit_ip') || $perm->get('limit_ip_max') > $uid->post_count || $uid->first_seen > $_SERVER['REQUEST_TIME'] - 86400) {
				$res = $db->q('SELECT author_ip FROM replies WHERE author = ? ORDER BY time DESC LIMIT 1', $target);
				$last_ip = $res->fetchColumn();
				
				if($last_ip && ! $perm->ip_banned($last_ip, false)) {
					$db->q('INSERT IGNORE INTO bans (`target`, `type`) VALUES (?, ?)', $last_ip, 'ip');
					log_mod('ban_ip', $last_ip, $ban_expiry, trim($_POST['reason'] . ' (autoban)'));
				} else {
					unset($last_ip);
				}
			}
		}
		
		cache::clear('bans');
		
		/* Notify the affected user of their ban */
		if(ALLOW_BAN_READING) {
			$explanation = 'has been banned ';
			if($ban_expiry == 0) {
				$explanation .= 'indefinitely';
			} else {
				$explanation .= 'for ' . age($ban_expiry);
			}
			$explanation .= ' by ' . $perm->get_name($_SESSION['UID']) . '. ';
			if(empty($_POST['reason'])) {
				$explanation .= 'No reason was given.';
			} else {
				$explanation .= 'The following reason was given: ' . $_POST['reason'];
			}
			
			if($type == 'ip') {
				/* Fetch the UID that most recently posted from this IP. */
				$res = $db->q('SELECT author FROM replies WHERE author_ip = ? ORDER BY time DESC LIMIT 1', $target);
				$latest_id = $res->fetchColumn();
				
				system_message($latest_id, 'Your IP address ('.$target.') ' . $explanation);
			} else if($type == 'uid') {
				system_message($target, 'Your UID ' . $explanation);
			}
		}
		
		if($type == 'ip') {
			$redirect = 'IP_address/' . $target;
		} else if($type == 'uid') {
			$redirect = 'profile/' . $target;
		} else {
			$redirect = '';
		}
		
		redirect($target . (isset($last_ip) ? ' and ' . $last_ip : '') . ' banned.', $redirect);
	}

	error::output();
}
?>
<p>You can ban an IP address or UID for any given time ("1 day", "5 minutes", "infinite", etc.). You can also ban IP ranges, either using <a href="http://www.mediawiki.org/wiki/Help:Range_blocks#IPv4">CIDR notation</a> (e.g., <kbd>151.60.62.0/18</kbd> — suffix must be between /16 and /32) or wildcards (e.g., <kbd>151.60.*.*</kbd> — the first two octets cannot be used as wildcards).</p>

<form action="" method="post">
	<?php csrf_token() ?>
	<div class="row">
		<label for="ban_target" class="short">IP address or UID</label>
		<input type="text" name="target" id="ban_target" value="<?php if(isset($target)) echo htmlspecialchars($target) ?>" class="inline" size="34" tabindex="1" />
	</div>
	<div class="row">
		<label for="ban_length" class="short">Ban length</label>
		<input type="text" name="length" id="ban_length" value="<?php if(isset($_POST['length'])) echo htmlspecialchars($_POST['length']) ?>" class="inline help" tabindex="2" title="A ban length of 'indefinite' or '0' will never expire." />
	</div>
	<div class="row">
		<label for="ban_reason" class="short">Reason</label>
		<input type="text" name="reason" id="ban_reason" value="<?php if(isset($_POST['reason'])) echo htmlspecialchars($_POST['reason']) ?>" class="inline help" tabindex="3" size="55" title="Optional." />
	</div>
	<div class="row">
		<input type="submit" value="Ban" tabindex="4" class="short_indent"/>
	</div>
</form>
<?php
$template->render();
?>