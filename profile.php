<?php
require './includes/bootstrap.php';
force_id();

if( ! $perm->get('view_profile')) {
	error::fatal(MESSAGE_ACCESS_DENIED);
}

if( ! isset($_GET['uid'])) {
	error::fatal('No UID specified.');
}

if(isset($_POST['mass_delete']) && check_token() && $perm->get('delete')) {
	$posts_deleted = 0;
	if(is_array($_POST['topics'])) {
		foreach($_POST['topics'] as $topic_id) {
			delete_topic($topic_id, false);
			$posts_deleted++;
		}
	}
	
	if(is_array($_POST['replies'])) {
		foreach($_POST['replies'] as $reply_id) {
			delete_reply($reply_id, false);
			$posts_deleted++;
		}
	}
	
	$_SESSION['notice'] = number_format($posts_deleted) . ' post' . ($posts_deleted === 1 ? '' : 's') . ' deleted.';
}

if(isset($_POST['mass_undelete']) && check_token() && $perm->get('undelete')) {
	$posts_restored = 0;
	if(is_array($_POST['undelete_topics'])) {
		foreach($_POST['undelete_topics'] as $topic_id) {
			$db->q("UPDATE topics SET deleted = '0' WHERE id = ?", $topic_id);
			log_mod('undelete_topic', $topic_id);
			$posts_restored++;
		}
	}
	
	if(is_array($_POST['undelete_replies'])) {
		foreach($_POST['undelete_replies'] as $reply_id) {
			$db->q("UPDATE replies SET deleted = '0' WHERE id = ?", $reply_id);
			log_mod('undelete_reply', $reply_id);
			$posts_restored++;
		}
	}
	
	$_SESSION['notice'] = number_format($posts_restored) . ' post' . ($posts_restored === 1 ? '' : 's') . ' restored.';
}

$res = $db->q('SELECT first_seen, last_seen, ip_address FROM users WHERE uid = ?', $_GET['uid']);
$uid = $res->fetchObject();

if( ! $uid) {
	error::fatal('There is no such user.');
}

$id_hostname = @gethostbyaddr($uid->ip_address);
if($id_hostname === $uid->ip_address) {
	$id_hostname = false;
}

// Check for ban.
$banned = false;
if($perm->is_banned($_GET['uid'])) {
	list($ban_reason, $ban_expiry, $ban_filed) = $perm->get_ban_log($_GET['uid']);
	if ( ! empty($ban_filed) && ($ban_expiry == 0 || $ban_expiry > $_SERVER['REQUEST_TIME']) ) {
		$banned = true;
	}
}

// Fetch number of topics and replies.
$res = $db->q('SELECT count(*) FROM topics WHERE author = ? AND deleted = 0', $_GET['uid']);
$id_num_topics = $res->fetchColumn();
$res = $db->q('SELECT count(*) FROM replies WHERE author = ? AND deleted = 0', $_GET['uid']);
$id_num_replies = $res->fetchColumn();

// Now print everything.
$template->title = 'Profile of poster ' . $_GET['uid'];

echo '<p>First seen <strong class="help" title="' . format_date($uid->first_seen) . '">' . age($uid->first_seen) . ' ago</strong> using the IP address <strong><a href="'.DIR.'IP_address/' . $uid->ip_address . '">' . $uid->ip_address . '</a></strong> (';

// If there's a valid host name.
if($id_hostname) {
	echo '<strong>' . $id_hostname . '</strong>';
} else {
	echo 'no valid host name';
}
echo ') and last seen <strong class="help" title="' . format_date($uid->last_seen) . '">' . age($uid->last_seen) . ' ago</strong>, has started <strong>' . number_format($id_num_topics) . '</strong> existing topic' . ($id_num_topics == 1 ? '' : 's') . ' and posted <strong>' . number_format($id_num_replies) . '</strong> existing repl' . ($id_num_replies == 1 ? 'y' : 'ies') . '.</p>';

if ($banned) {
	echo '<p>This poster is currently <strong>banned</strong>. The ban was filed <span class="help" title="' . format_date($ban_filed) . '">' . age($ban_filed) . ' ago</span> and will ';
	if ($ban_expiry == 0) {
		echo 'last indefinitely';
	} else {
		echo 'expire in ' . age($ban_expiry);
	}
	echo '.</p>';
}
?>
<form action="<?php echo DIR ?>ban" method="post">
	<?php csrf_token() ?>
	<input type="hidden" name="target" value="<?php echo $_GET['uid'] ?>" />
	<div class="row">
		<label for="ban_length" class="inline">Ban length</label>
		<input type="text" name="length" id="ban_length" value="<?php if( ! $banned) echo '1 day' ?>" class="inline help" tabindex="1" title="A ban length of 'indefinite' or '0' will never expire." onclick="this.value = ''" />
		<label for="ban_reason" class="inline">Reason</label>
		<input type="text" name="reason" id="ban_reason" value="<?php echo htmlspecialchars($ban_reason) ?>" class="inline help" tabindex="2" title="Optional." />
		<input type="submit" value="<?php echo ($banned) ? 'Update ban length' : 'Ban' ?>" class="inline" />
	</div>
</form>
<?php
// Menu
echo '<ul class="menu"><li><a href="'.DIR.'compose_message/' . $_GET['uid'] . '">Send PM</a>';
if($banned) {
	echo '<li><a href="'.DIR.'unban_poster/' . $_GET['uid'] . '" onclick="return quickAction(this, \'Really unban this poster?\');">Unban ID</a></li>';
}
echo '<li><a href="'.DIR.'nuke_ID/' . $_GET['uid'] . '" onclick="return quickAction(this, \'Really delete all topics and replies by this poster?\');">Delete all posts</a></li>';
echo '<li><a href="'.DIR.'delete_all_PMs/' . $_GET['uid'] . '" onclick="return quickAction(this, \'Really delete all PMs sent by this user?\');">Delete all PMs</a></li>';
if($perm->get('manage_permissions')) {
	echo '<li><a href="'.DIR.'manage_permissions/'.$_GET['uid'].'">Manage permissions</a></li>';
}
echo '</ul>',
'<form action="" method="post" id="mass_delete">';
csrf_token();

// Pagination
$page = new Paginate();
if ($page->current > 1) {
	$template->title   .= ', page #' . number_format($page->current);
}

$master_checkbox = '<input type="checkbox" name="master_checkbox" class="inline" onclick="checkAll(\'mass_delete\')" title="Check/uncheck all" />';
if($id_num_topics > 0) {
	echo '<h4 class="section">Topics</h4>';

	$res = $db->q
	(
		'SELECT id, time, replies, visits, headline, author_ip, namefag, tripfag, locked, sticky, poll
		FROM topics 
		WHERE author = ? AND deleted = 0 
		ORDER BY id DESC 
		LIMIT '.$page->offset.', '.$page->limit, $_GET['uid']
	);
	
	$columns = array
	(
		$master_checkbox . 'Headline',
		'Name',
		'IP address',
		'Replies',
		'Visits',
		'Age ▼'
	);
	$topics = new Table($columns, 0);
	$topics->add_td_class(0, 'topic_headline');
	
	while($topic = $res->fetchObject()) 
	{
		$values = array 
		(
			'<input type="checkbox" name="topics[]" value="'.$topic->id.'" class="inline" onclick="highlightRow(this)" />' . format_headline(htmlspecialchars($topic->headline), $topic->id, $topic->replies, $topic->poll, $topic->locked, $topic->sticky),
			($topic->namefag || $topic->tripfag) ? '<strong>' .htmlspecialchars($topic->namefag). '</strong> ' . $topic->tripfag : 'Anonymous',
			'<a href="'.DIR.'IP_address/' . $topic->author_ip . '">' . $topic->author_ip . '</a>',
			replies($topic->id, $topic->replies),
			format_number($topic->visits),
			'<span class="help" title="' . format_date($topic->time) . '">' . age($topic->time) . '</span>'
		);
								
		$topics->row($values);
	}
	$num_topics_fetched = $topics->row_count;
	$topics->output();
}

if($id_num_replies > 0) {
	echo '<h4 class="section">Replies</h4>';

	$res = $db->q
	(
		'SELECT replies.id, replies.parent_id, replies.time, replies.body, replies.author_ip, replies.namefag, replies.tripfag, 
		topics.headline, topics.time AS topic_time 
		FROM replies 
		INNER JOIN topics ON replies.parent_id = topics.id 
		WHERE replies.author = ? AND replies.deleted = 0 AND topics.deleted = 0 
		ORDER BY id DESC 
		LIMIT '.$page->offset.', '.$page->limit, $_GET['uid']
	);
	
	if($id_num_topics) {
		$master_checkbox = '';
	}
	
	$columns = array
	(
		$master_checkbox . 'Reply snippet',
		'Topic',
		'Name',
		'IP address',
		'Age ▼'
	);
	$replies = new Table($columns, 1);
	$replies->add_td_class(1, 'topic_headline');
	$replies->add_td_class(0, 'reply_body_snippet');

	while($reply = $res->fetchObject()) 
	{
		$values = array 
		(
			'<input type="checkbox" name="replies[]" value="'.$reply->id.'" class="inline" onclick="highlightRow(this)" /><a href="'.DIR.'topic/' . $reply->parent_id . ($_SESSION['settings']['posts_per_page'] ? '/reply/' : '#reply_') . $reply->id . '">' . parser::snippet($reply->body) . '</a>',
			'<a href="'.DIR.'topic/' . $reply->parent_id . '">' . htmlspecialchars($reply->headline) . '</a> <span class="help unimportant" title="' . format_date($topic_time) . '">(' . age($reply->topic_time) . ' old)</span>',
			($reply->namefag || $reply->tripfag) ? '<strong>' .htmlspecialchars($reply->namefag). '</strong> ' . $reply->tripfag : 'Anonymous',
			'<a href="'.DIR.'IP_address/' . $reply->author_ip . '">' . $reply->author_ip . '</a>',
			'<span class="help" title="' . format_date($reply->time) . '">' . age($reply->time) . '</span>'
		);
									
		$replies->row($values);
	}
	$num_replies_fetched = $replies->row_count;
	$replies->output();
}

if($perm->get('delete') && $num_topics_fetched + $num_replies_fetched):
?>
	<div class="row">
		<input name="mass_delete" type="submit" value="Delete selected" onclick="return confirm('Really delete selected posts?')" />
	</div>
	</form>
<?php
endif;

$page->navigation('profile/' . $_GET['uid'], $num_replies_fetched);

$fetch_trash = $db->q
(
	"(SELECT id, 0 as parent_id, headline, body, time FROM topics WHERE author = ? AND deleted = '1') 
	UNION 
	(SELECT id, parent_id, '' AS headline, body, time FROM replies WHERE author = ? AND deleted = '1') 
	ORDER BY time DESC", 
	$_GET['uid'], $_GET['uid']
);

$master_checkbox = '<input type="checkbox" name="master_checkbox" class="inline" onclick="checkAll(\'mass_undelete\')" title="Check/uncheck all" />';
$columns = array
(
	$master_checkbox . 'Headline',
	'Body',
	'Age ▼'
);
$table = new Table($columns, 1);

while($trash = $fetch_trash->fetchObject()) {
	if(empty($trash->headline)) {
		$trash->headline = '<input type="checkbox" name="undelete_replies[]" value="'.$trash->id.'" class="inline" onclick="highlightRow(this)" /><span class="unimportant"><a href="'.DIR.'topic/'. $trash->parent_id.'#reply_'. $trash->id.'">(Reply.)</a></span>';
	} else {
		$trash->headline = '<input type="checkbox" name="undelete_topics[]" value="'.$trash->id.'" class="inline" onclick="highlightRow(this)" /><a href="'.DIR.'topic/'.(int) $trash->id.'">' . htmlspecialchars($trash->headline) . '</a>';
	}

	$values = array 
	(
		$trash->headline,
		parser::snippet($trash->body),
		'<span class="help" title="' . format_date($trash->time) . '">' . age($trash->time) . '</span>'
	);
							
	$table->row($values);
}
	
if($page->current == 1 && $table->row_count > 0) {
	echo '<h4 class="section">Trash</h4>',
	'<form action="" method="post" id="mass_undelete">';
	csrf_token();
	$table->output();
	echo '<div class="row">',
	'<input name="mass_undelete" type="submit" value="Restore selected" onclick="return confirm(\'Really restore selected posts?\')" />',
	'</form>';
}

$template->render();
?>