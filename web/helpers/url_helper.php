<?php

define('IS_ABSOLUTE_URL', true);

function url_for_book($page_name, $absolute=false) {
	return url_for("/book/?p=" . $page_name, $absolute);
}

function url_for_news($page_name, $absolute=false) {
	return url_for("/news/?p=" . $page_name, $absolute);
}

function is_external_url($url)
{
	return (strpos($url, "http:") === 0 || strpos($url, "https:") === 0);
}

/**
 * 该方法用于获取项目的base url, 由于项目没有使用单一入口的框架, 而又不想使用绝对路径, 
 * 绝对路径不方便开发, 也不方便读者下载项目代码后自行阅读
 * NOTICE: 该方法所在文件必须放在这个目录, 由于采用文件相对位置的方式来确定路径
 *
 * 该方法利用backtrace()方法获取到正在被处理脚本的路径(物理路径), 然后获取config.php文件的路径,
 * 进行对比,就可以得出正在被请求的文件和根目录的"距离", 以此来生成项目base url.
 *
 * @param string  $url 		相对项目web根目录的路径
 * @param boolean $absolute 是否生产绝对地址
 * @sample:
 * 		url_for("/") 这返回项目的主目录一般为根域名, 而在开发时可能不是, 
 * 		例如链接到书籍首页的地址为: url_for("/book/?p=index")
 */
function url_for($url, $absolute=false)
{
	if(is_external_url($url)) return $url;

	static $base_url = null;
	if($base_url === null) {
		$backtrace = debug_backtrace();

		$base_path 		= dirname(dirname(__FILE__));    		// 这个路径是项目的物理路径,
		$document_root	= realpath($_SERVER['DOCUMENT_ROOT']); 	// 这个路径可能是软链接

		// 比如phpcloud的document_root和预期的有包含关系不存在，如果这样则把项目目录修改为文档根目录
		if(strpos($base_path, $document_root) !== 0) {
			$document_root = $base_path;
		}
		$script_root = realpath(dirname($_SERVER['SCRIPT_FILENAME']));
		// 获取最后的一个调用, 也就是被请求的脚本路径
		$request_script = realpath(dirname($backtrace[count($backtrace) - 1]['file']));
		$base_url = substr($script_root, strlen($document_root), strlen($script_root) - strlen($document_root));

		$offset = strlen($request_script) - strlen($base_path);
		if($offset) {
			$base_url = substr($base_url, 0, -$offset);
		}

		// SAE hack
		if(WWW_ROOT_DIR) {
			$base_url = substr($base_url, 0, -(strlen(WWW_ROOT_DIR) + 1));
		}
	}

	$protocal = (isset($_SERVER['HTTP_PORT']) && $_SERVER['HTTP_PORT'] == '443') ? 'https' : 'http';
	$port	  = (isset($_SERVER['HTTP_PORT']) && $_SERVER['HTTP_PORT']) ? $_SERVER['HTTP_PORT'] : "";
	$port_str = $port ? (($port == '80' || $port == '443') ? "" : ":" . $port) : "";

	// !isset的情况下就是在命令行模式下的，这时使用默认的线上host信息
	$host     = isset($_SERVER['HTTP_HOST']) && !$absolute ? $_SERVER['HTTP_HOST'] : ONLINE_HOSTNAME;

	return ($absolute ? "{$protocal}://{$host}{$port_str}" : "" ) . $base_url . $url;
}

