<?php
require './includes/bootstrap.php';
$template->title = 'Drop ID';

if($_POST['drop_ID'] && check_token()) {
	// CSRF checking.
	unset($_SESSION['UID']);
	unset($_SESSION['ID_activated']);
	setcookie('UID', '', $_SERVER['REQUEST_TIME'] - 3600, '/');
	setcookie('password', '', $_SERVER['REQUEST_TIME'] - 3600, '/');
	$_SESSION['notice'] = 'Your ID has been dropped.';
}
?>
<p><em>Dropping</em> your ID will simply remove the UID, password, and mode cookies from your browser, effectively logging you out. If you want to keep your post history, settings, etc., <a href="<?php echo DIR; ?>back_up_ID">back up your ID</a> and/or <a href="<?php echo DIR; ?>dashboard">set a memorable password</a> before doing this.</p>
<form action="" method="post">
	<?php csrf_token() ?>
	<input type="submit" name="drop_ID" value="Drop my ID" />
</form>
<?php
$template->render();
?>
