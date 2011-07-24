<?php

require_once "../lib/common.inc.php";
require_once "../lib/SimpieView.php";

$view = new SimpieView('../../README.markdown', "../templates/layout/common_page.php");

$view->render(array(
	'current_page' => 'about',
	'title' => '关于',
));
