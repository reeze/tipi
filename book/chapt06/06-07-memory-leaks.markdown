# 第七节 内存泄露

内存泄露指的是在程序运行过程中申请了内存，但是在使用完成后没有及时释放，
对于普通运行时间较短的程序来说可能问题不会那么明显，但是对于长时间运行的程序，
比如Web服务器，后台进程等就比较明显了，随着系统运行占用的内存会持续上升，
可能会因为占用内存过高而崩溃，或被系统杀掉([OOM](http://en.wikipedia.org/wiki/Out_of_memory))。

## PHP的内存泄露

PHP属于高级语言，语言级别并没有内存的概念，在使用过程中完全不需要主动申请或释放内存，
所以在PHP用户代码级别也就不存在内存泄露的概念了。

但毕竟PHP是使用C编写的解释器，所以本质上还是一样的，那么可以这么说：
如果你的PHP程序内存泄露了，肯定不是你的错，而是PHP实现的错:)，当然也有可能是其他人的错：
很多公司都会有自己的PHP扩展，而扩展通常也使用C/C++来编写，这样扩展本身也可能
会因为内存不正确释放而导致内存泄露。同时有些扩展是对第三方库的一种包裹，
比如PHP的sqlite数据库操作接口主要是在libsqlite之上进行了封装，所以如果
libsqlite本身有内存泄露的话，那也可能会带来问题。

## 内存泄露的debug及工具

### 内存占用的查看

### PHP内置内存泄露探测

PHP本身有自己的内存管理，如果发现PHP有内存泄露，可以尝试重新编译一个PHP，
将编译选项`--enable-debug`打开（同时所有的扩展也同样需要编译成支持debug模式的）:
`./configure --enable-debug`，这样重新编译后，如果PHP探测到有内存泄露发生则会往
[标准错误输出](http://zh.wikipedia.org/wiki/Stderr#.E6.A8.99.E6.BA.96.E9.8C.AF.E8.AA.A4.E8.BC.B8.E5.87.BA_.28stderr.29)
打印错误信息。这样我们可以快速的发现问题。

在开启debug模式下，PHP中会有一个函数`leak()`可以用于触发内存泄露，这个函数什么都不做，
只是申请一块内存但不释放，其实现很简单：

	/* {{{ proto void leak(int num_bytes=3)
	   Cause an intentional memory leak, for testing/debugging purposes */
	ZEND_FUNCTION(leak)
	{
		long leakbytes=3;

		if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "|l", &leakbytes) == FAILURE) {
			return;
		}

		emalloc(leakbytes); // 只申请，但是没有释放，这块内存将会泄露
	}
	/* }}} */


在下面的代码中我们执行这个函数，然后看看PHP输出的内容：

	[php]
	➜  chapt06 git:(master) ✗ cat leak.php 
	<?php
		leak();
	?>
    ➜  chapt06 git:(master) ✗ php leak.php 
	[Sat May  4 15:36:45 2013]  Script:  '/Users/reeze/www/tipi/book/chapt06/leak.php'
	/Users/reeze/Opensource/php-test/php-src-5.4/Zend/zend_builtin_functions.c(1422) : 
	 Freeing 0x106E07600 (3 bytes), script=/Users/reeze/www/tipi/book/chapt06/leak.php
	=== Total 1 memory leaks detected ===

在上面我们看到PHP在最后输出了在具体的那个代码中出现了内存泄露及出现的次数等。
利用这个信息我们往往能快速的定位出到底是那里出现了问题。

根据PHP提供的泄露信息你可能需要继续追查到底是哪里的问题导致泄露了，如果最终发现
是PHP的bug，那么很好，你可以编写一个相应的修复方法，并提交到<http://bugs.php.net>
这样其他的PHP开发者可以跟进这个问题并最终修复到最新版的PHP中。详细方式可以参考附录D:
怎么样为PHP做贡献。

>**NOTICE**
>本文使用的是cli命令行程序执行程序，当然如果你的程序是一个web应用，通常不太方便
>直接使用php命令来执行，如果是这样的话，那么你的PHP同样会将错误信息输出到标准错误输出
>如果你使用的是php-fpm的话，那么fpm会将错误重定向到日志文件`php-fpm.log`中。
>
>由于命令行执行起来比较简单，在追查问题过程中最好可以将你的代码尽可能的精简，
>将精力集中正确的问题上。不过在用命令测试的时候最好确认你的PHP命令和你的web应用
>使用的是同一个版本的PHP。

不过说到这里，前面提到的方法是有一些*代价和限制*的：

1. 首先这个方法有点麻烦，因为要重新编译PHP代码，同时还可能需要重新编译你的所有扩展
1. 这个方法只能检测到使用了Zend内存管理的情况，对于直接使用malloc/free来申请内存的
   应用或扩展是无法检测到的。

虽然有以上亮点限制`--enable-debug`编译选项在进行扩展或者PHP本身的开发时却是很有用的，
因为这样能快速的发现问题，而对于生产环境来说，后面提到的valgrind分析法可能会更有效一点。

### valgrind辅助法
[valgrind][1]是一个动态分析工具构建框架，可以用来分析程序的内存、线程等问题探测，
程序性能分析等。具体的功能见官网，这是非常值得尝试的工具。这里要使用的就是valgrind
的内存错误分析工具。

## PHP的unclean shutown


## 链接
Valgrind: <http://valgrind.org/>
