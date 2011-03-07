<html>

<head>
	<title><?php echo $title; ?> | TIPI Project </title>
	<meta http-equiv="Content-type" content="text/html; charset=utf-8">
	<link href="css/main.css" media="screen" rel="stylesheet" type="text/css" />
	<link href="css/book.css" media="screen" rel="stylesheet" type="text/css" />
</head>
<body>
	<?php SimpieView::include_partial("templates/layout/_header.php"); ?>
	<div id="home_page" class='body-wrapper'>
		<div id="main_logo">
			<img src="images/main-logo.png" />
		</div>

		<div id="get-it">
			<a class='read' href='book/'><span>阅读</span></a>
			<span id="get-it-sep"></span>
			<a class='downloads' href='downloads/'><span>下载</span></a>
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
