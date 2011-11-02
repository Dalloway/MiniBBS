<?php
require './includes/bootstrap.php';

$page = new Paginate();
// Check if we're on a specific page.
if ($page->current === 1) {
	$template->title   = 'Latest replies';
	update_activity('latest_replies');
} else {
	$template->title   = 'Replies, page #' . number_format($page->current);
	update_activity('replies', $page->current);
}

$res = $db->q('SELECT replies.id, replies.parent_id, replies.time, replies.body, replies.namefag, replies.tripfag, replies.link, topics.headline, topics.time AS parent_time FROM replies INNER JOIN topics ON replies.parent_id = topics.id WHERE replies.deleted = \'0\' AND topics.deleted = \'0\' ORDER BY id DESC LIMIT '.$page->offset.', '.$page->limit);

$columns = array
(
	'Snippet',
	'Topic',
	'Name',
	'Age ▼'
);
$replies = new Table($columns, 1);
$replies->add_td_class(1, 'topic_headline');
$replies->add_td_class(0, 'snippet');

while ($reply = $res->fetchObject()) {
	$values = array
	(
		'<a href="'.DIR.'topic/' . $reply->parent_id . '#reply_' . $reply->id . '">' . parser::snippet($reply->body) . '</a>',
		'<a href="'.DIR.'topic/' . $reply->parent_id . '">' . htmlspecialchars($reply->headline) . '</a> <span class="help unimportant" title="' . format_date($reply->parent_time) . '">(' . age($reply->parent_time) . ' old)</span>',
		format_name($reply->namefag, $reply->tripfag, $reply->link),
		'<span class="help" title="' . format_date($reply->time) . '">' . age($reply->time) . '</span>'
	);
	
	$replies->row($values);
}
$replies->output();

// Navigate backward or forward.
$page->navigation('replies', $replies->row_count);
$template->render();
?>