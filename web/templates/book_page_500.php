<div>
	抱歉, 你要浏览的章节<b>: <?php echo $book_page; ?> </b>版本:<?php echo $rev?>暂时不可用，请重试。

	<div><b>原因：</b><?php echo $exception->getMessage(); ?></div>

	<br />
	<br />
	<div> 建议您 <a href="<?php echo url_for_book("$book_page"); ?>">浏览最新版本: <?php echo $book_page; ?></a>，或者：</div>
	<div><b>- 浏览其他：</b>点击右侧的目录浏览您感兴趣的内容。</div>
	<div><b>- 搜索内容：</b>在页面右上角输入要搜索的内容，并回车进行搜索</div>
</div>
