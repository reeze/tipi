<?php

require_once "../lib/common.inc.php";
require_once "../lib/SimpieView.php";
require_once "../models/BookPage.php";

$page_name = (isset($_GET['p']) && $_GET['p']) ? trim($_GET['p']) : 'index';

try
{
	ensure_page_name_safe($page_name);

	// 章节列表
	$chapt_list = BookPage::getChapterList();

	$page = new BookPage($page_name);
	$page_file = $page->getPageFilePath();

	// 是详细页面还是索引目录页,目录页不需要侧边栏
	$is_detail_view = ($page_name != 'index');


	$view = new SimpieView($page->toHtml(), "../templates/layout/book.php", SimpieView::IS_RAW_TEXT);
	$view->render(array(
		'title' => $page->getTitle(),
		'page'  => $page,
		'chapt_list' => $chapt_list,
		'is_detail_view' => $is_detail_view,
	));
}
catch(PageNotFoundException $e)
{
	// 尝试查找是否是因为章节调整导致地址发生变化导致的404
	// 通过永久重定向解决搜索引擎和错误地址的问题
	if($similar_page_name = BookPage::getMostSimilarPageFromPageName($page_name)) {
		redirect_to("/book?p=" . $similar_page_name, 301);
	}

	$view = new SimpieView("../templates/book_page_404.php", "../templates/layout/book.php");
	$view->render(array(
		'book_page' => $page_name,
		'title' => "Page Not Found",
		'is_detail_view' => true,
		'chapt_list' => $chapt_list,
	));
}
