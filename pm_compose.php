<?php
// The following constant may be used by bootstrap.php, so it must come first.
define('REPRIEVE_BAN', true);
require './includes/bootstrap.php';

force_id();
$template->title = 'Create private message';

$banned = false;
if($perm->is_banned($_SESSION['UID'])) {
	$banned = $_SESSION['UID'];
} else if($perm->is_banned($_SERVER['REMOTE_ADDR'])) {
	$banned = $_SERVER['REMOTE_ADDR'];
}
if($banned) {
	if( ! ALLOW_BAN_APPEALS) {
		error::fatal('Ban appeals are disabled on this board.');
	}
	if($perm->get_ban_appeal($banned)) {
		error::fatal('You have already appealed your ban.');
	}
} else {
	if($_SESSION['post_count'] < POSTS_FOR_USER_PM) {
		error::fatal('Sorry, you need at least '.POSTS_FOR_USER_PM.' post'.(POSTS_FOR_USER_PM>1?'s':'').' to send PMs (you currently have ' . $_SESSION['post_count'] . ').');
	}
}

$parent = 0;
// Appealing a ban?
if($banned) {
	$destination = 'mods';
	$template->title = 'Ban appeal';
}
// To the mods or admins?
else if($_GET['to'] == 'mods' || $_GET['to'] == 'admins') {
	$destination = $_GET['to'];
	$template->title .= ' for the ' . $destination;
}
// To a specified ID?
else if($perm->get('view_profile') && ! empty($_GET['to'])) {
	$destination = $_GET['to'];
	$template->title .= ' for poster <a href="' . DIR . 'profile/' . htmlspecialchars($destination) . '">' . htmlspecialchars($destination) . '</a>';
}
// In reply to a previous PM?
else if(ctype_digit($_GET['replyto'])) {
	$res = $db->q('SELECT contents, source, destination, parent FROM private_messages WHERE id = ?', $_GET['replyto']);
	if($db->num_rows() == 0) {
		$template->title = 'Non-existent message';
		error::fatal('The message you tried to reply to does not exist.');
	}
	
	list($prev['contents'], $prev['source'], $prev['destination'], $prev['parent']) = $res->fetch();
	
	if($prev['destination'] != $_SESSION['UID'] && $prev['source'] != $_SESSION['UID'] && !$perm->get('read_mod_pms')) {
		error::fatal('The message you tried to reply to was not addressed to you.');
	}
	
	if($prev['parent'] !== $_GET['replyto']) {
		error::fatal('You can only reply to a parent message.');
	}
	
	$parent = $_GET['replyto'];
	if($_SESSION['UID'] == $prev['source']) {
		$destination = $prev['destination'];
	} else {
		$destination = $prev['source'];
	}
}
// To the poster of a topic or reply?
else if($_GET['topic'] || $_GET['reply']) {
	if( ! ALLOW_USER_PM) {
		error::fatal('Messaging other users is currently not allowed.');
	}
	
	if(ctype_digit($_GET['topic'])) {
		$res = $db->q('SELECT author FROM topics WHERE id = ?', $_GET['topic']);
		if($db->num_rows() == 0) {
			error::fatal('There is no topic with that ID.');
		}
		$destination = $res->fetchColumn();
		$topic_id = (int) $_GET['topic'];
		$reply_id = 0;
		$template->title .= ' for <a href="' . DIR . 'topic/' . $topic_id . '">topic</a> author';
	} else if(ctype_digit($_GET['reply'])) {
		$res = $db->q('SELECT author, parent_id FROM replies WHERE id = ?', $_GET['reply']);
		if($db->num_rows() == 0) {
			error::fatal('There is no reply with that ID.');
		}
		list($destination, $topic_id) = $res->fetch();
		$reply_id = $_GET['reply'];
		$template->title .= ' for <a href="' . DIR . 'topic/' . $topic_id . '#reply_' . $_GET['reply'] . '">reply</a> author';
	} else {
		error::fatal('The post ID was not valid.');
	}
}
// ...none of the above?
else {
	error::fatal('You did not specify any valid destination for this message.');
}

if($_POST['submit']) {
	$contents = super_trim($_POST['contents']);
	list($name, $trip) = tripcode($_POST['name']);
	
	check_token();
	check_length($contents, 'body', 3, MAX_LENGTH_BODY);
	check_length($name, 'name', 0, 30);
	
	// Flood checking.
	if( ! $perm->is_admin() && ! $perm->is_mod()) {
		$db->q('SELECT 1 FROM private_messages WHERE source = ? AND time > ? LIMIT 1', $_SESSION['UID'], $_SERVER['REQUEST_TIME'] - FLOOD_CONTROL_PM);
		if($db->num_rows() > 0) {
			error::add('Please wait at least '.FLOOD_CONTROL_PM.' seconds between private messages.');
		}
		
		$global_check = $db->q('SELECT count(*) FROM private_messages WHERE time > ?', $_SERVER['REQUEST_TIME'] - 300);
		$global_count = $global_check->fetchColumn();
		if($global_count > MAX_GLOBAL_PM) {
			error::add('Too many PMs have been sent in the last 5 minutes. Try again in a moment.');
		}
	}
	
	if(error::valid()) {
		// Silently mark this message as ignored if we're on the recipient's ignore list.
		$ignored = 0;
		if( ! $perm->is_admin() && ! $perm->is_mod()) {
			$res = $db->q('SELECT 1 FROM pm_ignorelist WHERE uid = ? AND (ignored_uid = ? OR ignored_uid = \'*\')', $destination, $_SESSION['UID']);
			if($res->fetchColumn()) {
				$ignored = 1;
			}
		}
		
		if($banned) {
			$contents .= "\n\n" . '(This is an appeal of the ban of '.htmlspecialchars($banned).'.)';
		}
		
		$db->q
		(
			'INSERT INTO private_messages 
			(source, destination, name, trip, contents, time, parent, topic, reply, ignored) VALUES 
			(?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
			$_SESSION['UID'], $destination, $name, $trip, $contents, $_SERVER['REQUEST_TIME'], $parent, (int) $topic_id, (int) $reply_id, $ignored
		);
	
		if($new_id = $db->lastInsertId()) {
			$notice = 'Private message sent.';
			// If this message isn't a reply, set its "parent" as its own ID. Hack-ish, but the best solution IMO.
			if($parent === 0) {
				$parent = $new_id;
				$db->q('UPDATE private_messages SET parent = ? WHERE id = ?', $parent, $parent);
			}
			// If the user is banned, this is the last PM they'll be able to send.
			if($banned) {
				$db->q('UPDATE bans SET appealed = 1 WHERE target = ?', $banned);
			}
			
			// Delete all notifications for this PM, if it's been dismissed (so other mods don't have to read it)
			if($_POST['dismiss'] && $perm->get('read_mod_pms')) {
				$db->q('DELETE FROM pm_notifications WHERE parent_id = ?', $parent);
				$notice = 'Private message sent and dismissed.';
			}
			// Create new notifications for the PM.
			if( ! $ignored) {
				$recipients = array();
				if($destination == 'mods') {
					$recipients = $perm->users_with_permission('read_mod_pms');
				} else if($destination == 'admins') {
					$recipients = $perm->users_with_permission('read_admin_pms');
				} else {
					$recipients[] = $destination;
				}
				
				foreach($recipients as $uid) {
					// Filter out crap like "MOD_ID" and our own UID.
					if(strlen($uid) < 20 || $uid == $_SESSION['UID']) {
						continue;
					}
					$db->q('INSERT INTO pm_notifications (uid, pm_id, parent_id) VALUES (?, ?, ?)', $uid, $new_id, $parent);
				}
			}
			
			redirect($notice, 'private_message/' . $new_id);
		} else {
			error::add('An error occurred while sending your private message.');
		}	
	}
}

// Check if any values should be preset in our form.
$set_name = '';
$message_body = '';
if($_POST['form_sent']) {
	$set_name = $_POST['name'];
	$message_body = $_POST['contents'];
} else {
	$set_name = $_SESSION['poster_name'];
}

error::output();

if($banned):
?>
<p>This is the only PM you'll be able to send while banned, so make it count.</p>
<?php
endif;
?>
<form action="" method="post">
	<input name="form_sent" type="hidden" value="1" />
<?php
	csrf_token();
	if($_POST['preview'] && !empty($_POST['contents'])): 
?>
		<h3 id="preview">Preview</h3>
		<div class="body standalone"><?php echo parser::parse($_POST['contents']) ?></div>
<?php 
	endif; 
?>
	<div class="row">
		<label for="name">Name</label>
		<input id="name" name="name" type="text" size="30" maxlength="30" tabindex="1" value="<?php echo htmlspecialchars($set_name) ?>">
	</div>
		
	<label for="contents" class="noscreen">Message</label> 
	<textarea name="contents" cols="80" rows="10" tabindex="2" id="contents"><?php echo htmlspecialchars($message_body) ?></textarea>
<?php 
	if($_GET['replyto'] && ($prev['destination'] == 'mods' || $prev['destination'] == 'admins') && $perm->get('read_mod_pms') ):
?>
		<div class="row"> <input type="checkbox" name="dismiss" id="dismiss" class="inline" <?php echo ( ! isset($_POST['dismiss']) ? '' : 'checked="checked" ') ?>/> <label for="dismiss" class="inline help" title="If checked, other <?php echo $prev['destination'] ?> will no longer be notified of the original message or its current replies (unless the sender replies again).">Dismiss message</label></div>
<?php 
	endif; 
?>
	<div class="row">
		<input type="submit" name="preview" value="Preview" class="inline" tabindex="3" />
		<input type="submit" name="submit" value="Send" class="inline" tabindex="4" />
	</div>
</form>

<?php
$template->render();
?>
