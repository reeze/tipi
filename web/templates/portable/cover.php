<!DOCTYPE html>
<html>
<head>
	<title><?php echo SITE_NAME; ?></title>
	<meta http-equiv="Content-type" content="text/html; charset=utf-8">
	<?php
		echo stylesheet_include_tag_embed(array('portable.css'));
	?>
</head>
<body id="portable-cover">
	<div style="text-align:center;font-size: 20em;padding-top: 200px;">TIPI</div>
	<div id="cover">
		<h1>TIPI:深入理解PHP内核</h1>
		<p>www.php-internal.com<span><?php echo TIPI::getVersion(); ?></span></p>

	<div id="cover-extra">
		<a href="<?php echo TIPI::getHomeUrlForPdf(); ?>"><img src="<?php echo url_for("/images/get-lastest.png"); ?>" /></a>
	</div>
	</div>

	<div id="cover-authors">
		<ul>
			<li>reeze <?php echo link_to("http://reeze.cn", "http://reeze.cn"); ?></li>
			<li>er <?php echo link_to("http://www.zhanger.com", "http://www.zhanger.com"); ?></li>
			<li>phppan <?php echo link_to("http://www.phppan.com", "http://www.phppan.com"); ?></li>
		</ul>
	</div>
</body>
</html>

