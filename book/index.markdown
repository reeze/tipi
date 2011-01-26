#目录

- 第0章 开篇
	* 本书的组织结构和阅读说明

- [第一章 准备工作和背景知识][prepare-and-background]
	* [第一节 环境搭建][build-env]
	* [第二节 PHP源码布局及阅读方法][code-structure]
	* [第三节 PHP实现中的常用代码][common-code-in-php-src]
	* [第四节 小结][01-summary]

- 第二章 概览
	* 第一节 PHP生命周期及Zend引擎概览
	* [第二节 SAPI][sapi-overview]
        + [PHP以模块方式注册到Apache][php-module-in-apache]
        + [嵌入式PHP][embedding-php]
        + [Fastcgi][fastcgi]
	* [第三节 脚本的执行][script-execution]
		+ 词法分析,语法分析及opcode

- 第三章 变量及数据类型
	* [第一节 变量的内存表示及弱类型实现][variables-in-memory]
	* 第二节 变量的作用域及其生命周期(er)
	* 第三节 数据类型转换:现式及隐式转换
	* 第四接 类型提示(Type Hinting)的实现
	* 第五节 PHP中的全局变量(pan)
    * 第六节 [PHP中的常量][const-var]

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

[prepare-and-background]: ?p=chapt01/01-00-prepare-and-background
[build-env]: ?p=chapt01/01-01-php-env-building
[code-structure]: ?p=chapt01/01-02-code-structure
[common-code-in-php-src]: ?p=chapt01/01-03-comm-code-in-php-src
[01-summary]: ?p=chapt01/01-04-summary

[sapi-overview]: ?p=chapt02/02-02-00-overview
[php-module-in-apache]: ?p=chapt02/02-02-01-apache-php-module
[embedding-php]: ?p=chapt02/02-02-02-embedding-php
[fastcgi]: ?p=chapt02/02-02-03-fastcgi
[script-execution]: ?p=chapt02/02-03-how-php-script-get-executed

[const-var]: ?p=chapt03/03-06-const-var
[variables-in-memory]: ?p=chapt03/03-01-var-memory

