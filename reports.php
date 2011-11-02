<?php
require './includes/bootstrap.php';
force_id();

if( ! $perm->get('handle_reports')) {
	error::fatal(MESSAGE_ACCESS_DENIED);
}

if(isset($_POST['dismiss_all'])) {
	$db->q('DELETE FROM reports');
	redirect('All reports dismissed.', '');
}

$template->title = 'Reported posts';

/* Topics */
$res = $db->q
(
	"SELECT 
	reports.reason, reports.reporter, 
	topics.id, topics.headline, topics.body, topics.author, topics.namefag, topics.tripfag, topics.link, topics.locked, topics.replies,
	images.file_name
	FROM reports 
	INNER JOIN topics ON reports.post_id = topics.id 
	LEFT OUTER JOIN images ON topics.id = images.topic_id
	WHERE reports.type = 'topic'
	ORDER BY topics.id"
);

while( $topic = $res->fetchObject() ):
	/* If this topic was already reported, we won't output the post again, just the reason. */
	if( ! isset($previous_topic) || $previous_topic != $topic->id):
?>
		</div>
		
		<h3>Topic: <strong><a href="<?php echo DIR ?>topic/<?php echo $topic->id ?>"><?php echo htmlspecialchars($topic->headline) ?></a></strong> by <?php echo format_name($topic->namefag, $topic->tripfag, $topic->link, 0) ?> <span class="reply_id unimportant"><a href="<?php echo DIR ?>topic/<?php echo $topic->id ?>">#<?php echo number_format($topic->id) ?></a></span></h3>

		<div class="body">
		<?php
		if($topic->file_name):
		?>
			<a href="<?php echo DIR ?>img/<?php echo htmlspecialchars($topic->file_name) ?>" class="thickbox">
				<img src="<?php echo DIR ?>thumbs/<?php echo htmlspecialchars($topic->file_name) ?>" alt="" />
			</a>
		<?php
		endif;
		echo parser::parse($topic->body);
		?>

			<ul class="menu">
		<?php
		if($perm->get('edit_others')):
		?>
				<li><a href="<?php echo DIR ?>edit_topic/<?php echo $topic->id ?>">Edit</a></li>
		<?php
		endif;

		if($perm->get('view_profile')):
		?>
				<li><a href="<?php echo DIR ?>profile/<?php echo $topic->author ?>">Profile</a></li>
				<li><a href="<?php echo DIR ?>compose_message/<?php echo $topic->author ?>">PM</a></li>
		<?php
		endif;
		
		if($perm->get('lock') && ! $topic->locked):
		?>
				<li><a href="<?php echo DIR ?>lock_topic/<?php echo $topic->id ?>" onclick="return quickAction(this, 'Really lock this topic?');">Lock</a></li>
		<?php
		endif;
		
		if($perm->get('delete')):
		?>
				<li><a href="<?php echo DIR ?>delete_topic/<?php echo $topic->id ?>" onclick="return quickAction(this, 'Really delete this topic?');">Delete</a></li>
		<?php
			if($topic->file_name):
		?>
				<li><a href="<?php echo DIR ?>delete_image/<?php echo $topic->id ?>" onclick="return quickAction(this, 'Really delete this image?');">Delete image</a></li>
		<?php
			endif;
		endif;
		?>
				<li><?php echo $topic->replies . ' repl' . ($topic->replies == 1 ? 'y' : 'ies') ?></li>
			</ul>
	<?php
	endif;
	?>
		
	<div class="report_reason">Reported by <a href="<?php echo DIR . 'profile/' . $topic->reporter?>"><?php echo $topic->reporter ?></a> (<a href="<?php echo DIR . 'compose_message/' . $topic->reporter?>">PM</a>)<?php if( ! empty($topic->reason)) echo ': <strong>' . parser::parse($topic->reason) . '</strong>' ?></div>
<?php
	$previous_topic = $topic->id;
endwhile;

/* We keep our post divs open to allow multiple reasons, so... */
echo '</div>';

/* Replies */
$res = $db->q
(
	"SELECT 
	reports.reason, reports.reporter, 
	replies.id, replies.parent_id, replies.body, replies.author, replies.namefag, replies.tripfag, replies.link,
	images.file_name
	FROM reports 
	INNER JOIN replies ON reports.post_id = replies.id 
	LEFT OUTER JOIN images ON replies.id = images.reply_id
	WHERE reports.type = 'reply'
	ORDER BY replies.id"
);

while( $reply = $res->fetchObject() ):
	if( ! isset($previous_reply) || $previous_reply != $reply->id):
?>
		</div>
		
		<h3><a href="<?php echo DIR ?>topic/<?php echo $reply->parent_id ?>#reply_<?php echo $reply->id ?>">Reply</a> by <?php echo format_name($reply->namefag, $reply->tripfag, $reply->link) ?> <span class="reply_id unimportant"><a href="<?php echo DIR ?>topic/<?php echo $reply->parent_id ?>#reply_<?php echo $reply->id ?>">#<?php echo number_format($reply->id) ?></a></span></h3>

		<div class="body">
		<?php
		if($reply->file_name):
		?>
			<a href="<?php echo DIR ?>img/<?php echo htmlspecialchars($reply->file_name) ?>" class="thickbox">
				<img src="<?php echo DIR ?>thumbs/<?php echo htmlspecialchars($reply->file_name) ?>" alt="" />
			</a>
		<?php
		endif;
		$reply->body = parser::parse($reply->body);
		$reply->body = preg_replace('/^@([0-9]+|OP),?([0-9]+)?/m', '<span class="unimportant"><a href="'.DIR.'topic/'.$reply->parent_id.'#reply_$1$2">$0</a></span>', $reply->body);
		echo $reply->body;
		?>

			<ul class="menu">
		<?php
		if($perm->get('edit_others')):
		?>
				<li><a href="<?php echo DIR ?>edit_reply/<?php echo $reply->parent_id . '/' . $reply->id ?>">Edit</a></li>
		<?php
		endif;

		if($perm->get('view_profile')):
		?>
				<li><a href="<?php echo DIR ?>profile/<?php echo $reply->author ?>">Profile</a></li>
				<li><a href="<?php echo DIR ?>compose_message/<?php echo $reply->author ?>">PM</a></li>
		<?php
		endif;
		
		if($perm->get('delete')):
		?>
				<li><a href="<?php echo DIR ?>delete_reply/<?php echo $reply->id ?>" onclick="return quickAction(this, 'Really delete this reply?');">Delete</a></li>
		<?php
			if($reply->file_name):
		?>
				<li><a href="<?php echo DIR ?>delete_image/<?php echo $reply->parent_id . '/' . $reply->id ?>" onclick="return quickAction(this, 'Really delete this image?');">Delete image</a></li>
		<?php
			endif;
		endif;
		?>
			</ul>
	<?php
	endif;
	?>
		
	<div class="report_reason">Reported by <a href="<?php echo DIR . 'profile/' . $reply->reporter?>"><?php echo $reply->reporter ?></a> (<a href="<?php echo DIR . 'compose_message/' . $reply->reporter?>">PM</a>)<?php if( ! empty($reply->reason)) echo ': <strong>' . parser::parse($reply->reason) . '</strong>' ?></div>
	<?php
	$previous_reply = $reply->id;
endwhile;
?>
</div>

<div class="row">
	<form action="" method="post">
		<input type="submit" name="dismiss_all" value="Dismiss all" />
	</form>
</div>

<?php
$template->render();
?>