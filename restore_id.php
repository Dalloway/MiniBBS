<?php
require './includes/bootstrap.php';
update_activity('restore_id');
$template->title  = 'Restore ID';
$template->onload = 'focusId(\'memorable_name\')';

// If an ID card was uploaded.
if (isset($_POST['do_upload'])) {
	list($uid, $password) = file($_FILES['id_card']['tmp_name'], FILE_IGNORE_NEW_LINES);
}
// ...or an ID and password was inputted.
else if ( ! empty($_POST['UID']) && ! empty($_POST['password'])) {
	$uid = $_POST['UID'];
	$password = $_POST['password'];
}
// ...or a link from a recovery e-mail is being used.
else if ( ! empty($_GET['UID']) && ! empty($_GET['password'])) {
	$uid = $_GET['UID'];
	$password = $_GET['password'];
}
// ...or a memorable name was inputted.
else if ( ! empty($_POST['memorable_name'])) {
	$res = $db->q('SELECT user_settings.uid, users.password FROM user_settings INNER JOIN users ON user_settings.uid = users.uid WHERE LOWER(user_settings.memorable_name) = LOWER(?) AND user_settings.memorable_password = ?', $_POST['memorable_name'], hash_password($_POST['memorable_password']));
	list($uid, $password) = $res->fetch();
	if (empty($uid)) {
			error::add('Your memorable information was incorrect.');
	}
}
	
if ( ! empty($uid)) {
	$previous_id = $_SESSION['UID'];
	$previous_post_count = $_SESSION['post_count'];
	if(activate_id($uid, $password)) {
		load_settings();
		$notice = 'Welcome back.';
		
		if( ! empty($_POST['merge_uid'])) {
			if($perm->is_banned($previous_id)) {
				$notice .= ' You cannot merge a banned ID.';
			} else {
				$db->q('UPDATE topics SET author = ? WHERE author = ?', $_SESSION['UID'], $previous_id);
				$db->q('UPDATE replies SET author = ? WHERE author = ?', $_SESSION['UID'], $previous_id);
				$db->q('UPDATE users SET post_count = post_count + ? WHERE uid = ?', $previous_post_count, $_SESSION['UID']);
				$db->q('UPDATE users SET post_count = 0 WHERE uid = ?', $previous_id);
				$db->q('UPDATE private_messages SET source = ? WHERE source = ?', $_SESSION['UID'], $previous_id);
				$db->q('UPDATE private_messages SET destination = ? WHERE destination = ?', $_SESSION['UID'], $previous_id);
				$notice .= ' Your IDs have been merged.';
			}
		}
		redirect($notice, '');
	}
	else {
		error::add('The username or password was incorrect.');
	}
}
error::output();
?>
<p>Your internal ID can be restored in a number of ways. If none of these work, you may be able to <a href="<?php echo DIR; ?>recover_ID_by_email">recover your ID by e-mail</a>.
<?php if($_SESSION['post_count']): ?><p>If you check the "<strong>Merge IDs</strong>" option, the post and PM history of your current ID will be merged into the restored ID.</p><?php endif; ?>
<fieldset>
	<legend>Input memorable name and password</legend>
	<p>Memorable information can be set from the <a href="<?php echo DIR; ?>dashboard">dashboard</a></p>
	<form action="" method="post">
		<div class="row">
			<label for="memorable_name">Memorable name</label>
			<input type="text" id="memorable_name" name="memorable_name" maxlength="100" />
		</div>
		<div class="row">
			<label for="memorable_password">Memorable password</label>
			<input type="password" id="memorable_password" name="memorable_password" />
		</div>
		<div class="row">
			<input type="submit" value="Restore" class="inline" />   <?php if($_SESSION['post_count']): ?><input type="checkbox" name="merge_uid" id="merge_uid" value="1" class="inline" /> <label for="merge_uid" class="inline">Merge IDs</label><?php endif; ?>
		</div>
	</form>
</fieldset>
<fieldset>
	<legend>Input UID and password</legend>
	<p>Your internal ID and password are automatically set upon creation of your ID. They are available from the <a href="<?php echo DIR; ?>back_up_ID">back up</a> page.</p>
	
	<form action="" method="post">
		<div class="row">
			<label for="UID">Internal ID</label>
			<input type="text" id="UID" name="UID" size="23" maxlength="23" />
		</div>
		<div class="row">
			<label for="password">Internal password</label>
			<input type="password" id="password" name="password" size="32" maxlength="32" />
		</div>
		<div class="row">
			<input type="submit" value="Restore" class="inline" />  <?php if($_SESSION['post_count']): ?><input type="checkbox" name="merge_uid" id="merge_uid2" value="1" class="inline" /> <label for="merge_uid2" class="inline">Merge IDs</</label><?php endif; ?>
		</div>
	</form>
</fieldset>
<fieldset>
	<legend>Upload ID card</legend>
	<p>If you have an <a href="<?php echo DIR; ?>generate_ID_card">ID card</a>, upload it here.</p>
	<form enctype="multipart/form-data" action="" method="post">
		<div class="row">
			<input name="id_card" type="file" />
		</div>
		<div class="row">
			<input name="do_upload" type="submit" value="Upload and restore" class="inline" />  <?php if($_SESSION['post_count']): ?><input type="checkbox" name="merge_uid" id="merge_uid3" value="1" class="inline" /> <label for="merge_uid3" class="inline">Merge IDs</</label><?php endif; ?>

		</div>
	</form>
</fieldset>
<?php
$template->render();
?>