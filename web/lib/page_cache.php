<?php

/**
 * 简单的页面缓存。通过URL进行页面的缓存。
 * 通过捕获output buffer来将输出内容进行缓存。目前页面均没有
 * 会改变页面内容的行为。所以可以简单进行缓存.
 *
 * 缓存默认存放在/tmp/simpie-cache-*, 如果需要清空可以手动删除缓存
 * 数据
 */

/**
 * 禁用缓存或者是POST请求的时候将不会缓存
 */
if(!ENABLE_PAGE_CACHE || !empty($_POST)) return;

$key = $_SERVER['REQUEST_URI'];

$cache_dir 	= defined('TIPI_CACHE_DIR') ? TIPI_CACHE_DIR : null;
$cache 		= new SimpieCache($cache_dir);
$output 	= $cache->get($key);

if($output === false) {
	// 将输出缓存起来
	register_shutdown_function('catch_and_cache_page_output');
	ob_start();
}
else {
	$headers = $cache->get($key . "headers");
	if($headers !== false) {
		foreach(json_decode($headers) as $header) {
			header($header);
		}	
	}
	echo $output;
	exit;
}

function catch_and_cache_page_output()
{
	global $cache, $key;
	$output = ob_get_clean();
	$cache->set($key, $output);

	// 缓存输出的头信息
	$headers = headers_list();
	if(!empty($headers)) {
		$cache->set($key . 'headers', json_encode($headers));
	}
	
	// 输出内容
	echo $output;
}
