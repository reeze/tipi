<?php
/**
 * 图书页逻辑
 */
class BookPage
{
	private $title = null;
	private $base_dir = null;
	private $page_name= null;

	// all pages are written in markdown
	const extension = 'markdown';

	/**
	 * @param string $page_path 	书籍页面的路径, 例如chapt01/01-04-summary
	 * @param string $book_base_dir 书籍目录地址, 默认值为TIPI项目路径的book目录
	 */
	public function __construct($page_name, $base_dir='../../book')
	{
		$this->page_name = $page_name;
		$this->base_dir  = $base_dir;

		if($title = $this->getTitle()) {
			$this->title = $title;	
		}
	}

	public function getPrevPage()
	{
		// 返回上一页文件
		return $this->_getRequestPage(-1);
	}

	public function getNextPage()
	{
		// 下一页
		return $this->_getRequestPage(1);
	}

	/**
	 * 获取当前页的前一页以及下一页信息. 该方法依赖于文件的组织形式
	 */
	private function _getRequestPage($step)
	{
		$page_name = $this->page_name;

		// chapt01/01-01-file-name
		list($chapt, $file) = explode("/", $page_name);			
		list($chapt_no, $sub_no) = explode("-", $file);

		// 新文件的序号, 并尝试找到需要的文件
		$next_no = str_pad($sub_no + $step, 2, '0', STR_PAD_LEFT);
		$new_page_file = "{$chapt_no}-{$next_no}-*." . self::extension;

		$base_path = dirname($this->getPageFilePath());
		$found_files = glob("{$base_path}/{$new_page_file}");

		if(isset($found_files[0])) {
			$found_page_name = $this->_getPageNameByFileName($found_files[0]);

			return new self("{$chapt}/{$found_page_name}");
		}
		else {
			// 没有找到则看看是否有下/上一章内容	
			$new_chapt_no = str_pad($chapt_no + $step, 2, '0', STR_PAD_LEFT);
			$found_files = glob("{$base_path}/../chapt{$new_chapt_no}/*");

			if($count=count($found_files)) {
				$index = ($step == 1 ? 0 : $count -1);
				$found_page_name = $this->_getPageNameByFileName($found_files[$index]);

				return new self("chapt{$new_chapt_no}/{$found_page_name}");
			}
		}
	}
	
	private function _getPageNameByFileName($file_name)
	{
			$found_file_name = array_pop(explode("/", $file_name));
			list($found_page_name) = explode(".", $found_file_name);

			return $found_page_name;
	}

	/**
	 * 返回本页的标题, 根据页面的命名约定, 文件的第一行为本页的标题
	 *
	 * @return string
	 */
	public function getTitle()
	{
		if($this->title === null) {
			$fp = @fopen($this->getPageFilePath(), "r");
			$title = "TIPI";
			if($fp && $title_line = fgets($fp)) {
				// 标题均以#开头.
				$title = trim($title_line, "# \n\r\0");
				fclose($fp);
			}

			$this->title = $title;
		}

		return $this->title;
	}

	/**
	 * 返回该页面的绝对路径.主要用来渲染markdown, 隐藏相关逻辑
	 *
	 * @return string
	 */
	public function getPageFilePath()
	{
		// TODO check whether the book page is the real book page

		return "{$this->base_dir}/{$this->page_name}." . self::extension;
	}

	public function getPageName()
	{
		return $this->page_name;	
	}
}

class BookPageNotFoundException extends Exception {}
