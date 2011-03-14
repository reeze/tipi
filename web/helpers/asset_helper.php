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

	$url = (strpos($script_name, "http:") === 0 || strpos($script_name, "https:") === 0) ? $script_name : url_for("/javascripts/{$script_name}");
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

	$url = (strpos($style_name, "http:") === 0 || strpos($style_name, "https:") === 0) ? $style_name : url_for("/css/{$style_name}");
	return '<link href="' . $url . '" media="screen" rel="stylesheet" type="text/css" />';
}
