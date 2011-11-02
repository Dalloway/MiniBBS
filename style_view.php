<?php
require './includes/bootstrap.php';
$template->title = 'View custom stylesheet';

if( ! ctype_digit($_GET['id'])) {
	error::fatal('Invalid style ID.');
}

$res = $db->q('SELECT style FROM user_styles WHERE id = ?', $_GET['id']);
$style = $res->fetchColumn();

if(empty($style)) {
	error::fatal('No style was found.');
}

if(isset($_POST['preview']) && check_token()) {
	$template->title = 'Previewing a custom stylesheet';
	$template->disable_custom = true;
	$template->head = '<style>' . strip_tags($style) . '</style>';
}

/* Really basic highlighting */
$highlighted_style = htmlspecialchars($style);
$highlighted_style = preg_replace('/(^|{|;)(.+?):/m', '$1<span style="color: #008000">$2</span>:', $highlighted_style);
$highlighted_style = preg_replace('/(^|})(.+?){/m', '$1<span style="color: #003AFF">$2</span>{', $highlighted_style);

?>
<form action="" method="post">
	<?php csrf_token() ?>
	<input type="submit" name="preview" value="Preview style" />
</form>

<pre style="display: block; background-color: #F0F0F0; padding: 1em; color: #000; margin-top: 1em;">
<?php echo $highlighted_style ?>
</pre>

<?php
$template->render();
?>