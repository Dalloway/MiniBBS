// by r04r - http://www.tinybbs.org/
var num_poll_options;

function showPoll(elem) {
	if($("#topic_poll").css("display") == "none") {
		$(elem).text("[-] Poll");
		$("#topic_poll").show();
		$("#poll_option_"+(num_poll_options-1)).focus();
		$("#enable_poll").val(1);
	}else{
		$(elem).text("[+] Poll");
		$("#topic_poll").hide();
		$("#enable_poll").val(0);
	}
}

$(document).ready(function(){
	$(".poll_input").keypress(pollFocus);
	$(".poll_input").focus(pollFocus);
	$(".poll_input").blur(pollFocus);
	num_poll_options = $(".poll_input").length;
	if(!$(".poll_input").eq(0).val()) {
		$("#topic_poll").hide();
		$("#enable_poll").val(0);
		$("#poll_toggle").text("[+] Poll").attr("href", "javascript:void(0)");
	}else{
		$("#poll_toggle").text("[-] Poll").attr("href", "javascript:void(0)");
		$("#enable_poll").val(1);
	}
	pollFocus();
});

function pollFocus() {
	if(num_poll_options>=9) return true;
	var cont = true;
	$(".poll_input").each(function(){
		if(!$(this).val()) cont = false;
	});
	
	if(!cont) return true;
	num_poll_options++;
	var tr = document.createElement("tr");
	var td1 = document.createElement("td");
	var td2 = document.createElement("td");
	var input = document.createElement("input");
	
	if(num_poll_options%2) tr.class='odd';
	td1.innerHTML = "<label for='poll_option_" + num_poll_options + "'>Poll option #"  + num_poll_options + "</label>";
	td1.class = 'minimal';
	
	input.type = 'text';
	input.id = "poll_option_" + num_poll_options;
	input.name = 'option[]';
	input.setAttribute("class", 'poll_input');
	input.setAttribute("size", '50');
	input.setAttribute("maxlength", '80');
	$(input).focus(pollFocus);
	$(input).keypress(pollFocus);
	$(input).blur(pollFocus);
	
	td2.appendChild(input);
	
	tr.appendChild(td1);
	tr.appendChild(td2);
	
	$("#topic_poll").append(tr);
}