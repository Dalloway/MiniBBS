<?php
require './includes/bootstrap.php';
update_activity('failed_postings');
$template->title = 'Failed postings';
$items_per_page = ITEMS_PER_PAGE;

$res = $db->q('SELECT time, uid, reason, headline, body FROM failed_postings ORDER BY time DESC LIMIT '. (int) $items_per_page);

$columns = array(
	'Error message',
	'Poster',
	'Age ▼'
);
if ( ! $perm->get('view_profile')) {
	unset($columns[1]);
}

$table = new Table($columns, 0);

while (list($fail_time, $fail_uid, $fail_reason, $fail_headline, $fail_body) = $res->fetch()) {
	if (strlen($fail_body) > 600) {
		$fail_body = substr($fail_body, 0, 600) . ' …';
	}
	
	$tooltip = '';
	if (empty($fail_headline)) {
		$tooltip = $fail_body;
	} else if (!empty($fail_body)) {
		$tooltip = 'Headline: ' . $fail_headline . ' Body: ' . $fail_body;
	}
	
	$fail_reasons  = unserialize($fail_reason);
	$error_message = '<ul class="error_message';
	if (!empty($tooltip)) {
		$error_message .= ' help';
	}
	$error_message .= '" title="' . htmlspecialchars($tooltip) . '">';
	foreach ($fail_reasons as $reason) {
		$error_message .= '<li>' . $reason . '</li>';
	}
	$error_message .= '</ul>';
	
	$values = array(
		$error_message,
		'<a href="'.DIR.'profile/' . $fail_uid . '">' . $fail_uid . '</a>',
		'<span class="help" title="' . format_date($fail_time) . '">' . age($fail_time) . '</span>'
	);
	if ( ! $perm->get('view_profile')) {
		unset($values[1]);
	}
	
	$table->row($values);
}
$table->output('(No failed postings to display.)');
$template->render();
?>