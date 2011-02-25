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

	$("#page_outline a").click(function() {
		$("#page_outline").toggle();	
	});
});
})(jQuery);
