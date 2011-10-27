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

	public function __construct($data, $parser=null)
	{
		$this->file = isset($data['file']) ? $data['file'] : NULL;
		$this->text = isset($data['text']) ? $data['text'] : NULL;

		if($this->file) {
			if(!file_exists($this->file)) throw new PageNotFoundException("Page Not Found:{$this->file}");
			$this->text = file_get_contents($this->file);
		}

		if(!$parser) {
			$parser = new TipiMarkdownExt();
		}
		$this->output = $parser->transform($this->text);

		$this->meta['headers'] = $parser->getHeaders();
	}

	public function toHtml()
	{
		return $this->output;
	}

	public function getLastUpdatedAt()
	{
		static $last_updated_at = null;
		if($last_updated_at) return $last_updated_at;

		return ($last_updated_at = $this->file ? filemtime($this->file) : null);
	}

	public function getPageFilePath() {
		return $this->file ? $this->file : null;
	}

	public function getPageContent($render=null) {
		if($render) {
			return $render->render(null, true);
		}
		else {
			return file_get_contents($this->getPageFilePath());
		}
	}
}

class PageNotFoundException extends Exception
{
}
