# 第二节 函数的定义,传参及返回值


## 函数的定义
***

词法分析

    [c]
    <ST_IN_SCRIPTING>"function" {
        return T_FUNCTION;
    }

语法分析

    [c]
    function:
        T_FUNCTION { $$.u.opline_num = CG(zend_lineno); }
    ;

    is_reference:
            /* empty */	{ $$.op_type = ZEND_RETURN_VAL; }
        |	'&'			{ $$.op_type = ZEND_RETURN_REF; }
    ;

    unticked_function_declaration_statement:
            function is_reference T_STRING { zend_do_begin_function_declaration(&$1, &$3, 0, $2.op_type, NULL TSRMLS_CC); }
                '(' parameter_list ')' '{' inner_statement_list '}' { zend_do_end_function_declaration(&$1 TSRMLS_CC); }
    ;

生成中间代码

    
执行中间代码