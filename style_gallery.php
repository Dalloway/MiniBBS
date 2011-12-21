<?php
require './includes/bootstrap.php';

$page = new Paginate();

$db->select('id, title, name, trip, color, modified, basis, uid, public')
   ->from('user_styles')
   ->order_by('modified DESC')
   ->limit($page->offset, $page->limit);
   
if( ! isset($_GET['mine'])) {
	$db->where('public = 1');
	$template->title = 'Custom theme gallery';
} else {
	$db->where('uid = ?', $_SESSION['UID']);
	$template->title = 'My themes';
}

$res = $db->exec();

if ($page->current > 1) {
	$template->title   .= ', page #' . number_format($page->current);
}

?>
<ul class="menu">
	<li><a href="<?php echo DIR ?>new_style">New theme</a></li>
	<?php if(isset($_GET['mine'])): ?>
		<li><a href="<?php echo DIR ?>theme_gallery">All themes</a></li>
	<?php else: ?>
		<li><a href="<?php echo DIR ?>theme_gallery/you">My themes</a></li>
	<?php endif; ?>
</ul>

<?php
$columns = array
(
	'',
	'Title',
	'Basis',
	'Author',
	'Modified ▼'
);
$table = new Table($columns, 1);

while($style = $res->fetchObject()) {
	$values = array
	(
		'<div style="height: 1em; width: 1em; border: 1px solid #aaa; background-color:' . htmlspecialchars($style->color) . '" class="theme_color"> </div>',
		'<strong><a href="'.DIR.'view_style/'.$style->id.'">' . htmlspecialchars($style->title) . '</a></strong>',
		empty($style->basis) ? '-' : htmlspecialchars($style->basis),
		format_name($style->name, $style->trip),
		'<span class="help" title="' . format_date($style->modified) . '">' . age($style->modified) . '</span>'
	);
	
	if($style->uid == $_SESSION['UID']) {
		$values[1] .= ' (' . ($style->public ? '' : 'private; ') . '<a href="'.DIR.'edit_style/'.$style->id.'">edit</a>)';
	}
	
	$table->row($values);
}
$table->output(isset($_GET['mine']) ? 'You haven\'t created any themes yet.' : 'No one has submitted a public theme yet.');
$page->navigation('theme_gallery', $table->row_count);
$template->render();
?>