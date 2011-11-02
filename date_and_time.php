<?php
require './includes/bootstrap.php';
update_activity('date_and_time');
$template->title = 'Date and time';
$day = date('l');
$dayn = date('jS');
$month = date('F');
$year = date('Y');
$week = date('W');
$dayz = date('z');
$dayy = 365 + date('L');
$percent = round(($dayz * 100) / $dayy);
$miltime = date('G:i');
$civtime = date('g:i A');
$intform = date('Y-m-d H:i:s'); 
?>
<p>Today is <strong><?php echo $day; ?></strong> the <strong><?php echo $dayn; ?></strong> of <strong><?php echo $month; ?></strong> in the year <strong><?php echo $year; ?></strong>, week <strong><?php echo $week; ?></strong> out of 52 and day <strong><?php echo $dayz; ?></strong> out of <?php echo $dayy; ?> (~<?php echo $percent; ?>%). The time is <strong><?php echo $miltime; ?></strong> (or <strong><?php echo $civtime; ?></strong>). In <a target="_blank" href="http://www.cl.cam.ac.uk/%7Emgk25/iso-time.html">international standard format</a>: <strong><?php echo $intform; ?></strong>.</p>
<?php
$template->render();
?>
