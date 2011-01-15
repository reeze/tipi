<?php
/**
 * Adds GeSHi support to markdown / markdown extra code blocks.  Simply precede
 * the code block with "{{lang:LANGUAGE}}."
 * 
 * Example:
 * 
 * <code>
 * Hi, I'm a markdown document.
 * 
 *     {{lang:php}}
 *     echo("And I'm PHP");
 * 
 * Easy.
 * </code>
 * 
 * Thanks to Dougal Stanton for posting about Markdown and GeSHi in WordPress:
 * http://www.dougalstanton.net/blog/index.php/2007/12/15/syntax-highlighting-with-markdown-in-wordpress/
 * 
 * @author Anthony Bush
 * @since 2009-03-06
 * @version 1.0
 **/

/**
 * Define DocBlock
 **/

// Load unmodified geshi: http://qbnz.com/highlighter/
include(dirname(dirname(__FILE__)) . '/geshi/geshi.php');

// Load umodified Michel Fortin's PHP Markdown Extra: http://michelf.com/projects/php-markdown/
define('MARKDOWN_PARSER_CLASS',  'MarkdownExtraGeshi_Parser');
include(dirname(dirname(__FILE__)) . '/markdown_extra/markdown.php');

// Override code block parsing to use GeSHi for syntax highlighting.  By
// extending the class and overriding only what we need we increase ability to
// upgrade markdown.php indepently from this file.
class MarkdownExtraGeshi_Parser extends MarkdownExtra_Parser
{
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
}

?>
