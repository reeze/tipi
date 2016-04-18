<?php
$fp = file_open("./CREDITS","r+");
var_dump($fp);
var_dump(file_read($fp,6));
var_dump(file_write($fp,"zhoumengakng"));
var_dump(file_close($fp));
?>
