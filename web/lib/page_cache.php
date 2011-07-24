<?php

require_once dirname(__FILE__) . "/SimpieCache.php";
require_once dirname(__FILE__) . "/TIPI.php";

/**
 * 缓存页面
 */

if(!ENABLE_PAGE_CACHE) return;

$key = $_SERVER['REQUEST_URI'] . TIPI::getVersion();

$output = SimpieCache::get($key);

if($output === null) {
	// 将输出缓存起来
	register_shutdown_function('catch_and_cache_page_output');
	ob_start();
}
else {
	$headers = SimpieCache::get($key . "headers");
	if($headers !== null) {
		foreach(json_decode($headers) as $header) {
			header($header);
		}	
	}
	echo $output;
	exit;
}

function catch_and_cache_page_output()
{
	global $key;
	$output = ob_get_clean();
	SimpieCache::set($key, $output, 0);

	// 缓存输出的头信息
	$headers = headers_list();
	if(!empty($headers)) {
		SimpieCache::set($key . 'headers', json_encode($headers), 0);
	}
	
	// 输出内容
	echo $output;
}
