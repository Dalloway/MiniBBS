<?php
define('MINIMAL_BOOTSTRAP', true);
require './includes/bootstrap.php';

if( ! $_SESSION['settings']['custom_style']) {
	exit();
}

/* The client may cache this. */
header('Pragma:');
header('Expires:');
header('Cache-Control: private, max-age=43200, pre-check=43200');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $_SESSION['style_last_modified']) . ' GMT');
header('Content-type: text/css');

$res = $db->q('SELECT style FROM user_styles WHERE uid = ?', $_SESSION['UID']);
$css = $res->fetchColumn();

echo $css;

$template->render(false);
?>