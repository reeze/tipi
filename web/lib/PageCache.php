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

class PageCache
{
	protected static $enable = true;

	protected $key	 = null;
	protected $cache = null;

	public function __construct($key, $cache)
	{
		$this->key = $key;
		$this->cache = $cache;	
	}

	public static function disable()
	{
		self::$enable = false;	
	}

	public static function enable()
	{
		self::$enable = true;	
	}

	public function start()
	{
		if(!self::$enable) return;

		$output 	= $this->cache->get($this->key);

		if(!$output) {
			// 将输出缓存起来
			register_shutdown_function(array($this, 'end'));
			ob_start();
		}
		else {
			$headers = $this->cache->get($this->key . "headers");
			if($headers !== false) {
				foreach(json_decode($headers) as $header) {
					header($header);
				}	
			}
			echo $output;
			exit;
		}
	}

	public function end()
	{
		$output = ob_get_clean();

		// 缓存输出的头信息
		// 但是忽略404 500 的请求request don't cache it 
		$headers = headers_list();
		if(strlen($output) > 0 && self::$enable && !in_array("Status: 404 Not Found", $headers) && !in_array("Status: 500 Server Internal Error", $headers)) {
			if(!empty($headers)) {
				$this->cache->set($this->key . 'headers', json_encode($headers), PAGE_CACHE_TIMEOUT);
			}
			$this->cache->set($this->key, $output, PAGE_CACHE_TIMEOUT);
		}
		
		echo $output;
	}
}

$key = $_SERVER['REQUEST_URI'];

$cache_dir 	= defined('TIPI_CACHE_DIR') ? TIPI_CACHE_DIR : null;
$cache 		= new SimpieCache($cache_dir);

$page_cache = new PageCache($key, $cache);
$page_cache->start();
