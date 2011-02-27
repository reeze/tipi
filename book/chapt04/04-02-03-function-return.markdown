# 函数的返回值

## return语句
依旧我们查看源码的方式，对return 关键字进行词法分析和语法分析后，生成中间代码。
从 Zend/zend_language_parser.y文件中可以确认其生成中间代码调用的是 **zend_do_return** 函数。

    [c]
    void zend_do_return(znode *expr, int do_end_vparse TSRMLS_DC) /* {{{ */
    {
        zend_op *opline;
        int start_op_number, end_op_number;

        if (do_end_vparse) {
            if (CG(active_op_array)->return_reference && !zend_is_function_or_method_call(expr)) {
                zend_do_end_variable_parse(expr, BP_VAR_W, 0 TSRMLS_CC);    /* 处理返回引用 */
            } else {
                zend_do_end_variable_parse(expr, BP_VAR_R, 0 TSRMLS_CC);    /* 处理常规变量返回 */
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

生成中间代码为 **ZEND_RETURN** . 第一个操作数的类型在返回值为可用的表达式时，其类型为表达式的操作类型，否则类型为 IS_CONST。这在后续计算执行中间代码函数时有用到。
根据操作数的不同，ZEND_RETURN中间代码会执行 ZEND_RETURN_SPEC_CONST_HANDLER,ZEND_RETURN_SPEC_TMP_HANDLER

## 没有return语句的函数
在PHP中，没有过程这个概念，只有没有返回值的函数。但是对于没有返回值的函数，PHP内核会“帮你“加上一个NULL来做为返回值。
这个“帮你”的操作也是在生成中间代码时进行的。在每个函数解析时都需要执行函数 **zend_do_end_function_declaration**，
在此函数中可以有一条语句如下：

    [c]
    zend_do_return(NULL, 0 TSRMLS_CC);

结合第小节的内容，我们知道这条语句的作用就是返回NULL。这就是没有return语句的函数返回NULL的原因所在。


## 内部函数的返回值