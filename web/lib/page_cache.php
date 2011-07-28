<?php

require_once dirname(__FILE__) . "/SimpieCache.php";
require_once dirname(__FILE__) . "/TIPI.php";

/**
 * 简单的页面缓存。通过URL+TIPI版本的方式来进行页面的缓存。
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
