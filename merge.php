<?php
require './includes/bootstrap.php';
force_id();

if( ! $perm->get('merge')) {
	error::fatal(MESSAGE_ACCESS_DENIED);
}

if( ! ctype_digit($_GET['id'])) {
	error::fatal('Invalid topic ID.');
}

$res = $db->q('SELECT namefag, tripfag, link, author, author_ip, time, headline, body, edit_time, edit_mod, imgur FROM topics WHERE id = ? AND deleted = 0', $_GET['id']);
$topic = $res->fetchObject();
if( ! $topic) {
	error::fatal('No topic with that ID exists.');
}

$template->title = 'Merge <a href="'.DIR.'topic/'.$_GET['id'].'">topic</a>';
$template->onload = "focusId('merge_target')";

if(isset($_POST['form_sent'])) {
	if(empty($_POST['merge_target']) && ! empty($_POST['merge_history'])) {
		$_POST['merge_target'] = $_POST['merge_history'];
	}

	if(ctype_digit($_POST['merge_target'])) {
		$merge_target = (int) $_POST['merge_target'];
	} else if(preg_match('|topic/([0-9]+)|', $_POST['merge_target'], $match)) {
		$merge_target = (int) $match[1];
	} else {
		error::add('You did not enter a valid ID or topic URL.');
	}
	
	if($merge_target == $_GET['id']) {
		error::add('You cannot merge a topic with itself.');
	}
	
	$res = $db->q('SELECT 1 from topics WHERE id = ? AND deleted = 0', $merge_target);
	$topic_exists = $res->fetchColumn();
	if( ! $topic_exists) {
		error::add('You cannot merge into a deleted or non-existent topic.');
	}
	
	if(error::valid()) {
		$topic->body = '[h]' . $topic->headline . '[/h]' . $topic->body;
		
		/* Insert the OP into our merge target */
		$db->q
		(
			'INSERT INTO replies
			(namefag, tripfag, link, author, author_ip, time, body, edit_time, edit_mod, imgur, parent_id, original_parent) VALUES
			(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
			$topic->namefag, $topic->tripfag, $topic->link, $topic->author, $topic->author_ip, $topic->time, $topic->body, $topic->edit_time, $topic->edit_mod, $topic->imgur, $merge_target, $_GET['id']
		);
		$op_id = $db->lastInsertId();
		
		/* Add its image */
		$db->q('UPDATE images SET reply_id = ? WHERE topic_id = ?', $op_id, $_GET['id']);
		
		/* Delete the OP */
		$db->q('UPDATE topics SET deleted = 1 WHERE id = ?', $_GET['id']);

		/* Record the original parent to allow reversal -- this is done separately for the IS NULL, so we can reverse multiple merges */
		$db->q('UPDATE replies SET original_parent = parent_id WHERE parent_id = ? AND original_parent IS NULL', $_GET['id']);
		
		/* Update the reply parents */
		$db->q('UPDATE replies SET parent_id = ? WHERE parent_id = ?', $merge_target, $_GET['id']);
		
		/* Clean up other tables */
		$db->q('DELETE FROM reports WHERE post_id = ?', $_GET['id']);
		$db->q('UPDATE IGNORE watchlists SET topic_id = ? WHERE topic_id = ?', $merge_target, $_GET['id']);
		$db->q('UPDATE citations SET topic = ? WHERE topic = ?', $merge_target, $_GET['id']);
		
		log_mod('merge', $_GET['id'], $merge_target);
		redirect('Topic merged.', 'reply/' . $op_id);
	}
}

/* Get recent merge choices */
$merge_history = $db->q
(
	"SELECT DISTINCT mod_actions.param AS id, topics.headline 
	FROM mod_actions
	INNER JOIN topics ON mod_actions.param = topics.id
	WHERE mod_actions.action = 'merge' AND topics.deleted = 0
	ORDER BY mod_actions.time DESC
	LIMIT 10"
);

error::output();

?>
<p>The posts in "<a href="<?php echo DIR . 'topic/' . $_GET['id'] ?>"><kbd><?php echo htmlspecialchars($topic->headline) ?></kbd></a>" will be merged into whatever topic you choose below.</p>

<form action="" method="post">
	<div class="row">
		<label for="merge_target" class="short">Topic URL or ID</label>
		<input type="text" class="inline" name="merge_target" id="merge_target" size="35" />
	</div>
	<div class="row">
	<?php if($merge_history): ?>
		<label for="merge_history" class="short">Recent choices</label>
		<select id="merge_history" name="merge_history" class="inline" onchange="getElementById('merge_target').value = this.value">
			<option value=""></option>
		<?php while($merge = $merge_history->fetchObject()): ?>
			<option value="<?php echo URL . 'topic/' . (int) $merge->id?>" style="font-size:88%;"><?php echo substr(htmlspecialchars($merge->headline), 0, 60) ?></option>
		<?php endwhile; ?>
		</select>
	<?php endif; ?>
	</div>
	<div class="row">
		<input type="submit" class="short_indent" name="form_sent" value="Merge" />
	</div>
</form>

<?php
$template->render();
?>