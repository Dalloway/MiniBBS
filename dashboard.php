<?php

/**
 * Steps to adding a dashboard option:
 * 1. Add your new option to the array in /config/default_dashboard.php, following the instructions there.
 * 2. Add a column to the "user_settings" table for your new option.
 * 3. Add the HTML input below.
 * Your setting will now be available from $_SESSION['settings']['your_setting'] 
 */
 
require './includes/bootstrap.php';
force_id();
update_activity('dashboard');
$template->title = 'Dashboard';

$stylesheets = get_styles();
$custom_styles = array();
$res = $db->q('SELECT id, title, basis FROM user_styles WHERE uid = ?', $_SESSION['UID']);
while($style = $res->fetchObject()) {
	$custom_styles[$style->id] = array(
		'title' => $style->title,
		'basis' => $style->basis
	);
}

if(isset($_POST['form_sent'])) {
	/* default_dashboard.php contains $default_dashboard -- an array of info on dashboard options */
	require SITE_ROOT . '/config/default_dashboard.php';

	check_token();
	
	if(isset($_POST['revert'])) {
		$new_settings = array_map('current', $default_dashboard);
		$new_settings['memorable_name'] = $_POST['form']['memorable_name'];
	} else {
		$new_settings = array_map('trim', $_POST['form']);
	}
	
	/* First, make specific validations and transformations. */
	if(preg_match('!custom:([0-9]+)!', $new_settings['style'], $match)) {
		if( ! isset($custom_styles[$match[1]])) {
			error::add('Invalid custom style ID.');
		} else {
			$new_settings['custom_style'] = $match[1];
			$new_settings['style'] = $custom_styles[$match[1]]['basis'];
		}
	} else {
		$new_settings['custom_style'] = '0';
	}
	
	if( ! empty($new_settings['style']) && ! in_array($new_settings['style'], $stylesheets)) {
		error::add('There is no such stylesheet.');
	}
	
	if( ! empty($new_settings['memorable_name']) && $new_settings['memorable_name'] != $_SESSION['settings']['memorable_name']) {
		$res = $db->q('SELECT 1 FROM user_settings WHERE LOWER(memorable_name) = LOWER(?)', $new_settings['memorable_name']);

		if($res->fetchColumn()) {
			error::add('The memorable name "' . htmlspecialchars($new_settings['memorable_name']) . '" is being used by someone else.');
		}
	}
	
	if($new_settings['memorable_password'] != '') {
		$new_settings['memorable_password'] = hash_password($new_settings['memorable_password']);
	}
	
	if(empty($new_settings['custom_menu'])) {
		/* Revert to the default */
		$new_settings['custom_menu'] = DEFAULT_MENU;
	}
	/* Clean up menu formatting*/
	$new_settings['custom_menu'] = str_replace(array('  ', ']{'), array(' ', '] {'), $new_settings['custom_menu']);
	
	
	/* Now let's get generic. */
	foreach($default_dashboard as $option => $prop) {
		if($new_settings[$option] === 'On') {
			/* Must be a ticked checkbox. */
			$new_settings[$option] = '1';
		}
	
		if( ! isset($new_settings[$option])) {
			if($prop['default'] === '1' || $prop['default'] === '0') {
				/* Must be an unticked checkbox. */
				$new_settings[$option] = '0';
			} else {
				/* Did someone forget to add the input? */
				$new_settings[$option] = $prop['default'];
			}
		}
		
		$setting_length = strlen($new_settings[$option]);
		if(isset($prop['max_length']) && $setting_length > $prop['max_length']) {
			error::add('The length of setting "'.$option.'" exceeded '.$prop['max_length'].'.');
		}
		
		/* Validate the option against its type. */
		if(isset($prop['type'])) {
			if($prop['type'] === 'int') {
				if($new_settings[$option] === '') {
					$new_settings[$option] = $prop['default'];
				} else if( ! ctype_digit($new_settings[$option]) || $setting_length > 25) {
					error::add('"'.$option.'" does not look like a valid integer.');
				}
			}
			if($prop['type'] === 'bool' && $new_settings[$option] !== '1' && $new_settings[$option] !== '0') {
				error::add('"'.$option.'" does not look like a valid boolean.');
			}
		}
	}
	
	if(error::valid()) {
		$update_count = 0;
		foreach($default_dashboard as $option => $prop) {
			if($option === 'memorable_password' && $new_settings[$option] === '') {
				continue;
			}
			
			if($new_settings[$option] != $_SESSION['settings'][$option]) {
				/**
				 * We use addslashes() because PDO's quote() function adds surrounding quotes; anyway, the option
				 * names aren't based on user input.
				 */
				 $db->q
				 (
					'INSERT INTO user_settings 
						(uid, ' . addslashes($option) . ') VALUES 
						(?, ?) 
					ON DUPLICATE KEY 
						UPDATE ' . addslashes($option) . ' = ?', 
					$_SESSION['UID'], $new_settings[$option], $new_settings[$option]
				);
				 $update_count++;
			}
		}
		
		/* Refresh our settings. */
		load_settings();
		$_SESSION['notice'] = $update_count . ' setting' . ($update_count === 1 ? '' : 's') . ' updated.';
	}
}

error::output();
?>
<form action="" method="post">
	<?php csrf_token() ?>
	<div>
		<label class="common" for="memorable_name">Memorable name</label>
		<input type="text" id="memorable_name" name="form[memorable_name]" class="inline" value="<?php echo htmlspecialchars($_SESSION['settings']['memorable_name']) ?>" maxlength="100" size="20" />
	</div>
	<div>
		<label class="common" for="memorable_password">Memorable password</label>
		<input type="password" class="inline" id="memorable_password" name="form[memorable_password]" maxlength="100" size="20" /> <?php if(!empty($_SESSION['settings']['memorable_password'])) echo '<em>Set</em>'; ?>
		
		<p class="caption">This information can be used to more easily <a href="<?php echo DIR; ?>restore_ID">restore your ID</a>.</p>
	</div>
	
	<div class="row">
		<label class="common" for="e-mail">E-mail address</label>
		<input type="text" id="e-mail" name="form[email]" class="inline" value="<?php echo htmlspecialchars($_SESSION['settings']['email']) ?>"  size="35" maxlength="100" />
		
		<p class="caption">Used to recover your internal ID <a href="<?php echo DIR; ?>recover_ID_by_email">via e-mail</a>.</p>
	</div>
	
	<div class="row">
		<label class="common" for="style" class="inline">Stylesheet</label>
		<select id="style" name="form[style]" class="inline">
        <?php
		$master_style = ($_SESSION['settings']['custom_style'] ? $_SESSION['settings']['custom_style'] : $_SESSION['settings']['style']);
		foreach($stylesheets as $style) {
			echo '<option value="'.htmlspecialchars($style).'"' . ($master_style == $style ? ' selected' : '') . '>' .htmlspecialchars($style) . (DEFAULT_STYLESHEET == $style ? ' (default)' : '') . '</option>';
		}
		foreach($custom_styles as $id => $style) {
			echo '<option value="custom:' . (int) $id . '"' . ($master_style == $id ? ' selected' : '') . '>' . htmlspecialchars($style['title']) . ' (custom)</option>';
		}
		?>
		</select>
		<p class="caption">Alter the board's appearance. You can add custom styles to this dropdown from the <a href="<?php echo DIR ?>theme_gallery">theme gallery</a>. The Gmail themes have distinctively colored poster names.</p>
	</div>
		
	<div class="row">
		<label class="common" for="topics_mode" class="inline">Sort topics by:</label>
		<select id="topics_mode" name="form[topics_mode]" class="inline">
			<option value="0"<?php if($_SESSION['settings']['topics_mode'] == 0) echo ' selected' ?>>Last post (default)</option>
			<option value="1"<?php if($_SESSION['settings']['topics_mode']) echo ' selected' ?>>Date created</option>
		</select>
		<p class="caption">In what order should topics on the index be listed?</p>
	</div>

	<div class="row">
		<label class="common" for="custom_menu">Custom menu</label>
		<textarea id="custom_menu" name="form[custom_menu]" class="inline" rows="2" style="width:60%;"><?php echo htmlspecialchars($_SESSION['settings']['custom_menu']) ?></textarea>
		
		<p class="caption"><strong>Standard options</strong> (click to add): 
<?php
		foreach($template->menu_options as $text => $path):
			$text = str_replace('New topic', 'New_topic', $text);
?>
			· <span onclick="document.getElementById('custom_menu').value += ' <?php echo $text ?>';"><?php echo $text ?></span> 
<?php
		endforeach;
?>
		</p>
		
		<p class="caption">Aside from the standard items above, you can add custom links in the form of <kbd>[/dashboard My dashboard]</kbd> or <kbd>[http://example.com Example]</kbd>. You can add custom submenus by surrounding a block of links with curly brackets <kbd>{}</kbd> immediately after their parent. For example, "<kbd>[/normal Normal] [/parent Parent] {[/child1 Child 1] [http://example.com Child 2]} Stuff</kbd>". If blank, the default menu will be used. Any item you exclude will appear in the "Stuff" section instead.</p>
	</div>
	
	<div class="row">
		<label class="common" for="posts_per_page" class="inline">Posts per topic page</label>
		<input id="posts_per_page" name="form[posts_per_page]" class="inline" value="<?php echo htmlspecialchars($_SESSION['settings']['posts_per_page']) ?>"  size="5" maxlength="4" />
		<p class="caption">The maximum number of replies shown on each page of a topic. To disable pagination and show all replies at once, use 0.</p>
	</div>
	
	<div class="row">
		<label class="common" for="snippet_length" class="inline">Snippet length</label>
		<select id="snippet_length" name="form[snippet_length]" class="inline">
			<option value="80"<?php if($_SESSION['settings']['snippet_length'] == 80) echo ' selected' ?>>80 (default)</option>
			<option value="100"<?php if($_SESSION['settings']['snippet_length'] == 100) echo ' selected' ?>>100</option>
			<option value="120"<?php if($_SESSION['settings']['snippet_length'] == 120) echo ' selected' ?>>120</option>
			<option value="140"<?php if($_SESSION['settings']['snippet_length'] == 140) echo ' selected' ?>>140</option>
			<option value="160"<?php if($_SESSION['settings']['snippet_length'] == 160) echo ' selected' ?>>160</option>
		</select>
		<p class="caption">How many characters should snippets of post bodies consist of? (Snippets are shown when you hover over a citation link, in spoiler mode, and various other places.)</p>
	</div>
	
	<div class="row">
		<label class="common" for="spoiler_mode">Spoiler mode</label>
		<input type="checkbox" id="spoiler_mode" name="form[spoiler_mode]" value="1" class="inline"<?php if($_SESSION['settings']['spoiler_mode']) echo ' checked="checked"' ?> />
		<p class="caption">When enabled, snippets of the bodies will show in the topic list. Not recommended unless you have a very high-resolution screen.</p>
	</div>
	<div class="row">
		<label class="common" for="ostrich_mode">Ostrich mode</label>
		<input type="checkbox" id="ostrich_mode" name="form[ostrich_mode]" value="1" class="inline"<?php if($_SESSION['settings']['ostrich_mode']) echo ' checked="checked"' ?> />
		
		<p class="caption">When enabled, any topic or reply that contains a phrase from your <a href="<?php echo DIR; ?>edit_ignore_list">ignore list</a> will be hidden. You will also have the option of filtering particular images with a "Hide image" link.</p>
	</div>
	
	<div class="row">
		<label class="common" for="celebrity_mode">Celebrity mode</label>
		<input type="checkbox" id="celebrity_mode" name="form[celebrity_mode]" value="1" class="inline"<?php if($_SESSION['settings']['celebrity_mode']) echo ' checked="checked"' ?> />
		
		<p class="caption">When enabled, poster names will be shown on the topic index.</p>
	</div>
	
	<div class="row">
		<label class="common" for="text_mode">Text mode</label>
		<input type="checkbox" id="text_mode" name="form[text_mode]" value="1" class="inline"<?php if($_SESSION['settings']['text_mode']) echo ' checked="checked"' ?> />
		
		<p class="caption">When enabled, all images will be hidden.</p>
	</div>
	
	<div class="row" id="dash_submit">
		<input type="hidden" name="form_sent" value="1" />
		<input type="submit" value="Save settings" class="inline" />
		<input type="submit" name="revert" value="Revert to defaults" onclick="return confirm('Really revert to the default settings? Your name and password will be kept, but other changes will be lost.')"  class="inline"/>
	</div>
</form>
<?php
$template->render();
?>