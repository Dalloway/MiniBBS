<?php
require './includes/bootstrap.php';
force_id();
$template->title = 'Edit ignored phrases';
update_activity('ignore_list', 1);
$template->onload = 'focusId(\'ignore_list\'); init();';

if ($_POST['form_sent']) {
	// CSRF checking.
	check_token();
	check_length($_POST['ignore_list'], 'ignore list', 0, 4000);
	if (error::valid()) {
		// Refresh our cache of phrases.
		unset($_SESSION['ignored_phrases']);
		// Insert or update.
		$db->q('INSERT INTO ignore_lists (uid, ignored_phrases) VALUES (?, ?) ON DUPLICATE KEY UPDATE ignored_phrases = ?', $_SESSION['UID'], $_POST['ignore_list'], $_POST['ignore_list']);
        $_SESSION['notice'] = 'Ignore list updated.';
        if ( ! $_SESSION['settings']['ostrich_mode']) {
            $_SESSION['notice'] .= ' You must <a href="'.DIR.'dashboard">enable ostrich mode</a> for this to have any effect.';
        }
    } else {
        $ignored_phrases = $_POST['ignore_list'];
    }
}

$res = $db->q('SELECT ignored_phrases FROM ignore_lists WHERE uid = ?', $_SESSION['UID']);
$ignored_phrases = $res->fetchColumn();
error::output();
?> 
<p>When ostrich mode is <a href="<?php echo DIR; ?>dashboard">enabled</a>, any topic or reply that contains a phrase on your ignore list will be hidden. You may also add names or tripcodes (separately) to ignore. Remember that ignoring a name will also filter any post that mentions it; a tripcode is more precise. Citations to hidden replies will be replaced with "@hidden". Enter one (case insensitive) phrase per line.</p>

<p>You can also match with regular expressions in the form of <kbd>/.../</kbd>. Regular expressions may be no longer than 28 characters.</p>

<p>Images can be ignored by their MD5 hash. If you see gibberish strings of 32-characters below, you probably chose to hide an image at some point.</p>
<form action="" method="post">
	<?php csrf_token() ?>
	<div>
		<textarea id="ignore_list" name="ignore_list" cols="80" rows="10"><?php echo htmlspecialchars($ignored_phrases) ?></textarea>
	</div>
	<div class="row">
		<input type="submit" name="form_sent" value="Update" />
	</div>
</form>
<?php
$template->render();
?>