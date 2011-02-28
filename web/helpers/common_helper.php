<?php

/**
 * For www.jiathis.com easy sharing
 * @see http://www.jiathis.com/help/html/support-media-website
 */
function get_jia_this($title='') {
	$sites = array(
		/* offset is the offset of the spirit image file: image/jiathis_icon.png */
		/* @see http://www.jiathis.com/share/?uid=905000 */
		'googlereader' => array('name' => "谷歌阅读器", 'offset' => -1088),
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
