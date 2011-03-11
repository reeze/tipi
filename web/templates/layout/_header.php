<div id="header">
	<div id="inner-header" class="clearfix">
		<ul>
			<li><a id='home-link' href="<?php echo url_for("/"); ?>">Home</a></li>
			<li><a id='book-link' href="<?php echo url_for("/book/"); ?>">Book</a></li>
			<li><a id='projects-link' href="<?php echo url_for("/projects/"); ?>">Projects</a></li>
			<li><a id='about-link' href="<?php echo url_for("/about/"); ?>">About</a></li>
			<li>
				<form id='search-form' action='<?php echo url_for("/search/"); ?>' method='GET'>
					<input id='search-input' name='query' type='text' />
					<input id='search-submit' type='submit' />
				</form>
			</li>
		</ul>
	</div>
</div>
