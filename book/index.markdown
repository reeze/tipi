#目录

- 第0章 开篇
	* 本书的组织结构和阅读说明

- [第一章 准备工作和背景知识][prepare-and-background]
	* [第一节 环境搭建][build-env]
	* [第二节 PHP源码布局及阅读方法][code-structure]
	* [第三节 PHP实现中的常用代码][common-code-in-php-src]
	* [第四节 小结][01-summary]

- [第二章 概览][survey]
	* [第一节 PHP生命周期及Zend引擎概览][php-life-cycle]
	* [第二节 SAPI][sapi-overview]
        + [PHP以模块方式注册到Apache][php-module-in-apache]
        + [嵌入式PHP][embedding-php]
        + [Fastcgi][fastcgi]
	* [第三节 脚本的执行][script-execution] (reeze)
		+ [词法分析和语法分析][lex-and-yacc]
		+ [opcode][opcode]
	* [第四节 小结][02-summary]

- [第三章 变量及数据类型][variables]
	* [第一节 变量的内部结构][variables-in-memory]
    * [第二节 常量][const-var]
	* [第三节 预定义变量][init-var]
	* [第四节 静态变量][static-var]
	* [第五节 类型提示(Type Hinting)的实现][receive-arg]
	* [第六节 变量的作用域][none]
		+ [global语句][var-global]
		+ [作用域及定义方式][var-scope]
		+ [局部变量][var-function]
	* [第七节 数据类型转换][type-cast]
	* [第八节 小结][03-summary]


- 第四章 函数的实现
    * [第一节 函数的内部结构][function-struct]
    * [第二节 函数的定义,传参及返回值][function-define](pan)
    * [第三节 函数的调用和执行][function-call](er)
    * 第四节 匿名函数(create_function, clouser)
    * 第五节 小结

- 第五章 类和面向对象

- 第六章 内存管理
	* 第一节 内存布局结构
	* 第二节 Heap层的实现
	* 第三节 内存管理的数据结构和相关算法
	* 第四节 内存分配的相关函数
	* 第五节 内存分配和回收
		+ 何时何地及时如何使用内存管理
	* 第六节 写时复制(Copy on write)的实现及fastcache
	* 第七节 小结
	
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

[prepare-and-background]: 	?p=chapt01/01-00-prepare-and-background
[build-env]: 				?p=chapt01/01-01-php-env-building
[code-structure]: 			?p=chapt01/01-02-code-structure
[common-code-in-php-src]: 	?p=chapt01/01-03-comm-code-in-php-src
[01-summary]: 				?p=chapt01/01-04-summary

[survey]: 				?p=chapt02/02-00-overview
[php-life-cycle]: 		?p=chapt02/02-01-php-life-cycle-and-zend-engine
[sapi-overview]: 		?p=chapt02/02-02-00-overview
[php-module-in-apache]: ?p=chapt02/02-02-01-apache-php-module
[embedding-php]: 		?p=chapt02/02-02-02-embedding-php
[fastcgi]: 				?p=chapt02/02-02-03-fastcgi
[script-execution]: 	?p=chapt02/02-03-00-how-php-script-get-executed
[lex-and-yacc]: 		?p=chapt02/02-03-01-lex-and-yacc
[opcode]: 				?p=chapt02/02-03-02-opcode
[02-summary]: 			?p=chapt02/02-04-summary

[variables]:            ?p=chapt03/03-00-variable-and-data-types
[variables-in-memory]: 	?p=chapt03/03-01-var-memory
[const-var]: 			?p=chapt03/03-02-const-var
[init-var]: 			?p=chapt03/03-03-init-var
[static-var]:           ?p=chapt03/03-04-static-var
[receive-arg]: 			?p=chapt03/03-05-receive-arg
[var-global]: 			?p=chapt03/03-06-01-var-global
[var-scope]: 			?p=chapt03/03-06-02-var-scope
[var-function]: 		?p=chapt03/03-06-03-var-function
[type-cast]: 			?p=chapt03/03-07-type-cast
[03-summary]: 			?p=chapt03/03-08-summary


[function-struct]:   	?p=chapt04/04-01-function-struct
[function-define]:   	?p=chapt04/04-02-function-define-param-return
[function-call]:   		?p=chapt04/04-03-function-call


[none]:		?
