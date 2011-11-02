<?php
/**
 * This file determines a reply's page number and/or parent topic, so we can link to a reply without said
 * information. For example, /reply/46 or /topic/1/reply/46 might redirect to /topic/1/2#reply_46  
 */
define('MINIMAL_BOOTSTRAP', true);
require './includes/bootstrap.php';

if( ! isset($_GET['reply'])) {
	redirect('No reply specified', '');
}

$topic = $_GET['topic'];
if(empty($topic)) {
	$res = $db->q('SELECT parent_id FROM replies WHERE id = ?', $_GET['reply']);
	$topic = $res->fetchColumn();
}

if($_SESSION['settings']['posts_per_page']) {
	$res = $db->q('SELECT COUNT(*) FROM replies WHERE parent_id = ? AND id < ? AND deleted = 0', $topic, $_GET['reply']);
	$reply_number = $res->fetchColumn() + 1;
	
	if($reply_number > $_SESSION['settings']['posts_per_page']) {
		/* This topic is definitely paginated, no need to check. */
		$total_replies = $reply_number;
	} else {
		$res = $db->q('SELECT replies FROM topics WHERE id = ?', $topic);
		$total_replies = $res->fetchColumn();
	}
} else {
	$total_replies = $reply_number = 1;
}

header('Location: ' . URL . 'topic/' . (int) $topic . page($total_replies, $reply_number) . '#reply_' . (int) $_GET['reply']);
exit();

?>