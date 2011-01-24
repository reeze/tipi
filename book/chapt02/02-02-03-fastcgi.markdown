# FastCGI

## FastCGI简介
***

### 什么是CGI
CGI全称是“通用网关接口”(Common Gateway Interface)，
它可以让一个客户端，从网页浏览器向执行在Web服务器上的程序，请求数据。
CGI描述了客户端和这个程序之间传输数据的一种标准。
CGI的一个目的是要独立于任何语言的，所以CGI可以用任何一种语言编写，只要这种语言具有标准输入、输出和环境变量。如php,perl,tcl等

### 　什么是FastCGI
FastCGI像是一个常驻(long-live)型的CGI，它可以一直执行着，只要激活后，不会每次都要花费时间去fork一次(这是CGI最为人诟病的fork-and-execute 模式)。它还支持分布式的运算, 即 FastCGI 程序可以在网站服务器以外的主机上执行并且接受来自其它网站服务器来的请求。

FastCGI是语言无关的、可伸缩架构的CGI开放扩展，其主要行为是将CGI解释器进程保持在内存中并因此获得较高的性能。众所周知，CGI解释器的反复加载是CGI性能低下的主要原因，如果CGI解释器保持在内存中并接受FastCGI进程管理器调度，则可以提供良好的性能、伸缩性、Fail- Over特性等等。

### FastCGI的工作原理
   1. Web Server启动时载入FastCGI进程管理器（IIS ISAPI或Apache Module)
   1. FastCGI进程管理器自身初始化，启动多个CGI解释器进程(可见多个php-cgi)并等待来自Web Server的连接。
   1. 当客户端请求到达Web Server时，FastCGI进程管理器选择并连接到一个CGI解释器。Web server将CGI环境变量和标准输入发送到FastCGI子进程php-cgi。
   1. FastCGI子进程完成处理后将标准输出和错误信息从同一连接返回Web Server。当FastCGI子进程关闭连接时，请求便告处理完成。FastCGI子进程接着等待并处理来自FastCGI进程管理器(运行在Web Server中)的下一个连接。 在CGI模式中，php-cgi在此便退出了。

## PHP中的CGI实现
***

PHP的cgi实现本质是是以socket编程实现一个tcp或udp协议的服务器，当启动时，创建tcp/udp协议的服务器的socket监听，并接收相关请求进行处理。这只是请求的处理，在此基础上添加模块初始化，sapi初始化，模块关闭，sapi关闭等就构成了整个cgi的生命周期。
程序是从cgi_main.c文件的main函数开始，而在main函数中调用了定义在fastcgi.c文件中的初始化，监听等函数。我们从main函数开始，看看PHP对于fastcgi的实现。

这里将整个流程分为初始化操作，请求处理，关闭操作三个部分。
我们先就整个流程进行简单的说明，在这个之后，我们取其中一些用到的重要函数进行介绍。

### 初始化操作
 过程说明代码注释

    [c]
    /* {{{ main
     */
    int main(int argc, char *argv[])
    {
    ...
    sapi_startup(&cgi_sapi_module); //  1512行 启动sapi,调用sapi全局构造函数，初始化sapi_globals_struct结构体
    ... //  根据启动参数，初始化信息

    if (cgi_sapi_module.startup(&cgi_sapi_module) == FAILURE) { //  模块初始化 调用php_cgi_startup方法
    ...
    }

    ...
    if (bindpath) {
        fcgi_fd = fcgi_listen(bindpath, 128);   //  实现socket监听，调用fcgi_init初始化
        ...
    }

    if (fastcgi) {
        ...
		/* library is already initialized, now init our request */
		fcgi_init_request(&request, fcgi_fd);   //  request内存分配，初始化变量
    }

在fcgi_listen函数中关键代码是创建、绑定socket并开始监听

    [c]
        if ((listen_socket = socket(sa.sa.sa_family, SOCK_STREAM, 0)) < 0 ||
            ...
            bind(listen_socket, (struct sockaddr *) &sa, sock_len) < 0 ||
            listen(listen_socket, backlog) < 0) {
            ...
        }

### 请求处理操作流程
 过程说明见代码注释

    [c]
    	while (parent) {
			do {
				pid = fork();   //  生成新的子进程
				switch (pid) {
				case 0: //  子进程
					parent = 0;

					/* don't catch our signals */
					sigaction(SIGTERM, &old_term, 0);   //  终止信号
					sigaction(SIGQUIT, &old_quit, 0);   //  终端退出符
					sigaction(SIGINT,  &old_int,  0);   //  终端中断符
					break;
                    ...
                    default:
					/* Fine */
					running++;
					break;
			} while (parent && (running < children));

        ...
        	while (!fastcgi || fcgi_accept_request(&request) >= 0) {
			SG(server_context) = (void *) &request;
			init_request_info(TSRMLS_C);
			CG(interactive) = 0;
                        ...
                }

### 关闭操作流程
 过程说明代码注释

    [c]
    ...
    php_request_shutdown((void *) 0);   //  php请求关闭函数
    ...
    fcgi_shutdown();    //  fcgi的关闭 销毁fcgi_mgmt_vars变量
    php_module_shutdown(TSRMLS_C);  //  模块关闭    清空sapi,关闭zend引擎 销毁内存，清除垃圾等
    sapi_shutdown();    //  sapi关闭  sapi全局变量关闭等
    ...

写操作：

    [c]
    static inline ssize_t safe_write(fcgi_request *req, const void *buf, size_t count)


写操作中，针对*nix系统的操作

    [c]
    ret = write(req->fd, ((char*)buf)+n, count-n);

读操作定义：

    [c]
    static inline ssize_t safe_read(fcgi_request *req, const void *buf, size_t count)

读操作中，针对*nix系统的操作

    [c]
    ret = read(req->fd, ((char*)buf)+n, count-n);

**处理请求**
处理请求的函数定义如下：

    [c]
    static int fcgi_read_request(fcgi_request *req)

请求的定义：

    [c]
    typedef struct _fcgi_request {
        int            listen_socket;
    #ifdef _WIN32
        int            tcp;
    #endif
        int            fd;
        int            id;
        int            keep;
        int            closed;

        int            in_len;
        int            in_pad;

        fcgi_header   *out_hdr;
        unsigned char *out_pos;
        unsigned char  out_buf[1024*8];
        unsigned char  reserved[sizeof(fcgi_end_request_rec)];

        HashTable     *env;
    } fcgi_request;

信号设置

    [c]
    static void fcgi_setup_signals(void)
    {
        struct sigaction new_sa, old_sa;

        sigemptyset(&new_sa.sa_mask);   //  sigemptyset函数初始化信号集合
        new_sa.sa_flags = 0;
        new_sa.sa_handler = fcgi_signal_handler;
        sigaction(SIGUSR1, &new_sa, NULL);  //  SIGUSR1 用户定义的信号
        sigaction(SIGTERM, &new_sa, NULL);  //  SIGTERM 终止
        sigaction(SIGPIPE, NULL, &old_sa);  //  SIGPIPE 写到无读进程的管道
        if (old_sa.sa_handler == SIG_DFL) {
            sigaction(SIGPIPE, &new_sa, NULL);
        }
    }

sinaction函数的功能是检查或修改与指定信号相关联的处理动作。此函数取代了unix早期使用的signal函数。

### 启动参数说明

    [shell]
     php <file> [args...]
    -a               Run interactively
    -b <address:port>|<port> Bind Path for external FASTCGI Server mode
    -C               Do not chdir to the script's directory
    -c <path>|<file> Look for php.ini file in this directory
    -n               No php.ini file will be used
    -d foo[=bar]     Define INI entry foo with value 'bar'
    -e               Generate extended information for debugger/profiler
    -f <file>        Parse <file>.  Implies `-q'
    -h               This help
    -i               PHP information
    -l               Syntax check only (lint)
    -m               Show compiled in modules
    -q               Quiet-mode.  Suppress HTTP Header output.
    -s               Display colour syntax highlighted source.
    -v               Version number
    -w               Display source with stripped comments and whitespace.
    -z <file>        Load Zend extension <file>.
    -T <count>       Measure execution time of script repeated <count> times.

与这些启动参数说明相关的实现在结构体中有体现：

    [c]
    static const opt_struct OPTIONS[] = {
        {'a', 0, "interactive"},
        {'b', 1, "bindpath"},
        {'C', 0, "no-chdir"},
        {'c', 1, "php-ini"},
        {'d', 1, "define"},
        {'e', 0, "profile-info"},
        {'f', 1, "file"},
        {'h', 0, "help"},
        {'i', 0, "info"},
        {'l', 0, "syntax-check"},
        {'m', 0, "modules"},
        {'n', 0, "no-php-ini"},
        {'q', 0, "no-header"},
        {'s', 0, "syntax-highlight"},
        {'s', 0, "syntax-highlighting"},
        {'w', 0, "strip"},
        {'?', 0, "usage"},/* help alias (both '?' and 'usage') */
        {'v', 0, "version"},
        {'z', 1, "zend-extension"},
        {'T', 1, "timing"},
        {'-', 0, NULL} /* end of args */
    };

## PHP-FPM
***
PHP-FPM (FastCGI Process Manager) is an alternative PHP FastCGI implementation with some additional features useful for sites of any size, especially busier sites.


## 参考资料
***
以下为本篇文章对于一些定义引用的参考资料：
http://www.fastcgi.com/drupal/node/2  
http://baike.baidu.com/view/641394.htm
http://zh.wikipedia.org/zh-cn/%E9%80%9A%E7%94%A8%E7%BD%91%E5%85%B3%E6%8E%A5%E5%8F%A3  
