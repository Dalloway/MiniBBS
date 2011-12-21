<?php
/**
 * This file is included from class.template.php's load() function.
 * $this references variables and functions within that class.
 */
$this->gzhandler();
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8" />
	<title><?php echo strip_tags($this->title) . ' — ' . SITE_TITLE ?></title>
	<link rel="icon" type="image/png" href="<?php echo DIR ?>favicon.png" />
	
	<link rel="stylesheet" type="text/css" media="screen" href="<?php echo DIR ?>style/main.css?14" />
<<<<<<< HEAD
	<link rel="stylesheet" type="text/css" media="screen" href="<?php echo DIR . 'style/themes/' . $this->get_stylesheet() . '.css?9' ?>" />
<?php	
=======
<?php	
	if( ! empty($_SESSION['settings']['style'])): 
?>
	<link rel="stylesheet" type="text/css" media="screen" href="<?php echo DIR . 'style/themes/' . $this->get_stylesheet() . '.css?9' ?>" />
<?php
	endif;
	
>>>>>>> 549290f098d657f40dbdf2a86f845badd4fec54d
	if($_SESSION['settings']['custom_style'] && $this->style_override === false):
?>
	<link rel="stylesheet" type="text/css" media="screen" href="<?php echo DIR; ?>custom_style/<?php echo $_SESSION['settings']['custom_style'] ?>/<?php echo (int) $_SESSION['style_last_modified'] ?>" />
<?php
	endif;
	
	if(MOBILE_MODE):
?>
	<meta name="viewport" content="width=320; initial-scale=1.0; maximum-scale=1.0; user-scalable=0;" />
	<link rel="stylesheet" type="text/css" media="screen" href="<?php echo DIR . 'style/mobile.css?2' ?>" />
<?php 
	elseif(FANCY_IMAGE):
?>
	<link rel="stylesheet" type="text/css" media="screen" href="<?php echo DIR; ?>style/thickbox.css" />
	<script type="text/javascript" src="<?php echo DIR; ?>javascript/thickbox.js"></script>
	<script type="text/javascript">var tb_pathToImage = "<?php echo URL; ?>javascript/img/loading.gif"</script>
<?php
	endif;
?>
	<script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.4.2/jquery.min.js"></script>
	<script type="text/javascript" src="<?php echo DIR; ?>javascript/main.js?6"></script>
<?php
	echo $this->head
?>
</head>

<body<?php echo ( ! empty($this->onload) ? ' onload="' . $this->onload . '"' : '' ) ?>>
<span id="top"></span>
<?php
if( ! empty($_SESSION['notice'])):
?>
<div id="notice" ondblclick="this.parentNode.removeChild(this);"><?php echo m('Notice label') ?> <?php echo $_SESSION['notice'] ?></div>
<?php
	unset($_SESSION['notice']);
endif;
?>
<div id="header">
	<h1 id="logo"><a rel="index" href="<?php echo DIR ?>"><?php echo SITE_TITLE ?></a></h1>
	
	<form action="<?php echo DIR ?>search" method="post">
		<input id="search_phrase" name="phrase" type="text" size="24" maxlength="255" class="inline" />
		<input type="submit" value="Search" name="deep_search" class="inline" />
	</form>
	
	<ul id="main_menu" class="menu">
<?php
/* Print the main menu. */
foreach($this->get_user_menu() as $linked_text => $path):
?>
		<li id="menu_<?php echo $path ?>">
			<a href="<?php echo (strpos($path, 'http') === 0 ? '' : DIR) . $path ?>"><?php echo $this->mark_new($linked_text, $path); if(isset($this->menu_children[$linked_text])) echo '<span class="dropdown">▼</span>' ?></a>
<?php
	if(isset($this->menu_children[$linked_text])):
?>
			<ul>
<?php
		foreach($this->menu_children[$linked_text] as $linked_text => $path):
?>
				<li><a href="<?php echo (strpos($path, 'http') === 0 ? '' : DIR) . $path ?>"><?php echo $this->mark_new($linked_text, $path) ?></a></li>
<?php
		endforeach;
?>
			</ul>
<?php
	endif;
?>
		</li>
<?php
endforeach;
?>
	</ul>
</div>

<h2><?php echo $this->title ?></h2>
<?php
echo $this->content;
$stats = $this->get_stats();
?>
<div id="footer" class="unimportant"><?php echo m('Footer', $stats['total_time'], $stats['query_percent'], $stats['query_count']) ?></div>

<form id="quick_action" action="" method="post" class="noscreen">
	<?php csrf_token() ?>
	<input type="hidden" name="confirm" value="1" />
</form>

<span id="bottom"></span>
</body>
</html>