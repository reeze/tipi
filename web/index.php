<?php

require_once "lib/SimpieView.php";

$view = new SimpieView("templates/index.markdown", "templates/main.php");
$view->render();
