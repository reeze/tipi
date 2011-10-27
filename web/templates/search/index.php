<h1>搜索</h1>
<input type='hidden' id='search-query' value='<?php echo $_GET['query']; ?>' />
<script>
google.load("search", "1");

$(function() {
function init() {
	var searchControl = new google.search.SearchControl();
	var webSearcher = new google.search.WebSearch();
	var options = new google.search.SearcherOptions();

	options.setNoResultsString("找不到结果");
	options.setExpandMode(GSearchControl.EXPAND_MODE_OPEN);

	webSearcher.setUserDefinedLabel("搜索结果");
	webSearcher.setSiteRestriction("php-internal.com");
	searchControl.setResultSetSize('large');
	searchControl.addSearcher(webSearcher, options);
	searchControl.draw(document.getElementById("search-zone"));

	searchControl.execute($('#search-query').val());
}

google.setOnLoadCallback(init);
});
</script>
<div id="search-zone">Loading...</div>
