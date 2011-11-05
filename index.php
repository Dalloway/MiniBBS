<?php
require './includes/bootstrap.php';

/* Should we sort by topic creation date or last bump? */
if($_GET['topics'] || ($_SESSION['settings']['topics_mode'] && ! $_GET['bumps']) ) {
	$topics_mode = true;
} else {
	$topics_mode = false;
}

/* Handle pagination */
$page = new Paginate();
update_activity('topics', $page->current);

if ($page->current === 1) {
	if($topics_mode) {
		$template->title = 'Latest topics';
		$last_seen = $_COOKIE['last_topic'];
	} else {
		$template->title = 'Latest bumps';
		$last_seen = $_COOKIE['last_bump'];
	}
} else {
	$template->title = 'Topics, page #' . number_format($page->current);
}

/* Update the last_bump and last_topic cookies. These control both the last seen marker and the exclamation mark in main menu. */
if($_COOKIE['last_bump'] <= $last_actions['last_bump']) {
	setcookie('last_bump', $_SERVER['REQUEST_TIME'], $_SERVER['REQUEST_TIME'] + 315569260, '/');
	$_COOKIE['last_bump'] = $_SERVER['REQUEST_TIME'];
}
if($_COOKIE['last_topic'] <= $last_actions['last_topic']) {
	setcookie('last_topic', $_SERVER['REQUEST_TIME'], $_SERVER['REQUEST_TIME'] + 315569260, '/');
	$_COOKIE['last_topic'] = $_SERVER['REQUEST_TIME'];
}

/* Print a few bulletins. */
$last_seen_bulletin = ($_COOKIE['last_bulletin'] ? $_COOKIE['last_bulletin'] : 0);
if(BULLETINS_ON_INDEX > 0 && ( ! isset($last_actions['last_bulletin']) || $last_actions['last_bulletin'] > $last_seen_bulletin)) {
	setcookie('last_bulletin', $_SERVER['REQUEST_TIME'], $_SERVER['REQUEST_TIME'] + 315569260, '/');

	$columns = array
	(
		'Bulletin',
		'Author',
		'Age ▼'
	);
	if($perm->get('delete')) {
		$columns[] = 'Delete';
	}

	$table = new Table($columns, 0);
	
	$res = $db->q('SELECT id, message, time, author, name, trip FROM bulletins WHERE time > ? ORDER BY id DESC LIMIT ' . (int) BULLETINS_ON_INDEX, $last_seen_bulletin);
	while( $bulletin = $res->fetchObject() ) {		
		$values = array
		(
			parser::parse($bulletin->message),
			format_name($bulletin->name, $bulletin->trip, $perm->get('link', $bulletin->author)),
			'<span class="help" title="'.format_date($bulletin->time).'">' . age($bulletin->time) . '</span>'
		);
		if($perm->get('delete')) {
			$values[] = '<a href="'.DIR.'delete_bulletin/'.$bulletin->id.'" onclick="return quickAction(this, \'Really delete this bulletin?\');">✘</a>';
		}
		
		$table->row($values);
	}
	$table->output();
}

/* Print the topic list. */
$order_name = ($topics_mode) ? 'Age' : ((MOBILE_MODE) ? 'Bump' : 'Last bump');
$columns = array
(
	'Headline',
	'Snippet',
	'Author',
	'Replies',
	'Visits',
	$order_name . ' ▼'
);
/* If mobile mode is enabled, remove the visits column. */
if(MOBILE_MODE) {
	unset($columns[4]);
}
/* If celebrity mode is disabled, remove the poster name. */
if( ! $_SESSION['settings']['celebrity_mode']) {	
	unset($columns[2]);
}
/* If spoiler mode is disabled, remove the snippet column. */
if( ! $_SESSION['settings']['spoiler_mode']) {	
	unset($columns[1]);
}

$table = new Table($columns, 0);
$table->add_td_class(0, 'topic_headline');
$table->add_td_class(1, 'snippet');

$order_by = ($topics_mode) ? 'id' : 'last_post';

$db->select('topics.id, topics.time, topics.replies, topics.visits, topics.headline, topics.body, topics.last_post, topics.locked, topics.sticky, topics.poll, topics.namefag, topics.tripfag')
   ->from('topics')
   ->where("deleted = '0'")
   ->order_by('sticky DESC, ' . $order_by . ' DESC')
   ->limit($page->offset, $page->limit);
if($notifications['citations']) {
	/* A temporary solution for emphasizing topics with replies-to-your-replies */
	$db->select('citations.topic AS citation')
	   ->distinct()
	   ->join('citations', '(citations.uid = ' . $db->quote($_SESSION['UID']) . ' AND citations.topic = topics.id)');
}
$res = $db->exec();

$new_items = false;
while($topic = $res->fetchObject()) {
	/* Should we even bother? */
	if(is_ignored($topic->headline, $topic->body, $topic->namefag, $topic->tripfag)) {
		/* We've encountered an ignored phrase, so skip the rest of this while() iteration. */
		$table->row_count++;
		continue;
	}
	
	/* Reset the CSS class */
	$row_class = '';
	
	/* Decide what to use for the last seen marker and the age/last bump column. */
	$order_time = ($topics_mode) ? $topic->time : $topic->last_post;
	
	/* Format the topic author's name. */
	if($topic->namefag) {
		$author = '<strong>'.htmlspecialchars($topic->namefag).'</strong>';
		if($topic->tripfag) {
			$author = '<span class="help" title="'.$topic->tripfag.'">' . $author . '</span>';
		}
	} else if($topic->tripfag) {
		$author = $topic->tripfag;
	} else {
		$author = 'Anonymous';
	}
	
	$headline = htmlspecialchars($topic->headline);
	if(isset($topic->citation) && $topic->citation) {
		$headline = '<em class="help" title="New reply to your reply inside!">' . $headline . '</em>';
	}
	
	/* snippet() is slow, so let's not run it unnecessarily */
	$snippet = '';
	if($_SESSION['settings']['spoiler_mode']) {
		$snippet = parser::snippet($topic->body);
	}
		
	/* Process the values for this row of our table. */
	$values = array 
	(
		format_headline($headline, $topic->id, $topic->replies, $topic->poll, $topic->locked, $topic->sticky),
		$snippet,
		$author,
		replies($topic->id, $topic->replies),
		format_number($topic->visits),
		'<span class="help" title="' . format_date($order_time) . '">' . age($order_time) . '</span>'
	);
	
	if(MOBILE_MODE) {
		unset($values[4]);
	}
	if( ! $_SESSION['settings']['celebrity_mode']) {	
		unset($values[2]);
	}
	if( ! $_SESSION['settings']['spoiler_mode']) {	
		unset($values[1]);
	}
	
	if($order_time > $last_seen) {
		$new_items = true;
	} else if($new_items) {
		$row_class = 'last_seen_marker';
		$new_items = false;
	}

	$table->row($values, $row_class);
}
$table->output('(No one has created a topic yet.)');

$navigation_path = ($topics_mode ? 'topics' : 'bumps');
$page->navigation($navigation_path, $table->row_count);
$template->render();
?>