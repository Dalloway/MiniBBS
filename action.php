<?php
require './includes/bootstrap.php';
force_id();
header('X-Frame-Options: SAMEORIGIN');

if(isset($_POST['confirm'])) {
	if( ! check_token()) {
		error::fatal('Your session expired.');
	}
}

// Take the action.
switch($_GET['action']) {
	// Normal actions.
	case 'watch_topic':
	
		if( ! ctype_digit($_GET['id'])) {
			error::fatal('Invalid ID.');
		}
		
		$id = $_GET['id'];
		$template->title = 'Watch topic';
		
		if(isset($_POST['confirm'])) {
			$check_watchlist = $db->q('SELECT 1 FROM watchlists WHERE uid = ? AND topic_id = ?', $_SESSION['UID'], $id);
			if( ! $check_watchlist->fetchColumn()) {
				$db->q('INSERT INTO watchlists (uid, topic_id) VALUES (?, ?)', $_SESSION['UID'], $id);
			}
			redirect('Topic added to your watchlist.');
		}
		
	break;
	
	case 'unwatch_topic':
	
		if( ! ctype_digit($_GET['id'])) {
			error::fatal('Invalid ID.');
		}
		
		$id = $_GET['id'];
		$template->title = 'Unwatch topic';
		
		if(isset($_POST['confirm'])) {
			$db->q('DELETE FROM watchlists WHERE uid = ? AND topic_id = ?', $_SESSION['UID'], $id);
			redirect('Topic removed from your watchlist.');
		}
		
	break;
	
	case 'hide_image':
		if(strlen($_GET['id']) != 32) {
			error::fatal('That doesn\'t look like a valid MD5.');
		}
		
		$id = $_GET['id'];
		$template->title = 'Hide image';
		
		if(isset($_POST['confirm'])) {
			$db->q('INSERT INTO ignore_lists (uid, ignored_phrases) VALUES (?, ?) ON DUPLICATE KEY UPDATE ignored_phrases = CONCAT(ignored_phrases, ?)', $_SESSION['UID'], $id, "\n" . $id);
			unset($_SESSION['ignored_phrases']);
			redirect('Image added to your ignore list.');
		}
	break;
	
	case 'unhide_image':
		if(strlen($_GET['id']) != 32) {
			error::fatal('That doesn\'t look like a valid MD5.');
		}
		
		$id = $_GET['id'];
		$template->title = 'Unhide image';
		
		if(isset($_POST['confirm'])) {
			$res = $db->q('SELECT ignored_phrases FROM ignore_lists WHERE uid = ?', $_SESSION['UID']);
			$ignored_phrases = str_replace($id, '', $res->fetchColumn());
			$ignored_phrases = preg_replace("/\n\r?\n/", "\n", $ignored_phrases);
			$db->q('UPDATE ignore_lists SET ignored_phrases = ? WHERE uid = ?', $ignored_phrases, $_SESSION['UID']);
			unset($_SESSION['ignored_phrases']);
			
			redirect('Image removed from your ignore list.');
		}
	break;
	
	case 'cast_vote':
		if( ! ctype_digit($_GET['id'])) {
			error::fatal('Invalid topic ID.');
		}
		
		$id = $_GET['id'];
		$template->title = 'Cast vote';
		
		if($_POST['show_results']) {
			$option_id = null;
		} else {
			if( ! ctype_digit($_POST['option_id'])) {
				redirect("You didn't select an option.", 'topic/' . $id);
			}
			$option_id = (int) $_POST['option_id'];
		}

		if(check_token()) {
			$check_votes = $db->q('SELECT 1 FROM poll_votes WHERE (ip = ? OR uid = ?) AND parent_id = ?', $_SERVER['REMOTE_ADDR'], $_SESSION['UID'], $id);
			if( ! $check_votes->fetchColumn()) {
				$db->q('INSERT INTO poll_votes (uid, ip, parent_id, option_id) VALUES (?, ?, ?, ?)', $_SESSION['UID'], $_SERVER['REMOTE_ADDR'], $id, $option_id);
				if( ! is_null($option_id)) {
					$db->q('UPDATE poll_options SET votes = votes + 1 WHERE id = ?', $option_id);
					redirect(m('Notice: Voted'), 'topic/' . $id);
				} else {
					redirect(null, 'topic/' . $id);
				}
			}
			else {
				error::fatal('You\'ve already voted in this poll.');
			}
		}
	break;
	
	case 'revert_change':
		if( ! ctype_digit($_GET['id'])) {
			error::fatal('Invalid topic ID.');
		}
		
		$id = $_GET['id'];
		$template->title = 'Revert change';
		
		if(isset($_POST['confirm'])) {
			$res = $db->q('SELECT type, foreign_key, text FROM revisions WHERE id = ?', $id);
			if( ! $res) {
				error::fatal('No revision with that ID was found.');
			}
			$revision = $res->fetchObject();
			
			switch($revision->type) {
				case 'page':
					if( ! $perm->get('cms')) {
						error::fatal(m('Error: Access denied'));
					}
					
					$previous = $db->q('SELECT content FROM pages WHERE id = ?', $revision->foreign_key);
					$db->q('UPDATE pages SET content = ? WHERE id = ?', $revision->text, $revision->foreign_key);
					$redirect = 'CMS';
				break;
				
				case 'topic':
					if( ! $perm->get('edit_others')) {
						error::fatal(m('Error: Access denied'));
					}

					$previous = $db->q('SELECT body FROM topics WHERE id = ?', $revision->foreign_key);
					$db->q('UPDATE topics SET body = ? WHERE id = ?', $revision->text, $revision->foreign_key);
					$redirect = 'topic/' . $revision->foreign_key;
				break;
				
				case 'reply':
					if( ! $perm->get('edit_others')) {
						error::fatal(m('Error: Access denied'));
					}

					$previous = $db->q('SELECT body FROM replies WHERE id = ?', $revision->foreign_key);
					$db->q('UPDATE replies SET body = ? WHERE id = ?', $revision->text, $revision->foreign_key);
					$redirect = 'reply/' . $revision->foreign_key;
				break;
			}
			
			$unreverted_text = $previous->fetchColumn();
			$db->q('INSERT INTO revisions (type, foreign_key, text) VALUES (?, ?, ?)', $revision->type, $revision->foreign_key, $unreverted_text);
			log_mod('revert_' . $revision->type, $revision->foreign_key, $db->lastInsertId());
			redirect('Change reverted.', $redirect);
		}
	break;
	
	case 'dismiss_all_PMs':
		$template->title = 'Mark all PMs as read';
		
		if(isset($_POST['confirm'])) {
			$db->q('DELETE FROM pm_notifications WHERE uid = ?', $_SESSION['UID']);
			redirect('All messages dismissed.');
		}
	break;
	
	case 'ignore_pm':
		if($_GET['id'] == '*') {
			$template->title = 'Ignore all PMs';
			$source = '*';
		}
		else {
			$template->title = 'Ignore PM author';
			
			if( ! ctype_digit($_GET['id'])) {
				error::fatal('Invalid PM ID.');
			}
			
			$res = $db->q('SELECT source, destination FROM private_messages WHERE id = ?', $_GET['id']);
			list($source, $destination) = $res->fetch();
			
			if($destination != $_SESSION['UID']) {
				error::fatal('You can only ignore PMs that are addressed to you.');
			}

			if($source == 'mods' || $source == 'admins') {
				error::fatal('You cannot ignore that user.');
			}
		}
		$id = $_GET['id'];
		
		if(isset($_POST['confirm'])) {
			 $db->q('INSERT into pm_ignorelist (uid, ignored_uid) VALUES (?, ?)', $_SESSION['UID'], $source);
			 if($id == '*') {
				// Mark all messages as read.
				$db->q('DELETE FROM pm_notifications WHERE uid = ?', $_SESSION['UID']);
			 } else {
				// Mark the offending message as ignored.
				$db->q('UPDATE private_messages SET ignored = 1 WHERE id = ?', $id);
			 }
			 redirect('Your PM ignore list has been updated.', 'private_messages');
		}
	break;
	
	case 'unignore_pm':
		if($_GET['id'] == '*') {
			$template->title = 'Stop ignoring all PMs';
			$source = '*';
		}
		else {
			$template->title = 'Unignore PM author';
			
			if( ! ctype_digit($_GET['id'])) {
				error::fatal('Invalid PM ID.');
			}
			
			$res = $db->q('SELECT source, destination FROM private_messages WHERE id = ?', $_GET['id']);
			list($source, $destination) = $res->fetch();
			
			if($destination != $_SESSION['UID']) {
				error::fatal('That PM was not addressed to you.');
			}
		}
		$id = $_GET['id'];
		
		if(isset($_POST['confirm'])) {
			$db->q('UPDATE private_messages SET ignored = 0 WHERE id = ?', $id);
			$db->q('DELETE from pm_ignorelist WHERE uid = ? AND ignored_uid = ?', $_SESSION['UID'], $source);
			 
			redirect('Your ignore list has been updated.', 'private_messages');
		}
	break;
	
	case 'dismiss_pm':
		if( ! ctype_digit($_GET['id'])) {
			error::fatal('Invalid PM ID.');
		}
		$id = $_GET['id'];
		
		if( ! $perm->get('read_mod_pms')) {
			error::fatal(m('Error: Access denied'));
		}
		
		if(isset($_POST['confirm'])) {
			$db->q('DELETE FROM pm_notifications WHERE parent_id = ?', $id);
			/* If bootstrap.php set $new_parent, we have another PM; redirect to that. */
			if(isset($new_parent)) {
				$redirect_to = 'private_message/' . $new_parent. ($new_pm != $new_parent ? '#reply_'.$new_pm : '');
			} else {
				$redirect_to = 'private_messages';
			}
			redirect('Message dismissed; other mods will no longer be notified of it.', $redirect_to);
		}
	break;
	
	case 'delete_pm':
		if( ! $perm->get('delete')){
			error::fatal(m('Error: Access denied'));
		}
		if( ! ctype_digit($_GET['id'])) {
			error::fatal('Invalid PM ID.');
		}
		$id = $_GET['id'];
		
		if(isset($_POST['confirm'])) {
			$res = $db->q('SELECT parent FROM private_messages WHERE id = ?', $_GET['id']);
			$parent = $res->fetchColumn();
			if($id == $parent) {
				$db->q('DELETE FROM private_messages WHERE parent = ?', $id);
				$db->q('DELETE FROM pm_notifications WHERE parent_id = ?', $id);
			}
			else {
				$db->q('DELETE FROM private_messages WHERE id = ?', $id);
				$db->q('DELETE FROM pm_notifications WHERE pm_id = ?', $id);
			}
			redirect('Message deleted.', 'private_messages');
		}
	break;
	
	case 'delete_all_pms':
		if( ! $perm->get('delete_all_pms')) {
			error::fatal(m('Error: Access denied'));
		}
		if( ! id_exists($_GET['id'])) {
			error::fatal('There is no such user.');
		}
		if($perm->is_admin($_GET['id']) || ($perm->is_mod($_GET['id']) && ! $perm->is_admin())) {
			error::fatal(m('Error: Access denied'));
		}
		$id = $_GET['id'];
		
		if(isset($_POST['confirm'])) {
			// Delete notifications and messages from source.
			$db->q('DELETE private_messages, pm_notifications FROM private_messages LEFT OUTER JOIN pm_notifications ON private_messages.id = pm_notifications.pm_id WHERE private_messages.source = ?', $id);
			redirect('PMs and notifications deleted.', 'profile/'.$id);
		}
	break;
	
	case 'delete_image':

		if( ! ctype_digit($_GET['id'])) {
			error::fatal('Invalid ID.');
		}
		
		$id = $_GET['id'];
		$template->title = 'Delete image';
		
		if(isset($_GET['topic'])) {
			$type = 'reply';
			$redirect_to = 'topic/' . (int) $_GET['topic'] . '#reply_' . $id;
		} else {
			$type = 'topic';
			$redirect_to = 'topic/' . $id;
		}
		
		if($type == 'topic') {
			$res = $db->q('SELECT author, time FROM topics WHERE id = ?', $id);
		} else {
			$res = $db->q('SELECT author, time FROM replies WHERE id = ?', $id);
		}
		if( ! $res) {
			error::fatal('There is no post with that ID.');
		}
		
		$post = $res->fetchObject();
		
		if(isset($_POST['confirm'])) {
			if($perm->get('delete')) {
				$res = $db->q('SELECT original_name FROM images WHERE ' . $type . '_id = ?', $id);
				$original_name = $res->fetchColumn();
				log_mod('delete_image', $id, $original_name);
			} else if($post->author != $_SESSION['UID']) {
				error::fatal('You are not the author of that post.');
			} else if( $perm->get('edit_limit') != 0 && ($_SERVER['REQUEST_TIME'] - $post->time > $perm->get('edit_limit')) ) {
				error::fatal('You\'re too late to delete that image.');
			}

			delete_image($type, $id);
			redirect('Image deleted.', $redirect_to);
		}
	
	break;
	
	case 'delete_page':
	
		if( ! $perm->get('cms')) {
			error::fatal(m('Error: Access denied'));
		}
		
		if( ! ctype_digit($_GET['id'])) {
			error::fatal('Invalid ID.');
		}
		
		$id = $_GET['id'];
		$template->title = 'Delete page';
		
		if(isset($_POST['confirm'])) {
			log_mod('delete_page', $id);
			$db->q('UPDATE pages SET deleted = 1 WHERE id = ?', $id);
			redirect('Page deleted.', 'CMS');
		}
		
	break;
	
	case 'undelete_page':
		if( ! $perm->get('cms')) {
			error::fatal(m('Error: Access denied'));
		}
		
		if( ! ctype_digit($_GET['id'])) {
			error::fatal('Invalid ID.');
		}
		
		$id = $_GET['id'];
		$template->title = 'Undelete page';	
		
		if(isset($_POST['confirm'])) {
			log_mod('undelete_page', $id);
			$db->q('UPDATE pages SET deleted = 0 WHERE id = ?', $id);
			redirect('Page restored.', 'CMS');
		}
	break;
	
	
	case 'delete_bulletin':
	
		if( ! $perm->get('delete')) {
			error::fatal(m('Error: Access denied'));
		}
		
		if( ! ctype_digit($_GET['id'])) {
			error::fatal('Invalid ID.');
		}
		
		$id = $_GET['id'];
		$template->title = 'Delete bulletin';
		
		if(isset($_POST['confirm'])) {
			log_mod('delete_bulletin', $id);
			$db->q('DELETE FROM bulletins WHERE id = ?', $id);
			redirect('Bulletin deleted.', 'bulletins');
		}
		
	break;
	
	case 'undo_merge':
		if( ! $perm->get('merge')) {
			error::fatal(m('Error: Access denied'));
		}
		
		if( ! ctype_digit($_GET['id'])) {
			error::fatal('Invalid ID.');
		}
		
		$id = $_GET['id'];
		$template->title = 'Undo merge';
		
		if(isset($_POST['confirm'])) {
			$res = $db->q('SELECT id FROM replies WHERE original_parent = ? ORDER BY time ASC LIMIT 1', $id);
			$merged_op = $res->fetchColumn();
			
			if( ! $merged_op) {
				error::fatal('Could not find any replies with that original parent.');
			}
			
			$db->q('DELETE FROM replies WHERE id = ?', $merged_op);
			$db->q('UPDATE images SET topic_id = ? WHERE reply_id = ?', $id, $merged_op);
			$db->q('UPDATE replies SET parent_id = original_parent, original_parent = null WHERE original_parent = ?', $id);
			$db->q('UPDATE topics SET deleted = 0 WHERE id = ?', $id);
			
			log_mod('unmerge', $id);
			redirect('Topic unmerged.', 'topic/' . $id);
		}
	break;
		
	case 'unban_uid':
	
		if( ! $perm->get('ban')) {
			error::fatal(m('Error: Access denied'));
		}
		
		if( ! id_exists($_GET['id'])) {
			error::fatal('There is no such user.');
		}
		
		$id = $_GET['id'];
		$template->title = 'Unban poster ' . $id;
		
		if(isset($_POST['confirm'])) {
			$db->q('DELETE FROM bans WHERE target = ?', $id);
			cache::clear('bans');

			log_mod('unban_uid', $id);
			redirect('User ID unbanned.');
		}
		
	break;
		
	case 'unban_ip':
	
		if( ! $perm->get('ban')) {
			error::fatal(m('Error: Access denied'));
		}
		
		if( ! filter_var($_GET['id'], FILTER_VALIDATE_IP)) {
			error::fatal('That is not a valid IP address.');
		}
		
		$id = $_GET['id'];
		$template->title = 'Unban IP address ' . $id;
		
		if(isset($_POST['confirm'])) {
			$db->q('DELETE FROM bans WHERE target = ?', $id);
			cache::clear('bans');
			
			log_mod('unban_ip', $id);
			redirect('IP address unbanned.', 'IP_address/'.$id);
		}
		
	break;
	
	case 'unban_cidr':
		if( ! $perm->get('ban')) {
			error::fatal(m('Error: Access denied'));
		}
		
		if($perm->get_ban_type($_GET['id']) !== 'cidr') {
			error::fatal('That is not a valid CIDR address.');
		}
		
		$id = $_GET['id'];
		$template->title = 'Unban CIDR range ' . htmlspecialchars($id);
		
		if(isset($_POST['confirm'])) {
			$db->q('DELETE FROM bans WHERE target = ?', $id);
			cache::clear('bans');
			
			log_mod('unban_cidr', $id);
			redirect('CIDR range unbanned.', 'mod_log');
		}
	break;
	
	case 'unban_wild':
		if( ! $perm->get('ban')) {
			error::fatal(m('Error: Access denied'));
		}
		
		if($perm->get_ban_type($_GET['id']) !== 'wild') {
			error::fatal('That is not a valid wildcard IP.');
		}
		
		$id = $_GET['id'];
		$template->title = 'Unban wildcard range ' . htmlspecialchars($id);

		if(isset($_POST['confirm'])) {
			$db->q('DELETE FROM bans WHERE target = ?', $id);
			cache::clear('bans');
			
			log_mod('unban_wild', $id);
			redirect('Wildcard range unbanned.', 'mod_log');
		}
	break;

	case 'stick_topic':
	
		if( ! $perm->get('stick')) {
			error::fatal(m('Error: Access denied'));
		}
		if( ! ctype_digit($_GET['id'])) {
			error::fatal('Invalid topic ID.');
		}
		
		$id = $_GET['id'];
	
		if(isset($_POST['confirm'])) {
			$db->q('UPDATE `topics` SET `sticky` = ? WHERE `topics`.`id` = ? LIMIT 1', 1, $id);
			log_mod('stick_topic', $id);
			redirect('Topic is now sticky.', 'topic/' . $id);
		}
		
	break;
	
	case 'unstick_topic':
	
		if( ! $perm->get('stick')) {
			error::fatal(m('Error: Access denied'));
		}
		if( ! ctype_digit($_GET['id'])) {
			error::fatal('Invalid topic ID.');
		}
		
		$id = $_GET['id'];
	
		if(isset($_POST['confirm'])) {
			$db->q('UPDATE `topics` SET `sticky` = ? WHERE `topics`.`id` = ? LIMIT 1', 0, $id);
			log_mod('unstick_topic', $id);
			redirect('Topic is no longer sticky.', 'topic/' . $id);
		}
		
	break;
	
	case 'lock_topic':
	
		if( ! $perm->get('lock')) {
			error::fatal(m('Error: Access denied'));
		}
		if( ! ctype_digit($_GET['id'])) {
			error::fatal('Invalid topic ID.');
		}
		
		$id = $_GET['id'];
		$template->title = 'Lock topic';
	
		if(isset($_POST['confirm'])) {
			$db->q('UPDATE `topics` SET `locked` = ? WHERE `topics`.`id` = ? LIMIT 1', 1, $id);
			log_mod('lock_topic', $id);
			redirect('Topic has been locked.', 'topic/' . $id);
		}
		
	break;
	
	case 'unlock_topic':
	
		if( ! $perm->get('lock')) {
			error::fatal(m('Error: Access denied'));
		}
		if( ! ctype_digit($_GET['id'])) {
			error::fatal('Invalid topic ID.');
		}
		
		$id = $_GET['id'];
		$template->title = 'Unlock topic';
	
		if(isset($_POST['confirm'])) {
			$db->q('UPDATE `topics` SET `locked` = ? WHERE `topics`.`id` = ? LIMIT 1', 0, $id);
			log_mod('unlock_topic', $id);
			redirect('Topic has been unlocked.', 'topic/' . $id);
		}
		
	break;
	
	case 'delete_topic':
	
		if( ! $perm->get('delete')) {
			error::fatal(m('Error: Access denied'));
		}
		if( ! ctype_digit($_GET['id'])) {
			error::fatal('Invalid topic ID.');
		}
		
		$id = $_GET['id'];
		$template->title = 'Delete topic';
	
		if(isset($_POST['confirm'])) {	
			delete_topic($id);
			
			redirect('Topic archived and deleted.', '');
		}
		
	break;
	
	case 'undelete_topic':
		if( ! $perm->get('undelete')) {
			error::fatal(m('Error: Access denied'));
		}
		if( ! ctype_digit($_GET['id'])) {
			error::fatal('Invalid topic ID.');
		}
		
		$id = $_GET['id'];
		$template->title = 'Undelete topic';
		
		if(isset($_POST['confirm'])) {
			$db->q("UPDATE topics SET deleted = '0' WHERE id = ?", $id);
			log_mod('undelete_topic', $id);
			redirect('Topic restored.');
		}
	break;
		
	case 'delete_reply':
	
		if( ! $perm->get('delete')) {
			error::fatal(m('Error: Access denied'));
		}
		if( ! ctype_digit($_GET['id'])) {
			error::fatal('Invalid reply ID.');
		}
		
		$id = $_GET['id'];
		$template->title = 'Delete reply';
	
		if(isset($_POST['confirm'])) {
			delete_reply($id);

			redirect('Reply archived and deleted.');
		}
		
	break;
	
	case 'undelete_reply':
		if( ! $perm->get('undelete')) {
			error::fatal(m('Error: Access denied'));
		}
		if( ! ctype_digit($_GET['id'])) {
			error::fatal('Invalid reply ID.');
		}
		
		$id = $_GET['id'];
		$template->title = 'Undelete reply';
		
		if(isset($_POST['confirm'])) {
			$db->q("UPDATE replies SET deleted = '0' WHERE id = ?", $id);
			log_mod('undelete_reply', $id);
			redirect('Reply restored.');
		}
	break;
	
	case 'delete_ip_ids':
	
		if( ! $perm->get('delete_ip_ids')) {
			error::fatal(m('Error: Access denied'));
		}
		
		if( ! filter_var($_GET['id'], FILTER_VALIDATE_IP)) {
			error::fatal('That is not a valid IP address.');
		}
		
		$id = $_GET['id'];
		$template->title = 'Delete IDs assigned to <a href="'.DIR.'IP_address/' . $id . '">' . $id . '</a>';
		
		if(isset($_POST['confirm'])) {
			$res = $db->q('SELECT uid FROM users WHERE ip_address = ?', $id);
			
			while($uid = $res->fetchColumn()) {
				if($perm->is_admin($uid)) {
					error::fatal(m('Error: Access denied'));
				}
				
				if($perm->is_mod($uid) && ! $perm->is_admin()) {
					error::fatal(m('Error: Access denied'));
				}
			}
			$db->q('DELETE users, user_settings FROM users LEFT OUTER JOIN user_settings ON users.uid=user_settings.uid WHERE users.ip_address = ?', $id);
			log_mod('delete_ip_ids', $id);
			redirect('IDs deleted.', 'IP_address/' . $id);
		}
		
	break;
	
	case 'nuke_id':
	
		if( ! $perm->get('nuke_id')) {
			error::fatal(m('Error: Access denied'));
		}
		
		if($perm->is_admin($_GET['id'])) {
			error::fatal(m('Error: Access denied'));
		}
		
		if($perm->is_mod($_GET['id']) && ! $perm->is_admin()) {
			error::fatal(m('Error: Access denied'));
		}
		
		if( ! id_exists($_GET['id'])) {
			error::fatal('There is no such user.');
		}
		
		$id = $_GET['id'];
		$template->title = 'Nuke all posts by <a href="'.DIR.'profile/' . $id . '">' . $id . '</a>';
		
		if(isset($_POST['confirm'])) {
			// Delete replies.
			$fetch_parents = $db->q('SELECT parent_id, id FROM replies WHERE author = ?', $id);
			
			$victim_parents = array();
			while(list($parent_id, $reply_id) = $fetch_parents->fetch()) {
				$victim_parents[] = $parent_id;
				delete_image('reply', $reply_id);
			}
			
			// Dump images which belong to topics.
			$fetch_topics = $db->q('SELECT id FROM topics WHERE author = ?', $id);
			
			while($topic_id = $fetch_topics->fetchColumn()) {
				delete_image('topic', $topic_id);
				$fetch_replies = $db->q('SELECT id FROM replies WHERE parent_id = ?', $topic_id);
				while($reply_id = $fetch_replies->fetch()) {
					delete_image('reply', $reply_id);
				}
			}
			
			$db->q("UPDATE replies SET deleted = '1' WHERE author = ?", $id);
			
			foreach($victim_parents as $parent_id) {
				$db->q('UPDATE topics SET replies = replies - 1 WHERE id = ?', $id);
			}
			
			// Delete topics.
			$db->q("UPDATE topics SET deleted = '1' WHERE author = ?", $id);
			log_mod('nuke_id', $id);
			redirect('All topics and replies by ' . $id . ' have been deleted.', 'profile/'.$id);
		}
		
	break;
	
	case 'nuke_ip':
	
		if( ! $perm->get('nuke_ip')) {
			error::fatal(m('Error: Access denied'));
		}
		
		if( ! filter_var($_GET['id'], FILTER_VALIDATE_IP)) {
			error::fatal('That is not a valid IP address.');
		}
		
		$id = $_GET['id'];
		$template->title = 'Nuke all posts by <a href="'.DIR.'IP_address/' . $id . '">' . $id . '</a>';
		
		if(isset($_POST['confirm'])) {		
			$res = $db->q('SELECT uid FROM users WHERE ip_address = ?', $id);
			while($uid = $res->fetchColumn()){
				if($perm->is_admin($uid)) {
					error::fatal(m('Error: Access denied'));
				}
				
				if($perm->is_mod($uid) && ! $perm->is_admin()) {
					error::fatal(m('Error: Access denied'));
				}
			}
			
			// Delete replies.
			$fetch_parents = $db->q('SELECT parent_id, id FROM replies WHERE author_ip = ?', $id);
			$victim_parents = array();
			while(list($parent_id, $reply_id) = $fetch_parents->fetch()) {
				$victim_parents[] = $parent_id;
				delete_image('reply', $reply_id);
			}
			
			// Nuke the images and delete replies.
			$fetch_topics = $db->q('SELECT id FROM topics WHERE author_ip = ?', $id);
			while($topic_id = $fetch_topics->fetchColumn()) {
				delete_image('topic', $topic_id);
				$fetch_replies = $db->q('SELECT id FROM replies WHERE parent_id = ?', $topic_id);
				while($reply_id = $fetch_replies->fetchColumn()) {
					delete_image('reply', $reply_id);
				}
			}

			$db->q("UPDATE replies SET deleted = '1' WHERE author_ip = ?", $id);
			foreach($victim_parents as $parent_id) {
				$db->q('UPDATE topics SET replies = replies - 1 WHERE id = ?', $parent_id);
			}
			
			// Delete topics.
			$db->q("UPDATE topics SET deleted = '1' WHERE author_ip = ?", $id);
			log_mod('nuke_ip', $id);
			redirect('All topics and replies by ' . $id . ' have been deleted.', 'IP_address/'.$id);
		}
	break;
	
	case 'hide_log':
		if( ! $perm->get('hide_log')) {
			error::fatal(m('Error: Access denied'));
		}
		
		if( ! ctype_digit($_GET['id'])) {
			error::fatal('That is not a valid ID.');
		}
		
		$template->title = 'Hide log';
		
		if(isset($_POST['confirm'])) {
			$db->q('UPDATE mod_actions SET hidden = 1 WHERE id = ?', $_GET['id']);
			redirect('Log hidden.', 'mod_log');
		}
	break;
	
	case 'unhide_log':
		if( ! $perm->get('hide_log')) {
			error::fatal(m('Error: Access denied'));
		}
		
		if( ! ctype_digit($_GET['id'])) {
			error::fatal('That is not a valid ID.');
		}
		
		$template->title = 'Unhide log';
		
		if(isset($_POST['confirm'])) {
			$db->q('UPDATE mod_actions SET hidden = 0 WHERE id = ?', $_GET['id']);
			redirect('Log unhidden.', 'mod_log');
		}
	break;
	
	default:
		error::fatal('No valid action specified.');	
}

echo '<p>Really?</p> <form action="" method="post">';
csrf_token();
echo '<div> <input type="hidden" name="confirm" value="1" /> <input type="submit" value="Do it" /> </div>';

$template->render();
?>