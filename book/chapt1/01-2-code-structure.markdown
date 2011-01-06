# PHP源码结构、阅读代码方法:
## PHP源码目录结构:  
***
  俗话讲：大巧不工。PHP的源码在结构上非常清晰甚至简单。下面，先简单介绍一下PHP源码的目录结构。

 * **build**: 源码编译相关文件，包括buildconft等sh文件，还有一些awk的脚本。
 * **ext**    官方扩展目录，包括了绝大多数PHP的函数的定义和实现，如array系列，pdo系列，spl系列等函数的实现，都在此处相对的子目录中。
 * **main**  PHP宏定义与实现，在需要扩展PHP时，经常要使用的PHP_*系列宏就在这里定义。
 * **Zend**  包含Zend引擎文件，Zend API宏的定义和实现。
 * **pear**  “PHP 扩展与应用仓库”, 包含PEAR的核心文件。
 * **sapi**  包含了各种服务器抽象层的代码，以目录区分。
 * **TSRM**  “线程安全资源管理器” (TSRM) 目录。
 * **tests**  测试脚本目录。
 * **win32**  Windows 下编译PHP相关的脚本。  

<br />


## PHP源码阅读方法:  
***

#### 使用VIM + Ctags查看并追踪源码：
VIM是一个非常给力的编辑器，在纯命令终端下，它几乎是无可替代的（Emacs？）。  
ctags可以将源代码中的各种函数、宏等信息做上标记。这样，使用VIM就可以很方便的查看源码。  
简洁使用说明：


    [bash]
    //在PHP源码目录(假定为/server/php-src)执行:
    $ cd /server/php-src
    $ ctags -R

    //在~/.vimrc中添加:
    set tags+=/server/php-src/tags

再用vim打开各种php源码文件时，将光标移到想查看的函数、宏、变量上面，
使用 **Ctrl+p** 就可以自动跳转至定义，**Ctrl+o** 可以返回上一次查看位置。
