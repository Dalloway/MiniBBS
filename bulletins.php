<?php
require './includes/bootstrap.php';

$template->title = 'Bulletins';
$template->onload = "focusId('bulletin');";
update_activity('bulletins');

$page = new Paginate();
if($page->current > 1) {
	$template->title .= ', page #' . number_format($page->current);
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
	
	if( ! $perm->get('bulletin')) {
		error::add(m('Error: Access denied'));
	}
	if($_SESSION['post_count'] < MIN_BULLETIN_POSTS && ! $perm->is_admin()) {
		error::add('Sorry, only regulars can post bulletins. You currently have ' . $_SESSION['post_count'] . ' posts, but need ' . MIN_BULLETIN_POSTS. '.');
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

setcookie('last_bulletin', $_SERVER['REQUEST_TIME'], $_SERVER['REQUEST_TIME'] + 315569260, '/');

if($perm->get('bulletin')):
?>

<form action="" method="post">
	<?php csrf_token() ?>
	<div class="row">
		<label for="name">Name:</label>
		<input id="name" name="name" type="text" size="30" maxlength="30" tabindex="1" value="<?php echo htmlspecialchars($_SESSION['poster_name']) ?>" class="inline">
	</div>
	<div>
		<textarea id="bulletin" name="bulletin" onkeydown="updateCharactersRemaining('bulletin', 'numCharactersLeftForBulletin', 512);" onkeyup="updateCharactersRemaining('bulletin', 'numCharactersLeftForBulletin', 512);" maxlength="512"><?php if(isset($bulletin)) echo htmlspecialchars($bulletin) ?></textarea>
	</div>
	<div class="row">
		<input type="submit" value="Submit bulletin" class="inline">  <script type="text/javascript"> printCharactersRemaining('numCharactersLeftForBulletin', 512); </script>
	</div>
</form>

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