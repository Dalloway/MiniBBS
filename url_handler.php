<?php
require './includes/bootstrap.php';
$requested_page = $_SERVER['REQUEST_URI'];
$base_path = dirname($_SERVER['SCRIPT_NAME']);
if(substr($requested_page, 0, strlen($base_path)) == $base_path){
	$requested_page = substr($requested_page, strlen($base_path));	
}
$requested_page = ltrim($requested_page, '/');

$automatic = array
(
	'robots.txt',
	'favicon.ico',
	'favicon.png'
);
if(in_array($requested_page, $automatic)) {
	header('HTTP/1.0 404 Not Found');
	exit();
}

$res = $db->q('SELECT id, page_title, content, markup FROM pages WHERE url = ? AND deleted = 0', $requested_page);
$cms_page = $res->fetchObject();

if ( ! $cms_page) {
	redirect('The page you requested (<strong>'.htmlspecialchars(urldecode($requested_page)).'</strong>) could not be located on the server.' . ($perm->get('cms') ? ' (Want to <a href="'.DIR.'new_page">create it?</a>)' : ''), '');
}

$template->title = $cms_page->page_title;
if($perm->get('cms')) {
	$template->title .= ' <sup>(<a href="' . DIR . 'edit_page/' . $cms_page->id . '" title="Edit this page">✎</a>)</sup>';
}

if($cms_page->markup) {
	$cms_page->content = parser::parse($cms_page->content);
}

echo $cms_page->content;

$template->render();
?> 