;(function($) {
$(function() {
	var commits_api = 'https://api.githu.com/repos/reeze/tipi/commits?callback=?'

	$.getJSON(commits_api, {}, function(data) {
		// TODO 	
	});
});
})(jQuery);