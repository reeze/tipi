<?php

require_once "../lib/common.inc.php";
require_once "../lib/SimpieView.php";
require_once "../models/BookPage.php";

$view = new SimpieView('../templates/portable/cover.php');
$view->render();
