# 变量的声明,初始化,赋值及销毁

在强类型的语言当中，当使用一个变量之前，我们需要先声明这个变量。然而，对于PHP来说，
在使用一个变量时，我们不需要声明，也不需要初始化，直接用就可以了。那为什么可以直接用呢？
下面我们看下原因。

## 变量的声明和赋值
在PHP中没有对于常规变量的声明操作，如果要使用一个变量,直接进行赋值操作即可。在赋值操作的时候已经将声明操作。
一个简单的赋值操作：

    [php]
    $a = 10;

使用VLD扩展查看其生成的中间代码为 **ASSIGN**. 依此，我们找到其执行的函数为 **ZEND_ASSIGN_SPEC_VAR_CONST_HANDLER**.
之所以为这个函数是因为$a为VAR,10为CONST，而我们进行的是一个ASSIGN操作。
在这个函数中



    [c]
    variable '=' expr		{ zend_check_writable_variable(&$1); zend_do_assign(&$$, &$1, &$3 TSRMLS_CC); }


    [c]
    static int ZEND_FASTCALL  ZEND_ASSIGN_SPEC_VAR_CONST_HANDLER(ZEND_OPCODE_HANDLER_ARGS)



## 变量的初始化
在PHP中，变量是不需要初始化的，用的时候就直接赋值了。
这里我们想说的是，在使用一个变量前初始化这个变量，这是一个很好的编程习惯，也是一种代码优化的方案。
以数组为例，我们来看下初始化和不初始化之间的区别。



## 变量的销毁
在PHP中销毁变量最常用的方法是使用unset函数。
unset函数并不是一个真正意义上的函数，它是一种语言结构。
在使用此函数时，它会根据变量的不同触发不同的操作。

一个简单的例子：

    [php]
    $a = 10;
    unset($a);

使用VLD扩展查看其生成的中间代码：

    compiled vars:  !0 = $a
    line     # *  op                           fetch          ext  return  operands
    ---------------------------------------------------------------------------------
       2     0  >   EXT_STMT
             1      ASSIGN                                                   !0, 10
       3     2      EXT_STMT
             3      UNSET_VAR                                                !0
             4    > RETURN                                                   1

去掉关于赋值的中间代码，得到unset函数生成的中间代码为 **UNSET_VAR**,由于我们unse的是一个变量，
在Zend/zend_vm_execute.h文件中查找到其最终调用的执行中间代码的函数为： **ZEND_UNSET_VAR_SPEC_VAR_HANDLER**
关键代码代码如下：

    [c]
    target_symbol_table = zend_get_target_symbol_table(opline, EX(Ts),
            BP_VAR_IS, varname TSRMLS_CC);
		if (zend_hash_quick_del(target_symbol_table, varname->value.str.val,
                varname->value.str.len+1, hash_value) == SUCCESS) {
			...//省略
		}

程序会先获取目标符号表，这个符号表是一个HashTable,然后将我们需要unset掉的变量从这个HashTable中删除。
关于HashTable的操作请参考前面的章节。

