<!DOCTYPE html>
<html>
<head>
	<title><?php echo SITE_NAME; ?></title>
	<meta http-equiv="Content-type" content="text/html; charset=utf-8">
	<?php
		echo stylesheet_include_tag_embed(array('portable.css'));
	?>
</head>
<body id="portable-header">
	<div id="header">
		<a href="<?php echo TIPI::getHomeUrlForPdf(); ?>">TIPI:深入理解PHP内核<span><?php echo TIPI::getVersion(); ?></span></a>
		<?php // TODO 根据当前页面来生成标题 ?>
	</div>
</body>
</html>

