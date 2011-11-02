<?php
require './includes/bootstrap.php';
force_id();
$template->title = 'Your trash can';
update_activity('trash_can', 1);

if ($_POST['empty_trash']) {
	if( ! check_token()) {
		error::fatal(MESSAGE_TOKEN_ERROR);
	}
	$db->q('DELETE FROM trash WHERE uid = ?', $_SESSION['UID']);
	$_SESSION['notice'] = 'Trash emptied.';
}
echo '<p>Your deleted topics and replies are archived here.</p>';

$fetch_trash = $db->q
(
	"(SELECT id, '' AS parent_id, headline, body, time FROM topics WHERE author = ? AND deleted = '1') 
	UNION
	(SELECT id, parent_id, '' AS headline, body, time FROM replies WHERE author = ? AND deleted = '1') 
	ORDER BY time DESC", 
	$_SESSION['UID'], $_SESSION['UID']
);

$columns = array
(
	'Headline',
	'Body',
	'Age ▼'
);
$table = new Table($columns, 1);

while($trash = $fetch_trash->fetchObject()) {
	if(empty($trash->headline)) {
		$trash->headline = '<span class="unimportant"><a href="'.DIR.'topic/'.(int) $trash->parent_id.'#reply_'.(int) $trash->id.'">(Reply.)</a></span>';
	} else {
		$trash->headline = '<a href="'.DIR.'topic/'.(int) $trash->id.'">' . htmlspecialchars($trash->headline) . '</a>';
	}

	$values = array 
	(
		$trash->headline,
		parser::snippet($trash->body),
		'<span class="help" title="' . format_date($trash->time) . '">' . age($trash->time) . '</span>'
	);
							
	$table->row($values);
}

$table->output();

$template->render();
?> 