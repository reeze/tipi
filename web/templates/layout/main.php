<!DOCTYPE html>
<html>
<head>
	<title><?php echo $title; ?> | <?php echo SITE_NAME; ?></title>
	<meta http-equiv="Content-type" content="text/html; charset=utf-8">
	<?php
		echo stylesheet_include_tag(array('book.css', 'main.css'));
	?>
	<meta name="description" content="<?php echo SITE_DESC; ?>" />
	<link rel="alternate" type="application/rss+xml" title="<?php echo SITE_NAME;?>" href="<?php echo url_for("/feed/"); ?>" />
	<link rel="shortcut icon" href="<?php echo url_for("/favicon.ico"); ?>" type="image/vnd.microsoft.icon">
	<meta name="google-site-verification" content="9MdZyBbYUlKcd9zfedQJEKhB66PngS1UUGHZ3NbZw70" />
</head>
<body id="home">
	<?php SimpieView::include_partial("templates/layout/_header.php"); ?>
	<?php echo $layout_content; ?>
	<?php SimpieView::include_partial("templates/layout/_footer.php"); ?>
</body>
</html>
