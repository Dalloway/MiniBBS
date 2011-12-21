<?php
/* Cleans up the database; called (at most) every 48 hours over AJAX by stuff.php. */

define('MINIMAL_BOOTSTRAP', true);
require './includes/bootstrap.php';

if(cache::fetch('maintenance') > $_SERVER['REQUEST_TIME'] - 172800) {
	exit('Too early.');
}

cache::set('maintenance', $_SERVER['REQUEST_TIME']);

/* Continue execution even if the request times out. */
@ignore_user_abort(true);
/* Force the request to time out quickly. */
@set_time_limit(1);

$deleted_rows = 0;

/* Delete activity older than an hour */
$res = $db->q('DELETE FROM activity WHERE time < ?', $_SERVER['REQUEST_TIME'] - 3600);
$deleted_rows += $res->rowCount();

/* Delete search logs older than an hour */
$res = $db->q('DELETE FROM search_log WHERE time < ?', $_SERVER['REQUEST_TIME'] - 3600);
$deleted_rows += $res->rowCount();

/* Delete users with 0 posts and no activity for two weeks */
$res = $db->q('DELETE FROM users WHERE last_seen < ? AND post_count = 0', $_SERVER['REQUEST_TIME'] - 1209600);
$deleted_rows += $res->rowCount();

/* Resort */
$db->q('OPTIMIZE TABLE activity, search_log, users');

log_mod('db_maintenance', '', $deleted_rows, '', 'system');

$template->render(false);
?>