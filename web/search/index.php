<?php

require_once "../config.php";
require_once "../lib/SimpieView.php";
require_once "../models/BookPage.php";
require_once "../helpers/common_helper.php";
require_once "../helpers/book_helper.php";

try
{
	$view = new SimpieView('../templates/search/index.php', "../templates/layout/common_page.php");
	$view->render(array(
		'title' => '搜索',
		'query' => $_GET['query'],
	));
}
catch(PageNotFoundException $e)
{
	// TODO Suggest the page like the page name
	$view = new SimpieView("../templates/book_page_404.php", "../templates/layout/book.php");
	$view->render(array(
		'book_page' => $page_name,
		'title' => "Page Not Found",
		'is_detail_view' => true,
		'chapt_list' => $chapt_list,
	));
}
