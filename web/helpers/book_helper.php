<?php

define('IS_ABSOLUTE_URL', true);

/**
 * Abstract url generation
 */
function url_for_book($page_name, $absolute=false) {
	return url_for("/book/?p=" . $page_name, $absolute);
}
