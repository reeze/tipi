<?php

function link_to($url, $text=null, $options=array())
{
	return tag_for('a', $text, array_merge($options, array("href" => url_for($url))));
}

function tag_for($tag_name, $text, $options)
{
	$tag = "<$tag_name ";
	foreach($options as $key => $value) {
		$tag .= "{$key}=\"" . addslashes($value) . "\" ";
	}

	$tag .= ">{$text}</{$tag_name}>";

	return $tag;
}
