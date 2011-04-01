<!DOCTYPE html>
<html>
<head>
	<title><?php echo SITE_NAME; ?></title>
	<meta http-equiv="Content-type" content="text/html; charset=utf-8">
	<?php
		echo stylesheet_include_tag(array('portable.css', 'highlight.css'));
	?>
</head>
<body id="portable" class="pdf">
	<?php foreach($pages as $i => $page): ?>
		<div class='page <?php if($page->isChapterIndex()) { echo 'page-break';}; ?>'>
			<?php echo $page->toHtml(); ?>
		</div>
	<?php endforeach; ?>
</body>
</html>
