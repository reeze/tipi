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

PHP的cgi实现是以socket编程实现一个tcp或udp协议的服务器，当启动时，创建tcp/udp协议的服务器的socket监听。

    [c]
    /* Create, bind socket and start listen on it */
        if ((listen_socket = socket(sa.sa.sa_family, SOCK_STREAM, 0)) < 0 ||
    #ifdef SO_REUSEADDR
            setsockopt(listen_socket, SOL_SOCKET, SO_REUSEADDR, (char*)&reuse, sizeof(reuse)) < 0 ||
    #endif
            bind(listen_socket, (struct sockaddr *) &sa, sock_len) < 0 ||
            listen(listen_socket, backlog) < 0) {

            fprintf(stderr, "Cannot bind/listen socket - [%d] %s.\n",errno, strerror(errno));
            return -1;
        }

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

## PHP-FPM
***
PHP-FPM (FastCGI Process Manager) is an alternative PHP FastCGI implementation with some additional features useful for sites of any size, especially busier sites.


## 参考资料
***
以下为本篇文章对于一些定义引用的参考资料：  
http://www.fastcgi.com/drupal/node/2  
http://baike.baidu.com/view/641394.htm
http://zh.wikipedia.org/zh-cn/%E9%80%9A%E7%94%A8%E7%BD%91%E5%85%B3%E6%8E%A5%E5%8F%A3  
