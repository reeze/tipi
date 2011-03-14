<?php

function javascript_include_tag($script_name)
{
	if(is_array($script_name)) {
		$tags = '';
		foreach($script_name as $script) {
			$tags .= javascript_include_tag($script) . "\n";
		}

		return $tags;
	}

	$url = __is_url_external($script_name) ?  $script_name : url_for("/javascripts/{$script_name}");
	return '<script src="' . $url . '" type="text/javascript"></script>';
}

function stylesheet_include_tag($style_name)
{
	if(is_array($style_name)) {
		$tags = '';
		foreach($style_name as $style) {
			$tags .= stylesheet_include_tag($style) . "\n";
		}

		return $tags;
	}

	$url = __is_url_external($style_name) ? $style_name : url_for("/css/{$style_name}");
	return '<link href="' . $url . '" media="screen" rel="stylesheet" type="text/css" />';
}


function __is_url_external($url)
{
	return (strpos($url, "http:") === 0 || strpos($url, "https:") === 0);
}
