<?php

/**
 * 简单基于文件的内容缓存
 */
class SimpieCache
{
	// 缓存文件夹
	public static $cache_dir = "/tmp";

	/**
	 * 根据Key获取缓存内容
	 * @param string $key
	 * @param callable $callback 如果缓存未命中，或者已超时则调用回调生成内容
	 * @param int $expire 缓存超时时间
	 *
	 * @return string
	 */
	public static function get_or_set($key, $callback, $expire=0)
	{
		$content = self::get($key);

		if($content !== NULL) {
			return $content;
		}
		if($content === NULL && $callback) {
			$value = $callback();

			return self::set($key, $value, $expire);
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
		if(!file_exists(self::getCacheFile($key))) {
			return null;
		}

		return file_get_contents(self::getCacheFile($key));

	}

	/**
	 * 设置key的值,如果该cache已存在将会覆盖原有内容
	 *
	 * @param string $key
	 * @param string $value
	 * @param int $expire 缓存过期时间
	 *
	 * @return string $value 直接返回刚才设置的内容
	 */
	public static function set($key, $value, $expire=0)
	{
		// TODO 检查目录是否可写
		file_put_contents(self::getCacheFile($key), $value);
		return $value;
	}

	/**
	 * 清理指定的缓存内容
	 */
	public function delete($key)
	{
		@unlink(self::getCacheFile($key));
	}

	public static function getCacheFile($key)
	{
		return self::$cache_dir . "/simpie-cache-" . self::_hash_key($key);
	}

	private function _hash_key($key)
	{
		return md5($key);
	}
}
