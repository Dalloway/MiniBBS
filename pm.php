<?php
/* The following variable is used by bootstrap.php, so it must come first. */
$reading_pm = true;
define('REPRIEVE_BAN', true);
require './includes/bootstrap.php';

force_id();
$template->title = 'Private message';

if( ! ctype_digit($_GET['id'])) {
	error::fatal('Invalid ID.');
}

if($perm->is_banned($_SESSION['UID'])) {
	$has_appealed = $perm->get_ban_appeal($_SESSION['UID']);
} else if($perm->is_banned($_SERVER['REMOTE_ADDR'])) {
	$has_appealed = $perm->get_ban_appeal($_SERVER['REMOTE_ADDR']);
}

/* Select the PM and its children. */
$res = $db->q
(
	'SELECT id, source, parent, destination, contents, time, name, trip, topic, reply, ignored
	FROM `private_messages` 
	WHERE id = ? OR parent = ? 
	ORDER BY id', 
	$_GET['id'], $_GET['id']
);

/* Assign variables for the first PM. This is repeated in the truth expression of the do-while below. */
$pm = $res->fetchObject();

if( ! $pm) {
	$template->title = 'Non-existent message';
	error::fatal('There is no such private message.');
}

$op_destination = $pm->destination;

/* Administrators can read all messages. */
if( ! $perm->get('read_admin_pms')) {
	/* If the message isn't a group PM, and the user didn't send or receive it, deny access. */
	if($pm->destination !== $_SESSION['UID'] && $pm->source !== $_SESSION['UID'] && $pm->destination != 'mods' && $pm->destination != 'admins') {
		error::fatal('This message is not addressed to you.');
	}
	/* If the message is a group PM, but the user isn't the sender or a member of the group, deny access. */
	if(	($pm->destination == 'admins' && $pm->source !== $_SESSION['UID']) || ($pm->destination == 'mods' && $pm->source !== $_SESSION['UID'] && !$perm->get('read_mod_pms')) ) {
		error::fatal(m('Error: Access denied'));
	}
}

/* If this message is a reply to another PM, redirect to the parent. */
if($pm->parent !== $_GET['id']) {
	redirect('', 'private_message/' . $pm->parent);
}

if($pm->destination == 'mods') {
	$template->title .= ' to all moderators';
} else if($pm->destination == 'admins') {
	$template->title .= ' to all administrators';
}

if($pm->source == 'system') {
	$template->title = 'System message';
	$system_pm = true;
}

?>
<table>
<thead>
	<tr> 
		<th class="minimal">Author</th> 
		<th>Message</th> 
		<th class="minimal">Age ▼</th> 
	</tr>
</thead> 
<tbody>
<?php 
$participants = array();
$i = 0;
do {
	/* Prepare the author. */
	if( ! array_key_exists($pm->source, $participants)) {
		$participants[$pm->source] = count($participants);
	}
	if($pm->source == 'system') {
		$author = m('System');
	} else {
		$author = '<span class="poster_number_' . $participants[$pm->source] . '">' . format_name($pm->name, $pm->trip, $perm->get('link', $pm->source), $participants[$pm->source]) . '</span>';
		if($pm->source == $_SESSION['UID']) {
			$author .= ' <span class="unimportant">(you)</span>';
		}
	}
	?>
	<tr id="reply_box_<?php echo $pm->id ?>"<?php echo ($i++ & 1 ? ' class="odd"' : '') ?>>
		<td class="minimal"><?php echo $author ?></td>
		<td class="pm_body" id="reply_<?php echo $pm->id?>">
<?php 
			echo parser::parse($pm->contents, $pm->source);
			
			/* If this message was sent via a "PM" link in a topic, provide context (first PM only). */
			if( ! empty($pm->topic)) {
				$tmp = $db->q('SELECT headline, body, namefag, tripfag FROM topics WHERE id = ?', $pm->topic);
				$topic = $tmp->fetchObject();
				$recipient_name = $topic->namefag;
				$recipient_trip = $topic->tripfag;
				
				if( ! empty($pm->reply)) {
					$tmp = $db->q('SELECT namefag, tripfag, body FROM replies WHERE id = ?', $pm->reply);
					$reply = $tmp->fetchObject();
					$recipient_name = $reply->namefag;
					$recipient_trip = $reply->tripfag;
				}
				
				echo '<p class="unimportant">(This message was sent via '. ($pm->destination==$_SESSION['UID'] ? 'your' : 'the recipient\'s') .' ' . (empty($pm->reply) ? 'original post' : '<a href="'.DIR.'reply/'.$pm->reply.'" class="help" title="' . parser::snippet($reply->body) . '">reply</a>') . ' as ' . format_name($recipient_name, $recipient_trip) . ' in "<strong><a href="'.DIR.'topic/' . $pm->topic . '" class="help" title="' . parser::snippet($topic->body) . '">' . htmlspecialchars($topic->headline) . '</a></strong>".)</p>';
			}
?>

			<ul class="menu">
<?php 
			if($perm->get('view_profile') && $pm->source != 'system'): 
?>
				<li><a href="<?php echo DIR ?>profile/<?php echo $pm->source ?>">Profile</a></li>
<?php
			endif;
			if($perm->get('delete')):
?>
				<li><a href="<?php echo DIR ?>delete_message/<?php echo $pm->id ?>" onclick="return quickAction(this, 'Really delete this PM?');">Delete</a></li>
<?php 
			else: 
?>
				<li><a href="<?php echo DIR ?>report_PM/<?php echo $pm->id ?>">Report</a></li>
<?php 
			endif; 
			if($pm->parent == $pm->id && ($pm->destination == 'mods' || $pm->destination == 'admins') && $perm->get('read_mod_pms') ):
?>
				<li><a href="<?php echo DIR ?>dismiss_PM/<?php echo $pm->id ?>" onclick="return quickAction(this, 'Really dismiss this PM?');">Dismiss</a></li>
<?php 
			endif;
			if($pm->ignored && $_SESSION['UID'] == $pm->destination):
?>
				<li><a href="<?php echo DIR ?>unignore_PM/<?php echo $pm->id ?>" onclick="return quickAction(this, 'Really stop ignoring PMs from this user?');">Unignore</a></li>
<?php 
			elseif($pm->destination == $_SESSION['UID'] && $pm->source != 'system'): 
?>
				<li><a href="<?php echo DIR ?>ignore_PM/<?php echo $pm->id ?>" onclick="return quickAction(this, 'Really ignore all future PMs from this user?');">Ignore</a></li>
<?php 
			endif; 
?>
			</ul>
		</td>
		<td class="minimal"><span class="help" title="<?php echo format_date($pm->time) ?>"><?php echo age($pm->time) ?></span></td>
	</tr>
<?php
} while ($pm = $res->fetchObject());
?>
</tbody>
</table>

<?php
/* Banned users can read PMs, but not reply. */
if(empty($has_appealed) && ! isset($system_pm)): 
?>
<ul class="menu">
	<li><a href="<?php echo DIR ?>reply_to_message/<?php echo (int) $_GET['id']; ?>" onclick="$('#quick_reply').toggle();$('#qr_text').get(0).scrollIntoView(true);$('#qr_text').focus(); return false;">Reply</a></li>
	<li><a href="<?php echo DIR ?>private_messages">Inbox</a></li>
</ul>

<div id="quick_reply" class="noscreen">
	<form action="<?php echo DIR ?>reply_to_message/<?php echo (int) $_GET['id'] ?>" method="post">
		<input name="form_sent" type="hidden" value="1" />
<?php 
		csrf_token();
?>
		<div class="row">
			<label for="name">Name</label>
			<input id="name" name="name" type="text" size="30" maxlength="30" tabindex="1" value="<?php echo htmlspecialchars($_SESSION['poster_name']) ?>">
		</div>
			
		<textarea name="contents" cols="80" rows="10" tabindex="2" id="qr_text"></textarea>
<?php 
		if( ($op_destination == 'mods' || $op_destination == 'admins') && $perm->get('read_mod_pms') ):
?>
			<div class="row"> <input type="checkbox" name="dismiss" id="dismiss" class="inline" checked="checked" /> <label for="dismiss" class="inline help" title="If checked, other <?php echo $op_destination ?> will no longer be notified of this message or its current replies (unless the original sender replies again).">Dismiss message</label></div>
<?php 
		endif;
?>
		<div class="row">
			<input type="submit" name="preview" value="Preview" class="inline" tabindex="3"/>
			<input type="submit" name="submit" value="Send" class="inline" tabindex="4" />
		</div>
	</form>
</div>
<?php 
endif; 

$template->render();
?>