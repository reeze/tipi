# 第二节 SAPI概述
前一小节介绍了PHP的生命周期, 在其生命周期的各个阶段，一些与服务相关的操作都是通过SAPI接口实现。
这些实现的物理位置在PHP源码的SAPI目录。这个目录存放了PHP对各个服务器抽象层的代码，
例如命令行程序的实现, Apache的mod_php模块实现以及fastcgi的实现等等。
而这每个服务器的接口都会对应一个或多个目录。

在各个服务器抽象层之间遵守着相同的约定，这里我们称之为SAPI接口。
SAPI接口在代码级是以_sapi_module_struct结构体的形式体现。
每个服务器都需要实现属于自己的_sapi_module_struct结构体（SAPI接口）。
在PHP的源码中，当需要调用服务器相关信息时，全部通过SAPI接口中对应方法调用实现，
而这对应的方法在各个服务器抽象层实现时都会有各自的实现。

>**NOTE**
>其实有很大一部分的接口方法使用的是默认方法。

如图2.4所示，为SAPI的简单示意图。

![图2.4 SAPI的简单示意图](../images/chapt02/02-02-01-sapi.png)

以cgi模式和apache2服务器为例，它们的启动方法如下：

    [c]
    cgi_sapi_module.startup(&cgi_sapi_module)   //  cgi模式 cgi/cgi_main.c文件

    apache2_sapi_module.startup(&apache2_sapi_module);
     //  apache2服务器  apache2handler/sapi_apache2.c文件

这里的cgi_sapi_module是sapi_module_struct结构体的静态变量。
它的startup方法指向php_cgi_startup函数指针。在这个结构体中除了startup函数指针，还有许多其它方法或字段。
其部分定义如下：

    [c]
    struct _sapi_module_struct {
        char *name;         //  名字（标识用）
        char *pretty_name;  //  更好理解的名字（自己翻译的）

        int (*startup)(struct _sapi_module_struct *sapi_module);    //  启动函数
        int (*shutdown)(struct _sapi_module_struct *sapi_module);   //  关闭方法

        int (*activate)(TSRMLS_D);  // 激活
        int (*deactivate)(TSRMLS_D);    //  停用

        int (*ub_write)(const char *str, unsigned int str_length TSRMLS_DC);
         //  不缓存的写操作(unbuffered write)
        void (*flush)(void *server_context);    //  flush
        struct stat *(*get_stat)(TSRMLS_D);     //  get uid
        char *(*getenv)(char *name, size_t name_len TSRMLS_DC); //  getenv

        void (*sapi_error)(int type, const char *error_msg, ...);   /* error handler */

        int (*header_handler)(sapi_header_struct *sapi_header, sapi_header_op_enum op,
            sapi_headers_struct *sapi_headers TSRMLS_DC);   /* header handler */

         /* send headers handler */
        int (*send_headers)(sapi_headers_struct *sapi_headers TSRMLS_DC);

        void (*send_header)(sapi_header_struct *sapi_header,
                void *server_context TSRMLS_DC);   /* send header handler */

        int (*read_post)(char *buffer, uint count_bytes TSRMLS_DC); /* read POST data */
        char *(*read_cookies)(TSRMLS_D);    /* read Cookies */

        /* register server variables */
        void (*register_server_variables)(zval *track_vars_array TSRMLS_DC);

        void (*log_message)(char *message);     /* Log message */
        time_t (*get_request_time)(TSRMLS_D);   /* Request Time */
        void (*terminate_process)(TSRMLS_D);    /* Child Terminate */

        char *php_ini_path_override;    //  覆盖的ini路径

        ...
        ...
    };

以上的这些结构在各服务器的接口实现中都有定义。如Apache2的定义：

    [c]
    static sapi_module_struct apache2_sapi_module = {
        "apache2handler",
        "Apache 2.0 Handler",

        php_apache2_startup,				/* startup */
        php_module_shutdown_wrapper,			/* shutdown */

        ...
    }

在PHP的源码中，SAPI存在多个服务接口，其文件结构如图2.5所示：

![图2.5 SAPI文件结构图](../images/chapt02/02-02-02-file-structure.png)

整个SAPI类似于一个面向对象中的模板方法模式的应用。
SAPI.c和SAPI.h文件所包含的一些函数就是模板方法模式中的抽象模板，
各个服务器对于sapi_module的定义及相关实现则是一个个具体的模板。
只是这里没有继承。
