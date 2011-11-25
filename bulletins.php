<?php
require './includes/bootstrap.php';

$template->title = 'Bulletins';
update_activity('bulletins');
setcookie('last_bulletin', $_SERVER['REQUEST_TIME'], $_SERVER['REQUEST_TIME'] + 315569260, '/');

$page = new Paginate();
if($page->current > 1) {
	$template->title .= ', page #' . number_format($page->current);
}

if($perm->get('bulletin')):
?>
<ul class="menu">
	<li><a href="<?php echo DIR ?>new_bulletin">New bulletin</a></li>
</ul>
<?php
endif;

$columns = array
(
	'Author',
	'Message',
	'Age ▼'
);
if($perm->get('delete')) {
	$columns[] = 'Delete';
}
$table = new Table($columns, 1);

$res = $db->q('SELECT id, message, time, author, name, trip FROM bulletins ORDER BY id DESC LIMIT '.$page->offset.', '.$page->limit);
while($bulletin = $res->fetchObject()) {	
	$values = array
	(
		format_name($bulletin->name, $bulletin->trip, $perm->get('link', $bulletin->author)),
		parser::parse($bulletin->message),
		'<span class="help" title="'.format_date($bulletin->time).'">' . age($bulletin->time) . '</span>'
	);
	if($perm->get('delete')) {
		$values[] = '<a href="'.DIR.'delete_bulletin/'.$bulletin->id.'" onclick="return quickAction(this, \'Really delete this bulletin?\');">✘</a>';
	}
	
	$table->row($values);
}

$table->output('(No bulletins to display.)');

$page->navigation('bulletins', $table->row_count);
$template->render();
?>