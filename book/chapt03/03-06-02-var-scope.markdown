# 变量的作用域

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