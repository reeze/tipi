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
我们就整个流程进行简单的说明，并在其中穿插介绍一些用到的重要函数。

### 初始化操作
 过程说明见代码注释

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

fcgi_listen函数主要用于创建、绑定socket并开始监听

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

在fcgi_accept_request函数中，处理连接请求，忽略受限制客户的请求，调用fcgi_read_request函数（定义在fastcgi.c文件），分析请求的信息，将相关的变量写到对应的变量中。
其中在读取请求内容时调用了safe_read方法。如下所示：
**[main() -> fcgi_accept_request() -> fcgi_read_request() -> safe_read()]**

    [c]
    static inline ssize_t safe_read(fcgi_request *req, const void *buf, size_t count)
    {
        size_t n = 0;
        do {
        ... //  省略  对win32的处理
            ret = read(req->fd, ((char*)buf)+n, count-n);   //  非win版本的读操作
        ... //  省略
        } while (n != count);

    }

在请求初始化完成，读取请求完毕后，就该处理请求的PHP文件了。假设此次请求为PHP_MODE_STANDARD则会调用php_execute_script执行PHP文件。
在此函数中它先初始化此文件相关的一些内容，然后再调用zend_execute_scripts函数，对PHP文件进行词法分析和语法分析，生成中间代码，
并执行zend_execute函数，从而执行这些中间代码。关于整个脚本的执行请参见第三节 脚本的执行。


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


## 参考资料
***
以下为本篇文章对于一些定义引用的参考资料：  
http://www.fastcgi.com/drupal/node/2  
http://baike.baidu.com/view/641394.htm  

