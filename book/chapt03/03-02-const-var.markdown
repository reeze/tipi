# 第二节 常量

常量，顾名思义是一个常态的量值。它与值只绑定一次，它的作用在于有肋于增加程序的可读性和可靠性。
在PHP中，常量的名字是一个简单值的标识符，在脚本执行期间该值不能改变。
和变量一样，常量默认为大小写敏感，但是按照我们的习惯常量标识符总是大写的。
常量名和其它任何 PHP 标签遵循同样的命名规则。合法的常量名以字母或下划线开始，后面跟着任何字母，数字或下划线。
在这一小节我们一起看下常量与我们常见的变量有啥区别，它在执行期间的不可改变的特性是如何实现的以及常量的定义过程。

首先看下常量与变量的区别，常量是在变量的zval结构的基础上添加了一额外的元素。如下所示为PHP中常量的内部结构。

## 常量的内部结构

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

## define定义常量的过程
define是PHP的内置函数，在Zend/zend_builtin_functions.c文件中定义了此函数的实现。如下所示为部分源码：

    [c]

    /* {{{ proto bool define(string constant_name, mixed value, boolean case_insensitive=false)
       Define a new constant */
    ZEND_FUNCTION(define)
    {
            if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "sz|b", &name,
                    &name_len, &val, &non_cs) == FAILURE) {
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

上面的代码已经对对象和类常量做了简化处理，
其实现上是一个将传递的参数传递给新建的zend_constant结构，并将这个结构体注册到常量列表中的过程。
关于大小写敏感，函数的第三个参数表示是否**大小不敏感**，默认为false（大小写敏感）。
这个参数最后会赋值给zend_constant结构体的flags字段。其在函数中实现代码如下：

    [c]
    zend_bool non_cs = 0;   //  第三个参数的临时存储变量
    int case_sensitive = CONST_CS;  //  是否大小写敏感，默认为1

    if(non_cs) {    //  输入为真，大小写不敏感
        case_sensitive = 0;
    }

    c.flags = case_sensitive; //     赋值给结构体字段

从上面的define函数的实现来看，**PHP对于常量的名称在定义时其实是没有所谓的限制**。如下所示代码：

    [php]
    define('^_^', 'smile');

    if (defined('^_^')) {
        echo 'yes';
    }else{
        echo 'no';
    }
    //$var = ^_^;   //语法错误
    $var = constant("^_^"); 

通过defined函数测试表示，‘^_^’这个常量已经定义好，这样的常量无法直接调用，
只能使用constant()方法来获取到，否则在语法解析时会报错，因为它不是一个合法的标示符。

除了CONST_CS标记，常量的flags字段通常还可以用CONST_PERSISTENT和CONST_CT_SUBST。

CONST_PERSISTENT表示这个常量需要持久化。这里的持久化内存申请时的持久化是一个概念，
非持久常量会在请求结束时释放该常量，如果读者还不清楚PHP的生命周期，可以参考，
[PHP生命周期](?p=chapt02/02-01-php-life-cycle-and-zend-engine)这一小节，
也就是说，如果是非持久常量，会在RSHUTDOWN阶段就该常量释放，否则只会在MSHUTDOWN阶段
将内存释放，在用户空间，也就是用户定义的常量都是非持久化的，通常扩展和内核定义的常量
会设置为持久化，因为如果常量被释放了，而下次请求又需要使用这个常量，该常量就必须
在请求时初始化一次，而对于常量这些不变的量来说就是个没有意义的重复计算。

在PHP，只有标量才能被定义为常量，而在内核C代码中，一些字符串，数字等作为代码的一部分，
并且他们被定义成PHP内核中的常量。这些常量属于静态对象，被给定了一个绝对地址，当释放这些常量时，
我们并不需要将这些静态的内存释放掉，从而也就有了我们这里的CONST_PERSISTENT标记。

CONST_CT_SUBST我们看注释可以知道其表示Allow compile-time substitution（在编译时可被替换）。
在PHP内核中这些常量包括：TRUE、FALSE、NULL、ZEND_THREAD_SAFE和ZEND_DEBUG_BUILD五个。

## 标准常量的初始化

通过define()函数定义的常量的模块编号都是PHP_USER_CONSTANT，这表示是用户定义的常量。
除此之外我们在平时使用较多的常量：如错误报告级别E_ALL, E_WARNING等常量就有点不同了。
这些是PHP内置定义的常量，他们属于标准常量。

在Zend引擎启动后，会执行如下的标准常量注册操作。
**php_module_startup() -> zend_startup() -> zend_register_standard_constants()]**

    [c]

    void zend_register_standard_constants(TSRMLS_D)
    {
        ... //  若干常量以REGISTER_MAIN_LONG_CONSTANT设置，
        REGISTER_MAIN_LONG_CONSTANT("E_ALL", E_ALL, CONST_PERSISTENT | CONST_CS);
        ...
    }

REGISTER_MAIN_LONG_CONSTANT()是一个宏，用于注册一个长整形数字的常量，因为C是强类型
语言，不同类型的数据等分别处理，以上的宏展开到下面这个函数。

    [c]
    ZEND_API void zend_register_long_constant(const char *name, uint name_len,
            long lval, int flags, int module_number TSRMLS_DC)
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

代码很容易理解，前面看到注册内置常量都是用了CONST_PERSISTENT标志位，也就是说，
这些常量都是持久化常量。

## 魔术常量

PHP提供了大量的预定义常量，有一些是内置的，也有一些是扩展提供的，只有在加载了这些扩展库时才会出现。

不过PHP中有七个魔术常量，他们的值其实是变化的，它们的值随着它们在代码中的位置改变而改变。
所以称他们为魔术常量。例如 \_\_LINE\_\_ 的值就依赖于它在脚本中所处的行来决定。
这些特殊的常量不区分大小写。在手册中这几个变量的简单说明如下：

几个 PHP 的“魔术常量”

    名称                         | 说明
    ---------------------------- | --------------------------------
    \_\_LINE\_\_					 | 文件中的当前行号
    \_\_FILE\_\_					 | 文件的完整路径和文件名。如果用在被包含文件中，则返回被包含的文件名。自 PHP 4.0.2 起，\_\_FILE\_\_ 总是包含一个绝对路径（如果是符号连接，则是解析后的绝对路径），而在此之前的版本有时会包含一个相对路径。
    \_\_DIR\_\_						 | 文件所在的目录。如果用在被包括文件中，则返回被包括的文件所在的目录。它等价于 dirname(\_\_FILE\_\_)。除非是根目录，否则    目录中名不包括末尾的斜杠。（PHP 5.3.0中新增）
    \_\_FUNCTION\_\_				 | 函数名称（PHP 4.3.0 新加）。自 PHP 5 起本常量返回该函数被定义时的名字（区分大小写）。在 PHP 4 中该值总是小写>    字母的
    \_\_CLASS\_\_					 | 类的名称（PHP 4.3.0 新加）。自 PHP 5 起本常量返回该类被定义时的名字（区分大小写）。在 PHP 4 中该值总是小写字母的
    \_\_METHOD\_\_					 | 类的方法名（PHP 5.0.0 新加）。返回该方法被定义时的名字（区分大小写）。
    \_\_NAMESPACE\_\_				 | 当前命名空间的名称（大小写敏感）。这个常量是在编译时定义的（PHP 5.3.0 新增）

>**NOTE**
>PHP中的一些比较*魔术*的变量或者标示都习惯使用下划线来进行区分，
>所以在编写PHP代码时也尽量不要定义双下线开头的常量。

PHP内核会在词法解析时将这些常量的内容赋值进行替换，而不是在运行时进行分析。
如下PHP代码：

    [php]
    <?PHP
    echo __LINE__;
    function demo() {
        echo __FUNCTION__;
    }
    demo();


PHP已经在词法解析时将这些常量换成了对应的值，以上的代码可以看成如下的PHP代码：

    [php]
    <?PHP
    echo 2;
    function demo() {
        echo "demo";
    }
    demo();

如果我们使用VLD扩展查看以上的两段代码生成的中间代码，你会发现其结果是一样的。

前面我们有说PHP是在词法分析时做的赋值替换操作，以\_\_FUNCTION\_\_为例，
在Zend/zend_language_scanner.l文件中，\_\_FUNCTION\_\_是一个需要分析垢元标记（token）：

    [c]
    <ST_IN_SCRIPTING>"__FUNCTION__" {
        char *func_name = NULL;

        if (CG(active_op_array)) {
            func_name = CG(active_op_array)->function_name;
        }

        if (!func_name) {
            func_name = "";
        }
        zendlval->value.str.len = strlen(func_name);
        zendlval->value.str.val = estrndup(func_name, zendlval->value.str.len);
        zendlval->type = IS_STRING;
        return T_FUNC_C;
    }


就是这里，当当前中间代码处于一个函数中时，则将当前函数名赋值给zendlval(也就是tokenT_FUNC_C的值内容)，
如果没有，则将空字符串赋值给zendlval(因此在顶级作用域名中直接打印\_\_FUNCTION\_\_会输出空格)。
这个值在语法解析时会直接赋值给返回值。这样我们就在生成的中间代码中看到了这些常量的位置都已经赋值好了。

和\_\_FUNCTION\_\_类似，在其附近的位置，上面表格中的其它常量也进行了类似的操作。

>**NOTE**
>前面有个比较特殊的地方，当func_name不存在时，\_\_FUNCTION\_\_被替换成空字符串，
>你可能会想，怎么会有变量名不存在的方法呢，这里并不是匿名方法，匿名方法的function_name
>并不是空的，而是:"{closure}", 有兴趣的读者可以去代码找找在那里给定义了。
>
>这里涉及PHP字节码的编译，在PHP中，一个函数或者一个方法会变编译成一个opcode array
>opcode array的function name字段标示的就是这个函数或方法的名称，同时一段普通的代码
>也会被当成一个完整实体被编译成一段opcode array，只不过没有函数名称。
>
>在PHP5.4中增加了对于trait类的常量定义：\_\_TRAIT\_\_。

**这些常量其实相当于一个常量模板，或者说是一个占位符，在词法解析时这些模板或占位符就被替换成实际的值**
