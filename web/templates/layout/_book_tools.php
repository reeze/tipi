<div id="book_tools" class="clearfix">
	<div id="book_path_navor" class="fl">
		第一章 PHP变量及其生命周期
	</div>
	<?php $headers = $page->getOutlineHeaders(); ?>
	<div id="book_item_tools" class="fr" style="position: relative">
		<a href='#comment' id="comment_link" class="tool_item" title="发表下看法吧!"><span>Comment</span></a>
		<a href='#' id="font_size_changer" class="tool_item" title="调整字体大小"><span>Font Size</span></a>
		<a href='#' id="share_page" class="tool_item" title="喜欢这篇文章? 分享吧!"><span>Share</span></a>
		<?php if(count($headers)): ?>
			<a id="page_outline_button" class='first_border_tool tool_item' href="#" title="查看小节大纲"><span>Toc</span></a>
			<div id="page_outline" class='shadow'>
				<ul>
				<?php foreach($headers as $header): ?>
					<li><a href='#<?php echo $header['text']; ?>'><?php echo $header['text']; ?></a></li>
				<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>
		<!--
		<a href='#back_top' id='back_to_top' class='tool_item <?php if(!count($headers)) { echo "first_border_tool'"; } ?>' title="返回页首"></a>
		-->
	</div>
</div>
