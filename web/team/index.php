<?php

require_once "../lib/common.inc.php";
require_once "../lib/SimpieView.php";

$view = new SimpieView('../../AUTHORS', "../templates/layout/common_page.php", null, SimpieView::MARKDOWN_VIEW);
$html = $view->render(array(
	'current_page' => 'team',
	'title' => 'TIPI团队',
),true);
if(!file_exists('index.html')){
	file_put_contents('index.html', $html);
}
echo $html;
