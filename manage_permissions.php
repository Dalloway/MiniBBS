<?php
require './includes/bootstrap.php';
force_id();
if( ! $perm->get('manage_permissions')) {
	error::fatal(m('Error: Access denied'));
}

if( ! isset($_GET['uid']) || ! id_exists($_GET['uid'])) {
	error::fatal('There is no such UID.');
}

if(isset($_POST['form_sent'])) {
	check_token();
	if(empty($_POST['log_name'])) {
		error::add('The log name cannot be empty.');
	}
	if( ! ctype_digit($_POST['user_group'])) {
		error::add('Invalid user group.');
	}
	
	if(error::valid()) {
		$db->q
		(
			'INSERT INTO group_users 
			(uid, group_id, log_name) VALUES 
			(?, ?, ?) ON DUPLICATE KEY UPDATE 
			group_id = ?, log_name = ?', 
			$_GET['uid'], $_POST['user_group'], $_POST['log_name'], 
			$_POST['user_group'], $_POST['log_name']
		);
		log_mod('perm_change', $_GET['uid'], $_POST['user_group']);
		cache::clear('group_users');
		redirect('Permissions updated.', 'profile/'.$_GET['uid']);
	}
}
error::output();

$template->title = 'Manage permissions for <a href="'.DIR.'profile/'.$_GET['uid'].'">'.$_GET['uid'].'</a>';

?>
<p>A user's permission set is determined by their user group. "Log name" is the name that appears in the mod logs for this poster.</p>
<form action="" method="post">
	<?php csrf_token() ?>
	<div class="row">
		<label for="user_group" class="short">User group</label>
		<select name="user_group" id="user_group" class="inline">
		<?php
		$groups = $perm->get_groups();
		$current_group = $perm->get('name', $_GET['uid']);
		foreach($groups as $id => $settings) {
			echo '<option value="'.$id.'"' . ($settings['name'] == $current_group ? ' selected="selected"' : '') . '>'.$settings['name'].'</option>';
		}
		?>
		</select>
	</div>
	<div class="row">
		<label for="log_name" class="short">Log name</label>
		<input name="log_name" id="log_name" type="text" value="<?php echo $perm->get_name($_GET['uid']) ?>" class="inline" />
	</div>
	<div class="row">
		<input type="submit" name="form_sent" value="Update" tabindex="4" class="short_indent"/>
	</div>
</form>

<?php
$template->render();
?>