<?php
// TODO add style to feed ouput
echo "<style type='text/css'>" . file_get_contents("../css/feed.css"). file_get_contents("../css/highlight.css") . "</style>";
echo $layout_content;
?>

<hr />
<?php SimpieView::include_partial("../templates/layout/_footer.php"); ?>
