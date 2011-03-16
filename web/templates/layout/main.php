<html>

<head>
	<title><?php echo $title; ?> | <?php echo SITE_NAME; ?></title>
	<meta http-equiv="Content-type" content="text/html; charset=utf-8">
	<?php
		echo stylesheet_include_tag(array('book.css', 'main.css'));
	?>
	<link rel="alternate" type="application/rss+xml" title="<?php echo SITE_NAME;?>" href="<?php echo url_for("/book/rss.php"); ?>" />
</head>
<body id="home">
	<?php SimpieView::include_partial("templates/layout/_header.php"); ?>
	<div id="home_page" class='body-wrapper'>
		<div id="main_logo">
			<img src="images/main-logo.png" />
		</div>

		<div id="get-it">
			<a class='read' href='<?php echo url_for("/book/"); ?>'><span>阅读</span></a>
			<span id="get-it-sep"></span>
			<a class='downloads' href='<?php echo url_for("/downloads/"); ?>'><span>下载</span></a>
		</div>

		<div id="get-started" class="clearfix">
			<div class='fl'>
			</div>

			<div class='fl'>
			
			</div>
		</div>
	</div>
	<?php SimpieView::include_partial("templates/layout/_footer.php"); ?>
</body>
</html>
