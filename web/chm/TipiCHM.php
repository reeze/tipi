<?php

require_once "../lib/common.inc.php";
require_once "../lib/SimpieView.php";
require_once "../models/BookPage.php";



if (class_exists('TipiCHM') === FALSE) {

    class TipiCHM {

        private $_data;
        private $_filename;
        private $_filepath;
        private $_subPath;

        public function __construct($filename, $data) {
            $this->_filepath = dirname(__FILE__) . "/source/";
            $this->_filename = $filename;
            $this->_subPath = 'files';
            $this->_data = $data;
        }

        public function copyCSS($filename) {
            copy($filename, $this->_filepath . $this->_subPath . "/" . basename($filename));
        }
        
        public  function copyImagesOfDiretory() {
            $list = glob(TIPI_ROOT_PATH . "/book/images/chapt*/*.*");
            foreach ($list as $filename) {
                $this->copyImages($filename);
            }
        }
        
        public function copyCHM() {
            $filename = $this->_filename . ".chm";
            copy($this->_filepath . $filename, ROOT_PATH . "/releases/" . basename($filename));
        }
        
         public function copyImages($filename) {
            copy($filename, $this->_filepath . $this->_subPath . "/" . basename($filename));
        }
        
        private function _createFilename($page_name) {
            return $this->_subPath . "/" . substr($page_name, strrpos($page_name, '/') + 1) . '.html';
        }

        private function _createHHCItem($data, $level = 1) {
            $out = str_repeat("\t", $level) . "<ul>\n";
            $out .= str_repeat("\t", $level + 1) . "<li> <object type=\"text/sitemap\">\n";
            $out .= str_repeat("\t", $level + 1) . "<param name=\"Name\" value=\"" . $data['title'] . "\">\n";
            $out .= str_repeat("\t", $level + 1) . "<param name=\"Local\" value=\"" . $this->_createFilename($data['page_name']) . "\">\n";
            $out .= str_repeat("\t", $level + 1) . "</object>\n";

            if (is_array($data['list'])) {
                foreach ($data['list'] as $row) {
                    $out .= $this->_createHHCItem($row, $level + 1);
                }
            }
            $out .= str_repeat("\t", $level) . "</ul>\n";

            return $out;
        }

        private function _createHHPFiles($data) {
            $out = $this->_createFilename($data['page_name']) . "\n";

            if (is_array($data['list'])) {
                foreach ($data['list'] as $row) {
                    $out .= $this->_createHHPFiles($row);
                }
            }

            return $out;
        }

        private function _encodeAndwrite($fp, $content) {
            fwrite($fp, iconv('UTF-8', 'GBK', $content));
        }

        function createHHC() {
            // create HTMLHelp table of contents
            $filename = $this->_filepath . $this->_filename . ".hhc";
            $fp = fopen($filename, "w");

            if (!$fp) {
                echo "Could not create HTMLHelp table of contents!<br>";
            }

            echo "Creating HTMLHelp table of contents<br>\n";


            $header = <<<EOF
<!DOCTYPE HTML PUBLIC "-//IETF//DTD HTML//EN">
<HTML>
<HEAD>
<meta name="GENERATOR" content="TIPI">
<!-- Sitemap 1.0 -->
</HEAD>
<BODY>
<OBJECT type="text/site properties">
    <param name="ImageType" value="Folder">
    <param name="Window Styles" value="0x800227">
</OBJECT>\n
EOF;

            $footer = "</BODY></HTML>";

            $this->_encodeAndwrite($fp, $header);
            $this->_encodeAndwrite($fp, $this->_createHHCItem($this->_data, 1));
            $this->_encodeAndwrite($fp, $footer);
            fclose($fp);
        }

        function createHHK() {
            $filename = $this->_filepath . $this->_filename . ".hhk";
            $fp = fopen($filename, "w");

            if (!$fp) {
                echo "Could not create HTMLHelp table of contents!<br>";
            }

            echo "Creating HTMLHelp index of contents<br>\n";


            $header = <<<EOF
<!DOCTYPE HTML PUBLIC "-//IETF//DTD HTML//EN">
<html>
<head>
  <meta name="generator" content="TIPI">
  <meta http-equiv="Content-Type" content="UTF-8" />
  <!-- Sitemap 1.0 -->
</head>
<body>
  <object type="text/site properties">
    <param name="Window Styles" value="0x800227">
  </object>\n
EOF;

            $footer = "</BODY></HTML>";

            $this->_encodeAndwrite($fp, $header);
            $this->_encodeAndwrite($fp, $this->_createHHCItem($this->_data, 1));
            $this->_encodeAndwrite($fp, $footer);
            fclose($fp);
        }

        public function createHHP() {

            $name = $this->_filename;
            $filename = $this->_filepath . $this->_filename . ".hhp";
            $fp = fopen($filename, "w");
            if ($fp) {
                $hhp = <<<EOF
[OPTIONS]
Auto Index=Yes
Compatibility=1.1 or later
Compiled file=$name.chm
Contents file=$name.hhc
Default topic=$name
Display compile progress=No
Index file=$name.hhk
Title=$name
[FILES]\n
EOF;

                $hhp .= $this->_createHHPFiles($this->_data);

                $hhp .= "\n[INFOTYPES]\n\n\n";

                $this->_encodeAndwrite($fp, $hhp);
                fclose($fp);
            }
        }

        public function createFiles($view, $pages) {
            foreach ($pages as $key => $row) {

                $filename = $this->_filepath . $this->_createFilename($row->getPageName());

                $content = $view->render(array(
                            'pages' => array($row)
                                ), TRUE);
                $content = preg_replace("%src=\"[^\"]+chapt[^/]+/%i", 'src="', $content);
                
                $fp = fopen($filename, "w");
                flock($fp, LOCK_EX);
                fwrite($fp, $content);
                flock($fp, LOCK_UN);
                fclose($fp);
            }
        }

    }

}



?>
