# 变量的作用域

变量的作用域是变量的一个作用范围，在这个范围内变量为可见的，即可以访问该变量的代码区域，
相反，如果不在这个范围内，变量是不可见的，无法被调用。
（全局变量可以将作用范围看作为整个程序）
如下面的例子：（会输出什么样的结果呢？）

	[php]
	<?php
		$foo = 'tipi';
		function variable_scope(){
			$foo = 'foo';
			print $foo ;
			print $bar ;
		}
		

由此可见，变量的作用域是一个很基础的概念，在变量的实现中比较重要。

## 全局变量与局部变量

变量按作用域类型分为：全局变量和局部变量。**全局变量**是在整个程序中任何地方随意调用的变量，
在PHP中，全局变量的“全局化”使用gloal语句来实现。
相对于全局变量，**局部变量**的作用域是程序中的部分代码（如函数中），而不是程序的全部。

变量的作用域与变量的生命周期有一定的联系，
如在一个函数中定义的变量，
这个变量的作用域从变量声明的时候开始到这个函数结束的时候。
这种变量我们称之为局部变量。它的生命周期开始于函数开始，结束于函数的调用完成之时。


>**QUESTION**
>变量的作用域决定其生命周期吗？程序运行到变量作用域范围之外，就会将变量进行销毁吗？        
>如果你知道答案，可以回复在下面。

对于不同作用域的变量，如果存在冲突情况，就像上面的例子中，全局变量中有一个名为$bar的变量，
在局部变量中也存在一个名为$bar的变量，
此时如何区分呢？

对于全局变量，ZEND内核有一个_zend_executor_globals结构，该结构中的symbol_table就是全局符号表，
其中保存了在顶层作用域中的变量。同样，函数或者对象的方法在被调用时会创建active_symbol_table来保存局部变量。
当程序在顶层中使用某个变量时，ZE就会在symbol_table中进行遍历，
同理，如果程序运行于某个函数中，Zend内核会遍历查询与其对应的active_symbol_table，
而每个函数的active_symbol_table是相对独立的，由此而实现的作用域的独立。

展来来看，如果我们调用的一个函数中的变量，ZE使用_zend_execute_data来存储
某个单独的op_array（每个函数都会生成单独的op_array)执行过程中所需要的信息，它的结构如下：

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

函数中的局部变量就存储在_zend_execute_data的symbol_table中，在执行当前函数的op_array时，
全局zend_executor_globals中的*active_symbol_table会指向当前_zend_execute_data中的*symbol_table。
而此时，其他函数中的symbol_table不会出现在当前的active_symbol_table中，
其他函数中的变量也就不会被找到，
局部变量的作用域就是以这种方式来实现的。
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
所以，变量的作用域是使用不同的符号表来实现的，于是顶层的全局变量在函数内部使用时，
需要先使用global语句来将变量“挪”到函数独立的*active_symbol_table中，
即变量的跨域操作。（关于global的详细解释，见下一小节）

>**NOTE**
>在PHP的源码中，EX宏经常出现，它的作用是获取结构体zend_execute_data的字段值，它的实现是：        
>#define EX(element) execute_data->element

