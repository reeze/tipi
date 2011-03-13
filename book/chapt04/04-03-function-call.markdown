# 第三节 函数的调用和执行
本节将介绍函数如何被调用及执行的过程。

**函数的调用**
我们还是从以下两个小例子开始：

	[php]
	<?php
		function foo(){
			echo "I'm foo!";
		}	
		foo();
	?>

下面我们先看一下其对应的opcodes：

	[php]
	function name:  (null)
	line     # *  op                           fetch          ext  return  operands
	---------------------------------------------------------------------------------
	 		  >   NOP                                                      
	 		      DO_FCALL                                      0          'foo'
	 		      NOP                                                      
	 		    > RETURN                                                   1
	
	function name:  foo
	line     # *  op                           fetch          ext  return  operands
	---------------------------------------------------------------------------------
	   4     0  >   ECHO                                                     'I%27m+foo%21'
	   5     1    > RETURN                                                   null
	
	
上面是去除了一些枝节信息的的opcodes，可以看到执行时函数部分的opcodes是单独独立出来的，这点对于函数的执行特别重要，下面的部分会详细介绍。现在，我们把焦点放到对foo数的调用上面。
调用foo的OPCODE是“DO_FCALL“， DO_FCALL进行函数调用操作时，ZE会在function_table中，根据函数名（如前所述，这里的函数名经过str_tolower的处理，所以PHP的函数名大小写不敏感)查找函数的定义， 如果不存在，则报出“Call to undefined function xxx()"的错误信息; 如果存在，就返回该函数zend_function结构指针, 然后通过function.type的值来判断函数是内部函数还是用户定义的函数,调用zend_execute_internal（zend_internal_function.handler）或者直接 调用zend_execute来执行这个函数包含的zend_op_array。


**函数的执行**

用户定义函数的执行与其他语句的执行并无区别，在本质上看，其实函数中的php语句与函数外的php语句并无不同。函数体本身最大的区别，在于其执行环境的不同。这个“执行环境”最重要的特征就是变量的作用域。大家都知道，函数内定义的变量在函数体外是无法直接使用的，反之也是一样。那么，在函数执行的时候，进入函数前的环境信息是必须要保存的。在函数执行完毕后，这些环境信息也会被还原，使整个程序继续的执行下去。

内部函数的执行与用户函数不同。用户函数是php语句一条条“翻译”成op_line组成的一个op_array,而内部函数则是用C来实现的，因为执行环境也是C环境，所以可以直接调用。如下面的例子：

	[php]	
	<?php
		$foo = 'test';
		print_r($foo);
	?>

对应的opcodes也很简单：

	[c]
	line     # *  op                           fetch          ext  return  operands
	---------------------------------------------------------------------------------
	   2     0  >   ASSIGN                                                   !0, 'test'
	   3     1      SEND_VAR                                                 !0
	         2      DO_FCALL                                      1          'print_r'
	   4     3    > RETURN                                                   1

可以看出，生成的opcodes中，内部函数和用户函数的处理都是由DO_FCALL来进行的。而在其具体实现的zend_do_fcall_common_helper_SPEC()中，则对是否为内部函数进行了判断，如果是内部函数，则使用一个比较长的调用

	[c]
	((zend_internal_function *) EX(function_state).function)->handler(opline->extended_value, EX_T(opline->result.u.var).var.ptr, EX(function_state).function->common      .return_reference?&EX_T(opline->result.u.var).var.ptr:NULL, EX(object), RETURN_VALUE_USED(opline) TSRMLS_CC);



