<?php
error_reporting(E_ALL ^ E_NOTICE);
date_default_timezone_set('Asia/Shanghai');

// 应用上线的地址
define('ONLINE_HOSTNAME', 'www.php-internal.com');

define('IN_PROD_MODE', ($_SERVER['HTTP_HOST'] == ONLINE_HOSTNAME ? true : false)); 

// 线上模式下开启缓存，也可以手动修改
define('ENABLE_PAGE_CACHE', IN_PROD_MODE);

define('DISQUS_SHORT_NAME', 'tipiphp');
define('SITE_NAME', 'TIPI: 深入理解PHP内核');
define('SITE_DESC', 'TIPI(Thinking In PHP Internal)是一个开源项目，关注PHP的内部实现。PHP源码阅读、分析，Zend引擎，PHP扩展，脚本语言实现');


define('JIATHIS_UID', 905000);

define('ROOT_PATH', dirname(__FILE__));
define('TEMPLATE_PATH', ROOT_PATH . "/templates");
define('NEWS_ROOT_PATH', ROOT_PATH . "/../news");
define('TIPI_ROOT_PATH', dirname(ROOT_PATH));
