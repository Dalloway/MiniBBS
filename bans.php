<?php
require './includes/bootstrap.php';

if( ! $perm->get('ban')) {
	error::fatal(MESSAGE_ACCESS_DENIED);
}

if(is_array($_POST['unban']) && check_token()) {
	foreach($_POST['unban'] as $target) {
		if(filter_var($target, FILTER_VALIDATE_IP)) {
			$type = 'ip';
		} else if(id_exists($target)) {
			$type = 'uid';
		} else {
			error::fatal('That does not seem to be a valid IP or UID.');
		}
		
		$db->q('DELETE FROM bans WHERE target = ?', $target);
		log_mod('unban_' . $type, $target);
	}
	cache::clear('bans');
	$count = count($_POST['unban']);
	$_SESSION['notice'] = $count . ' poster' . ($count === 1 ? '' : 's') . ' unbanned.';
}

$page = new Paginate();
$template->title   = 'Active bans';
if ($page->current > 1) {
	$template->title   .= ', page #' . number_format($page->current);
}

$res = $db->q
(
	"SELECT bans.target, mod_actions.action, mod_actions.reason, mod_actions.mod_uid, mod_actions.time, mod_actions.param AS expiry
	FROM bans
	INNER JOIN mod_actions ON bans.target = mod_actions.target
	WHERE mod_actions.action = 'ban_ip' OR mod_actions.action = 'ban_uid'
	GROUP BY bans.target
	ORDER BY mod_actions.time DESC
	LIMIT ".$page->offset.', '.$page->limit
);

$columns = array(
	'<input type="checkbox" name="master_checkbox" class="inline" onclick="checkAll(\'mass_ban\')" title="Check/uncheck all" />IP/UID',
	'Reason',
	'Mod',
	'Filed ▼',
	'Expiry'
);
$table = new Table($columns, 1);
		
while($ban = $res->fetchObject()) {	
	if($ban->expiry != 0 && $ban->expiry < $_SERVER['REQUEST_TIME']) {
		/* Expired. */
		continue;
	}

	$values = array
	(
		'<input type="checkbox" name="unban[]" value="'.$ban->target.'" class="inline" />' . ($ban->action == 'ban_ip' ? '<a href="'.DIR.'IP_address/'.$ban->target.'">'.$ban->target.'</a>' : '<a href="'.DIR.'profile/'.$ban->target.'">'.$ban->target.'</a>'),
		htmlspecialchars($ban->reason),
		'<a href="'.DIR.'profile/'.$ban->mod_uid.'">'.($perm->get_name($ban->mod_uid) ? $perm->get_name($ban->mod_uid) : $ban->mod_uid).'</a>',
		'<span class="help" title="' . format_date($ban->time) . '">' . age($ban->time) . '</span>',
		($ban->expiry == 0) ? 'Never' : age($ban->expiry)
	);
	
	$table->row($values);
}
echo '<form id="mass_ban" action="" method="post">';
csrf_token();
$table->output('(No one is currently banned.)');

if($table->row_count) {
	echo '<div class="row"><input type="submit" value="Unban selected" onclick="return confirm(\'Really unban selected posters?\');" class="inline" /></div>';
}
echo '</form>';

$page->navigation('bans', $table->row_count);

$template->render();
?>
