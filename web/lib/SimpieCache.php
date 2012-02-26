<?php

/**
 * 简单基于文件的内容缓存
 */
class SimpieCache
{
	// 缓存文件夹
	protected $cache_dir = null;
	protected static $global_cache_prefix = "";
	protected $cache_file_prefix = "simpie-cache-";
	protected $cache_file_suffix = ".cache";
	protected $cache_expire_callback = null;

	public function __construct($cache_dir=null, $cache_file_prefix=null, $cache_file_suffix=null, $fallback_to_tmp=false)
	{
		if($cache_file_prefix !== null) {
			$this->cache_file_prefix = $cache_file_prefix;
		}

		if($cache_file_suffix !== null) {
			$this->cache_file_suffix = $cache_file_suffix;
		}

		if($cache_dir !== null) {
			if(file_exists($cache_dir) && !is_dir($cache_dir)) {
				throw new SimpieCacheException("The cache dir: $cache_dir in __construct() is not a valid dir but file");
			}

			if(!file_exists($cache_dir)) {
				// 自动创建该目录
				if(mkdir($cache_dir, 0700, true) === false) {
					if($fallback_to_tmp) {
						// 如果目录创建失败并且设置fallback到临时目录
						// 增加缓存文件前缀避免冲突
						$this->cache_file_prefix .= "fallback_tmp_" . md5($cache_dir);
						$cache_dir = null;
					}
					else {
						throw new SimpieCacheException("The cacahe dir $cache_dir auto create failed.");
					}
				}
			}
		}

		// 默认保存在临时目录
		if($cache_dir === null) {
			if(function_exists('sys_get_temp_dir')) {
				$cache_dir = sys_get_temp_dir();
			}
			elseif(!($cache_dir=getenv("TMPDIR"))) {
				$cache_dir = "/tmp";
			}
		}

		$this->cache_dir = $cache_dir;
	}

	public static function setGlobalCacheFilePrefix($prefix) {
		self::$global_cache_prefix = $prefix;
	}

	/**
	 * 根据Key获取缓存内容
	 * @param string $key
	 * @param callable $callback 如果缓存未命中，或者已超时则调用回调生成内容
	 * @param int $expire 缓存过期时间
	 *
	 * @return string
	 */
	public function get_or_set($key, $callback, $expire=0)
	{
		$content = $this->get($key);	

		if($content !== false) {
			return $content;
		}
		if($content === false && $callback) {
			$value = $callback();

			return $this->set($key, $value, $expire);
		}
	}

	/**
	 * 根据key获取缓存
	 *
	 * @param string $key
	 *
	 * @return mixed 没有命中或者缓存过期则返回null,否则返回缓存字符串
	 */
	public function get($key)
	{
		$cache_file = $this->getCacheFile($key);

		if(($time = @filemtime($cache_file)) > time()) {
			return file_get_contents($cache_file);
		}
		else if($time > 0) {
			@unlink($cache_file);	
		}
		
		return false;
	}

	/**
	 * 设置key的值,如果该cache已存在将会覆盖原有内容
	 *
	 * @param string $key
	 * @param string $value
	 * @param int $expire 缓存过期时间
	 *
	 * @return boolean 成功或失败
	 */
	public function set($key, $value, $expire=0)
	{
		if(file_put_contents($this->getCacheFile($key), $value) === false) {
			throw new SimpieCacheException("Can't save the key $key's value to cache. please make sure the cache dir: {$this->cache_dir} is writable");
		}

		if($expire <= 0) {
			$expire = 31536000; // 1 year later
		}

		@touch($this->getCacheFile($key), time() + $expire);

		return true;
	}

	/**
	 * 清理指定的缓存内容
	 */
	public function delete($key)
	{
		@unlink($this->getCacheFile($key));
	}

	protected function getCacheFile($key)
	{
		return $this->cache_dir . "/" . self::$global_cache_prefix . $this->cache_file_prefix . $this->_hash_key($key) . $this->cache_file_suffix;
	}

	private function _hash_key($key)
	{
		return md5($key);	
	}
}

class SimpieCacheException extends Exception
{
}
