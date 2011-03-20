# 第五节 类型提示的实现

PHP是弱类型语言，向方法传递参数时候一般也不太区分数据类型。
PHP中提供了一些函数，来判断数据的类型。例如，我们可使用is_numeric()。判断是否是一个数值或者可转换为数值的字符串。
为了避免对象类型不规范引起的问题，PHP5中引入了类型提示这个概念。在定义方法参数时，同时定义参数的对象类型。
如果在调用的时候，传入参数的类型不对会报错。这样保证了数据的安全性。
下面我们从PHP的整个解释过程分析类型提示的源码实现过程。我们将从function开始词法分析，在语法分析中找到类型提示的相关代码，然后跟踪到中间代码的实现。

## 1、词法解析

类型提示是作用于function，我们从function语句起查找类型提示的实现。如下所示，为function在 Zend/zend_language_scanner.l文件中体现。

    [c]
    <ST_IN_SCRIPTING>"function" {
	return T_FUNCTION;
    }

## 2、语法解析

在词法解析完成后，查找Zend/zend_language_parser.y文件，查找T_FUNCTION，并查找对应的参数列表。如下整个查找过程。

    [c]
    expr_without_variable:
        ...//省略
        |	function is_reference '(' { zend_do_begin_lambda_function_declaration(&$$, &$1, $2.op_type TSRMLS_CC); }
			parameter_list ')' lexical_vars '{' inner_statement_list '}' {  zend_do_end_function_declaration(&$1 TSRMLS_CC); $$ = $4; }
    ;

    function:
	T_FUNCTION { $$.u.opline_num = CG(zend_lineno); }
    ;

    parameter_list:
		non_empty_parameter_list
	|	/* empty */
    ;


    non_empty_parameter_list:
            optional_class_type T_VARIABLE				{ znode tmp;  fetch_simple_variable(&tmp, &$2, 0 TSRMLS_CC); $$.op_type = IS_CONST; Z_LVAL($$.u.constant)=1; Z_TYPE($$.u.constant)=IS_LONG; INIT_PZVAL(&$$.u.constant); zend_do_receive_arg(ZEND_RECV, &tmp, &$$, NULL, &$1, &$2, 0 TSRMLS_CC); }
        |	optional_class_type '&' T_VARIABLE			{ znode tmp;  fetch_simple_variable(&tmp, &$3, 0 TSRMLS_CC); $$.op_type = IS_CONST; Z_LVAL($$.u.constant)=1; Z_TYPE($$.u.constant)=IS_LONG; INIT_PZVAL(&$$.u.constant); zend_do_receive_arg(ZEND_RECV, &tmp, &$$, NULL, &$1, &$3, 1 TSRMLS_CC); }
        |	optional_class_type '&' T_VARIABLE '=' static_scalar			{ znode tmp;  fetch_simple_variable(&tmp, &$3, 0 TSRMLS_CC); $$.op_type = IS_CONST; Z_LVAL($$.u.constant)=1; Z_TYPE($$.u.constant)=IS_LONG; INIT_PZVAL(&$$.u.constant); zend_do_receive_arg(ZEND_RECV_INIT, &tmp, &$$, &$5, &$1, &$3, 1 TSRMLS_CC); }
        |	optional_class_type T_VARIABLE '=' static_scalar				{ znode tmp;  fetch_simple_variable(&tmp, &$2, 0 TSRMLS_CC); $$.op_type = IS_CONST; Z_LVAL($$.u.constant)=1; Z_TYPE($$.u.constant)=IS_LONG; INIT_PZVAL(&$$.u.constant); zend_do_receive_arg(ZEND_RECV_INIT, &tmp, &$$, &$4, &$1, &$2, 0 TSRMLS_CC); }
        |	non_empty_parameter_list ',' optional_class_type T_VARIABLE 	{ znode tmp;  fetch_simple_variable(&tmp, &$4, 0 TSRMLS_CC); $$=$1; Z_LVAL($$.u.constant)++; zend_do_receive_arg(ZEND_RECV, &tmp, &$$, NULL, &$3, &$4, 0 TSRMLS_CC); }
        |	non_empty_parameter_list ',' optional_class_type '&' T_VARIABLE	{ znode tmp;  fetch_simple_variable(&tmp, &$5, 0 TSRMLS_CC); $$=$1; Z_LVAL($$.u.constant)++; zend_do_receive_arg(ZEND_RECV, &tmp, &$$, NULL, &$3, &$5, 1 TSRMLS_CC); }
        |	non_empty_parameter_list ',' optional_class_type '&' T_VARIABLE	 '=' static_scalar { znode tmp;  fetch_simple_variable(&tmp, &$5, 0 TSRMLS_CC); $$=$1; Z_LVAL($$.u.constant)++; zend_do_receive_arg(ZEND_RECV_INIT, &tmp, &$$, &$7, &$3, &$5, 1 TSRMLS_CC); }
        |	non_empty_parameter_list ',' optional_class_type T_VARIABLE '=' static_scalar 	{ znode tmp;  fetch_simple_variable(&tmp, &$4, 0 TSRMLS_CC); $$=$1; Z_LVAL($$.u.constant)++; zend_do_receive_arg(ZEND_RECV_INIT, &tmp, &$$, &$6, &$3, &$4, 0 TSRMLS_CC); }
    ;

    optional_class_type:
            /* empty */					{ $$.op_type = IS_UNUSED; }
        |	fully_qualified_class_name	{ $$ = $1; }
        |	T_ARRAY						{ $$.op_type = IS_CONST; Z_TYPE($$.u.constant)=IS_NULL;}
    ;

    fully_qualified_class_name:
            namespace_name { $$ = $1; }
        |	T_NAMESPACE T_NS_SEPARATOR namespace_name { $$.op_type = IS_CONST; ZVAL_EMPTY_STRING(&$$.u.constant);  zend_do_build_namespace_name(&$$, &$$, &$3 TSRMLS_CC); }
        |	T_NS_SEPARATOR namespace_name { char *tmp = estrndup(Z_STRVAL($2.u.constant), Z_STRLEN($2.u.constant)+1); memcpy(&(tmp[1]), Z_STRVAL($2.u.constant), Z_STRLEN($2.u.constant)+1); tmp[0] = '\\'; efree(Z_STRVAL($2.u.constant)); Z_STRVAL($2.u.constant) = tmp; ++Z_STRLEN($2.u.constant); $$ = $2; }
    ;

    namespace_name:
            T_STRING { $$ = $1; }
        |	namespace_name T_NS_SEPARATOR T_STRING { zend_do_build_namespace_name(&$$, &$1, &$3 TSRMLS_CC); }


## 3、查找中间代码及其对应的实现

从上面的代码中我们可以看到关于类型提示的检测最后调用的是：

    [c]
    zend_do_receive_arg(ZEND_RECV, &tmp, &$$, NULL, &$1, &$2, 0 TSRMLS_CC);

    void zend_do_receive_arg(zend_uchar op, const znode *var, const znode *offset, const znode *initialization, znode *class_type, const znode *varname, zend_uchar pass_by_reference TSRMLS_DC) /* {{{ */
    {
            ...//省略
            opline = get_next_op(CG(active_op_array) TSRMLS_CC);
            CG(active_op_array)->num_args++;
            opline->opcode = op;
            opline->result = *var;
            opline->op1 = *offset;
            if (op == ZEND_RECV_INIT) {
                    opline->op2 = *initialization;
            } else {
                    CG(active_op_array)->required_num_args = CG(active_op_array)->num_args;
                    SET_UNUSED(opline->op2);
            }
            ...//省略
    }
    /* }}} */

从以上代码可以看出：其opcode为ZEND_RECV。

在Zend/zend_vm_opcodes.h文件中查找ZEND_RECV，得到如下结果：

    [c]
    #define ZEND_RECV                             63

根据opcode的映射计算规则得出其在执行时调用的是ZEND_RECV_SPEC_HANDLER。其代码如下：

    [c]
    static int ZEND_FASTCALL  ZEND_RECV_SPEC_HANDLER(ZEND_OPCODE_HANDLER_ARGS)
    {
           ...//省略

            if (param == NULL) {
                    char *space;
                    char *class_name = get_active_class_name(&space TSRMLS_CC);
                    zend_execute_data *ptr = EX(prev_execute_data);

                    if (zend_verify_arg_type((zend_function *) EG(active_op_array), arg_num, NULL, opline->extended_value TSRMLS_CC)) {
                           ...//省略
                    }
                   ...//省略
            } else {
                  ...//省略

                    zend_verify_arg_type((zend_function *) EG(active_op_array), arg_num, *param, opline->extended_value TSRMLS_CC);
                  ...//省略
            }

          ...//省略
    }

如上所示：在ZEND_RECV_SPEC_HANDLER中最后调用的是zend_verify_arg_type。其代码如下：

    [c]
    static inline int zend_verify_arg_type(zend_function *zf, zend_uint arg_num, zval *arg, ulong fetch_type TSRMLS_DC)
    {
       ...//省略

        if (cur_arg_info->class_name) {
            const char *class_name;

            if (!arg) {
                need_msg = zend_verify_arg_class_kind(cur_arg_info, fetch_type, &class_name, &ce TSRMLS_CC);
                return zend_verify_arg_error(zf, arg_num, cur_arg_info, need_msg, class_name, "none", "" TSRMLS_CC);
            }
            if (Z_TYPE_P(arg) == IS_OBJECT) { // 既然是类对象参数, 传递的参数需要是对象类型
				// 下面检查这个对象是否是参数提示类的实例对象, 这里是允许传递子类实力对象
                need_msg = zend_verify_arg_class_kind(cur_arg_info, fetch_type, &class_name, &ce TSRMLS_CC);
                if (!ce || !instanceof_function(Z_OBJCE_P(arg), ce TSRMLS_CC)) {
                    return zend_verify_arg_error(zf, arg_num, cur_arg_info, need_msg, class_name, "instance of ", Z_OBJCE_P(arg)->name TSRMLS_CC);
                }
            } else if (Z_TYPE_P(arg) != IS_NULL || !cur_arg_info->allow_null) { // 参数为NULL, 也是可以通过检查的,
			                                                                    // 如果函数定义了参数默认值, 不传递参数调用也是可以通过检查的
                need_msg = zend_verify_arg_class_kind(cur_arg_info, fetch_type, &class_name, &ce TSRMLS_CC);
                return zend_verify_arg_error(zf, arg_num, cur_arg_info, need_msg, class_name, zend_zval_type_name(arg), "" TSRMLS_CC);
            }
        } else if (cur_arg_info->array_type_hint) {
            if (!arg) {
                return zend_verify_arg_error(zf, arg_num, cur_arg_info, "be an array", "", "none", "" TSRMLS_CC);
            }
            if (Z_TYPE_P(arg) != IS_ARRAY && (Z_TYPE_P(arg) != IS_NULL || !cur_arg_info->allow_null)) {
                return zend_verify_arg_error(zf, arg_num, cur_arg_info, "be an array", "", zend_zval_type_name(arg), "" TSRMLS_CC);
            }
        }
        return 1;
    }

如果类型提示报错，zend_verify_arg_type函数最后都会调用 zend_verify_arg_class_kind  生成报错信息，并且调用 zend_verify_arg_error 报错。如下所示代码：

    [c]
    static inline char * zend_verify_arg_class_kind(const zend_arg_info *cur_arg_info, ulong fetch_type, const char **class_name, zend_class_entry **pce TSRMLS_DC)
    {
        *pce = zend_fetch_class(cur_arg_info->class_name, cur_arg_info->class_name_len, (fetch_type | ZEND_FETCH_CLASS_AUTO | ZEND_FETCH_CLASS_NO_AUTOLOAD) TSRMLS_CC);

        *class_name = (*pce) ? (*pce)->name: cur_arg_info->class_name;
        if (*pce && (*pce)->ce_flags & ZEND_ACC_INTERFACE) {
            return "implement interface ";
        } else {
            return "be an instance of ";
        }
    }


    static inline int zend_verify_arg_error(const zend_function *zf, zend_uint arg_num, const zend_arg_info *cur_arg_info, const char *need_msg, const char *need_kind, const char *given_msg, char *given_kind TSRMLS_DC)
    {
        zend_execute_data *ptr = EG(current_execute_data)->prev_execute_data;
        char *fname = zf->common.function_name;
        char *fsep;
        char *fclass;

        if (zf->common.scope) {
            fsep =  "::";
            fclass = zf->common.scope->name;
        } else {
            fsep =  "";
            fclass = "";
        }

        if (ptr && ptr->op_array) {
            zend_error(E_RECOVERABLE_ERROR, "Argument %d passed to %s%s%s() must %s%s, %s%s given, called in %s on line %d and defined", arg_num, fclass, fsep, fname, need_msg, need_kind, given_msg, given_kind, ptr->op_array->filename, ptr->opline->lineno);
        } else {
            zend_error(E_RECOVERABLE_ERROR, "Argument %d passed to %s%s%s() must %s%s, %s%s given", arg_num, fclass, fsep, fname, need_msg, need_kind, given_msg, given_kind);
        }
        return 0;
    }
