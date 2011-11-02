<?php
require './includes/bootstrap.php';
force_id();
$template->title = 'New bulletin';
$template->onload = "focusId('bulletin');";

if( ! $perm->get('bulletin')) {
	error::fatal(MESSAGE_ACCESS_DENIED);
}
if($_SESSION['post_count'] < MIN_BULLETIN_POSTS && ! $perm->is_admin()) {
	error::fatal('Sorry, only regulars can post bulletins. You currently have ' . $_SESSION['post_count'] . ' posts, but need ' . MIN_BULLETIN_POSTS. '.');
}

if($_POST['bulletin']) {
	$bulletin = super_trim($_POST['bulletin']);
	list($name, $trip) = tripcode($_POST['name']);
	
	check_token();
	check_length($name, 'name', 0, 30);
	check_length($bulletin, 'bulletin', 2, 512);
		
	if( ! $perm->is_admin() && ! $perm->is_mod()) {
		$res = $db->q('SELECT 1 FROM bulletins WHERE ip = ? AND time > (? - ?)', $_SERVER['REMOTE_ADDR'], $_SERVER['REQUEST_TIME'], FLOOD_CONTROL_BULLETINS);
		if($res->fetchColumn()) {
			error::add('Please wait a while before submitting another bulletin.');
		}
	}
	
	if(error::valid()) {
		$res = $db->q
		(
			'INSERT INTO bulletins 
			(message, author, name, trip, ip, time) VALUES 
			(?, ?, ?, ?, ?, ?)', 
			$bulletin, $_SESSION['UID'], $name, $trip, $_SERVER['REMOTE_ADDR'], $_SERVER['REQUEST_TIME']
		);
		if($res->rowCount() > 0) {
			$db->q('UPDATE last_actions SET time = ? WHERE feature = ?', $_SERVER['REQUEST_TIME'], 'last_bulletin');
			redirect('Bulletin posted.', '');
		} else {
			error::add('Database error.');
		}
	}
	
	error::output();
}

?>
<p>Bulletins are short announcements or updates about the site. <script type="text/javascript"> printCharactersRemaining('numCharactersLeftForBulletin', 512); </script></p>

<form action="" method="post">
	<?php csrf_token() ?>
	<div class="row">
		<label for="name">Name</label>
		<input id="name" name="name" type="text" size="30" maxlength="30" tabindex="1" value="<?php echo htmlspecialchars($_SESSION['poster_name']) ?>">
	</div>
	<div class="row">
		<textarea id="bulletin" name="bulletin" onkeydown="updateCharactersRemaining('bulletin', 'numCharactersLeftForBulletin', 512);" onkeyup="updateCharactersRemaining('bulletin', 'numCharactersLeftForBulletin', 512);" maxlength="512"><?php if($bulletin) echo htmlspecialchars($bulletin) ?></textarea>
	</div>
	<div class="row">
		<input type="submit" value="Submit bulletin">
	</div>
</form>

<?php
$template->render();
?>