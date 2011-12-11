# 第四节 静态变量

通常意义上静态变量是静态分配的，他们的生命周期和程序的生命周期一样，
只有在程序退出时才结束期生命周期，这和局部变量相反，有的语言中全局变量也是静态分配的。
例如PHP和Javascript中的全局变量。

静态变量可以分为：

* 静态全局变量，PHP中的全局变量也可以理解为静态全局变量，因为除非明确unset释放，在程序运行过程中始终存在。
* 静态局部变量，也就是在函数内定义的静态变量，函数在执行时对变量的操作会保持到下一次函数被调用。
* 静态成员变量，这是在类中定义的静态变量，和实例变量相对应，静态成员变量可以在所有实例中共享。

最常见的是静态局部变量及静态成员变量。局部变量只有在函数执行时才会存在。
通常，当一个函数执行完毕，它的局部变量的值就已经不存在，而且变量所占据的内存也被释放。
当下一次执行该过程时，它的所有局部变量将重新初始化。如果某个局部变量定义为静态的，
则它的值不会在函数调用结束后释放，而是继续保留变量的值。

在本小节将介绍静态局部变量，有关静态成员变量的内容将在类与对象章节进行介绍。

先看看如下局部变量的使用：

    [php]
    function t() {
        static $i = 0;
        $i++;
        echo $i, ' ';
    }

    t();
    t();
    t();

上面的程序会输出1 2 3。从这个示例可以看出，$i变量的值在改变后函数继续执行还能访问到，
$i变量就像是只有函数t()才能访问到的一个全局变量。
那PHP是怎么实现的呢？

static是PHP的关键字，我们需要从词法分析，语法分析，中间代码生成到执行中间代码这几个部分探讨整个实现过程。

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

语法分析的过程中如果匹配到相应的模式则会进行相应的处理动作，通常是进行opcode的编译。
在本例中的static关键字匹配中，是由函数zend_do_fetch_static_variable处理的。

## 3. 生成opcode中间代码

zend_do_fetch_static_variable函数的作用就是生成opcode，定义如下：

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

opcode的编译阶段完成后就开始opcode的执行了。
在Zend/zend_vm_opcodes.h文件中包含所有opcode的宏定义，这些宏丙没有特殊含义，只是作为opcode的唯一标示，
包含本例中相关的如下两个宏的定义：

    [c]
    #define ZEND_FETCH_W                          83
    #define ZEND_ASSIGN_REF                       39

前面第二章 [脚本的执行一节][from-op-code-to-handler]介绍了根据opcode查找到相应处理函数的方法。
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
			// 取符号表，这里我们取的是EG(active_op_array)->static_variables
            target_symbol_table = zend_get_target_symbol_table(opline, EX(Ts), type, varname TSRMLS_CC);
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

在上面的代码中有一个关键的函数zend_get_target_symbol_table。它的作用是获取当前正在执行的目标符号表，
而在函数执行时当前的op_array则是函数体本身，先看看zend_op_array的结构。

	[c]
	struct _zend_op_array {
		/* Common elements */
		zend_uchar type;
		char *function_name;
		zend_uint num_args;
		zend_uint required_num_args;
		zend_arg_info *arg_info;
		zend_bool pass_rest_by_reference;
		unsigned char return_reference;
		/* END of common elements */

		zend_bool done_pass_two;

		zend_uint *refcount;

		zend_op *opcodes;
		zend_uint last, size;

		/* static variables support */
		HashTable *static_variables;

		zend_op *start_op;
		int backpatch_count;

		zend_uint this_var;
		// ...
	}

由上可以看到zend_op_array中包含function_name字段，也就是当前函数的名称。
再看看获取当前符号标的函数：

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
从而这zend_get_target_symbol_table函数中我们返回的是EG(active_op_array)->static_variables。
也就是当前函数的的静态变量哈希表。每次执行时都会从该符号表中查找相应的值，由于op_array在程序执行时始终存在。
所有对静态符号表中数值的修改会继续保留，下次函数执行时继续从该符号表获取信息。
也就是说Zend为每个函数(准确的说是zend_op_array)分配了一个私有的符号表来保存该函数的静态变量。


[from-opcode-to-handler]: ?p=chapt02/02-03-03-from-opcode-to-handler
