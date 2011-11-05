<?php
require './includes/bootstrap.php';
update_activity('stuff');
$template->title = 'Stuff';
?>
<div style="width: 50%; float:left">
<ul class="stuff">
	<li><strong><a href="<?php echo DIR; ?>dashboard">Dashboard</a></strong> — <span class="unimportant">Your personal settings, including username and password.</span></li>
	<li><a href="<?php echo DIR; ?>private_messages">Inbox</a> — <span class="unimportant">Your private messages (<?php echo $notifications['pms']; ?> new).</span></li>
	<li><a href="<?php echo DIR; ?>edit_ignore_list">Edit ignore list</a> — <span class="unimportant">Self-censorship.</span></li>
	<li><a href="<?php echo DIR; ?>edit_style">Edit custom stylesheet</a> — <span class="unimportant">Change the board's appearance.</span></li>
</ul>

<ul class="stuff">
<?php
	$user_menu = $template->get_user_menu();
	foreach($template->menu_options as $text => $link):
		if( ! in_array($link, $user_menu)):
?>
		<li><a href="<?php echo DIR . $link ?>"><?php echo $template->mark_new($text, $link) ?></a></li>
<?php
		endif;
	endforeach;
?>
</ul>
</div>

<div class="width:50%; float:right">
<ul class="stuff">
	<li><strong><a href="<?php echo DIR; ?>restore_ID">Restore ID</a></strong> — <span class="unimportant">Log in.</span></li>
	<li><a href="<?php echo DIR; ?>back_up_ID">Back up ID</a></li>
	<li><a href="<?php echo DIR; ?>recover_ID_by_email">Recover ID by e-mail</a></li>
	<li><a href="<?php echo DIR; ?>drop_ID">Drop ID</a> — <span class="unimportant">Log out.</span></li>
	<li><a href="<?php echo DIR; ?>trash_can">Trash can</a> — <span class="unimportant">Your deleted posts.</span></li>
</ul>
<ul class="stuff">
	<li><a href="<?php echo DIR; ?>mod_log">Mod logs</a></li>
	<li><a href="<?php echo DIR; ?>statistics">Statistics</a></li>
	<li><a href="<?php echo DIR; ?>failed_postings">Failed postings</a></li>
	<li><a href="<?php echo DIR; ?>date_and_time">Date and time</a></li>
	<li><a href="<?php echo DIR; ?>notepad">Notepad</a> — <span class="unimportant">Your personal notepad.</span></li>
</ul>
</div>


<?php
if ($perm->get('cms') || $perm->get('ban') || $perm->get('defcon')):
?>
<h4 class="section" style="clear: both;">Moderation</h4>
<ul class="stuff">
<?php
	if($perm->get('admin_dashboard')):
?>
	<li><a href="<?php echo DIR ?>admin_dashboard"><strong>Administrative dashboard</strong></a>  — <span class="unimportant">Manage board-wide settings.</span></li>
<?php
	endif;
	if($perm->get('cms')): 
?>
	<li><a href="<?php echo DIR ?>CMS">Content management</a>  — <span class="unimportant">Manage non-dynamic (static) pages.</span></li>
<?php
	endif;
	if($perm->get('ban')):
?>
	<li><a href="<?php echo DIR ?>bans">Bans</a>  — <span class="unimportant">View a list of current bans and manage them.</span></li>
<?php
	endif;
	if ($perm->get('exterminate')):
?>
	<li><a href="<?php echo DIR ?>exterminate">Exterminate trolls by phrase</a>  — <span class="unimportant">A last measure.</span></li>
<?php
	endif;
	if($perm->get('defcon')):
?>
	<li><a href="<?php DIR ?>defcon">Manage DEFCON</a>  — <span class="unimportant">Do not treat this lightly.</span></li>
<?php
	endif;
?>
</ul>
<?php
endif;

$template->render();
?>