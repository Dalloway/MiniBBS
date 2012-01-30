<?php
require './includes/bootstrap.php';

if ( ! ctype_digit($_GET['id'])) {
	error::fatal('Invalid ID.');
}

try {
	$topic = new Topic( $_GET['id'], (empty($_GET['page']) || ! $_SESSION['settings']['posts_per_page']) ? 0 : (int) $_GET['page'] );
	$OP = $topic->OP;
} catch(Exception $e) {
	$template->title = 'Non-existent topic';
	error::fatal('There is no topic with that ID.');
}

/* Delete citation notifications for this topic. */
if($notifications['citations']) {
	$notifications['citations'] -= $topic->clear_citations();
}

/* Delete watchlist notifications for this topic. */
if($topic->watched_new) {
	$notifications['watchlist'] -= $topic->clear_watchlist();
}

/* If the topic is deleted and we don't have permission to view, die. */
if( ! $OP->deleted) {
	update_activity('topic', $topic->id);
} else if($OP->author != $_SESSION['UID'] && ! $perm->get('undelete')) {
	$template->title = 'Deleted topic';
	error::fatal('This topic was deleted.');
}

$template->title = 'Topic: ' . htmlspecialchars($OP->headline);

$topic->print_pages();

if($topic->page < 2):
?>

<h3 id="OP">
	<span class="join_space help" title="This poster started the topic." onclick="highlightPoster(0);">+</span> 
	<span class="poster_number_0" id="join_0"><?php echo format_name($OP->namefag, $OP->tripfag, $OP->link, 0) ?></span> <?php if($OP->author == $_SESSION['UID']) echo mc('Topic: (you)') ?> — 
	<strong>
		<span class="help" title="<?php echo format_date($OP->time) ?>"><?php echo age($OP->time) ?> ago</span>  
		<span class="reply_id unimportant"><a href="<?php echo DIR ?>topic/<?php echo $topic->id ?>">#<?php echo number_format($topic->id) ?></a></span>
	</strong>
</h3>

<div class="body poster_body_0">
<?php
	if($OP->imgur):
?>
	<a href="http://i.imgur.com/<?php echo htmlspecialchars($OP->imgur) ?>.jpg" class="thickbox">
		<img src="http://i.imgur.com/<?php echo htmlspecialchars($OP->imgur) ?>m.jpg" alt="" class="help" title="Externally hosted image" />
	</a>
<?php
	elseif($OP->image_ignored):
?>
	<div class="unimportant hidden_image">(<strong><a href="<?php echo DIR . 'img/' . htmlspecialchars($OP->file_name) ?>"><?php echo htmlspecialchars($OP->original_name) ?></a></strong> hidden.)</div>
<?php
	elseif($OP->file_name):
?>
	<a href="<?php echo DIR ?>img/<?php echo htmlspecialchars($OP->file_name) ?>" class="thickbox">
		<img src="<?php echo DIR ?>thumbs/<?php echo htmlspecialchars($OP->file_name) ?>" alt=""<?php if(!empty($OP->original_name)) echo ' class="help" title="'.htmlspecialchars($OP->original_name).'"' ?> />
	</a>
<?php
	endif;
	
	echo parser::parse($OP->body, $OP->author);
	
	if($OP->edit_time):
?>
	<p class="unimportant">
	(Edited <?php echo age($OP->time, $OP->edit_time) ?> later<?php if($OP->edit_mod): ?> by <?php echo (empty($OP->edited_by) ? 'a moderator' : $perm->get_name($OP->edited_by) ); endif ?>.<?php if(!empty($OP->edit_reason)): ?> Reason: <?php echo htmlspecialchars($OP->edit_reason); endif ?>)</p>
	</p>
<?php
	endif;
	
	if($OP->image_deleted):
?>
	<p class="unimportant">
	(<strong><?php echo htmlspecialchars($OP->original_name) ?></strong> was deleted<?php if(!empty($OP->image_deleted_by)): ?> <?php echo age($OP->image_deleted_at, $OP->time) ?> later by <?php echo htmlspecialchars($perm->get_name($OP->image_deleted_by)); endif ?>.<?php if(!empty($OP->image_delete_reason)): ?> Reason: <?php echo htmlspecialchars($OP->image_delete_reason); endif ?>)
	</p>

<?php
	endif;
?>

	<ul class="menu">
<?php
	if($OP->image_ignored && ! $_SESSION['settings']['text_mode']):
?>
		<li><a href="<?php echo DIR ?>unhide_image/<?php echo $OP->md5 ?>" onclick="return quickAction(this, 'Really unhide all instances of this image?');">Unhide image</a></li>
<?php
	elseif($_SESSION['settings']['ostrich_mode'] && $OP->file_name):
?>	
		<li><a href="<?php echo DIR ?>hide_image/<?php echo $OP->md5 ?>" onclick="return quickAction(this, 'Really hide all instances of this image?');">Hide image</a></li>
<?php
	endif;
	if($OP->author == $_SESSION['UID'] && $perm->get('edit_limit') == 0 || $OP->author == $_SESSION['UID'] && ($_SERVER['REQUEST_TIME'] - $OP->time < $perm->get('edit_limit')) || $perm->get('edit_others')):
?>
		<li><a href="<?php echo DIR ?>edit_topic/<?php echo $topic->id ?>">Edit</a></li>
<?php
	endif;

	if( ! $perm->get('read_mod_pms')):
?>
		<li><a href="<?php echo DIR ?>report_topic/<?php echo $topic->id ?>">Report</a></li>
<?php
	endif;

	if($perm->get('merge') && ! $OP->deleted):
?>
		<li><a href="<?php echo DIR ?>merge/<?php echo $topic->id ?>">Merge</a></li>
<?php
	endif;
	
	if($perm->get('view_profile')):
?>
		<li><a href="<?php echo DIR ?>profile/<?php echo $OP->author ?>">Profile</a></li>
<?php
	endif;
	if($perm->get('stick') && ! $OP->sticky):
?>	
		<li><a href="<?php echo DIR ?>stick_topic/<?php echo $topic->id ?>" onclick="return quickAction(this, 'Stick this topic?')">Stick</a></li>
<?php
	elseif($perm->get('stick')):
?>
		<li><a href="<?php echo DIR ?>unstick_topic/<?php echo $topic->id ?>" onclick="return quickAction(this, 'Unstick this topic?');">Unstick</a></li>
<?php
	endif;
	if($perm->get('lock') && ! $topic->locked):
?>
		<li><a href="<?php echo DIR ?>lock_topic/<?php echo $topic->id ?>" onclick="return quickAction(this, 'Really lock this topic?');">Lock</a></li>
<?php
	elseif($perm->get('lock')):
?>
		<li><a href="<?php echo DIR ?>unlock_topic/<?php echo $topic->id ?>" onclick="return quickAction(this, 'Unlock this topic?');">Unlock</a></li>
<?php
	endif;
	if($perm->get('delete')):
		if( ! $OP->deleted):
?>
		<li><a href="<?php echo DIR ?>delete_topic/<?php echo $topic->id ?>" onclick="return quickAction(this, 'Really delete this topic?');">Delete</a></li>
<?php
		else:
?>
		<li><a href="<?php echo DIR ?>undelete_topic/<?php echo $topic->id ?>" onclick="return quickAction(this, 'Really undelete this topic?');">Undelete</a></li>
<?php
		endif;
	endif;
	
	if($OP->file_name && ( $perm->get('delete') || ($OP->author == $_SESSION['UID'] && ($perm->get('edit_limit') == 0 || ($_SERVER['REQUEST_TIME'] - $OP->time < $perm->get('edit_limit')))))):
?>
		<li><a href="<?php echo DIR ?>delete_image/<?php echo $topic->id ?>" onclick="return quickAction(this, 'Really delete this image?');">Delete image</a></li>
<?php	
	endif;
	
	if($OP->author !== $_SESSION['UID']):
?>
		<li><a href="<?php echo DIR ?>contact_OP/<?php echo $topic->id ?>">PM</a></li>
<?php
	endif;
	if( ! $watched):
?>
		<li><a href="<?php echo DIR ?>watch_topic/<?php echo $topic->id ?>" onclick="return quickAction(this, 'Really watch this topic?');">Watch</a></li>
<?php
	else:
?>
		<li><a href="<?php echo DIR ?>unwatch_topic/<?php echo $topic->id ?>" onclick="return quickAction(this, 'Really unwatch this topic?');">Unwatch</a></li>
<?php
	endif;
	if( ! $topic->locked || $perm->get('lock')):
?>
		<li><a href="<?php echo DIR ?>new_reply/<?php echo $topic->id ?>/quote_topic" onclick="return quickReply('OP', '<?php echo $topic->encode_quote($OP->body) ?>');">Quote</a></li>
<?php
	endif;
?>
		<li><a href="<?php echo DIR ?>trivia_for_topic/<?php echo $topic->id ?>" class="help" title="<?php echo $OP->replies . ' repl' . ($OP->replies == 1 ? 'y' : 'ies') ?>"><?php echo $OP->visits . ' visit' . ($OP->visits == 1 ? '' : 's') ?></a></li>
	</ul>
<?php
	if($OP->deleted):
?>
	<div class="deleted_post">This topic was deleted<?php if(!empty($OP->deleted_by)): ?> <span class="help" title="<?php echo format_date($OP->deleted_at) ?>"><?php echo age($OP->deleted_at) ?> ago</span> by <?php echo htmlspecialchars($perm->get_name($OP->deleted_by)); endif ?>. <?php if(!empty($OP->delete_reason)): ?>Reason: <?php echo htmlspecialchars($OP->delete_reason); endif ?></div>
<?php
	endif;
?>
</div>
<?php
endif;

/* Output poll. */
if( ! empty($topic->poll_options)) {
	
	echo '<form action="' . DIR . 'cast_vote/' . $topic->id . '" method="post" id="poll">';
	csrf_token();
	
	$columns = array
	(
		'Poll option',
		'Votes',
		'Percentage',
		'Graph'
	);
	
	if($topic->poll_hide && ! $topic->voted) {
		$columns = array_slice($columns, 0, 1);
	}
	
	$table = new Table($columns, 0);

	foreach($topic->poll_options as $option_id => $option) {
		$percent = (empty($topic->poll_votes) ? 0 : round(100 * $option['votes'] / $topic->poll_votes));
		
		$values = array
		(
			htmlspecialchars($option['text']),
			format_number($option['votes']),
			$percent . '%',
			'<div class="bar_container help" title=" ' . $option['votes'] . ' of ' . $topic->poll_votes . ' "><div class="bar" style="width: ' . $percent . '%;"></div></div>'
		);
		
		if($topic->poll_hide && ! $topic->voted) {
			$values = array_slice($values, 0, 1);
		}
		
		if( ! $topic->voted) {
			$values[0] = '<input name="option_id" class="inline" value="' . $option_id . '" id="option_' . $option_id . '" type="radio" /><label for="option_' . $option_id . '" class="inline">' . $values[0] . '</label>';
		}
		else if($topic->chosen_option == $option->id) {
			$values[0] = '<strong title="You voted for this." class="help">' . $values[0] . '</strong>';
		}
		
		$table->row($values);
	}
	
	$table->output('(This topic is marked as a poll, but there does not seem to be any options associated with it.)');
	if( ! $topic->voted) {
		echo '<div class="row"><input type="submit" name="cast_vote" value="Cast your vote" class="inline" />';
		if($OP->poll_hide) {
			echo '<input type="submit" name="show_results" value="Show results" class="inline" />';
		}
		echo '</div>';
	}
	echo '</form>';
}

while($reply = $topic->get_reply()) {
	if($reply === 'skip') {
		/* Deleted, ignored, or not on this page. See class.topic.php for an explanation of this temporary hack. */
		continue;
	}

	echo '<h3 name="reply_' . $reply->id . '" id="reply_' . $reply->id . '"' . ($reply->author == $topic->previous_author ? ' class="repeat_post"' : '') . '>';
	
	/* If this is the newest unread post, let the #new anchor highlight it. */
	if ($topic->reply_recount == $topic->last_read_post + 1) {
		echo '<span id="new"></span><input type="hidden" id="new_id" class="noscreen" value="' . $reply->id . '" />';
	}
	
	if( ! $reply->joined_in) {
		echo '<a href="'.DIR.'topic/' . $topic->id . page($topic->reply_count, $reply->first_post_number_by_author) . '#join_'.$reply->poster_number.'" class="join_space help" title="Click to jump to this person\'s first post." onclick="createSnapbackLink(' . $reply->id . '); highlightPoster('.$reply->poster_number.');">·</a>';
	} else {
		echo '<span class="join_space help" title="This poster just joined the thread." onclick="highlightPoster('.$reply->poster_number.');">+</span>';
	}
	
	echo ' <span class="poster_number_'.$topic->posters[$reply->author]['number'].'"' . ($reply->joined_in ? ' id="join_'.$topic->posters[$reply->author]['number'].'"' : '') . '>' . format_name($reply->namefag, $reply->tripfag, $reply->link, $topic->posters[$reply->author]['number']) . '</span> ';
	
	if($reply->author == $OP->author) {
		if($reply->author == $_SESSION['UID']) {
			echo mc('Topic: (OP, you)');
		} else {
			echo mc('Topic: (OP)');
		}
	} else if($reply->author == $_SESSION['UID']) {
		echo mc('Topic: (you)');
	}
	
	echo ' — <strong><span class="help" title="' . format_date($reply->time) . '">' . age($reply->time) . ' ago</span></strong><span class="unimportant timing_details">, ' . age($reply->time, $topic->previous_time) . ' later';
	if ($topic->reply_recount > 1) {
		echo ', ' . age($reply->time, $OP->time) . ' after the original post';
	}
	
	if($reply->original_parent && $reply->original_parent != $topic->id) {
		if( ! isset($topic->merges[$reply->original_parent])) {
			$topic->merges[$reply->original_parent] = $reply->id;
			$merge_tooltip = 'This was the original post of another topic merged into this one.';
		} else {
			$merge_tooltip = 'This was a reply to another topic merged into this one. Click me to jump to the original OP.';
		}
		echo ' <a href="'.DIR.'topic/' . (int) $_GET['id'] . page($topic->reply_count, $topic->history[$topic->merges[$reply->original_parent]]['post_number']) . '#reply_'.$topic->merges[$reply->original_parent].'" class="merge_marker help" title="'.$merge_tooltip.'" onclick="highlightReply('.$topic->merges[$reply->original_parent].')">[M]</a>';
	}
		
	echo '</span><span class="reply_id unimportant"><a href="#top">[^]</a> <a href="#bottom">[v]</a> <a href="#reply_' . $reply->id . '" onclick="highlightReply(\'' . $reply->id . '\'); removeSnapbackLink">#' . number_format($reply->id) . '</a></span></h3>',
	'<div class="body poster_body_'.$topic->posters[$reply->author]['number'].'" id="reply_box_' . $reply->id . '">';

	if($reply->imgur) {
		echo '<a href="http://i.imgur.com/' . htmlspecialchars($reply->imgur) . '.jpg" class="thickbox">',
		'<img src="http://i.imgur.com/' . htmlspecialchars($reply->imgur) . 'm.jpg" alt="" class="help" title="Externally hosted image" />',
		'</a>';
	}
	else if($reply->image_ignored) {
		echo '<div class="unimportant hidden_image">(<strong><a href="' . DIR . 'img/' . htmlspecialchars($reply->file_name) . '">' . htmlspecialchars($reply->original_name) . '</a></strong> hidden.)</div>';
	}
	else if($reply->file_name) {
		echo '<a href="'.DIR.'img/' . htmlspecialchars($reply->file_name) . '" class="thickbox"><img src="'.DIR.'thumbs/' . htmlspecialchars($reply->file_name) . '" alt=""';
		if( ! empty($reply->original_name)) {
			echo ' class="help" title="'.htmlspecialchars($reply->original_name).'"';
		}
		echo ' /></a>';
	}
	
	echo $reply->parsed_body;
	
	if($reply->edit_time) {
		echo '<p class="unimportant">(Edited ' . age($reply->time, $reply->edit_time) . ' later';
		if($reply->edit_mod) {
			echo ' by ' . (empty($reply->edited_by) ? 'a moderator' : $perm->get_name($reply->edited_by));
		}
		if( ! empty($reply->edit_reason)) {
			echo '. Reason: ' . htmlspecialchars($reply->edit_reason);
		}
		echo ')</p>';
	}
	
	if($reply->image_deleted) {
		echo '<p class="unimportant">(<strong>' . $reply->original_name . '</strong> was deleted';
		if( ! empty($reply->image_deleted_by)) {
			echo ' ' . age($reply->image_deleted_at, $reply->time) . ' later by ' . htmlspecialchars($perm->get_name($reply->image_deleted_by));
		}
		if( ! empty($reply->image_delete_reason)) {
			echo '. Reason: ' . htmlspecialchars($reply->image_delete_reason);
		}
		echo ')</p>';
	}
	
	echo '<ul class="menu">';
	
	if($reply->image_ignored && ! $_SESSION['settings']['text_mode']) {
		echo '<li><a href="'.DIR.'unhide_image/' . $reply->md5 . '" onclick="return quickAction(this, \'Really unhide all instances of this image?\');">Unhide image</a></li>';
	} else if($_SESSION['settings']['ostrich_mode'] && $reply->file_name) {
		echo '<li><a href="'.DIR.'hide_image/' . $reply->md5 . '" onclick="return quickAction(this, \'Really hide all instances of this image?\');">Hide image</a></li>'; 
	}
	if ($reply->author == $_SESSION['UID'] && $perm->get('edit_limit') == 0 || $reply->author == $_SESSION['UID'] && ($_SERVER['REQUEST_TIME'] - $reply->time < $perm->get('edit_limit')) || $perm->get('edit_others')) {
		echo '<li><a href="'.DIR.'edit_reply/' . $topic->id . '/' . $reply->id . '">Edit</a></li>';
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
		echo '<li><a href="'.DIR.'delete_image/' . $topic->id . '/' . $reply->id . '" onclick="return quickAction(this, \'Really delete this image?\');">Delete image</a></li>';
	}
	
	if($reply->author !== $_SESSION['UID']) {
		echo '<li><a href="'.DIR.'contact_poster/' . $reply->id . '">PM</a></li>';
	}
	if( ! $topic->locked || $perm->get('lock')) {
		echo '<li><a href="'.DIR.'new_reply/' . $topic->id . '/quote_reply/' . $reply->id . '" onclick="return quickReply('. $reply->id . ', \'' . $topic->encode_quote($reply->body) . '\');">Quote</a></li>',
		'<li><a href="'.DIR.'new_reply/' . $topic->id . '/cite_reply/' . $reply->id . '" onclick="return quickReply('.$reply->id.');">Cite</a></li></ul>';
	}
	
	if($reply->deleted) {
		echo '<div class="deleted_post">This reply was deleted ';
		if( ! empty($reply->deleted_by)) {
			echo '<span class="help" title="' . format_date($reply->deleted_at) . '">' . age($reply->deleted_at) . ' ago</span> by ' . htmlspecialchars($perm->get_name($reply->deleted_by));
		}
		if( ! empty($reply->delete_reason)) {
			echo '. Reason: ' . htmlspecialchars($reply->delete_reason);
		}
		echo '</div>';
	}
	
	echo '</div>' . "\n\n";
	
	/* Store information for the next round. */
	$topic->previous_id = $reply->id;
	$topic->previous_author = $reply->author;
	if( ! $reply->deleted) {
		$topic->previous_time = $reply->time;
	}
}

$topic->print_pages();

if( (! $topic->locked || $perm->get('lock')) && ! $OP->deleted):
?>
	<ul class="menu">
		<li><a href="<?php echo DIR ?>new_reply/<?php echo $topic->id ?>" onclick="$('#quick_reply').toggle();$('#qr_text').get(0).scrollIntoView(true);$('#qr_text').focus(); return false;">Reply</a>
		<?php if($topic->locked) echo '<small>(locked)</small>' ?></li>
	</ul>
<?php
elseif($OP->deleted):
?>
	<ul class="menu"><li>Topic deleted</li></ul>
<?php
else:
?>
	<ul class="menu"><li>Topic locked</li></ul>
<?php
endif;
?>
<div id="quick_reply" class="noscreen">
	<form enctype="multipart/form-data" action="<?php echo DIR; ?>new_reply/<?php echo $topic->id; ?>" method="post">
		
		<?php csrf_token(); ?>
		<input name="form_sent" type="hidden" value="1" />
		<input name="e-mail" type="hidden" />
		<input name="start_time" type="hidden" value="<?php echo time(); ?>" />
		<input name="image" type="hidden" value="" />
<?php 
if( ! FORCED_ANON || $perm->get('link')): 
?>
		<div class="row"><label for="name">Name</label>:
			<input id="name" name="name" type="text" size="30" maxlength="30" tabindex="2" value="<?php echo htmlspecialchars($_SESSION['poster_name']); ?>" class="inline"> 
<?php 
	if($_SESSION['UID'] == $OP->author) { 
		echo ' (OP)';
	} else if(isset($topic->your_name)) {
		echo ' ('.htmlspecialchars($topic->your_name).')';
	}
endif;
	
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