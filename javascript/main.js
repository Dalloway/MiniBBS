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
		if (divs[i].className.indexOf('poster_body_' + number) != -1) {
			divs[i].className += ' highlighted';
		}
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

function quickQuote(id, content){
	document.getElementById('quick_reply').style.display = 'block';
	document.getElementById('qr_text').scrollIntoView(true);
	document.getElementById('qr_text').focus();
	document.getElementById('qr_text').value += '@' + addCommas(id) + '\r\n\r\n';
	document.getElementById('qr_text').value += decodeURIComponent(content.replace(/\+/g, '%20')) + "\r\n\r\n" ;
	document.getElementById('qr_text').scrollTop = document.getElementById('qr_text').scrollHeight;
	document.getElementById('qr_text').sel
	return false;
}

function quickCite(id){
	document.getElementById('quick_reply').style.display = 'block';
	document.getElementById('qr_text').scrollIntoView(true);
	document.getElementById('qr_text').focus();
	document.getElementById('qr_text').value += '@' + addCommas(id) + '\r\n';
	document.getElementById('qr_text').scrollTop = document.getElementById('qr_text').scrollHeight;
	return false;
}

function checkAll(formId) {
	form = document.getElementById(formId);
	inputs = form.getElementsByTagName('input');
	master_checked = form.master_checkbox.checked;

	for (i = 0; i < inputs.length; i++) {
		if (inputs[i].type == 'checkbox') {
			if (master_checked)
				inputs[i].checked = true;
			else
				inputs[i].checked = false;
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