# 第二节 常量
常量，顾名思义是一个常态的量值。在PHP中，它是一个简单值的标识符，在脚本执行期间该值不能改变。
和变量一样，常量默认为大小写敏感，但是按照我们的习惯常量标识符总是大写的。
常量名和其它任何 PHP 标签遵循同样的命名规则。合法的常量名以字母或下划线开始，后面跟着任何字母，数字或下划线。
在这一小节我们一起看下常量与我们常见的变量有啥区别，它在执行期间的不可改变的特性是如何实现的以及常量的定义过程。

首先看下常量与变量的区别，常量是在变量的zval结构的基础上添加了一额外的元素。如下所示为PHP中常量的内部结构。

### 常量的内部结构

    [c]
    typedef struct _zend_constant {
        zval value; /* zval结构，PHP内部变量的存储结构，在第一小节有说明 */
        int flags;  /* 常量的标记如 CONST_PERSISTENT | CONST_CS */
        char *name; /* 常量名称 */
        uint name_len;  
        int module_number;  /* 模块号 */
    } zend_constant;

在Zend/zend_constants.h文件的33行可以看到如上所示的结构定义。
在常量的结构中，除了与变量一样的zval结构，它还包括属于常量的标记，常量名以及常量所在的模块号。

在了解了常量的存储结构后，我们来看PHP常量的定义过程。一个例子。

    [c]
    define('TIPI', 'Thinking In PHP Internal');

这是一个很常规的常量定义过程，它使用了PHP的内置函数**define**。常量名为TIPI，值为一个字符串，存放在zval结构中。
从这个例子出发，我们看下define定义常量的过程实现。

### define定义常量的过程
define是PHP的内置函数，在zend_builtin_functions.c文件中定义了此函数的实现。如下所示为部分源码：

    [c]

    /* {{{ proto bool define(string constant_name, mixed value, boolean case_insensitive=false)
       Define a new constant */
    ZEND_FUNCTION(define)
    {
            if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "sz|b", &name, &name_len, &val, &non_cs) == FAILURE) {
                    return;
            }

            ... // 类常量定义 此处不做介绍

            ... // 值类型判断和处理

            c.value = *val;
            zval_copy_ctor(&c.value);
            if (val_free) {
                    zval_ptr_dtor(&val_free);
            }
            c.flags = case_sensitive; /* non persistent */
            c.name = zend_strndup(name, name_len);
            c.name_len = name_len+1;
            c.module_number = PHP_USER_CONSTANT;
            if (zend_register_constant(&c TSRMLS_CC) == SUCCESS) {
                    RETURN_TRUE;
            } else {
                    RETURN_FALSE;
            }
    }
    /* }}} */

上面的代码是已经对对象和类常量做了简化处理，
其实现基本上是一个将传递的参数传递给新建的zend_constant结构，并将这个结构体注册到常量列表中的过程。

### defined判断常量是否设置
和define一样， defined的实现也在zend_builtin_functions.c文件，
其实现是一个读取参数变量，调用 zend_get_constant_ex函数获取常量的值来判断常量是否存在的过程。
而zend_get_constant_ex函数不仅包括了常规的常规的常量获取，还包括类常量的获取，
最后是通过zend_get_constant函数获取常量的值。在zend_get_constant函数中，基本上是通过下面的代码来获取常量的值。

    [c]
    zend_hash_find(EG(zend_constants), name, name_len+1, (void **) &c)

只是这个前面和后面对name有一些特别的处理。

### 标准常量的初始化
我们以cgi模式为例说明标准常量的初始化。
整个调用顺序如下所示：  
**[php_cgi_startup() -> php_module_startup() -> zend_startup() -> zend_register_standard_constants()]**

    [c]

    void zend_register_standard_constants(TSRMLS_D)
    {
        ... //  若干常量以REGISTER_MAIN_LONG_CONSTANT设置，
        REGISTER_MAIN_LONG_CONSTANT("E_ALL", E_ALL, CONST_PERSISTENT | CONST_CS);
        ...
    }

REGISTER_MAIN_LONG_CONSTANT宏展开是以zend_register_long_constant实现。
zend_register_long_constant函数将常量中值的类型，值，名称及模块号赋值给新的zend_constant。
并调用zend_register_constant添加到全局的常量列表中。

**[php_cgi_startup() -> php_module_startup() -> zend_startup() -> zend_register_standard_constants() -> zend_register_constant]**

    [c]
    ZEND_API void zend_register_long_constant(const char *name, uint name_len, long lval, int flags, int module_number TSRMLS_DC)
    {
        zend_constant c;

        c.value.type = IS_LONG;
        c.value.value.lval = lval;
        c.flags = flags;
        c.name = zend_strndup(name, name_len-1);
        c.name_len = name_len;
        c.module_number = module_number;
        zend_register_constant(&c TSRMLS_CC);
    }

zend_register_constant函数首先根据常量中的c->flags判断是否区分大小写，如果不区分，则名字统一为小写，如果包含"\\\\"，也统一成小写。否则为定义的名字
然后将调用下面的语句将当前常量添加到EG(zend_constants)。EG(zend_constants)是一个HashTable（这在前面的章节中说明），下面的代码是将常量添加到这个HashTable中。

    [c]
    zend_hash_add(EG(zend_constants), name, c->name_len, (void *) c, sizeof(zend_constant), NULL)==FAILURE)

在php_module_startup函数中，除了zend_startup函数中有注册标准的常量，它本身体通过宏REGISTER_MAIN_LONG_CONSTANT等注册了一些常量，如：PHP_VERSION，PHP_OS等。

关于接口和类中的常量我们将在后面的类所在章节中详细说明。
