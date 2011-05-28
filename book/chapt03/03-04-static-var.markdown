# 第四节 静态变量
通常意义上静态变量是静态分配的，他们的生命周期和程序的生命周期一样，只有在程序退出时才结束期生命周期，
这和局部变量相反，有的语言中全局变量也是静态分配的。例如PHP和Javascript中的全局变量。

静态变量可以分为：

* 静态全局变量，PHP中的全局变量也可以理解为静态全局变量
* 静态局部变量，也就是在函数内定义的静态变量，函数在执行时对变量的操作会保持到下一次函数被调用。
* 静态成员变量，这主要是在类中定义的静态变量，和实例变量相对应，静态成员变量可以在所有实例中共享。

我们常见的应该是静态局部变量。局部变量只有在函数执行时才会存在。
通常，当一个函数执行完毕，它的局部变量的值就已经不存在，而且变量所占据的内存也被释放。
当下一次执行该过程时，它的所有局部变量将重新初始化。当把局部变量定义成静态的，从而保留变量的值。
在函数内部用 static 关键字声明一个或多个变量，其用法如下：

    [php]
    function t() {
        static $i = 0;
        $i++;
        echo $i, ' ';
    }

    t();
    t();
    t();

上面的程序会输出1 2 3。从这个示例可以看出，$i变量是独立于其它局部变量的。那么这个独立性是如何实现的，下面我们一起来探索静态变量的实现过程。

static是PHP语言的一个语句，我们需要从词法分析，语法分析，中间代码生成到执行中间代码这几个部分探讨整个实现过程。

## 1. 词法分析
首先查看 Zend/zend_language_scanner.l文件，搜索 static关键字。我们可以找到如下代码：

    [c]
    <ST_IN_SCRIPTING>"static" {
        return T_STATIC;
    }

## 2. 语法分析

在词法分析找到token后，通过这个token，在Zend/zend_language_parser.y文件中查找。找到相关代码如下：

    [c]
    |	T_STATIC static_var_list ';'

    static_var_list:
            static_var_list ',' T_VARIABLE { zend_do_fetch_static_variable(&$3, NULL, ZEND_FETCH_STATIC TSRMLS_CC); }
        |	static_var_list ',' T_VARIABLE '=' static_scalar { zend_do_fetch_static_variable(&$3, &$5, ZEND_FETCH_STATIC TSRMLS_CC); }
        |	T_VARIABLE  { zend_do_fetch_static_variable(&$1, NULL, ZEND_FETCH_STATIC TSRMLS_CC); }
        |	T_VARIABLE '=' static_scalar { zend_do_fetch_static_variable(&$1, &$3, ZEND_FETCH_STATIC TSRMLS_CC); }

    ;

从上面代码可知，PHP在解释static变量赋值生成中间是调用zend_do_fetch_static_variable函数。

## 3. 生成中间代码
调用zend_do_fetch_static_variable函数其实是生成中间代码的过程。其代码如下：

    [c]
    void zend_do_fetch_static_variable(znode *varname, const znode
            *static_assignment, int fetch_type TSRMLS_DC)
    {
        zval *tmp;
        zend_op *opline;
        znode lval;
        znode result;

        ALLOC_ZVAL(tmp);


        if (static_assignment) {
            *tmp = static_assignment->u.constant;
        } else {
            INIT_ZVAL(*tmp);
        }
        if (!CG(active_op_array)->static_variables) {   /* 初始化此时的静态变量存放位置 */
            ALLOC_HASHTABLE(CG(active_op_array)->static_variables);
            zend_hash_init(CG(active_op_array)->static_variables, 2, NULL, ZVAL_PTR_DTOR, 0);
        }
        //  将新的静态变量放进来
        zend_hash_update(CG(active_op_array)->static_variables, varname->u.constant.value.str.val,
            varname->u.constant.value.str.len+1, &tmp, sizeof(zval *), NULL);

        ...//省略
        opline = get_next_op(CG(active_op_array) TSRMLS_CC);
        opline->opcode = (fetch_type == ZEND_FETCH_LEXICAL) ? ZEND_FETCH_R : ZEND_FETCH_W;		/* 由于fetch_type=ZEND_FETCH_STATIC，程序会选择ZEND_FETCH_W*/
        opline->result.op_type = IS_VAR;
        opline->result.u.EA.type = 0;
        opline->result.u.var = get_temporary_variable(CG(active_op_array));
        opline->op1 = *varname;
        SET_UNUSED(opline->op2);
        opline->op2.u.EA.type = ZEND_FETCH_STATIC;  /* 这在中间代码执行时会有大用 */
        result = opline->result;

        if (varname->op_type == IS_CONST) {
            zval_copy_ctor(&varname->u.constant);
        }
        fetch_simple_variable(&lval, varname, 0 TSRMLS_CC); /* Relies on the fact that the default fetch is BP_VAR_W */

        if (fetch_type == ZEND_FETCH_LEXICAL) {
            ...//省略
        } else {
            zend_do_assign_ref(NULL, &lval, &result TSRMLS_CC); //  赋值操作中间代码生成
        }
        CG(active_op_array)->opcodes[CG(active_op_array)->last-1].result.u.EA.type |= EXT_TYPE_UNUSED;

    }

从上面的代码我们可知，在解释成中间代码时，静态变量是存放在CG(active_op_array)->static_variables中的。
并且生成的中间代码为：**ZEND_FETCH_W** 和 **ZEND_ASSIGN_REF** 。
其中ZEND_FETCH_W中间代码是在zend_do_fetch_static_variable中直接赋值，而ZEND_ASSIGN_REF中间代码是在zend_do_fetch_static_variable中调用zend_do_assign_ref生成的。

## 4. 执行中间代码
在生成完中间代码后，ZE会调用执行中间代码。而中间代码会先查看中间代码的编号。在Zend/zend_vm_opcodes.h文件中，这两个的定义如下：

    [c]
    #define ZEND_FETCH_W                          83
    #define ZEND_ASSIGN_REF                       39

通过中间代码调用映射方法计算得此时ZEND_FETCH_W 对应的操作为ZEND_FETCH_W_SPEC_CV_HANDLER。其代码如下：

    [c]
    static int ZEND_FASTCALL  ZEND_FETCH_W_SPEC_CV_HANDLER(ZEND_OPCODE_HANDLER_ARGS)
    {
        return zend_fetch_var_address_helper_SPEC_CV(BP_VAR_W, ZEND_OPCODE_HANDLER_ARGS_PASSTHRU);
    }

    static int ZEND_FASTCALL zend_fetch_var_address_helper_SPEC_CV(int type, ZEND_OPCODE_HANDLER_ARGS)
    {
        ...//省略

        if (opline->op2.u.EA.type == ZEND_FETCH_STATIC_MEMBER) {
            retval = zend_std_get_static_property(EX_T(opline->op2.u.var).class_entry, Z_STRVAL_P(varname), Z_STRLEN_P(varname), 0 TSRMLS_CC);
        } else {
            target_symbol_table = zend_get_target_symbol_table(opline, EX(Ts), type, varname TSRMLS_CC);    // 取符号表，这里我们取的是EG(active_op_array)->static_variables
            ...//   省略
            if (zend_hash_find(target_symbol_table, varname->value.str.val, varname->value.str.len+1, (void **) &retval) == FAILURE) {
                switch (type) {
                    ...//省略
                    //  在前面的调用中我们知道type = case BP_VAR_W，于是程序会走按case BP_VAR_W的流程走。
                    case BP_VAR_W: {
                            zval *new_zval = &EG(uninitialized_zval);

                            Z_ADDREF_P(new_zval);
                            zend_hash_update(target_symbol_table, varname->value.str.val, varname->value.str.len+1, &new_zval, sizeof(zval *), (void **) &retval);
                            // 更新符号表，执行赋值操作
                        }
                        break;
                    EMPTY_SWITCH_DEFAULT_CASE()
                }
            }
            switch (opline->op2.u.EA.type) {
                ...//省略
                case ZEND_FETCH_STATIC:
                    zval_update_constant(retval, (void*) 1 TSRMLS_CC);
                    break;
                case ZEND_FETCH_GLOBAL_LOCK:
                    if (IS_CV == IS_VAR && !free_op1.var) {
                        PZVAL_LOCK(*EX_T(opline->op1.u.var).var.ptr_ptr);
                    }
                    break;
            }
        }

        ...//省略
    }

在上面的代码中有一个关键的函数zend_get_target_symbol_table。它的作用是取目标符号表，如下为本次调用的部分代码实现。

    [c]
    static inline HashTable *zend_get_target_symbol_table(const zend_op *opline, const temp_variable *Ts, int type, const zval *variable TSRMLS_DC)
    {
        switch (opline->op2.u.EA.type) {
            ...//   省略
            case ZEND_FETCH_STATIC:
                if (!EG(active_op_array)->static_variables) {
                    ALLOC_HASHTABLE(EG(active_op_array)->static_variables);
                    zend_hash_init(EG(active_op_array)->static_variables, 2, NULL, ZVAL_PTR_DTOR, 0);
                }
                return EG(active_op_array)->static_variables;
                break;
            EMPTY_SWITCH_DEFAULT_CASE()
        }
        return NULL;
    }

在前面的zend_do_fetch_static_variable执行时，op2.u.EA.type的值为ZEND_FETCH_STATIC，
从而这zend_get_target_symbol_table函数中我们取EG(active_op_array)->static_variables的值返回。

从上面的实现可以看出静态变量在生成中间代码以及在执行时都是以一个HashTable类型的变量static_variables独立存在，这就是静态变量与其它变量不同所在。
