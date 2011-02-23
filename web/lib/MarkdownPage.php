<?php

require_once 'TipiMarkdownExt.php';

/**
 * Markdown page
 *
 */
class MarkdownPage
{
	protected $output = NULL;
	protected $file   = NULL;
	protected $text	  = NULL;

	// Meta data: headers, links and etc
	protected $meta	  = array();

    const extension = 'markdown';

	public function __construct($data)
	{
		$this->file = isset($data['file']) ? $data['file'] : NULL;	
		$this->text = isset($data['text']) ? $data['text'] : NULL;	

		if($this->file) {
			$this->text = file_get_contents($this->file);	
		}

		$parser = new TipiMarkdownExt();
		$this->output = $parser->transform($this->text);

		$this->meta['headers'] = $parser->getHeaders();
	}

	public function toHtml()
	{
		return $this->output;	
	}
}
