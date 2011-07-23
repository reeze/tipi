<?php

require_once "../lib/common.inc.php";
require_once "../lib/SimpieView.php";

$view = new SimpieView('../../HISTORY', "../templates/layout/common_page.php", null, SimpieView::MARKDOWN_VIEW);
$html = $view->render(array(
	'current_page' => 'events',
	'title' => 'TIPI大事记',
), true);

if(!file_exists('index.html')){
	file_put_contents('index.html', $html);
}
echo $html;
