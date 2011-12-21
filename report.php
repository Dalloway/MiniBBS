<?php
require './includes/bootstrap.php';
force_id();
$template->onload = "focusId('reason');";

if($_SESSION['post_count'] < 1) {
	error::fatal('You need at least one post to file a report.');
}

if( ! $perm->get('report')) {
	error::fatal(m('Error: Access denied'));
}

if(ctype_digit($_GET['reply'])) {
	$res = $db->q('SELECT parent_id, deleted FROM replies WHERE id = ?', $_GET['reply']);
	list($topic_id, $deleted) = $res->fetch(PDO::FETCH_NUM);
	if( ! $topic_id || $deleted) {
		error::fatal('There is no such reply.');
	}
	$post_type = 'reply';
	$post_id = $_GET['reply'];
	$location = 'topic/' . (int) $topic_id . '#reply_' . (int) $post_id;
} else if(ctype_digit($_GET['topic'])) {
	$res = $db->q('SELECT 1, deleted FROM topics WHERE id = ?', $_GET['topic']);
	list($exists, $deleted) = $res->fetch(PDO::FETCH_NUM);
	if( ! $exists || $deleted) {
		error::fatal('There is no such topic.');
	}
	$post_type = 'topic';
	$post_id = $_GET['topic'];
	$location = 'topic/' . (int) $post_id;
} else {
	error::fatal('No valid ID was specified.');
}

$template->title = 'Report <a href="' . DIR . $location . '">a ' . $post_type . '</a>';

if(isset($_POST['reason'])) {
	check_token();
	check_length($_POST['reason'], 'report reason', 0, 512);
	$res = $db->q('SELECT COUNT(*) FROM reports WHERE reporter = ?', $_SESSION['UID']);
	if($res->fetchColumn() > 12) {
		error::add('Please wait a while before reporting any more posts.');
	}
	
	if(error::valid()) {
		$db->q
		(
			'INSERT INTO reports 
			(type, post_id, reason, reporter) VALUES 
			(?, ?, ?, ?)', 
			$post_type, $post_id, $_POST['reason'], $_SESSION['UID']
		);
		redirect('Thanks for your report.', $location);
	}
}

error::output();
?>
<p><?php echo m('Report: Help') ?></p>

<form action="" method="post">
	<?php csrf_token() ?>
	<label for="reason">Reason</label>
	<input type="text" id="reason" name="reason" size="80" maxlength="512" />
	<input type="submit" name="submit" value="Report <?php echo $post_type ?>" />
</form>

<?php
$template->render();
?>