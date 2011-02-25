<html>

<head>
	<title><?php echo $page->getAbsTitle(); ?> | 深入理解PHP内核(TIPI) </title>
	<meta http-equiv="Content-type" content="text/html; charset=utf-8">
	<link href="../css/book.css" media="screen" rel="stylesheet" type="text/css" />
	<link href="../css/main.css" media="screen" rel="stylesheet" type="text/css" />
	<link href="../css/highlight.css" media="screen" rel="stylesheet" type="text/css" />

	<link href="../css/tipsy.css" media="screen" rel="stylesheet" type="text/css" />

	<script src="../javascripts/jquery-1.5.min.js" type="text/javascript"></script>
	<script src="../javascripts/jquery.tipsy.js" type="text/javascript"></script>
	<script src="../javascripts/book.js" type="text/javascript"></script>
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
					<?php SimpieView::include_partial("../templates/layout/_book_tools.php", array('page' => $page)); ?>
					<div id="book_body" class="inner-containner">
						<?php echo $layout_content; ?>

						<?php if($is_detail_view): ?>
							<?php SimpieView::include_partial("../templates/layout/_book_navor.php", array('prev_page' => $page->getPrevPage(), 'next_page' => $page->getNextPage())); ?>
							<?php SimpieView::include_partial("../templates/layout/_comment.php"); ?>
						<?php endif; ?>
					</div>
				</div>

				<?php if($is_detail_view): ?>
					<div id="book_sidebar">
						<div class='inner-containner'><?php SimpieView::include_partial("../templates/layout/_sidebar.php", array('chapt_list' => $chapt_list, 'current_page_name' => $page->getPageName())); ?></div>
					</div>
				<?php endif; ?>
			</div>
			
		<?php SimpieView::include_partial("../templates/layout/_footer.php"); ?>
		</div>
	</div>
</body>
</html>
