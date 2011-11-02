<?php
require './includes/bootstrap.php';
force_id();

if( ! $perm->get('cms')) {
	error::fatal(MESSAGE_ACCESS_DENIED);
}

$page_data = array();

if($_POST['form_sent'] && check_token()) {
	$page_data['url']     = ltrim($_POST['url'], '/');
	$page_data['title']   = $_POST['title'];
	$page_data['content'] = $_POST['content'];
	$page_data['markup']  = ($_POST['syntax'] == 'markup' ? 1 : 0);
}

if($_GET['edit']) {
	if( ! ctype_digit($_GET['edit'])) {
		error::fatal('Invalid page ID.');
	}
	
	$res = $db->q('SELECT url, page_title, content, markup FROM pages WHERE id = ?', $_GET['edit']);
	if( ! $res) {
		$template->title = 'Non-existent page';
		error::fatal('There is no page with that ID.');
	}
	if( ! $_POST['form_sent']) {
		list($page_data['url'], $page_data['title'], $page_data['content'], $page_data['markup']) = $res->fetch();
	}
	$editing = true;
	$template->title = 'Editing page: <a href="' . DIR . $page_data['url'] . '">' . htmlspecialchars($page_data['title']) . '</a>';
	$page_data['id'] = $_GET['edit'];
} else { // New page.
	$template->title = 'New page';
	if ( ! empty($page_data['title'])) {
		$template->title .= ': ' . htmlspecialchars($page_data['title']);
	}
}

if($_POST['post']) {
	check_token();
	if(empty($page_data['url'])) {
		error::add('A path is required.');
	}
	
	if(error::valid()) {
		$page_data['content'] = str_replace('&#47;textarea', '/textarea', $page_data['content']);
		if ($editing) {
			$db->q('UPDATE pages SET url = ?, page_title = ?, content = ?, markup = ? WHERE id = ?', $page_data['url'], $page_data['title'], $page_data['content'], $page_data['markup'], $page_data['id']);
			$notice = 'Page successfully edited.';
			log_mod('cms_edit', $page_data['url']);
		} else { // New page.
			$add_page = $db->q('INSERT INTO pages (url, page_title, content, markup) VALUES (?, ?, ?, ?)', $page_data['url'], $page_data['title'], $page_data['content'], $page_data['markup']);
			$notice = 'Page successfully created.';
			log_mod('cms_new', $page_data['url']);
		}
		redirect($notice, $page_data['url']);
	}
	
	error::output();
}

if ($_POST['preview'] && ! empty($page_data['content'])) {
	$preview = $page_data['content'];
	if($page_data['markup']) {
		$preview = parser::parse($preview);
	}
	echo '<h3 id="preview">Preview</h3><div class="body standalone"> <h2>' . $page_data['title'] . '</h2>' . $preview . '</div>';
}

?>
<form action="" method="post">
	<?php csrf_token() ?>
	<div class="noscreen">
		<input type="hidden" name="form_sent" value="1" />
	</div>
	<div class="row">	
		<label for="url">Path</label>
		<input id="url" name="url" value="<?php echo htmlspecialchars($page_data['url']) ?>" />
	</div>
	<div class="row">	
		<label for="title">Page title</label>
		<input id="title" name="title" value="<?php echo htmlspecialchars($page_data['title']) ?>" />
	</div>
	<div class="row">	
		 <textarea id="content" name="content" cols="120" rows="25"><?php echo str_replace('/textarea', '&#47;textarea', $page_data['content']) ?></textarea>
	</div>
	<div class="row">
		 <input type="radio" name="syntax" value="HTML" class="inline"<?php if( ! $page_data['markup']) echo ' checked' ?>> HTML
		 <input type="radio" name="syntax" value="markup" class="inline"<?php if($page_data['markup']) echo ' checked' ?>> <a href="<?php echo DIR ?>markup_syntax">Markup syntax</a>
	</div>
	<div class="row">
			<input type="submit" name="preview" value="Preview" class="inline" /> 
			<input type="submit" name="post" value="Submit" class="inline">
	</div>
</form>
<?php
$template->render();
?>