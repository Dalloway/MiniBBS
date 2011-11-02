<?php
if(!empty($_SESSION['notice'])) {
	echo '<script>showNotice(\'' . $_SESSION['notice'] . '\');</script>';
	unset($_SESSION['notice']);
}
?>

<h2><?php echo $this->title ?></h2>

<?php echo $this->content; ?>

<form id="quick_action" action="" method="post" class="noscreen">
	<?php csrf_token() ?>
	<input type="hidden" name="confirm" value="1" />
</form>