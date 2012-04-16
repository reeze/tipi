<?php

require_once "../../web/lib/common.inc.php";
require_once "../../web/lib/SimpieView.php";
require_once "../../web/lib/TIPI.php";
require_once "../../web/models/BookPage.php";
require_once dirname(__FILE__) . "/TipiCHM.php";
set_time_limit(0);
$pages = BookPage::getFlatPagesArrayForPrint();
$view = new SimpieView('../../web/templates/chm/print.php');
$chapt_list = BookPage::getChapterList();

#array_shift($chapt_list);

$data = array('page_name' => 'ch/home', 'list' => $chapt_list, 'title' => 'TIPI');
define('ROOT', dirname(__FILE__) . "/");
$filename = TIPI::getVersion();

$tipichm = new TipiCHM($filename, $data);

/* 执行HTML help Workshop的complie后 */
if ($_GET['complied'] == 1) {
    $tipichm->copyCHM();
    die();
}

$tipichm->copyCSS(ROOT_PATH . "/css/book.css");
$tipichm->copyCSS(ROOT_PATH . "/css/main.css");
$tipichm->copyCSS(ROOT_PATH . "/css/chm.css");
$tipichm->copyCSS(ROOT_PATH . "/css/highlight.css");
$tipichm->copyImages(ROOT_PATH . "/images/get-lastest.png");
$tipichm->createHome();
$tipichm->copyImagesOfDiretory();
$tipichm->createFiles($view, $pages);
$tipichm->createHHC();
$tipichm->createHHK();
$tipichm->createHHP();

echo 'complete!';



