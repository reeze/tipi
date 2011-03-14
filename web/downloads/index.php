<?php


require_once "../lib/common.inc.php";
require_once "../lib/SimpieView.php";
require_once "../models/BookPage.php";

try
{
	$view = new SimpieView('../templates/search/placeholder.php', "../templates/layout/common_page.php");
	$view->render(array(
		'title' => '下载',
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
