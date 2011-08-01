<?php

require_once "../lib/common.inc.php";
require_once "../lib/SimpieView.php";
require_once "../models/News.php";

$page_name = (isset($_GET['p']) && $_GET['p']) ? $_GET['p'] : null;

try
{
	ensure_page_name_safe($page_name);

	if(!$page_name) {
		$view_path	= TEMPLATE_PATH . '/news/list.php';
		$params 	= array(
			'news_array' => News::findAll(),
			'title' => '新闻',
		);
	}
	else {
		$view_path	= NEWS_ROOT_PATH . "/{$page_name}." . MarkdownPage::extension;
		$news_page = new News($page_name);
		$params		= array(
			'news' => $news_page,
			'title' => $news_page->getTitle() . " - 新闻",
		);
	}

	$params = array_merge($params, array('current_page' => 'news'));

	$view = new SimpieView($view_path, "../templates/layout/common_page.php");
	$view->render($params);
}
catch(PageNotFoundException $e)
{
	$view = new SimpieView("../templates/book_page_404.php", "../templates/layout/book.php");
	$view->render(array(
		'book_page' => $page_name,
	));
}
