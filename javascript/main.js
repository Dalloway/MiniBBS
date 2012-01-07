function highlightReply(id) {
	var divs = document.getElementsByTagName('div');
	for (var i = 0; i < divs.length; i++) {
		if (divs[i].className.indexOf('body') != -1)
			divs[i].className = divs[i].className.replace(/highlighted/, '');
	}
	if (id)
		document.getElementById('reply_box_' + id).className += ' highlighted';
		
	if($("#reply_button_"+id).length > 0){
		$("#reply_"+id).show();
		$("#reply_box_"+id).show();
		$("#reply_button_"+id).text("[hide]");
		document.location.hash = 'reply_' + id + '_info';
		return false;
	}
	return true;
}

function highlightPoster(number) {
	var divs = document.getElementsByTagName('div');
	for (var i = 0; i < divs.length; i++) {
		if (divs[i].className.indexOf('body') != -1) {
			divs[i].className = divs[i].className.replace(/highlighted/, '');
		}
		if ($(divs[i]).hasClass('poster_body_' + number)) {
			divs[i].className += ' highlighted';
		}
	}
}

function highlightRow(checkbox) {
	if(checkbox.checked) {
		$(checkbox).parents('tr').addClass('checked');
	} else {
		$(checkbox).parents('tr').removeClass('checked');
	}
}

function focusId(id) {
	document.getElementById(id).focus();
	init();
}

function addCommas(nStr){
	nStr += '';
	x = nStr.split('.');
	x1 = x[0];
	x2 = x.length > 1 ? '.' + x[1] : '';
	var rgx = /(\d+)(\d{3})/;
	while (rgx.test(x1)) {
		x1 = x1.replace(rgx, '$1' + ',' + '$2');
	}
	return x1 + x2;
}

function quickReply(id, content) {
	textarea = document.getElementById('qr_text');
	
	document.getElementById('quick_reply').style.display = 'block';
	textarea.value += '@' + addCommas(id) + '\r\n';
	
	if(content !== undefined) {
		textarea.value += decodeURIComponent(content.replace(/\+/g, '%20')) + "\r\n\r\n" ;
	}
	
	textarea.scrollIntoView(true);
	textarea.focus();
	textarea.scrollTop = textarea.scrollHeight;
	textarea.selectionStart = textarea.selectionEnd = textarea.value.length;
	
	return false;
}


function checkAll(formId) {
	form = document.getElementById(formId);
	inputs = form.getElementsByTagName('input');
	master_checked = form.master_checkbox.checked;

	for (i = 0; i < inputs.length; i++) {
		if (inputs[i].type == 'checkbox') {
			if (master_checked) {
				inputs[i].checked = true;
			} else {
				inputs[i].checked = false;
			}
			highlightRow(inputs[i]);
		}
	}
}

function quickAction(theElement, confirmMessage) {
	if (confirmMessage === undefined)
		var tmp = confirm('Really?');
	else
		var tmp = confirm(confirmMessage);
	if (tmp) {
		form = document.getElementById('quick_action');
		form.action = theElement.href;
		form.submit();
	}
	return false;
}

function updateCharactersRemaining(theInputOrTextarea, theElementToUpdate, maxCharacters) {
	tmp = document.getElementById(theElementToUpdate);
	tmp.firstChild.data = maxCharacters - document.getElementById(theInputOrTextarea).value.length;
}

function printCharactersRemaining(idOfTrackerElement, numDefaultCharacters) {
	document.write(' (<STRONG ID="' + idOfTrackerElement + '">' + numDefaultCharacters + '</STRONG> characters left)');
}

function removeSnapbackLink() {
	var tmp = document.getElementById("snapback_link");
	if (tmp)
		tmp.parentNode.removeChild(tmp);
}

function createSnapbackLink(lastReplyId) {
	removeSnapbackLink();
	var div = document.createElement('DIV');
	div.id = 'snapback_link';
	var a = document.createElement('A');
	a.href = '#reply_' + lastReplyId;
	a.onclick = function () { highlightReply(lastReplyId); removeSnapbackLink(); };
	a.className = 'help_cursor';
	a.title = 'Click me to snap back!';
	var strong = document.createElement('STRONG');
	strong.appendChild(document.createTextNode('↕'));
	a.appendChild(strong);
	div.appendChild(a);
	document.body.appendChild(div);
}

function play_video ( provider, media_ID, element, record_class, record_ID ) {
        my_ID = record_class + '-' + record_ID + '-media-' + media_ID;
        if ( jQuery(element).html() == 'play' ) {
                jQuery(element).html('close');
        } else {
                jQuery(element).html('play');
                jQuery('#' + my_ID).slideUp();
                return false;
        }
        video_player_html = '';
        if ( provider == 'youtube' ) {
                video_player_html = '<div id="' + my_ID + '" style="display: none;" class="video wrapper c"><object width="500" height="405"><param name="movie" value="http://www.youtube-nocookie.com/v/' + media_ID + '&amp;hl=en_US&amp;fs=1&amp;border=1&amp;autoplay=1"></param><param name="allowFullScreen" value="true"></param><param name="allowscriptaccess" value="always"></param><embed src="http://www.youtube-nocookie.com/v/' + media_ID + '&amp;hl=en_US&amp;fs=1&amp;border=1&amp;autoplay=1" type="application/x-shockwave-flash" allowscriptaccess="always" allowfullscreen="true" width="500" height="405"></embed></object><a href="http://www.youtube.com/watch?v=' + media_ID + '" class="youtube_alternate"><img src="http://img.youtube.com/vi/' + media_ID + '/0.jpg" width="480" height="360" alt="Video" /></a></div>';
        } else if ( provider == 'vimeo' ) {
                video_player_html = '<div id="' + my_ID + '" style="display: none;" class="video wrapper c"><object width="512" height="294"><param name="allowfullscreen" value="true" /><param name="allowscriptaccess" value="always" /><param name="movie" value="http://vimeo.com/moogaloop.swf?clip_id=' + media_ID + '&amp;server=vimeo.com&amp;show_title=1&amp;show_byline=1&amp;show_portrait=1&amp;fullscreen=1&amp;autoplay=1" /><embed src="http://vimeo.com/moogaloop.swf?clip_id=' + media_ID + '&amp;server=vimeo.com&amp;show_title=1&amp;show_byline=1&amp;show_portrait=0&amp;fullscreen=1&amp;autoplay=1" type="application/x-shockwave-flash" allowfullscreen="true" allowscriptaccess="always" width="512" height="294"></embed></object></div>';
        }
        jQuery(element).parent().after(video_player_html + "\n");
        jQuery('#' + my_ID).slideDown();
}

/* Source: http://hacks.mozilla.org/2011/03/the-shortest-image-uploader-ever/ */
function imgurUpload(file, apiKey) {
	/* Is the file an image? */
	if (!file || !file.type.match(/image.*/)) {
		return false;
	}

	/* It is! */
	document.getElementById('imgur_status').innerHTML = 'Uploading...';
	
	var fd = new FormData();
	fd.append('image', file);
	fd.append('key', apiKey);
	var xhr = new XMLHttpRequest();
	xhr.open('POST', 'http://api.imgur.com/2/upload.json');
	xhr.onload = function() {
		document.getElementById('imgur').value = JSON.parse(xhr.responseText).upload.links.original;
		$("#imgur_status").remove();
	}

	xhr.send(fd);
	
	return false;
}

function editReason(editLink, currentReason, token) {
	$('.mod_reason').remove();
	$('.mod_edit').show();
	
	form = document.createElement('form');
	form.setAttribute('action', editLink.href);
	form.setAttribute('method', 'post');
	form.setAttribute('class', 'mod_reason');
	
	csrf = document.createElement('input');
	csrf.setAttribute('name', 'CSRF_token');
	csrf.setAttribute('type', 'hidden');
	csrf.setAttribute('value', token);
	
	input = document.createElement('input');
	input.setAttribute('name', 'reason');
	input.setAttribute('type', 'text');
	input.setAttribute('size', '46');
	input.setAttribute('maxlength', '260');
	
	submit = document.createElement('input');
	submit.setAttribute('type', 'submit');
	
	if(currentReason === '') {
		submit.setAttribute('value', 'Add reason');
	} else {
		submit.setAttribute('value', 'Edit reason');
		input.setAttribute('value', decodeURIComponent(currentReason));
	}
	
	form.appendChild(csrf);
	form.appendChild(input);
	form.appendChild(submit);
	$(editLink).parents('td').append(form);
	$(editLink).hide();
	input.focus();
	
	return false;
}

function init() {
	if (document.getElementById(window.location.hash.substring(1))) {
		if (window.location.hash.indexOf('reply_') != -1)
			highlightReply(window.location.hash.substring(7));
		else if (window.location.hash.indexOf('join_') != -1)
			highlightPoster(window.location.hash.substring(6));
		else if (window.location.hash.indexOf('new') != -1)
			highlightReply(document.getElementById('new_id').value);
	}
}

window.onload = init;