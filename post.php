<?php
require './includes/bootstrap.php';
force_id();

/* Check DEFCON */
if( ! $perm->is_admin() && ! $perm->is_mod()) {
	if(DEFCON < 3) { // DEFCON 2.
		error::fatal(m('DEFCON 2'));
	}
	if(DEFCON < 4 && $_SESSION['post_count'] < POSTS_TO_DEFY_DEFCON_3) { // DEFCON 3.
		error::fatal(m('DEFCON 3'));
	}
}

/* Is this a reply or a new thread? */
if ($_GET['reply']) {

	$reply = true;
	$template->onload = 'focusId(\'body\');';
	
	if( ! $perm->get('post_reply')) {
		error::fatal('You do not have permission to reply.');
	}
	
	if ( ! ctype_digit($_GET['reply'])) {
		error::fatal('Invalid topic ID.');
	}
	
	$res = $db->q('SELECT headline, author, replies, deleted, locked, last_post FROM topics WHERE id = ?', $_GET['reply']);
	if ($db->num_rows() < 1) {
		$template->title = 'Non-existent topic';
		error::fatal('There is no such topic. It may have been deleted.');
	}
	
	list($replying_to, $topic_author, $topic_replies, $topic_deleted, $locked, $last_bump) = $res->fetch();
	
	if($topic_deleted) {
		error::fatal('You cannot respond to a deleted topic.');
	}
	
	if(AUTOLOCK && ($_SERVER['REQUEST_TIME'] - $last_bump) > AUTOLOCK && $topic_author != $_SESSION['UID']) {
		$locked = true;
	}
	
	update_activity('replying', $_GET['reply']);
	$template->title = 'New reply in topic: <a href="'.DIR.'topic/' . $_GET['reply'] . '">' . htmlspecialchars($replying_to) . '</a>';
	
	$check_watchlist = $db->q('SELECT 1 FROM watchlists WHERE uid = ? AND topic_id = ?', $_SESSION['UID'], $_GET['reply']);
	if ($check_watchlist->fetchColumn()) {
		$watching_topic = true;
	}
} else { // This is a topic.
	if( ! $perm->get('post_topic')) {
		error::fatal('You do not have permission to create a topic.');
	}
	
	$reply             = false;
	$template->onload = 'focusId(\'headline\');';
	$template->head   = '<script type="text/javascript" src="' . DIR . 'javascript/polls.js"></script>';
	update_activity('new_topic');
	
	$template->title = 'New topic';
	
	if (!empty($_POST['headline'])) {
		$template->title .= ': ' . htmlspecialchars($_POST['headline']);
	}
}

// If we're trying to edit and it's not disabled in the configuration.
if ($perm->get('edit') && ctype_digit($_GET['edit'])) {
	$editing = true;
	
	if ($reply) {
		$fetch_edit = $db->q('SELECT author, time, body, edit_mod FROM replies WHERE id = ?', $_GET['edit']);
	} else {
		$fetch_edit = $db->q('SELECT author, time, body, edit_mod, headline FROM topics WHERE id = ?', $_GET['edit']);
	}
	
	if ($db->num_rows() < 1) {
		error::fatal('There is no such post. It may have been deleted.');
	}
	if ($reply) {
		list($edit_data['author'], $edit_data['time'], $edit_data['body'], $edit_data['mod']) = $fetch_edit->fetch();
		$template->title = 'Editing <a href="'.DIR.'topic/' . $_GET['reply'] . '#reply_' . $_GET['edit'] . '">reply</a> to topic: <a href="'.DIR.'topic/' . $_GET['reply'] . '">' . htmlspecialchars($replying_to) . '</a>';
	} else {
		list($edit_data['author'], $edit_data['time'], $edit_data['body'], $edit_data['mod'], $edit_data['headline']) = $fetch_edit->fetch();
		$template->title = 'Editing topic';
	}
	
	if($perm->is_admin($edit_data['author']) && ! $perm->is_admin()) {
		error::fatal(m('Error: Access denied'));
	}
	
	if ($edit_data['author'] === $_SESSION['UID']) {
		$edit_mod = 0;
		
		if ($perm->get('edit_limit') != 0 && ($_SERVER['REQUEST_TIME'] - $edit_data['time'] > $perm->get('edit_limit'))) {
			error::fatal('You can no longer edit your post.');
			}
		if ($edit_data['mod']) {
			error::add('You can not edit a post that has been edited by a moderator.');
		}
	} else if ($perm->get('edit_others')) {
		$edit_mod = 1;
	} else {
		error::fatal('You are not allowed to edit that post.');
	}
	
	if (!$_POST['form_sent']) {
	// CSRF checking; No checking needed for the first edit form, needs to be checked on submit, below.
		$body = $edit_data['body'];
		if (!$reply) {
			$template->title .= ': <a href="'.DIR.'topic/' . $_GET['edit'] . '">' . htmlspecialchars($edit_data['headline']) . '</a>';
			$headline = $edit_data['headline'];
		}
	} else if (!empty($_POST['headline'])) {
		$template->title .= ':  <a href="'.DIR.'topic/' . $_GET['edit'] . '">' . htmlspecialchars($_POST['headline']) . '</a>';
	}
}

if ($_POST['form_sent']) {
	// Trimming.
	$headline = super_trim($_POST['headline']);
	$body     = super_trim($_POST['body']);
	$namefag  = (isset($_POST['name']) ? super_trim($_POST['name']) : '');
	
	// Parse for mass quote tag ([quote]). I'm not sure about create_function, it seems kind of slow.
	$body = preg_replace_callback('/\[quote\](.+?)\[\/quote\]/s', create_function('$matches', 'return preg_replace(\'/.*[^\s]$/m\', \'> $0\', $matches[1]);'), $body);
	
	$user_link = $perm->get('link');
	if(isset($_POST['post_as_group'])) {
		$_SESSION['show_group'] = true;
	} else {
		unset($_SESSION['show_group']);
		$user_link = '';
	}
	
	if(strpos($body, 'http') !== false) {
		if( ! $perm->get('post_link')) {
			error::add('You do not have permission to post links.');
		} else if( ! $_SESSION['post_count'] && RECAPTCHA_ENABLE) {
			show_captcha('Your first post includes a link. To prove that you\'re not a spambot, complete the following CAPTCHA.');
		}
	}
	
	if ($_POST['post']) {
		// Check for poorly made bots.
		if ( ! $editing && $_SERVER['REQUEST_TIME'] - $_POST['start_time'] < 3) {
			error::add('Wait a few seconds between starting to compose a post and actually submitting it.');
		}
		if ( ! empty($_POST['e-mail'])) {
			error::add('Bot detected.');
		}
		check_token();
		
		if(empty($_FILES['image']['name']) && empty($_POST['imgur'])) {
			check_length($body, 'body', MIN_LENGTH_BODY, MAX_LENGTH_BODY);
		}
		
		check_length($namefag, 'name', 0, 30);
		if (count(explode("\n", $body)) > MAX_LINES) {
			error::add('Your post has too many lines.');
		}
		
		$uploading = false;
		if (ALLOW_IMAGES && ! empty($_FILES['image']['name'])) {
			$image_data = array();
			
			switch ($_FILES['image']['error']) {
				case UPLOAD_ERR_OK:
					$uploading = true;
					break;
				
				case UPLOAD_ERR_PARTIAL:
					error::add('The image was only partially uploaded.');
					break;
				
				case UPLOAD_ERR_INI_SIZE:
					error::add('The uploaded file exceeds the upload_max_filesize directive in php.ini.');
					break;
				
				case UPLOAD_ERR_NO_FILE:
					error::add('No file was uploaded.');
					break;
				
				case UPLOAD_ERR_NO_TMP_DIR:
					error::add('Missing a temporary directory.');
					break;
				
				case UPLOAD_ERR_CANT_WRITE:
					error::add('Failed to write image to disk.');
					break;
				
				default:
					error::add('Unable to upload image.');
			}
			
			if ($uploading) {
				$uploading   = false; // Until we make our next checks.
				$valid_types = array
				(
					'jpg',
					'gif',
					'png'
				);
				
				$valid_name         = preg_match('/(.+)\.([a-z0-9]+)$/i', $_FILES['image']['name'], $match);
				$image_data['type'] = strtolower($match[2]);
				$image_data['md5']  = md5_file($_FILES['image']['tmp_name']);
				$image_data['name'] = $_SERVER['REQUEST_TIME'] . mt_rand(99, 999999);
				$image_data['original_name'] = str_replace(array
				(
					'/',
					'<',
					'>',
					'"',
					"'",
					'%'
				), '', $_FILES['image']['name']);
				$image_data['original_name'] = substr(trim($image_data['original_name']), 0, 70);
				
				if ($image_data['type'] == 'jpeg') {
					$image_data['type'] = 'jpg';
				}
				
				if ( ! $perm->get('post_image')) {
					error::add('You do not have permission to upload images.');
				} else if ($valid_name === 0) {
					error::add('The image has an invalid file name.');
				} else if ( ! in_array($image_data['type'], $valid_types)) {
					error::add('Only <strong>GIF</strong>, <strong>JPEG</strong> and <strong>PNG</strong> files are allowed.');
				} else if ($_FILES['image']['size'] > MAX_IMAGE_SIZE) {
					error::add('Uploaded images can be no greater than ' . round(MAX_IMAGE_SIZE / 1048576, 2) . ' MB. ');
				} else {
					$uploading = true;
					$image_data['name'] = $image_data['name'] . '.' . $image_data['type'];			
				}
			}
		} 
		
		$imgur = '';
		if( ! $uploading && ! empty($_POST['imgur'])) {
			$_POST['imgur'] = trim($_POST['imgur']);
			if( ! preg_match('/imgur\.com\/([a-zA-Z0-9]{3,10})/', $_POST['imgur'], $matches)) {
				error::add('That does not appear to be a valid imgur URL.');
			} else {
				$imgur = $matches[1];
			}
		}
		
		// Set the author (internal use only).
		$author = $_SESSION['UID'];
		
		// If this is a reply.
		if ($reply) {
			if ( ! $editing) {

			// Check if topic is locked, if so deny posting, except for admins and mods.
			if($locked != 0 && ! $perm->get('lock')) {
				error::add('You cannot reply to a locked thread.');
			}
				// Lurk more.
				if ($_SERVER['REQUEST_TIME'] - $_SESSION['first_seen'] < REQUIRED_LURK_TIME_REPLY) {
					error::add('Lurk for at least ' . REQUIRED_LURK_TIME_REPLY . ' seconds before posting your first reply.');
				}
				
				// Flood control.
				$too_early = $_SERVER['REQUEST_TIME'] - FLOOD_CONTROL_REPLY;
				$res = $db->q('SELECT 1 FROM replies WHERE author_ip = ? AND time > ?', $_SERVER['REMOTE_ADDR'], $too_early);

				if ($res->fetchColumn()) {
					error::add('Wait at least ' . FLOOD_CONTROL_REPLY . ' seconds between each reply. ');
				}

				if ( ! empty($namefag)) {
					$namefag = tripcode($namefag);
				}
				
				if(error::valid()) {
					$db->q
					(
						'INSERT INTO replies 
						(author, author_ip, parent_id, body, namefag, tripfag, link, time, imgur) VALUES 
						(?, ?, ?, ?, ?, ?, ?, ?, ?)',
						$author, $_SERVER['REMOTE_ADDR'], $_GET['reply'], $body, $namefag[0], $namefag[1], $user_link, $_SERVER['REQUEST_TIME'], $imgur
					);
					$inserted_id = $db->lastInsertId();
				
					// Notify cited posters.
					if(strpos($body, '@OP') !== false) {
						$db->q('INSERT INTO citations (reply, topic, uid) VALUES (?, ?, ?)', $inserted_id, $_GET['reply'], $topic_author);
					}
					preg_match_all('/@([0-9,]+)/m', $body, $matches);
					// Needs to filter before array_unique in case of @11, @1,1 etc.
					$citations = filter_var_array($matches[0], FILTER_SANITIZE_NUMBER_INT);
					$citations = array_unique($citations);
					$citations = array_slice($citations, 0, 9);
					foreach ($citations as $citation) {
						// Note that nothing is inserted unless the SELECT returns a row.
						$db->q('INSERT INTO citations (reply, topic, uid) SELECT ?, ?, `author` FROM replies WHERE replies.id = ? AND replies.parent_id = ?', $inserted_id, $_GET['reply'], (int) $citation, $_GET['reply']);
					}
					
					// Update watchlists.
					$db->q('UPDATE watchlists SET new_replies = 1 WHERE topic_id = ? AND uid != ?', $_GET['reply'], $_SESSION['UID']);
					
					$congratulation = m('Notice: Reply posted');
				}
			} else { // Editing.
				if(error::valid()) {
					$db->q('UPDATE replies SET body = ?, edit_mod = ?, edit_time = ? WHERE id = ?', $body, $edit_mod, $_SERVER['REQUEST_TIME'], $_GET['edit']);
					if($edit_mod) {
						$db->q('INSERT INTO revisions (type, foreign_key, text) VALUES (?, ?, ?)', 'reply', $_GET['edit'], $edit_data['body']);
						log_mod('edit_reply', $_GET['edit'], $db->lastInsertId());
					}
					$congratulation = m('Notice: Reply edited');
				}
			}
		} else { // Or a topic.
			check_length($headline, 'headline', MIN_LENGTH_HEADLINE, MAX_LENGTH_HEADLINE);
	
			if ( ! $editing) {
				// Do we need to lurk some more?
				if ($_SERVER['REQUEST_TIME'] - $_SESSION['first_seen'] < REQUIRED_LURK_TIME_TOPIC) {
					error::add('Lurk for at least ' . REQUIRED_LURK_TIME_TOPIC . ' seconds before posting your first topic.');
				}
				
				// Flood control.
				$too_early = $_SERVER['REQUEST_TIME'] - FLOOD_CONTROL_TOPIC;
				$res = $db->q('SELECT 1 FROM topics WHERE author_ip = ? AND time > ?', $_SERVER['REMOTE_ADDR'], $too_early);
				
				if ($res->fetchColumn()) {
					error::add('Wait at least ' . FLOOD_CONTROL_TOPIC . ' seconds before creating another topic. ');
				}
				
				// Is this a valid poll?
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
				
				if(isset($_POST['hide_results'])) {
					$hide_results = 1;
				} else {
					$hide_results = 0;
				}
				
				if ( ! empty($namefag)) {
					$namefag = tripcode($namefag);
				}
				
				if(isset($_POST['sticky']) && $perm->get('stick')) {
					$sticky = 1;
				} else {
					$sticky = 0;
				}

				if(isset($_POST['locked']) && $perm->get('lock')) {
					$locked = 1;
				} else {
					$locked = 0;
				}

				// Prepare our query.
				if(error::valid()) {
					$db->q
					(
						'INSERT INTO topics 
						(author, author_ip, headline, body, last_post, time, namefag, tripfag, link, sticky, locked, poll, poll_hide, imgur) VALUES 
						(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', 
						$author, $_SERVER['REMOTE_ADDR'], $headline, $body, $_SERVER['REQUEST_TIME'], $_SERVER['REQUEST_TIME'], $namefag[0], $namefag[1], $user_link, $sticky, $locked, $poll, $hide_results, $imgur
					);
					$inserted_id = $db->lastInsertId();
					if($poll) {
						foreach($_POST['option'] as $option) {
							$db->q('INSERT INTO poll_options (`parent_id`, `option`) VALUES (?, ?)', $inserted_id, $option);
						}
					}
					$congratulation = m('Notice: Topic created');
				}
				
			} else { // Editing.
				if( error::valid()) {
					$res = $db->q('UPDATE topics SET headline = ?, body = ?, edit_mod = ?, edit_time = ? WHERE id = ?', $headline, $body, $edit_mod, $_SERVER['REQUEST_TIME'], $_GET['edit']);
					$congratulation = m('Notice: Topic edited');
					if($edit_mod) {
						$db->q('INSERT INTO revisions (type, foreign_key, text) VALUES (?, ?, ?)', 'topic', $_GET['edit'], $edit_data['body']);
						log_mod('edit_topic', $_GET['edit'], $db->lastInsertId());
					}
				}
			}
		}
		
		// If all is well, execute!
		if (error::valid()) {
			
			// We did it!
			if ( ! $editing) {
				$raw_name = (isset($_POST['name']) ? super_trim($_POST['name']) : '');
				$db->q('UPDATE users SET post_count = post_count + 1, namefag = ? WHERE uid = ?', $raw_name, $_SESSION['UID']);
				$_SESSION['post_count']++;
				$_SESSION['poster_name'] = $raw_name;

				setcookie('last_bump', time(), $_SERVER['REQUEST_TIME'] + 315569260, '/');
				
				if ($reply) {
					// Update last bump.
					$db->q("UPDATE last_actions SET time = ? WHERE feature = 'last_bump'", $_SERVER['REQUEST_TIME']);
					$db->q('UPDATE topics SET replies = replies + 1, last_post = ? WHERE id = ?', $_SERVER['REQUEST_TIME'], $_GET['reply']);
					
					$target_topic = $_GET['edit'];
					$redir_loc    = $_GET['reply'] . ($_SESSION['settings']['posts_per_page'] ? '/reply/' : '#reply_') . $inserted_id;
				} else { // If topic.
					// Do not change the time() below to REQUEST_TIME. The script execution may have taken a second.
					setcookie('last_topic', time(), $_SERVER['REQUEST_TIME'] + 315569260, '/');
					// Update last topic and last bump, for people using the "date created" order option in the dashboard.
					$db->q("UPDATE last_actions SET time = ? WHERE feature = 'last_topic' OR feature = 'last_bump'", $_SERVER['REQUEST_TIME']);
					
					$target_topic = $inserted_id;
					$redir_loc    = $inserted_id;
				}
			} else { // If editing.
				if ($reply) {
					$target_topic = $_GET['reply'];
					$redir_loc    = $_GET['reply'] . ($_SESSION['settings']['posts_per_page'] ? '/reply/' : '#reply_') . $_GET['edit'];
				} else { // If topic.
					$target_topic = $_GET['edit'];
					$redir_loc    = $_GET['edit'];
				}
			}
			
			// Take care of the upload.
			if ($uploading) {
				// Check if this image is already on the server.
				$duplicate_check = $db->q('SELECT file_name FROM images WHERE md5 = ?', $image_data['md5']);
				$previous_image = $duplicate_check->fetchColumn();				
				
				// If the file has been uploaded before this, just link the old version.
				if ($previous_image) {
					$image_data['name'] = $previous_image;
				} else { // Otherwise, keep the new image and make a thumbnail.
					thumbnail($_FILES['image']['tmp_name'], $image_data['name'], $image_data['type']);
					move_uploaded_file($_FILES['image']['tmp_name'], 'img/' . $image_data['name']);
				}
				
				if($editing) {
					delete_image($reply ? 'reply' : 'topic', $_GET['edit']);
					$image_target = $_GET['edit'];
				} else {
					$image_target = $inserted_id;
				}

				if ($reply) {
					$db->q('INSERT INTO images (file_name, original_name, md5, reply_id) VALUES (?, ?, ?, ?)', $image_data['name'], $image_data['original_name'], $image_data['md5'], $image_target);
				} else {
					$db->q('INSERT INTO images (file_name, original_name, md5, topic_id) VALUES (?, ?, ?, ?)', $image_data['name'], $image_data['original_name'], $image_data['md5'], $image_target);
				}
			}
			
			// Add topic to watchlist if desired.
			if ($_POST['watch_topic'] && ! $watching_topic) {
				$db->q('INSERT INTO watchlists (uid, topic_id) VALUES (?, ?)', $_SESSION['UID'], $target_topic);
			}
			
			// Set the congratulation notice and redirect to affected topic or reply.
			redirect($congratulation, 'topic/' . $redir_loc);
			
		} else { // If we got an error, insert this into failed postings.
			if ($reply) {
				$db->q('INSERT INTO failed_postings (time, uid, reason, body) VALUES (?, ?, ?, ?)', $_SERVER['REQUEST_TIME'], $_SESSION['UID'], serialize(error::$errors), substr($body, 0, MAX_LENGTH_BODY));
			} else {
				$db->q('INSERT INTO failed_postings (time, uid, reason, body, headline) VALUES (?, ?, ?, ?, ?)', $_SERVER['REQUEST_TIME'], $_SESSION['UID'], serialize(error::$errors), substr($body, 0, MAX_LENGTH_BODY), substr($headline, 0, MAX_LENGTH_HEADLINE));
			}
		}
	}
}

error::output();

// For the bot check.
$start_time = $_SERVER['REQUEST_TIME'];
if (ctype_digit($_POST['start_time'])) {
	$start_time = $_POST['start_time'];
}

echo '<div>';

// Check if OP.
if ($reply && ! $editing) {
	echo '<p>You <strong>are';
	if ($_SESSION['UID'] !== $topic_author) {
		echo ' not';
	}
	echo '</strong> recognized as the original poster of this topic.</p>';
}

// Print deadline for edit submission.
if ($editing && $perm->get('edit_limit') != 0) {
	echo '<p>You have <strong>' . age($_SERVER['REQUEST_TIME'], $edit_data['time'] + $perm->get('edit_limit')) . '</strong> left to finish editing this post.</p>';
}

// Print preview.
if ($_POST['preview'] && ! empty($body)) {
	$preview_body = parser::parse($body);
	$preview_body = preg_replace('/^@([0-9]+|OP),?([0-9]+)?/m', '<span class="unimportant"><a href="'.DIR.'topic/'.(int)$_GET['reply'].'#reply_$1$2">$0</a></span>', $preview_body);
	echo '<h3 id="preview">Preview</h3><div class="body standalone">' . $preview_body . '</div>';
}

// Check if any new replies have been posted since we last viewed the topic.
if ($reply && isset($_SESSION['topic_visits'][$_GET['reply']]) && $_SESSION['topic_visits'][$_GET['reply']] < $topic_replies) {
	$new_replies = $topic_replies - $_SESSION['topic_visits'][$_GET['reply']];
	echo '<p><a href="'.DIR.'topic/' . $_GET['reply'] . '#new"><strong>' . $new_replies . '</strong> new repl' . ($new_replies == 1 ? 'y</a> has' : 'ies</a> have') . ' been posted in this topic since you last checked!</p>';
}

if($_POST['form_sent']) {
	$set_name = $_POST['name'];
} else {
	$set_name = $_SESSION['poster_name'];
}

// Print the main form.
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
	<?php if( ! $editing): ?>
			<div class="row"><label for="name">Name</label>: <input id="name" name="name" type="text" size="30" maxlength="30" tabindex="2" value="<?php echo htmlspecialchars($set_name) ?>" class="inline">
		<?php if($perm->get('link')): ?>
			<input type="checkbox" name="post_as_group" id="post_as_group" value="1" class="inline" <?php if(isset($_SESSION['show_group'])) echo ' checked="checked"' ?> />
			<label for="post_as_group" class="inline"> Post as <?php echo htmlspecialchars($perm->get('name')) ?></label>
		<?php endif; ?>
	<?php endif; ?>
	<div class="row">
		<label for="body" class="noscreen">Post body</label> 
		<textarea name="body" cols="120" rows="18" tabindex="2" id="body"><?php
	// If we've had an error or are previewing, print the submitted text.
	if ($_POST['form_sent'] || $editing) {
		echo htmlspecialchars($body);
	}  else if (isset($_GET['quote_topic']) || ctype_digit($_GET['quote_reply'])) { // Otherwise, fetch any text we may be quoting.
		// Fetch the topic.
		if (isset($_GET['quote_topic'])) {
			$res = $db->q('SELECT body FROM topics WHERE id = ? AND deleted = 0', $_GET['reply']);
		} else { // ...or a reply.
			echo '@' . number_format($_GET['quote_reply']) . "\n\n";
			$res = $db->q('SELECT body FROM replies WHERE id = ? AND deleted = 0', $_GET['quote_reply']);
		}

		// Execute it.
		$quoted_text = $res->fetchColumn();

		// Snip citations from quote.
		$quoted_text = trim(preg_replace('/^@([0-9,]+|OP)/m', '', $quoted_text));

		// Prefix newlines with >.
		$quoted_text = preg_replace('/^/m', '> ', $quoted_text);
		echo htmlspecialchars($quoted_text) . "\n\n";
	}

	// If we're just citing, print the citation.
	else if (ctype_digit($_GET['cite'])) {
		echo '@' . number_format($_GET['cite']) . "\n\n";
	}
	echo '</textarea>';

	if (ALLOW_IMAGES && $perm->get('post_image')) {
		echo '<label for="image" class="noscreen">Image</label> <input type="file" name="image" id="image" />';
	}
	

	if(IMGUR_KEY && ! $editing):
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
		<p><?php echo m('Post: Help') ?></p>
	</div>
<?php	
	if ( ! $watching_topic) {
		echo '<div class="row"><input type="checkbox" name="watch_topic" id="watch_topic" class="inline"';
		if ($_POST['watch_topic']) {
			echo ' checked="checked"';
		}
		echo ' /><label for="watch_topic" class="inline"> Watch</label></div>';
	}
	if( ! $reply && ! $editing) {
		if($perm->get('stick')) {
			echo '<div><input type="checkbox" name="sticky" value="1" class="inline"/><label for="sticky" class="inline"> Stick</label></div>';
		}
		if($perm->get('lock')) {
			echo '<div class="row"><input type="checkbox" name="locked" value="1" class="inline"/><label for="locked" class="inline"> Lock</label></div>';
		}
	}
	
	if(!$reply && !$editing):
?>
		
	<input type="hidden" id="enable_poll" name="enable_poll" value="1" />
	<ul class="menu"><li><a id="poll_toggle" onclick="showPoll(this);">Poll options</a></li></ul>
		
	<table id="topic_poll">
		<tr class="odd">
			<th colspan="2"><input type="checkbox" name="hide_results" id="hide_results" value="1" class="inline"<?php if($_POST['hide_results']) echo ' checked="checked"' ?>/><label for="hide_results" class="inline help" title="If checked, the results of the poll will be hidden until a user either votes or chooses to 'show results'."> Hide results before voting</label></td>
		</tr>
<?php
		// Print at least two, or as many as were submitted (in case of preview/error)
		for($i = 1, $s = count($_POST['option']); ($i <= 2 || $i <= $s); ++$i):
?>
		<tr>
			<td class="minimal">
				<label for="poll_option_<?php echo $i ?>">Poll option #<?php echo $i ?></label>
			</td>
			<td>
				<input type="text" size="50" maxlength="80" id="poll_option_<?php echo $i ?>" name="option[]" value="<?php if($_POST['form_sent']) echo htmlspecialchars($_POST['option'][$i - 1]) ?>" class="poll_input" />
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
</form>
</div>
<?php
// If citing, fetch and display the reply in question.
if (ctype_digit($_GET['cite'])) {
	$res = $db->q('SELECT body, namefag, tripfag FROM replies WHERE id = ?', $_GET['cite']);
	list($cited_text, $r_namefag, $r_tripfag) = $res->fetch();
	if (!empty($cited_text)) {
		$cited_text = parser::parse($cited_text);
		// Linkify citations within the text.
		preg_match_all('/^@([0-9,]+)/m', $cited_text, $matches);
		foreach ($matches[0] as $formatted_id) {
			$pure_id = str_replace(array(
				'@',
				','
			), '', $formatted_id);
			$cited_text = str_replace($formatted_id, '<a href="'.DIR.'topic/' . $_GET['reply'] . '#reply_' . $pure_id . '" class="unimportant">' . $formatted_id . '</a>', $cited_text);
		}
		// And now, let us parse it!
		if($r_namefag != '' || $r_tripfag != ''){
			if($r_namefag != ''){ 
				$replyTo = htmlspecialchars($r_namefag);
				if($_tripfag != '') $replyTo .= " ";
			}else{
				$replyTo = '';
			}
			if($r_tripfag!=''){
				$replyTo .= '<a style="font-weight: 400">'.htmlspecialchars($r_tripfag).'</a>';
			}
		}else{
			$replyTo = '<a style="font-weight: 400">Anonymous</a> ';
		}
		echo '<h3 id="replying_to">Replying to ' . $replyTo . '&hellip;</h3> <div class="body standalone">' . $cited_text . '</div>';
	}
} else if ($reply && !isset($_GET['quote_topic']) && !isset($_GET['quote_reply']) && !$editing) { // If we're not citing or quoting, display the original post.
	$res = $db->q('SELECT body FROM topics WHERE id = ?', $_GET['reply']);
	$cited_text = $res->fetchColumn();
	echo '<h3 id="replying_to">Original post</h3> <div class="body standalone">' . parser::parse($cited_text) . '</div>';
}

$template->render();
?>