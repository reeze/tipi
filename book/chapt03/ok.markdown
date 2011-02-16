# 第二节 变量的生命周期与作用域

通过上节的描述，我们已经知道了PHP中变量的存储方式－－所有的变量都保存在zval结构中。那么PHP内核如何实现变量的作用域？变量的生命周期又是怎样的呢？

先准备一个示例程序：

	[php]
	<?php
		$tipi = 'o_o';
	?>

这个程序只有一条赋值语句，经过词法和语法的分析后，生成的opcodes如下：

	[c]
	Finding entry points
	Branch analysis from position: 0
	Return found
	filename:       /path/test.php
	function name:  (null)
	number of ops:  2
	compiled vars:  !0 = $tipi
	line     # *  op                           fetch          ext  return  operands
	---------------------------------------------------------------------------------
	   2     0  >   ASSIGN                                                   !0, 'o_o'
	  14     1    > RETURN                                                   1
	
	branch: #  0; line:     2-   14; sop:     0; eop:     1
	path #1: 0, 


	


在ZE进行词法和语法分析之后,生成具体的opcode,这些opcode最终被execute函数(Zend/zend_vm_execute.h:46)解释执行。在excute函数中，有以下代码：

    [c]
    if (op_array->this_var != -1 && EG(This)) {
        Z_ADDREF_P(EG(This)); /* For $this pointer */
        if (!EG(active_symbol_table)) {
            EX(CVs)[op_array->this_var] = (zval**)EX(CVs) + (op_array->last_var + op_array->this_var);
            *EX(CVs)[op_array->this_var] = EG(This);
        } else {
            if (zend_hash_add(EG(active_symbol_table), "this", sizeof("this"), &EG(This), sizeof(zval *), (void**)&EX(CVs)[op_array->this_var])==FAILURE) {
                Z_DELREF_P(EG(This));
            }     
        }     
    }

这样，需要“注册”的变量就被写入到active_symbol_table中去了。active_symbol_table是活动符号表，其结构依然是HashTable。




PHP变量本身存储于zval结构体中，而zval本身仅存储了变量的值。变量名则保存在某些hashTable中。



