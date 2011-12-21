<?php
require './includes/bootstrap.php';

if( ! $perm->get('manage_messages')) {
	error::fatal(m('Error: Access denied'));
}

$current_message = $lang->get_raw($_GET['key']);

if($current_message === false) {
	error::fatal('No message with that key was found.');
}

$template->title = 'Edit <a href="'.DIR.'message_manager">message</a>: ' . htmlspecialchars($_GET['key']);

if(isset($_POST['form_sent'])) {
	$db->q('INSERT INTO messages (`key`, `message`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `message` = ?', $_GET['key'], $_POST['message'], $_POST['message']);
	cache::clear('lang');
	redirect('Message updated', 'message_manager');
}

?>

<p>Messages use the following syntax:</p>
<ul>
	<li><kbd>$1</kbd>, <kbd>$2</kbd>, and so on translate to the message parameters, which are provided by the script.</li>
	<li><kbd>{{DIR}}</kbd> translates to <kbd><?php echo DIR ?></kbd> (the directory of the forum from config.php)</li>
	<li><kbd>{{URL}}</kbd> translates to <kbd><?php echo URL ?></kbd> (the URL of the forum from config.php)</li>
	<li><kbd>{{PLURAL:<strong>$1</strong>|<strong>chicken</strong>|<strong>chickens</strong>}}</kbd> translates to <kbd><strong>chicken</strong></kbd> if the first parameter (<kbd><strong>$1</strong></kbd>) is one, or <kbd><strong>chickens</strong></kbd> otherwise.</li>
</ul>	

<form action="" method="post">
	<textarea name="message" rows="10"><?php echo htmlspecialchars($current_message) ?></textarea>
	<input type="submit" name="form_sent" value="Update" />
</form>

<?php
$template->render();
?>