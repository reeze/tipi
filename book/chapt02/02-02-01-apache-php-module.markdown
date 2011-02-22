# PHP以模块方式注册到Apache
为了让Apache支持php,我们通常的做法是编译一个apche的php模块, 在配置中配置让mod_php来处理php文件的请求.
php模块通过注册apache2的ap_hook_post_config挂钩, 在apache启动的时候启动php模块以接受php的请求.

下面介绍apache模块加载的基本知识以及PHP对于apache的实现

## Apache模块加载机制简介
***
Apache的模块可以在运行的时候动态装载，这意味着对服务器可以进行功能扩展而不需要重新对源代码进行编译，甚至根本不需要停止服务器。
我们所需要做的仅仅是给服务器发送信号HUP或者AP_SIG_GRACEFUL通知服务器重新载入模块。
但是在动态加载之前，我们需要将模块编译成为动态链接库。此时的动态加载就是加载动态链接库。  
Apache中对动态链接库的处理是通过模块mod_so来完成的，因此mod_so模块不能被动态加载，
它只能被静态编译进Apache的核心。这意味着它是随着Apache一起启动的。  
比如我们要加载PHP模块，那么首先我们需要在httpd.conf文件中添加一行：

    [c]
    LoadModule php5_module modules/mod_php5.so

该命令的第一个参数是模块的名称，名称可以在模块实现的源码中找到。第二个选项是该模块所处的路径。
如果需要在服务器运行时加载模块，可以通过发送信号HUP或者AP_SIG_GRACEFUL给服务器，一旦接受到该信号，Apache将重新装载模块，而不需要重新启动服务器。

下面我们以PHP模块的加载为例，分析Apache的模块加载过程。在配置文件中添加了所上所示的指令后，Apache在加载模块时会根据模块名查找模块并加载，
对于每一个模块，Apache必须保证其文件名是以“mod_”开始的，如php的mod_php5.c。如果命名格式不对，Apache将认为此模块不合法。
module结构的name属性在最后是通过宏STANDARD20_MODULE_STUFF以\__FILE__体现。 关于这点可以在后面介绍mod_php5模块时有看到。
通过之前指令中指定的路径找到相关的动态链接库文件，Apache通过内部的函数获取动态链接库中的内容，并将模块的内容加载到内存中的指定变量中。  
在真正激活模块之前，Apache会检查所加载的模块是否为真正的Apache模块，这个检测是通过检查magic字段进行的。而magic字段是通过宏STANDARD20_MODULE_STUFF体现，在这个宏中magic的值为MODULE_MAGIC_COOKIE，MODULE_MAGIC_COOKIE定义如下：

    [c]
    #define MODULE_MAGIC_COOKIE 0x41503232UL /* "AP22" */

最后Apache会调用相关函数(ap_add_loaded_module)将模块激活，此处的激活就是将模块放入相应的链表中(ap_top_modules链表：ap_top_modules链表用来保存Apache中所有的被激活的模块，包括默认的激活模块和激活的第三方模块。）

## Apache2的mod_php5模块说明
***
Apache2的mod_php5模块包括sapi/apache2handler和sapi/apache2filter两个目录
在apache2_handle/mod_php5.c文件中，模块定义的相关代码如下：

    [c]
    AP_MODULE_DECLARE_DATA module php5_module = {
        STANDARD20_MODULE_STUFF,
            /* 宏，包括版本，小版本，模块索引，模块名，下一个模块指针等信息，其中模块名以__FILE__体现 */
        create_php_config,		/* create per-directory config structure */
        merge_php_config,		/* merge per-directory config structures */
        NULL,					/* create per-server config structure */
        NULL,					/* merge per-server config structures */
        php_dir_cmds,			/* 模块定义的所有的指令 */
        php_ap2_register_hook
            /* 注册钩子，此函数通过ap_hoo_开头的函数在一次请求处理过程中对于指定的步骤注册钩子 */
    };

它所对应的是apache的module结构，module的结构定义如下：

    [c]
    typedef struct module_struct module;
    struct module_struct {
        int version;
        int minor_version;
        int module_index;
        const char *name;
        void *dynamic_load_handle;
        struct module_struct *next;
        unsigned long magic;
        void (*rewrite_args) (process_rec *process);
        void *(*create_dir_config) (apr_pool_t *p, char *dir);
        void *(*merge_dir_config) (apr_pool_t *p, void *base_conf, void *new_conf);
        void *(*create_server_config) (apr_pool_t *p, server_rec *s);
        void *(*merge_server_config) (apr_pool_t *p, void *base_conf, void *new_conf);
        const command_rec *cmds;
        void (*register_hooks) (apr_pool_t *p);
    }

上面的模块结构与我们在mod_php5.c中所看到的结构有一点不同，这是由于STANDARD20_MODULE_STUFF的原因，这个宏它包含了前面8个字段的定义。
STANDARD20_MODULE_STUFF宏的定义如下：

    [c]
    /** Use this in all standard modules */
    #define STANDARD20_MODULE_STUFF	MODULE_MAGIC_NUMBER_MAJOR, \
                    MODULE_MAGIC_NUMBER_MINOR, \
                    -1, \
                    __FILE__, \
                    NULL, \
                    NULL, \
                    MODULE_MAGIC_COOKIE, \
                                    NULL      /* rewrite args spot */


php_dir_cmds所定义的内容如下：

    [c]
    const command_rec php_dir_cmds[] =
    {
        AP_INIT_TAKE2("php_value", php_apache_value_handler, NULL,
            OR_OPTIONS, "PHP Value Modifier"),
        AP_INIT_TAKE2("php_flag", php_apache_flag_handler, NULL,
            OR_OPTIONS, "PHP Flag Modifier"),
        AP_INIT_TAKE2("php_admin_value", php_apache_admin_value_handler,
            NULL, ACCESS_CONF|RSRC_CONF, "PHP Value Modifier (Admin)"),
        AP_INIT_TAKE2("php_admin_flag", php_apache_admin_flag_handler,
            NULL, ACCESS_CONF|RSRC_CONF, "PHP Flag Modifier (Admin)"),
        AP_INIT_TAKE1("PHPINIDir", php_apache_phpini_set, NULL,
            RSRC_CONF, "Directory containing the php.ini file"),
        {NULL}
    };

以上为php模块定义的指令表。它实际上是一个command_rec结构的数组。
当Apache遇到指令的时候将逐一遍历各个模块中的指令表，查找是否有哪个模块能够处理该指令，
如果找到，则调用相应的处理函数，如果所有指令表中的模块都不能处理该指令，那么将报错。
如上可见，php模块仅提供php_value等5个指令。

php_ap2_register_hook函数的定义如下：

    [c]
    void php_ap2_register_hook(apr_pool_t *p)
    {
        ap_hook_pre_config(php_pre_config, NULL, NULL, APR_HOOK_MIDDLE);
        ap_hook_post_config(php_apache_server_startup, NULL, NULL, APR_HOOK_MIDDLE);
        ap_hook_handler(php_handler, NULL, NULL, APR_HOOK_MIDDLE);
        ap_hook_child_init(php_apache_child_init, NULL, NULL, APR_HOOK_MIDDLE);
    }

以上代码声明了pre_config,post_config,handler和child_init 4个挂钩以及对应的处理函数。
其中pre_config,post_config,child_init是启动挂钩，它们在服务器启动时调用。
handler挂钩是请求挂钩，它在服务器处理请求时调用。其中在post_config挂钩中启动php。
它通过php_apache_server_startup函数实现。php_apache_server_startup函数通过调用sapi_startup启动sapi,
并通过调用php_apache2_startup来注册sapi module struct（此结构在本节开头中有说明）,
最后调用php_module_startup来初始化PHP, 其中又会初始化ZEND引擎,以及填充zend_module_struct中
的treat_data成员(通过php_startup_sapi_content_types)等。


## Apache的运行过程
***
Apache的运行分为启动阶段和运行阶段。
在启动阶段，Apache为了获得系统资源最大的使用权限，将以特权用户root（\*nix系统）或超级管理员Administrator(Windows系统)完成启动，并且整个过程处于一个单进程单线程的环境中，。
这个阶段包括配置文件解析(如http.conf文件)、模块加载(如mod_php,mod_perl)和系统资源初始化（例如日志文件、共享内存段、数据库连接等）等工作。

Apache的启动阶段执行了大量的初始化操作，并且将许多比较慢或者花费比较高的操作都集中在这个阶段完成，以减少了后面处理请求服务的压力。

在运行阶段，Apache主要工作是处理用户的服务请求。
在这个阶段，Apache放弃特权用户级别，使用普通权限，这主要是基于安全性的考虑，防止由于代码的缺陷引起的安全漏洞。
Apache对HTTP的请求可以分为连接、处理和断开连接三个大的阶段。同时也可以分为11个小的阶段，依次为：
Post-Read-Request，URI Translation，Header Parsing，Access Control，Authentication，Authorization，MIME Type Checking，FixUp，Response，Logging，CleanUp


## Apache Hook机制
***
Apache 的Hook机制是指：Apache 允许模块(包括内部模块和外部模块，例如mod_php5.so,mod_perl.so等)将自定义的函数注入到请求处理循环中。换句话说，模块可以在 Apache的任何一个处理阶段中挂接(Hook)上自己的处理函数，从而参与Apache的请求处理过程。
mod_php5.so/ php5apache2.dll就是将所包含的自定义函数，通过Hook机制注入到Apache中，在Apache处理流程的各个阶段负责处理php请求。
关于Hook机制在Windows系统开发也经常遇到，在Windows开发既有系统级的钩子，又有应用级的钩子。

以上介绍了apache的加载机制，hook机制，apache的运行过程以及php5模块的相关知识，下面简单的说明在查看源码中的一些常用对象。

## Apache常用对象
*** 
在说到Apache的常用对象时，我们不得不先说下httpd.h文件。httpd.h文件包含了Apache的所有模块都需要的核心API。
它定义了许多系统常量。但是更重要的是它包含了下面一些对象的定义。

**request_rec对象**  
当一个客户端请求到达Apache时，就会创建一个request_rec对象，当Apache处理完一个请求后，与这个请求对应的request_rec对象也会随之被释放。
request_rec对象包括与一个HTTP请求相关的所有数据，并且还包含一些Apache自己要用到的状态和客户端的内部字段。

**server_rec对象**  
server_rec定义了一个逻辑上的WEB服务器。如果有定义虚拟主机，每一个虚拟主机拥有自己的server_rec对象。
server_rec对象在Apache启动时创建，当整个httpd关闭时才会被释放。
它包括服务器名称，连接信息，日志信息，针对服务器的配置，事务处理相关信息等
server_rec对象是继request_rec对象之后第二重要的对象。

**conn_rec对象**  
conn_rec对象是TCP连接在Apache的内部体现。
它在客户端连接到服务器时创建，在连接断开时释放。


# 参考资料
***
《The Apache Modules Book--Application Development with Apache》
