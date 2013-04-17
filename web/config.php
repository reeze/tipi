<?php
error_reporting(E_ALL ^ E_NOTICE);
date_default_timezone_set('Asia/Shanghai');

// 应用上线的地址
define('OLD_DOMAIN', 'php-internal.com');
define('ONLINE_HOSTNAME', 'www.php-internals.com');

// 从Github获取章节修订历史的接口信息
define('GITHUB_API_USER', 'reeze');
define('GITHUB_API_REPOS','tipi');
define('GITHUB_API_BRANCH', 'release');

define('IN_PROD_MODE', ($_SERVER['HTTP_HOST'] == ONLINE_HOSTNAME ? true : false)); 

// 现在线上部署在SAE上，web根目录和SAE的目录不一样。为子目录
define('WWW_ROOT_DIR', (IN_PROD_MODE ? 'web' : 'web'));

// 线上模式下开启缓存，也可以手动修改
define('ENABLE_PAGE_CACHE', IN_PROD_MODE);

define('DISQUS_SHORT_NAME', 'php-internals');
define('SITE_NAME', 'TIPI: 深入理解PHP内核');
define('SITE_DESC', 'TIPI(Thinking In PHP Internals)是一个开源项目，关注PHP的内部实现。PHP源码阅读、分析，Zend引擎，PHP扩展，脚本语言实现');

// 用于统计TIPI的分享情况
define('JIATHIS_UID', 905000);

define('ROOT_PATH', dirname(__FILE__));
define('TEMPLATE_PATH', ROOT_PATH . "/templates");
define('NEWS_ROOT_PATH', ROOT_PATH . "/../news");
define('TIPI_ROOT_PATH', dirname(ROOT_PATH));

/* TIPI的缓存保存目录，默认为临时目录，如果有需要请修改为自己设置的目录 */
define('TIPI_CACHE_DIR', dirname(__FILE__) . '/tmp');

/* Redirect all of the old request */
if(strpos($_SERVER['HTTP_HOST'], OLD_DOMAIN) !== false) {
	$location = "http://" . ONLINE_HOSTNAME . $_SERVER['REQUEST_URI'];
	header("Location: $location", true, 301);
	exit();
}
