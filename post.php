<?php
require './includes/bootstrap.php';
force_id();

/* Check DEFCON */
if( ! $perm->is_admin() && ! $perm->is_mod()) {
	if(DEFCON < 3) {
		error::fatal(m('DEFCON 2'));
	}
	if(DEFCON < 4 && $_SESSION['post_count'] < POSTS_TO_DEFY_DEFCON_3) {
		error::fatal(m('DEFCON 3'));
	}
}

$topic_id = (empty($_GET['reply']) ? 0 : (int) $_GET['reply']);

if ($topic_id) {
	/* This is a reply. */
	
	if( ! $perm->get('post_reply')) {
		error::fatal('You do not have permission to reply.');
	}
		
	$res = $db->q('SELECT headline, author, replies, deleted, locked, last_post FROM topics WHERE id = ?', $topic_id);
	$topic = $res->fetchObject();
	
	if( ! $topic) {
		$template->title = 'Non-existent topic';
		error::fatal('There is no such topic. It may have been deleted.');
	}
		
	if($topic->deleted) {
		error::fatal('You cannot respond to a deleted topic.');
	}
	
	if(AUTOLOCK && ($_SERVER['REQUEST_TIME'] - $topic->last_post) > AUTOLOCK && $topic->author != $_SESSION['UID']) {
		$topic->locked = true;
	}
	
	update_activity('replying', $topic_id);
	$reply = true;
	$template->onload = "focusId('body');";
	$template->title = 'New reply in topic: <a href="'.DIR.'topic/' . $topic_id . '">' . htmlspecialchars($topic->headline) . '</a>';
	
	$check_watchlist = $db->q('SELECT 1 FROM watchlists WHERE uid = ? AND topic_id = ?', $_SESSION['UID'], $topic_id);
	if ($check_watchlist->fetchColumn()) {
		$watching_topic = true;
	}
	
} else {
	/* This is a topic. */
	
	if( ! $perm->get('post_topic')) {
		error::fatal('You do not have permission to create a topic.');
	}
	
	update_activity('new_topic');
	$reply = false;
	$template->onload = "focusId('headline')";
	$template->head = '<script type="text/javascript" src="' . DIR . 'javascript/polls.js"></script>';
	$template->title = 'New topic';
	
	if ( ! empty($_POST['headline'])) {
		$template->title .= ': ' . htmlspecialchars($_POST['headline']);
	}
	
}

$edit_id = (empty($_GET['edit']) ? false : (int) $_GET['edit']);

if ($edit_id) {
	/* We're editing a post. */
	$editing = true;
	
	if( ! $perm->get('edit')) {
		error::fatal('You do not have permission to edit posts.');
	}
	
	if ($reply) {
		$fetch_edit = $db->q('SELECT author, time, body, edit_mod AS `mod` FROM replies WHERE id = ?', $edit_id);
		$template->title = 'Editing <a href="'.DIR.'topic/' . $topic_id . '#reply_' . $edit_id . '">reply</a> to topic: <a href="'.DIR.'topic/' . $topic_id . '">' . htmlspecialchars($topic->headline) . '</a>';
	} else {
		$fetch_edit = $db->q('SELECT author, time, body, edit_mod AS `mod`, headline FROM topics WHERE id = ?', $edit_id);
		$template->title = 'Editing topic';
	}
	
	$edit_data = $fetch_edit->fetchObject();
	
	if ( ! $edit_data) {
		error::fatal('There is no such post. It may have been deleted.');
	}
		
	if ($edit_data->author === $_SESSION['UID']) {
		$edit_mod = 0;
		
		if ($perm->get('edit_limit') != 0 && ($_SERVER['REQUEST_TIME'] - $edit_data->time > $perm->get('edit_limit'))) {
			error::fatal('You can no longer edit your post.');
		}
		if ($edit_data->mod) {
			error::fatal('You can not edit a post that has been edited by a moderator.');
		}
	} else if ($perm->get('edit_others')) {
		$edit_mod = 1;
	} else {
		error::fatal('You are not allowed to edit that post.');
	}
	
	/* Fill in the form. */
	if ( ! $_POST['form_sent']) {
		$body = $edit_data->body;
		
		if ( ! $reply) {
			$headline = $edit_data->headline;
			$template->title .= ': <a href="'.DIR.'topic/' . $edit_id . '">' . htmlspecialchars($edit_data->headline) . '</a>';
		}
	} else if ( ! empty($_POST['headline'])) {
		$template->title .= ':  <a href="'.DIR.'topic/' . $edit_id . '">' . htmlspecialchars($_POST['headline']) . '</a>';
	}
}

if (isset($_POST['form_sent'])) {

	$headline = super_trim($_POST['headline']);
	$body     = super_trim($_POST['body']);
	$name     = (isset($_POST['name']) && ( ! FORCED_ANON || $perm->get('link'))  ? super_trim($_POST['name']) : '');
	$trip     = '';
	if ( ! empty($name)) {
		list($name, $trip) = tripcode($name);
	}

	/* Parse for mass quote tag ([quote]). */
	$body = preg_replace_callback
	(
		'/\[quote\](.+?)\[\/quote\]/s', 
		create_function('$matches', 'return preg_replace(\'/.*[^\s]$/m\', \'> $0\', $matches[1]);'), $body
	);
	
	$user_link = $perm->get('link');
	if(isset($_POST['post_as_group'])) {
		$_SESSION['show_group'] = true;
	} else {
		unset($_SESSION['show_group']);
		$user_link = '';
	}
	
	if ($_POST['post']) {
		/* Check for poorly made bots. */
		if ( ! $editing && $_SERVER['REQUEST_TIME'] - $_POST['start_time'] < 3) {
			error::add('Wait a few seconds between starting to compose a post and actually submitting it.');
		}
		
		if ( ! empty($_POST['e-mail'])) {
			error::add('Bot detected.');
		}
		
		if(strpos($body, 'http') !== false) {
			if( ! $perm->get('post_link')) {
				error::add('You do not have permission to post links.');
			} else if( ! $_SESSION['post_count'] && RECAPTCHA_ENABLE) {
				show_captcha('Your first post includes a link. To prove that you\'re not a spambot, complete the following CAPTCHA.');
			}
		}
		
		check_token();
		
		$min_body = MIN_LENGTH_BODY;
		if( ! empty($_FILES['image']['name']) || ! empty($_POST['imgur']) || $editing) {
			$min_body = 0;
		}
		
		check_length($body, 'body', $min_body, MAX_LENGTH_BODY);
		check_length($name, 'name', 0, 30);
		
		if( ! $reply) {
			check_length($headline, 'headline', MIN_LENGTH_HEADLINE, MAX_LENGTH_HEADLINE);
		}
		
		if (count(explode("\n", $body)) > MAX_LINES) {
			error::add('Your post has too many lines.');
		}
		
		if(ALLOW_IMAGES && $perm->get('post_image') && ! empty($_FILES['image']['name'])) {
			try {
				$image = new Upload($_FILES['image']);
			} catch (Exception $e) {
				error::add($e->getMessage());
			}
		}
				
		$imgur = '';
		if( ! isset($image) && ! empty($_POST['imgur'])) {
			$_POST['imgur'] = trim($_POST['imgur']);
			if( ! preg_match('/imgur\.com\/([a-zA-Z0-9]{3,10})/', $_POST['imgur'], $matches)) {
				error::add('That does not appear to be a valid imgur URL.');
			} else {
				$imgur = $matches[1];
			}
		}
		
		
		if($editing && error::valid()) {
			
			if($reply) {
				/* Editing a reply. */
				
				$db->q
				(
					'UPDATE replies 
					SET body = ?, edit_mod = ?, edit_time = ? 
					WHERE id = ?', 
					$body, $edit_mod, $_SERVER['REQUEST_TIME'], 
					$edit_id
				);
										
				$congratulation = m('Notice: Reply edited');
				
			} else {
				/* Editing a topic. */
				
				$db->q
				(
					'UPDATE topics 
					SET headline = ?, body = ?, edit_mod = ?, edit_time = ? 
					WHERE id = ?',
					$headline, $body, $edit_mod, $_SERVER['REQUEST_TIME'], 
					$edit_id
				);
					
				$congratulation = m('Notice: Topic edited');
			}
			
			if($edit_mod) {
				/* Log the changes. */
				$type = ($reply ? 'reply' : 'topic');
				
				$db->q
				(
					'INSERT INTO revisions 
					(type, foreign_key, text) VALUES 
					(?, ?, ?)', 
					$type, $edit_id, $edit_data->body
				);
					
				log_mod('edit_' . $type, $edit_id, $db->lastInsertId());
			}
		} 
		else if ($reply) {
			/* Posting a reply. */

			if($topic->locked != 0 && ! $perm->get('lock')) {
				error::add('You cannot reply to a locked thread.');
			}
			
			/* Lurk more. */
			if ($_SERVER['REQUEST_TIME'] - $_SESSION['first_seen'] < REQUIRED_LURK_TIME_REPLY) {
				error::add('Lurk for at least ' . REQUIRED_LURK_TIME_REPLY . ' seconds before posting your first reply.');
			}
			
			/* Flood control. */
			$too_early = $_SERVER['REQUEST_TIME'] - FLOOD_CONTROL_REPLY;
			$res = $db->q('SELECT 1 FROM replies WHERE author_ip = ? AND time > ?', $_SERVER['REMOTE_ADDR'], $too_early);

			if ($res->fetchColumn()) {
				error::add('Wait at least ' . FLOOD_CONTROL_REPLY . ' seconds between each reply. ');
			}
				
			if(error::valid()) {
				$db->q
				(
					'INSERT INTO replies 
					(author, author_ip, parent_id, body, namefag, tripfag, link, time, imgur) VALUES 
					(?, ?, ?, ?, ?, ?, ?, ?, ?)',
					$_SESSION['UID'], $_SERVER['REMOTE_ADDR'], $topic_id, $body, $name, $trip, $user_link, $_SERVER['REQUEST_TIME'], $imgur
				);
				$inserted_id = $db->lastInsertId();
			
				/* Notify cited posters. */					
				preg_match_all('/@([0-9,]+)/m', $body, $matches);
				/* Needs to filter before array_unique in case of @11, @1,1 etc. */
				$citations = filter_var_array($matches[0], FILTER_SANITIZE_NUMBER_INT);
				$citations = array_unique($citations);
				$citations = array_slice($citations, 0, 9);
					
				foreach ($citations as $citation) {
					/* Note that nothing is inserted unless the SELECT returns a row. */
					$db->q
					(
						'INSERT INTO citations 
						(reply, topic, uid) 
						SELECT ?, ?, `author` FROM replies WHERE replies.id = ? AND replies.parent_id = ?',
						$inserted_id, $topic_id, (int) $citation, $topic_id
					);
				}
				if(strpos($body, '@OP') !== false) {
					$db->q('INSERT INTO citations (reply, topic, uid) VALUES (?, ?, ?)', $inserted_id, $topic_id, $topic->author);
				}
					
				/* Update watchlists. */
				$db->q('UPDATE watchlists SET new_replies = 1 WHERE topic_id = ? AND uid != ?', $topic_id, $_SESSION['UID']);
				
				$congratulation = m('Notice: Reply posted');
			}
		} else {
			/* Posting a topic. */
				
			/* Do we need to lurk some more? */
			if ($_SERVER['REQUEST_TIME'] - $_SESSION['first_seen'] < REQUIRED_LURK_TIME_TOPIC) {
				error::add('Lurk for at least ' . REQUIRED_LURK_TIME_TOPIC . ' seconds before posting your first topic.');
			}
			
			/* Flood control. */
			$too_early = $_SERVER['REQUEST_TIME'] - FLOOD_CONTROL_TOPIC;
			$res = $db->q('SELECT 1 FROM topics WHERE author_ip = ? AND time > ?', $_SERVER['REMOTE_ADDR'], $too_early);
			
			if ($res->fetchColumn()) {
				error::add('Wait at least ' . FLOOD_CONTROL_TOPIC . ' seconds before creating another topic. ');
			}
			
			/* Is this a valid poll? */
			$poll = 0;
			if($_POST['enable_poll'] && isset($_POST['option'][0])) {
				if(count($_POST['option']) > 10) {
					$_POST['option'] = array_slice($_POST['option'], 0, 9);
				}
				
				foreach($_POST['option'] as $id => $text) {
					if($text === '') {
						unset($_POST['option'][$id]);
					}
					else if(strlen($text) > 80) {
						error::add('Poll option ' . ($id + 1) . ' exceeded 80 characters.');
					}
				}
				
				if(count($_POST['option']) > 1) {
					$poll = 1;
				}
			}
			
			$hide_results = (isset($_POST['hide_results']) ? 1 : 0);
			$sticky       = (isset($_POST['sticky']) && $perm->get('stick') ? 1 : 0);
			$locked       = (isset($_POST['locked']) && $perm->get('lock') ? 1 : 0);
			
			if(error::valid()) {
				$db->q
				(
					'INSERT INTO topics 
					(author, author_ip, headline, body, last_post, time, namefag, tripfag, link, sticky, locked, poll, poll_hide, imgur) VALUES 
					(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', 
					$_SESSION['UID'], $_SERVER['REMOTE_ADDR'], $headline, $body, $_SERVER['REQUEST_TIME'], $_SERVER['REQUEST_TIME'], $name, $trip, $user_link, $sticky, $locked, $poll, $hide_results, $imgur
				);
				$inserted_id = $db->lastInsertId();
				
				if($poll) {
					foreach($_POST['option'] as $option) {
						$db->q('INSERT INTO poll_options (`parent_id`, `option`) VALUES (?, ?)', $inserted_id, $option);
					}
				}
				
				$congratulation = m('Notice: Topic created');
			}
		}
		
		
		if (error::valid()) {
			/* We successfully submitted or edited the post. */
			
			if ( ! $editing) {
				$raw_name = (isset($_POST['name']) ? super_trim($_POST['name']) : '');
				$db->q('UPDATE users SET post_count = post_count + 1, namefag = ? WHERE uid = ?', $raw_name, $_SESSION['UID']);
				$_SESSION['post_count']++;
				$_SESSION['poster_name'] = $raw_name;

				setcookie('last_bump', $_SERVER['REQUEST_TIME'], $_SERVER['REQUEST_TIME'] + 315569260, '/');
				
				if ($reply) {
					$db->q("UPDATE last_actions SET time = ? WHERE feature = 'last_bump'", $_SERVER['REQUEST_TIME']);
					$db->q('UPDATE topics SET replies = replies + 1, last_post = ? WHERE id = ?', $_SERVER['REQUEST_TIME'], $topic_id);
					
					$target_topic = $edit_id;
					$redir_loc    = $topic_id . ($_SESSION['settings']['posts_per_page'] ? '/reply/' : '#reply_') . $inserted_id;
				} else { # If topic.
					setcookie('last_topic', $_SERVER['REQUEST_TIME'], $_SERVER['REQUEST_TIME'] + 315569260, '/');
					$db->q("UPDATE last_actions SET time = ? WHERE feature = 'last_topic' OR feature = 'last_bump'", $_SERVER['REQUEST_TIME']);
					
					$target_topic = $inserted_id;
					$redir_loc    = $inserted_id;
				}
			} else { # If editing.
				if ($reply) {
					$target_topic = $topic_id;
					$redir_loc    = $topic_id . ($_SESSION['settings']['posts_per_page'] ? '/reply/' : '#reply_') . $edit_id;
				} else { # If topic.
					$target_topic = $edit_id;
					$redir_loc    = $edit_id;
				}
			}
			
			// Take care of the upload.
			if(isset($image) && $image->success) {
				$post_type = ($reply ? 'reply' : 'topic');
				
				if($editing) {
					delete_image($post_type, $edit_id, true);
					$image_target = $edit_id;
				} else {
					$image_target = $inserted_id;
				}
				
				$image->move($post_type, $image_target);
			}
			
			/* Add topic to watchlist if desired. */
			if (isset($_POST['watch_topic']) && ! $watching_topic) {
				$db->q('INSERT INTO watchlists (uid, topic_id) VALUES (?, ?)', $_SESSION['UID'], $target_topic);
			}
			
			/* Set the congratulation notice and redirect to the affected post. */
			redirect($congratulation, 'topic/' . $redir_loc);
			
		} else {
			/* If an error occured, insert this into failed postings. */
			if ($reply) {
				$db->q('INSERT INTO failed_postings (time, uid, reason, body) VALUES (?, ?, ?, ?)', $_SERVER['REQUEST_TIME'], $_SESSION['UID'], serialize(error::$errors), substr($body, 0, MAX_LENGTH_BODY));
			} else {
				$db->q('INSERT INTO failed_postings (time, uid, reason, body, headline) VALUES (?, ?, ?, ?, ?)', $_SERVER['REQUEST_TIME'], $_SESSION['UID'], serialize(error::$errors), substr($body, 0, MAX_LENGTH_BODY), substr($headline, 0, MAX_LENGTH_HEADLINE));
			}
		}
	}
}

error::output();

/* For the bot check. */
$start_time = $_SERVER['REQUEST_TIME'];
if (ctype_digit($_POST['start_time'])) {
	$start_time = $_POST['start_time'];
}

/* Get name and tripcode. */
if($_POST['form_sent']) {
	$set_name = $_POST['name'];
} else {
	$set_name = $_SESSION['poster_name'];
}

/* Get cited or original post and prepare body. */
if($reply) {
	if( ! isset($_GET['cite']) && ! isset($_GET['quote_reply'])) {
		$cited_reply = false;
	} else {
		$cited_reply = (isset($_GET['cite']) ? (int) $_GET['cite'] : (int) $_GET['quote_reply']);
	}

	if ($cited_reply) {
		$new_body = '@' . number_format($cited_reply) . "\n\n";
		$res = $db->q('SELECT body, namefag, tripfag FROM replies WHERE id = ? AND deleted = 0', $cited_reply);
	} else {
		$res = $db->q('SELECT body, namefag, tripfag FROM topics WHERE id = ? AND deleted = 0', $topic_id);
	}
	
	list($cited_text, $cited_name, $cited_trip) = $res->fetch();
	
	if(isset($_GET['quote_topic']) || isset($_GET['quote_reply'])) {
		/* Snip citations from quote. */
		$quoted_text = trim(preg_replace('/^@([0-9,]+|OP)/m', '', $cited_text));

		/* Prefix newlines with > */
		$quoted_text = preg_replace('/^/m', '> ', $cited_text);
		$new_body .= $quoted_text . "\n\n";
	}
	
	/* $body may already be set from previewing or error */
	if( ! isset($body)) {
		$body = $new_body;
	}
	
	$cited_text = parser::parse($cited_text);
	$cited_text = preg_replace('/^@([0-9]+|OP),?([0-9]+)?/m', '<span class="unimportant"><a href="'.DIR.'topic/'. (int) $topic_id.'#reply_$1$2">$0</a></span>', $cited_text);
}

echo '<div>';

/* Check if OP. */
if ($reply && ! $editing) {
	echo '<p>You <strong>are';
	if ($_SESSION['UID'] !== $topic->author) {
		echo ' not';
	}
	echo '</strong> recognized as the original poster of this topic.</p>';
}

/* Print deadline for edit submission. */
if ($editing && $perm->get('edit_limit') != 0) {
	echo '<p>You have <strong>' . age($_SERVER['REQUEST_TIME'], $edit_data->time + $perm->get('edit_limit')) . '</strong> left to finish editing this post.</p>';
}

/* Print preview. */
if ($_POST['preview'] && ! empty($body)) {
	$preview_body = parser::parse($body, $_SESSION['UID']);
	$preview_body = preg_replace('/^@([0-9]+|OP),?([0-9]+)?/m', '<span class="unimportant"><a href="'.DIR.'topic/'.(int)$topic_id.'#reply_$1$2">$0</a></span>', $preview_body);
	echo '<h3 id="preview">Preview</h3><div class="body standalone">' . $preview_body . '</div>';
}

/* Check if any new replies have been posted since we last viewed the topic. */
if ($reply && isset($_SESSION['topic_visits'][$topic_id]) && $_SESSION['topic_visits'][$topic_id] < $topic->replies) {
	$new_replies = $topic->replies - $_SESSION['topic_visits'][$topic_id];
	echo '<p><a href="'.DIR.'topic/' . $topic_id . '#new"><strong>' . $new_replies . '</strong> new repl' . ($new_replies == 1 ? 'y</a> has' : 'ies</a> have') . ' been posted in this topic since you last checked!</p>';
}

?>
<form action="" method="post"<?php if(ALLOW_IMAGES) echo ' enctype="multipart/form-data"' ?>>
	<?php csrf_token() ?>
	<div class="noscreen">
		<input name="form_sent" type="hidden" value="1" />
		<input name="e-mail" type="hidden" />
		<input name="start_time" type="hidden" value="<?php echo $start_time ?>" />
	</div>
	<?php if( ! $reply): ?>
	<div class="row">
		<label for="headline">Headline</label> <script type="text/javascript"> printCharactersRemaining('headline_remaining_characters', 100); </script>.
		<input id="headline" name="headline" tabindex="1" type="text" size="124" maxlength="100" onkeydown="updateCharactersRemaining('headline', 'headline_remaining_characters', 100);" onkeyup="updateCharactersRemaining('headline', 'headline_remaining_characters', 100);" value="<?php if($_POST['form_sent'] || $editing) echo htmlspecialchars($headline) ?>">
	</div>
	<?php endif; ?>
	<?php if( ! $editing && ( ! FORCED_ANON || $perm->get('link'))): ?>
			<div class="row"><label for="name">Name</label>: <input id="name" name="name" type="text" size="30" maxlength="30" tabindex="2" value="<?php echo htmlspecialchars($set_name) ?>" class="inline">
		<?php if($perm->get('link')): ?>
			<input type="checkbox" name="post_as_group" id="post_as_group" value="1" class="inline" <?php if(isset($_SESSION['show_group'])) echo ' checked="checked"' ?> />
			<label for="post_as_group" class="inline"> Post as <?php echo htmlspecialchars($perm->get('name')) ?></label>
		<?php endif; ?>
	<?php endif; ?>
	<div class="row">
		<label for="body" class="noscreen">Post body</label> 
		<textarea name="body" cols="120" rows="18" tabindex="2" id="body"><?php if(isset($body)) echo htmlspecialchars($body) ?></textarea>
	<?php if (ALLOW_IMAGES && $perm->get('post_image')): ?>
		<label for="image" class="noscreen">Image</label> <input type="file" name="image" id="image" />
	<?php endif; ?>
	

	<?php if(IMGUR_KEY && ! $editing): ?>
		<div>
			<?php if(ALLOW_IMAGES) echo 'Or use an' ?> imgur URL: 
			<input type="text" name="imgur" id="imgur" class="inline" size="21" placeholder="http://i.imgur.com/wDizy.gif" />
			<a href="http://imgur.com/" id="imgur_status" onclick="$('#imgur_file').click(); return false;">[upload]</a>
			<input type="file" id="imgur_file" class="noscreen" onchange="imgurUpload(this.files[0], '<?php echo IMGUR_KEY ?>')" />
		</div>
	<?php endif; ?>
		<p><?php echo m('Post: Help') ?></p>
	</div>
	<?php if ( ! $watching_topic): ?>
		<div class="row">
			<input type="checkbox" name="watch_topic" id="watch_topic" class="inline"<?php if(isset($_POST['watch_topic'])) echo ' checked="checked"' ?> />
			<label for="watch_topic" class="inline"> Watch</label>
		</div>
	<?php endif; ?>
	<?php if( ! $reply && ! $editing): ?>
		<?php if($perm->get('stick')): ?>
			<div>
				<input type="checkbox" name="sticky" value="1" class="inline"/>
				<label for="sticky" class="inline"> Stick</label>
			</div>
		<?php endif; ?>
		<?php if($perm->get('lock')): ?>
			<div class="row">
				<input type="checkbox" name="locked" value="1" class="inline"/>
				<label for="locked" class="inline"> Lock</label>
			</div>
		<?php endif;?>
	<?php endif; ?>

<?php
if( ! $reply && ! $editing):
?>
		
	<input type="hidden" id="enable_poll" name="enable_poll" value="1" />
	<ul class="menu"><li><a id="poll_toggle" onclick="showPoll(this);">Poll options</a></li></ul>
		
	<table id="topic_poll">
		<tr class="odd">
			<th colspan="2"><input type="checkbox" name="hide_results" id="hide_results" value="1" class="inline"<?php if($_POST['hide_results']) echo ' checked="checked"' ?>/><label for="hide_results" class="inline help" title="If checked, the results of the poll will be hidden until a user either votes or chooses to 'show results'."> Hide results before voting</label></td>
		</tr>
<?php
	/* Print at least two, or as many as were submitted (in case of preview/error) */
	for($i = 1, $s = count($_POST['option']); ($i <= 2 || $i <= $s); ++$i):
?>
	<tr>
		<td class="minimal">
			<label for="poll_option_<?php echo $i ?>">Poll option #<?php echo $i ?></label>
		</td>
		<td>
			<input type="text" size="50" maxlength="80" id="poll_option_<?php echo $i ?>" name="option[]" value="<?php if(isset($_POST['form_sent'])) echo htmlspecialchars($_POST['option'][$i - 1]) ?>" class="poll_input" />
		</td>
	</tr>
<?php
	endfor;
endif; 
?>
	</table>
		
	<div class="row">
	<input type="submit" name="preview" tabindex="3" value="Preview" class="inline"<?php if(ALLOW_IMAGES) echo ' onclick="document.getElementById(\'image\').value=\'\'"' ?> /> 
		<input type="submit" name="post" tabindex="4" value="<?php echo ($editing) ? 'Update' : 'Post' ?>" class="inline">
	</div>
</form>
</div>

<?php if( ! empty($cited_text)): ?>
	<h3 id="replying_to">Replying to <?php echo format_name($cited_name, $cited_trip) ?>&hellip;</h3> 
	<div class="body standalone"><?php echo $cited_text ?></div>
<?php endif; ?>

<?php
$template->render();
?>