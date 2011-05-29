# 第三节 函数的调用和执行
前面小节中对函数的内部表示以及参数的传递，返回值都有了介绍，那函数是怎么被调用的呢？内置函数和用户定义函数在调用时会有什么不一样呢？
下面将介绍函数调用和执行的过程。

## 函数的调用
函数被调用需要一些基本的信息，比如函数的名称，参数以及函数的定义(也就是最终函数是怎么执行的)， 从我们开发者的角度来看，
定义了一个函数我们在执行的时候自然知道这个函数叫什么名字，以及调用的时候给传递了什么参数，以及函数是怎么执行的。
但是对于Zend引擎来说，它并不能像我们这样能“看懂”php源代码，他们需要对代码进行处理以后才能执行。我们还是从以下两个小例子开始：

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
	 		      DO_FCALL                                      0          'foo'
	 		      NOP                                                      
	 		    > RETURN                                                   1
	
	function name:  foo
	line     # *  op                           fetch          ext  return  operands
	---------------------------------------------------------------------------------
	   4     0  >   ECHO                                                     'I%27m+foo%21'
	   5     1    > RETURN                                                   null
	
	
上面是去除了一些枝节信息的的opcodes，可以看到执行时函数部分的opcodes是单独独立出来的，这点对于函数的执行特别重要，下面的部分会详细介绍。
现在，我们把焦点放到对foo函数的调用上面。调用foo的OPCODE是“DO_FCALL“， DO_FCALL进行函数调用操作时，ZE会在function_table中根据函数名
（如前所述，这里的函数名经过str_tolower的处理，所以PHP的函数名大小写不敏感)查找函数的定义， 如果不存在，
则报出“Call to undefined function xxx()"的错误信息; 如果存在，就返回该函数zend_function结构指针，
然后通过function.type的值来判断函数是内部函数还是用户定义的函数，
调用zend_execute_internal（zend_internal_function.handler）或者直接 调用zend_execute来执行这个函数包含的zend_op_array。


## 函数的执行

细心的读者可能会注意到上面opcodes里函数被调用的时候以及函数定义那都有个"function name:"，其实用户定义函数的执行与其他语句的执行并无区别，
在本质上看，其实函数中的php语句与函数外的php语句并无不同。函数体本身最大的区别，在于其执行环境的不同。
这个“执行环境”最重要的特征就是变量的作用域。大家都知道，函数内定义的变量在函数体外是无法直接使用的，反之也是一样。那么，在函数执行的时候，
进入函数前的环境信息是必须要保存的。在函数执行完毕后，这些环境信息也会被还原，使整个程序继续的执行下去。

内部函数的执行与用户函数不同。用户函数是php语句一条条“翻译”成op_line组成的一个op_array，而内部函数则是用C来实现的，因为执行环境也是C环境，
所以可以直接调用。如下面的例子：

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

可以看出，生成的opcodes中，内部函数和用户函数的处理都是由DO_FCALL来进行的。而在其具体实现的zend_do_fcall_common_helper_SPEC()中，
则对是否为内部函数进行了判断，如果是内部函数，则使用一个比较长的调用

	[c]
	((zend_internal_function *) EX(function_state).function)->handler(opline->extended_value, EX_T(opline->result.u.var).var.ptr, EX(function_state).function->common      .return_reference?&EX_T(opline->result.u.var).var.ptr:NULL, EX(object), RETURN_VALUE_USED(opline) TSRMLS_CC);

上面这种方式的内部函数是在zend_execute_internal函数没有定义的情况下。而在而在Zend/zend.c文件的zend_startup函数中，

    [c]
    zend_execute_internal = NULL;

此函数确实被赋值为NULL。于是我们在if (!zend_execute_internal)判断时会成立，所以我们是执行那段很长的调用。
那么，这段很长的调用到底是什么呢？以我们常用的 **count**函数为例。在[<<第一节 函数的内部结构>>][function-struct]中，
我们知道内部函数所在的结构体中
有一个handler指针指向此函数需要调用的内部定义的C函数。
这些内部函数在模块初始化时就以扩展的函数的形式加载到EG(function_table)。其调用顺序：

    [shell]
    php_module_startup --> php_register_extensions --> zend_register_internal_module
    --> zend_register_module_ex --> zend_register_functions

    zend_register_functions(NULL, module->functions, NULL, module->type TSRMLS_CC)

在standard扩展中。module的定义为：

    [c]
    zend_module_entry basic_functions_module = { /* {{{ */
        STANDARD_MODULE_HEADER_EX,
        NULL,
        standard_deps,
        "standard",					/* extension name */
        basic_functions,			/* function list */
        ... //省略
    }

从上面的代码可以看出，module->functions是指向basic_functions。在basic_functions.c文件中查找basic_functions的定义。

    [c]
    const zend_function_entry basic_functions[] = { /* {{{ */
        ...//   省略
        PHP_FE(count,															arginfo_count)
        ...//省略
    }

    #define PHP_FE			ZEND_FE
    #define ZEND_FE(name, arg_info)						ZEND_FENTRY(name, ZEND_FN(name), arg_info, 0)
    #define ZEND_FN(name) zif_##name
    #define ZEND_FENTRY(zend_name, name, arg_info, flags)	{ #zend_name, name, arg_info, (zend_uint) (sizeof(arg_info)/sizeof(struct _zend_arg_info)-1), flags },

综合上面的代码，count函数最后调用的函数名为zif_count，但是此函数对外的函数名还是为count。
调用的函数名name以第二个元素存放在zend_function_entry结构体数组中。
对于zend_function_entry的结构

    [c]
    typedef struct _zend_function_entry {
        const char *fname;
        void (*handler)(INTERNAL_FUNCTION_PARAMETERS);
        const struct _zend_arg_info *arg_info;
        zend_uint num_args;
        zend_uint flags;
    } zend_function_entry;

第二个元素为handler。这也就是我们在执行内部函数时的调用方法。因此在执行时就会调用到对应的函数。

对于用户定义的函数，在zend_do_fcall_common_helper_SPEC()函数中，

    [c]
    if (EX(function_state).function->type == ZEND_USER_FUNCTION ||
	    EX(function_state).function->common.scope) {
		should_change_scope = 1;
		EX(current_this) = EG(This);
		EX(current_scope) = EG(scope);
		EX(current_called_scope) = EG(called_scope);
		EG(This) = EX(object);
		EG(scope) = (EX(function_state).function->type == ZEND_USER_FUNCTION || !EX(object)) ? EX(function_state).function->common.scope : NULL;
		EG(called_scope) = EX(called_scope);
	}

先将EG下的This，scope等暂时缓存起来（这些在后面会都恢复到此时缓存的数据）。在此之后，对于用户自定义的函数，
程序会依据zend_execute是否等于execute并且是否为异常来判断是返回，还是直接执行函数定义的op_array：

    [c]
	if (zend_execute == execute && !EG(exception)) {
			EX(call_opline) = opline;
			ZEND_VM_ENTER();
		} else {
			zend_execute(EG(active_op_array) TSRMLS_CC);
		}

而在Zend/zend.c文件的zend_startup函数中，已将zend_execute赋值为：

    [c]
    zend_execute = execute;

从而对于异常，程序会抛出异常；其它情况，程序会调用execute执行此函数中生成的opcodes。
execute函数会遍历所传递给它的zend_op_array数组，以方式

    [c]
    ret = EX(opline)->handler(execute_data TSRMLS_CC)

调用每个opcode的处理函数。而execute_data在execute函数开始时就已经给其分配了空间，这就是这个函数的执行环境。


[function-struct]:   	?p=chapt04/04-01-function-struct
