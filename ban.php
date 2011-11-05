<?php
require './includes/bootstrap.php';
force_id();
$template->title = 'Ban user';

if( ! $perm->get('ban')) {
	error::fatal(MESSAGE_ACCESS_DENIED);
}

if ( ! empty($_POST['target'])) {
	check_token();
	
	if(filter_var($_POST['target'], FILTER_VALIDATE_IP)) {
		$type = 'ip';
	} else if(id_exists($_POST['target'])) {
		$type = 'uid';
	} else {
		error::add('That does not seem to be a valid IP or UID.');
	}
	
	if ($_POST['length'] == 'indefinite' || $_POST['length'] == 'infinite' || $_POST['length'] === '0') {
		$ban_expiry = 0;
	} else if (strtotime($_POST['length']) > $_SERVER['REQUEST_TIME']) {
		$ban_expiry = strtotime($_POST['length']);
	} else {
		error::add('Invalid ban length.');
	}
	
	if($type == 'ip') {
		$res = $db->q('SELECT uid FROM users WHERE ip_address = ?', $_POST['target']);
		while($uid = $res->fetchColumn()){
			if($perm->is_admin($uid)) {
				error::add('This IP is associated with an administrator.');
			}
			
			if($perm->is_mod($uid) && ! $perm->is_admin()) {
				error::add('This IP is associated with another moderator.');
			}
		}
	}
	else if($perm->is_admin($_POST['target'])) {
		error::add('You cannot ban an administrator.');
	}
		
	if(error::valid()) {
		$db->q('INSERT IGNORE INTO bans (target) VALUES (?)', $_POST['target']);
		cache::clear('bans');
		log_mod('ban_'.$type, $_POST['target'], $ban_expiry, $_POST['reason']);
		
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
				$res = $db->q('SELECT author FROM replies WHERE author_ip = ? ORDER BY time DESC LIMIT 1', $_POST['target']);
				$latest_id = $res->fetchColumn();
				
				system_message($latest_id, 'Your IP address ('.$_POST['target'].') ' . $explanation);
			} else {
				system_message($_POST['target'], 'Your UID ' . $explanation);
			}
		}
		
		redirect($_POST['target'] . ' banned.', ($type == 'ip' ? 'IP_address' : 'profile') . '/' . $_POST['target'] );
	}

	error::output();
}
?>
<p>You can ban either an IP address or UID for any given time ("1 day", "5 minutes", "infinite", etc.).</p>

<form action="" method="post">
	<?php csrf_token() ?>
	<div class="row">
		<label for="ban_target" class="short">IP address or UID</label>
		<input type="text" name="target" id="ban_target" value="<?php if(isset($_POST['target'])) echo htmlspecialchars($_POST['target']) ?>" class="inline" size="34" tabindex="1" />
	</div>
	<div class="row">
		<label for="ban_length" class="short">Ban length</label>
		<input type="text" name="length" id="ban_length" value="" class="inline help" tabindex="2" title="A ban length of 'indefinite' or '0' will never expire." />
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