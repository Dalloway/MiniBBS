<?php
require './includes/bootstrap.php';

if( ! empty($_POST['phrase'])) {
	header('Location: ' . URL . 'search/' . urlencode($_POST['phrase']));
	exit;
}

force_id();
update_activity('search');
$template->title = 'Search';
$template->onload = 'focusId(\'phrase\'); init();';
$page = new Paginate();

if( ! empty($_GET['q'])) {
	$search_query = addcslashes(trim($_GET['q']), '%_');
	
	$res = $db->q('SELECT COUNT(*) FROM search_log WHERE ip_address = ? AND time > (? - 60)', $_SERVER['REMOTE_ADDR'], $_SERVER['REQUEST_TIME']);
	if($res->fetchColumn() > RECAPTCHA_MAX_SEARCHES_PER_MIN) {
		if(show_captcha('You\'re searching too quickly.')) {
			$db->q('DELETE FROM search_log WHERE ip_address = ?', $_SERVER['REMOTE_ADDR']);
		}
	}
	
	if ($search_query === '') {
		error::add('Your must enter a search term.');
	} else if(strlen($search_query) > 255) {
		error::add('Your query must be shorter than 256 characters.');
	}

	if(error::valid()) {
		$db->q('INSERT INTO search_log (ip_address, time) VALUES (?, ?)', $_SERVER['REMOTE_ADDR'], $_SERVER['REQUEST_TIME']);
		
		$query_sql = '%'.$search_query.'%';
		
		$topic_results = $db->q
		(
			'SELECT id, time, replies, visits, poll, locked, sticky, namefag, tripfag, headline, body 
			FROM topics 
			WHERE (body LIKE ? OR headline LIKE ?) AND deleted = 0
			ORDER BY time DESC
			LIMIT ' . $page->offset . ', ' . $page->limit, 
			$query_sql, $query_sql 
		);
		
		$reply_results = $db->q
		(
			'SELECT replies.id, replies.parent_id, replies.time, replies.body, replies.namefag, replies.tripfag, topics.headline
			FROM replies
			INNER JOIN topics ON replies.parent_id = topics.id
			WHERE replies.body LIKE ? AND topics.deleted = 0 AND replies.deleted = 0
			ORDER BY replies.time DESC
			LIMIT ' . $page->offset . ', ' . $page->limit, 
			$query_sql
		);
	}
	
	error::output();
}

?>
<p><?php echo m('Search: Help') ?></p>

<form action="" method="post">
	<div class="row">
		<input id="phrase" name="phrase" type="text" size="80" maxlength="255" value="<?php echo htmlspecialchars($_GET['q']) ?>" class="inline" />
		<input type="submit" value="Search" class="inline" />
	</div>
</form>
<?php

if(isset($topic_results)) {
	$search_query_p = preg_quote($search_query);

	/* Topic results */
	$columns = array
	(
		'Headline',
		'Snippet',
		'Author',
		'Replies',
		'Age ▼'
	);
	if( ! $_SESSION['settings']['celebrity_mode']) {	
		unset($columns[2]);
	}	
	
	$table = new Table($columns, 0);
	$table->add_td_class(0, 'topic_headline');
	$table->add_td_class(1, 'snippet');
	
	while( $topic = $topic_results->fetchObject() ) {
		/* If the search query is too far into the body for it to appear in the snippet, trim the body. */
		$query_pos = stripos($topic->body, $search_query);
		if($query_pos > 40) {
			$topic->body = '…' . substr($topic->body, $query_pos - 30);
		}
		$snippet = parser::snippet($topic->body, 120);
		
		$headline = preg_replace('/(' .$search_query_p . ')/i', '<em class="marked">$1</em>', htmlspecialchars($topic->headline));
		if($query_pos !== false) {
			$snippet = preg_replace('/(' . $search_query_p . ')/i', '<em class="marked">$1</em>', $snippet);
		}
				
		$values = array
		(
			format_headline($headline, $topic->id, $topic->replies, $topic->poll, $topic->locked, $topic->sticky),
			$snippet,
			format_name($topic->namefag, $topic->tripfag, null, null, true),
			replies($topic->id, $topic->replies),
			'<span class="help" title="' . format_date($topic->time) . '">' . age($topic->time) . '</span>'
		);
		if( ! $_SESSION['settings']['celebrity_mode']) {	
			unset($values[2]);
		}

		
		$table->row($values);
	}
	
	$topic_count = $table->row_count;
	if($topic_count) {
		echo '<h4 class="section">Topics</h4>';
		$table->output();
	}
	
	
	/* Reply results */
	$columns = array
	(
		'Reply snippet',
		'Topic',
		'Author',
		'Age ▼'
	);
	if( ! $_SESSION['settings']['celebrity_mode']) {	
		unset($columns[2]);
	}

	
	$table = new Table($columns, 1);
	$table->add_td_class(0, 'snippet');
	$table->add_td_class(1, 'topic_headline');
	
	while( $reply = $reply_results->fetchObject() ) {
		$query_pos = stripos($reply->body, $search_query);
		if($query_pos > 40 && strlen($reply->body) > 90) {
			$reply->body = '…' . substr($reply->body, $query_pos - 30);
		}
		$snippet = parser::snippet($reply->body, 120);
		
		$headline = '<a href="'.DIR.'topic/'.$reply->parent_id.'">' . htmlspecialchars($reply->headline) . '</a>';
		$headline = preg_replace('/(' .$search_query_p . ')/i', '<em class="marked">$1</em>', $headline);
		if($query_pos !== false) {
			$snippet = preg_replace('/(' . $search_query_p . ')/i', '<em class="marked">$1</em>', $snippet);
		}
		$snippet = '<a href="'.DIR.'topic/'.$reply->parent_id.'#reply_'.$reply->id.'">' . $snippet . '</a>';
				
		$values = array
		(
			$snippet,
			$headline,
			format_name($reply->namefag, $reply->tripfag, null, null, true),
			'<span class="help" title="' . format_date($reply->time) . '">' . age($reply->time) . '</span>'
		);
		
		if( ! $_SESSION['settings']['celebrity_mode']) {	
			unset($values[2]);
		}

		
		$table->row($values);
	}
	
	$reply_count = $table->row_count;
	if($reply_count) {
		echo '<h4 class="section">Replies</h4>';
		$table->output();
	}
	
	if($topic_count + $reply_count == 0) {
		echo '<p>' . m('Search: No results') . '</p>';
	}
}
$page->navigation('search/' . urlencode($search_query), max(array($topic_count, $reply_count)));

$template->render();
?>