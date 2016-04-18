# 第三节 使用全局变量

这里说的全局变量指的是我们在使用PHP中配合 `global` 关键字的变量。

## 准备

还是采用原型框架的方式，不再多说

    [c]
    [root@localhost ext]# cat globals_var.proto 
    int tipi_get_global_var()
    [root@localhost ext]# ./ext_skel --extname=tipi_globals_demo --proto=globals_var.proto

## 定义全局变量

在头文件的 `ZEND_BEGIN_MODULE_GLOBALS` 宏和 `ZEND_END_MODULE_GLOBALS` 宏之间添加全局变量。
在我们用原型模式生产的骨架代码的头文件 `php_tipi_globals_demo.h` 中，全局变量使用默认是注释的，需要注释打开

    [c]
    /*
        Declare any global variables you may need between the BEGIN
        and END macros here:
    
    ZEND_BEGIN_MODULE_GLOBALS(tipi_globals_demo)
        zend_long  global_value;
        char *global_string;
    ZEND_END_MODULE_GLOBALS(tipi_globals_demo)
    */
    
我们仅仅使用一个 `long` 变量作为演示
    
    [c]
    ZEND_BEGIN_MODULE_GLOBALS(tipi_globals_demo)
       long  global_value;
    ZEND_END_MODULE_GLOBALS(tipi_globals_demo)
    
## 声明全局变量

将 `tipi_globals_demo.c` 中 `ZEND_DECLARE_MODULE_GLOBALS` 的注释，声明全局变量，下面其他步骤的修改，不做特殊说明也均对于 `tipi_globals_demo.c` 文件。
    
    [c]
    ZEND_DECLARE_MODULE_GLOBALS(tipi_globals_demo)

## 初始化全局变量

打开 `php_tipi_globals_demo_init_globals` 的注释，初始化全局变量

    [c]
    static void php_tipi_globals_demo_init_globals(zend_tipi_globals_demo_globals *tipi_globals_demo_globals)
    {
        tipi_globals_demo_globals->global_value = 10;
    }

## 注册全局变量

在 `PHP_MINIT_FUNCTION` 里注册化全局变量

    [c]
    PHP_MINIT_FUNCTION(tipi_globals_demo)
    {
        ZEND_INIT_MODULE_GLOBALS(tipi_globals_demo, php_tipi_globals_demo_init_globals, NULL);
        return SUCCESS;
    }

## 使用全局变量

根据在头文件 `php_tipi_globals_demo.h` 中的定义，我们通过 `TIPI_GLOBALS_DEMO_G(v)` 来访问修改全局变量 `v` 。    
我们在原型中定义了一个函数，名为 `tipi_get_global_var`，我们将其补充完整，其功能就是调用一次加 1。

    [c]
    PHP_FUNCTION(tipi_get_global_var)
    {
       TIPI_GLOBALS_DEMO_G(global_value)++;
       RETURN_LONG(TIPI_GLOBALS_DEMO_G(global_value));
    }


## 编译测试

编译不再重复说明，在命令行测试

    [shell]
    [root@localhost tipi_globals_demo]# php7 -d"extension=tipi_globals_demo.so" -r "var_dump(tipi_get_global_var());var_dump(tipi_get_global_var());"
    int(11)
    int(12)

调用了两次 `tipi_get_global_var`，全局变量 `global_value` 自增了两次，所以从10变成12，作用域在函数之外，符合我们的预期。