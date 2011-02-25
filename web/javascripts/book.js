/**
 * Book.js
 */


(function($) {
$(function() {
	$("#book_tools a").tipsy();

	$("#page_outline_button").click(function() {	
		$("#page_outline").toggle();

		return false;
	});

	$("#share_page").click(function() {	
		$("#share_page_pannel").toggle();

		return false;
	});

	$("#share_page_pannel a").click(function() {
		$('#share_page_pannel').hide();	
	});

	$("#font_size_changer").click(function() {
		$("#font_size_pannel").toggle();

		return false;
	});

	$("#page_outline a").click(function() {
		$("#page_outline").toggle();	
	});


	var change_font_size = function(delta) {
		var book_body = $("#book_body");
		var org_font_size = book_body.css("font-size");

		if(!$("#book_body").data('org_font_size')) {
			$("#book_body").data("org_font_size", org_font_size);
		}

		var new_size = delta ? parseFloat(org_font_size) + delta + "px" : book_body.data("org_font_size");
		$("#book_body").css("font-size", new_size);
	}

	$("#font_size_incr").click(function() {
		change_font_size(1);
	});

	$("#font_size_decr").click(function() {
		change_font_size(-1);
	});

	$("#font_size_default").click(function() {
		change_font_size();
	});
});
})(jQuery);
