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
其实现基本上是一个将传递的参数传递给新建的zend_constant结构，并将这个结构体注册到常量列表中的过程。
关于大小写敏感，函数的第三个参数表示是否**大小不敏感**，默认为false（大小写敏感）。这个参数最后会赋值给zend_constant结构体的flags字段。其在函数中实现代码如下：

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
只能使用constant语句来使用，
否则在语法解析时会显示错误。

除了CONST_CS标记，常量的flags字段通常还可以用CONST_PERSISTENT和CONST_CT_SUBST。

CONST_PERSISTENT表示这个常量需要持久化，当然，这只是字面意思，从常量的销毁函数free_zend_constant看，

    [c]
    void free_zend_constant(zend_constant *c)
    {
        if (!(c->flags & CONST_PERSISTENT)) {
            zval_dtor(&c->value);
        }
        free(c->name);
    }

如果常量设置了flags包含 CONST_PERSISTENT，则在销毁时不会销售常量结构中的value字段。
比如，我们现在在内核中定义了一个常量TIPI，其value包含一个字符串“深入理解PHP内核”。
一般来说当销毁一个变量时，它所占有的内存将会被完全回收; 同样,当我们销毁一个常量时，这个常量所占的内存也会被完全回收。
如果我们设置了常量的flags包含 CONST_PERSISTENT，则在释放该常量时就不会释放“深入理解PHP内核”所占用的内存。
从PHP的实现角度看，这个标记是有意义的，为什么这样说呢？
我们知道，一般来说，语言中的一个对象（非面向对象中的对象）的生存期可以对应三种内存存储机制：

* 栈：按后进先出的方式分分配，通常与子程序的调用和退出相关，常用于局部变量
* 堆：可以在任意时刻分配，在C语言中，一般是使用malloc/calloc/realloc等分配，对于这样分配的内存，
需要使用free释放内存，否则会造成内存泄露，程序结束后会由OS回收。
* 静态： 被给定一个绝对地址，在程序的整个执行过程中都保持不变。比如一些全局变量，静态变量或代码中的字符串等。

在PHP，只有标量才能被定义为常量，而在内核C代码中，一些字符串，数字等作为代码的一部分，
并且他们被定义成PHP内核中的常量。这些常量属于静态对象，被给定了一个绝对地址，当释放这些常量时，
我们并不需要将这些静态的内存释放掉，从而也就有了我们这里的CONST_PERSISTENT标记。

CONST_CT_SUBST我们看注释可以知道其表示Allow compile-time substitution（在编译时可被替换）。
在PHP内核中这些常量包括：TRUE、FALSE、NULL、ZEND_THREAD_SAFE和ZEND_DEBUG_BUILD五个。

在上面的代码中有用到一个判断常量是否定义的函数，下面我们看看这个函数是如何实现的。

## defined判断常量是否设置
和define一样， defined的实现也在Zend/zend_builtin_functions.c文件，
其实现是一个读取参数变量，调用 zend_get_constant_ex函数获取常量的值来判断常量是否存在的过程。
而zend_get_constant_ex函数不仅包括了常规的常规的常量获取，还包括类常量的获取，
最后是通过zend_get_constant函数获取常量的值。在zend_get_constant函数中，基本上是通过下面的代码来获取常量的值。

    [c]
    zend_hash_find(EG(zend_constants), name, name_len+1, (void **) &c)

除此之外，只是调用这个函数之前和之后对name有一些特殊的处理。

## 标准常量的初始化
以上通过define定义的常量的模块编号都是PHP_USER_CONSTANT，这表示是用户定义的常量。
除此之外我们在平时使用较多的，如在显示所有级别错误报告时使用的E_ALL常量就有点不同了。
这里我们以cgi模式为例说明标准常量的定义过程。
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

zend_register_constant函数首先根据常量中的c->flags判断是否区分大小写，
如果不区分，则名字统一为小写，如果包含"\\\\"，也统一成小写。否则为定义的名字
然后将调用下面的语句将当前常量添加到EG(zend_constants)。
EG(zend_constants)是一个HashTable（这在前面的章节中说明），
下面的代码是将常量添加到这个HashTable中。

    [c]
    zend_hash_add(EG(zend_constants), name, c->name_len, (void *) c,
            sizeof(zend_constant), NULL)==FAILURE)

在php_module_startup函数中，除了zend_startup函数中有注册标准的常量，
它本身体通过宏REGISTER_MAIN_LONG_CONSTANT等注册了一些常量，如：PHP_VERSION，PHP_OS等。

关于接口和类中的常量我们将在后面的类所在章节中详细说明。

## 魔术常量

PHP向它运行的任何脚本提供了大量的预定义常量。
不过很多常量都是由不同的扩展库定义的，只有在加载了这些扩展库时才会出现，或者动态加载后，或者在编译时已经包括进去了。

有七个魔术常量它们的值随着它们在代码中的位置改变而改变。
例如 \__LINE__ 的值就依赖于它在脚本中所处的行来决定。
这些特殊的常量不区分大小写。在手册中这几个变量的简单说明如下：

几个 PHP 的“魔术常量”
<table>
<tr>
<th>名称</th> <th>说明</th>
</tr>
<tr>
<td>__LINE__</td><td>文件中的当前行号。</td>
</tr>
<tr>
<td>__FILE__</td><td>文件的完整路径和文件名。如果用在被包含文件中，则返回被包含的文件名。自 PHP 4.0.2 起，__FILE__ 总是包含一个绝对路径（如果是符号连接，则是解析后的绝对路径），而在此之前的版本有时会包含一个相对路径。</td>
</tr>
<tr>
<td>__DIR__</td><td>文件所在的目录。如果用在被包括文件中，则返回被包括的文件所在的目录。它等价于 dirname(__FILE__)。除非是根目录，否则目录中名不包括末尾的斜杠。（PHP 5.3.0中新增） =</td>
</tr>
<tr>
<td>__FUNCTION__</td><td>函数名称（PHP 4.3.0 新加）。自 PHP 5 起本常量返回该函数被定义时的名字（区分大小写）。在 PHP 4 中该值总是小写字母的。</td>
</tr>
<tr>
<td>__CLASS__</td><td>类的名称（PHP 4.3.0 新加）。自 PHP 5 起本常量返回该类被定义时的名字（区分大小写）。在 PHP 4 中该值总是小写字母的。</td>
</tr>
<tr>
<td>__METHOD__</td><td>类的方法名（PHP 5.0.0 新加）。返回该方法被定义时的名字（区分大小写）。</td>
</tr>
<tr>
<td>__NAMESPACE__</td><td>当前命名空间的名称（大小写敏感）。这个常量是在编译时定义的（PHP 5.3.0 新增）</td>
</tr>
<tr>
</table>

PHP内核会在词法解析时将这些相对静态的内容赋值给这些变量，而不是在运行时执行的分析。
如下PHP代码：

    [php]
    <?PHP
    echo __LINE__;
    function demo() {
        echo __FUNCTION__;
    }
    demo();


其实PHP已经在词法解析时将这些常量换成了对应的值，以上的代码可以看成如下的PHP代码：

    [php]
    <?PHP
    echo 2;
    function demo() {
        echo "demo";
    }
    demo();

如果我们使用VLD扩展查看以上的两段代码生成的中间代码，你会发现其结果是一样的。

前面我们有说PHP是在词法分析时做的赋值替换操作，以\__FUNCTION__为例，
在Zend/zend_language_scanner.l文件中，\__FUNCTION__是一个需要分析垢元标记（token）：

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


就是这里，当当前中间代码处于一个函数中时，则将当前函数名赋值给zendlval，
如果没有，则将空字符串赋值给zendlval(因此在顶级作用域名中直接打印\__FUNCTION__会输出空格)。
这个值在语法解析时会直接赋值给返回值。这样我们就在生成的中间代码中看到了这些常量的位置都已经赋值好了。

和\__FUNCTION__类似，在其附近的位置，上面表格中的其它常量也进行了类似的操作。

>**NOTE**
>在PHP5.4中增加了对于trait类的常量定义：\__TRAIT__。

**这些常量其实相当于一个常量模板，或者说是一个占位符，在词法解析时这些模板或占位符就被替换成实际的值**


