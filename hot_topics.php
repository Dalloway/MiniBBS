<?php
require './includes/bootstrap.php';
update_activity('hot_topics', 1);
$template->title = 'Hot topics';
$all_time = $db->q('SELECT headline, id, time, replies FROM topics WHERE deleted = 0 ORDER BY replies DESC LIMIT 50', $_SERVER['REQUEST_TIME']);
$last_hour = $db->q('SELECT headline, id, time, replies FROM topics WHERE time > (? - 3600) AND deleted = 0  ORDER BY replies DESC LIMIT 10', $_SERVER['REQUEST_TIME']);
$last_24_hours = $db->q('SELECT headline, id, time, replies FROM topics WHERE time > (? - 86400) AND deleted = 0 ORDER BY replies DESC LIMIT 10', $_SERVER['REQUEST_TIME']);
$this_week = $db->q('SELECT headline, id, time, replies FROM topics WHERE time > (? - 604800) AND deleted = 0 ORDER BY replies DESC LIMIT 10', $_SERVER['REQUEST_TIME']);
$this_month = $db->q('SELECT headline, id, time, replies FROM topics WHERE time > (? - 2629743) AND deleted = 0 ORDER BY replies DESC LIMIT 10', $_SERVER['REQUEST_TIME']);
?>

<div style="float: left; width: 50%;">
	<h2>Last hour:</h2>
	<ol>
<?php
	while (list($hot_headline, $hot_id, $hot_time, $hot_replies) = $last_hour->fetch()):
?>
		<li><a href="<?php echo DIR ?>topic/<?php echo $hot_id ?>"><?php echo htmlspecialchars($hot_headline) ?></a> (<?php echo number_format($hot_replies) ?>)</li>
<?php
	endwhile;
?>
	</ol>
	
	<h2>Last 24 hours:</h2>
	<ol>
<?php
	while (list($hot_headline, $hot_id, $hot_time, $hot_replies) = $last_24_hours->fetch()):
?>
		<li><a href="<?php echo DIR ?>topic/<?php echo $hot_id ?>"><?php echo htmlspecialchars($hot_headline) ?></a> (<?php echo number_format($hot_replies) ?>)</li>
<?php
	endwhile;
?>
	</ol>
	
	<h2>This week:</h2>
	<ol>
<?php
	while (list($hot_headline, $hot_id, $hot_time, $hot_replies) = $this_week->fetch()):
?>
		<li><a href="<?php echo DIR ?>topic/<?php echo $hot_id ?>"><?php echo htmlspecialchars($hot_headline) ?></a> (<?php echo number_format($hot_replies) ?>)</li>
<?php
	endwhile;
?>
	</ol>
	
	<h2>This month:</h2>
	<ol>
<?php
	while (list($hot_headline, $hot_id, $hot_time, $hot_replies) = $this_month->fetch()):
?>
		<li><a href="<?php echo DIR ?>topic/<?php echo $hot_id ?>"><?php echo htmlspecialchars($hot_headline) ?></a> (<?php echo number_format($hot_replies) ?>)</li>
<?php
	endwhile;
?>
	</ol>
</div>
<div style="float: right; width: 50%;">
	<h2>All time:</h2>
	<ol>
<?php
	while (list($hot_headline, $hot_id, $hot_time, $hot_replies) = $all_time->fetch()):
?>
		<li><a href="<?php echo DIR ?>topic/<?php echo $hot_id ?>"><?php echo htmlspecialchars($hot_headline) ?></a> (<?php echo number_format($hot_replies) ?>)</li>
<?php
	endwhile;
?>
	</ol>
</div>
</div>
		
<?php
$template->render();
?>