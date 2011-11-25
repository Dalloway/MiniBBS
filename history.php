<?php
require './includes/bootstrap.php';
update_activity('history');
force_id();

$page = new Paginate();
$template->title = 'Your posting history';

if ($page->current > 1) {
	$template->title .= ', page #' . number_format($page->current);
}

if(isset($_POST['clear_citations']) && check_token()) {
	$db->q('DELETE FROM citations WHERE uid = ?', $_SESSION['UID']);
	redirect('Citations cleared.', '');
}

if($notifications['citations']) {
	if( ! isset($_GET['citations'])) {
		echo '<h4 class="section">Replies to your replies</h4>';
	} else {
		$template->title = 'Replies to your replies';
	}

	// Delete notifications of replies-to-replies that no longer exist.
	$db->q
	(
		"DELETE citations FROM citations
		INNER JOIN replies ON citations.reply = replies.id 
		INNER JOIN topics ON citations.topic = topics.id 
		WHERE citations.uid = ? AND (topics.deleted = '1' OR replies.deleted = '1')", 		
		$_SESSION['UID']
	);

	// List replies to user's replies.
	$res = $db->q
	(
		'SELECT DISTINCT citations.reply AS id, replies.parent_id, replies.time, replies.body, topics.headline, topics.time AS parent_time
		FROM citations 
		INNER JOIN replies ON citations.reply = replies.id 
		INNER JOIN topics ON replies.parent_id = topics.id 
		WHERE citations.uid = ? ORDER BY citations.reply 
		DESC LIMIT '.$page->offset.', '.$page->limit,
		$_SESSION['UID']
	);

	$columns = array
	(
		'Reply to your reply',
		'Topic',
		'Age ▼'
	);
	$citations = new Table($columns, 1);
	$citations->add_td_class(1, 'topic_headline');
	$citations->add_td_class(0, 'reply_body_snippet');

	while ($reply = $res->fetchObject()) {
		$values = array
		(
			'<a href="'.DIR.'topic/' . $reply->parent_id . ($_SESSION['settings']['posts_per_page'] ? '/reply/' : '#reply_') . $reply->id . '">' . parser::snippet($reply->body) . '</a>',
			'<a href="'.DIR.'topic/' . $reply->parent_id . '">' . htmlspecialchars($reply->headline) . '</a> <span class="help unimportant" title="' . format_date($reply->parent_time) . '">(' . age($reply->parent_time) . ' old)</span>',
			'<span class="help" title="' . format_date($reply->time) . '">' . age($reply->time) . '</span>'
		);
		
		$citations->row($values);
	}
	$citations->output('(It appears that the reply to your reply has since been deleted.)');
?>
<form action="" method="post">
	<?php csrf_token() ?>
	<input type="submit" name="clear_citations" value="Clear citations" class="help" title="You will no longer be notified of these replies." />
</form>
<?php
}

if( ! $_GET['citations']) {
	if($notifications['citations']) {
		echo '<h4 class="section">Your posts</h4>';
	}
	// List topics.
	$res = $db->q('SELECT id, time, replies, visits, headline, poll, locked, sticky FROM topics WHERE author = ? AND deleted = 0 ORDER BY id DESC LIMIT '.$page->offset.', '.$page->limit, $_SESSION['UID']);

	$columns = array
	(
		'Headline',
		'Replies',
		'Visits',
		'Age ▼'
	);
	$topics = new Table($columns, 0);
	$topics->add_td_class(0, 'topic_headline');

	while (list($topic_id, $topic_time, $topic_replies, $topic_visits, $topic_headline, $topic_poll, $topic_locked, $topic_sticky) = $res->fetch()) {
		$values = array
		(
			format_headline(htmlspecialchars($topic_headline), $topic_id, $topic_replies, $topic_poll, $topic_locked, $topic_sticky),
			replies($topic_id, $topic_replies),
			format_number($topic_visits),
			'<span class="help" title="' . format_date($topic_time) . '">' . age($topic_time) . '</span>'
		);
		
		$topics->row($values);
	}
	$num_topics_fetched = $topics->row_count;
	echo $topics->output();

	// List replies.
	$res = $db->q
	(
		'SELECT replies.id, replies.parent_id, replies.time, replies.body, topics.headline, topics.time, topics.replies
		FROM replies 
		INNER JOIN topics ON replies.parent_id = topics.id 
		WHERE replies.author = ? AND replies.deleted = 0 AND topics.deleted = 0 
		ORDER BY id DESC LIMIT '.$page->offset.', '.$page->limit, 
		$_SESSION['UID']
	);

	$columns = array
	(
		'Reply snippet',
		'Topic',
		'Replies',
		'Age ▼'
	);
	$replies = new Table($columns, 1);
	$replies->add_td_class(1, 'topic_headline');
	$replies->add_td_class(0, 'reply_body_snippet');

	while (list($reply_id, $parent_id, $reply_time, $reply_body, $topic_headline, $topic_time, $topic_replies) = $res->fetch()) {
		$values = array
		(
			'<a href="'.DIR.'topic/' . $parent_id . ($_SESSION['settings']['posts_per_page'] ? '/reply/' : '#reply_') . $reply_id . '">' . parser::snippet($reply_body) . '</a>',
			'<a href="'.DIR.'topic/' . $parent_id . '">' . htmlspecialchars($topic_headline) . '</a> <span class="help unimportant" title="' . format_date($topic_time) . '">(' . age($topic_time) . ' old)</span>',
			replies($parent_id, $topic_replies),
			'<span class="help" title="' . format_date($reply_time) . '">' . age($reply_time) . '</span>'
		);
		
		$replies->row($values);
	}
	$num_replies_fetched = $replies->row_count;
	$replies->output();
}

if($num_topics_fetched + $num_replies_fetched == 0 && ! isset($_GET['citations'])) {
	echo '<p>You haven\'t posted anything yet.</p>';
}

$page->navigation('history', $num_replies_fetched);
$template->render();
?>