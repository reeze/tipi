<?php

require_once "../lib/common.inc.php";
require_once "../lib/SimpieView.php";
require_once "../models/BookPage.php";

$page_name = (isset($_GET['p']) && $_GET['p']) ? trim($_GET['p']) : 'index';

try
{
	ensure_page_name_safe($page_name);

	$page = new BookPage($page_name);

	$chapt_list = BookPage::getChapterList();

	// 线下模式不显示修改时间，因为从Github读取需要的时间太长
	$page_last_update_time = IN_PROD_MODE ? $page->getLastUpdatedAt(true, "Y-m-d H:h") : false;

	// 如果获取修改时间失败，则先暂时禁用缓存，否则无法重新获取最后修改时间
	if($page_last_update_time === false) {
		// Do not disable cache for now
		// PageCache::disable();
	}

	$view = new SimpieView($page->toHtml(), "../templates/layout/book.php", SimpieView::IS_RAW_TEXT);
	$view->render(array(
		'title' => $page->getTitle(),
		'page'  => $page,
		'chapt_list' => $chapt_list,
		'is_detail_view' => ($page_name != 'index'), // 目录页不需要边栏
		'page_last_update_time' => $page_last_update_time,
	));
}
catch(PageNotFoundException $e)
{
	// 尝试查找是否是因为章节调整导致地址发生变化导致的404
	// 通过永久重定向解决搜索引擎和错误地址的问题
	if($similar_page_name = BookPage::getMostSimilarPageFromPageName($page_name)) {
		redirect_to("/book?p=" . $similar_page_name, 301);
	}

	header($_SERVER['SERVER_PROTOCOL'] . " 404 Not Found");  
	header("Status: 404 Not Found"); 

	$view = 404;
	$title = "Page Not Found";

	$view = new SimpieView("../templates/book_page_$view.php", "../templates/layout/book.php");
	$view->render(array(
		'book_page' => $page_name,
		'exception' => $e,
		'title' 	=> $title,
		'is_detail_view' => true,
		'chapt_list' => $chapt_list,
	));
}
