<?php

require_once '../lib/MarkdownPage.php';

define('NEWS_ROOT_PATH', ROOT_PATH . "/../news");

class News extends MarkdownPage
{
	protected static $base_dir = NEWS_ROOT_PATH;
	protected $page_name;

    /**
     * @param string $page_name 例如: 2011-03-20-first-release
     */
    public function __construct($page_name) {
		$this->page_name = $page_name;
		parent::__construct(array('file' => self::$base_dir . "/$page_name." . self::extension));
    }

	/**
	 * 返回新闻更新列表
	 * 寻找/news目录下的markdown文件，文件以时间为前缀，并以markdown结尾
	 */
	public static function findAll() {
		$news = glob(self::$base_dir . "/20*." . self::extension);

		// map the page
		$news_pages = array();
		foreach($news as $page) {
			// get page name
			$page = array_pop(explode('/', $page));
			list($page_name) = explode('.', $page);
			$news_pages[] = new self($page_name);
		}

		return $news_pages;
	}

	public function getUrl($absolute=IS_ABSOLUTE_URL)
	{
		return url_for_news($this->page_name, $absolute);	
	}

    public function getTitle() {
		return empty($this->meta['headers'][0]) ? null : $this->meta['headers'][0]['text'];
    }

	public function getAbsTitle() {
		return "[NEWS]" . $this->getTitle();	
	}
}

class NewsNotFoundException extends Exception {

}
