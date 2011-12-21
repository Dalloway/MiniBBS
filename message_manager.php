<?php
require './includes/bootstrap.php';
/* Contains $messages -- default messages */
require SITE_ROOT . '/lang/en.php';

$template->title = 'Message manager';

if( ! $perm->get('manage_messages')) {
	error::fatal(m('Error: Access denied'));
}

?>

<p>From this page, you can edit the text of MiniBBS's interface.</p>

<?php
/* Print custom messages */
$columns = array
(
	'Key',
	'Message'
);
$table = new Table($columns, 1);
$table->add_td_class(0, 'topic_headline');

$res = $db->q('SELECT `key`, `message` AS text FROM messages');

while($message = $res->fetchObject()) {
	/* Unset default message */
	unset($messages[$message->key]);

	$values = array
	(
		'<a href="'.DIR.'edit_message/'.urlencode($message->key).'">'.htmlspecialchars($message->key).'</a>',
		htmlspecialchars($message->text)
	);
	
	$table->row($values);
}

if($table->row_count) {
	echo '<h4 class="section">Custom messages</h4>';
	$table->output();
}

/* Print default messages */
$table = new Table($columns, 1);
$table->add_td_class(0, 'topic_headline');

foreach($messages as $key => $text) {
	$values = array
	(
		'<a href="'.DIR.'edit_message/'.urlencode($key).'">'.htmlspecialchars($key).'</a>',
		htmlspecialchars($text)
	);
	
	$table->row($values);
}

if($table->row_count) {
	echo '<h4 class="section">Default messages</h4>';
	$table->output();
}

$template->render();
?>