<?php
require './includes/bootstrap.php';
force_id();

if ( ! $perm->get('cms')) {
	error::fatal(m('Error: Access denied'));
}

$template->title = 'Content management';
?>
<p>This feature can be used to edit and create non-dynamic pages.</p>
<?php
$columns = array
(
	'Path',
	'Title',
	'Content snippet',
	'Edit',
	'Delete'
);
$table = new Table($columns, 2);
$table->add_td_class(2, 'snippet');
$res = $db->q('SELECT id, url, page_title, content FROM pages WHERE deleted = 0');

while ($page = $res->fetchObject()) {
	$values = array
	(
		'<a href="'.DIR. $page->url . '">' . $page->url . '</a>', 
		$page->page_title,
		parser::snippet($page->content),
		'<a href="'.DIR.'edit_page/' . $page->id . '">&#9998;</a>',
		'<a href="'.DIR.'delete_page/' . $page->id . '">&#10008;</a>'
	);
	$table->row($values);
}
$table->output('(No pages to display.)');
?>
<ul class="menu">
	<li><a href="<?php echo DIR; ?>new_page">New page</a></li>
</ul>
<?php
$template->render();
?>