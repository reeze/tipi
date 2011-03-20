<?php

/**
 * 该方法用于获取项目的base url, 由于项目没有使用单一入口的框架, 而又不想使用绝对路径, 
 * 绝对路径不方便开发, 也不方便读者下载项目代码后自行阅读
 * NOTICE: 该方法必须放在这个目录, 由于采用文件相对位置的方式来确定路径
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
	static $base_url = null;
	if(!$base_url) {
		$backtrace = debug_backtrace();

		$base_path = dirname(dirname(__FILE__));    // 这个路径是物理路径, 由于使用
		$document_root = $_SERVER['DOCUMENT_ROOT']; // 这个路径可能是软链接
		$script_root = dirname($_SERVER['SCRIPT_FILENAME']);
		// 获取最后的一个调用, 也就是被请求的脚本路径
		$request_script = dirname($backtrace[count($backtrace) - 1]['file']);
		$base_url = substr($script_root, strlen($document_root), strlen($script_root) - strlen($document_root));

		$offset = strlen($request_script) - strlen($base_path);
		if($offset) {
			$base_url = substr($base_url, 0, -$offset);
		}
	}

	$protocal = ($_SERVER['HTTP_PORT'] == '443') ? 'https' : 'http';
	$host     = $_SERVER['HTTP_HOST'];

	return ($absolute ? "{$protocal}://{$host}" : "" ) . $base_url . $url;
}

function redirect_to($url, $absolute=false)
{
	header('Location: ' . url_for($url, $absolute), 302);
	exit;
}


/**
 * For www.jiathis.com easy sharing
 * @see http://www.jiathis.com/help/html/support-media-website
 */
function get_jia_this($title='') {
	$sites = array(
		/* offset is the offset of the spirit image file: image/jiathis_icon.png */
		/* @see http://www.jiathis.com/share/?uid=905000 */
		'googlereader' => array('name' => "谷歌阅读", 'offset' => -1088),
		'google' 	=> array('name' => 'Google', 'offset' => -352),
		'twitter' 	=> array('name' => 'Twitter', 'offset' => -704),
		'douban'  	=> array('name' => '豆 瓣', 'offset' => -560),
		'xianguo' 	=> array('name' => '鲜 果', 'offset' => -592),
		'delicious' => array('name' => 'Delicious', 'offset' => -656),
		'zhuaxia' 	=> array('name' => '抓 虾', 'offset' => -576),
		'digg' 		=> array('name' => 'Digg', 'offset' => -672),
		'evernote' 	=> array('name' => 'Evernote', 'offset' => -1120),
		'reddit' 	=> array('name' => 'Reddit', 'offset' => -1280),
		'tsina' 	=> array('name' => '新浪微博', 'offset' => -96),
		'gmail' 	=> array('name' => 'Gmail', 'offset' => -272),
	);

	$title 	= urlencode($title . " | " . SITE_NAME);
	$url 	= urlencode("http://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}");

	$return = '';
	foreach($sites as $webid => $site) {
		$name 	= $site['name'];
		$offset = $site['offset'];
		$return .= "<a style='background-position: 0 {$offset}px' href='http://www.jiathis.com/send/?webid={$webid}&url={$url}&uid=" . JIATHIS_UID . "&title={$title}' target='_blank'>{$name}</a>";
	}

	return $return;
}
