# 第二节 源码结构、阅读代码方法

## PHP源码目录结构
  俗话讲：重剑无锋，大巧不工。PHP的源码在结构上非常清晰。下面先简单介绍一下PHP源码的目录结构。

* **根目录: /** 这个目录包含的东西比较多，主要包含一些说明文件以及设计方案。 其实项目中的这些README文件是非常值得阅读的例如：
	- /README.PHP4-TO-PHP5-THIN-CHANGES 这个文件就详细列举了PHP4和PHP5的一些差异。
	- 还有有一个比较重要的文件/CODING_STANDARDS，如果要想写PHP扩展的话，这个文件一定要阅读一下，
		不管你个人的代码风格是什么样，怎么样使用缩进和花括号，既然来到了这样一个团体里就应该去适应这样的规范，这样在阅读代码或者别人阅读你的
		代码是都会更轻松。
* **build** 顾名思义，这里主要放置一些和源码编译相关的一些文件，比如开始构建之前的buildconf脚本等文件，还有一些检查环境的脚本等。
* **ext**   官方扩展目录，包括了绝大多数PHP的函数的定义和实现，如array系列，pdo系列，spl系列等函数的实现，都在这个目录中。个人写的扩展在测试时也可以放到这个目录，方便测试和调试。
* **main**  这里存放的就是PHP最为核心的文件了，主要实现PHP的基本设施，这里和Zend引擎不一样，Zend引擎主要实现语言最核心的语言运行环境。
* **Zend**  Zend引擎的实现目录，比如脚本的词法语法解析，opcode的执行以及扩展机制的实现等等。
* **pear**  “PHP 扩展与应用仓库”，包含PEAR的核心文件。
* **sapi**  包含了各种服务器抽象层的代码，例如apache的mod_php，cgi，fastcgi以及fpm等等接口。
* **TSRM**  PHP的线程安全是构建在TSRM库之上的，PHP实现中常见的\*G宏通常是对TSRM的封装，TSRM(Thread Safe Resource Manager)线程安全资源管理器。
* **tests**  PHP的测试脚本集合，包含PHP各项功能的测试文件
* **win32**  这个目录主要包括Windows平台相关的一些实现，比如sokcet的实现在Windows下和\*Nix平台就不太一样，同时也包括了Windows下编译PHP相关的脚本。 

PHP的测试比较有意思，它使用PHP来测试PHP，测试php脚本在/run-tests。php，这个脚本读取tests目录中phpt文件。
读者可以打开这些看看，php定义了一套简单的规则来测试，例如一下的这个测试脚本/tests/basic/001。phpt：

	[php]
	--TEST--
	Trivial "Hello World" test
	--FILE--
	<?php echo "Hello World"?>
	--EXPECT--
	Hello World

这段测试脚本很容易看懂，执行--FILE--下面的PHP文件，如果最终的输出是--EXPECT--所期望的结果则表示这个测试通过，
可能会有读者会想，如果测试的脚本不小心触发Fatal Error，或者抛出未被捕获的异常了，因为如果在同一个进程中执行，
测试就会停止，后面的测试也将无法执行，php中有很多将脚本隔离的方法比如：
system()，exec()等函数，这样可以使用主测试进程服务调度被测脚本和检测测试结果，通过这些外部调用执行测试。
php测试使用了[proc_open()函数](http://www.php.net/manual/en/function.proc-open.php)，这样就可以保证测试脚本和被测试脚本之间能隔离开。


## PHP源码阅读方法
### 使用VIM + Ctags查看源码
通常在Linux或其他\*Nix环境我们都使用[VIM](http://www.vim.org/)作为代码编辑工具，在纯命令终端下，它几乎是无可替代的。
它具有非常强大的扩展机制，在文字编辑方面基本上无所不能。
不过Emacs用户请不要激动，笔者还没有真正使用Emacs，虽然我知道它甚至可以[煮咖啡](http://people.ku.edu/~syliu/shredderyin/emacs_power.html)，
还是等笔者有时间了或许会试试煮杯咖啡边喝边写。

推荐在Linux下编写代码的读者或多或少的试一试[ctags](http://ctags.sourceforge.net/)。
ctags支持非常多的语言，可以将源代码中的各种符号（如:函数、宏类等信息）抽取出来做上标记并保存到一个文件中，
供其他文本编辑工具（VIM，EMACS等）进行检索。
它保存的文件格式符合[UNIX的哲学（小即是美）](http://zh.wikipedia.org/zh/Unix%E5%93%B2%E5%AD%A6)，
使用也比较简洁：

    [bash]
    #在PHP源码目录(假定为/server/php-src)执行:
    $ cd /server/php-src
    $ ctags -R

	#小技巧：在当前目录生成的tags文件中使用的是相对路径，
	#若改用 ctags -R /server/ ，可以生成包含完整路径的ctags，就可以随意放到任意文件夹中了。 

    #在~/.vimrc中添加:
    set tags+=/server/php-src/tags
    #或者在vim中运行命令:
    :set tags+=/server/php-src/tags

上面代码会在/sever/php-src目录下生成一个名为tags的文件，这个文件的[格式如下](http://ctags.sourceforge.net/FORMAT)：

	{tagname}<Tab>{tagfile}<Tab>{tagaddress}

	EG  Zend/zend_globals_macros.h  /^# define EG(/;"   d

它的每行是上面的这样一个格式，第一列是符号名（如上例的EG宏），第二列是该符号的文件位置以及这个符号所在的位置。
VIM可以读取tags文件，当我们在符号上（可以是变量名之类）使用**CTRL+]**时VIM将尝试从tags文件中检索这个符号。
如果找到则根据该符号所在的文件以及该符号的位置打开该文件，
并将光标定位到符号定义所在的位置。 这样我们就能快速的寻找到符号的定义。

使用 **Ctrl+]** 就可以自动跳转至定义，**Ctrl+t** 可以返回上一次查看位置。这样就可以快速的在代码之间“游动”了。

习惯这种浏览代码的方式之后，大家会感觉很方便的。不过若你不习惯使用VIM这类编辑器，也可以看看下面介绍的[IDE](http://zh.wikipedia.org/wiki/%E9%9B%86%E6%88%90%E5%BC%80%E5%8F%91%E7%8E%AF%E5%A2%83)。

>**NOTE**
>如果你使用的Mac OS X，运行ctags程序可能会出错，因为Mac OS X自带的ctags程序有些[问题](http://adamyoung.net/Exuberant-Ctags-OS-X)，
>所以需要自己下载安装ctags，笔者推荐使用[homebrew](https://github.com/mxcl/homebrew)来安装。


### 使用IDE查看代码
如果不习惯使用VIM来看代码，也可以使用一些功能较丰富的IDE，比如Windows下可以使用Visual Studio 2010 Express 。
或者使用跨平台的[Netbeans](http://www.netbeans.org/)、[Eclipse](http://www.eclipse.org/)来查看代码，
当然，这些工具都相对较**重量级**一些，不过这些工具不管是调试还是查看代码都相对较方便，

在Eclipse及Netbeans下查看符号定义的方式通常是将鼠标移到符号上，同时按住**CTRL**，然后单击，将会跳转到符号定义的位置。

而如果使用VS的话， 在win32目录下已经存在了可以直接打开的工程文件，如果由于版本原因无法打开，
可以在此源码目录上新建一个基于现有文件的Win32 Console Application工程。

**常用快捷键**：

    F12 转到定义
    CTRL + F12转到声明

    F3: 查找下一个
    Shift+F3: 查找上一个

    Ctrl+G: 转到指定行

    CTRL + -向后定位
    CTRL + SHIFT + -向前定位

对于一些搜索类型的操作，可以考虑使用Editplus或其它文本编辑工具进行，这样的搜索速度相对来说会快一些。 
如果使用Editplus进行搜索，一般是选择 【搜索】 中的 【在文件中查找...】

