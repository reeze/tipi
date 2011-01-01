<?php

require_once "../lib/SimpieView.php";

if(!isset($_SERVER['PATH_INFO'])) {
	header("Location: " . $_SERVER['REQUEST_URI'] . "/", 302);
	exit;
}

// 通过path_info来显示书中相应的markdown文件.默认为该书的首页
$request_path = $_SERVER['PATH_INFO'];
$request_path = $request_path == '/' ? '/index' : $request_path;

// 直接指向web目录上层的书籍目录
$book_base_dir = "../../book";
$page_file = $book_base_dir . $request_path . ".markdown";

// TODO Add markdown parse cache
$view = new SimpieView($page_file, "../templates/layout/book.php");
$view->render();
