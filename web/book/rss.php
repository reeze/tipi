<?php

require_once "../config.php";
require_once "../lib/FeedWriter/FeedWriter.php";
require_once "../lib/SimpieView.php";
require_once "../models/BookPage.php";

/**
 * Used for user to subscribe tipi's update
 */

$home_page = "http://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}/../..";

$feed = new FeedWriter(RSS2);
$feed->setTitle("Tipi: 深入理解PHP内核");
$feed->setLink($home_page);
$feed->setDescription("TIPI是一本关于PHP内核实现方方面面的书籍, 有关Zend引擎, PHP扩展编写等等");
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
	$feed_item->setTitle($page['title']);
	$feed_item->setLink($home_page . "/book/?p=" . $page['page_name']);
	$feed_item->setDescription($book_page->getPageContent($render));
  	$feed_item->setDate($last_updated_at);
	$feed_item->addElement('author', 'Tipi-Team');

	$feed->addItem($feed_item);
}

// set the last modify time
$feed->setChannelElement('pubDate', date(DATE_RSS, $book_updated_at));

$feed->genarateFeed();

