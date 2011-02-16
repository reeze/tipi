<?php
  // This is a minimum example of using the class
  include("FeedWriter.php");
  
  //Creating an instance of FeedWriter class. 
  $TestFeed = new FeedWriter(RSS2);
  
  //Setting the channel elements
  //Use wrapper functions for common channel elements
  $TestFeed->setTitle('Testing & Checking the RSS writer class');
  $TestFeed->setLink('http://www.ajaxray.com/projects/rss');
  $TestFeed->setDescription('This is test of creating a RSS 2.0 feed Universal Feed Writer');
  
  //Image title and link must match with the 'title' and 'link' channel elements for valid RSS 2.0
  $TestFeed->setImage('Testing the RSS writer class','http://www.ajaxray.com/projects/rss','http://www.rightbrainsolution.com/images/logo.gif');
  
	//Detriving informations from database addin feeds
	$db->query($query);
	$result = $db->result;

	while($row = mysql_fetch_array($result, MYSQL_ASSOC))
	{
		//Create an empty FeedItem
		$newItem = $TestFeed->createNewItem();
		
		//Add elements to the feed item    
		$newItem->setTitle($row['title']);
		$newItem->setLink($row['link']);
		$newItem->setDate($row['create_date']);
		$newItem->setDescription($row['description']);
		
		//Now add the feed item
		$TestFeed->addItem($newItem);
	}
  
  //OK. Everything is done. Now genarate the feed.
  $TestFeed->genarateFeed();
  
?>
