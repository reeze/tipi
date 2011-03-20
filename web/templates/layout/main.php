<!DOCTYPE html>
<html>
<head>
	<title><?php echo $title; ?> | <?php echo SITE_NAME; ?></title>
	<meta http-equiv="Content-type" content="text/html; charset=utf-8">
	<?php
		echo stylesheet_include_tag(array('book.css', 'main.css'));
	?>
	<link rel="alternate" type="application/rss+xml" title="<?php echo SITE_NAME;?>" href="<?php echo url_for("/feed/"); ?>" />
	<link rel="shortcut icon" href="<?php echo url_for("/images/favicon.ico"); ?>" type="image/vnd.microsoft.icon">
</head>
<body id="home">
	<?php SimpieView::include_partial("templates/layout/_header.php"); ?>
	<?php echo $layout_content; ?>
	<?php SimpieView::include_partial("templates/layout/_footer.php"); ?>
</body>
</html>
