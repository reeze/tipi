# 第五节 魔术方法,延迟绑定及静态成员
##魔术方法
魔术方法存储于**_zend_class_entry**结构体中，与普通方法一样，它们本身使用HashTable来存储，所以它们与普通方法在底层的存储是完全相同的。导致它们成为了”魔术方法“的原因则主要是由于”存在便执行，不存在则跳过“的”魔术调用“，总而言之，魔术方法与普通方法有以下不同：   

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
这段代码明确的在对象内部定义了不同的指针来保存各种魔术变量。关于Zend VM对魔术方法的调用机制，由于每种方法的调用情境不同，笔者在这里也分开进行分析。


###__construct###
__construct是构造方法，在对象创建时被调用，与其它很多语言（如JAVA）不同的是，在PHP中，构造方法并没有使用”与类定义同名“的约定方式，而是单独用魔术方法来实现。**__construct**方法的调用入口是new关键字对应的ZEND_NEW_SPEC_HANDLER函数。
Zend VM在初始化对象的时候，使用了new关键字，对其OPCODE进行分析后，使用GDB可以得到下面的堆栈信息：

	[c]
	#0  ZEND_NEW_SPEC_HANDLER (execute_data=0x100d00080) at zend_vm_execute.h:461
	#1  0x000000010041c1f0 in execute (op_array=0x100a1fd60) at zend_vm_execute.h:107
	#2  0x00000001003e9394 in zend_execute_scripts (type=8, retval=0x0, file_count=3) at /Volumes/DEV/C/php-5.3.4/Zend/zend.c:1194
	#3  0x0000000100368031 in php_execute_script (primary_file=0x7fff5fbff890) at /Volumes/DEV/C/php-5.3.4/main/main.c:2265
	#4  0x00000001004d4b5c in main (argc=2, argv=0x7fff5fbffa30) at /Volumes/DEV/C/php-5.3.4/sapi/cli/php_cli.c:1193


上面的椎栈信息清晰显示了new关键的调用过程，可以发现new关键字对应了ZEND_NEW_SPEC_HANDLER的处理函数，在ZEND_NEW_SPEC_HANDLER中，Zend VM使用下面的代码来获取对象是否定义了**__construct**方法：

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

在判断了**__construct魔术变量存在之后，ZEND_NEW_SPEC_HANDLER中对当前EX(called_scope)进行了重新赋值，使ZEND_VM_NEXT_OPCODE();将opline指针指向__construct方法的op_array，开始执行__construct魔术方法

		[c]
        EX(object) = object_zval;
        EX(fbc) = constructor;
        EX(called_scope) = EX_T(opline->op1.u.var).class_entry;
		ZEND_VM_NEXT_OPCODE();



###__destruct###
**__destruct**是析构方法，运行于对象被显示销毁或者脚本关闭时,一般被用于释放占用的资源。
**__destruct**的调用涉及到垃圾回收机制，在第七章中会有更详尽的介绍。
本文笔者只针对**__destruct**调用机制进行分析,其调用的顺序如下：

	[bash]
	#0  zend_call_function (fci=0x7fff5fbff050, fci_cache=0x7fff5fbff0a0) at /Volumes/DEV/C/php-5.3.4/Zend/zend_execute_API.c:767
	#1  0x0000000100406bb2 in zend_call_method (object_pp=0x7fff5fbff1b8, obj_ce=0x100a22258, fn_proxy=0x7fff5fbff1c8, function_name=0x10052a80c "__destruct", function_name_len=10, retval_ptr_ptr=0x0, param_count=0, arg1=0x0, arg2=0x0) at /Volumes/DEV/C/php-5.3.4/Zend/zend_interfaces.c:97
	#2  0x0000000100413a21 in zend_objects_destroy_object (object=0x100a1fea0, handle=1) at /Volumes/DEV/C/php-5.3.4/Zend/zend_objects.c:112
	#3  0x0000000100419167 in zend_objects_store_del_ref_by_handle_ex (handle=1, handlers=0x1007d1ac0) at /Volumes/DEV/C/php-5.3.4/Zend/zend_objects_API.c:206
	#4  0x0000000100418fa8 in zend_objects_store_del_ref (zobject=0x100a1e0c8) at /Volumes/DEV/C/php-5.3.4/Zend/zend_objects_API.c:172
	#5  0x00000001003e6475 in _zval_dtor_func (zvalue=0x100a1e0c8, __zend_filename=0x10052ba18 "/Volumes/DEV/C/php-5.3.4/Zend/zend_execute_API.c", __zend_lineno=443) at /Volumes/DEV/C/php-5.3.4/Zend/zend_variables.c:52
	#6  0x00000001003d4e19 in _zval_dtor (zvalue=0x100a1e0c8, __zend_filename=0x10052ba18 "/Volumes/DEV/C/php-5.3.4/Zend/zend_execute_API.c", __zend_lineno=443) at zend_variables.h:35
	#7  0x00000001003d51bf in _zval_ptr_dtor (zval_ptr=0x100a20060, __zend_filename=0x10052c1c8 "/Volumes/DEV/C/php-5.3.4/Zend/zend_variables.c", __zend_lineno=189) at /Volumes/DEV/C/php-5.3.4/Zend/zend_execute_API.c:443
	#8  0x00000001003e6932 in _zval_ptr_dtor_wrapper (zval_ptr=0x100a20060) at /Volumes/DEV/C/php-5.3.4/Zend/zend_variables.c:189
	#9  0x00000001003fa791 in zend_hash_apply_deleter (ht=0x10082d9c8, p=0x100a20048) at /Volumes/DEV/C/php-5.3.4/Zend/zend_hash.c:614
	#10 0x00000001003fade6 in zend_hash_reverse_apply (ht=0x10082d9c8, apply_func=0x1003d472f <zval_call_destructor>) at /Volumes/DEV/C/php-5.3.4/Zend/zend_hash.c:763
	#11 0x00000001003d47e9 in shutdown_destructors () at /Volumes/DEV/C/php-5.3.4/Zend/zend_execute_API.c:226
	#12 0x00000001003e8438 in zend_call_destructors () at /Volumes/DEV/C/php-5.3.4/Zend/zend.c:874
	#13 0x0000000100366c44 in php_request_shutdown (dummy=0x0) at /Volumes/DEV/C/php-5.3.4/main/main.c:1587
	#14 0x00000001004d554f in main (argc=2, argv=0x7fff5fbffa30) at /Volumes/DEV/C/php-5.3.4/sapi/cli/php_cli.c:1374

**__destruct**方法存在与否是在**zend_objects_destroy_object**函数中进行判断的。
在zend_objects_destroy_object中，与**__construct**一样，Zend VM判断zend_object->ce->destructor是否为空，如果不为空，则调用**zend_call_method**执行**__destruct**析构方法。
进入**__destruct**的方式与**__construct**不同的是，**__destruct**的执行方式是由Zend VM直接调用**zend_call_function**来执行。

