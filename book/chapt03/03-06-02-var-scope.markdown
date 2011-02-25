# 变量的生命周期与作用域

通过前面章节的描述，我们已经知道了PHP中变量的存储方式－－所有的变量都保存在zval结构中。下面介绍一下PHP内核如何实现变量的定义方式以及作用域。

##变量的生命周期

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

在执行的过程中，变量名及指针主要存储于_zend_executor_globals的符号表中，_zend_executor_globals的结构这样的：
 	
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

在执行的过程中，active_symbol_table会根据执行的具体语句不断发生变化(详请见本节下半部分)，针对线程安全的EG宏就是用来取此变量中的值。
ZE将op_array执行完毕以后，HashTable会被FREE_HASHTABLE()释放掉。 如果程序使用了unset语句来主动消毁变量，则会调用ZEND_UNSET_VAR_SPEC_CV_HANDLER来将变量销毁，回收内存，这部分内存可以参考《第六章 内存管理》的内容。

##变量的作用域

_zend_executor_globals结构中的symbol_table就是全局符号表，其中保存了在顶层作用域中的变量。同样，函数或者对象的方法在被调用时会创建active_symbol_table来保存局部变量。当程序在顶层中使用某个变量时，ZE就会在symbol_table中进行遍历，同理，每个函数也会有对应的active_symbol_table来供程序使用。

ZE使用_zend_execute_data来存储某个单独的op_array（每个函数都会生成单独的op_array)执行过程中所需要的信息，它的结构如下：

	[c]
	struct _zend_execute_data {
		struct _zend_op *opline;
		zend_function_state function_state;
		zend_function *fbc; /* Function Being Called */
		zend_class_entry *called_scope;
		zend_op_array *op_array;
		zval *object;
		union _temp_variable *Ts;
		zval ***CVs;
		HashTable *symbol_table;
		struct _zend_execute_data *prev_execute_data;
		zval *old_error_reporting;
		zend_bool nested;
		zval **original_return_value;
		zend_class_entry *current_scope;
		zend_class_entry *current_called_scope;
		zval *current_this;
		zval *current_object;
		struct _zend_op *call_opline;
	};

函数中的局部变量就存储在_zend_execute_data的symbol_table中，在执行当前函数的op_array时，全局zend_executor_globals中的*active_symbol_table会指向当前_zend_execute_data中的*symbol_table。而此时，其他函数中的symbol_table不会出现在当前的active_symbol_table中，如此便实现了局部变量。
相关操作在 Zend/zend_vm_execute.h 文件中定义的execute函数中一目了然，如下所示代码：

    [c]
    zend_vm_enter:
	/* Initialize execute_data */
	execute_data = (zend_execute_data *)zend_vm_stack_alloc(
		sizeof(zend_execute_data) +
		sizeof(zval**) * op_array->last_var * (EG(active_symbol_table) ? 1 : 2) +
		sizeof(temp_variable) * op_array->T TSRMLS_CC);

    EX(symbol_table) = EG(active_symbol_table);
	EX(prev_execute_data) = EG(current_execute_data);
	EG(current_execute_data) = execute_data;

EX宏的作用是取结构体zend_execute_data的字段值，如下所示代码：

    [c]
    #define EX(element) execute_data->element

所以，变量的作用域是使用不同的符号表来实现的，于是顶层的全局变量在函数内部使用时，需要先使用上一节中提到的global语句进行变量的跨域操作。

