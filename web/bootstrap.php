<?php

require_once dirname(__FILE__) . "/lib/SimpieCache.php";
require_once dirname(__FILE__) . "/lib/TIPI.php";
require_once dirname(__FILE__) . "/models/BookPage.php";

/* ---------------------------------------------------------------------------
用于进行一些基本的配置及初始化
----------------------------------------------------------------------------*/

/**
 * 通过prefix的方式控制版本，因为所有的内容都和发布的TIPI版本一致。
 * 这样在上线新版本时的缓存也能自动清除，唯一的问题是历史版本的缓存会继续保留
 * 不过对于TIPI来说丙没有什么问题
 */
SimpieCache::setGlobalCacheFilePrefix(TIPI::getVersion());
