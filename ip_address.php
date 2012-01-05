<?php
require './includes/bootstrap.php';
force_id();

if ( ! $perm->get('view_profile')) {
	error::fatal(m('Error: Access denied'));
}

// Validate IP address.
if ( ! filter_var($_GET['ip'], FILTER_VALIDATE_IP)) {
	error::fatal('That is not a valid IP address.');
}

$ip_address = $_GET['ip'];
$hostname = gethostbyaddr($ip_address);
if ($hostname === $ip_address) {
	$hostname = false;
}

$template->title  = 'Information on IP address ' . $ip_address;
$template->onload = 'focusId(\'ban_length\'); init();';

// Check for ban.
$banned = false;
if($perm->ip_banned($ip_address, false)) {
	list($ban_reason, $ban_expiry, $ban_filed) = $perm->get_ban_log($ip_address);
	if ( ! empty($ban_filed) && ($ban_expiry == 0 || $ban_expiry > $_SERVER['REQUEST_TIME']) ) {
		$banned = true;
	}
}

// Get statistics.
$res = $db->q('SELECT count(*) FROM topics WHERE author_ip = ?', $ip_address);
$ip_num_topics = $res->fetchColumn();
$res = $db->q('SELECT count(*) FROM replies WHERE author_ip = ?', $ip_address);
$ip_num_replies = $res->fetchColumn();
$res = $db->q('SELECT count(*) FROM users WHERE ip_address = ?', $ip_address);
$ip_num_ids = $res->fetchColumn();

echo '<p>This IP address (';
if ($hostname) {
	echo '<strong>' . $hostname . '</strong>';
} else {
	echo 'no valid host name';
}
echo ') is associated with <strong>' . (int) $ip_num_ids . '</strong> ID' . ($ip_num_ids == 1 ? '' : 's') . ' and has been used to post <strong>' . $ip_num_topics . '</strong> existing topic' . ($ip_num_topics == 1 ? '' : 's') . ' and <strong>' . $ip_num_replies . '</strong> existing repl' . ($ip_num_replies == 1 ? 'y' : 'ies') . '.</p>';
if ($banned) {
	echo '<p>This IP is currently <strong>banned</strong>. The ban was filed <span class="help" title="' . format_date($ban_filed) . '">' . age($ban_filed) . ' ago</span> and will ';
	if ($ban_expiry == 0) {
		echo 'last indefinitely';
	} else {
		echo 'expire in ' . age($ban_expiry);
	}
	echo '.</p>';
}
?>
<form action="<?php echo DIR ?>ban" method="post">
	<?php csrf_token() ?>
	<input type="hidden" name="target" value="<?php echo $ip_address ?>" />
	<div class="row">
		<label for="ban_length" class="inline">Ban length</label>
		<input type="text" name="length" id="ban_length" value="<?php if( ! $banned) echo '1 day' ?>" class="inline help" tabindex="1" title="A ban length of 'indefinite' or '0' will never expire." onclick="this.value = ''" />
		<label for="ban_reason" class="inline">Reason</label>
		<input type="text" name="reason" id="ban_reason" value="<?php echo htmlspecialchars($ban_reason) ?>" class="inline help" maxlength="100" tabindex="2" title="Optional." />
		<input type="submit" value="<?php echo ($banned) ? 'Update ban length' : 'Ban' ?>" class="inline" />
	</div>
</form>

<ul class="menu">
	<?php if($banned) echo '<li><a href="'.DIR.'unban_IP/' . $ip_address . '">Unban</a></li>' ?>
	<li><a href="<?php echo DIR; ?>delete_IP_IDs/<?php echo $ip_address ?>">Delete all IDs</a></li>
	<li><a href="<?php echo DIR; ?>nuke_IP/<?php echo $ip_address ?>">Delete all posts</a></li>
	<li><a target="_blank" href="http://whois.domaintools.com/<?php echo $ip_address ?>">Whois</a></li>
</ul>
<?php
if ($ip_num_ids > 0) {
	echo '<h4 class="section">IDs</h4>';
	
	$res = $db->q('SELECT uid, first_seen, post_count FROM users WHERE ip_address = ? ORDER BY post_count DESC, first_seen DESC LIMIT 5000', $ip_address);
	
	$columns  = array(
		'ID',
		'Post count ▼',
		'First seen'
	);
	$id_table = new Table($columns, 0);
	
	while ($id = $res->fetchObject()) {
		
		if($perm->get('limit_ip') && $perm->get('limit_ip_max') < $id->post_count) {
			/* We do not have permission to view this user's IP. */
			$values = array
			(
				'(Hidden.)',
				format_number($perm->get('limit_ip_max')) . '+',
				'?'
			);
		} else {
			$values = array
			(
				'<a href="'.DIR.'profile/' . $id->uid . '">' . $id->uid . '</a>',
				format_number($id->post_count),
				'<span class="help" title="' . format_date($id-first_seen) . '">' . age($id->first_seen) . '</span>'
			);
		}
		
		$id_table->row($values);
	}
	$id_table->output();
}
$template->render();
?>