<?php

function javascript_include_tag($script_names)
{
	if(is_array($script_names)) {
		$tags = '';
		foreach($script_names as $script) {
			$tags .= javascript_include_tag($script) . "\n";
		}

		return $tags;
	}

	$url = is_external_url($script_names) ?  $script_names : url_for("/javascripts/{$script_names}?v=" . TIPI::getVersion());
	return '<script src="' . $url . '" type="text/javascript"></script>';
}

function stylesheet_include_tag($style_names)
{
	if(is_array($style_names)) {
		$tags = '';
		foreach($style_names as $style) {
			$tags .= stylesheet_include_tag($style) . "\n";
		}

		return $tags;
	}

	$url = is_external_url($style_names) ? $style_names : url_for("/css/{$style_names}?v=" . TIPI::getVersion());
	return '<link href="' . $url . '" media="screen" rel="stylesheet" type="text/css" />';
}

/**
 * 和stylesheet_include_tag()方法类似，不过这个方法用于将样式直接嵌入到页面里，而不是引用
 */
function stylesheet_include_tag_embed($style_names)
{
	$styles = '';
	if(!is_array($style_names)) $style_names = array($style_names);
	
	foreach($style_names as $style) {
		$styles .= file_get_contents(ROOT_PATH . "/css/$style");
	}

	return "<style type='text/css'>	$styles</style>";
}
