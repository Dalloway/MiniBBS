<?php

/* Fetches and formats information for a topic. */
class Topic {
	
	/* The global database object. */
	private $db;
	
	/* The topic ID. */
	public $id;
	
	/* The current page number. Page "0" will show all replies at once. */
	public $page = 0;
	
	/* The number of the first and last reply to be printed (for pagination). */
	private $page_start = 1;
	private $page_end;
	
	/* Did we stop printing replies before the end of the topic (due to pagination)? */
	private $stopped_prematurely = false;
	
	/* When the current user last read this topic, the reply count was: */
	public $last_read_post;
	
	/* The number of replies to this topic (the "replies" column in the topic table). */
	public $reply_count = 0;
	
	/* We recount the number of replies as we grab them, just in case $reply_count is wrong and needs correction. */
	public $reply_recount = 0;
	
	/* Is this topic locked? */
	public $locked = false;
		
	/* Are results hidden before voting in this poll? */
	public $poll_hide = false;
	
	/* The total number of votes in our poll. */
	public $poll_votes = 0;
	
	/* An array of poll options, with the option ID as key, and the value an array with keys 'text' and 'votes'. */
	public $poll_options = array();
	
	/* Has the current user voted in this poll? */
	public $voted = false;
	
	/* Which option ID did the current user vote for? */
	public $chosen_option;
	
	/* Is this topic being watched by the current user? */
	public $watched = false;
	
	/* If this topic is watched, does it have unread replies? */
	public $watched_new = false;
	
	/**
	 * An array of poster information, with the poster UID as key and the value an array with two elements:
	 * 'first_post' => The ID of this poster's first reply in this thread (excluded for the OP)
	 * 'number'     => Counting from 0 (OP), the number of other posters who joined the thread before
	 *                 the current poster. Translates to poster letter (0 = A, 1 = B, etc.)
	 */
	public $posters = array();
	
	/**
	 * An array of information about previously fetched replies, with the reply ID as key and the value an array of:
	 * 'body'          => The reply body.
	 * 'author'        => The reply author's UID.
	 * 'name'          => The reply author's name.
	 * 'trip'          => The reply author's tripcode.
	 * 'poster_number' => The poster number of the reply author. (See explanation for $this->posters).
	 * 'post_number'   => The first reply would be 1, the second 2, etc.
	 * 'deleted'       => True. Only set when the reply has been deleted.
	 * 'hidden'        => True. Only set when the reply has been hidden (ostrich mode).
	 */
	public $history = array();
	
	/**
	 * An array of former OPs from topics that have been merged into this topic. The key is the original ID of the
	 * merged topic, and the value is the reply ID that it was moved to.
	 */
	public $merges = array();
	
	/* Incremented every time a new poster joins the topic. */
	private $poster_number = 1;
	
	/* The unix timestamp of the previously fetched post. */
	public $previous_time;
	
	/* The UID who authored the previously fetched post. */
	public $previous_author;
	
	/* The ID of the previous fetched reply. */
	public $previous_id = false;
	
	/* If the current user has posted in this topic, their last name or tripcode, or letter if anonymous. */
	public $your_name;
	
	/* Set properties and fetch the OP and poll. */
	public function __construct($id, $page) {
		global $db;
		
		$this->db = $db;
		$this->id = (int) $id;
		$this->page = (int) $page;
		
		if($_SESSION['settings']['posts_per_page'] && $this->page) {
			$this->page_start += $_SESSION['settings']['posts_per_page'] * ($this->page - 1);
		}
		
		$this->page_end = $this->page_start + $_SESSION['settings']['posts_per_page'];

		
		/* Fetch OP and topic metadata. */
		$this->db->select
		(
			't.time, t.author, t.visits, t.replies, t.headline, t.body, 
			t.edit_time, t.edit_mod, t.namefag, t.tripfag, t.link, t.deleted, 
			t.sticky, t.locked, t.poll, t.poll_hide, t.last_post, t.imgur'
		)
		->from('topics t')
		->where('id = ?', $this->id);
		
		/* No point joining the images table if images are disabled. */
		if(ALLOW_IMAGES) {
			$this->db->select('i.file_name, i.original_name, i.md5, i.deleted AS image_deleted')
			         ->join('images i', 't.id = i.topic_id');
		}
		
		$res = $this->db->exec();
		$this->OP = $res->fetchObject();
		
		if( ! $this->OP) {
			throw new Exception('There is no topic with that ID.');
		}
		
		$this->OP->image_ignored = $this->OP->file_name && ($_SESSION['settings']['text_mode'] || is_ignored($this->OP->md5));
		
		$this->last_post = $this->OP->last_post;
		$this->reply_count = (int) $this->OP->replies;
		
		$this->previous_time = $this->OP->time;
		$this->previous_author = $this->OP->author;
		$this->posters[$this->OP->author] = array('number' => 0);
		
		/* Initalize the topic visits array for new visitors. */
		if( ! isset($_SESSION['topic_visits'])) {
			$_SESSION['topic_visits'] = array();
		}

		/* Last time we viewed this thread, the reply count was: */
		$this->last_read_post = isset($_SESSION['topic_visits'][$this->id]) ? $_SESSION['topic_visits'][$this->id] : 0;

		/* Increment visit count. */
		if ( ! isset($_SESSION['topic_visits'][$this->id]) && isset($_COOKIE['SID'])) {
			$this->db->q('UPDATE topics SET visits = visits + 1 WHERE id = ?', $this->id);
		}
		
		$this->locked = $this->OP->locked;
		/* Automatically lock the topic after so many seconds from the last reply. */
		if(AUTOLOCK && ($_SERVER['REQUEST_TIME'] - $this->last_post) > AUTOLOCK && $this->OP->author != $_SESSION['UID']) {
			$this->locked = true;
		}
		
		/* Get poll data. */
		if($this->OP->poll) {
			$this->poll_hide = (bool) $this->OP->poll_hide;
			$this->set_poll();
		}
		
		/* Check if the current user is watching this topic. */
		if(isset($_SESSION['ID_activated']) && $_SESSION['ID_activated']) {
			/**
			 * If the topic is watched, either '0' or '1' will be returned. '0' = no new replies, '1' = unread replies.
			 * False (bool) will be returned if the topic is not watched.
			 */
			$res = $this->db->q('SELECT new_replies FROM watchlists WHERE topic_id = ? AND uid = ? LIMIT 1', $this->id, $_SESSION['UID']);
			$res = $res->fetchColumn();
			
			$this->watched = ($res !== false);
			$this->watched_new = (bool) $res;
		}
		
		/* If the topic was deleted, fetch the log. */
		if($this->OP->deleted) {
			list($this->OP->deleted_by, $this->OP->deleted_at, $this->OP->delete_reason) = $this->get_mod_log('delete_topic', $this->id);
		}
		
		/* If the image was removed, fetch the log. */
		if($this->OP->image_deleted) {
			$this->OP->file_name = false;
			list($this->OP->image_deleted_by, $this->OP->image_deleted_at, $this->OP->image_delete_reason) = $this->get_mod_log('delete_image', $this->id);
		}
		
		/* If the topic was edited by a mod, fetch the log. */
		if($this->OP->edit_mod) {
			list($this->OP->edited_by, /* unneeded */, $this->OP->edit_reason) = $this->get_mod_log('edit_topic', $this->id);
		}
	}
	
	/* Once we've fetched all the replies, make sure that the reply count and last post time on record are correct. */
	public function __destruct() {
		if( ! $this->stopped_prematurely && ($this->reply_recount != $this->reply_count || $this->previous_time != $this->last_post)) {
			/* The DB's reply count or last bump time is inaccurate. Fix. */
			$this->db->q('UPDATE topics SET replies = ?, last_post = ? WHERE id = ?', $this->reply_recount, $this->previous_time, $this->id);
			$this->reply_count = $this->reply_recount;
		}
		/* Remember the reply count so we can check for new posts. */
		if ( ! isset($_SESSION['topic_visits'][$this->id]) || $this->last_read_post !== $this->reply_count) {
			/* Prepend the array with this topic. */
			$_SESSION['topic_visits'] = array(
				$this->id => $this->reply_count
			) + $_SESSION['topic_visits'];
			/* Limit the number of remembered topics */
			$_SESSION['topic_visits'] = array_slice($_SESSION['topic_visits'], 0, MEMORABLE_TOPICS, true);
			/* Update topic visits in the DB (and last_seen while we're there) */
			$this->db->q('UPDATE users SET topic_visits = ?, last_seen = ? WHERE uid = ? LIMIT 1', json_encode($_SESSION['topic_visits']), $_SERVER['REQUEST_TIME'], $_SESSION['UID']);
		}
	}
	
	/**
	 * Fetches the log for a given mod action. 
	 * @param  string  $action  The mod action name (e.g., 'delete_topic')
	 * @param  int     $id      The ID affected by the action (e.g., the topic ID)
	 * @return  If a log is found, a numerical array with the mod UID, action time, and reason.
	 *          If no log is found, an array with three false elements.
	 */
	private function get_mod_log($action, $id) {
		$res = $this->db->q
		(
			"SELECT mod_uid, time, reason
			FROM mod_actions 
			WHERE `action` = ? AND `target` = ? 
			LIMIT 1", 
			$action, $id
		);
			
		$res = $res->fetch(PDO::FETCH_NUM);
		
		if(is_array($res)) {
			return $res;
		} 
		
		return array(false, false, false);

	}
	
	/* Fetches the poll and sets poll-related properties. */
	private function set_poll() {
		$check_votes = $this->db->q
		(
			'SELECT 1, option_id 
			FROM poll_votes
			WHERE uid = ? AND parent_id = ? 
			LIMIT 1', 
			$_SESSION['UID'], $this->id
		);
		
		list($this->voted, $this->chosen_option) = $check_votes->fetch(PDO::FETCH_NUM);	

		$options = $this->db->q
		(
			'SELECT poll_options.id, poll_options.option, poll_options.votes 
			FROM poll_options 
			WHERE poll_options.parent_id = ?', 
			$this->id
		);
		
		while($option = $options->fetch(PDO::FETCH_ASSOC)) {
			$this->poll_options[ $option['id'] ] = array
			(
				'text'  => $option['option'],
				'votes' => $option['votes']
			);
			
			$this->poll_votes += $option['votes'];
		}
	}
	
	/**
	 * Fetches the next reply, parsing it for mark-up and citations.
	 *
	 * @return  If the reply is deleted, hidden or on a previous page, the string 'skip'. Otherwise, an object
	 *          with the selected DB columns (id, time, body, etc.) as properties, plus the following:
	 *          'parsed_body'   : The reply body, escaped and parsed for mark-up and citations.
	 *          'image_ignored' : Is the image hidden by ostrich mode?
	 *          'joined_in'     : Is this the author's first post in this topic?
	 *          'deleted_by'    : If the reply is deleted, the UID of the mod responsible.
	 *          'deleted_at'    : If the reply is deleted, the deletion time as a unix timestamp.
	 *          'delete_reason' : If the reply is deleted, the reason given for deletion in the mod logs.
	 *          'poster_number' : The number of posters who joined the topic before the reply author.
	 *          'edited_by'     : If the reply was edited by a mod, their UID.
	 *          'edit_reason'   : If the reply was edited by a mod, the reason.
	 *          'image_deleted_at'    : If the image was removed, the deletion time.
	 *          'image_deleted_by'    : If the image was removed, the UID of the mod responsible.
	 *          'image_delete_reason' : If the image was removed, the reason.
	 *          'first_post_number_by_author' : The post number (OP = 0, reply 1 = 1, and so on) of the author's first post in this topic.
	 */
	public function get_reply() {
		global $perm;
		
		/* If we haven't fetched any replies yet, prepare the query. */
		if( ! isset($this->reply_handle)) {
			$this->db->select
			(
				'r.id, r.time, r.author, r.body, r.deleted, r.edit_time, 
				r.edit_mod, r.namefag, r.tripfag, r.link, r.imgur, r.original_parent
			')
			->from('replies r')
			->where('r.parent_id = ?', $this->id)
			->order_by('r.time');
			if (ALLOW_IMAGES) {
				$this->db->select('i.file_name, i.original_name, i.md5, i.deleted AS image_deleted')
				         ->join('images i', 'r.id = i.reply_id');
			}
			
			$this->reply_handle = $this->db->exec();
		}
		
		$reply = $this->reply_handle->fetchObject();

		if( ! $reply) {
			return false;
		}
		
		/* Store information about this reply. */
		$reply->image_ignored = $reply->file_name && ($_SESSION['settings']['text_mode'] || is_ignored($reply->md5));		
		$reply->joined_in = ! isset($this->posters[$reply->author]);
		
		if($reply->joined_in) {
			$this->posters[$reply->author] = array
			(
				'first_reply' => $reply->id,
				'number'      => $this->poster_number++
			);
		}
		
		$this->history[$reply->id] = array
		(
			'body'          => $reply->body,
			'author'        => $reply->author,
			'name'          => $reply->namefag,
			'trip'          => $reply->tripfag,
			'poster_number' => $this->posters[$reply->author]['number'],
			'post_number'   => $this->reply_recount + 1 # no incrementation here in case post is deleted
		);
		
		$reply->poster_number = $this->posters[$reply->author]['number'];
		
		if($reply->author == $this->OP->author) {
			$reply->first_post_number_by_author = 0;
		} else {
			$reply->first_post_number_by_author = $this->history[ $this->posters[$reply->author]['first_reply'] ]['post_number'];
		}
		
		if($reply->author == $_SESSION['UID']) {
			if($reply->namefag) {
				$this->your_name = $reply->namefag;
			} else if($reply->tripfag) {
				$this->your_name = $reply->tripfag;
			} else {
				$this->your_name = number_to_letter($this->posters[$reply->author]['number']);
			}
		}
		
		/**
		 * If this reply is deleted, ignored, or on a previous page, we'll skip its output. To do this, we
		 * return 'skip' as a string, which instructs topic.php to 'continue' the loop. Recursion would be
		 * a nicer solution (return $this->get_reply()), but that has memory issues and can trigger xdebug's
		 * function nesting limit (when skipping 100+ posts). The real solution would of course be to iterate
		 * over every reply before outputting any, which we'll probably do later.
		 */
		 
		/* Skip if deleted and we don't have permission to view */
		if($reply->deleted) {
			$this->history[$reply->id]['deleted'] = true;
			if($reply->author != $_SESSION['UID'] && ! $perm->get('undelete')) {
				return 'skip';
			}
		} else {
			$this->reply_recount++;
		}
		
		/* Skip if on ignore list (ostrich mode) */
		if(is_ignored($reply->body, $reply->namefag, $reply->tripfag)) {
			$this->history[$reply->id]['hidden'] = true;
			return 'skip';
		}
		
		/* Skip if it doesn't belong on this page of the topic */
		if($_SESSION['settings']['posts_per_page'] && $this->page) {
			if($this->page > 1 && $this->reply_recount < $this->page_start) {
				return 'skip';
			} else if($this->reply_recount == $this->page_end) {
				$this->stopped_prematurely = true;
				return false;
			}
		}
		
		/* Parse the body. */
		$reply->parsed_body = parser::parse($reply->body, $reply->author);
		
		/* Linkify citations */
		$reply->parsed_body = str_ireplace('@OP', '<span class="unimportant poster_number_0"><a href="#OP">@OP</a></span>', $reply->parsed_body);

		$citation_count = preg_match_all('/@([0-9,]+)/m', $reply->parsed_body, $citations);
		$citations = (array) $citations[1];
		
		/* If this is a merged reply that contains no citations, add a citation to the original parent. */
		if( ! $citations && $reply->original_parent && isset($this->merges[$reply->original_parent])) {
			$merge_citation = number_format($this->merges[$reply->original_parent]);
			$reply->parsed_body = '@' . $merge_citation . '<br />' . $reply->parsed_body;
			$citations[] = $merge_citation;
		}

		if($citation_count > 1) {
			/* Replace each citation only once (preventing memory attacks). */
			$citations = array_unique($citations);
		}
		
		foreach($citations as $citation) {
			$pure_id = str_replace(',', '', $citation);
			
			/* The text that appears next to a citation */
			if ($this->history[$pure_id]['author'] == $_SESSION['UID']) {
				$cited_name = '<em class="you">(you)</em>';
			} else if($this->history[$pure_id]['name']) {
				$cited_name = '(' . trim(htmlspecialchars($this->history[$pure_id]['name'])) . ')';
			} else if($this->history[$pure_id]['trip']) {
				$cited_name = '(' . trim($this->history[$pure_id]['trip']) . ')';
			} else {
				$cited_name = '(<strong>' . number_to_letter($this->history[$pure_id]['poster_number']) . '</strong>)';
			}
			$cited_name = ' <span class="citee">' . $cited_name . '</span>';
			
			if( ! isset($this->history[$pure_id])) {
				/* Non-existent reply */
				$link = '<span class="unimportant help" title="' . $citation. '">(Citing a non-existent reply.)</span>';
			} else if(isset($this->history[$pure_id]['deleted']) && $this->history[$pure_id]['author'] != $_SESSION['UID'] && ! $perm->get('undelete')) {
				/* Deleted reply */
				$link = '<span class="unimportant help" title="@' . $citation . '">@deleted' . $cited_name .'</span>';
			} else if(isset($this->history[$pure_id]['hidden'])) {
				/* Hidden reply (ostrich mode) */
				$link = '<span class="unimportant help" title="' . parser::snippet($this->history[$pure_id]['body']) . '">@hidden' . $cited_name . '</span>';
			} else {
				/* Normal citation */
				if($pure_id == $this->previous_id) {
					$link_text = 'previous';
				} else {
					$link_text = $citation;
				}
				
				$page_link = '';
				if($_SESSION['settings']['posts_per_page'] && $this->page) {
					$page_link = DIR . 'topic/' . $this->id . page($this->reply_count, $this->history[$pure_id]['post_number']);
				}
							
				$link = '<span class="unimportant poster_number_'.$this->history[$pure_id]['poster_number'].'"><a href="' . $page_link . '#reply_' . $pure_id . '" onclick="createSnapbackLink(\'' . $reply->id . '\'); highlightReply(\'' . $pure_id . '\');" class="help" title="' . parser::snippet($this->history[$pure_id]['body']) . '">@' . $link_text . '</a>' . $cited_name . '</span>';
			}
			
			$reply->parsed_body = str_replace('@' . $citation, $link, $reply->parsed_body);
		}
		
		/* If deleted, fetch the log. */
		if($reply->deleted) {			
			list($reply->deleted_by, $reply->deleted_at, $reply->delete_reason) = $this->get_mod_log('delete_reply', $reply->id);
		}
		
		/* If the image was removed, fetch the log. */
		if($reply->image_deleted) {
			$reply->file_name = false;
			list($reply->image_deleted_by, $reply->image_deleted_at, $reply->image_delete_reason) = $this->get_mod_log('delete_image', $reply->id);
		}
		
		/* If the reply was edited by a mod, fetch the log. */
		if($reply->edit_mod) {
			list($reply->edited_by, /* unneeded */, $reply->edit_reason) = $this->get_mod_log('edit_reply', $reply->id);
		}

		
		return $reply;
	}
	
	/* Deletes citation notifications for this topic. */
	public function clear_citations() {
		$res = $this->db->q('DELETE FROM citations WHERE uid = ? AND topic = ?', $_SESSION['UID'], $this->id);
		return $res->rowCount();
	}
	
	/* Marks new replies as "read" on the user's watchlist. */
	public function clear_watchlist() {
		$res = $this->db->q('UPDATE watchlists SET new_replies = 0 WHERE topic_id = ? AND uid = ? LIMIT 1', $this->id, $_SESSION['UID']);
		return $res->rowCount();
	}
	
	/* Prints page navigation. */
	public function print_pages() {
		if($_SESSION['settings']['posts_per_page'] && $this->page && $this->reply_count > $_SESSION['settings']['posts_per_page']) {
			$pages = ceil($this->reply_count / $_SESSION['settings']['posts_per_page']);
			echo '<div class="topic_pages">';
			if($this->page > 1) {
				$prev = $this->page - 1;
				echo '<span class="topic_page"><a href="'.DIR.'topic/' . $this->id . '/' . $prev . '">«</a></span>';
			}
			for($i = 1; $i <= $pages; ++$i) {
				if($i == $this->page) {
					echo '<span class="topic_page current_page">' . number_format($i) . '</span> ';
				} else {
					echo '<span class="topic_page"><a href="'.DIR.'topic/' . $this->id . '/' . $i . '">' . number_format($i) . '</a></span> ';
				}
			}
			if($this->page < $pages) {
				$next = $_GET['page'] + 1;
				echo '<span class="topic_page"><a href="'.DIR.'topic/' . $this->id . '/' . $next . '">»</a></span>';
			}
			echo '<span class="topic_page all_pages"><a href="'.DIR.'topic/' . $this->id . '">All</a></span></div>';
		}
	}
	
	
	/* Formats a post body for JavaScript quick-quoting. */
	public function encode_quote($body) {
		$body = trim(preg_replace('/^@([0-9,]+|OP)/m', '', $body));
		$body = preg_replace('/^/m', '> ', $body);
		return rawurlencode($body);
	}

}

?>