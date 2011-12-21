<?php
require './includes/bootstrap.php';

if( ! $perm->get('manage_messages')) {
	error::fatal(m('Error: Access denied'));
}

$current_message = $lang->get_raw($_GET['key']);

if( ! $current_message)) {
	error::fatal('No message with that key was found.');
}

$template->title = 'Edit message: ' . htmlspecialchars($_GET['key']);

if(isset($_POST['form_sent'])) {

}

?>

<form action="" method="post">

	<input type="submit" name="form_sent" value="Update" />
</form>

<?php
$template->render();
?>