# 函数的定义

在PHP中，用户函数的定义从function关键字开始。如下所示简单示例：

    [php]
    function foo($var) {
        echo $var;
    }

这是一个非常简单的函数，它所实现的功能是定义一个函数，函数有一个参数，函数的内容是在标准输出端输出传递给它的参数变量的值。

函数的一切从function开始。我们从function开始函数定义的探索之旅。


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

>**NOTE**
>关注点在 function is_reference T_STRING，表示function关键字，是否引用，函数名。

T_FUNCTION标记只是用来定位函数的声明，表示这是一个函数，而更多的工作是与这个函数相关的东西，包括参数，返回值等。

**生成中间代码**

语法解析后，我们看到所执行编译函数为zend_do_begin_function_declaration。在 Zend/zend_complie.c文件中找到其实现如下：

    [c]
    void zend_do_begin_function_declaration(znode *function_token, znode *function_name,
     int is_method, int return_reference, znode *fn_flags_znode TSRMLS_DC) /* {{{ */
    {
        ...//省略
        function_token->u.op_array = CG(active_op_array);
        lcname = zend_str_tolower_dup(name, name_len);

        orig_interactive = CG(interactive);
        CG(interactive) = 0;
        init_op_array(&op_array, ZEND_USER_FUNCTION, INITIAL_OP_ARRAY_SIZE TSRMLS_CC);
        CG(interactive) = orig_interactive;

         ...//省略

        if (is_method) {
            ...//省略 类方法 在后面的类章节介绍
        } else {
            zend_op *opline = get_next_op(CG(active_op_array) TSRMLS_CC);


            opline->opcode = ZEND_DECLARE_FUNCTION;
            opline->op1.op_type = IS_CONST;
            build_runtime_defined_function_key(&opline->op1.u.constant, lcname,
                name_len TSRMLS_CC);
            opline->op2.op_type = IS_CONST;
            opline->op2.u.constant.type = IS_STRING;
            opline->op2.u.constant.value.str.val = lcname;
            opline->op2.u.constant.value.str.len = name_len;
            Z_SET_REFCOUNT(opline->op2.u.constant, 1);
            opline->extended_value = ZEND_DECLARE_FUNCTION;
            zend_hash_update(CG(function_table), opline->op1.u.constant.value.str.val,
                opline->op1.u.constant.value.str.len, &op_array, sizeof(zend_op_array),
                 (void **) &CG(active_op_array));
        }

    }
    /* }}} */

生成的中间代码为 **ZEND_DECLARE_FUNCTION** ，根据这个中间代码及操作数对应的op_type。
我们可以找到中间代码的执行函数为 **ZEND_DECLARE_FUNCTION_SPEC_HANDLER**。

>**NOTE**
>在生成中间代码时，可以看到已经统一了函数名全部为小写，表示函数的名称不是区分大小写的。

为验证这个实现，我们看一段代码：

    [php]
    function T() {
        echo 1;
    }

    function t() {
        echo 2;
    }

执行代码，可以看到屏幕上输出如下报错信息：

    [shell]
    Fatal error: Cannot redeclare t() (previously declared in ...)

表示对于PHP来说T和t是同一个函数名。检验函数名是否重复，这个过程是在哪进行的呢？
下面将要介绍的函数声明中间代码的执行过程包含了这个检查过程。

**执行中间代码**

在 Zend/zend_vm_execute.h 文件中找到 ZEND_DECLARE_FUNCTION中间代码对应的执行函数：ZEND_DECLARE_FUNCTION_SPEC_HANDLER。
此函数只调用了函数do_bind_function。其调用代码为：

    [c]
    do_bind_function(EX(opline), EG(function_table), 0);

在这个函数中将EX(opline)所指向的函数添加到EG(function_table)中，并判断是否已经存在相同名字的函数，如果存在则报错。
EG(function_table)用来存放执行过程中全部的函数信息，相当于函数的注册表。
它的结构是一个HashTable，所以在do_bind_function函数中添加新的函数使用的是HashTable的操作函数**zend_hash_add**
