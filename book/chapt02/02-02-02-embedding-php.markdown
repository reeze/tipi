# 嵌入式

从第一章中对PHP源码目录结构的介绍以及PHP生命周期可知：嵌入式PHP类似CLI，也是SAPI接口的另一种实现。
一般情况下，它的一个请求的生命周期也会和其它的SAPI一样：模块初始化=>请求初始化=>处理请求=>关闭请求=>关闭模块。
当然，这只是理想情况。因为特定的应用由自己特殊的需求，只是在处理PHP脚本这个环节基本一致。

对于嵌入式PHP或许我们了解比较少，或者说根本用不到，甚至在网上相关的资料也不多，
例如很多游戏中使用Lua语言作为粘合语言，或者作为扩展游戏的脚本语言，类似的，
浏览器中的Javascript语言就是嵌入在浏览器中的。只是目前很少有应用将PHP作为嵌入语言来使用，
PHP的强项目前还是在Web开发方面。

这一小节，我们从这本书的一个示例说起，介绍PHP对于嵌入式PHP的支持以及PHP为嵌入式提供了哪些接口或功能。
首先我们看下所要用到的示例源码：

    [c]
    #include <sapi/embed/php_embed.h>
    #ifdef ZTS
        void ***tsrm_ls;
    #endif
    /* Extension bits */
    zend_module_entry php_mymod_module_entry = {
        STANDARD_MODULE_HEADER,
        "mymod", /* extension name */
        NULL, /* function entries */
        NULL, /* MINIT */
        NULL, /* MSHUTDOWN */
        NULL, /* RINIT */
        NULL, /* RSHUTDOWN */
        NULL, /* MINFO */
        "1.0", /* version */
        STANDARD_MODULE_PROPERTIES
    };
    /* Embedded bits */
    static void startup_php(void)
    {
        int argc = 1;
        char *argv[2] = { "embed5", NULL };
        php_embed_init(argc, argv PTSRMLS_CC);
        zend_startup_module(&php_mymod_module_entry);
    }
    static void execute_php(char *filename)
    {
        zend_first_try {
            char *include_script;
            spprintf(&include_script, 0, "include '%s'", filename);
            zend_eval_string(include_script, NULL, filename TSRMLS_CC);
            efree(include_script);
        } zend_end_try();
    }
    int main(int argc, char *argv[])
    {
        if (argc <= 1) {
            printf("Usage: embed4 scriptfile";);
            return -1;
        }
        startup_php();
        execute_php(argv[1]);
        php_embed_shutdown(TSRMLS_CC);
        return 0;
    }


以上的代码可以在《Extending and Embedding PHP》在第20章找到（原始代码有一个符号错误，有兴趣的童鞋可以去围观下）。
上面的代码是一个嵌入式PHP运行器（我们权当其为运行器吧），在这个运行器上我们可以运行PHP代码。
这段代码包括了对于PHP嵌入式支持的声明，启动嵌入式PHP运行环境，运行PHP代码，关闭嵌入式PHP运行环境。
下面我们就这段代码分析PHP对于嵌入式的支持做了哪些工作。 
首先看下第一行：

    [c]
    #include <sapi/embed/php_embed.h>

在sapi目录下的embed目录是PHP对于嵌入式的抽象层所在。在这里有我们所要用到的函数或宏定义。
如示例中所使用的php_embed_init，php_embed_shutdown等函数。

第2到4行：

    [c]
     #ifdef ZTS
        void ***tsrm_ls;
    #endif

ZTS是Zend Thread Safety的简写，与这个相关的有一个TSRM（线程安全资源管理）的东东，这个后面的章节会有详细介绍，这里就不再作阐述。

第6到17行：

    [c]
     zend_module_entry php_mymod_module_entry = {
        STANDARD_MODULE_HEADER,
        "mymod", /* extension name */
        NULL, /* function entries */
        NULL, /* MINIT */
        NULL, /* MSHUTDOWN */
        NULL, /* RINIT */
        NULL, /* RSHUTDOWN */
        NULL, /* MINFO */
        "1.0", /* version */
        STANDARD_MODULE_PROPERTIES
    };

以上PHP内部的模块结构声明，此处对于模块初始化，请求初始化等函数指针均为NULL，
也就是模块在初始化及请求开始结束等事件发生的时候不执行任何操作。
不过这些操作在sapi/embed/php_embed.c文件中的php_embed_shutdown等函数中有体现。
关于模块结构的定义在zend/zend_modules.h中。

startup_php函数:

    [c]
    static void startup_php(void)
    {
        int argc = 1;
        char *argv[2] = { "embed5", NULL };
        php_embed_init(argc, argv PTSRMLS_CC);
        zend_startup_module(&php_mymod_module_entry);
    }

这个函数调用了两个函数php_embed_init和zend_startup_module完成初始化工作。
php_embed_init函数定义在sapi/embed/php_embed.c文件中。它完成了PHP对于嵌入式的初始化支持。
zend_startup_module函数是PHP的内部API函数，它的作用是注册定义的模块，这里是注册mymod模块。
这个注册过程仅仅是将所定义的zend_module_entry结构添加到注册模块列表中。

execute_php函数:

    [c]
    static void execute_php(char *filename)
    {
        zend_first_try {
            char *include_script;
            spprintf(&include_script, 0, "include '%s'", filename);
            zend_eval_string(include_script, NULL, filename TSRMLS_CC);
            efree(include_script);
        } zend_end_try();
    }

从函数的名称来看，这个函数的功能是执行PHP代码的。
它通过调用spprintf函数构造一个include语句，然后再调用zend_eval_string函数执行这个include语句。
zend_eval_string最终是调用zend_eval_stringl函数，这个函数是流程是一个编译PHP代码，
生成zend_op_array类型数据，并执行opcode的过程。
这段程序相当于下面的这段php程序，这段程序可以用php命令来执行，虽然下面这段程序没有实际意义，
而通过嵌入式PHP中，你可以在一个用C实现的系统中嵌入PHP，然后用PHP来实现功能。

	[php]
	<?php
	if($argc < 2) die("Usage: embed4 scriptfile");

	include $argv[1];


main函数：

    [c]
    int main(int argc, char *argv[])
    {
        if (argc <= 1) {
            printf("Usage: embed4 scriptfile";);
            return -1;
        }
        startup_php();
        execute_php(argv[1]);
        php_embed_shutdown(TSRMLS_CC);
        return 0;
    }

这个函数是主函数，执行初始化操作，根据输入的参数执行PHP的include语句，最后执行关闭操作，返回。
其中php_embed_shutdown函数定义在sapi/embed/php_embed.c文件中。它完成了PHP对于嵌入式的关闭操作支持。
包括请求关闭操作，模块关闭操作等。

以上是使用PHP的嵌入式方式开发的一个简单的PHP代码运行器，它的这些调用的方式都基于PHP本身的一些实现，
而针对嵌入式的SAPI定义是非常简单的，没有Apache和CGI模式的复杂，或者说是相当简陋，这也是由其所在环境决定。
在嵌入式的环境下，很多的网络协议所需要的方法都不再需要。如下所示，为嵌入式的模块定义。

    [c]
    sapi_module_struct php_embed_module = {
        "embed",                       /* name */
        "PHP Embedded Library",        /* pretty name */

        php_embed_startup,              /* startup */
        php_module_shutdown_wrapper,   /* shutdown */

        NULL,                          /* activate */
        php_embed_deactivate,           /* deactivate */

        php_embed_ub_write,             /* unbuffered write */
        php_embed_flush,                /* flush */
        NULL,                          /* get uid */
        NULL,                          /* getenv */

        php_error,                     /* error handler */

        NULL,                          /* header handler */
        NULL,                          /* send headers handler */
        php_embed_send_header,          /* send header handler */

        NULL,                          /* read POST data */
        php_embed_read_cookies,         /* read Cookies */

        php_embed_register_variables,   /* register server variables */
        php_embed_log_message,          /* Log message */
        NULL,							/* Get request time */
        NULL,							/* Child terminate */

        STANDARD_SAPI_MODULE_PROPERTIES
    };
    /* }}} */

在这个定义中我们看到了若干的NULl定义，在前面一小节中说到SAPI时，我们是以cookie的读取为例，
在这里也有读取cookie的实现——php_embed_read_cookies函数，但是这个函数的实现是一个空指针NULL。

而这里的flush实现与Apache的不同:

    [c]
    static void php_embed_flush(void *server_context)
    {
        if (fflush(stdout)==EOF) {
            php_handle_aborted_connection();
        }
    }

flush是直接调用fflush(stdout)，以达到清空stdout的缓存的目的。
如果输出失败（fflush成功返回0，失败返回EOF），则调用php_handle_aborted_connection，进入中断处理程序。

## 参与资料
《Extending and Embedding PHP》
