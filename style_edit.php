<?php
require './includes/bootstrap.php';
force_id();
update_activity('editing_style', 1);
$template->onload = 'focusId(\'custom_style\')';
$template->title = 'Creating a stylesheet';

$editing = false;
if(isset($_GET['edit'])) {
	if( ! ctype_digit($_GET['edit'])) {
		error::fatal('Invalid style ID.');
	}
	
	$res = $db->q('SELECT title, style AS css, name, trip, uid, public, basis, original FROM user_styles WHERE id = ?', $_GET['edit']);
	$style = $res->fetchObject();
	
	if( ! $style) {
		error::fatal('There is no style with that ID.');
	}
	if($style->uid == $_SESSION['UID']) {
		$editing = true;
		$template->title = 'Editing a stylesheet';
	} else if( ! $style->public) {
		error::fatal('That style is private.');
	} else {
		$style->public = '0';
	}
}

$base_styles = get_styles();
$set_name = $_SESSION['poster_name'];
if(isset($_POST['form_sent'])) {
	$set_name = $_POST['name'];
	
	$style = (object) $_POST['style'];
	$style->css = super_trim($style->css);
	$style->md5 = md5($style->css);
	$style->title = super_trim($style->title);
	$style->public = (isset($style->public) ? '1' : '0');
	$style->basis = str_replace(array('/', '\\', '.', "\0"), '', $style->basis);
	$style->color = '';
	list($style->name, $style->trip) = tripcode($set_name);
	$style->original = (isset($style->original) ? (int) $style->original : 0);
	
	if(isset($_POST['preview']) && check_token()) {
		$template->style_override = $style->basis;
		$template->head = '<style>' . htmlspecialchars($style->css, ENT_NOQUOTES) . '</style>';
	} else if($_POST['update']) {
		check_token();
		check_length($style->css, 'CSS', 10, 30000);
		check_length($style->title, 'title', 1, 60);
		check_length($style->name, 'name', 0, 30);
		
		/* Flood control */
		$res = $db->q('SELECT COUNT(*) FROM user_styles WHERE uid = ? AND modified > ?', $_SESSION['UID'], $_SERVER['REQUEST_TIME'] - 300);
		if($res->fetchColumn() > 9) {
			error::add('You\'re editing too many stylesheets too quickly. Please wait a while.');
		}
		
		/* Uncheck the public switch if a style identical to this already exists*/
		if($style->public && ! $editing) {
			$res = $db->q('SELECT 1 FROM user_styles WHERE md5 = ? AND basis = ?', $style->md5, $style->basis);
			if($res->fetchColumn()) {
				$style->public = '0';
			}
		}
		
		/* XSS protection */
		$dangerous = array('expression', 'binding', 'behavior', 'script', '\\\\', '&', '@import', 'content\s*:');
		foreach($dangerous as $word) {
			if(preg_match('!'.$word.'!', $style->css)) {
				error::add('Your CSS contains "' . $word . '", which is not allowed for security reasons.');
			}
		}
		
		if($style->basis != '') {
			if( ! in_array($style->basis, $base_styles)) {
				error::add('The basis of your style does not appear to exist.');
			} else {
				$basis_css = file_get_contents(SITE_ROOT . '/style/themes/' . $style->basis . '.css');

				if(preg_match('/background-color:\s*([#0-9a-zA-Z]+)/', $basis_css, $match)) {
					$style->color = $match[1];
				}
			} 
		}
		
		if(preg_match('/background-color:\s*([#0-9a-zA-Z]+)/', $style->css, $match)) {
			$style->color = $match[1];
		}
		
		if(error::valid()) {
			$notice = 'Style saved.';
			if( ! $_SESSION['settings']['custom_style']) {
				$notice .= ' You can enable it from the <a href="'.DIR.'dashboard">dashboard</a>.';
			}
			
			if($editing) {
				$db->q
				(
					'UPDATE user_styles
					SET title = ?, style = ?, md5 = ?, public = ?, basis = ?, color = ?, name = ?, trip = ?, modified = ?
					WHERE id = ? AND uid = ?',
					$style->title, $style->css, $style->md5, $style->public, $style->basis, $style->color, $style->name, $style->trip, $_SERVER['REQUEST_TIME'],
					$_GET['edit'], $_SESSION['UID']
				);
				
				$_SESSION['style_last_modified'] = $_SERVER['REQUEST_TIME'];
				$_SESSION['notice'] = $notice;
			} else {
				$db->q
				(
					'INSERT INTO user_styles
					(title, style, md5, public, basis, color, name, trip, modified, original, uid) VALUES
					(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
					$style->title, $style->css, $style->md5, $style->public, $style->basis, $style->color, $style->name, $style->trip, $_SERVER['REQUEST_TIME'], $style->original, $_SESSION['UID']
				);
				
				$new_id = $db->lastInsertId();
				redirect($notice, 'edit_style/' . $new_id);
			}
		}
	} 
}

if( ! empty($style->title)) {
	$template->title .= ' (' . htmlspecialchars($style->title) . ')';
}

error::output();
?> 
<p>Once saved, you'll be able to select your new theme from the stylesheet dropdown in the <a href="<?php echo DIR; ?>dashboard">dashboard</a>. If you choose a "basis" below, your new stylesheet will be cascaded atop that style (combining them). <kbd>main.css</kbd> will always be present. You can include your style in the <a href="<?php echo DIR ?>theme_gallery">public theme gallery</a> for others to use.</p>

<?php
if($editing && $style->public):
?>
<p>To show others your style, give them this link: <strong><a href="<?php echo DIR . 'view_style/' . (int) $_GET['edit'] ?>"><?php echo URL . 'view_style/' . (int) $_GET['edit'] ?></a></strong></p>
<?php
endif;
?>

<form action="" method="post">
	<input type="hidden" name="form_sent" value="1" />
	<?php csrf_token() ?>
	<div class="row">
		<label for="style_title">Style title</label>
		<input id="style_title" name="style[title]" tabindex="1" type="text" size="50" maxlength="60" value="<?php echo htmlspecialchars($style->title) ?>">
	</div>
	<div class="row">
		<label for="name">Your name</label> 
		<input id="name" name="name" type="text" size="30" maxlength="30" tabindex="2" value="<?php echo htmlspecialchars($set_name) ?>" placeholder="name #tripcode" />
	</div>

	<div>
		<textarea id="custom_style" name="style[css]" cols="80" rows="29"><?php echo htmlspecialchars($style->css) ?></textarea>
	</div>
	<div class="row">
		<label for="style_basis" class="inline">Basis: </label>
		<select id="style_basis" name="style[basis]" class="inline">
			<option value="">None</option>
			<?php foreach($base_styles as $style_title): ?>
				<option value="<?php echo htmlspecialchars($style_title) ?>"<?php if($style->basis == $style_title) echo ' selected'?>><?php echo htmlspecialchars($style_title) ?></option>
			<?php endforeach; ?>
		</select>
	</div>
	<div class="row">
		<input id="style_public" name="style[public]" type="checkbox" value="1"<?php if($style->public) echo ' checked="checked"'?> class="inline" />
		<label for="style_public" class="inline">Add to public gallery</label>
	</div>
	<div class="row">
		<input type="submit" name="preview" value="Preview" class="inline" />
		<input type="submit" name="update" value="<?php echo ($editing ? 'Update' : 'Create') ?>" class="inline" />
	</div>
</form>
<?php
$template->render();
?>