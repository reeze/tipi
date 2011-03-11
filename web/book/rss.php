<?php

require_once "../lib/common.inc.php";
require_once "../lib/FeedWriter/FeedWriter.php";
require_once "../lib/SimpieView.php";
require_once "../models/BookPage.php";

/**
 * Used for user to subscribe tipi's update
 */
$feed = new FeedWriter(RSS2);
$feed->setTitle(SITE_NAME);
$feed->setLink(url_for("/", IS_ABSOLUTE_URL));
$feed->setDescription(SITE_DESC);
$feed->setChannelElement('language', 'zh-cn');

// get flat pages
$section_pages = BookPage::getFlatPages();

$book_updated_at = null;
foreach($section_pages as $page) {
	$book_page = new BookPage($page['page_name']);
	$book_file_path = $book_page->getPageFilePath();
	$last_updated_at = filemtime($book_file_path);

	if(!$book_updated_at || ($last_updated_at > $book_updated_at)) {
		$book_updated_at = $last_updated_at;
	}

	$render = new SimpieView($book_file_path, '../templates/layout/feed.php');

	$feed_item = $feed->createNewItem();
	$feed_item->setTitle($book_page->getAbsTitle());
	$feed_item->setLink(url_for_book($page['page_name'], IS_ABSOLUTE_URL));
	$feed_item->setDescription($book_page->getPageContent($render));
  	$feed_item->setDate($last_updated_at);
	$feed_item->addElement('author', 'TIPI-Team');

	$feed->addItem($feed_item);
}

// set the last modify time
$feed->setChannelElement('pubDate', date(DATE_RSS, $book_updated_at));

// fix html entriy problem in feedwriter
ob_start();
$feed->genarateFeed();
$xml = ob_get_clean();
$xml = str_replace('&raquo;', '&#187;', $xml);
echo $xml;
