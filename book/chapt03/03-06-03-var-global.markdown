# global语句

global语句的作用是定义全局变量，例如如果想在函数内访问全局作用域内的变量则可以通过global声明来定义。
下面从语法解释开始分析。

**1. 词法解析**

查看 Zend/zend_language_scanner.l文件，搜索 global关键字。我们可以找到如下代码：

    [c]
    <ST_IN_SCRIPTING>"global" {
	return T_GLOBAL;
    }

**2. 语法解析**

在词法解析完后，获得了token，此时通过这个token，我们去Zend/zend_language_parser.y文件中查找。找到相关代码如下：

    [c]
    |	T_GLOBAL global_var_list ';'

    global_var_list:
		global_var_list ',' global_var	{ zend_do_fetch_global_variable(&$3, NULL, ZEND_FETCH_GLOBAL_LOCK TSRMLS_CC); }
	|	global_var						{ zend_do_fetch_global_variable(&$1, NULL, ZEND_FETCH_GLOBAL_LOCK TSRMLS_CC); }
    ;

上面代码中的**$3**是指global_var（如果不清楚yacc的语法，可以查阅yacc入门类的文章。）

从上面的代码可以知道，对于全局变量的声明调用的是zend_do_fetch_global_variable函数，查找此函数的实现在Zend/zend_compile.c文件。

    [c]
    void zend_do_fetch_global_variable(znode *varname, const znode *static_assignment, int fetch_type TSRMLS_DC)
    {
            ...//省略
            opline->opcode = ZEND_FETCH_W;		/* the default mode must be Write, since fetch_simple_variable() is used to define function arguments */
            opline->result.op_type = IS_VAR;
            opline->result.u.EA.type = 0;
            opline->result.u.var = get_temporary_variable(CG(active_op_array));
            opline->op1 = *varname;
            SET_UNUSED(opline->op2);
            opline->op2.u.EA.type = fetch_type;
            result = opline->result;

            ... // 省略
            fetch_simple_variable(&lval, varname, 0 TSRMLS_CC); /* Relies on the fact that the default fetch is BP_VAR_W */

            zend_do_assign_ref(NULL, &lval, &result TSRMLS_CC);
            CG(active_op_array)->opcodes[CG(active_op_array)->last-1].result.u.EA.type |= EXT_TYPE_UNUSED;
    }
    /* }}} */

上面的代码确认了opcode为ZEND_FETCH_W外，还执行了zend_do_assign_ref函数。zend_do_assign_ref函数的实现如下：

    [c]
    void zend_do_assign_ref(znode *result, const znode *lvar, const znode *rvar TSRMLS_DC) /* {{{ */
    {
            zend_op *opline;

           ... //省略

            opline = get_next_op(CG(active_op_array) TSRMLS_CC);
            opline->opcode = ZEND_ASSIGN_REF;
           ...//省略
            if (result) {
                    opline->result.op_type = IS_VAR;
                    opline->result.u.EA.type = 0;
                    opline->result.u.var = get_temporary_variable(CG(active_op_array));
                    *result = opline->result;
            } else {
                    /* SET_UNUSED(opline->result); */
                    opline->result.u.EA.type |= EXT_TYPE_UNUSED;
            }
            opline->op1 = *lvar;
            opline->op2 = *rvar;
    }

从上面的zend_do_fetch_global_variable函数和zend_do_assign_ref函数的实现可以看出，
使用global声明一个全局变量后，其执行了两步操作，ZEND_FETCH_W和ZEND_ASSIGN_REF。

**3. 生成并执行中间代码**

我们看下ZEND_FETCH_W的最后执行。从代码中我们可以知道：

* ZEND_FETCH_W = 83
* op->op1.op_type = 4
* op->op2.op_type = 0

而计算最后调用的方法在代码中的体现为：

    [c]
    zend_opcode_handlers[opcode * 25 + zend_vm_decode[op->op1.op_type] * 5 + zend_vm_decode[op->op2.op_type]];

计算，最后调用ZEND_FETCH_W_SPEC_CV_HANDLER函数。即

    [c]
    static int ZEND_FASTCALL  ZEND_FETCH_W_SPEC_CV_HANDLER(ZEND_OPCODE_HANDLER_ARGS)
    {
            return zend_fetch_var_address_helper_SPEC_CV(BP_VAR_W, ZEND_OPCODE_HANDLER_ARGS_PASSTHRU);
    }

在zend_fetch_var_address_helper_SPEC_CV中调用如下代码获取符号表

    [c]
    target_symbol_table = zend_get_target_symbol_table(opline, EX(Ts), type, varname TSRMLS_CC);

在zend_get_target_symbol_table函数的实现如下：

    [c]
    static inline HashTable *zend_get_target_symbol_table(const zend_op *opline, const temp_variable *Ts, int type, const zval *variable TSRMLS_DC)
    {
            switch (opline->op2.u.EA.type) {
                    ... //  省略
                    case ZEND_FETCH_GLOBAL:
                    case ZEND_FETCH_GLOBAL_LOCK:
                            return &EG(symbol_table);
                            break;
                   ...  //  省略
            }
            return NULL;
    }

在前面语法分析过程中，程序传递的参数是 ZEND_FETCH_GLOBAL_LOCK，于是如上所示。我们取&EG(symbol_table);的值。这也是全局变量的存放位置。

如上就是整个global的解析过程。








