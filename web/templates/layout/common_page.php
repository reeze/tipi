<!DOCTYPE html>
<html>
<head>
	<title><?php echo ($page ? $page->getAbsTitle() : ($title ? $title : 'Page Not Found')); ?> | <?php echo SITE_NAME; ?> </title>
	<meta http-equiv="Content-type" content="text/html; charset=utf-8">

	<?php
		echo stylesheet_include_tag(array('book.css', 'main.css', 'highlight.css', 'tipsy.css'));
		echo javascript_include_tag(array('jquery-1.5.min.js', 'jquery.tipsy.js', 'book.js'));

		// google search api
		echo javascript_include_tag("http://www.google.com/jsapi?key=AIzaSyDP4wJCphYhYAWaqAecUh1hiB7zzbJMqPs");
	?>
	<link rel="alternate" type="application/rss+xml" title="<?php echo SITE_NAME;?>" href="<?php echo url_for("/feed/"); ?>" />
	<link rel="shortcut icon" href="<?php echo url_for("/favicon.ico"); ?>" type="image/vnd.microsoft.icon">
</head>
<body<?php echo (isset($current_page) ? " id=\"{$current_page}\"" : ''); ?>>
	<?php SimpieView::include_partial("../templates/layout/_header.php"); ?>
	<div id="wrapper">
			<div id="book_header">
				<h1><a href='<?php echo url_for("/"); ?>'><span>深入理解PHP内核</span></a></h1>
				<p>Thinking In PHP Internal</p>
			</div>

			<div id='common-main' class="clearfix">
				<div class='inner clearfix'>
				<?php SimpieView::include_partial("../templates/layout/_common_sidebar.php"); ?>
				<div id="common-page-body">
					<div id="page-body">
						<?php echo $layout_content; ?>
					</div>
					<?php SimpieView::include_partial("../templates/layout/_comment.php"); ?>
				</div>
				</div>
			</div>
		</div>
	</div>
	<?php SimpieView::include_partial("../templates/layout/_footer.php"); ?>
</body>
</html>
