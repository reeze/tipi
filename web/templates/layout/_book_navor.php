<div id="book_page_navor" class="clearfix">
	<?php if($page && ($prev_page = $page->getPrevPage())): ?>
	<div id="prev_page_link">
		<a href="?p=<?php echo $prev_page->getPageName(); ?>">«&nbsp;<?php echo $prev_page->getTitle(); ?></a>	
	</div>
	<?php endif; ?>

	<?php if($page && ($next_page = $page->getNextPage())): ?>
	<div id="next_page_link">
		<a href="?p=<?php echo $next_page->getPageName(); ?>"><?php echo $next_page->getTitle(); ?>&nbsp;»</a>
	</div>
	<?php endif; ?>
</div>
