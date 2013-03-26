<?php

require_once "../lib/common.inc.php";
require_once "../lib/FeedWriter/FeedItem.php";
require_once "../lib/FeedWriter/FeedWriter.php";
require_once "../lib/SimpieView.php";
require_once "../models/News.php";
require_once "../models/BookPage.php";

$news = News::findAll();
$book_pages = BookPage::getFlatPagesArray();

/** Merge the pages **/
$pages = array_merge($news, $book_pages);

/** Sort Desc **/
function _sort_pages($page1, $page2) {
	if($page1->getLastUpdatedAt() == $page2->getLastUpdatedAt()) return 0;

	// Desc
	return ($page1->getLastUpdatedAt() > $page2->getLastUpdatedAt()) ? -1 : 1;
}

usort($pages, '_sort_pages');

/** Start Feed Output **/
$last_updated_at = null;

$feed = new FeedWriter(RSS2);
$feed->setTitle(SITE_NAME);
$feed->setLink(url_for("/", IS_ABSOLUTE_URL));
$feed->setDescription(SITE_DESC);
$feed->setChannelElement('language', 'zh-cn');

// get flat pages
foreach($pages as $page) {
	$page_updated_at = $page->getLastUpdatedAt();

	if(!$last_updated_at || ($page_updated_at > $last_updated_at)) {
		$last_updated_at = $page_updated_at;
	}

	$render = new SimpieView($page->getPageFilePath(), '../templates/layout/feed.php');

	$feed_item = $feed->createNewItem();
	$feed_item->setTitle($page->getAbsTitle());
	$feed_item->setLink(($page->getUrl(IS_ABSOLUTE_URL)));
	$feed_item->setDescription($page->getPageContent($render));
  	$feed_item->setDate($page_updated_at);
	$feed_item->addElement('author', 'TIPI-Team');

	$feed->addItem($feed_item);
}

// set the last modify time
$feed->setChannelElement('pubDate', date(DATE_RSS, $last_updated_at));

// fix html entriy problem in feedwriter
ob_start();
$feed->genarateFeed();
$xml = ob_get_clean();
$xml = str_replace('&raquo;', '&#187;', $xml);
echo $xml;
