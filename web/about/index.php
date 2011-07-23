<?php

require_once "../lib/common.inc.php";
require_once "../lib/SimpieView.php";

$view = new SimpieView('../../README.markdown', "../templates/layout/common_page.php");
$html = $view->render(array(
	'current_page' => 'about',
	'title' => '关于',
),true);
if(!file_exists('index.html')){
	file_put_contents('index.html', $html);
}
echo $html;
