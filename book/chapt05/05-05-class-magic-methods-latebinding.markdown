# 第五节 魔术方法，延迟绑定及静态成员

PHP中有一些特殊的函数和方法，这些函数和方法相比普通方法的特殊之处在于: 用户代码通常不会主动调用，
而是在特定的时机会被PHP自动调用。在PHP中通常以"\_\_"打头的方法都作为魔术方法， 所以通常不要定义以"\_\_"开头的函数或方法。
例如:\_\_autoload()函数， 通常我们不会手动调用这个函数， 而如果在代码中访问某个未定义的方法，
如过已经定义了\_\_autoload()函数，此时PHP将会尝试调用\_\_autoload()函数， 例如在类的定义中如果定义了\_\_construct()方法，
在初始化类的实例时将会调用这个方法， 同理还有\_\_destuct()方法，
详细内容请参考[PHP手册](http://php.net/manual/en/language.oop5.magic.php)。

## 魔术函数和魔术方法
前面提到魔术函数和魔术方法的特殊之处在于这些方法(在这里把函数和方法统称方法)的调用时机是在某些特定的场景才会被触发，
这些方法可以理解为一些事件监听方法， 在事件触发时才会执行。

### 魔术方法
根据前面的介绍， 魔术方法就是在类的某些场景下触发的一些监听方法。这些方法需要在类定义中进行定义，
在存储上魔术方法自然存储于类中， 而类在PHP内部是一个**_zend_class_entry**结构体，与普通方法一样，
只不过这些类不是存储在类的函数表， 而是直接存储在类结构体中:

*    在**_zend_class_entry**结构体中的存储位置不同;
*    由ZendVM自动分情境进行调用;
*    不是必须的，按需定义，自动调用

从以上三个方面可以发现，关于魔术变量的关键理解，主要集中在两个方面：**一，定义在哪里; 二，如何判断其存在并进行调用。**

首先，魔术变量的存储在**_zend_class_entry**中的代码如下：（完整的**_zend_class_entry**代码见本章第一节）

	[c]
	struct _zend_class_entry {
		...
		//构造方法 __construct
	    union _zend_function *constructor;
		//析构方法 __destruct
	    union _zend_function *destructor;
		//克隆方法 __clone
	    union _zend_function *clone;
	    union _zend_function *__get;
	    union _zend_function *__set;
	    union _zend_function *__unset;
	    union _zend_function *__isset;
	    union _zend_function *__call;
	    union _zend_function *__callstatic;
	    union _zend_function *__tostring;
		//序列化
	    union _zend_function *serialize_func;
		//反序列化
	    union _zend_function *unserialize_func;
		...
	}

这段代码明确的在对象内部定义了不同的指针来保存各种魔术变量。
关于Zend VM对魔术方法的调用机制，由于每种方法的调用情境不同，笔者在这里也分开进行分析。


###__construct
__construct构造方法，在对象创建时被自动调用。
与其它很多语言（如JAVA）不同的是，在PHP中，构造方法并没有使用”与类定义同名“的约定方式，而是单独用魔术方法来实现。
**__construct**方法的调用入口是new关键字对应的ZEND_NEW_SPEC_HANDLER函数。
Zend VM在初始化对象的时候，使用了new关键字，对其OPCODE进行分析后，使用GDB可以得到下面的堆栈信息：

	[c]
	#0  ZEND_NEW_SPEC_HANDLER (execute_data=0x100d00080) at zend_vm_execute.h:461
	#1  0x000000010041c1f0 in execute (op_array=0x100a1fd60) at zend_vm_execute.h:107
	#2  0x00000001003e9394 in zend_execute_scripts (type=8, retval=0x0, file_count=3) at /Volumes/DEV/C/php-5.3.4/Zend/zend.c:1194
	#3  0x0000000100368031 in php_execute_script (primary_file=0x7fff5fbff890) at /Volumes/DEV/C/php-5.3.4/main/main.c:2265
	#4  0x00000001004d4b5c in main (argc=2, argv=0x7fff5fbffa30) at /Volumes/DEV/C/php-5.3.4/sapi/cli/php_cli.c:1193


上面的椎栈信息清晰显示了new关键的调用过程，可以发现new关键字对应了ZEND_NEW_SPEC_HANDLER的处理函数，
在ZEND_NEW_SPEC_HANDLER中，Zend VM使用下面的代码来获取对象是否定义了**__construct**方法：

	[c]
	...
	constructor = Z_OBJ_HT_P(object_zval)->get_constructor(object_zval TSRMLS_CC);
	if (constructor == NULL){
		...
	} else {
		...
	}

	//get_constructor的实现
	ZEND_API union _zend_function *zend_std_get_constructor(zval *object TSRMLS_DC)
	{
	    zend_object *zobj = Z_OBJ_P(object);
	    zend_function *constructor = zobj->ce->constructor;

		if(constructor){ ... } else { ...}
		...
	}

从上面的代码可以看出ZendVM通过读取**zend_object->ce->constructor**的值来判断对象是不是定义的构造函数。


>**NOTE**
> Z_OBJ_P(zval); Z_OBJ_P宏将一个zval类型变量构造为zend_object类型。

在判断了**__construct**魔术变量存在之后，ZEND_NEW_SPEC_HANDLER中对当前EX(called_scope)进行了重新赋值，
使ZEND_VM_NEXT_OPCODE();将opline指针指向__construct方法的op_array，开始执行__construct魔术方法

		[c]
        EX(object) = object_zval;
        EX(fbc) = constructor;
        EX(called_scope) = EX_T(opline->op1.u.var).class_entry;
		ZEND_VM_NEXT_OPCODE();



###__destruct
**__destruct**是析构方法，运行于对象被显示销毁或者脚本关闭时，一般被用于释放占用的资源。
**__destruct**的调用涉及到垃圾回收机制，在第七章中会有更详尽的介绍。
本文笔者只针对**__destruct**调用机制进行分析，其调用堆栈信息如下：

	[bash]
	//省略部分内存地址信息后的堆栈：
	#0  zend_call_function () at /..//php-5.3.4/Zend/zend_execute_API.c:767
	#1  zend_call_method () at /..//php-5.3.4/Zend/zend_interfaces.c:97
	#2  zend_objects_destroy_object () at /..//php-5.3.4/Zend/zend_objects.c:112
	#3  zend_objects_store_del_ref_by_handle_ex () at /..//php-5.3.4/Zend/zend_objects_API.c:206
	#4  zend_objects_store_del_ref () at /..//php-5.3.4/Zend/zend_objects_API.c:172
	#5  _zval_dtor_func () at /..//php-5.3.4/Zend/zend_variables.c:52
	#6  _zval_dtor () at zend_variables.h:35
	#7  _zval_ptr_dtor () at /..//php-5.3.4/Zend/zend_execute_API.c:443
	#8  _zval_ptr_dtor_wrapper () at /..//php-5.3.4/Zend/zend_variables.c:189
	#9  zend_hash_apply_deleter () at /..//php-5.3.4/Zend/zend_hash.c:614
	#10 zend_hash_reverse_apply () at /..//php-5.3.4/Zend/zend_hash.c:763
	#11 shutdown_destructors () at /..//php-5.3.4/Zend/zend_execute_API.c:226
	#12 zend_call_destructors () at /..//php-5.3.4/Zend/zend.c:874
	#13 php_request_shutdown () at /..//php-5.3.4/main/main.c:1587
	#14 main () at /..//php-5.3.4/sapi/cli/php_cli.c:1374

**__destruct**方法存在与否是在**zend_objects_destroy_object**函数中进行判断的。
在脚本执行结果时，ZendVM在**php_request_shutdown**阶段会将对象池中的对象一一销毁，
这时如果某对象定义了**__destruct**魔术方法，此方法便会被执行。

在**zend_objects_destroy_object**中，与**__construct**一样，
ZendVM判断**zend_object->ce->destructor**是否为空，如果不为空，则调用**zend_call_method**执行**__destruct**析构方法。
进入**__destruct**的方式与**__construct**不同的是，**__destruct**的执行方式是由ZendVM直接调用**zend_call_function**来执行。

###__call与__callStatic###
+    **__call**：在对对象不存在的方法进行调用时自动执行;
+    **__callStatic**：在对对象不存在的静态方法进行调用时自动执行;

**__call**与**__callStatic**的调用机制几乎完全相同，关于函数的执行已经在上一章中提到，
用户对函数的调用是由**zend_do_fcall_common_helper_SPEC()**方法进行处理的。

####__call：####
经过**[ZEND_DO_FCALL_BY_NAME_SPEC_HANDLER]-> [zend_do_fcall_common_helper_SPEC]-> [zend_std_call_user_call]-> [zend_call_method]->[zend_call_function]**
调用，经过**zend_do_fcall_common_helper_SPEC**的分发，最终使用**zend_call_function**来执行**__call**。
####__callStatic：####
经过**[ZEND_DO_FCALL_BY_NAME_SPEC_HANDLER]-> [zend_do_fcall_common_helper_SPEC]-> [zend_std_callstatic_user_call]-> [zend_call_method]->[zend_call_function]**
调用，经过**zend_do_fcall_common_helper_SPEC**的分发，最终使用**zend_call_function**来执行**__callStatic**。

###其他魔术方法###
PHP中还有很多种魔术方法，它们的处理方式基本与上面类似，运行时执行与否取决的判断根据，
最终都是**_zend_class_entry**结构体中对应的指针是否为空。
这里列出它们的底层实现函数：

| 魔术方法 | 对应处理函数 |所在源文件|
|:-----------|:------------|:--------|
| __set       |        zend_std_call_setter() | Zend/zend_object_handlers.c|
| __get     	|      zend_std_call_getter() |Zend/zend_object_handlers.c|
| __isset       |        zend_std_call_issetter() |Zend/zend_object_handlers.c|
| __unset         |          zend_std_call_unsetter() | Zend/zend_object_handlers.c|
| __sleep       |       php_var_serialize_intern() |ext/standard/var.c|
| __wakeup	|	php_var_unserialize()	|	ext/standard/var_unserializer.c	|
| __toString	|	zend_std_cast_object_tostring()	|	Zend/zend_object_handlers.c|
| __invoke|	ZEND_DO_FCALL_BY_NAME_SPEC_HANDLER() | Zend/zend_vm_execute.h |
| __set_state | php_var_export_ex() | ext/standard/var.c |
| __clone |ZEND_CLONE_SPEC_CV_HANDLER() | Zend/zend_vm_execute.h |

##延迟绑定
在PHP手册中，对延迟绑定有以下定义。
>**NOTE**
>从PHP 5.3.0开始，PHP增加了一个叫做后期静态绑定的功能，用于在继承范围内引用静态调用的类。
>该功能从语言内部角度考虑被命名为“后期静态绑定”。
>“后期绑定”的意思是说，static::不再被解析为定义当前方法所在的类，而是在实际运行时计算的。
>也可以称之为”静态绑定“，因为它可以用于（但不限于）静态方法的调用。

延迟绑定的实现关键在于static关键字，如果以static调用静态方法，则在语法解析时:

    function_call:
    ...//省略若干其它情况的函数调用
	|	class_name T_PAAMAYIM_NEKUDOTAYIM T_STRING '(' { $4.u.opline_num = zend_do_begin_class_member_function_call(&$1, &$3 TSRMLS_CC); }
			function_call_parameter_list
			')' { zend_do_end_function_call($4.u.opline_num?NULL:&$3, &$$, &$6, $4.u.opline_num, $4.u.opline_num TSRMLS_CC); zend_do_extended_fcall_end(TSRMLS_C);}
    ...//省略若干其它情况的函数调用

    class_name:
		T_STATIC { $$.op_type = IS_CONST; ZVAL_STRINGL(&$$.u.constant, "static", sizeof("static")-1, 1);}

如上所示，static将以第一个参数(class_name)传递给zend_do_begin_class_member_function_call函数。
此时class_name的op_type字段为IS_CONST，但是通过zend_get_class_fetch_type获取此类的类型为ZEND_FETCH_CLASS_STATIC。
这个类型作为操作的extended_value字段存在，此字段在后面执行获取类的中间代码ZEND_FETCH_CLASS（ZEND_FETCH_CLASS_SPEC_CONST_HANDLER）时，
将作为第三个参数(fetch_type)传递给获取类名的最终执行函数zend_fetch_class。

    EX_T(opline->result.u.var).class_entry = zend_fetch_class(Z_STRVAL_P(class_name),
        Z_STRLEN_P(class_name), opline->extended_value TSRMLS_CC);

至于在后面如何执行，请查看下一小节：第六节 PHP保留类及特殊类
