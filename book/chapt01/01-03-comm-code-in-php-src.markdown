# 第三节 常用代码
在PHP的源码中经常会看到一些宏或一些对于刚开始看源码的童鞋比较纠结的代码。这里提取中间的一些进行说明。
## 1. 关于"##"和"#"
***
在PHP的宏定义中，最常见的要数双井号。
### **双井号**
在C语言的宏中，"##"被称为 **连接符**（concatenator），用来把两个语言符号(Token)组合成单个语言符号。这里的语言符号不一定是宏的变量。并且双井号不能作为第一个或最后一个元素存在。如下所示源码：


    [c]
    #define PHP_FUNCTION			ZEND_FUNCTION
    #define ZEND_FUNCTION(name)				ZEND_NAMED_FUNCTION(ZEND_FN(name))
    #define ZEND_FN(name) zif_##name
    #define ZEND_NAMED_FUNCTION(name)		void name(INTERNAL_FUNCTION_PARAMETERS)
    #define INTERNAL_FUNCTION_PARAMETERS int ht, zval *return_value, zval **return_value_ptr, \
    zval *this_ptr, int return_value_used TSRMLS_DC

    PHP_FUNCTION(count);

    //  预处理器处理以后, PHP_FUCNTION(count);就展开为如下代码
    void zif_count(int ht, zval *return_value, zval **return_value_ptr,
            zval *this_ptr, int return_value_used TSRMLS_DC)

宏ZEND_FN(name)中有一个"##"，它的作用一如之前所说，是一个连接符，将zif和宏的变量name得值连接起来。

### **单井号**
"#"的功能是将其后面的宏参数进行 **字符串化操作** ，简单说就是在对它所引用的宏变量通过替换后在其左右各加上一个双引号，用比较官方的话说就是将语言符号(Token)转化为字符串。

  
## 2. 关于宏定义中的do-while
***
如下所示为PHP5.3新增加的垃圾收集机制中的一段代码：

    [c]
    #define ALLOC_ZVAL(z) 									\
	do {												\
		(z) = (zval*)emalloc(sizeof(zval_gc_info));		\
		GC_ZVAL_INIT(z);								\
	} while (0)

如上所示的代码，在宏定义中使用了 **do{ }while(0)** 语句格式。如果我们搜索整个PHP的源码目录，会发现这样的语句还有很多。那为什么在宏定义时需要使用do-while语句呢?
我们知道do-while循环语句是先执行再判断条件是否成立, 所以说至少会执行一次。当使用do{ }while(0)时代码肯定只执行一次, 肯定只执行一次的代码为什么要放在do-while语句里呢?
这种方式适用于宏定义中存在多语句的情况。如下所示代码：  

    [c]
    #define TEST(a, b)  a++;b++;

    if (expr)
        TEST(a, b);
    else
        do_else();

代码进行预处理后，会变成：

    [c]
    if (expr)
        a++;b++;
    else
        do_else();

这样if-else的结构就被破坏了: if后面有两个语句, 这样是无法编译通过的, 那为什么非要do-while而不是简单的用{}括起来呢.这样也能保证if后面只有一个语句。
例如上面的例子,在调用宏TEST的时候后面加了一个分号, 虽然这个分号可有可无, 但是出于习惯我们一般都会写上. 那如果是把宏里的代码用{}括起来,加上最后的那个分号.
还是不能通过编译. 所以一般的多表达式宏定义中都采用do-while(0)的方式.


## 3. #line 预处理
***

    [c]
    #line 838 "Zend/zend_language_scanner.c"

\#line预处理用于改变当前的行号和文件名。  
如上所示代码，将当前的行号改变为838,文件名Zend/zend_language_scanner.c  
它的作用体现在编译器的编写中，我们知道编译器对C 源码编译过程中会产生一些中间文件，通过这条指令，可以保证文件名是固定的，不会被这些中间文件代替，有利于进行调试分析。


## 4.PG宏
***
PG宏的定义在main/php_globals.h文件。
PG宏是PHP的核心全局变量宏。
如下所示代码为其定义。

    [c]
    #ifdef ZTS
    # define PG(v) TSRMG(core_globals_id, php_core_globals *, v)
    extern PHPAPI int core_globals_id;
    #else
    # define PG(v) (core_globals.v)
    extern ZEND_API struct _php_core_globals core_globals;
    #endif

如上，ZTS是线程安全的标记，这个在以后的章节会详细介绍，这里就不再说明。
我们这里只说明在非线程安全下的PG宏中相关子字段。如下所示，为宏所对应结构的各个字段。各字段的说明见代码注释。

    [c]
    struct _php_core_globals {
            zend_bool magic_quotes_gpc; //  是否对输入的GET/POST/Cookie数据使用自动字符串转义。
            zend_bool magic_quotes_runtime; //是否对运行时从外部资源产生的数据使用自动字符串转义
            zend_bool magic_quotes_sybase;  //   是否采用Sybase形式的自动字符串转义

            zend_bool safe_mode;    //  是否启用安全模式

            zend_bool allow_call_time_pass_reference;   //是否强迫在函数调用时按引用传递参数
            zend_bool implicit_flush;   //是否要求PHP输出层在每个输出块之后自动刷新数据

            long output_buffering;  //输出缓冲区大小(字节)

            char *safe_mode_include_dir;    //在安全模式下，该组目录和其子目录下的文件被包含时，将跳过UID/GID检查。
            zend_bool safe_mode_gid;    //在安全模式下，默认在访问文件时会做UID比较检查
            zend_bool sql_safe_mode;
            zend_bool enable_dl;    //是否允许使用dl()函数。dl()函数仅在将PHP作为apache模块安装时才有效。

            char *output_handler;   // 将所有脚本的输出重定向到一个输出处理函数。

            char *unserialize_callback_func;    // 如果解序列化处理器需要实例化一个未定义的类，这里指定的回调函数将以该未定义类的名字作为参数被unserialize()调用，
            long serialize_precision;   //将浮点型和双精度型数据序列化存储时的精度(有效位数)。

            char *safe_mode_exec_dir;   //在安全模式下，只有该目录下的可执行程序才允许被执行系统程序的函数执行。

            long memory_limit;  //一个脚本所能够申请到的最大内存字节数(可以使用K和M作为单位)。
            long max_input_time;    // 每个脚本解析输入数据(POST, GET, upload)的最大允许时间(秒)。

            zend_bool track_errors; //是否在变量$php_errormsg中保存最近一个错误或警告消息。
            zend_bool display_errors;   //是否将错误信息作为输出的一部分显示。
            zend_bool display_startup_errors;   //是否显示PHP启动时的错误。
            zend_bool log_errors;   // 是否在日志文件里记录错误，具体在哪里记录取决于error_log指令
            long      log_errors_max_len;   //设置错误日志中附加的与错误信息相关联的错误源的最大长度。
            zend_bool ignore_repeated_errors;   //   记录错误日志时是否忽略重复的错误信息。
            zend_bool ignore_repeated_source;   //是否在忽略重复的错误信息时忽略重复的错误源。
            zend_bool report_memleaks;  //是否报告内存泄漏。
            char *error_log;    //将错误日志记录到哪个文件中。

            char *doc_root; //PHP的”根目录”。
            char *user_dir; //告诉php在使用 /~username 打开脚本时到哪个目录下去找
            char *include_path; //指定一组目录用于require(), include(), fopen_with_path()函数寻找文件。
            char *open_basedir; // 将PHP允许操作的所有文件(包括文件自身)都限制在此组目录列表下。
            char *extension_dir;    //存放扩展库(模块)的目录，也就是PHP用来寻找动态扩展模块的目录。

            char *upload_tmp_dir;   // 文件上传时存放文件的临时目录
            long upload_max_filesize;   // 允许上传的文件的最大尺寸。

            char *error_append_string;  // 用于错误信息后输出的字符串
            char *error_prepend_string; //用于错误信息前输出的字符串

            char *auto_prepend_file;    //指定在主文件之前自动解析的文件名。
            char *auto_append_file; //指定在主文件之后自动解析的文件名。

            arg_separators arg_separator;   //PHP所产生的URL中用来分隔参数的分隔符。

            char *variables_order;  // PHP注册 Environment, GET, POST, Cookie, Server 变量的顺序。

            HashTable rfc1867_protected_variables;  //  RFC1867保护的变量名，在main/rfc1867.c文件中有用到此变量

            short connection_status;    //  连接状态，有三个状态，正常，中断，超时
            short ignore_user_abort;    //  是否即使在用户中止请求后也坚持完成整个请求。

            unsigned char header_is_being_sent; //  是否头信息正在发送

            zend_llist tick_functions;  //  仅在main目录下的php_ticks.c文件中有用到，此处定义的函数在register_tick_function等函数中有用到。

            zval *http_globals[6];  // 存放GET、POST、SERVER等信息

            zend_bool expose_php;   //  是否展示php的信息

            zend_bool register_globals; //  是否将 E, G, P, C, S 变量注册为全局变量。
            zend_bool register_long_arrays; //   是否启用旧式的长式数组(HTTP_*_VARS)。
            zend_bool register_argc_argv;   //  是否声明$argv和$argc全局变量(包含用GET方法的信息)。
            zend_bool auto_globals_jit; //  是否仅在使用到$_SERVER和$_ENV变量时才创建(而不是在脚本一启动时就自动创建)。

            zend_bool y2k_compliance;   //是否强制打开2000年适应(可能在非Y2K适应的浏览器中导致问题)。

            char *docref_root;  // 如果打开了html_errors指令，PHP将会在出错信息上显示超连接，
            char *docref_ext;   //指定文件的扩展名(必须含有’.')。

            zend_bool html_errors;  //否在出错信息中使用HTML标记。
            zend_bool xmlrpc_errors;   

            long xmlrpc_error_number;

            zend_bool activated_auto_globals[8];

            zend_bool modules_activated;    //  是否已经激活模块
            zend_bool file_uploads; //是否允许HTTP文件上传。
            zend_bool during_request_startup;   //是否在请求初始化过程中
            zend_bool allow_url_fopen;  //是否允许打开远程文件
            zend_bool always_populate_raw_post_data;    //是否总是生成$HTTP_RAW_POST_DATA变量(原始POST数据)。
            zend_bool report_zend_debug;    //  是否打开zend debug，仅在main/main.c文件中有使用。

            int last_error_type;    //  最后的错误类型
            char *last_error_message;   //  最后的错误信息
            char *last_error_file;  //  最后的错误文件
            int  last_error_lineno; //  最后的错误行

            char *disable_functions;    //该指令接受一个用逗号分隔的函数名列表，以禁用特定的函数。
            char *disable_classes;  //该指令接受一个用逗号分隔的类名列表，以禁用特定的类。
            zend_bool allow_url_include;    //是否允许include/require远程文件。
            zend_bool exit_on_timeout;  //  超时则退出
    #ifdef PHP_WIN32
            zend_bool com_initialized;
    #endif
            long max_input_nesting_level;   //最大的嵌套层数
            zend_bool in_user_include;  //是否在用户包含空间

            char *user_ini_filename;    //  用户的ini文件名
            long user_ini_cache_ttl;    //  ini缓存过期限制

            char *request_order;    //  优先级比variables_order高，在request变量生成时用到，个人觉得是历史遗留问题

            zend_bool mail_x_header;    //  仅在ext/standard/mail.c文件中使用，
            char *mail_log;

            zend_bool in_error_log;
    };

上面的字段很大一部分是与php.ini文件中的配置项对应的。
如此一来，可以预知在PHP内核启动时，加载php.ini时将会将相关配置项赋值给PG宏。
此时，想到一个问题，PG宏存在的意义是什么？仅仅是为了保存ini文件中的信息吗？既然这是核心全局变量，那如何区分核心呢？这个界限是什么？
