<?php
require './includes/bootstrap.php';
force_id();
update_activity('watchlist');
$template->title = 'Your watchlist';

if($notifications['watchlist']) {
	/* Stop watching topics that no longer exist */
	$db->q
	(
		"DELETE watchlists FROM watchlists
		INNER JOIN topics ON watchlists.topic_id = topics.id 
		WHERE watchlists.uid = ? AND topics.deleted = 1", 		
		$_SESSION['UID']
	);
}

if (is_array($_POST['rejects'])) {
	foreach ($_POST['rejects'] as $reject_id) {
		$db->q('DELETE FROM watchlists WHERE uid = ? AND topic_id = ?', $_SESSION['UID'], $reject_id);
	}
	$_SESSION['notice'] = 'Selected topics unwatched.';
}

echo '<form id="watchlist" name="watch_list" action="" method="post">';

$res = $db->q('SELECT watchlists.topic_id AS id, topics.headline, topics.replies, topics.visits, topics.time FROM watchlists INNER JOIN topics ON watchlists.topic_id = topics.id WHERE watchlists.uid = ? ORDER BY watchlists.new_replies DESC, topics.last_post DESC', $_SESSION['UID']);

$master_checkbox = '<input type="checkbox" name="master_checkbox" class="inline" onclick="checkAll(\'watchlist\')" title="Check/uncheck all" />';
$columns = array
(
	$master_checkbox . 'Topic',
	'Replies',
	'Visits',
	'Age ▼'
);
$topics = new Table($columns, 0);
$topics->add_td_class(0, 'topic_headline');

while ($topic = $res->fetchObject()) {
	$values = array
	(
		'<input type="checkbox" name="rejects[]" value="' . $topic->id . '" class="inline" /> <a href="'.DIR.'topic/' . $topic->id . '">' . htmlspecialchars($topic->headline) . '</a>',
		replies($topic->id, $topic->replies),
		format_number($topic->visits),
		'<span class="help" title="' . format_date($topic->time) . '">' . age($topic->time) . '</span>'
	);
	
	$topics->row($values);
}
$num_topics_fetched = $topics->row_count;
$topics->output('You haven\'t watched any topics yet. Once you do, you can keep track of their latest replies here.');

if ($num_topics_fetched !== 0) {
	echo '<div class="row"><input type="submit" value="Unwatch selected" onclick="return confirm(\'Really remove selected topic(s) from your watchlist?\');" class="inline" /></div>';
}
echo '</form>';

$template->render();
?>