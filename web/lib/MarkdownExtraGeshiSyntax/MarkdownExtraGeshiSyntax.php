<?php
/**
 * This extension works similar to the one for pmwiki, except instead of using
 * (:markdown:)Markdown Syntax Here(:markdownend:) you use <markdown>Markdown
 * Extra Syntax Here with GeSHi code blocks</markdown>. The end tag is optional.
 * 
 * Pros:
 * - Since you have to enable it, it doesn't break all the other parts of the
 *   page (why does MediaWiki even call parsers on anything but the document
 *   text?)
 * - It does not pass control on to MediaWiki, so all Markdown Extra features
 *   should work and the markdown you write will be that much more portable (e.g.
 *   b/c you can't mix in Wiki-style links)
 * 
 * Cons:
 * - It does not pass control on to MediaWiki, so you can't mix in Wiki-style
 *   links or other elements.
 * - No auto-generation of TOC.
 * - No auto-generation of section "edit" links.
 * 
 * @author Anthony Bush
 * @since 2009-03-06
 * @version 1.0
 **/

/**
 * Define DocBlock
 **/

if (!defined('MEDIAWIKI')) die();

$wgExtensionCredits['parserhook'][] = array(
    'name'        => 'MarkdownExtraGeshiSyntax',
    'author'      => 'Anthony Bush',
    'url'         => 'http://anthonybush.com/projects/markdown_extra_geshi/',
    'description' => 'Adds support for markdown extra syntax with Geshi support.',
    'version'     => '1.0'
);

$wgExtensionFunctions[] = 'ExtensionMarkdownTag';

function ExtensionMarkdownTag()
{
	global $wgParser;
	$wgParser->setHook('markdown', 'Markdown');
}

require_once('markdown_geshi/markdown_geshi.php');
