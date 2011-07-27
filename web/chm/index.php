<?php

require_once "../lib/common.inc.php";
require_once "../lib/SimpieView.php";
require_once "../models/BookPage.php";
require_once "TipiCHM.php";

$pages = BookPage::getFlatPagesArrayForPrint();
$view = new SimpieView('../templates/chm/print.php');
$chapt_list = BookPage::getChapterList();

array_shift($chapt_list);

$data = array('page_name' => 'ch/index', 'list' => $chapt_list, 'title' => 'TIPI');
define('ROOT', dirname(__FILE__) . "/");
$filename = "tipi";

$tipichm = new TipiCHM($filename, $data);
$tipichm->copyCSS(ROOT_PATH . "/css/chm.css");
$tipichm->copyCSS(ROOT_PATH . "/css/highlight.css");
$tipichm->copyImagesOfDiretory();
$tipichm->createFiles($view, $pages);
$tipichm->createHHC();
$tipichm->createHHK();
$tipichm->createHHP();

echo 'complete!';


/* 执行HTML help Workshop的complie后 */
//$tipichm->copyCHM();

?>
