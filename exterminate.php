<?php
require './includes/bootstrap.php';

if ( ! $perm->get('exterminate')) {
	error::fatal(m('Error: Access denied'));
}

$template->title = 'Exterminate trolls by phrase';

if ($_POST['exterminate']) {
	// CSRF checking.
	if( ! check_token()) {
		error::fatal(m('Error: Invalid token'));
	}
	
	$_POST['phrase'] = str_replace("\r", '', $_POST['phrase']);
	
	// Prevent CSRF.
	if (empty($_POST['start_time']) || $_POST['start_time'] != $_SESSION['exterminate_start_time']) {
		error::fatal('Session error.');
	}
	
	if (strlen($_POST['phrase']) < 4) {
		error::fatal('That phrase is too short.');
	}
	
	$phrase = '%' . $_POST['phrase'] . '%';
	
	if (ctype_digit($_POST['range'])) {
		$affect_posts_after = $_SERVER['REQUEST_TIME'] - $_POST['range'];
		
		// Delete replies.
		$fetch_parents = $db->q('SELECT id, parent_id FROM replies WHERE body LIKE ? AND time > ?', $phrase, $affect_posts_after);
		
		$victim_parents = array();
		while (list($reply_id, $parent_id) = $fetch_parents->fetch()) {
			$db->q('UPDATE topics SET replies = replies - 1 WHERE id = ?', $parent_id);
			delete_image('reply', $reply_id);
		}
		
		$db->q('DELETE FROM replies WHERE body LIKE ? AND time > ?', $phrase, $affect_posts_after);
		
		$fetch_topics = $db->q('SELECT id FROM topics WHERE body LIKE ? OR headline LIKE ? AND time > ?', $phrase, $phrase, $affect_posts_after);
		while ($topic_id = $fetch_topics->fetchColumn()) {
			delete_image('topic', $topic_id);
			$fetch_replies = $db->q('SELECT id FROM replies WHERE parent_id = ?', $topic_id);
			while($reply_id = $fetch_replies->fetchColumn()) {
				delete_image('reply', $reply_id);
			}
			$db->q('DELETE FROM replies WHERE parent_id = ?', $topic_id);
		}
		
		// Delete topics.
		$db->q('DELETE FROM topics WHERE body LIKE ? OR headline LIKE ? AND time > ?', $phrase, $phrase, $affect_posts_after);
		$_SESSION['notice'] = 'Finished.';
	}
}

$start_time                         = $_SERVER['REQUEST_TIME'];
$_SESSION['exterminate_start_time'] = $start_time;
?>
<p>This features removes all posts that contain anywhere in the body or headline the exact phrase that you specify.</p>
<form action="" method="post" onsubmit="if(!confirm('Are you sure you want to do this?')){return false;}">
	<?php csrf_token() ?>
	<div class="noscreen">
		<input type="hidden" name="start_time" value="<?php echo $start_time ?>" />
	</div>
	<div class="row">
		<label for="phrase">Phrase</label>
		<textarea id="phrase" name="phrase"></textarea>
	</div>
	<div class="row">
		<label for="range" class="inline">Affect posts made within:</label>
		<select id="range" name="range" class="inline">
			<option value="28800">Last 8 hours</option>
			<option value="86400">Last 24 hours</option>
			<option value="259200">Last 72 hours</option>
			<option value="604800">Last week</option>
			<option value="2629743">Last month</option>
		</select>
	</div>
	<div class="row">
			<input type="submit" name="exterminate" value="Do it" />
		</div>
</form>
<?php
$template->render();
?>