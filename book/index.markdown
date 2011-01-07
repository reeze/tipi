#目录

- 第0章 开篇
	* 本书的组织结构和阅读说明

- 第一章 准备工作和背景知识
	* [第一节 环境搭建][build-env]
	* [第二节 PHP源码布局及阅读方法][code-structure]
	* [第三节 PHP实现中的常见代码][common-code-in-php-src]

- 第二章 概览
	* 第一节 PHP生命周期及Zend引擎概览
	* 第二节 SAPI协议以及WebServer: cli/mod_php/fastcgi/fpm
		+ 真实环境中的PHP: PHP和WebServer,命令程序
		+ Fastcgi/fpm及嵌入式PHP
	* 第三节 脚本的执行
		+ 词法分析,语法分析及opcode

- 第三章 变量及数据类型
	* 第一节 变量的内存表示
	* 第二节 变量的作用域及其生命周期
	* 第三节 数据类型及PHP的弱类型实现
	* 第四接 数据类型转换:现式及隐式转换
	* 第五节 类型提示(Type Hinting)的实现
	* 第六节 PHP中的全局变量

- 第四章 内存管理
	* 第一节 内存布局结构
	* 第二节 Heap层的实现
	* 第三节 内存管理的数据结构和相关算法
	* 第四节 内存分配的相关函数
	* 第五节 内存分配和回收
		+ 何时何地及时如何使用内存管理
	* 第六节 写时复制(Copy on write)的实现及fastcache

- 第五章 函数实现
	* 第一节 用户定义函数及内置函数
	* 第二节 函数的内部实现
	* 第三节 函数的定义,传参及返回值
	* 第四节 函数的调用和执行

- 第六章 类和面向对象

- 第七章 垃圾收集

- 第八章 线程安全

- 第九章 错误和异常处理

- 第十章 文件和流

- 第十一章 网络编程

- 第十二章 Zend虚拟机

- 第十三章 扩展开发

- 第十四章 PHP新功能
	* 命名空间(Namespace)
	* 匿名函数
	* 闭包
	* Traits

- 第十五章 CPHP以外: PHP编译器
	* HipHop
	* phc
	* Roadsend

- 第十六章 开发实例
	* 第一节 opcode缓存扩展
	* 第二节 性能监控及优化扩展
	* 第三节 扩展PHP语法,为PHP增加语法特性

[build-env]: ?p=chapt01/01-01-env-build
[code-structure]: ?p=chapt01/01-02-code-structure
[common-code-in-php-src]: ?p=chapt01/01-03-comm-code-in-php-src
