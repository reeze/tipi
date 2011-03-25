# 第二节 源码结构、阅读代码方法

## PHP源码目录结构
  俗话讲：大巧不工。PHP的源码在结构上非常清晰甚至简单。下面先简单介绍一下PHP源码的目录结构。

* **根目录:/** 这个目录包含的东西比较多, 主要包含一些说明文件以及设计方案. 其实项目中的这些README文件是非常值得阅读的例如:
	- /README.PHP4-TO-PHP5-THIN-CHANGES 这个文件就详细列举了PHP4和PHP5的一些差异.
	- 还有有一个比较重要的文件/CODING_STANDARDS, 如果要想写一些PHP扩展的话,这个文件一定要阅读一下,
		不管你个人的代码风格是什么样, 怎么样使用缩进和花括号, 既然来到了这样一个团体里就应该去适应这样的规范, 这样在阅读代码或者别人阅读你的
		代码是都会更轻松. 
* **build** 顾名思义, 这里主要放置一些和源码编译相关的一些文件，比如开始构建之前的buildconf脚本等文件,还有一些检查环境的脚本等.
* **ext**   官方扩展目录，包括了绝大多数PHP的函数的定义和实现，如array系列，pdo系列，spl系列等函数的实现，都在这个目录中。
* **main**  这里存放的就是PHP最为核心的文件了,主要实现PHP的基本设施, 这里和Zend引擎不一样,Zend引擎主要实现语言最核心的语言运行环境.
* **Zend**  Zend引擎的实现目录,比如脚本的词法语法解析, opcode的执行以及扩展机制的实现等等.
* **pear**  “PHP 扩展与应用仓库”, 包含PEAR的核心文件.
* **sapi**  包含了各种服务器抽象层的代码，例如apache的mod_php,cgi, fastcgi以及fpm等等接口.
* **TSRM**  PHP的线程安全是构建在TSRM库之上的, PHP实现中常见的\*G宏通常是对TSRM的封装, TSRM(Thread Safe Resource Manager)线程安全资源管理器.
* **tests**  PHP的测试比较有意思,它使用PHP来测试PHP, 测试php脚本在/run-tests.php, 这个脚本读取tests目录中phpt文件.

读者可以打开这些看看, php定义了一套简单的规则来测试, 例如一下的这个测试脚本/tests/basic/001.phpt:

	[php]
	--TEST--
	Trivial "Hello World" test
	--FILE--
	<?php echo "Hello World"?>
	--EXPECT--
	Hello World

这段测试脚本很容易看懂, 执行--FILE--下面的PHP文件,如果最终的输出是--EXPECT--所期望的结果则表示这个测试通过, 可能会有读者回想,如果测试的脚本
不小心触发Fatel Error了,那后面的测试岂不是测试不了了? php中有很多将脚本隔离的方法比如: system(), exec()等函数, 
php测试使用了[proc_open()函数](http://www.php.net/manual/en/function.proc-open.php), 这样就可以保证测试脚本和被测试脚本之间能隔离开.

* **win32**  这个目录主要包括Windows平台相关的一些实现, 比如sokcet的实现在Windows下和\*Nix平台就不太一样, 同时也包括了Windows下编译PHP相关的脚本。 

## PHP源码阅读方法
### 使用VIM + Ctags查看源码
通常在Linux或其他\*Nix环境我们都使用[VIM](http://www.vim.org/)作为代码编辑工具, 在纯命令终端下，它几乎是无可替代的, 它具有非常强大的扩展机制,基本是无所不能了.
不过Emacs用户请不要激动, 笔者还没有真正使用Emacs, 虽然我知道它甚至可以[煮咖啡](http://people.ku.edu/~syliu/shredderyin/emacs_power.html),
等笔者有时间了或许会试试煮杯咖啡边喝边写.

在Linux下编写过代码的读者应该或多或少都试过[ctags](http://ctags.sourceforge.net/)吧.
ctags支持非常多的语言,可以将源代码中的各种符号,比如:函数、宏类等信息抽取出来做上标记。保存到一个文件中.
它保存的文件格式其实也挺简单, 比如符合[UNIX的哲学](http://zh.wikipedia.org/zh/Unix%E5%93%B2%E5%AD%A6),
使用起来也很简单：

    [bash]
    #在PHP源码目录(假定为/server/php-src)执行:
    $ cd /server/php-src
    $ ctags -R

    #在~/.vimrc中添加:
    set tags+=/server/php-src/tags

上面代码会在/sever/php-src目录下生成一个名为tags的文件, 这个文件的[格式很简单](http://ctags.sourceforge.net/FORMAT):

	{tagname}<Tab>{tagfile}<Tab>{tagaddress}

	EG  Zend/zend_globals_macros.h  /^# define EG(/;"   d
它的每行是上面的这样一个格式, 第一个就是符号名, 例如上例的EG宏, 这个符号的文件位置以及这个符号所在的位置.
VIM可以读取tags文件,当我们在符号上**CTRL+]**时VIM将尝试从tags文件中寻找这个符号,如果找到了则根据该符号所在的文件已经该符号的位置打开该文件,
并将光标定位到符号定义所在的位置. 这样我们就能快速的寻找到符号的定义了.

使用 **Ctrl+]** 就可以自动跳转至定义，**Ctrl+t** 可以返回上一次查看位置。这样就可以快速的在代码之间游动了.

这种浏览方式用习惯了还是很方便的. 不过如果你不习惯使用VIM这类编辑器,可以看看下面介绍的[IDE](http://zh.wikipedia.org/wiki/%E9%9B%86%E6%88%90%E5%BC%80%E5%8F%91%E7%8E%AF%E5%A2%83).

>**NOTE**
>如果你使用的Mac OS X, 运行ctags程序可能会出错, 因为Mac OS X自带的ctags程序有些[问题](http://adamyoung.net/Exuberant-Ctags-OS-X),
>所以需要自己下载安装ctags, 笔者推荐使用[homebrew](https://github.com/mxcl/homebrew)来安装.


### 使用IDE查看代码
如果不习惯使用VIM来看代码,那在可以使用一些功能较丰富IDE，比如Windows下可以使用Visual Studio 2010. 或者使用跨平台的[Netbeans](http://www.netbeans.org/),
或者[Eclipse](http://www.eclipse.org/)来看代码,这些工具都相对较**重量级**一些,不过这些工具不管是调试还是查看代码都相对较方便,

在Eclipse及Netbeans下查看符号定义的方式通常是将鼠标移到符号上,同时按住**CTRL**,然后单击,将会跳转到符号定义的位置.

而如果使用VS的话, 在win32目录下已经存在了可以直接打开的工程文件，如果由于版本原因无法打开，可以在此源码目录上新建一个基于现有文件的Win32 Console Application工程。
**常用快捷键**：

    [c]
    F12 转到定义
    CTRL + F12转到声明

    F3: 查找下一个
    Shift+F3: 查找上一个

    Ctrl+G: 转到指定行

    CTRL + -向后定位
    CTRL + SHIFT + -向前定位

对于一些搜索类的操作，可以考虑使用editplus或其它文本编辑工具进行，这样的搜索速度相对来说会快一些。 
如果使用editplus进行搜索，一般是选择 【搜索】 中的 【在文件中查找...】

