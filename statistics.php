<?php
require './includes/bootstrap.php';
force_id();
update_activity('statistics');
$template->title = 'Statistics';

$statistics = array();

$query = array();
$res = $db->q('SELECT count(*) FROM topics WHERE deleted = 0');
$num_topics = $res->fetchColumn();
$res = $db->q('SELECT count(*) FROM replies WHERE deleted = 0');
$num_replies = $res->fetchColumn();
$res = $db->q('SELECT count(*) FROM topics WHERE author = ? AND deleted = 0', $_SESSION['UID']);
$your_topics = $res->fetchColumn();
$res = $db->q('SELECT count(*) FROM replies WHERE author = ? AND deleted = 0', $_SESSION['UID']);
$your_replies = $res->fetchColumn();
$res = $db->q('SELECT count(*) FROM bans');
$num_bans = $res->fetchColumn();

$replies_per_topic = round($num_replies / $num_topics);
$your_posts = $your_topics + $your_replies;
$total_posts = $num_topics + $num_replies; 
$days_since_start = floor(( $_SERVER['REQUEST_TIME'] - SITE_FOUNDED ) / 86400);
if ($days_since_start == 0) {
	$days_since_start = 1;
}
$posts_per_day = round($total_posts / $days_since_start);
$topics_per_day = round($num_topics / $days_since_start);
$replies_per_day = round($num_replies / $days_since_start);
?>
<table>
	<tr>
		<th></th>
		<th class="minimal">Amount</th>
		<th>Comment</th>
	</tr>
	<tr class="odd">
		<th class="minimal">Total existing posts</th>
		<td class="minimal"><?php echo format_number($total_posts) ?></td>
		<td>-</td>
	</tr>
	<tr>
		<th class="minimal">Existing topics</th>
		<td class="minimal"><?php echo format_number($num_topics) ?></td>
		<td>-</td>
	</tr>
	<tr class="odd">
		<th class="minimal">Existing replies</th>
		<td class="minimal"><?php echo format_number($num_replies) ?></td>
		<td>That's ~<?php echo $replies_per_topic ?> replies/topic.</td>
	</tr>
	<tr>
		<th class="minimal">Posts/day</th>
		<td class="minimal">~<?php echo format_number($posts_per_day) ?></td>
		<td>-</td>
	</tr>
	<tr class="odd">
		<th class="minimal">Topics/day</th>
		<td class="minimal">~<?php echo format_number($topics_per_day) ?></td>
		<td>-</td>
	</tr>
	<tr>
		<th class="minimal">Replies/day</th>
		<td class="minimal">~<?php echo format_number($replies_per_day) ?></td>
		<td>-</td>
	</tr>
	<tr class="odd">
		<th class="minimal">Active bans</th>
		<td class="minimal"><?php echo format_number($num_bans) ?></td>
		<td>-</td>
	</tr>
	<tr class="odd">
		<th class="minimal">Days since launch</th>
		<td class="minimal"><?php echo number_format($days_since_start) ?></td>
		<td>Went live on <?php echo date('Y-m-d', SITE_FOUNDED) . ', ' . age(SITE_FOUNDED) ?> ago.</td>
	</tr>
</table>
<table>
	<tr>
		<th></th>
		<th>Amount</th>
	</tr>
	<tr class="odd">
		<th class="minimal">Total posts by you</th>
		<td><?php echo format_number($your_posts) ?></td>
	</tr>
	<tr>
		<th class="minimal">Topics started by you</th>
		<td><?php echo format_number($your_topics) ?></td>
	</tr>
	<tr class="odd">
		<th class="minimal">Replies by you</th>
		<td><?php echo format_number($your_replies) ?></td>
	</tr>
</table>
<?php
$template->render();
?>