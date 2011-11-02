<?php
require './includes/bootstrap.php';
update_activity('activity', 1);

$res = $db->q('SELECT activity.action_name, activity.action_id, activity.uid, activity.time, topics.headline FROM activity LEFT OUTER JOIN topics ON activity.action_id = topics.id WHERE activity.time > ? - 960 ORDER BY time DESC', $_SERVER['REQUEST_TIME']);
$count      = $db->num_rows();
$template->title = 'Folks on-line (' . $count . ')';

$columns = array(
	'Doing',
	'Poster',
	'Last sign of life ▼'
);

$table = new Table($columns, 0);

$i = 0;

while (list($action, $action_id, $uid, $age, $headline) = $res->fetch()) {
	// Maximum amount of actions to be shown.
	if (++$i == 100) {
		break;
	}
	
	if ($uid == $_SESSION['UID']) {
		$uid = 'You!';
	} else {
		if ($perm->get('view_profile')) {
			$uid = '<a href="'.DIR.'profile/' . $uid . '">' . $uid . '</a>';
		} else {
			$uid = '?';
		}
	}
	
	$bump     = age($age, $_SERVER['REQUEST_TIME']);
	$headline = htmlspecialchars($headline);
	// Array key based off.
	$actions  = array(
		'advertise' => 'Inquiring about advertising.',
		'statistics' => 'Looking at board statistics.',
		'hot_topics' => 'Looking at the hottest topics.',
		'shuffle' => 'Doing a topic shuffle.',
		'bulletins' => 'Reading latest bulletins.',
		'bulletins_old' => 'Reading latest bulletins.',
		'bulletins_new' => 'Posting a new bulletin.',
		'events' => 'Checking out events.',
		'events_new' => 'Posting a new event.',
		'activity' => 'Looking at what other people are doing.',
		'ignore_list' => 'Editing their ignore list.',
		'notepad' => 'Reading or writing in their <a href="'.DIR.'notepad">notepad</a>.',
		'topics' => 'Looking at older topics.',
		'dashboard' => 'Modifying their dashboard',
		'latest_replies' => 'Looking at latest replies.',
		'latest_bumps' => 'Checking out latest bumps.',
		'latest_topics' => 'Checking out latest topics.',
		'search' => 'Searching for a topic.',
		'stuff' => 'Looking at stuff.',
		'history' => 'Looking at post history.',
		'failed_postings' => 'Looking at post failures.',
		'watchlist' => 'Checking out their watchlist.',
		'restore_id' => 'Logging in.',
		'new_topic' => 'Creating a new topic.',
		'nonexistent_topic' => 'Trying to look at a non-existant topic.',
		'topic' => "Reading in topic: <strong><a href=\"".DIR."topic/$action_id\">$headline</a></strong>",
		'replying' => "Replying to topic: <strong><a href=\"".DIR."topic/$action_id\">$headline</a></strong>",
		'topic_trivia' => "Reading <a href=\"".DIR."trivia_for_topic/$action_id\">trivia for topic</a>: <strong><a href=\"".DIR."topic/$action_id\">$headline</a></strong>",
		'trash_can' => 'Going through the trash.',
		'status_check' => 'Doing a status check.',
		'banned' => 'Being banned.'
	);
	
	$action = $actions[$action];
	
	// Unknown or unrecorded actions are bypassed.
	if ($action == null) {
		continue;
	}
	
	// Repeated actions are listed as (See above).
	if ($action == $old_action) {
		$temp = '<span class="unimportant">(See above)</span>';
	} else {
		$old_action = $action;
		$temp       = $action;
	}
	
	$values = array(
		$temp,
		$uid,
		'<span class="help" title="' . format_date($age) . '">' . age($age) . '</span>'
	);
	$table->row($values);
}
$table->output();
if ($count > 100) {
	echo '<p class="unimportant">(There are "a lot" of people active right now. Not all are shown here.)</p>';
}
$template->render();
?>