<?php

/**
 * 图书页逻辑
 */
class BookPage {

    private $title = NULL;
    private $base_dir = NULL;
    private $page_name = NULL;

    // all pages are written in markdown
    const extension = 'markdown';

    /**
     * @param string $page_path 	书籍页面的路径, 例如chapt01/01-04-summary
     * @param string $book_base_dir 书籍目录地址, 默认值为TIPI项目路径的book目录
     */
    public function __construct($page_name, $base_dir='../../book') {
        $this->page_name = $page_name;
        $this->base_dir = $base_dir;

        if ($title = $this->getTitle()) {
            $this->title = $title;
        }
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
     */
    private function _getRequestPage($step) {
        $page_name = $this->page_name;
        list($chapt, $file) = explode("/", $page_name);

        $filename = basename($page_name);
        list($chapt_no) = explode("-", $filename);
        $level = self::_getChapterLevelByFilename($filename);

        $new_file_anme = $this->_createNewFilename($filename, $level, $step);

        if ($new_file_anme) {
            $found_page_name = $this->_getPageNameByFileName($new_file_anme);

            return new self("{$chapt}/{$found_page_name}");
        } else {

            // 没有找到则看看是否有下/上一章内容
            $base_path = dirname($this->getPageFilePath());
            $new_chapt_no = str_pad($chapt_no + $step, 2, '0', STR_PAD_LEFT);
            $found_files = glob("{$base_path}/../chapt{$new_chapt_no}/*");

            if ($count = count($found_files)) {
                $index = ($step == 1 ? 0 : $count - 1);
                $found_page_name = self::_getPageNameByFileName($found_files[$index]);

                return new self("chapt{$new_chapt_no}/{$found_page_name}");
            }
        }
    }

    /**
     * 生成新的文件地址
     * @param <type> $filename
     * @param <type> $level
     * @param <type> $step
     * @return <type>
     */
    private function _createNewFilename($filename, $level, $step) {
        if ($level == 1) {
            return NULL;
        }

        $items = explode("-", $filename);

        $index = intval($items[$level - 1] + $step);

        $items[$level - 1] = str_pad($index, 2, '0', STR_PAD_LEFT);

        $new_page_file = implode("-", array_slice($items, 0, $level)) . "-*." . self::extension;
        $base_path = dirname($this->getPageFilePath());
        $found_files = glob("{$base_path}/{$new_page_file}");

        if (isset($found_files[0])) {
            return $found_files[0];
        } else {
            return $this->_createNewFilename($filename, $level - 1, $step);
        }
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
            $this->title = self::_getTitleFromFileName($this->getPageFilePath());
        }

        return $this->title;
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
        // TODO check whether the book page is the real book page

        return "{$this->base_dir}/{$this->page_name}." . self::extension;
    }

    public function getPageName() {
        return $this->page_name;
    }

    /**
     * 返回章节列表
     * TODO add cache
     */
    public static function getChapterList($base_dir = "../../book") {
        // 只处理两个层级,章/节
        $list = array();

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
