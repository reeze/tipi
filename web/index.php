<?php
date_default_timezone_set('Asia/Shanghai');
require_once "lib/SimpieView.php";

$view = new SimpieView("templates/index.php", "templates/layout/main.php");
$view->render(array('title' => "首页"));
