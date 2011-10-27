# 第六节 变量的生命周期

通过前面章节的描述，我们已经知道了PHP中变量的存储方式－－所有的变量都保存在zval结构中。
下面介绍一下PHP内核如何实现变量的定义方式以及作用域。

##变量的生命周期

在ZE进行词法和语法的分析之后，生成具体的opcode，这些opcode最终被execute函数(Zend/zend_vm_execute.h:46)解释执行。
在excute函数中，有以下代码：

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
这里的EX(opline)->handler(...)将op_array中的操作顺序执行，
其中变量赋值操作在ZEND_ASSIGN_SPEC_CV_CONST_HANDLER()函数中进行。
ZEND_ASSIGN_SPEC_CV_CONST_HANDLER中进行一些变量类型的判断并在内存中分配一个zval，然后将变量的值存储其中。
变量名和指向这个zval的指针，则会存储于符号表内。
ZEND_ASSIGN_SPEC_CV_CONST_HANDLER的最后会调用ZEND_VM_NEXT_OPCODE()将op_array的指针移到下一条opline，
这样就会形成循环执行的效果。

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
ZE将op_array执行完毕以后，HashTable会被FREE_HASHTABLE()释放掉。
 如果程序使用了unset语句来主动消毁变量，则会调用ZEND_UNSET_VAR_SPEC_CV_HANDLER来将变量销毁，
回收内存，这部分内存可以参考《第六章 内存管理》的内容。



