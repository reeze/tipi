<?php

require_once "../config.php";
require_once "../lib/SimpieView.php";
require_once "../models/BookPage.php";
require_once "../helpers/common_helper.php";
require_once "../helpers/book_helper.php";

$page_name = (isset($_GET['p']) && $_GET['p']) ? trim($_GET['p']) : 'index';

try
{
	// 基本的安全检查,防止主动修改文件路径
	if(!$page_name || $page_name[0] == '/' || strpos($page_name, '..')) {
		throw new PageNotFoundException("页面不存在");	
	}

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
	// TODO Suggest the page like the page name
	$view = new SimpieView("../templates/book_page_404.php", "../templates/layout/book.php");
	$view->render(array(
		'book_page' => $page_name,
		'title' => "Page Not Found",
		'is_detail_view' => true,
		'chapt_list' => $chapt_list,
	));
}
