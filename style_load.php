<?php
define('MINIMAL_BOOTSTRAP', true);
require './includes/bootstrap.php';

if( ! $_SESSION['settings']['custom_style']) {
	exit();
}

$res = $db->q('SELECT style AS css, modified FROM user_styles WHERE id = ?', $_SESSION['settings']['custom_style']);
$style = $res->fetchObject();

/* The client may cache this. */
header('Pragma:');
header('Expires:');
header('Cache-Control: private, max-age=43200, pre-check=43200');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $style->modified) . ' GMT');
header('Content-type: text/css');

echo htmlspecialchars($style->css, ENT_NOQUOTES);

$template->render(false);
?>