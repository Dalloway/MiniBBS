<?php
require './includes/bootstrap.php';

if ( ! ctype_digit($_GET['id'])) {
	error::fatal('Invalid ID.');
}
$topic_id = (int) $_GET['id'];

update_activity('topic', $topic_id);

/* Delete any citation notifications for this topic. */
if($notifications['citations']) {
	$res = $db->q('DELETE FROM citations WHERE uid = ? AND topic = ?', $_SESSION['UID'], $topic_id);
	$notifications['citations'] -= $res->rowCount();
}

/* Fetch OP data. */
$db->select('topics.time, topics.author, topics.visits, topics.replies, topics.headline, topics.body, topics.edit_time, topics.edit_mod, topics.namefag, topics.tripfag, topics.link, topics.deleted, topics.sticky, topics.locked, topics.poll, topics.poll_votes, topics.poll_hide, topics.last_post, topics.imgur')
  ->from('topics')
  ->where('id = ?', $topic_id);
/* No point joining the images table if images are disabled. */
if(ALLOW_IMAGES && ! $_SESSION['settings']['text_mode']) {
	$db->select('images.file_name, images.original_name, images.md5')
	   ->join('images', 'topics.id = images.topic_id');
}
$res = $db->exec();

if( ! $topic = $res->fetchObject()) {
	$template->title = 'Non-existent topic';
	error::fatal('There is no such topic. It may have been deleted.');
}

if($topic->deleted && $topic->author != $_SESSION['UID'] && ! $perm->get('undelete')) {
	$template->title = 'Deleted topic';
	error::fatal('This topic was deleted.');
}

$template->title = 'Topic: ' . htmlspecialchars($topic->headline);

/* Initalize the topic visits array for new visitors. */
if( ! is_array($_SESSION['topic_visits'])) {
	$_SESSION['topic_visits'] = array();
}
/* Last time we viewed this thread, the reply count was: */
$last_read_post = $_SESSION['topic_visits'][$topic_id];
/* Increment visit count. */
if ( ! isset($_SESSION['topic_visits'][$topic_id]) && isset($_COOKIE['SID'])) {
	$db->q('UPDATE topics SET visits = visits + 1 WHERE id = ? LIMIT 1', $topic_id);
}

/* Check if this topic is being watched */
if($_SESSION['ID_activated']) {
	$res = $db->q('SELECT new_replies FROM watchlists WHERE topic_id = ? AND uid = ? LIMIT 1', $topic_id, $_SESSION['UID']);
	$watchlist_new = $res->fetchColumn();
	/* If the topic is watched, either '0' or '1' (string) will be returned; false (bool) will be returned otherwise. */
	if($watchlist_new !== false) {
		$watched = true;
		if($watchlist_new) {
			/* This topic had unread replies until now. */
			$res = $db->q('UPDATE watchlists SET new_replies = 0 WHERE topic_id = ? AND uid = ? LIMIT 1', $topic_id, $_SESSION['UID']);
			$notifications['watchlist'] -= $res->rowCount();
		}
	} else {
		$watched = false;
	}
}

/* Automatically lock the topic after so many seconds from the last reply */
if(AUTOLOCK && ($_SERVER['REQUEST_TIME'] - $topic->last_post) > AUTOLOCK && $topic->author != $_SESSION['UID']) {
	$topic->locked = true;
}

?>
<a name="topic_top"> </a>

<?php
topic_pages($topic->replies);

if( ! $_SESSION['settings']['posts_per_page'] || ! isset($_GET['page']) || $_GET['page'] == 1):
?>

<h3 id="OP"><span class="join_space help" title="This poster started the topic." onclick="highlightPoster(0);">+</span> <span class="poster_number_0" id="join_0"><?php echo format_name($topic->namefag, $topic->tripfag, $topic->link, 0) ?></span> <?php if($topic->author == $_SESSION['UID']) echo '(you)' ?> — <strong><span class="help" title="<?php echo format_date($topic->time) ?>"><?php echo age($topic->time) ?> ago</span>  <span class="reply_id unimportant"><a href="<?php echo DIR ?>topic/<?php echo $topic_id ?>">#<?php echo number_format($topic_id) ?></a></span></strong></h3>

<div class="body poster_body_0">
<?php
	if($topic->imgur):
?>
	<a href="http://i.imgur.com/<?php echo htmlspecialchars($topic->imgur) ?>.jpg" class="thickbox">
		<img src="http://i.imgur.com/<?php echo htmlspecialchars($topic->imgur) ?>m.jpg" alt="" class="help" title="Externally hosted image" />
	</a>
<?php
	elseif(is_ignored($topic->md5)):
		$image_ignored = true;
?>
	<div class="unimportant hidden_image">(<strong><a href="<?php echo DIR . 'img/' . htmlspecialchars($topic->file_name) ?>"><?php echo htmlspecialchars($topic->original_name) ?></a></strong> hidden.)</div>
<?php
	elseif($topic->file_name):
?>
	<a href="<?php echo DIR ?>img/<?php echo htmlspecialchars($topic->file_name) ?>" class="thickbox">
		<img src="<?php echo DIR ?>thumbs/<?php echo htmlspecialchars($topic->file_name) ?>" alt=""<?php if(!empty($topic->original_name)) echo ' class="help" title="'.htmlspecialchars($topic->original_name).'"' ?> />
	</a>
<?php
	endif;
	echo parser::parse($topic->body);
	edited_message($topic->time, $topic->edit_time, $topic->edit_mod);
?>

	<ul class="menu">
<?php
	if(isset($image_ignored)):
		unset($image_ignored);
?>
		<li><a href="<?php echo DIR ?>unhide_image/<?php echo $topic->md5 ?>" onclick="return quickAction(this, 'Really unhide all instances of this image?');">Unhide image</a></li>
<?php
	elseif($_SESSION['settings']['ostrich_mode'] && $topic->file_name):
?>	
		<li><a href="<?php echo DIR ?>hide_image/<?php echo $topic->md5 ?>" onclick="return quickAction(this, 'Really hide all instances of this image?');">Hide image</a></li>
<?php
	endif;
	if($topic->author == $_SESSION['UID'] && $perm->get('edit_limit') == 0 || $topic->author == $_SESSION['UID'] && ($_SERVER['REQUEST_TIME'] - $topic->time < $perm->get('edit_limit')) || $perm->get('edit_others')):
?>
		<li><a href="<?php echo DIR ?>edit_topic/<?php echo $topic_id ?>">Edit</a></li>
<?php
	endif;

	if( ! $perm->get('read_mod_pms')):
?>
		<li><a href="<?php echo DIR ?>report_topic/<?php echo $topic_id ?>">Report</a></li>
<?php
	endif;

	if($perm->get('merge') && ! $topic->deleted):
?>
		<li><a href="<?php echo DIR ?>merge/<?php echo $topic_id ?>">Merge</a></li>
<?php
	endif;
	
	if($perm->get('view_profile')):
?>
		<li><a href="<?php echo DIR ?>profile/<?php echo $topic->author ?>">Profile</a></li>
<?php
	endif;
	if($perm->get('stick') && ! $topic->sticky):
?>	
		<li><a href="<?php echo DIR ?>stick_topic/<?php echo $topic_id ?>" onclick="return quickAction(this, 'Stick this topic?')">Stick</a></li>
<?php
	elseif($perm->get('stick')):
?>
		<li><a href="<?php echo DIR ?>unstick_topic/<?php echo $topic_id ?>" onclick="return quickAction(this, 'Unstick this topic?');">Unstick</a></li>
<?php
	endif;
	if($perm->get('lock') && ! $topic->locked):
?>
		<li><a href="<?php echo DIR ?>lock_topic/<?php echo $topic_id ?>" onclick="return quickAction(this, 'Really lock this topic?');">Lock</a></li>
<?php
	elseif($perm->get('lock')):
?>
		<li><a href="<?php echo DIR ?>unlock_topic/<?php echo $topic_id ?>" onclick="return quickAction(this, 'Unlock this topic?');">Unlock</a></li>
<?php
	endif;
	if($perm->get('delete')):
		if( ! $topic->deleted):
?>
		<li><a href="<?php echo DIR ?>delete_topic/<?php echo $topic_id ?>" onclick="return quickAction(this, 'Really delete this topic?');">Delete</a></li>
<?php
		else:
?>
		<li><a href="<?php echo DIR ?>undelete_topic/<?php echo $topic_id ?>" onclick="return quickAction(this, 'Really undelete this topic?');">Undelete</a></li>
<?php
		endif;
	endif;
	
	if($topic->file_name && ( $perm->get('delete') || ($topic->author == $_SESSION['UID'] && ($perm->get('edit_limit') == 0 || ($_SERVER['REQUEST_TIME'] - $topic->time < $perm->get('edit_limit')))))):
?>
		<li><a href="<?php echo DIR ?>delete_image/<?php echo $topic_id ?>" onclick="return quickAction(this, 'Really delete this image?');">Delete image</a></li>
<?php	
	endif;
	
	if($topic->author !== $_SESSION['UID']):
?>
		<li><a href="<?php echo DIR ?>contact_OP/<?php echo $topic_id ?>">PM</a></li>
<?php
	endif;
	if( ! $watched):
?>
		<li><a href="<?php echo DIR ?>watch_topic/<?php echo $topic_id ?>" onclick="return quickAction(this, 'Really watch this topic?');">Watch</a></li>
<?php
	else:
?>
		<li><a href="<?php echo DIR ?>unwatch_topic/<?php echo $topic_id ?>" onclick="return quickAction(this, 'Really unwatch this topic?');">Unwatch</a></li>
<?php
	endif;
	if( ! $topic->locked || $perm->get('lock')):
?>
		<li><a href="<?php echo DIR ?>new_reply/<?php echo $topic_id ?>/quote_topic" onclick="quickQuote('OP', '<?php echo encode_quote($topic->body) ?>');return false;">Quote</a></li>
<?php
	endif;
?>
		<li><a href="<?php echo DIR ?>trivia_for_topic/<?php echo $topic_id ?>" class="help" title="<?php echo $topic->replies . ' repl' . ($topic->replies == 1 ? 'y' : 'ies') ?>"><?php echo $topic->visits . ' visit' . ($topic->visits == 1 ? '' : 's') ?></a></li>
	</ul>
<?php
	if($topic->deleted):
		$res = $db->q("SELECT mod_uid, time FROM mod_actions WHERE `action` = 'delete_topic' AND `target` = ? LIMIT 1", $topic_id);
		list($deleted_by, $deleted_at) = $res->fetch(PDO::FETCH_NUM);
?>
	<div class="deleted_post">This topic was deleted<?php if(!empty($deleted_by)): ?> <span class="help" title="<?php echo format_date($deleted_at) ?>"><?php echo age($deleted_at) ?> ago</span> by <?php echo htmlspecialchars($perm->get_name($deleted_by)); endif ?>.</div>
<?php
	endif;
?>
</div>
<?php
endif;
?>

<?php
/* Output poll. */
if($topic->poll) {
	$check_votes = $db->q('SELECT 1, option_id FROM poll_votes WHERE uid = ? AND parent_id = ? LIMIT 1', $_SESSION['UID'], $topic_id);
	list($voted, $chosen_option) = $check_votes->fetch(PDO::FETCH_NUM);

	echo '<form action="' . DIR . 'cast_vote/' . $topic_id . '" method="post" id="poll">';
	csrf_token();
	
	$columns = array
	(
		'Poll option',
		'Votes',
		'Percentage',
		'Graph'
	);
	
	if($topic->poll_hide && ! $voted) {
		$columns = array_slice($columns, 0, 1);
	}
	
	$table = new Table($columns, 0);

	$options = $db->q('SELECT poll_options.id, poll_options.option, poll_options.votes FROM poll_options WHERE poll_options.parent_id = ?', $topic_id);
	while($option = $options->fetchObject()) {
		if($topic->poll_votes == 0) {
			$percent = 0;
		} else {
			$percent = round(100 * $option->votes / $topic->poll_votes);
		}
	
		$values = array
		(
			htmlspecialchars($option->option),
			format_number($option->votes),
			$percent . '%',
			'<div class="bar_container help" title=" ' . $option->votes . ' of ' . $topic->poll_votes . ' "><div class="bar" style="width: ' . $percent . '%;"></div></div>'
		);
		
		if($topic->poll_hide && ! $voted) {
			$values = array_slice($values, 0, 1);
		}
		
		if( ! $voted) {
			$values[0] = '<input name="option_id" class="inline" value="' . $option->id . '" id="option_' . $option->id . '" type="radio" /><label for="option_' . $option->id . '" class="inline">' . $values[0] . '</label>';
		}
		else if($chosen_option == $option->id) {
			$values[0] = '<strong title="You voted for this." class="help">' . $values[0] . '</strong>';
		}
		
		$table->row($values);
	}
	
	$table->output('(This topic is marked as a poll, but there does not seem to be any options associated with it.)');
	if( ! $voted) {
		echo '<div class="row"><input type="submit" name="cast_vote" value="Cast your vote" class="inline" />';
		if($topic->poll_hide) {
			echo '<input type="submit" name="show_results" value="Show results" class="inline" />';
		}
		echo '</div>';
	}
	echo '</form>';
}	
	
/* Output replies. */
$db->select('replies.id, replies.time, replies.author, replies.body, replies.deleted, replies.edit_time, replies.edit_mod, replies.namefag, replies.tripfag, replies.link, replies.imgur, replies.original_parent')
   ->from('replies')
   ->where('replies.parent_id = ?', $topic_id)
   ->order_by('replies.time');
if (ALLOW_IMAGES && ! $_SESSION['settings']['text_mode']) {
	$db->select('images.file_name, images.original_name, images.md5')
	   ->join('images', 'replies.id = images.reply_id');
}
$replies = $db->exec();

/* The number of the first reply to be printed (for pagination) */
$page_start = 1;
if($_SESSION['settings']['posts_per_page'] && ! empty($_GET['page'])) {
	$page_start += $_SESSION['settings']['posts_per_page'] * ($_GET['page'] - 1);
}
$page_end = $page_start + $_SESSION['settings']['posts_per_page'];
				
$history = array(); // Data on replies.
$posters = array(); // Data on posters
$posters[$topic->author] = array('number' => 0);
$merges = array(); // IDs of merged OPs.
$reply_count = 0; // The number of non-deleted replies.
$poster_number = 1; // The current number of posters in this thread.
$previous_post_time = $topic->time;
$previous_author = $topic->author;

while( $reply = $replies->fetchObject() ) {
	/* Store information about this reply. */
	$joined_in = ! isset($posters[$reply->author]);
	if($joined_in) {
		$posters[$reply->author] = array
		(
			'first_reply' => $reply->id,
			'number'      => $poster_number++
		);
	}
	
	$history[$reply->id] = array
	(
		'body'          => $reply->body,
		'author'        => $reply->author,
		'name'          => $reply->namefag,
		'trip'          => $reply->tripfag,
		'poster_number' => $posters[$reply->author]['number'],
		'post_number'   => $reply_count + 1
	);
	
	/* Skip if deleted and we don't have permission to view */
	if($reply->deleted) {
		$history[$reply->id]['deleted'] = true;
		if($reply->author != $_SESSION['UID'] && ! $perm->get('undelete')) {
			continue;
		}
	} else {
		$reply_count++;
	}
	
	/* Skip if on ignore list (ostrich mode) */
	if(is_ignored($reply->body, $reply->namefag, $reply->tripfag)) {
		$history[$reply->id]['hidden'] = true;
		continue;
	}
	
	/* Skip if it doesn't belong on this page of the topic */
	if($_SESSION['settings']['posts_per_page'] && isset($_GET['page'])) {
		if($_GET['page'] > 1 && $reply_count < $page_start) {
			continue;
		} else if($reply_count == $page_end) {
			$stopped_prematurely = true;
			break;
		}
	}
	
	/* Prepare the header */
	$out = array();
	$out['author_desc'] = '';
	if ($reply->author == $topic->author) {
		$out['author_desc'] = '(OP';
		if ($reply->author == $_SESSION['UID']) {
			$out['author_desc'] .= ', you';
		}
		$out['author_desc'] .= ')';
		
		$out['join_marker'] = '<a href="'.DIR.'topic/' . $topic_id . page($topic->replies) . '#join_0" class="join_space help" title="Click to jump to the OP." onclick="createSnapbackLink(' . $reply->id . '); highlightPoster(0);">·</a>';
	} else {
		if ($reply->author == $_SESSION['UID']) {
			$out['author_desc'] .= '(you)';
		}
		
		if( ! $joined_in) {
			$first_reply = $posters[$reply->author]['first_reply'];
			$out['join_marker'] = '<a href="'.DIR.'topic/' . $topic_id . page($topic->replies, $history[$first_reply]['post_number']) . '#join_'.$posters[$reply->author]['number'].'" class="join_space help" title="Click to jump to this poster\'s first reply." onclick="createSnapbackLink(' . $reply->id . '); highlightPoster('.$posters[$reply->author]['number'].');">·</a>';
		} else {
			$out['join_marker'] = '<span class="join_space help" title="This poster just joined the thread." onclick="highlightPoster('.$posters[$reply->author]['number'].');">+</span>';
		}
	}
	
	/* Prepare the body */
	$parsed_body = parser::parse($reply->body);
	
	/* Linkify citations */
	$parsed_body = str_ireplace('@OP', '<span class="unimportant poster_number_0"><a href="#OP">@OP</a></span>', $parsed_body);

	$citation_count = preg_match_all('/@([0-9,]+)/m', $parsed_body, $citations);
	$citations = (array) $citations[1];
	
	/* If this is a merged reply that contains no citations, add a citation to the original parent. */
	if( ! $citations && $reply->original_parent && isset($merges[$reply->original_parent])) {
		$merge_citation = number_format($merges[$reply->original_parent]);
		$parsed_body = '@' . $merge_citation . '<br />' . $parsed_body;
		$citations[] = $merge_citation;
	}

	if($citation_count > 1) {
		/* Replace each citation only once (preventing memory attacks). */
		$citations = array_unique($citations);
	}
	
	foreach($citations as $citation) {
		$pure_id = str_replace(',', '', $citation);
		
		/* The text that appears next to a citation */
		if ($history[$pure_id]['author'] == $_SESSION['UID']) {
			$cited_name = '<em class="you">(you)</em>';
		} else if($history[$pure_id]['name']) {
			$cited_name = '(' . trim(htmlspecialchars($history[$pure_id]['name'])) . ')';
		} else if($history[$pure_id]['trip']) {
			$cited_name = '(' . trim($history[$pure_id]['trip']) . ')';
		} else {
			$cited_name = '(<strong>' . number_to_letter($history[$pure_id]['poster_number']) . '</strong>)';
		}
		$cited_name = ' <span class="citee">' . $cited_name . '</span>';
		
		if( ! isset($history[$pure_id])) {
			/* Non-existent reply */
			$link = '<span class="unimportant help" title="' . $citation. '">(Citing a non-existent reply.)</span>';
		} else if(isset($history[$pure_id]['deleted']) && $history[$pure_id]['author'] != $_SESSION['UID'] && ! $perm->get('undelete')) {
			/* Deleted reply */
			$link = '<span class="unimportant help" title="@' . $citation . '">@deleted' . $cited_name .'</span>';
		} else if(isset($history[$pure_id]['hidden'])) {
			/* Hidden reply (ostrich mode) */
			$link = '<span class="unimportant help" title="' . parser::snippet($history[$pure_id]['body']) . '">@hidden' . $cited_name . '</span>';
		} else {
			/* Normal citation */
			if($pure_id == $previous_id) {
				$link_text = 'previous';
			} else {
				$link_text = $citation;
			}
			
			$page_link = '';
			if($_SESSION['settings']['posts_per_page'] && isset($_GET['page'])) {
				$page_link = DIR . 'topic/' . (int) $_GET['id'] . page($topic->replies, $history[$pure_id]['post_number']);
			}
						
			$link = '<span class="unimportant poster_number_'.$history[$pure_id]['poster_number'].'"><a href="' . $page_link . '#reply_' . $pure_id . '" onclick="createSnapbackLink(\'' . $reply->id . '\'); highlightReply(\'' . $pure_id . '\');" class="help" title="' . parser::snippet($history[$pure_id]['body']) . '">@' . $link_text . '</a>' . $cited_name . '</span>';
		}
		
		$parsed_body = str_replace('@' . $citation, $link, $parsed_body);
	}
	
	
	/* Now output the reply. */
	echo '<h3 name="reply_' . $reply->id . '" id="reply_' . $reply->id . '"' . ($reply->author == $previous_author ? ' class="repeat_post"' : '') . '>';
	
	/* If this is the newest unread post, let the #new anchor highlight it. */
	if ($reply_count == $last_read_post + 1) {
		echo '<span id="new"></span><input type="hidden" id="new_id" class="noscreen" value="' . $reply->id . '" />';
	}
	
	echo $out['join_marker'] . ' <span class="poster_number_'.$posters[$reply->author]['number'].'"' . ($joined_in ? ' id="join_'.$posters[$reply->author]['number'].'"' : '') . '>' . format_name($reply->namefag, $reply->tripfag, $reply->link, $posters[$reply->author]['number']) . '</span> ' . $out['author_desc'] . ' — <strong><span class="help" title="' . format_date($reply->time) . '">' . age($reply->time) . ' ago</span></strong><span class="unimportant timing_details">, ' . age($reply->time, $previous_post_time) . ' later';
	if ($reply_count > 1) {
		echo ', ' . age($reply->time, $topic->time) . ' after the original post';
	}
	echo '</span>';
	
	if($reply->original_parent && $reply->original_parent != $topic_id) {
		if( ! isset($merges[$reply->original_parent])) {
			$merges[$reply->original_parent] = $reply->id;
			$merge_tooltip = 'This was the original post of another topic merged into this one.';
		} else {
			$merge_tooltip = 'This was a reply to another topic merged into this one. Click me to jump to the original OP.';
		}
		echo ' <a href="'.DIR.'topic/' . (int) $_GET['id'] . page($topic->replies, $history[$merges[$reply->original_parent]]['post_number']) . '#reply_'.$merges[$reply->original_parent].'" class="merge_marker help" title="'.$merge_tooltip.'" onclick="highlightReply('.$merges[$reply->original_parent].')">⧒</a>';
	}
	
	echo '<span class="reply_id unimportant"><a href="#top">[^]</a> <a href="#bottom">[v]</a> <a href="#reply_' . $reply->id . '" onclick="highlightReply(\'' . $reply->id . '\'); removeSnapbackLink">#' . number_format($reply->id) . '</a></span></h3>',
	'<div class="body poster_body_'.$posters[$reply->author]['number'].'" id="reply_box_' . $reply->id . '">';

	if($reply->imgur) {
		echo '<a href="http://i.imgur.com/' . htmlspecialchars($reply->imgur) . '.jpg" class="thickbox">',
		'<img src="http://i.imgur.com/' . htmlspecialchars($reply->imgur) . 'm.jpg" alt="" class="help" title="Externally hosted image" />',
		'</a>';
	}
	else if(is_ignored($reply->md5)) {
		$image_ignored = true;
		echo '<div class="unimportant hidden_image">(<strong><a href="' . DIR . 'img/' . htmlspecialchars($reply->file_name) . '">' . htmlspecialchars($reply->original_name) . '</a></strong> hidden.)</div>';
	}
	else if ($reply->file_name) {
		echo '<a href="'.DIR.'img/' . htmlspecialchars($reply->file_name) . '" class="thickbox"><img src="'.DIR.'thumbs/' . htmlspecialchars($reply->file_name) . '" alt=""';
		if( ! empty($reply->original_name)) {
			echo ' class="help" title="'.htmlspecialchars($reply->original_name).'"';
		}
		echo ' /></a>';
	}
	
	echo $parsed_body;
	
	edited_message($reply->time, $reply->edit_time, $reply->edit_mod);
	
	echo '<ul class="menu">';
	
	if(isset($image_ignored)) {
		unset($image_ignored);
		echo '<li><a href="'.DIR.'unhide_image/' . $reply->md5 . '" onclick="return quickAction(this, \'Really unhide all instances of this image?\');">Unhide image</a></li>';
	} else if($_SESSION['settings']['ostrich_mode'] && $reply->file_name) {
		echo '<li><a href="'.DIR.'hide_image/' . $reply->md5 . '" onclick="return quickAction(this, \'Really hide all instances of this image?\');">Hide image</a></li>'; 
	}
	if ($reply->author == $_SESSION['UID'] && $perm->get('edit_limit') == 0 || $reply->author == $_SESSION['UID'] && ($_SERVER['REQUEST_TIME'] - $reply->time < $perm->get('edit_limit')) || $perm->get('edit_others')) {
		echo '<li><a href="'.DIR.'edit_reply/' . $topic_id . '/' . $reply->id . '">Edit</a></li>';
	}
	
	if ($perm->get('view_profile')) {
		echo '<li><a href="'.DIR.'profile/' . $reply->author . '">Profile</a></li>';
	}
	if ($perm->get('delete')) {
		if( ! $reply->deleted) {
			echo '<li><a href="'.DIR.'delete_reply/' . $reply->id . '" onclick="return quickAction(this, \'Really delete this reply?\');">Delete</a></li>';
		} else if($perm->get('undelete')) {
			echo '<li><a href="'.DIR.'undelete_reply/' . $reply->id . '" onclick="return quickAction(this, \'Really undelete this reply?\');">Undelete</a></li>';
		}
	} else {
		echo '<li><a href="'.DIR.'report_reply/' . $reply->id . '">Report</a></li>';
	}
	
	if($reply->file_name && ( $perm->get('delete') || ($reply->author == $_SESSION['UID'] && ($perm->get('edit_limit') == 0 || ($_SERVER['REQUEST_TIME'] - $reply->time < $perm->get('edit_limit')))))) {
		echo '<li><a href="'.DIR.'delete_image/' . $topic_id . '/' . $reply->id . '" onclick="return quickAction(this, \'Really delete this image?\');">Delete image</a></li>';
	}
	
	if($reply->author !== $_SESSION['UID']) {
		echo '<li><a href="'.DIR.'contact_poster/' . $reply->id . '">PM</a></li>';
	}
	if( ! $topic->locked || $perm->get('lock')) {
		echo '<li><a href="'.DIR.'new_reply/' . $topic_id . '/quote_reply/' . $reply->id . '" onclick="quickQuote('. $reply->id . ', \'' . encode_quote($reply->body) . '\');return false;">Quote</a></li>',
		'<li><a href="'.DIR.'new_reply/' . $topic_id . '/cite_reply/' . $reply->id . '" onclick="quickCite('.$reply->id.');return false;">Cite</a></li></ul>';
	}
	
	if($reply->deleted) {
		$res = $db->q("SELECT mod_uid, time FROM mod_actions WHERE `action` = 'delete_reply' AND `target` = ? LIMIT 1", $reply->id);
		list($deleted_by, $deleted_at) = $res->fetch(PDO::FETCH_NUM);
		echo '<div class="deleted_post">This reply was deleted ';
		if( ! empty($deleted_by)) {
			echo '<span class="help" title="' . format_date($deleted_at) . '">' . age($deleted_at) . ' ago</span> by ' . htmlspecialchars($perm->get_name($deleted_by));
		}
		echo '</div>';
	}
	
	echo '</div>' . "\n\n";
	
	/* Store information for the next round. */
	$previous_id = $reply->id;
	$previous_author = $reply->author;
	if( ! $reply->deleted) {
		$previous_post_time = $reply->time;
	}
}

if( ! isset($stopped_prematurely) && ($reply_count != $topic->replies || $previous_post_time != $topic->last_post)) {
	/* The DB's reply count or last bump time is inaccurate. Fix. */
	$db->q('UPDATE topics SET replies = ?, last_post = ? WHERE id = ? LIMIT 1', $reply_count, $previous_post_time, $topic_id);
	$topic->replies = $reply_count;
}
/* Remember the reply count so we can check for new posts. */
if ($last_read_post !== $topic->replies && isset($_SESSION['UID'])) {
	/* Prepend the array with this topic. */
	$_SESSION['topic_visits'] = array(
		$topic_id => $topic->replies
	) + $_SESSION['topic_visits'];
	/* Limit the number of remembered topics */
	$_SESSION['topic_visits'] = array_slice($_SESSION['topic_visits'], 0, MEMORABLE_TOPICS, true);
	/* Update topic visits in the DB (and last_seen while we're there) */
	$db->q('UPDATE users SET topic_visits = ?, last_seen = ? WHERE uid = ? LIMIT 1', json_encode($_SESSION['topic_visits']), $_SERVER['REQUEST_TIME'], $_SESSION['UID']);
}

topic_pages($topic->replies);

if( (! $topic->locked || $perm->get('lock')) && ! $topic->deleted) {
	echo '<ul class="menu"><li><a href="'.DIR.'new_reply/'.(int)$topic_id.'" onclick="$(\'#quick_reply\').toggle();$(\'#qr_text\').get(0).scrollIntoView(true);$(\'#qr_text\').focus(); return false;">Reply</a>' . ($topic->locked ? ' <small>(locked)</small>' : '') .  '</li></ul>';
} else if($topic->deleted) {
	echo '<ul class="menu"><li>Topic deleted</li></ul>';
} else {
	echo '<ul class="menu"><li>Topic locked</li></ul>';
}
?>
<div id="quick_reply" class="noscreen">
	<form enctype="multipart/form-data" action="<?php echo DIR; ?>new_reply/<?php echo $topic_id; ?>" method="post">
		
		<?php csrf_token(); ?>
		<input name="form_sent" type="hidden" value="1" />
		<input name="e-mail" type="hidden" />
		<input name="start_time" type="hidden" value="<?php echo time(); ?>" />
		<input name="image" type="hidden" value="" />
		<div class="row"><label for="name">Name</label>:
			<input id="name" name="name" type="text" size="30" maxlength="30" tabindex="2" value="<?php echo htmlspecialchars($_SESSION['poster_name']); ?>" class="inline"> <?php if($_SESSION['UID'] == $topic->author) echo ' (OP)' ?>
<?php
if($perm->get('link')):
?>
			<input type="checkbox" name="post_as_group" id="post_as_group" value="1" class="inline" <?php if(isset($_SESSION['show_group'])) echo ' checked="checked"' ?> />
			<label for="post_as_group" class="inline"> Post as <?php echo htmlspecialchars($perm->get('name')) ?></label>
<?php
endif;
?>
		</div>

		<textarea class="inline" name="body" id="qr_text" rows="6" cols="55" tabindex="3"></textarea>
		<div class="unimportant" id="syntax_link"><a href="<?php echo DIR ?>markup_syntax" target="_blank">markup syntax</a></div>
<?php
if(ALLOW_IMAGES && $perm->get('post_image')):
?>
		<input type="file" name="image" id="image" tabindex="5">
<?php
endif;

if(IMGUR_KEY):
?>
		<div>
			<?php if(ALLOW_IMAGES) echo 'Or use an' ?> imgur URL: 
			<input type="text" name="imgur" id="imgur" class="inline" size="21" placeholder="http://i.imgur.com/wDizy.gif" />
			<a href="http://imgur.com/" id="imgur_status" onclick="$('#imgur_file').click(); return false;">[upload]</a>
			<input type="file" id="imgur_file" class="noscreen" onchange="imgurUpload(this.files[0], '<?php echo IMGUR_KEY ?>')" />
		</div>
<?php
endif;
?>

		<input type="submit" name="preview" tabindex="6" value="Preview" class="inline" /> 
		<input type="submit" name="post" tabindex="4" value="Post" class="inline">
	</form>
</div>
<?php
$template->render();
?>