<?php

require_once "../lib/common.inc.php";
require_once "../lib/SimpieView.php";
require_once "../models/BookPage.php";

try
{
	$view = new SimpieView('../templates/projects/index.php', "../templates/layout/common_page.php");
	$html = $view->render(array(
		'current_page' => 'projects',
		'title' => '项目',
	),true);
	if(!file_exists('index.html')){
		file_put_contents('index.html', $html);
	}
	echo $html;
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
