<div id="header">
	<div id="inner-header" class="clearfix">
		<ul>
			<li><a id='nav-home-link' href="<?php echo url_for("/"); ?>">Home</a></li>
			<li><a id='nav-book-link' href="<?php echo url_for("/book/"); ?>">Book</a></li>
			<li><a id='nav-projects-link' href="<?php echo url_for("/projects/"); ?>">Projects</a></li>
			<li><a id='nav-news-link' href="<?php echo url_for("/news/"); ?>">News</a></li>
			<li><a id='nav-about-link' href="<?php echo url_for("/about/"); ?>">About</a></li>
			<li>
				<form id='search-form' action='<?php echo url_for("/search/"); ?>' method='GET'>
					<input id='search-input' name='query' type='text' />
					<input id='search-submit' type='submit' />
				</form>
			</li>
		</ul>
	</div>
</div>
