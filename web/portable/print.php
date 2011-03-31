<?php

require_once "../lib/common.inc.php";
require_once "../lib/SimpieView.php";
require_once "../models/BookPage.php";

$pages = BookPage::getFlatPagesArrayForPrint();


// 处理页面中的header，PDF生成工具可以根据标题级别来生成目录，将所有目录的层级缩短
$view = new SimpieView('../templates/portable/print.php');
$view->render(array(
	'pages' => $pages
));
