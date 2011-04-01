<h1><?php echo $title; ?></h1>

<ul>
<?php foreach($news_array as $news): ?>
	<li><span><?php echo $news->getPubDate(); ?></span> <a href="<?php echo $news->getUrl(); ?>"><?php echo $news->getTitle(); ?></a></li>
<?php endforeach; ?>
</ul>
