# 第六节 变量的作用域

通过上节的描述，我们已经知道了PHP中变量的存储方式－－所有的变量都保存在zval结构中。下面介绍一下PHP内核如何实现变量的作用域以及变量的定义方式。

## 变量的作用域及定义方式
在ZE进行词法和语法的分析之后,生成具体的opcode,这些opcode最终被execute函数(Zend/zend_vm_execute.h:46)解释执行。在excute函数中，有以下代码：

    [c]
    while (1) { 

		... 
		if ((ret = EX(opline)->handler(execute_data TSRMLS_CC)) > 0) {
			switch (ret) {
				case 1:
					EG(in_execution) = original_in_execution;
					return;
				case 2:
					op_array = EG(active_op_array);
					goto zend_vm_enter;
				case 3:
					execute_data = EG(current_execute_data);
				default:
					break;
			}     
		}     
		...
	}
这里的EX(opline)->handler(...)将op_array中的操作顺序执行，其中变量赋值操作在ZEND_ASSIGN_SPEC_CV_CONST_HANDLER()函数中进行。ZEND_ASSIGN_SPEC_CV_CONST_HANDLER中进行一些变量类型的判断并在内存中分配一个zval,然后将变量的值存储其中。变量名和指向这个zval的指针，则会存储于符号表内。ZEND_ASSIGN_SPEC_CV_CONST_HANDLER的最后会调用ZEND_VM_NEXT_OPCODE()将op_array的指针移到下一条opline，这样就会形成循环执行的效果。

在ZE执行的过程中，有四个全局的变量，这些变量都是用于ZE运行时所需信息的存储：

	[c]
	//_zend_compiler_globals 编译时信息，包括函数表等
	zend_compiler_globals    *compiler_globals;  
	//_zend_executor_globals 执行时信息
	zend_executor_globals    *executor_globals; 
	//_php_core_globals 主要存储php.ini内的信息
	php_core_globals         *core_globals; 
	//_sapi_globals_struct SAPI的信息
	sapi_globals_struct      *sapi_globals; 

其中，变量名及指针主要存储于_zend_executor_globals，它的相关代码：
 	
	[c]
	struct _zend_executor_globals {
	...
    /* symbol table cache */
    HashTable *symtable_cache[SYMTABLE_CACHE_SIZE];
    HashTable **symtable_cache_limit;
    HashTable **symtable_cache_ptr;

    zend_op **opline_ptr;

    HashTable *active_symbol_table;  /* active symbol table */
    HashTable symbol_table;     /* main symbol table */

    HashTable included_files;   /* files already included */
	...
	}

symbol_table就是全局符号表，其中保存了在顶层作用域中的变量。同样，函数或者对象的方法在被调用时会创建active_symbol_table来保存局部变量。当程序在顶层中使用某个变量时，ZE就会在symbol_table中进行遍历，同理，每个函数也会有对应的active_symbol_table来供程序使用。程序执行完毕， HashTable会被FREE_HASHTABLE()释放掉。 如果程序使用了unset语句来主动消毁变量，则会调用ZEND_UNSET_VAR_SPEC_CV_HANDLER来将变量销毁，回收内存，这部分内存可以参考《第六章 内存管理》的内容。

由于变量的作用域是使用不同的符号表来实现，所以说顶层的全局变量在函数内部使用时，需要先使用global语句进行变量的跨域操作。

## global语句
global语句的作用是定义全局变量, 例如如果想在函数内访问全局作用域内的变量则可以通过global声明来定义。
下面从语法解释开始分析.
***
**1. 词法解析**

查看 Zend/zend_language_scanner.l文件，搜索 global关键字。我们可以找到如下代码：

    [c]
    <ST_IN_SCRIPTING>"global" {
	return T_GLOBAL;
    }

**2. 语法解析**

在词法解析完后，获得了token,此时通过这个token，我们去Zend/zend_language_parser.y文件中查找。找到相关代码如下：

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

从上面的zend_do_fetch_global_variable函数和zend_do_assign_ref函数的实现可以看出，使用global声明一个全局变量后，其执行了两步操作，ZEND_FETCH_W和ZEND_ASSIGN_REF。

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








