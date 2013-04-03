# 第三节 PHP中的线程安全

## 缘起TSRM
当PHP运行在多线程服务器（Apache2-worker或者IIS），或者说当PHP在多线程的生命周期时，
在一定的时间内，一个进程空间中会存在多个线程，每一个线程都会类似于一个单进程的方式处理PHP的脚本（这里与单进程有一些区别，比如会公用模块初始化）。
在脚本运行，每个线程都会试图读写进程内存空间中的全局变量，当多个线程并发时，就会产生常见的数据一致性等并发问题。

为解决线程并发问题，PHP引入了TSRM： 线程安全资源管理器(Thread Safe Resource Manager)。
TRSM 的实现代码在 PHP 源码的 /TSRM 目录下，调用随处可见，通常，我们称之为 TSRM 层。
一般来说，TSRM 层只会在被指明需要的时候才会在编译时启用(比如,Apache2+worker MPM，一个基于线程的MPM)，
因为Win32下的Apache来说，是基于多线程的，所以这个层在Win32下总是被开启的。


## TSRM的实现


## TSRM的使用



## 参考资料
*  [究竟什么是TSRMLS_CC？- 54chen](http://www.54chen.com/php-tech/what-is-tsrmls_cc.html)
http://blog.codinglabs.org/articles/zend-thread-safety.html

