<!DOCTYPE html>
<html>
<head>
	<title><?php echo SITE_NAME; ?></title>
	<meta http-equiv="Content-type" content="text/html; charset=gbk">
	<link href="main.css" media="screen" rel="stylesheet" type="text/css" />
	<link href="book.css" media="screen" rel="stylesheet" type="text/css" />
	<link href="chm.css" media="screen" rel="stylesheet" type="text/css" />
	<link href="highlight.css" media="screen" rel="stylesheet" type="text/css" />
</head>
<body id="portable" class="chm">
	<?php foreach($pages as $i => $page): ?>
		<div class="top">
			<?php SimpieView::include_partial(dirname(__FILE__) . "/../layout/_book_navor.php", array('page' => $page)); ?>
		</div>
		<div class='page'>
			<?php echo $page->toHtml(); ?>
		</div>
		<div class="leave-comment"><a href="<?php echo $page->getUrl(IS_ABSOLUTE_URL); ?>&ref=chm&v=<?php echo TIPI::getVersion(); ?>#comment" >看到这有什么想法或疑问？点击这里参与讨论吧！</a></div>
		<?php SimpieView::include_partial(dirname(__FILE__) . "/../layout/_book_navor.php", array('page' => $page)); ?>
	<?php endforeach; ?>
</body>
</html>
