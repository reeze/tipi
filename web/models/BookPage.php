<?php

require_once dirname(__FILE__) . '/../lib/MarkdownPage.php';

/**
 * 图书页逻辑
 */
class BookPage extends MarkdownPage
{

    protected $title 		= NULL;
    protected $base_dir 	= NULL;
    protected $page_name 	= NULL;
    protected static $_allArticleList = NULL;

	protected $headers 	= array();

    /**
     * @param string $page_path 	书籍页面的路径, 例如chapt01/01-04-summary
     * @param string $book_base_dir 书籍目录地址, 默认值为TIPI项目路径的book目录
     */
    public function __construct($page_name, $base_dir='../../book', $is_for_print=false) {
        $this->page_name = $page_name;
        $this->base_dir = $base_dir;

        if ($title = $this->getTitle()) {
            $this->title = $title;
        }

		$parser = $is_for_print ? new TipiMarkdownExt(array('header' => array($this, 'reAssignHeaderLevel'))) : null;

		parent::__construct(array('file' => $this->getPageFilePath()), $parser);

		// markdown文件的大纲标题信息
		$this->headers = is_array($this->meta['headers']) ? $this->meta['headers'] : array();
    }
	
	// Pdf版的内容需要重新对标题级别调整一下以便生成目录
	public function reAssignHeaderLevel($level) {
		if($this->isChapterIndex() && $level == 1) return $level; 

		// 所有层级增加一级
		return $level + $this->getPageLevel() - 1;
	}

	public function isChapterIndex() {
		return $this->getPageLevel() <= 1;
	}

	public function getPageLevel() {
		$filename = array_pop(explode('/', $this->page_name));
		return self::_getChapterLevelByFilename($filename);	
	}

	public function getHeaders() {
		return $this->headers;
	}

	public function getOutlineHeaders() {
		$headers = $this->headers;

		// 第一个是标题,就不显示了
		if(isset($headers[0]) && $headers[0]['level'] == 1) {
			array_shift($headers);
		}

		return $headers;
	}

    public function getPrevPage() {
        // 返回上一页文件
        return $this->_getRequestPage(-1);
    }

    public function getNextPage() {
        // 下一页
        return $this->_getRequestPage(1);
    }

    /**
     * 获取当前页的前一页以及下一页信息. 该方法依赖于文件的组织形式
     * 得到所有的文章列表，按文件名排序，得到上一页和下一页
     * 需要确保文件的名称是以章节等排序而成
     */
    private function _getRequestPage($step) {

        $allArticleList = $this->_getAllArticleList();

        $count = count($allArticleList);

        $index = 0;
        $currentFilename = $this->getPageFilePath();
        if (is_array($allArticleList)) {
            foreach ($allArticleList as $key => $row) {
                if (strpos($row, $currentFilename) !== FALSE) {
                    $index = $key;
                    break;
                }
            }
        }
        $index = ($index + $step) % $count;

        if (isset($allArticleList[$index])) {
             $filepath = substr($allArticleList[$index], 11, - (strlen(self::extension) + 1));
             return new self($filepath);
        }
       

		return NULL;
    }

    /**
     * 获取所有的文章列表，以存放在base_dir目录，以chapt开头的目录
     */
    private function _getAllArticleList() {
        if (self::$_allArticleList == NULL) {
            $articleList = glob($this->base_dir . "/chapt*/*");
            sort($articleList);

            self::$_allArticleList = $articleList;
        }

        return self::$_allArticleList;
    }

    private static function _getPageNameByFileName($file_name) {
        $found_file_name = array_pop(explode("/", $file_name));
        list($found_page_name) = explode(".", $found_file_name);

        return $found_page_name;
    }

    /**
     * 返回本页的标题, 根据页面的命名约定, 文件的第一行为本页的标题
     *
     * @return string
     */
    public function getTitle() {
        if ($this->title === NULL) {
			if(isset($this->headers[0]) && $this->headers[0]['level'] == 1) {
				$this->title = $this->headers[0]['text'];	
			}
        }

        return $this->title;
    }

	public function getUrl($absolute=IS_ABSOLUTE_URL)
	{
		return url_for_book($this->page_name, $absolute);	
	}

	
	/**
	 * 返回完整的章节名称, 例如在RSS输出等地方需要完整的章节名称.
     *
	 * @return string
	 */
	public function getAbsTitle($html_encode=false) {
		// "chapt02/02-03-page-sample  or index etc.
        if (strpos($this->page_name, '/') === FALSE) {
            $page = $this->page_name;
        } else {
            list($page, $real_page) = explode('/', $this->page_name);
            if ($real_page) {
                $page = $real_page;
            }
        }

        $chapt_seq = NULL;
        $sect_seq = NULL;
        $sub_seq = NULL;

        if (strpos($page, '-') === FALSE) {
            $chapt_seq = $page;
        }else{
            list($chapt_seq, $sect_seq, $sub_seq) = explode('-', $page);
        }

        

		$num_chs_map = array(
			'01' => "一",
			'02' => '二',
			'03' => '三',
			'04' => '四',
			'05' => '五',
			'06' => '六',
			'07' => '七',
			'08' => '八',
			'09' => '九',
			'10' => '十',
			'11' => '十一',
			'12' => '十二',
			'13' => '十三',
			'14' => '十四',
			'15' => '十五',
			'16' => '十六',
			'17' => '十七',
			'18' => '十八',
			'19' => '十九',
			'20' => '二十',
		);
		
		$title = $this->getTitle();

		// 类似目录以及附录等页面
		$is_index_page = ((int)$chapt_seq == 0);

		// 三级页面
		$is_sub_section_page = ($sub_seq != null && (int)$sub_seq > 0);

		// 每章的介绍页面
		$is_section_index_page = (!$is_index_page && !$is_sub_section_page && $sect_seq == '00');
		
		// 小节页面
		$is_section_page = (!$is_section_index_page && (int)$sect_seq > 0);


		if($is_sub_section_page) {
			$prefix = "第" . (isset($num_chs_map[$chapt_seq]) ? $num_chs_map[$chapt_seq] : $chapt_seq) . "章 » ";
			$prefix .= "第" . (isset($num_chs_map[$sect_seq]) ? $num_chs_map[$sect_seq] : $sect_seq) . "节 » ";
			$title = $prefix . $title;
		}
		else if($is_section_page) {
			$title = "第" . (isset($num_chs_map[$chapt_seq]) ? $num_chs_map[$chapt_seq] : $chapt_seq) . "章 » " . $title; 
		}

		// $is_section_index_page and $is_index_page and all other page
		// return the page's title

		return ($html_encode ? htmlentities($title) : $title);
	}

    private static function _getTitleFromFileName($file, $default='NO_COMPLETED') {
        $fp = @fopen($file, "r");
        $title = $default;
        if ($fp && $title_line = fgets($fp)) {
            // 标题均以#开头.
            $title = trim($title_line, "# \n\r\0");
            fclose($fp);
        }

        return $title;
    }

    /**
     * 返回该页面的绝对路径.主要用来渲染markdown, 隐藏相关逻辑
     *
     * @return string
     */
    public function getPageFilePath() {
        return "{$this->base_dir}/{$this->page_name}." . self::extension;
    }

    public function getPageName() {
        return $this->page_name;
    }

	/**
	 * 返回当前页的父级页面, 主要使用来得到页面的导航信息
	 */
	public function getPagePath() {
		$chapt_lists = self::getChapterList();
		return $this->_find_parent_path($chapt_lists, $this->page_name);
	}

	private function _find_parent_path($list, $page_name) {
		$path = array();
		foreach($list as $l) {
			if($l['page_name'] == $page_name) {
				$path[] = $l;

				return $path;
			}

			if($l['list']) {
				foreach($l['list'] as $s_l) {
					if($s_l['page_name'] == $page_name)	{
						$path[] = $l;
						$path[] = $s_l;
						return $path;
					}

					if($s_l['list']) {
						foreach($s_l['list'] as $sub_l) {
							if($sub_l['page_name'] == $page_name) {
								$path[] = $l;
								$path[] = $s_l;
								$path[] = $sub_l;

								return $path;
							}
						}
					}
				}
			}
		}
		return $path;
	}

    /**
     * 返回章节列表
     * TODO add cache
     */
    public static function getChapterList($base_dir = "../../book") {
        // 只处理两个层级,章/节
        static $list = array();

		if(!empty($list)) return $list;

        $list[] = array(
            "page_name" => "index",
            "title" => "目录",
            "list" => array()
        );

        // 生成章列表
        $top_level_chapters = glob($base_dir . "/chapt*");
        foreach ($top_level_chapters as $chapt) {
            $data = self::_initChapterListData($chapt);
            $sub_list = self::getSubChapterList($data, $data['root']);

            if (empty($sub_list)) {
                continue;
            }

            $list = array_merge($list, $sub_list);
        }

        // 其他的文件
        $other_files = glob($base_dir . "/*." . self::extension);
        foreach ($other_files as $file) {
            $title = self::_getTitleFromFileName($file, NULL);
            $page_name = self::_getPageNameByFileName($file);

            if ($page_name == 'index' || ! $title)
                continue;

            $list[] = array(
                'page_name' => $page_name,
                'title' => $title,
            );
        }

        return $list;
    }

	public static function getFlatPages($chapt_lists=null) {
		if($chapt_lists === null) {
			$chapt_lists = self::getChapterList();
		}
		if(empty($chapt_lists)) return array();

		$pages = array();
		foreach($chapt_lists as $chapt) {
			$pages[] = array('title' => $chapt['title'], 'page_name' => $chapt['page_name']);
			if(isset($chapt['list'])) {
				$pages = array_merge(
					$pages,
					self::getFlatPages($chapt['list'])
				);
			}
		}

		return $pages;
	}

	public static function getFlatPagesArray($is_for_print=false) {
		$pages = array();
		foreach(self::getFlatPages() as $page) {
			$pages[] = new self($page['page_name'], '../../book', $is_for_print);
		}
	
		return $pages;
	}

	public static function getFlatPagesArrayForPrint() {
		return self::getFlatPagesArray(true);	
	}

	/**
	 * TIPI的目录结构经常会发生变化, 导致地址经常会发生变化, 为此需要对老的地址做一些处理,
	 * 否则一些用户收藏的地址就会无效, 所以采用两种方式来处理:
	 *
	 * 1. 如果能找到和请求的页面地址非常接近的页面, 比如只是修改了一小部分的内容.则自动跳转到新页面
	 * 2. 如果找到的页面匹配度不够, 则给出比较相近的页面供用户参考.
	 *
	 * 这个方法的目的是找到和$page_name比较接近的页. 并求出相似度
	 *
	 * @param $page_name string
	 *
	 * return array  array('0.8' => 'chapt03/03-01-00-**')
	 */
	public static function getSuggestPagesFromName($page_name) {
		$match_pages = array();
		$pages = self::getFlatPagesArray();
		foreach($pages as $page) {
			// TODO	
		}
		return array();	
	}

	private static function _strDistance($str1, $str2) {
		// TODO	
	}

    /**
     * 获取子章节列表
     * 依赖于文件名
     * @param <type> $chapt_dir
     * @param <type> $level
     * @return <type>
     */
    public static function getSubChapterList($data, $parent, $level = 1, $recursive = TRUE) {
        $list = $data['list'];
        $chapt_name = $data['chapt_name'];
        $max_level = $data['max_level'];

        if ($max_level < $level) {
            return NULL;
        }

        $sub_list = array();
        foreach ($list as $row) {
            if ($row['level'] == $level && $row['parent'] == $parent) {
                $title = $row['title'];
                if (!$title) {
                    continue;
                }

                $page_name = $row['page_name'];
                $sub_chapter = array(
                    'page_name' => "{$chapt_name}/{$page_name}",
                    'title' => $title,
                );

                if ($recursive === TRUE) {
                    $sub_chapter['list'] = self::getSubChapterList($data, $row['id'], $level + 1, $recursive);
                }

                $sub_list[] = $sub_chapter;
            }
        }

        return $sub_list;
    }

    /**
     * 初始化单个文件路径章节列表数据
     * @param <type> $chapterDir
     * @return <type> 
     */
    private static function _initChapterListData($chapterDir) {
        $data = array();
        $max_level = 0;
        $chapt_name = array_pop(explode("/", $chapterDir));
        $root = '';

        $files = glob($chapterDir . "/*." . self::extension);

        foreach ($files as $file) {
            $item = array();
            $filename = basename($file);
            $level = self::_getChapterLevelByFilename($filename);

            $item['level'] = $level;
            $item['filename'] = $filename;
            $item['title'] = self::_getTitleFromFileName($file, NULL);
            $item['page_name'] = self::_getPageNameByFileName($file);
            $item['id'] = self::_getIdByFilenameAndLevel($filename, $level);
            $item['parent'] = self::_getParentByFilenameAndLevel($filename, $level);

            $max_level = $max_level < $item['level'] ? $item['level'] : $max_level;
            $root = $level == 1 ? $item['id'] : $root;

            $data[] = $item;
        }

        return array('chapt_name' => $chapt_name, 'list' => $data,
            'max_level' => $max_level, 'root' => $root);
    }

    /**
     * 依据文件名判断所在章节的等级
     * @param <type> $filename
     */
    private static function _getChapterLevelByFilename($filename) {
        $structure = explode("-", $filename);

        $level = 0;
        if (is_array($structure) && ! empty($structure)) {
            foreach ($structure as $row) {
                if (!is_numeric($row)) {
                    break;
                }

                if (intval($row) > 0) {
                    $level++;
                }
            }
        }

        return $level;
    }

    /**
     * 根据文件名和所在等级 生成父结点
     * 依赖于文件名结构
     * @param <type> $filename
     * @param <type> $level
     * @return <type>
     */
    private static function _getParentByFilenameAndLevel($filename, $level) {
        if ($level <= 1) {
            return self::_getIdByFilenameAndLevel($filename, $level);
        }

        return self::_getIdByFilenameAndLevel($filename, $level - 1);
    }

    /**
     * 根据文件名和所在等级 生成ID
     * 依赖于文件名结构
     * @param <type> $filename
     * @param <type> $level
     * @return <type>
     */
    private static function _getIdByFilenameAndLevel($filename, $level) {
        $items = explode("-", $filename);

        return implode("-", array_slice($items, 0, $level));
    }

}

class BookPageNotFoundException extends Exception {

}
