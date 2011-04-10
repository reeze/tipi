# 变量的赋值和销毁

在强类型的语言当中，当使用一个变量之前，我们需要先声明这个变量。然而，对于PHP来说，
在使用一个变量时，我们不需要声明，也不需要初始化，直接用就可以了。那为什么可以直接用呢？
下面我们看下原因。

## 变量的声明和赋值
在PHP中没有对于常规变量的声明操作，如果要使用一个变量,直接进行赋值操作即可。在赋值操作的时候已经将声明操作。
一个简单的赋值操作：

    [php]
    $a = 10;

使用VLD扩展查看其生成的中间代码为 **ASSIGN**. 依此，我们找到其执行的函数为 **ZEND_ASSIGN_SPEC_CV_CONST_HANDLER**.
之所以为这个函数是因为$a为CV,10为CONST，而我们进行的是一个ASSIGN操作。
CV是PHP在5.1后增加的一个在编译期的缓存。如我们在使用VLD查看上面的PHP代码生成的中间代码时会看到：

    [c]
    compiled vars:  !0 = $a

这个$a变量就是op_type为IS_CV的变量。

>**NOTE**
>IS_CV值的设置是在语法解析时进行的。具体见Zend/zend_complie.c文件zend_do_end_variable_parse函数。

在这个函数中,获取这个赋值操作的左值和右值的代码为：

    [c]
    zval *value = &opline->op2.u.constant;
	zval **variable_ptr_ptr = _get_zval_ptr_ptr_cv(&opline->op1, EX(Ts), BP_VAR_W TSRMLS_CC);

由于右值为一个数值，我们可以理解为一个常量，则直接取操作数存储的constant字段，关于这个字段的说明将在后面的虚拟机章节说明。
左值是通过 _get_zval_ptr_ptr_cv函数获取zval值。这个函数最后的调用顺序为：
[_get_zval_ptr_ptr_cv] --> [_get_zval_cv_lookup]

在_get_zval_cv_lookup函数中关键代码为：

    [c]
    zend_hash_quick_find(EG(active_symbol_table), cv->name, cv->name_len+1, cv->hash_value, (void **)ptr)

这是一个HashTable的查找函数，它的作用是从EG(active_symbol_table)中查找名称为cv->name的变量，并将这个值赋值给ptr。
最后，这个在符号表中找到的值将传递给ZEND_ASSIGN_SPEC_CV_CONST_HANDLER函数的variable_ptr_ptr变量。

以上是获取左值和右值的过程，在这步操作后将执行赋值操作的核心操作--赋值。赋值操作是通过调用zend_assign_to_variable函数实现。
在zend_assign_to_variable函数中，赋值操作分为好几种情况来处理，在程序中就是以几层的if语句体现。

### 情况一：赋值的左值存在引用，即zval变量中is_ref__gc字段不为0并且左值不等于右值

其在代码中的体现为：

    [c]
    if (PZVAL_IS_REF(variable_ptr)) {
		if (variable_ptr!=value) {
			zend_uint refcount = Z_REFCOUNT_P(variable_ptr);

			garbage = *variable_ptr;
			*variable_ptr = *value;
			Z_SET_REFCOUNT_P(variable_ptr, refcount);
			Z_SET_ISREF_P(variable_ptr);
			if (!is_tmp_var) {
				zendi_zval_copy_ctor(*variable_ptr);
			}
			zendi_zval_dtor(garbage);
			return variable_ptr;
		}
	}

PZVAL_IS_REF(variable_ptr)判断is_ref__gc字段是否为0.在左值不等于右值的情况下执行操作。
所有指向这个zval容器的变量的值都变成了*value。并且引用计数的值不变。下面是这种情况的一个示例：

    [php]
    $a = 10;
    $b = &$a;

    xdebug_debug_zval('a');

    $b = 20;
    xdebug_debug_zval('a');

上面的程序输出：

    [c]
    a:
    (refcount=2, is_ref=1),int 10
    a:
    (refcount=2, is_ref=1),int 20

### 情况二：赋值的左值不存在引用，左值的引用计数为1，左值等于右值
在这种情况下，我们所看到的应该是什么都不会发生。看一个示例：

    [php]
    $a = 10;
    $a = $a;

这个时候引用计数还是1，虽然没有变化，但是在内核中，变量的引用计数已经经历了一次加一和一次减一的操作。如下代码：

    [c]
    if (Z_DELREF_P(variable_ptr)==0) {  //  引用计数减一操作
			if (!is_tmp_var) {
				if (variable_ptr==value) {
					Z_ADDREF_P(variable_ptr);   //  引用计数加一操作
				}
    ...//省略

### 情况三：赋值的左值不存在引用,左值的引用计数为1，右值存在引用
一个PHP的示例：

    [php]
    $a = 10;
    $b = &$a;
    $c = $a;

第三行的操作就是我们所示的第三种情况。其内核实现代码如下：

    [c]

	garbage = *variable_ptr;
	*variable_ptr = *value;
	INIT_PZVAL(variable_ptr);   //  初始化一个新的zval变量容器
	zval_copy_ctor(variable_ptr);   
	zendi_zval_dtor(garbage);
	return variable_ptr;


对于这种情况，ZEND内核直接创建一个新的zval容器，左值的值为右值，并且左值的引用计数为1。

### 情况四：赋值的左值不存在引用,左值的引用计数为1，右值不存在引用
一个PHP示例：

    [php]
    $a = 10;
    $c = $a;

在这种情况下，右值的引用计数加上，一般情况下，会对左值进行垃圾收集操作，将其移入垃圾缓冲池。垃圾缓冲池的功能是在PHP5.3后才有的。
在PHP内核中的代码体现为：

    [c]
    Z_ADDREF_P(value);  //  引用计数加1
	*variable_ptr_ptr = value;
	if (variable_ptr != &EG(uninitialized_zval)) {
		GC_REMOVE_ZVAL_FROM_BUFFER(variable_ptr);   //  调用垃圾收集机制
		zval_dtor(variable_ptr);
		efree(variable_ptr);    //  释放变量内存空间
	}
	return value;

### 情况五：赋值的左值不存在引用,左值的引用计数为大于0，右值存在引用，并且引用计数大于0
一个演示这种情况的PHP示例：

    [php]
    $a = 10;
    $b = $a;
    $va = 20;
    $vb = &$va;

    $a = $va;

最后一个操作就是我们的情况五。
使用xdebug看引用计数，我们可以知道，$a变量的引用计数为2，$va变量的引用计数为2，并且存在引用。
从源码层分析这个原因：

    [c]
    ALLOC_ZVAL(variable_ptr);   //  分配新的zval容器
	*variable_ptr_ptr = variable_ptr;
	*variable_ptr = *value;
	zval_copy_ctor(variable_ptr);
	Z_SET_REFCOUNT_P(variable_ptr, 1);  //  设置引用计数为1

从代码可以看出是新分配了一个zval容器，并设置了引用计数为1,这可以确认我们之前的例子中的$a变量的结果是正确，

除了这五种情况，函数中针对临时变量全部做了另外的处理。关于变量赋值的各种情况尽在这个函数。



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
在Zend/zend_vm_execute.h文件中查找到其最终调用的执行中间代码的函数为： **ZEND_UNSET_VAR_SPEC_CV_HANDLER**
关键代码代码如下：

    [c]
    target_symbol_table = zend_get_target_symbol_table(opline, EX(Ts),
            BP_VAR_IS, varname TSRMLS_CC);
		if (zend_hash_quick_del(target_symbol_table, varname->value.str.val,
                varname->value.str.len+1, hash_value) == SUCCESS) {
			...//省略
		}

程序会先获取目标符号表，这个符号表是一个HashTable,然后将我们需要unset掉的变量从这个HashTable中删除。
关于HashTable的操作请参考 [<< 哈希表(HashTable) >>][variables-hashtable]。

[variables-hashtable]: 	?p=chapt03/03-01-01-hashtable