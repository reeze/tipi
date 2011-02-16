<?php
  
  include("FeedWriter.php");
  
  //Creating an instance of FeedWriter class. 
  //The constant RSS1 is passed to mention the version
  $TestFeed = new FeedWriter(RSS1);
  
  //Setting the channel elements
  //Use wrapper functions for common elements
  //For other optional channel elements, use setChannelElement() function
  $TestFeed->setTitle('Testing the RSS writer class');
  $TestFeed->setLink('http://www.ajaxray.com/rss2/channel/about');
  $TestFeed->setDescription('This is test of creating a RSS 1.0 feed by Universal Feed Writer');
   
  //It's important for RSS 1.0 
  $TestFeed->setChannelAbout('http://www.ajaxray.com/rss2/channel/about');
  
  //Adding a feed. Genarally this protion will be in a loop and add all feeds.
  
  //Create an empty FeedItem
  $newItem = $TestFeed->createNewItem();
  
  //Add elements to the feed item
  //Use wrapper functions to add common feed elements
  $newItem->setTitle('The first feed');
  $newItem->setLink('http://www.yahoo.com');
  //The parameter is a timestamp for setDate() function
  $newItem->setDate(time());
  $newItem->setDescription('This is test of adding CDATA Encoded description by the php <b>Universal Feed Writer</b> class');
  //Use core addElement() function for other supported optional elements
  $newItem->addElement('dc:subject', 'Nothing but test');
  
  //Now add the feed item
  $TestFeed->addItem($newItem);
  
  //Adding multiple elements from array
  //Elements which have an attribute cannot be added by this way
  $newItem = $TestFeed->createNewItem();
  $newItem->addElementArray(array('title'=>'The 2nd feed', 'link'=>'http://www.google.com', 'description'=>'This is test of feedwriter class'));
  $TestFeed->addItem($newItem);
  
  //OK. Everything is done. Now genarate the feed.
  $TestFeed->genarateFeed();
  
?>
