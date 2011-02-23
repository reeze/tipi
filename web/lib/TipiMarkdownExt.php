<?php

/**
 * TIPI Project's markdown extension based on  Anthony Bush's Markdown geshi support
 *
 * - GeShi Code highlight
 * - Url rewrite
 * - Header extract
 */


define('MARKDOWN_PARSER_CLASS',  'TipiMarkdownExt');

// Load unmodified geshi: http://qbnz.com/highlighter/
include(dirname(__FILE__) . '/geshi/geshi.php');

// Load umodified Michel Fortin's PHP Markdown Extra: http://michelf.com/projects/php-markdown/
include(dirname(__FILE__) . '/markdown_extra/markdown.php');

class TipiMarkdownExt extends MarkdownExtra_Parser 
{
	/**
	 * Store the headers from the markdown
	 */
	private $_headers = array();


	function getHeaders()
	{
		return $this->_headers;
	}

	function _doCodeBlocks_callback($matches)
	{
		$codeblock = $matches[1];
		
		$codeblock = $this->outdent($codeblock);
		// trim leading newlines and trailing whitespace
		$codeblock = preg_replace(array('/\A\n+/', '/\s+\z/'), '', $codeblock);
		
		$codeblock = preg_replace_callback(
			'/^(\[([\w]+)\]\n|)(.*?)$/s', // {{lang:...}}greedy_code
			array($this, 'syntaxHighlight'),
			$codeblock
		);
		
		return "\n\n".$this->hashBlock($codeblock)."\n\n";
	}
	
	function syntaxHighlight($matches)
	{
		$geshi = new GeSHi($matches[3], empty($matches[2]) ? "txt" : $matches[2]);
		$geshi->enable_classes();
		$geshi->set_overall_style(""); // empty style
		return $geshi->parse_code();
	}

	static $quote_class = '';

	// Added by tipi-team for tipi book
	function _doBlockQuotes_callback($matches)
	{

		$bq = $matches[1];
		# trim one level of quoting - trim whitespace-only lines
		$bq = preg_replace('/^[ ]*>[ ]?|^[ ]+$/m', '', $bq);
		$bq = preg_replace('/^/m', "  ", $bq);

		// add class
		self::$quote_class = '';
		$bq = trim($bq);
		$bq = preg_replace_callback(
			'/^(\*\*([\w]+)\*\*\n|)(.*?)$/s',
			array($this, 'blockQuote'),
			$bq
			);
		$quote_class = self::$quote_class ? " class='" . strtolower(self::$quote_class) . "'" : '';

		$bq = $this->runBlockGamut($bq);		# recurse

		# These leading spaces cause problem with <pre> content, 
		# so we need to fix that:

		$bq = preg_replace_callback('{(\s*<pre>.+?</pre>)}sx', 
			array(&$this, '_DoBlockQuotes_callback2'), $bq);

		$bq = "\n". $this->hashBlock("<blockquote{$quote_class}>\n$bq\n</blockquote>")."\n\n";



		return $bq;
	}

	function blockQuote($matches)
	{
		self::$quote_class = empty($matches[2]) ? '' : $matches[2];
		return $matches[3];	
	}

	function _doImages_reference_callback($matches) {
		$whole_match = $matches[1];
		$alt_text    = $matches[2];
		$link_id     = strtolower($matches[3]);

		if ($link_id == "") {
			$link_id = strtolower($alt_text); # for shortcut links like ![this][].
		}

		$alt_text = $this->encodeAttribute($alt_text);
		if (isset($this->urls[$link_id])) {
			// TIPI replacement
			$_url = $this->urls[$link_id];
			$url = $this->__tipi_web_image_repalce($_url);
			$url = $this->encodeAttribute($url);

			$result = "<img src=\"$url\" alt=\"$alt_text\"";
			if (isset($this->titles[$link_id])) {
				$title = $this->titles[$link_id];
				$title = $this->encodeAttribute($title);
				$result .=  " title=\"$title\"";
			}
			$result .= $this->empty_element_suffix;
			$result = $this->hashPart($result);
		}
		else {
			# If there's no such link ID, leave intact:
			$result = $whole_match;
		}

		return $result;
	}

	function _doImages_inline_callback($matches) {
		$whole_match	= $matches[1];
		$alt_text		= $matches[2];
		$url			= $matches[3] == '' ? $matches[4] : $matches[3];
		$title			=& $matches[7];

		// TIPI replacement
		$url = $this->__tipi_web_image_repalce($url);

		$alt_text = $this->encodeAttribute($alt_text);
		$url = $this->encodeAttribute($url);
		$result = "<img src=\"$url\" alt=\"$alt_text\"";
		if (isset($title)) {
			$title = $this->encodeAttribute($title);
			$result .=  " title=\"$title\""; # $title already quoted
		}
		$result .= $this->empty_element_suffix;

		return $this->hashPart($result);
	}

	function __tipi_web_image_repalce($url) {
		// don't replace the absolute urls
		if(strpos($url, ':') === false) {
			return str_replace('images/', 'book/images/', $url);
		}

		return $url;
	}

	// Header 
	function _doHeaders_callback_setext($matches) {
		# Terrible hack to check we haven't found an empty list item.
		if ($matches[2] == '-' && preg_match('{^-(?: |$)}', $matches[1]))
			return $matches[0];
		
		$level = $matches[2]{0} == '=' ? 1 : 2;
		$block = "<h$level>".$this->runSpanGamut($matches[1]). "<a name='{$matches[1]}'></a>" . "</h$level>";

		$this->_headers[] = array('text' => $matches[1], 'level' => $level);
		return "\n" . $this->hashBlock($block) . "\n\n";
	}

	function _doHeaders_callback_atx($matches) {
		$level = strlen($matches[1]);
		$block = "<h$level>".$this->runSpanGamut($matches[2]). "<a name='{$matches[2]}'></a>" . "</h$level>";

		$this->_headers[] = array('text' => $matches[2], 'level' => $level);
		return "\n" . $this->hashBlock($block) . "\n\n";
	}

}
