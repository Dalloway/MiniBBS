<?php
require './includes/bootstrap.php';
force_id();
$template->title = 'Manage DEFCON';

if( ! $perm->get('defcon')) {
	error::fatal(MESSAGE_ACCESS_DENIED);
}

if(isset($_POST['id'])) {
	$defcon = $_POST['id'];
		
	check_token();
		
	if( ! ctype_digit($defcon) || $defcon > 5 || $defcon < 1) {
		error::add('Invalid DEFCON value.');
	}
	if( ! $perm->get('defcon_all') && ($defcon == 1 || $defcon == 2 )) {
		error::add('You may not change the DEFCON to that level.');
	}
	
	if(error::valid()) {
		$db->q("UPDATE flood_control SET value = ? WHERE setting = 'defcon'", $defcon);
		cache::clear('defcon');
		log_mod('defcon', $defcon);
		redirect('DEFCON updated to ' . (int) $defcon, '');
	}

	error::output();
}
?>
<form action="" method="post">
	<input type="radio" name="id" value="1" id="defcon_1"<?php if(DEFCON == 1) echo ' checked="checked"'; if(!$perm->get('defcon_all')) echo ' disabled="disabled"'; ?>> <label for="defcon_1">DEFCON 1 — Block everyone from accessing the board except for admins.</label><br>
	<input type="radio" name="id" value="2" id="defcon_2"<?php if(DEFCON == 2) echo ' checked="checked"'; if(!$perm->get('defcon_all')) echo ' disabled="disabled"'; ?>> <label for="defcon_2">DEFCON 2 — Block all users except moderators and admins from posting.</label><br>
	<input type="radio" name="id" value="3" id="defcon_3"<?php if(DEFCON == 3) echo ' checked="checked"'; ?>> <label for="defcon_3">DEFCON 3 — Block posting for users with less than <?php echo POSTS_TO_DEFY_DEFCON_3; ?> posts.</label><br>
	<input type="radio" name="id" value="4" id="defcon_4"<?php if(DEFCON == 4) echo ' checked="checked"'; ?>> <label for="defcon_4">DEFCON 4 — Block the creation of new UIDs. Existing UIDs have full privileges. (THIS BLOCKS ALL NEW USERS. DO NOT LEAVE ON FOR LONG PERIODS OF TIME.)</label><br>
	<input type="radio" name="id" value="5" id="defcon_5"<?php if(DEFCON == 5) echo ' checked="checked"'; ?>> <label for="defcon_5">DEFCON 5 — Normal board operation.</label><br>
	<?php csrf_token(); ?>
	<input type="submit" value="Set" />
</form>
<?php
$template->render();
?>