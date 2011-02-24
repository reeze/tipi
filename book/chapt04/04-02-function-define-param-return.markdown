# 第二节 函数的定义,传参及返回值

在本章开头部分，介绍了四种函数，而在本小节，我们从第一种函数：用户自定义的函数开始来认识函数。
本小节包括函数的定义，函数的参数传递和函数的返回值三个部分。
我们将对每个部分做详细介绍。
## 函数的定义
***
在PHP中，用户函数的定义从function关键字开始。如下所示简单示例：

    [php]
    function foo($var) {
        echo $var;
    }

这是一个非常简单的函数，它所实现的功能是定义一个函数，函数有一个参数，它将在执行时在标准输出端输出传递给它的参数变量的值。

函数的一切从function开始。那么我们就从function开始我们对于函数定义的探索之旅。


**词法分析**

在 Zend/zend_language_scanner.l中我们找到如下所示的代码：

    [c]
    <ST_IN_SCRIPTING>"function" {
        return T_FUNCTION;
    }

它所表示的含义是function将会生成T_FUNCTION标记。在获取这个标记后，我们开始语法分析。

**语法分析**

在 Zend/zend_language_parser.y文件中找到函数的声明过程标记如下：

    [c]
    function:
        T_FUNCTION { $$.u.opline_num = CG(zend_lineno); }
    ;

    is_reference:
            /* empty */	{ $$.op_type = ZEND_RETURN_VAL; }
        |	'&'			{ $$.op_type = ZEND_RETURN_REF; }
    ;

    unticked_function_declaration_statement:
            function is_reference T_STRING {
    zend_do_begin_function_declaration(&$1, &$3, 0, $2.op_type, NULL TSRMLS_CC); }
                '(' parameter_list ')' '{' inner_statement_list '}' {
                    zend_do_end_function_declaration(&$1 TSRMLS_CC); }
    ;

>关注点在 function is_reference T_STRING，表示function关键字，是否引用，函数名。

**生成中间代码**

语法解析后，我们看到所执行编译函数为zend_do_begin_function_declaration。在 Zend/zend_complie.c文件中找到其实现如下：

    [c]
    void zend_do_begin_function_declaration(znode *function_token, znode *function_name, int is_method, int return_reference, znode *fn_flags_znode TSRMLS_DC) /* {{{ */
    {
        zend_op_array op_array;
        char *name = function_name->u.constant.value.str.val;
        int name_len = function_name->u.constant.value.str.len;
        int function_begin_line = function_token->u.opline_num;
        zend_uint fn_flags;
        char *lcname;
        zend_bool orig_interactive;
        ALLOCA_FLAG(use_heap)

        if (is_method) {
            ...//省略 类方法 在后面的类章节介绍
        } else {
            fn_flags = 0;
        }
        if ((fn_flags & ZEND_ACC_STATIC) && (fn_flags & ZEND_ACC_ABSTRACT) && !(CG(active_class_entry)->ce_flags & ZEND_ACC_INTERFACE)) {
            zend_error(E_STRICT, "Static function %s%s%s() should not be abstract", is_method ? CG(active_class_entry)->name : "", is_method ? "::" : "", Z_STRVAL(function_name->u.constant));
        }

        function_token->u.op_array = CG(active_op_array);
        lcname = zend_str_tolower_dup(name, name_len);

        orig_interactive = CG(interactive);
        CG(interactive) = 0;
        init_op_array(&op_array, ZEND_USER_FUNCTION, INITIAL_OP_ARRAY_SIZE TSRMLS_CC);
        CG(interactive) = orig_interactive;

        op_array.function_name = name;
        op_array.return_reference = return_reference;
        op_array.fn_flags |= fn_flags;
        op_array.pass_rest_by_reference = 0;

        op_array.scope = is_method?CG(active_class_entry):NULL;
        op_array.prototype = NULL;

        op_array.line_start = zend_get_compiled_lineno(TSRMLS_C);

        if (is_method) {
            ...//省略 类方法 在后面的类章节介绍
        } else {
            zend_op *opline = get_next_op(CG(active_op_array) TSRMLS_CC);

            if (CG(current_namespace)) {
                /* Prefix function name with current namespcae name */
                znode tmp;

                tmp.u.constant = *CG(current_namespace);
                zval_copy_ctor(&tmp.u.constant);
                zend_do_build_namespace_name(&tmp, &tmp, function_name TSRMLS_CC);
                op_array.function_name = Z_STRVAL(tmp.u.constant);
                efree(lcname);
                name_len = Z_STRLEN(tmp.u.constant);
                lcname = zend_str_tolower_dup(Z_STRVAL(tmp.u.constant), name_len);
            }

            opline->opcode = ZEND_DECLARE_FUNCTION;
            opline->op1.op_type = IS_CONST;
            build_runtime_defined_function_key(&opline->op1.u.constant, lcname, name_len TSRMLS_CC);
            opline->op2.op_type = IS_CONST;
            opline->op2.u.constant.type = IS_STRING;
            opline->op2.u.constant.value.str.val = lcname;
            opline->op2.u.constant.value.str.len = name_len;
            Z_SET_REFCOUNT(opline->op2.u.constant, 1);
            opline->extended_value = ZEND_DECLARE_FUNCTION;
            zend_hash_update(CG(function_table), opline->op1.u.constant.value.str.val, opline->op1.u.constant.value.str.len, &op_array, sizeof(zend_op_array), (void **) &CG(active_op_array));
        }

        if (CG(compiler_options) & ZEND_COMPILE_EXTENDED_INFO) {
            zend_op *opline = get_next_op(CG(active_op_array) TSRMLS_CC);

            opline->opcode = ZEND_EXT_NOP;
            opline->lineno = function_begin_line;
            SET_UNUSED(opline->op1);
            SET_UNUSED(opline->op2);
        }

        {
            /* Push a seperator to the switch and foreach stacks */
            zend_switch_entry switch_entry;

            switch_entry.cond.op_type = IS_UNUSED;
            switch_entry.default_case = 0;
            switch_entry.control_var = 0;

            zend_stack_push(&CG(switch_cond_stack), (void *) &switch_entry, sizeof(switch_entry));

            {
                /* Foreach stack separator */
                zend_op dummy_opline;

                dummy_opline.result.op_type = IS_UNUSED;
                dummy_opline.op1.op_type = IS_UNUSED;

                zend_stack_push(&CG(foreach_copy_stack), (void *) &dummy_opline, sizeof(zend_op));
            }
        }

        if (CG(doc_comment)) {
            CG(active_op_array)->doc_comment = CG(doc_comment);
            CG(active_op_array)->doc_comment_len = CG(doc_comment_len);
            CG(doc_comment) = NULL;
            CG(doc_comment_len) = 0;
        }

        zend_stack_push(&CG(labels_stack), (void *) &CG(labels), sizeof(HashTable*));
        CG(labels) = NULL;
    }
    /* }}} */

**执行中间代码**