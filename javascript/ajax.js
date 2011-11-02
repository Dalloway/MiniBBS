function showTopic(data) {
	$('#topic_panel').html('<button onclick="hideTopic()" id="hide_topic">X</button>' + data).show();
	
	$('input:submit').click(function() {
		var submitName = this.getAttribute('name');
		var curForm = $(this).closest('form');
		var curAction = $(curForm).attr('action');
		$.post(curAction, curForm.serialize() + '&' + submitName + '=submit', function(data) {
			showTopic(data);
		});
		return false;
	});
}

function hideTopic() {
	$('#topic_panel').hide();
	$('#main_panel').css('width', '98%');
	$('tr').removeClass('active_topic');
}

function showNotice(notice) {
	$('#notice').remove();
	$('body').append('<div id="notice" onclick="this.parentNode.removeChild(this);"><strong>Notice</strong>: ' + notice + '</div>');
	$('#notice').delay('3000').fadeOut();
}
	
$('a').click(function() {
	var target = this.href;
	if(target.indexOf(window.location.hostname) != -1) {
		var regex = /\/topic\/(\d+)/
		var threadID = regex.exec(target);
		if(threadID != null) {
			$('tr').removeClass('active_topic').removeClass('loading_topic');
			var active_tr = $(this).closest('tr');
			$(active_tr).addClass('loading_topic');
			$.get('topic.php?id=' + threadID[1], function(data) {
				showTopic(data);
				$('#main_panel').css('width', '44%');
				$(active_tr).removeClass('loading_topic').addClass('active_topic').find('.new_replies').remove();
				if(target.indexOf('#new') != -1) {
					document.getElementById('topic_panel').scrollTop = document.getElementById('new').offsetTop - 70;
					highlightReply(document.getElementById('new_id').value);
				}
			});
			return false;
		}
	}
});