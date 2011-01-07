<?php

require_once "../lib/SimpieView.php";

$book_page = isset($_GET['p']) ? $_GET['p'] : 'index';

// 指向web目录上层的书籍目录
$book_base_dir = "../../book";
$page_file = "{$book_base_dir}/{$book_page}.markdown";

// For security concern. check whether the book page is the real book page
if(strpos(realpath($page_file), realpath($book_base_dir)) !== 0) {
	$view = new SimpieView("../templates/book_page_404.php", "../templates/layout/book.php");
	$view->render(array('book_page' => $book_page, 'title' => "Page Not Found"));
	die();
} 

/**
 * 是详细页面还是索引目录页,目录页不需要侧边栏
 */
$is_detail_view = ($book_page != 'index');

// TODO  - Add markdown parse cache
//	     - 生成上页下一页地址以及自动提取目录信息

// TODO Add markdown parse cache to SimpieView
try
{
	$view = new SimpieView($page_file, "../templates/layout/book.php");

	// 获取当前也的标题 默认获取第一行作为标题
	$fp = @fopen($page_file, "r");
	$title = "TIPI";
	if($fp && $title_line = fgets($fp)) {
		$title = trim($title_line, "# \n\r\0");
		fclose($fp);
	}

	$view->render(array('is_detail_view' => $is_detail_view, 'title' => $title));
}
catch(SimpieViewNotFoundException $e)
{
	// Not Found
	$view = new SimpieView("../templates/book_page_404.php", "../templates/layout/book.php");
	$view->render(array('book_page' => $book_page, 'title' => "Page Not Found"));
}
