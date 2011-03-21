<?php

function is_page_name_safe($path) {
	return ($path && is_string($path)) ? ($path[0] != '/' && !strpos($path, '..')) : true;
}

function ensure_page_name_safe($path) {
	if(!is_page_name_safe($path)) throw new PageNotFoundException($path);
}


/**
 * Handle uncatched exceptions
 */
function tipi_handle_exception($e)
{
	// TODO
}
