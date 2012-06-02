# 函数的参数
前一小节介绍了函数的定义，函数的定义是一个将函数名注册到函数列表的过程，在了解了函数的定义后，我们来看看函数的参数。
这一小节将包括用户自定义函数的参数、内部函数的参数和参数的传递：

## 用户自定义函数的参数

在[<<第三章第五小节 类型提示的实现>>][receive-arg]中，我们对于参数的类型提示做了分析，这里我们在这一小节的基础上，进行一些更详细的说明。
在经过词语分析，语法分析后，我们知道对于函数的参数检查是通过 **zend_do_receive_arg** 函数来实现的。在此函数中对于参数的关键代码如下：

    [php]
    CG(active_op_array)->arg_info = erealloc(CG(active_op_array)->arg_info,
            sizeof(zend_arg_info)*(CG(active_op_array)->num_args));
	cur_arg_info = &CG(active_op_array)->arg_info[CG(active_op_array)->num_args-1];
	cur_arg_info->name = estrndup(varname->u.constant.value.str.val,
            varname->u.constant.value.str.len);
	cur_arg_info->name_len = varname->u.constant.value.str.len;
	cur_arg_info->array_type_hint = 0;
	cur_arg_info->allow_null = 1;
	cur_arg_info->pass_by_reference = pass_by_reference;
	cur_arg_info->class_name = NULL;
	cur_arg_info->class_name_len = 0;

整个参数的传递是通过给中间代码的arg_info字段执行赋值操作完成。关键点是在arg_info字段。arg_info字段的结构如下：

    [php]
    typedef struct _zend_arg_info {
        const char *name;   /* 参数的名称*/
        zend_uint name_len;     /* 参数名称的长度*/
        const char *class_name; /* 类名 */
        zend_uint class_name_len;   /* 类名长度*/
        zend_bool array_type_hint;  /* 数组类型提示 */
        zend_bool allow_null;   /* 是否允许为NULL　*/
        zend_bool pass_by_reference;    /*　是否引用传递 */
        zend_bool return_reference; 
        int required_num_args;  
    } zend_arg_info;

>**NOTE**
>参数的值传递和参数传递的区分是通过 **pass_by_reference**参数在生成中间代码时实现的。

对于参数的个数，中间代码中包含的arg_nums字段在每次执行 **zend_do_receive_arg×× 时都会加1.如下代码：

    [php]
    CG(active_op_array)->num_args++;

并且当前参数的索引为CG(active_op_array)->num_args-1 .如下代码：

    [php]
    cur_arg_info = &CG(active_op_array)->arg_info[CG(active_op_array)->num_args-1];

以上的分析是针对函数定义时的参数设置，这些参数是固定的。而在实际编写程序时可能我们会用到可变参数。
此时我们会使用到函数 **func_num_args** 和 **func_get_args**。
它们是以内部函数存在。在 Zend\zend_builtin_functions.c 文件中找到这两个函数的实现。
首先我们来看func_num_args函数的实现。其代码如下：

    [c]
    /* {{{ proto int func_num_args(void)
       Get the number of arguments that were passed to the function */
    ZEND_FUNCTION(func_num_args)
    {
        zend_execute_data *ex = EG(current_execute_data)->prev_execute_data;

        if (ex && ex->function_state.arguments) {
            RETURN_LONG((long)(zend_uintptr_t)*(ex->function_state.arguments));
        } else {
            zend_error(E_WARNING,
    "func_num_args():  Called from the global scope - no function context");
            RETURN_LONG(-1);
        }
    }
    /* }}} */

在存在 ex->function_state.arguments的情况下，即函数调用时，返回ex->function_state.arguments转化后的值 ，否则显示错误并返回-1。
这里最关键的一点是EG(current_execute_data)。这个变量存放的是当前执行程序或函数的数据。此时我们需要取前一个执行程序的数据，为什么呢？
因为这个函数的调用是在进入函数后执行的。函数的相关数据等都在之前执行过程中。于是调用的是：

    [c]
    zend_execute_data *ex = EG(current_execute_data)->prev_execute_data;

>**NOTE**
>function_state等结构请参照本章第一小节。


在了解func_num_args函数的实现后，func_get_args函数的实现过程就简单了，它们的数据源是一样的，
只是前面返回的是长度，而这里返回了一个创建的数组。数组中存放的是从ex->function_state.arguments转化后的数据。

## 内部函数的参数
以上我们所说的都是用户自定义函数中对于参数的相关内容。下面我们开始讲解内部函数是如何传递参数的。
以常见的count函数为例。其参数处理部分的代码如下：

    [c]
    /* {{{ proto int count(mixed var [, int mode])
       Count the number of elements in a variable (usually an array) */
    PHP_FUNCTION(count)
    {
        zval *array;
        long mode = COUNT_NORMAL;

        if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "z|l",
             &array, &mode) == FAILURE) {
            return;
        }
        ... //省略
    }

这包括了两个操作：一个是取参数的个数，一个是解析参数列表。

**取参数的个数**

取参数的个数是通过ZEND_NUM_ARGS()宏来实现的。其定义如下：

    [c]
    #define ZEND_NUM_ARGS()		(ht)

>**NOTE**
>PHP3 中使用的是宏 ARG_COUNT

ht是在 Zend/zend.h文件中定义的宏 **INTERNAL_FUNCTION_PARAMETERS** 中的ht，如下：

    [c]
    #define INTERNAL_FUNCTION_PARAMETERS int ht, zval *return_value,
    zval **return_value_ptr, zval *this_ptr, int return_value_used TSRMLS_DC

**解析参数列表**

PHP内部函数在解析参数时使用的是 **zend_parse_parameters**。
它可以大大简化参数的接收处理工作，虽然它在处理可变参数时还有点弱。

其声明如下：

    [c]
    ZEND_API int zend_parse_parameters(int num_args TSRMLS_DC, char *type_spec, ...)

* 第一个参数num_args表明表示想要接收的参数个数，我们经常使用ZEND_NUM_ARGS() 来表示对传入的参数“有多少要多少”。
* 第二参数应该总是宏 TSRMLS_CC 。
* 第三个参数 type_spec 是一个字符串，用来指定我们所期待接收的各个参数的类型，有点类似于 printf 中指定输出格式的那个格式化字符串。
* 剩下的参数就是我们用来接收PHP参数值的变量的指针。

zend_parse_parameters() 在解析参数的同时会尽可能地转换参数类型，这样就可以确保我们总是能得到所期望的类型的变量。
任何一种标量类型都可以转换为另外一种标量类型，但是不能在标量类型与复杂类型（比如数组、对象和资源等）之间进行转换。
如果成功地解析和接收到了参数并且在转换期间也没出现错误，那么这个函数就会返回 SUCCESS，否则返回 FAILURE。
如果这个函数不能接收到所预期的参数个数或者不能成功转换参数类型时就会抛出一些错误信息。

第三个参数指定的各个参数类型列表如下所示：

* l - 长整形
* d - 双精度浮点类型
* s - 字符串 (也可能是空字节)和其长度
* b - 布尔型
* r - 资源，保存在 zval*
* a - 数组，保存在 zval*
* o - （任何类的）对象，保存在 zval *
* O - （由class entry 指定的类的）对象，保存在 zval *
* z - 实际的 zval*

除了各个参数类型，第三个参数还可以包含下面一些字符，它们的含义如下：

* | - 表明剩下的参数都是可选参数。如果用户没有传进来这些参数值，那么这些值就会被初始化成默认值。
* / - 表明参数解析函数将会对剩下的参数以 SEPARATE_ZVAL_IF_NOT_REF() 的方式来提供这个参数的一份拷贝，除非这些参数是一个引用。
* ! - 表明剩下的参数允许被设定为 NULL（仅用在 a、o、O、r和z身上）。如果用户传进来了一个 NULL 值，则存储该参数的变量将会设置为 NULL。

## 参数的传递

在PHP的运行过程中，如果函数有参数，当执行参数传递时，所传递参数的引用计数会发生变化。
如和Xdebug的作者[Derick Rethans](http://derickrethans.nl/who.html)在其文章
[php variables](http://derickrethans.nl/talks/phparch-php-variables-article.pdf)中的示例的类似代码：

    [php]
    function do_something($s) {
           xdebug_debug_zval('s');
            $s = 100;
            return $s;
    }

    $a = 1111;
    $b = do_something($a);
    echo $b;

如果你安装了xdebug，此时会输出s变量的refcount为3，如果使用debug_zval_dump，会输出4。
因为此内部函数调用也对refcount执行了加1操作。
这里的三个引用计数分别是：

* function stack中的引用
* function symbol table中引用
* 原变量$a的引用。

这个函数符号表只有用户定义的函数才需要，内置和扩展里的函数不需要此符号表。
debug_zval_dump()是内置函数，并不需要符号表，所以只增加了1。 
xdebug_debug_zval()传递的是变量名字符串，所以没有增加refcount。

每个PHP脚本都有自己专属的全局符号表，而每个用户自定义的函数也有自己的符号表，
这个符号表用来存储在这个函数作用域下的属于它自己的变量。当调用每个用户自定义的函数时，
都会为这个函数创建一个符号表，当这个函数返回时都会释放这个符号表。

当执行一个拥有参数的用户自定义的函数时，其实它相当于赋值一个操作，即$s = $a;
只是这个赋值操作的引用计数会执行两次，除了给函数自定义的符号表，还有一个是给函数栈。

参数的传递的第一步是SEND_VAR操作，这一步操作是在函数调用这一层级，如示例的PHP代码通过VLD生成的中间代码:

    
    compiled vars:  !0 = $a, !1 = $b
    line     # *  op                           fetch          ext  return  operands
    --------------------------------------------------------------------------------
    -
       2     0  >   EXT_STMT
             1      NOP
       7     2      EXT_STMT
             3      ASSIGN                                                   !0, 1111
       8     4      EXT_STMT
             5      EXT_FCALL_BEGIN
             6      SEND_VAR                                                 !0
             7      DO_FCALL                                      1          'demo'
             8      EXT_FCALL_END
             9      ASSIGN                                                   !1, $1
       9    10      EXT_STMT
            11      ECHO                                                     !1
            12    > RETURN                                                   1

    branch: #  0; line:     2-    9; sop:     0; eop:    12
    path #1: 0,
    Function demo:

函数调用是DO_FCALL，在此中间代码之前有一个SEND_VAR操作，此操作的作用是将实参传递给函数，
并且将它添加到函数栈中。最终调用的具体代码参见zend_send_by_var_helper_SPEC_CV函数，
在此函数中执行了引用计数加1（Z_ADDREF_P）操作和函数栈入栈操作（zend_vm_stack_push）。

与第一步的SEND操作对应，第二步是RECV操作。
RECV操作和SEND_VAR操作不同，它是归属于当前函数的操作，仅为此函数服务。
它的作用是接收SEND过来的变量，并将它们添加到当前函数的符号表。示例函数生成的中间代码如下：

    compiled vars:  !0 = $s
    line     # *  op                           fetch          ext  return  operands
    --------------------------------------------------------------------------------
    -
       2     0  >   EXT_NOP
             1      RECV                                                     1
       3     2      EXT_STMT
             3      ASSIGN                                                   !0, 10
       4     4      EXT_STMT
             5    > RETURN                                                   !0
       5     6*     EXT_STMT
             7*   > RETURN                                                   null

    branch: #  0; line:     2-    5; sop:     0; eop:     7

参数和普通局部变量一样 ，都需要进行操作，都需要保存在符号表（或CVs里，不过查找一般都是直接从变量变量数组里查找的）。
如果函数只是需要读这个变量，如果我们将这个变量复制一份给当前函数使用的话，在内存使用和性能方面都会有问题，而现在的方案却避免了这个问题，
如我们的示例：使用类似于赋值的操作，将原变量的引用计数加一，将有变化时才将原变量引用计数减一，并新建变量。
其最终调用是ZEND_RECV_SPEC_HANDLER。

参数的压栈操作用户自定义的函数和内置函数都需要，而RECV操作仅用户自定义函数需要。


[receive-arg]: 			?p=chapt03/03-05-impl-of-type-hint
