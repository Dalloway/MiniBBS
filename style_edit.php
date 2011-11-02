<?php
require './includes/bootstrap.php';
force_id();
$template->title = 'Edit your custom stylesheet';
update_activity('custom_style', 1);
$template->onload = 'focusId(\'custom_style\'); init();';

$res = $db->q('SELECT style, id FROM user_styles WHERE uid = ?', $_SESSION['UID']);
list($custom_style, $style_id) = $res->fetch(PDO::FETCH_NUM);

if ($_POST['form_sent']) {
	$custom_style = $_POST['custom_style'];
	if($_POST['update']) {
		check_token();
		check_length($custom_style, 'custom style', 0, 20000);
		if (error::valid()) {
			// Insert or update.
			$db->q('INSERT INTO user_styles (uid, style) VALUES (?, ?) ON DUPLICATE KEY UPDATE style = ?', $_SESSION['UID'], $custom_style, $custom_style);
			$_SESSION['style_last_modified'] = $_SERVER['REQUEST_TIME'];
			$_SESSION['notice'] = 'Custom style updated.';
			if ( ! $_SESSION['settings']['custom_style']) {
				$_SESSION['notice'] .= ' You must <a href="'.DIR.'dashboard">enable custom styles</a> for this to have any effect.';
			}
		}
	} else if($_POST['preview']) {
		$template->title = 'Previewing your custom stylesheet';
		/* Disable the previous version of their stylesheet while previewing */
		$template->disable_custom = true;
		$template->head = '<style>' . strip_tags($custom_style) . '</style>';
	}
}

error::output();
?> 
<p>When custom styles are <a href="<?php echo DIR; ?>dashboard">enabled</a>, the CSS defined below will be cascaded atop your selected stylesheet. If you chose "custom only" as your stylesheet in the dashboard, <em>only</em> the below CSS will be used. You can use the "preview" button to test your CSS on the current page.</p>

<?php
if( ! empty($style_id)):
?>
<p>To show others your style, give them this link: <strong><a href="<?php echo DIR . 'view_style/' . (int) $style_id ?>"><?php echo URL . 'view_style/' . (int) $style_id ?></a></strong></p>
<?php
endif;
?>

<form action="" method="post">
	<input type="hidden" name="form_sent" value="1" />
	<?php csrf_token() ?>
	<div>
		<textarea id="custom_style" name="custom_style" cols="80" rows="26"><?php echo htmlspecialchars($custom_style) ?></textarea>
	</div>
	<div class="row">
		<input type="submit" name="preview" value="Preview" class="inline" />
		<input type="submit" name="update" value="Update" class="inline" />
	</div>
</form>
<?php
$template->render();
?>