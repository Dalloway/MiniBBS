<?php
require './includes/bootstrap.php';

if (!ctype_digit($_GET['id'])) {
	error::fatal('Invalid ID.');
}

$res = $db->q('SELECT headline, visits, replies, author FROM topics WHERE id = ?', $_GET['id']);

if($db->num_rows() < 1) {
	$template->title = 'Non-existent topic';
	error::fatal('There is no such topic. It may have been deleted.');
}

list($topic_headline, $topic_visits, $topic_replies, $topic_author) = $res->fetch();

update_activity('topic_trivia', $_GET['id']);

$template->title = 'Trivia for topic: <a href="'.DIR.'topic/' . $_GET['id'] . '">' . htmlspecialchars($topic_headline) . '</a>';

$statistics = array();
$res = $db->q('SELECT count(*) FROM watchlists WHERE topic_id = ?', $_GET['id']);
$topic_watchers = $res->fetchColumn();
$res = $db->q('SELECT count(*) FROM activity WHERE action_name = ? AND action_id = ?', 'topic', $_GET['id']);
$topic_readers = $res->fetchColumn();
$res = $db->q('SELECT count(*) FROM activity WHERE action_name = ? AND action_id = ?', 'replying', $_GET['id']);
$topic_writers = $res->fetchColumn();
$res = $db->q('SELECT count(DISTINCT author) FROM replies WHERE parent_id = ? AND author != ?', $_GET['id'], $topic_author);
$topic_participants = $res->fetchColumn() + 1;  // Include topic author.

?>
<table>
	<tr>
		<th class="minimal">Total visits</th>
		<td><?php echo format_number($topic_visits) ?></td>
	</tr>
	<tr class="odd">
		<th class="minimal">Watchers</th>
		<td><?php echo format_number($topic_watchers) ?></td>
	</tr>
	<tr>
		<th class="minimal">Participants</th>
		<td><?php echo ($topic_participants === 1) ? '(Just the creator.)' : format_number($topic_participants) ?></td>
	</tr>
	<tr class="odd">
		<th class="minimal">Replies</th>
		<td><?php echo format_number($topic_replies) ?></td>
	</tr>
	<tr>
		<th class="minimal">Current readers</th>
		<td><?php echo format_number($topic_readers) ?></td>
	</tr>
	<tr class="odd">
		<th class="minimal">Current reply writers</th>
		<td><?php echo format_number($topic_writers) ?></td>
	</tr>
</table>
<?php
$template->render();
?>