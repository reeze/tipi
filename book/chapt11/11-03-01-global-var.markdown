# 第三节 使用全局变量原理分析

仅仅会用调用 api 是不够的，能分析其背后的原理才更有意义，下面针对前面的每一步进行分析。
因为PHP在编译的时候分为是否需要使用线程安全，
可以先理解PHP的线程安全是对每个PHP线程中需要使用的全局变量的拷贝隔离，每个线程使用自己线程中得全局变量，互不干扰。
归根结底和非线程安全下地原理是解耦，下面的一些宏都是添加了是否线程安全判断的，我们只需要分析非线程安全下的原理即可。

## 定义全局变量

对于 `ZEND_BEGIN_MODULE_GLOBALS` 和 `ZEND_END_MODULE_GLOBALS` 两个宏的定义在非线程安全下地定义

    [c]
    #define ZEND_BEGIN_MODULE_GLOBALS(module_name)		\
        typedef struct _zend_##module_name##_globals {
    #define ZEND_END_MODULE_GLOBALS(module_name)		\
        } zend_##module_name##_globals;
        
也就说我们前面定义的

    [c]
    ZEND_BEGIN_MODULE_GLOBALS(tipi_globals_demo)
       long  global_value;
    ZEND_END_MODULE_GLOBALS(tipi_globals_demo)

展开之后就是

    [c]
    typedef struct _zend_tipi_globals_demo_globals {
        long  global_value;
    } zend_tipi_globals_demo_globals;
    
## 定义全局变量

    [c]
    #define ZEND_DECLARE_MODULE_GLOBALS(module_name)                           \
        zend_##module_name##_globals module_name##_globals;
    
这里相当于声明了一个变量

    [c]
    zend_tipi_globals_demo_globals tipi_globals_demo_globals;
    
## 初始化全局变量

    [c]
    #define ZEND_INIT_MODULE_GLOBALS(module_name, globals_ctor, globals_dtor)	\
    	globals_ctor(&module_name##_globals);

前面我使用

    [c]
    ZEND_INIT_MODULE_GLOBALS(tipi_globals_demo, php_tipi_globals_demo_init_globals, NULL);
    
就比较好理解了。

## 使用全局变量

    [c]
    #define TIPI_GLOBALS_DEMO_G(v) (tipi_globals_demo_globals.v)

## 综述

在非线程安全的情况下，实际就是对C语言的全局变量使用的一个封装，并且是讲该模块的所有的全局变量放在以了一个结构体中。
线程安全完整的分析可以参考[第八章 PHP中的线程安全](/book/?p=chapt08/08-03-zend-thread-safe-in-php)。