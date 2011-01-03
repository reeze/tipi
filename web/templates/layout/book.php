<html>

<head>
	<title><?php echo $title; ?> | 深入理解PHP内核(TIPI) </title>
	<meta http-equiv="Content-type" content="text/html; charset=utf-8">
	<link href="../css/book.css" media="screen" rel="stylesheet" type="text/css" />
	<link href="../css/main.css" media="screen" rel="stylesheet" type="text/css" />
	<link href="../css/highlight.css" media="screen" rel="stylesheet" type="text/css" />
</head>
<body>
	<?php SimpieView::include_partial("../templates/layout/_header.php"); ?>
	<div id="wrapper">
			<div id="book_header">
				<h1><a href='.'>深入理解PHP内核</a></h1>
				<p>Thinking In PHP Internal</p>
			</div>

			<div id="book_main" class="clearfix">
				<div id="<?php echo ($is_detail_view ? 'book_content' : 'book_index'); ?>" >
					<div class="inner-containner"><?php echo $layout_content; ?></div>
				</div>

				<?php if($is_detail_view): ?>
					<div id="book_sidebar">
						<div class='inner-containner'><?php SimpieView::include_partial("../templates/layout/_sidebar.php"); ?></div>
					</div>
				<?php endif; ?>
			</div>

			<div id="footer"></div>
		</div>
	</div>
</body>
</html>
