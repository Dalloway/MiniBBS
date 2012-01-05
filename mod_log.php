<?php
require './includes/bootstrap.php';
update_activity('mod_log', 1);

$page = new Paginate();

if($page->current === 1) {
	$template->title = 'Latest moderator logs';
} else {
	$template->title = 'Mod logs, page #' . number_format($page->current);
}

setcookie('last_mod_action', $_SERVER['REQUEST_TIME'], $_SERVER['REQUEST_TIME'] + 315569260, '/');

$res = $db->q('SELECT COUNT(*) FROM mod_actions WHERE time > (? - 86400)', $_SERVER['REQUEST_TIME']);
$todays_count = $res->fetchColumn();

$res = $db->q("SELECT mod_uid, COUNT(*) AS action_count FROM mod_actions WHERE mod_uid != 'system' GROUP BY mod_uid ORDER BY action_count DESC");
/* Filter out junk from the resulting array. */
$stats = array_map('reset', array_map('reset', $res->fetchAll(PDO::FETCH_GROUP|PDO::FETCH_ASSOC)));
$total = array_sum($stats);
	
if($total > 0) {
	echo '<p>A total of <strong>' . number_format($total) . '</strong> actions have been taken by the mods (' . number_format($todays_count) . ' in the last 24 hours); ';
	$punctuation = '';
	foreach($stats as $mod_id => $count) {
		$mod_name = $perm->get_name($mod_id);
		if(empty($mod_name)) {
			$mod_name = 'an unnamed mod';
		}
		if($count == $total) {
			echo 'all by ' . $mod_name;
			break;
		}
		echo $punctuation . ' ' . number_format($count) . ' by <a href="'.DIR.'mod_log/mod/'.$mod_name.'">' . $mod_name . '</a>';
		$punctuation = ',';
	}
	echo '.</p>';
}

$searchable_types = array
(
	'' => 'All logs',
	'delete' => 'Deletion logs',
	'edit' => 'Edit logs',
	'ban' => 'Ban logs',
	'lock' => 'Lock logs',
	'stick' => 'Sticky logs',
	'defcon' => 'DEFCON logs',
	'cms' => 'CMS logs',
	'merge' => 'Merge logs',
	'system' => 'System logs'
);
?>
<fieldset>
	<legend>Search logs</legend>
	<form action="" method="post">
	<select name="log_type">
<?php
	foreach($searchable_types as $type => $label) {
		echo '<option value="'.$type.'"' . ($type == $_REQUEST['log_type'] ? ' selected="selected"' : '') . '>'.$label.'</option>';
	}
?>
	</select>
	
	<label class="inline">Mod:</label>
	<select name="mod">
		<option value="">All mods</option>
<?php
	foreach($stats as $mod_id => $tmp) {
		$mod_name = $perm->get_name($mod_id);
		echo '<option type="'.htmlspecialchars($mod_name).'"' . ($mod_name == $_REQUEST['mod'] ? ' selected="selected"' : '') . '>'.htmlspecialchars($mod_name).'</option>';
	}
?>
	</select>
	
	<label class="inline help" title="For example, an IP address or topic ID.">Target:</label>
	<input type="text" name="target" class="inline" value="<?php echo htmlspecialchars($_REQUEST['target']) ?>" />
	
	<input type="submit" value="Search" class="inline" />
	</form>
</fieldset>
<?php
$db->select('id, action, target, reason, param, mod_uid, time')->from('mod_actions');
if( ! empty($_REQUEST['log_type']) && isset($searchable_types[$_REQUEST['log_type']])) {
	$db->where('type = ?', $_REQUEST['log_type']);
}
if( ! empty($_REQUEST['mod']) && ($mod_id = $perm->get_uid($_REQUEST['mod']))) {
	$db->where('mod_uid = ?', $mod_id);
}
if( ! empty($_REQUEST['target']) and strlen($_REQUEST['target']) < 60) {
	/* Filter out anything not a dot or alphanumeric */
	$log->target = preg_replace('|[^0-9a-z\.]|', '', $_REQUEST['target']);
	$db->where('target = ?', $log->target);
}
$res = $db->order_by('time DESC')->limit($page->offset, $page->limit)->exec();

$columns = array
(
	'Mod',
	'Action',
	'Time ▼'
);

$table = new Table($columns, 1);

function censor_ip($ip) {
	$ip = explode('.', $ip, 2);
	return $ip[0] . '.' . preg_replace('|\d|', '*', $ip[1]);
}

while( $log = $res->fetchObject() ) {
	$undo = '';

	if($log->mod_uid === 'system') {
		$mod_name = m('System');
	} else {
		$mod_name = $perm->get_name($log->mod_uid);
		if(empty($mod_name)) {
			$mod_name = '?';
		}
		
		if ($perm->get('view_profile')) {
			$mod_name = '<a href="'.DIR.'profile/' . $log->mod_uid . '">' . $mod_name . '</a>';
		}
	}
	
	switch($log->action) {
		case 'db_maintenance':
			$action = 'Optimized the database; ' . number_format($log->param) . ' rows removed.';
		break;
	
		case 'delete_image': 
			$action = 'Deleted an image ('.htmlspecialchars($log->param).').';
		break;
		
		case 'delete_page':
			$action = 'Deleted a page.';
			
			if($perm->get('cms')) {
				$undo = '[<a href="'.DIR.'undelete_page/'.$log->target.'" onclick="return quickAction(this, \'Really undelete page?\');">undo</a>]';
			}
		break;
		
		case 'undelete_page':
			$action = 'Restored a page.';
			
			if($perm->get('cms')) {
				$undo = '[<a href="'.DIR.'delete_page/'.$log->target.'" onclick="return quickAction(this, \'Really delete page?\');">undo</a>]';
			}
		break;
		
		case 'edit_topic':
			$action = 'Edited <a href="'.DIR.'topic/'.$log->target.'">a topic</a>.';
			
			if($perm->get('edit_others')) {
				$undo = '[<a href="'.DIR.'revert_change/'.$log->param.'" onclick="return quickAction(this, \'Really revert that topic edit?\');">undo</a>]';
			}
		break;
		
		case 'edit_reply':
			$action = 'Edited <a href="'.DIR.'reply/'.$log->target.'">a reply</a>.';
			
			if($perm->get('edit_others')) {
				$undo = '[<a href="'.DIR.'revert_change/'.$log->param.'" onclick="return quickAction(this, \'Really revert that reply edit?\');">undo</a>]';
			}
		break;
		
		case 'ban_uid':
			if($log->target == $_SESSION['UID']) {
				$action = 'Banned <em>your</em> UID';
			} else if($perm->get('view_profile')) {
				$action = 'Banned <a href="'.DIR.'profile/'.$log->target.'">'.$log->target.'</a>';
			} else {
				$action = 'Banned a UID';
			}
			
			if($log->param == 0) {
				$action .= ' indefinitely.';
			} else {
				$action .= ' for ' . age($log->param, $log->time) . '.';
			}

			if($log->param != '0' && $log->param < $_SERVER['REQUEST_TIME']) {
				$undo = '<span class="undo">[expired]';
			} else if($perm->get('ban')) {
				$undo = '[<a href="'.DIR.'unban_poster/'.$log->target.'" onclick="return quickAction(this, \'Really unban '.$log->target.'?\');">undo</a>]';
			}
		break;
		
		case 'unban_uid':
			if($log->target == $_SESSION['UID']) {
				$action = 'Unbanned <em>your</em> UID.';
			} else if($perm->get('view_profile')) {
				$action = 'Unbanned <a href="'.DIR.'profile/'.$log->target.'">'.$log->target.'</a>.';
			} else {
				$action = 'Unbanned a UID.';
			}
		break;
		
		case 'ban_ip':
			if($log->target == $_SERVER['REMOTE_ADDR']) {
				$action = 'Banned <em>your</em> IP ('.$log->target.')';
			} else if($perm->get('view_profile')) {
				$action = 'Banned <a href="'.DIR.'IP_address/'.$log->target.'">'.$log->target.'</a>';
			} else {
				$action = 'Banned an IP ('.censor_ip($log->target).')';
			}
			
			if($log->param == 0) {
				$action .= ' indefinitely.';
			} else {
				$action .= ' for ' . age($log->param, $log->time) . '.';
			}

			if($log->param != '0' && $log->param < $_SERVER['REQUEST_TIME']) {
				$undo = '<span class="undo">[expired]';
			} else if($perm->get('ban')) {
				$undo = '[<a href="'.DIR.'unban_IP/'.$log->target.'" onclick="return quickAction(this, \'Really unban '.$log->target.'?\');">undo</a>]';
			}
		break;
		
		case 'unban_ip':
			if($log->target == $_SERVER['REMOTE_ADDR']) {
				$action = 'Unbanned <em>your</em> IP ('.$log->target.').';
			} else if($perm->get('view_profile')) {
				$action = 'Unbanned <a href="'.DIR.'IP_address/'.$log->target.'">'.$log->target.'</a>.';
			} else {
				$action = 'Unbanned an IP ('.censor_ip($log->target).').';
			}
		break;
		
		case 'ban_cidr':
			list($subnet, $suffix) = explode('/', $log->target);
			$affected = pow(2, 32 - $suffix);
			
			if($perm->get('view_profile')) {
				$action = 'Banned a CIDR range (' . htmlspecialchars($log->target) . ') of ' . number_format($affected) . ' IP addresses';
			} else {
				$action = 'Banned a CIDR range (' . htmlspecialchars( censor_ip($subnet) . '/' . $suffix) . ') of ' . number_format($affected) . ' IP addresses';
			}
			
			if($log->param == 0) {
				$action .= ' indefinitely.';
			} else {
				$action .= ' for ' . age($log->param, $log->time) . '.';
			}

			if($log->param != '0' && $log->param < $_SERVER['REQUEST_TIME']) {
				$undo = '<span class="undo">[expired]';
			} else if($perm->get('ban')) {
				$undo = '[<a href="'.DIR.'unban_CIDR/'.htmlspecialchars($log->target).'" onclick="return quickAction(this, \'Really unban '.htmlspecialchars($log->target, ENT_QUOTES).'?\');">undo</a>]';
			}
		break;
		
		case 'unban_cidr':
			list($subnet, $suffix) = explode('/', $log->target);

			if($perm->get('view_profile')) {
				$action = 'Unbanned a CIDR range (' . htmlspecialchars($log->target) . ').';
			} else {
				$action = 'Unbanned a CIDR range (' . htmlspecialchars( censor_ip($subnet) . '/' . $suffix) . ').';
			}
		break;
		
		case 'ban_wild':
			
			$action = 'Banned a wildcard range (' . htmlspecialchars($log->target) . ')';
			if($log->param == 0) {
				$action .= ' indefinitely.';
			} else {
				$action .= ' for ' . age($log->param, $log->time) . '.';
			}

			if($log->param != '0' && $log->param < $_SERVER['REQUEST_TIME']) {
				$undo = '<span class="undo">[expired]';
			} else if($perm->get('ban')) {
				$undo = '[<a href="'.DIR.'unban_wild/'.htmlspecialchars($log->target).'" onclick="return quickAction(this, \'Really unban '.htmlspecialchars($log->target, ENT_QUOTES).'?\');">undo</a>]';
			}
		break;
		
		case 'unban_wild':
			$action = 'Unbanned a wildcard range (' . htmlspecialchars($log->target) . ').';
		break;
		
		case 'stick_topic':
			$action = 'Stuck <a href="'.DIR.'topic/'.$log->target.'">a topic</a>.';
			if($perm->get('stick')) {
				$undo = '[<a href="'.DIR.'unstick_topic/'.$log->target.'" onclick="return quickAction(this, \'Really unstick that topic?\');">undo</a>]';
			}
		break;
		
		case 'unstick_topic':
			$action = 'Unstuck <a href="'.DIR.'topic/'.$log->target.'">a topic</a>.';
			if($perm->get('stick')) {
				$undo = '[<a href="'.DIR.'stick_topic/'.$log->target.'" onclick="return quickAction(this, \'Really sticky that topic?\');">undo</a>]';
			}
		break;
		
		case 'lock_topic':
			$action = 'Locked <a href="'.DIR.'topic/'.$log->target.'">a topic</a>.';
			if($perm->get('lock')) {
				$undo = '[<a href="'.DIR.'unlock_topic/'.$log->target.'" onclick="return quickAction(this, \'Really unlock that topic?\');">undo</a>]';
			}
		break;
		
		case 'unlock_topic':
			$action = 'Unlocked <a href="'.DIR.'topic/'.$log->target.'">a topic</a>.';
			if($perm->get('lock')) {
				$undo = '[<a href="'.DIR.'lock_topic/'.$log->target.'" onclick="return quickAction(this, \'Really lock that topic?\');">undo</a>]';
			}
		break;
		
		case 'delete_topic':
			if($perm->get('undelete')) {
				$action = 'Deleted <a href="'.DIR.'topic/'.$log->target.'">a topic</a>';
			} else {
				$action = 'Deleted a topic (#'.number_format((int)$log->target).')';
			}
			
			$log->param = trim($log->param);
			if(empty($log->param)) {
				$action .= ' by ' . m('Anonymous') . '.';
			} else {
				$action .= ' by ' . htmlspecialchars($log->param) . '.';
			}
			
			if($perm->get('undelete')) {
				$undo = '[<a href="'.DIR.'undelete_topic/'.$log->target.'" onclick="return quickAction(this, \'Really restore that topic?\');">undo</a>]';
			}
		break;
		
		case 'delete_reply':
			if($perm->get('undelete')) {
				$action = 'Deleted <a href="'.DIR.'reply/'.$log->target.'">a reply</a>';
			} else {
				$action = 'Deleted a reply (#'.number_format((int)$log->target).')';
			}
			
			$log->param = trim($log->param);
			if(empty($log->param)) {
				$action .= ' by ' . m('Anonymous') . '.';
			} else {
				$action .= ' by ' . htmlspecialchars($log->param) . '.';
			}
			
			if($perm->get('undelete')) {
				$undo = '[<a href="'.DIR.'undelete_reply/'.$log->target.'" onclick="return quickAction(this, \'Really restore that reply?\');">undo</a>]';
			}
		break;
		
		case 'undelete_topic':
			$action = 'Restored <a href="'.DIR.'topic/'.$log->target.'">a topic</a>.';
			
			if($perm->get('delete')) {
				$undo = '[<a href="'.DIR.'delete_topic/'.$log->target.'" onclick="return quickAction(this, \'Really delete that topic?\');">undo</a>]';
			}
		break;
		
		case 'undelete_reply':
			$action = 'Restored <a href="'.DIR.'reply/'.$log->target.'">a reply</a>.';
			
			if($perm->get('delete')) {
				$undo = '[<a href="'.DIR.'delete_reply/'.$log->target.'" onclick="return quickAction(this, \'Really delete that reply?\');">undo</a>]';
			}
		break;
		
		case 'delete_bulletin':
			$action = 'Deleted a bulletin.';
		break;
		
		case 'delete_ip_ids':
			if($perm->get('view_profile')) {
				$action = 'Purged all UIDs associated with <a href="'.DIR.'IP_address/'.$log->target.'">'.$log->target.'</a>.';
			} else {
				$action = 'Purged all UIDs associated with an IP ('.censor_ip($log->target).').';
			}
		break;
		
		case 'nuke_id':
			if($log->target == $_SESSION['UID']) {
				$action = 'Nuked <em>your</em> UID.';
			} else if($perm->get('view_profile')) {
				$action = 'Nuked <a href="'.DIR.'profile/'.$log->target.'">'.$log->target.'</a>.';
			} else {
				$action = 'Nuked an ID.';
			}
		break;
		
		case 'nuke_ip':
			if($log->target == $_SERVER['REMOTE_ADDR']) {
				$action = 'Nuked <em>your</em> IP ('.$log->target.').';
			} else if($perm->get('view_profile')) {
				$action = 'Nuked <a href="'.DIR.'IP_address/'.$log->target.'">'.$log->target.'</a>.';
			} else {
				$action = 'Nuked an IP ('.censor_ip($log->target).').';
			}
		break;
		
		case 'defcon':
			$action = 'Adjusted the DEFCON to '.$log->target.'.';
			if($perm->get('defcon')) {
				$undo = '[<a href="'.DIR.'defcon">undo</a>]';
			}
		break;
		
		case 'cms_new':
			$action = 'Created a page (<a href="'.DIR. htmlspecialchars($log->target).'">'.htmlspecialchars($log->target).'</a>).';
		break;	
		
		case 'cms_edit':
			$action = 'Edited a page (<a href="'.DIR. htmlspecialchars($log->target).'">'.htmlspecialchars($log->target).'</a>).';
			
			if($perm->get('cms')) {
				$undo = '[<a href="'.DIR.'revert_change/'.$log->param.'" onclick="return quickAction(this, \'Really revert that page edit?\');">undo</a>]';
			}
		break;
		
		case 'revert_page':
			$action = 'Reverted changes to a page.';
			
			if($perm->get('cms')) {
				$undo = '[<a href="'.DIR.'revert_change/'.$log->param.'" onclick="return quickAction(this, \'Really undo that page reversion?\');">undo</a>]';
			}
		break;
		
		case 'revert_reply':
			$action = 'Reverted changes to <a href="'.DIR.'reply/'.$log->target.'">a reply</a>.';
			
			if($perm->get('edit_others')) {
				$undo = '[<a href="'.DIR.'revert_change/'.$log->param.'" onclick="return quickAction(this, \'Really undo that reply reversion?\');">undo</a>]';
			}
		break;
		
		case 'revert_topic':
			$action = 'Reverted changes to <a href="'.DIR.'topic/'.$log->target.'">a topic</a>.';
			
			if($perm->get('edit_others')) {
				$undo = '[<a href="'.DIR.'revert_change/'.$log->param.'" onclick="return quickAction(this, \'Really undo that topic reversion?\');">undo</a>]';
			}
		break;
		
		case 'perm_change':
			if($perm->get('view_profile')) {
				$action = 'Changed permissions of <a href="'.DIR.'profile/'.$log->target.'">'.$log->target.'</a>.';
			} else {
				$action = 'Changed permissions of a UID.';
			}
			
			if($perm->get('manage_permissions')) {
				$undo = '[<a href="'.DIR.'manage_permissions/'.$log->target.'">undo</a>]';
			}
		break;
		
		case 'merge':
			$action = 'Merged a topic (#' . number_format($log->target) . ') into <a href="'.DIR.'topic/'.$log->param.'">another</a>.';
			
			if($perm->get('merge')) {
				$undo = '[<a href="'.DIR.'undo_merge/'.$log->target.'" onclick="return quickAction(this, \'Really unmerge that topic?\');">undo</a>]';
			}
		break;
		
		case 'unmerge':
			$action = 'Unmerged <a href="'.DIR.'topic/'.$log->target.'">a topic</a>.';
			
			if($perm->get('merge')) {
				$undo = '[<a href="'.DIR.'merge/'.$log->target.'">undo</a>]';
			}
		break;
		
		default:
			$action = 'Undefined action ('.htmlspecialchars($log->action).')';
	}
	
	if( ! empty($log->reason)) {
		$action .= ' Reason: "' . htmlspecialchars($log->reason) . '".';
	}
	
	if($log->mod_uid === $_SESSION['UID']) {
		$undo .= ' <a href="' . DIR . 'edit_reason/' . $log->id . '" onclick="return editReason(this, \'' . rawurlencode($log->reason) . '\', \'' . $_SESSION['token'] . '\')" title="Edit reason" class="help mod_edit">[+]</a>';
	}
	
	if( ! empty($undo)) {
		$action .= ' <span class="undo">' . $undo . '</span>';
	}
		
	$values = array
	(
		$mod_name,
		$action,
		'<span class="help" title="' . format_date($log->time) . '">' . age($log->time) . '</span>'
	);
	
	$row_class = '';
	if($log->time > $_COOKIE['last_mod_action']) {
		$new_items = true;
	} else if($new_items) {
		$row_class = 'last_seen_marker';
		$new_items = false;
	}
	
	$table->row($values, $row_class);
}

$table->output('(No mod actions to display.)');
$page->navigation('mod_log', $table->row_count);
$template->render();
?>