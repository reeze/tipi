# 函数的返回值

在编程语言中，一个函数或一个方法一般都有返回值，但也存在不返回值的情况，此时，这些函数仅仅仅是处理一些事务，
没有返回，或者说没有明确的返回值，在pascal语言中它有一个专有的关键字 **procedure** 。
在PHP中，函数都有返回值，分两种情况，使用return语句明确的返回和没有return语句返回NULL。

## return语句
当使用return语句时，PHP给用户自定义的函数返回指定类型的变量。
依旧我们查看源码的方式，对return 关键字进行词法分析和语法分析后，生成中间代码。
从 Zend/zend_language_parser.y文件中可以确认其生成中间代码调用的是 **zend_do_return** 函数。

    [c]
    void zend_do_return(znode *expr, int do_end_vparse TSRMLS_DC) /* {{{ */
    {
        zend_op *opline;
        int start_op_number, end_op_number;

        if (do_end_vparse) {
            if (CG(active_op_array)->return_reference
                    && !zend_is_function_or_method_call(expr)) {
                zend_do_end_variable_parse(expr, BP_VAR_W, 0 TSRMLS_CC);/* 处理返回引用 */
            } else {
                zend_do_end_variable_parse(expr, BP_VAR_R, 0 TSRMLS_CC);/* 处理常规变量返回 */
            }
        }

       ...// 省略  取其它中间代码操作

        opline->opcode = ZEND_RETURN;

        if (expr) {
            opline->op1 = *expr;

            if (do_end_vparse && zend_is_function_or_method_call(expr)) {
                opline->extended_value = ZEND_RETURNS_FUNCTION;
            }
        } else {
            opline->op1.op_type = IS_CONST;
            INIT_ZVAL(opline->op1.u.constant);
        }

        SET_UNUSED(opline->op2);
    }
    /* }}} */

生成中间代码为 **ZEND_RETURN**。 第一个操作数的类型在返回值为可用的表达式时，
其类型为表达式的操作类型，否则类型为 IS_CONST。这在后续计算执行中间代码函数时有用到。
根据操作数的不同，ZEND_RETURN中间代码会执行 ZEND_RETURN_SPEC_CONST_HANDLER，
ZEND_RETURN_SPEC_TMP_HANDLER或ZEND_RETURN_SPEC_TMP_HANDLER。
这三个函数的执行流程基本类似，包括对一些错误的处理。
这里我们以ZEND_RETURN_SPEC_CONST_HANDLER为例说明函数返回值的执行过程：

    [c]
    static int ZEND_FASTCALL  ZEND_RETURN_SPEC_CONST_HANDLER(ZEND_OPCODE_HANDLER_ARGS)
    {
        zend_op *opline = EX(opline);
        zval *retval_ptr;
        zval **retval_ptr_ptr;


        if (EG(active_op_array)->return_reference == ZEND_RETURN_REF) {

            //  返回引用时不允许常量和临时变量
            if (IS_CONST == IS_CONST || IS_CONST == IS_TMP_VAR) {   
                /* Not supposed to happen, but we'll allow it */
                zend_error(E_NOTICE, "Only variable references \
                    should be returned by reference");
                goto return_by_value;
            }

            retval_ptr_ptr = NULL;  //  返回值

            if (IS_CONST == IS_VAR && !retval_ptr_ptr) {
                zend_error_noreturn(E_ERROR, "Cannot return string offsets by reference");
            }

            if (IS_CONST == IS_VAR && !Z_ISREF_PP(retval_ptr_ptr)) {
                if (opline->extended_value == ZEND_RETURNS_FUNCTION &&
                    EX_T(opline->op1.u.var).var.fcall_returned_reference) {
                } else if (EX_T(opline->op1.u.var).var.ptr_ptr ==
                        &EX_T(opline->op1.u.var).var.ptr) {
                    if (IS_CONST == IS_VAR && !0) {
                          /* undo the effect of get_zval_ptr_ptr() */
                        PZVAL_LOCK(*retval_ptr_ptr);
                    }
                    zend_error(E_NOTICE, "Only variable references \
                     should be returned by reference");
                    goto return_by_value;
                }
            }

            if (EG(return_value_ptr_ptr)) { //  返回引用
                SEPARATE_ZVAL_TO_MAKE_IS_REF(retval_ptr_ptr);   //  is_ref__gc设置为1
                Z_ADDREF_PP(retval_ptr_ptr);    //  refcount__gc计数加1

                (*EG(return_value_ptr_ptr)) = (*retval_ptr_ptr);
            }
        } else {
    return_by_value:

            retval_ptr = &opline->op1.u.constant;

            if (!EG(return_value_ptr_ptr)) {
                if (IS_CONST == IS_TMP_VAR) {

                }
            } else if (!0) { /* Not a temp var */
                if (IS_CONST == IS_CONST ||
                    EG(active_op_array)->return_reference == ZEND_RETURN_REF ||
                    (PZVAL_IS_REF(retval_ptr) && Z_REFCOUNT_P(retval_ptr) > 0)) {
                    zval *ret;

                    ALLOC_ZVAL(ret);
                    INIT_PZVAL_COPY(ret, retval_ptr);   //  复制一份给返回值 
                    zval_copy_ctor(ret);
                    *EG(return_value_ptr_ptr) = ret;
                } else {
                    *EG(return_value_ptr_ptr) = retval_ptr; //  直接赋值
                    Z_ADDREF_P(retval_ptr);
                }
            } else {
                zval *ret;

                ALLOC_ZVAL(ret);
                INIT_PZVAL_COPY(ret, retval_ptr);    //  复制一份给返回值 
                *EG(return_value_ptr_ptr) = ret;    
            }
        }

        return zend_leave_helper_SPEC(ZEND_OPCODE_HANDLER_ARGS_PASSTHRU);   //  返回前执行收尾工作
    }

函数的返回值在程序执行时存储在 *EG(return_value_ptr_ptr)。ZE内核对值返回和引用返回作了区分，
并且在此基础上对常量，临时变量和其它类型的变量在返回时进行了不同的处理。在return执行完之前，
ZE内核通过调用zend_leave_helper_SPEC函数，清除函数内部使用的变量等。
这也是ZE内核自动给函数加上NULL返回的原因之一。

## 没有return语句的函数
在PHP中，没有过程这个概念，只有没有返回值的函数。但是对于没有返回值的函数，PHP内核会“帮你“加上一个NULL来做为返回值。
这个“帮你”的操作也是在生成中间代码时进行的。在每个函数解析时都需要执行函数 **zend_do_end_function_declaration**，
在此函数中有一条语句：

    [c]
    zend_do_return(NULL, 0 TSRMLS_CC);

结合前面的内容，我们知道这条语句的作用就是返回NULL。这就是没有return语句的函数返回NULL的原因所在。


## 内部函数的返回值
内部函数的返回值都是通过一个名为 return_value 的变量传递的。
这个变量同时也是函数中的一个参数，在PHP_FUNCTION函数扩展开来后可以看到。
这个参数总是包含有一个事先申请好空间的 zval 容器，因此你可以直接访问其成员并对其进行修改而无需先对 return_value 执行一下 MAKE_STD_ZVAL 宏指令。
为了能够更方便从函数中返回结果，也为了省却直接访问 zval 容器内部结构的麻烦，ZEND 提供了一大套宏命令来完成相关的这些操作。
这些宏命令会自动设置好类型和数值。

**从函数直接返回值的宏：**

* RETURN_RESOURCE(resource)	返回一个资源。
* RETURN_BOOL(bool)	返回一个布尔值。
* RETURN_NULL()	返回一个空值。
* RETURN_LONG(long)	返回一个长整数。
* RETURN_DOUBLE(double)	返回一个双精度浮点数。
* RETURN_STRING(string, duplicate)	返回一个字符串。duplicate 表示这个字符是否使用 estrdup() 进行复制。
* RETURN_STRINGL(string, length, duplicate)	返回一个定长的字符串。其余跟 RETURN_STRING 相同。这个宏速度更快而且是二进制安全的。
* RETURN_EMPTY_STRING()	返回一个空字符串。
* RETURN_FALSE	返回一个布尔值假。
* RETURN_TRUE	返回一个布尔值真。

**设置函数返回值的宏：**

* RETVAL_RESOURCE(resource)	设定返回值为指定的一个资源。
* RETVAL_BOOL(bool)	设定返回值为指定的一个布尔值。
* RETVAL_NULL	设定返回值为空值
* RETVAL_LONG(long)	设定返回值为指定的一个长整数。
* RETVAL_DOUBLE(double)	设定返回值为指定的一个双精度浮点数。
* RETVAL_STRING(string, duplicate)	设定返回值为指定的一个字符串，duplicate 含义同 RETURN_STRING。
* RETVAL_STRINGL(string, length, duplicate)	设定返回值为指定的一个定长的字符串。其余跟 RETVAL_STRING 相同。这个宏速度更快而且是二进制安全的。
* RETVAL_EMPTY_STRING	设定返回值为空字符串。
* RETVAL_FALSE	设定返回值为布尔值假。
* RETVAL_TRUE	设定返回值为布尔值真。

如果需要返回的是像数组和对象这样的复杂类型的数据，那就需要先调用 array_init() 和 object_init()，
也可以使用相应的 hash 函数直接操作 return_value。
由于这些类型主要是由一些杂七杂八的东西构成，所以对它们就没有了相应的宏。

关于内部函数的return_value值是如何赋值给*EG(return_value_ptr_ptr)，
函数的调用是如何进行的，请阅读下一小节 [<<函数的调用和执行>>][function-call].

[function-call]:   		?p=chapt04/04-03-function-call