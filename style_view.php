<?php
require './includes/bootstrap.php';
if( ! ctype_digit($_GET['id'])) {
	error::fatal('Invalid style ID.');
}

$res = $db->q('SELECT style AS css, title, public, uid, name, trip, modified, basis FROM user_styles WHERE id = ?', $_GET['id']);
$style = $res->fetchObject();

if( ! $style) {
	error::fatal('No style was found.');
}

if($style->uid != $_SESSION['UID'] && ! $style->public) {
	error::fatal('This style is private.');
}

$template->title = '<a href="'.DIR.'theme_gallery">Theme</a>: ' . htmlspecialchars($style->title);

if(isset($_POST['preview']) && check_token()) {
	$template->style_override = $style->basis;
	$template->head = '<style>' . htmlspecialchars($style->css, ENT_NOQUOTES) . '</style>';
} else if(isset($_POST['edit'])) {
	header('Location: ' . URL . 'edit_style/' . $_GET['id']);
}

/* Really basic highlighting */
$highlighted_style = htmlspecialchars($style->css);
$highlighted_style = preg_replace('/(^|{|;)(.+?):/m', '$1<span style="color: #008000">$2</span>:', $highlighted_style);
$highlighted_style = preg_replace('/(^|})(.+?){/m', '$1<span style="color: #003AFF">$2</span>{', $highlighted_style);

?>
<form action="" method="post">
	<?php csrf_token() ?>
	<input type="submit" name="preview" value="Preview" class="inline" />
	<input type="submit" name="edit" value="<?php echo ($style->uid == $_SESSION['UID'] ? 'Edit' : 'Clone and edit') ?>" class="inline" />
</form>

<p>This theme was last modified <strong><span class="help" title="<?php echo format_date($style->modified) ?>"><?php echo age($style->modified) ?></span> ago</strong> by <?php echo trim(format_name($style->name, $style->trip)) ?>.</p>

<pre style="display: block; background-color: #F0F0F0; padding: 1em; color: #000; margin-top: 1em;">
<?php echo $highlighted_style ?>
</pre>

<?php
$template->render();
?>