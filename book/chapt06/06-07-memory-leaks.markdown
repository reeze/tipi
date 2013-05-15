# 第七节 内存泄露

内存泄露指的是在程序运行过程中申请了内存，但是在使用完成后没有及时释放的现象，
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

内存泄露的程序通常很容易发现，因为症状都表现为内存占用的持续增长，
在发现内存持续增长后我们需要判断是什么导致了内存泄露，这时往往需要
借助一些工具来帮助追查，我们可以用到两个工具：PHP内置内存泄露探测
及valgrind内存泄露分析。

### PHP内置内存泄露探测

PHP本身有自己的内存管理，如果发现PHP有内存泄露，可以尝试重新编译一个PHP，
将编译选项`--enable-debug`打开（同时所有的扩展也同样需要编译成支持debug模式的）:
`./configure --enable-debug`，这样重新编译后，如果PHP探测到有内存泄露发生则会往
[标准错误输出](http://zh.wikipedia.org/wiki/Stderr#.E6.A8.99.E6.BA.96.E9.8C.AF.E8.AA.A4.E8.BC.B8.E5.87.BA_.28stderr.29)
打印错误信息。这样我们可以快速的发现问题。

在开启debug模式下，PHP中会有一个函数`leak()`可以用于触发内存泄露，这个函数什么都不做，
只是申请一块内存但不释放，其实现很简单：

	[c]
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

虽然有以上的限制`--enable-debug`编译选项在进行扩展或者PHP本身的开发时却是很有用的，
因为这样能快速的发现问题，而对于生产环境来说，后面提到的valgrind分析法可能会更有效一点。

### valgrind辅助法
[valgrind](http://valgrind.org/)是一个动态分析工具构建框架，可以用来分析程序的内存、线程等问题探测，
程序性能分析等。具体的功能见官网，这是非常值得尝试的工具。这里要使用的就是valgrind
的内存错误分析工具。

我们大部分的时候使用的是Web模式，这时的调试相对麻烦一些。

由于我们需要发现PHP的内存泄露，根据前面的章节我们知道PHP的内存分配是有一个内存池的，
也就是说，并不是每次`emalloc`都会向操作系统申请内存，如果池有足够的内存的话是会从池里进行分配的，
而valgrind分析内存泄露依赖的是内存的实际分配和实际释放之间的关系，它会记下所有的
`malloc`调用和`free`调用，如果出现不匹配的情况，那么就是发生了内存泄露，所以在这种情况
下我们需要将PHP的内存管理功能关闭才能不影响到我们的分析。

PHP提供了一个hook，我们可以在启动PHP前指定`USE_ZEND_ALLOC`环境变量为0，即关闭内存管理功能。
这样所有的内存分配都会直接向操作系统申请，这样valgrind就可以帮助我们定位问题。

valgrind程序可以这样对一个程序进行内存分析

	reeze@ubuntu:~$ export USE_ZEND_ALLOC=0   # 设置环境变量关闭内存管理
	reeze@ubuntu:~$ cat leak.php 
	<?php
		leak();
	reeze@ubuntu:~$ valgrind --leak-check=full php leak.php

	Memcheck, a memory error detector
	Copyright (C) 2002-2012, and GNU GPL'd, by Julian Seward et al.
	Using Valgrind-3.8.1 and LibVEX; rerun with -h for copyright info
	Command: php leak.php
	
	HEAP SUMMARY:
	    in use at exit: 60 bytes in 3 blocks
	  total heap usage: 19,906 allocs, 19,903 frees, 3,782,702 bytes allocated
	
	3 bytes in 1 blocks are definitely lost in loss record 1 of 3
	   at 0x4C2A66E: malloc (vg_replace_malloc.c:270)
	   by 0x80FC56: _emalloc (zend_alloc.c:2348)
	   by 0x858C7D: zif_leak (zend_builtin_functions.c:1346)         # 检测到我们的leak函数的泄露
	   by 0x87C1CA: zend_do_fcall_common_helper_SPEC (zend_vm_execute.h:320)
	   by 0x8816BC: ZEND_DO_FCALL_SPEC_CONST_HANDLER (zend_vm_execute.h:1640)
	   by 0x87B17E: execute (zend_vm_execute.h:107)
	   by 0x840AF0: zend_execute_scripts (zend.c:1236)
	   by 0x7A1717: php_execute_script (main.c:2308)
	   by 0x9403E8: main (php_cli.c:1189)
	
	LEAK SUMMARY:
	   definitely lost: 3 bytes in 1 blocks
	   indirectly lost: 0 bytes in 0 blocks
	     possibly lost: 0 bytes in 0 blocks
	   still reachable: 57 bytes in 2 blocks
	        suppressed: 0 bytes in 0 blocks
	Reachable blocks (those to which a pointer was found) are not shown.
	To see them, rerun with: --leak-check=full --show-reachable=yes
	
	For counts of detected and suppressed errors, rerun with: -v
	Use --track-origins=yes to see where uninitialised values come from
	ERROR SUMMARY: 2 errors from 2 contexts (suppressed: 2 from 2)


如上面所示，这里对某个php脚本执行并检测内存泄露，valgrind顺利的发现了我们在leak函数中申请的内存
并没有正确的释放，发现问题后修复起来就很简单了。

这里是以命令行的方式来进行测试。对于web应用我们同样可以用类似的方式来定位问题。已php-fpm为例，
我们可以修改php-fpm启动脚本，在启动脚本中增加环境变量`USE_ZEND_ALLOC=0`以及将bin文件由原来的
php-fpm文件修改为由valgrind启动，并将valgrind的日志重定向到日志文件中，修改如下:

	修改内容：
	+ export USE_ZEND_ALLOC=0
	+
	- php_fpm_BIN="/usr/local/php/bin/php-fpm"
	+ php_fpm_BIN="valgrind --log-file=/home/reeze/valgrind-log/%p.log /usr/local/php/bin/php-fpm"

修改好后使用：

	[bash]
	$ php-fpm restart

重新访问有内存泄露嫌疑的页面，这时指定的日志文件中就会有可能出现的内存泄露信息。
前面示例中的`--log-file=/home/reeze/valgrind-log/%p.log`其中的`%p`是一个占位符，
指的是进程号，所以比如运行起来的进程ID是1那么会将日志输出到`1.log`文件，这主要是
因为启动的程序可能会`fork()`出多个子进程，这样的好处是可以方便的知道具体是哪个进程的日志。

更多关于valgrind的使用还是建议阅读相关的手册。PHP官方也有对valgrind的使用的[说明](https://bugs.php.net/bugs-getting-valgrind-log.php)

## PHP的unclean shutdown

前面提到的关于使用`USE_ZEND_ALLOC=0`来关闭PHP内存管理，这里就有一个疑问：
将内存管理关掉出了性能上的差别还有其他差别么？

直觉上上理解，只是把内存管理关掉，直接向操作系统申请内存并没有什么的，
只是每次内存申请都需要想系统申请，只是效率变差了。

其实并不然，简单讲：PHP中的异常执行流依赖于内存管理来释放内存。
这里说的异常执行流指的是PHP实现级别的异常执行流，在用户看来指的是出现PHP Fatal error
或者内部异常时。

比如在出现PHP Fatal error时，PHP使用`longjmp`来进行跳转，在C中我们通常使用配对的`malloc`和`free`
来管理内存，但是使用`longjmp`后执行流发生了变化，可能会导致很多内存只进行了申请而无法正确释放。

具体以文件`Zend/zend.c`中的`zend_call_destructors()`方法为例：

	[c]
	void zend_call_destructors(TSRMLS_D) /* {{{ */
	{
		zend_try {
			shutdown_destructors(TSRMLS_C);
		} zend_end_try();
	}
	/* }}} */

	/* Zend/zend.h */
	#define zend_try                                                \
		{                                                           \
			JMP_BUF *__orig_bailout = EG(bailout);                  \
			JMP_BUF __bailout;                                      \ 
																	\
			EG(bailout) = &__bailout;                               \
			if (SETJMP(__bailout)==0) {
	#define zend_catch                                              \
			} else {                                                \
				EG(bailout) = __orig_bailout;
	#define zend_end_try()                                          \
			}                                                       \
			EG(bailout) = __orig_bailout;                           \
		}

`zend_call_destructors`函数只在请求结束的最后执行所有对象的析构函数，由于
我们的请求已经结束了，所以这里加了个try/catch防止执行的代码抛出异常，如果
抛出异常只是简单的忽略它，因为我们已经不能做任何事情了。

内核实现中是使用`zend_bailout()`函数来实现"抛出异常"的。

	[c]
	ZEND_API void _zend_bailout(char *filename, uint lineno)
	{
		// ...
		CG(unclean_shutdown) = 1;  /* 标记 */
		LONGJMP(*EG(bailout), FAILURE); // 跳转至响应的catch位置
		// ...
	}


我们知道C语言并不支持异常，PHP中的try catch使用的是`jmp_buf`来模拟异常。

我们来看一下一个实际的例子：

	reeze@ubuntu:~$ cat fatal.php
	<?php
	not_exists_func(); # 调用一个不存在的函数
	reeze@ubuntu:~$ valgrind --leak-check=full php fatal.php
	Memcheck, a memory error detector
	Copyright (C) 2002-2012, and GNU GPL'd, by Julian Seward et al.
	Using Valgrind-3.8.1 and LibVEX; rerun with -h for copyright info
	Command: php fatal.php
	
	Fatal error: Call to undefined function not_exists_func() in /home/parallels/fatal.php on line 3
	
	HEAP SUMMARY:
	    in use at exit: 686 bytes in 7 blocks
	  total heap usage: 19,910 allocs, 19,903 frees, 3,783,288 bytes allocated
	
	628 (232 direct, 396 indirect) bytes in 1 blocks are definitely lost in loss record 7 of 7
	   at 0x4C2A66E: malloc (vg_replace_malloc.c:270)
	   by 0x80FC56: _emalloc (zend_alloc.c:2348)
	   by 0x7DFDA6: compile_file (zend_language_scanner.l:334)
	   by 0x60CBC5: phar_compile_file (phar.c:3397)
	   by 0x840997: zend_execute_scripts (zend.c:1228)
	   by 0x7A1717: php_execute_script (main.c:2308)
	   by 0x9403E8: main (php_cli.c:1189)
	
	LEAK SUMMARY:
	   definitely lost: 232 bytes in 1 blocks # 内存泄露
	   indirectly lost: 396 bytes in 4 blocks
	     possibly lost: 0 bytes in 0 blocks
	   still reachable: 58 bytes in 2 blocks
	        suppressed: 0 bytes in 0 blocks
	Reachable blocks (those to which a pointer was found) are not shown.
	To see them, rerun with: --leak-check=full --show-reachable=yes
	
	For counts of detected and suppressed errors, rerun with: -v
	Use --track-origins=yes to see where uninitialised values come from
	ERROR SUMMARY: 2 errors from 2 contexts (suppressed: 2 from 2)


这个脚本只是执行一个不存在的方法，最终抛出了致命错误而终止。这个看起来只是编码错误，
但是我们看到valgrind发现有内存泄露，所以如果关闭了PHP的内存管理是有问题的，每次请求这个页面都会导致内存
不停的泄露。

而如果PHP内存管理打开了之后，如果发生了这种异常情况下的长跳转，PHP会将标志位：`CG(unclean_shutdown)`设置为true，
在请求结束后会将所有的内存进行释放：

	[c]
	// Zend/zend_alloc.c
	ZEND_API void zend_mm_shutdown(zend_mm_heap *heap, int full_shutdown, int silent TSRMLS_DC)
	{
		// ...	
		if (full_shutdown) { // full_shutdown == CG(unclean_shutdown) 
			// 释放掉所有PHP堆栈中的内存
			storage->handlers->dtor(storage);
			if (!internal) {
				free(heap);
			}
		} else {
			if (heap->compact_size &&
				heap->real_peak > heap->compact_size) {
				storage->handlers->compact(storage);
			}
			heap->segments_list = NULL;
			zend_mm_init(heap);
			heap->real_size = 0;
			heap->real_peak = 0;
			heap->size = 0;
			heap->peak = 0;
			if (heap->reserve_size) {
				heap->reserve = _zend_mm_alloc_int(heap, heap->reserve_size  ZEND_FILE_LINE_CC ZEND_FILE_LINE_EMPTY_CC);
			}
			heap->overflow = 0;
		}
		// ...	
	}

如果发了unclean shutdown并不是简单的将内存回收到内存池，而是直接将所有的内存释放以避免内存泄露。
这样的好处是实现简单，同时出现Fatal错误也是需要及时处理的问题。

## 参考
1. Valgrind: <http://valgrind.org/>
1. C语言实现异常处理：<http://www.cnblogs.com/jiayu1016/archive/2012/10/20/2732712.html>

