# opcode处理函数查找

从上一小节读者可以了解到opcode在PHP内部的实现，那怎么找到某个opcode的处理函数呢？
为了方便读者在追踪代码的过程中找到各种opcode对应的处理函数实现，下面介绍几种方法。

>**NOTE**
>从PHP5.1开始,PHP对opcode的分发方式可以用户自定义，分为CALL, SWITCH, 和GOTO三种类型。
>默认使用的CALL的方式，本文也应用于这种方式。有关Zend虚拟机的介绍请阅读后面相关内容。

## Debug法

在学习研究PHP内核的过程中，经常通过opcode来查看代码的执行顺序，opcode的执行由在文件Zend/zend_vm_execute.h中的execute函数执行。

	[c]
	ZEND_API void execute(zend_op_array *op_array TSRMLS_DC)
	{
	...
	zend_vm_enter:
	....
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
	｝
	...
	}

在执行的过程中，EX(opline)->handler（展开后为  *execute_data->opline->handler）存储了处理当前操作的函数指针。
使用gdb调试，在execute函数处增加断电，使用p命令可以打印出类似这样的结果：

	[c]
	(gdb) p *execute_data->opline->handler
	$1 = {int (zend_execute_data *)} 0x10041f394 <ZEND_NOP_SPEC_HANDLER>

这样就可以方便的知道当前要执行的处理函数了，这种debug的方法。这种方法比较麻烦，需要使用gdb来调试。


##计算法

在PHP内部有一个函数用来快速的返回特定opcode对应的opcode处理函数指针：zend_vm_get_opcode_handler()函数：

    [c]
    static opcode_handler_t
    zend_vm_get_opcode_handler(zend_uchar opcode, zend_op* op)
    {
            static const int zend_vm_decode[] = {
                _UNUSED_CODE, /* 0              */
                _CONST_CODE,  /* 1 = IS_CONST   */
                _TMP_CODE,    /* 2 = IS_TMP_VAR */
                _UNUSED_CODE, /* 3              */
                _VAR_CODE,    /* 4 = IS_VAR     */
                _UNUSED_CODE, /* 5              */
                _UNUSED_CODE, /* 6              */
                _UNUSED_CODE, /* 7              */
                _UNUSED_CODE, /* 8 = IS_UNUSED  */
                _UNUSED_CODE, /* 9              */
                _UNUSED_CODE, /* 10             */
                _UNUSED_CODE, /* 11             */
                _UNUSED_CODE, /* 12             */
                _UNUSED_CODE, /* 13             */
                _UNUSED_CODE, /* 14             */
                _UNUSED_CODE, /* 15             */
                _CV_CODE      /* 16 = IS_CV     */
            };  
            return zend_opcode_handlers[
                 opcode * 25 + zend_vm_decode[op->op1.op_type] * 5
                         + zend_vm_decode[op->op2.op_type]];
    }


由上面的代码可以看到，opcode到php内部函数指针的查找是由下面的公式来进行的：

    [c]
    opcode * 25 + zend_vm_decode[op->op1.op_type] * 5
                    + zend_vm_decode[op->op2.op_type]

然后将其计算的数值作为索引到zend_init_opcodes_handlers数组中进行查找。
不过这个数组实在是太大了，有3851个元素，手动查找和计算都比较麻烦。


## 命名查找法

上面的两种方法其实都是比较麻烦的，在定位某一opcode的实现执行代码的过程中，
都不得不对程序进行执行或者计算中间值。而在追踪的过程中，笔者发现处理函数名称是有一定规则的。
这里以函数调用的opcode为例，调用某函数的opcode及其对应在php内核中实现的处理函数如下：

    [c]
    //函数调用：
    DO_FCALL  ==>  ZEND_DO_FCALL_SPEC_CONST_HANDLER

    //变量赋值：
    ASSIGN     =>      ZEND_ASSIGN_SPEC_VAR_CONST_HANDLER
                       ZEND_ASSIGN_SPEC_VAR_TMP_HANDLER
                       ZEND_ASSIGN_SPEC_VAR_VAR_HANDLER
                       ZEND_ASSIGN_SPEC_VAR_CV_HANDLER            
    //变量加法：
    ASSIGN_SUB =>   ZEND_ASSIGN_SUB_SPEC_VAR_CONST_HANDLER,
                        ZEND_ASSIGN_SUB_SPEC_VAR_TMP_HANDLER,
                        ZEND_ASSIGN_SUB_SPEC_VAR_VAR_HANDLER,
                        ZEND_ASSIGN_SUB_SPEC_VAR_UNUSED_HANDLER,
                        ZEND_ASSIGN_SUB_SPEC_VAR_CV_HANDLER,
                        ZEND_ASSIGN_SUB_SPEC_UNUSED_CONST_HANDLER,
                        ZEND_ASSIGN_SUB_SPEC_UNUSED_TMP_HANDLER,
                        ZEND_ASSIGN_SUB_SPEC_UNUSED_VAR_HANDLER,
                        ZEND_ASSIGN_SUB_SPEC_UNUSED_UNUSED_HANDLER,
                        ZEND_ASSIGN_SUB_SPEC_UNUSED_CV_HANDLER,
                        ZEND_ASSIGN_SUB_SPEC_CV_CONST_HANDLER,
                        ZEND_ASSIGN_SUB_SPEC_CV_TMP_HANDLER,
                        ZEND_ASSIGN_SUB_SPEC_CV_VAR_HANDLER,
                        ZEND_ASSIGN_SUB_SPEC_CV_UNUSED_HANDLER,
                        ZEND_ASSIGN_SUB_SPEC_CV_CV_HANDLER,

在上面的命名就会发现，其实处理函数的命名是有以下规律的：

    [c]
    ZEND_[opcode]_SPEC_(变量类型1)_(变量类型2)_HANDLER


这里的变量类型1和变量类型2是可选的，如果同时存在，那就是左值和右值，归纳有下几类：
VAR TMP CV UNUSED CONST
这样可以根据相关的执行场景来判定。

## 日志记录法
这种方法是上面**计算法**的升级，同时也是比较精准的方式。在**zend_vm_get_opcode_handler** 方法中添加以下代码：

	[c]
	static opcode_handler_t
	zend_vm_get_opcode_handler(zend_uchar opcode, zend_op* op)
	{
	        static const int zend_vm_decode[] = {
	            _UNUSED_CODE, /* 0              */
	            _CONST_CODE,  /* 1 = IS_CONST   */
	            _TMP_CODE,    /* 2 = IS_TMP_VAR */
	            _UNUSED_CODE, /* 3              */
	            _VAR_CODE,    /* 4 = IS_VAR     */
	            _UNUSED_CODE, /* 5              */
	            _UNUSED_CODE, /* 6              */
	            _UNUSED_CODE, /* 7              */
	            _UNUSED_CODE, /* 8 = IS_UNUSED  */
	            _UNUSED_CODE, /* 9              */
	            _UNUSED_CODE, /* 10             */
	            _UNUSED_CODE, /* 11             */
	            _UNUSED_CODE, /* 12             */
	            _UNUSED_CODE, /* 13             */
	            _UNUSED_CODE, /* 14             */
	            _UNUSED_CODE, /* 15             */
	            _CV_CODE      /* 16 = IS_CV     */
	        };  
	
	   	 //很显然，我们把opcode和相对应的写到了/tmp/php.log文件中
	   	 int op_index;
	   	 op_index = opcode * 25 + zend_vm_decode[op->op1.op_type] * 5 + zend_vm_decode[op->op2.op_type];
	
	   	 FILE *stream;
	   	 if((stream = fopen("/tmp/php.log", "a+")) != NULL){
	   		 fprintf(stream, "opcode: %d , zend_opcode_handlers_index:%d\n", opcode, op_index);
	   	 }    
	   	 fclose(stream);
	
	
	        return zend_opcode_handlers[
	             opcode * 25 + zend_vm_decode[op->op1.op_type] * 5
	                     + zend_vm_decode[op->op2.op_type]];
	}

然后，就可以在**/tmp/php.log**文件中生成类似如下结果:

	[c]
	opcode: 38 , zend_opcode_handlers_index:970

前面的数字是opcode的，我们可以这里查到： http://php.net/manual/en/internals2.opcodes.list.php
后面的数字是static const opcode_handler_t labels[] 索引，里面对应了处理函数的名称，
对应源码文件是：Zend/zend_vm_execute.h （第30077行左右）。 这是一个超大的数组，php5.3.4中有3851个元素，
在上面的例子里，看样子我们要数到第970个了，当然，有很多种方法来避免人工去计算, 这里就不多介绍了。
