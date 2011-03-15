<?php

require_once "../lib/common.inc.php";
require_once "../lib/SimpieView.php";

$view = new SimpieView('../../AUTHORS', "../templates/layout/common_page.php", null, SimpieView::MARKDOWN_VIEW);
$view->render(array(
	'current_page' => 'team',
	'title' => 'TIPI团队',
));
