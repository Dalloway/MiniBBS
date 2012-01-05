<?php
require './includes/bootstrap.php';

$template->title = 'Edit mod reason';
$template->onload = "focusId('mod_reason');";

if( ! ctype_digit($_GET['id'])) {
	error::fatal('No valid ID specified.');
}

$res = $db->q('SELECT reason, action, mod_uid FROM mod_actions WHERE id = ?', $_GET['id']);
$log = $res->fetchObject();

if($log->mod_uid != $_SESSION['UID']) {
	error::fatal('You can only edit your own actions.');
}

if(isset($_POST['reason'])) {
	check_token();
	
	$log->reason = $_POST['reason'];
	
	if(strlen($log->reason) > 160) {
		error::add('Your reason must be under 160 characters.');
	}
	
	if(error::valid()) {
		$db->q('UPDATE mod_actions SET reason = ? WHERE id = ?', $log->reason, $_GET['id']);
		
		redirect('Reason updated.', 'mod_log');
	}
}

error::output();

?>

<p>You're editing an action of type "<kbd><?php echo htmlspecialchars($log->action) ?></kbd>".</p>

<form action="" method="post">
	<?php csrf_token() ?>
	<input type="text" name="reason" id="mod_reason" size="50" maxlength="100" value="<?php echo htmlspecialchars($log->reason) ?>" />
	<input type="submit" value="Edit reason" />
</form>

<?php
$template->render();
?>