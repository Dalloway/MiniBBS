<?php
require './includes/bootstrap.php';
force_id();

$page = new Paginate();
$template->title = ($_GET['outbox'] ? 'Outbox' : 'Inbox');
if ($page->current > 1) {
	$template->title   .= ', page #' . number_format($page->current);
}

// Check if the user is ignoring all non-mods.
$res = $db->q('SELECT 1 FROM pm_ignorelist WHERE uid = ? and ignored_uid = \'*\'', $_SESSION['UID']);
$ignoring_all_users = (bool) $res->fetchColumn();

// Check for ignored PMs.
if( ! $_GET['ignored']) {
	$res = $db->q('SELECT COUNT(*) FROM private_messages WHERE ignored = 1 AND destination = ?', $_SESSION['UID']);
	$num_ignored = $res->fetchColumn();
} else {
	$template->title = 'Ignored private messages';
}

$db->select('`id`, `parent`, `source`, `destination`, `contents`, `time`, `name`, `trip`')
   ->from('private_messages');
if($_GET['outbox']) {
	$db->where('`source` = ?', $_SESSION['UID']);
} else if($_GET['ignored']) {
	$db->where("`ignored` = '1' AND `destination` = ?", $_SESSION['UID']);
} else if($perm->get('read_admin_pms')) {
	$db->where("`ignored` = '0' AND (`destination` = ? OR `destination` = 'mods' OR `destination` = 'admins')", $_SESSION['UID']);
} else if($perm->get('read_mod_pms')) {
	$db->where("`ignored` = '0' AND (`destination` = ? OR `destination` = 'mods')", $_SESSION['UID']);
} else {
	$db->where("`ignored` = '0' AND `destination` = ?", $_SESSION['UID']);
}
$db->group_by('`parent`')
   ->order_by('`time` DESC')
   ->limit($page->offset, $page->limit);
$res = $db->exec();

// Print the table.
$columns = array(
	($_GET['outbox'] ? 'Recipient' : 'Author'),
	'Snippet',
	'Age ▼'
);
if($perm->get('delete')) {
	$columns[] = 'Delete';
}
$pms = new Table($columns, 1);
$pms->add_td_class(1, 'snippet');

while( $pm = $res->fetchObject() ) {
	$values = array();

	// If we're using the outbox, determine what should be in the "Recipient" field.
	if($_GET['outbox']) {
		if($pm->destination == 'mods' || $pm->destination == 'admins') {
			$author = ucfirst($pm->destination);
		} else if($perm->get('view_profile')) {
			$author = '<a href="'.DIR.'profile/' . $pm->destination . '">' . $pm->destination . '</a>';
		} else {
			$author = 'A poster';
		}
	}
	// If we're using the inbox, determine what should be in the "Author" field.
	else if(empty($pm->name) && empty($pm->trip)) {
		if($pm->source == 'system') {
			$author = '<em>System</em>';
		} else if($perm->get('view_profile')) {
			$author = '<a href="'.DIR.'profile/' . $pm->source . '">' . $pm->source . '</a>';
		} else {
			$author = 'Anonymous';
		}
	} else {
		$author = '<strong>' . htmlspecialchars($pm->name) . '</strong> ' . $pm->trip;
		if($perm->get('view_profile')) {
			$author = '<a href="'.DIR.'profile/' . $pm->source . '">' . $author . '</a>';
		}
	}
	$values[] = $author;
	
	$values[] = '<a href="' . DIR . 'private_message/'.$pm->parent. ($new_pm != $new_parent ? '#reply_'.$new_pm : '') .'">' . parser::snippet($pm->contents) . '</a>';
	
	$values[] = '<span class="help" title="' . format_date($pm->time) . '">' . age($pm->time) . '</span>';
	
	if($perm->get('delete')) {
		$values[] = '<a href="' . DIR . 'delete_message/' . $pm->parent . '">✘</a>';
	}
	$pms->row($values);
}

?>
<ul class="menu">
	<li><a href="<?php echo DIR; ?>compose_message/mods">Mod PM</a></li>
	<li><a href="<?php echo DIR; ?>compose_message/admins">Admin PM</a></li>
<?php 
	if($_GET['ignored'] || $_GET['outbox']): 
?>
	<li><a href="<?php echo DIR; ?>private_messages">Inbox</a></li>
<?php 
	endif; 
	if( ! $_GET['outbox']): 
?>
	<li><a href="<?php echo DIR; ?>outbox">Outbox</a></li>
<?php 
	endif; 
	if( ! $_GET['ignored'] && $num_ignored > 0): 
?>
	<li><a href="<?php echo DIR; ?>ignored_PMs">Show ignored PMs</a> (<?php echo $num_ignored ?>)</li>
<?php 
	endif; 
	if( ! $ignoring_all_users): 
?>
	<li><a href="<?php echo DIR; ?>ignore_PM/*" class="help" title="You will no longer be notified of any PM, except those sent by mods or admins. All currently unread messages will be marked as read." onclick="return quickAction(this, 'Really ignore all future user-to-user PMs?');">Ignore all PMs</a></li>
<?php 
	else: 
?>
	<li><a href="<?php echo DIR; ?>unignore_PM/*" class="help" title="You are not currently being notified of new PMs, except those sent by mods or admins." onclick="return quickAction(this, 'Really stop ignoring PMs?');">Stop ignoring PMs</a></li>
<?php 
	endif; 
?>
</ul>
<?php
$pms->output('(No PMs to display.)');
$page->navigation('private_messages', $pms->row_count);
$template->render();
?>