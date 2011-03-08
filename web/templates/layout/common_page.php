<html>
<head>
	<title><?php echo ($page ? $page->getAbsTitle() : ($title ? $title : 'Page Not Found')); ?> | <?php echo SITE_NAME; ?> </title>
	<meta http-equiv="Content-type" content="text/html; charset=utf-8">
	<link href="../css/book.css" media="screen" rel="stylesheet" type="text/css" />
	<link href="../css/main.css" media="screen" rel="stylesheet" type="text/css" />
	<link href="../css/highlight.css" media="screen" rel="stylesheet" type="text/css" />
	<link href="../css/tipsy.css" media="screen" rel="stylesheet" type="text/css" />

	<script src="../javascripts/jquery-1.5.min.js" type="text/javascript"></script>
	<script src="../javascripts/jquery.tipsy.js" type="text/javascript"></script>
	<script src="../javascripts/book.js" type="text/javascript"></script>

	<script src="http://www.google.com/jsapi?key=AIzaSyDP4wJCphYhYAWaqAecUh1hiB7zzbJMqPs" type="text/javascript"></script>
</head>
<body>
	<?php SimpieView::include_partial("../templates/layout/_header.php"); ?>
	<div id="wrapper">
			<div id="book_header">
				<h1><a href='.'>深入理解PHP内核</a></h1>
				<p>Thinking In PHP Internal</p>
			</div>

			<div class="clearfix">
				<?php SimpieView::include_partial("../templates/layout/_common_sidebar.php"); ?>
				<div id="page-body">
					<?php echo $layout_content; ?>
					<?php SimpieView::include_partial("../templates/layout/_comment.php"); ?>
				</div>
			</div>
		</div>
	</div>
	<?php SimpieView::include_partial("../templates/layout/_footer.php"); ?>
</body>
</html>
