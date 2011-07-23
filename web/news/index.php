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
			'news_array' => News::findAll()
		);
	}
	else {
		$view_path	= NEWS_ROOT_PATH . "/{$page_name}." . MarkdownPage::extension;
		$params		= array(
			'news' => new News($page_name)
		);
	}

	$params = array_merge($params, array('current_page' => 'news', 'title' => '新闻'));

	$view = new SimpieView($view_path, "../templates/layout/common_page.php");
	$html = $view->render($params , true);
	if(!file_exists('index.html')){
		file_put_contents('index.html', $html);
	}
	echo $html;
}
catch(PageNotFoundException $e)
{
	$view = new SimpieView("../templates/book_page_404.php", "../templates/layout/book.php");
	$view->render(array(
		'book_page' => $page_name,
	));
}
