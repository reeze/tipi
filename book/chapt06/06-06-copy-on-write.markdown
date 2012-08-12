# 第六节 写时复制（Copy On Write）

写时复制（[Copy on Write](http://en.wikipedia.org/wiki/Copy-on-write)，也缩写为COW)的应用场景非常多，
比如Linux中对进程复制中内存使用的优化，在各种编程语言中，如C++的STL等等中均有类似的应用。
COW是常用的优化手段，可以归类于：资源延迟分配。只有在真正需要使用资源时才占用资源，
这样做的目的通常能减少资源的占用。

不过这也不是绝对的，资源延时分配并不适用于所有的情况，需要根据具体的应用场景来使用，
比如在某些场景下资源申请会很频繁，同时申请资源本身也会消耗大量的资源，这是可能需要进行资源预分配，
比如很多语言通常都会自己实现内存管理层，通过预申请一个资源池来管理资源。
内存的分配都通过这个抽象层来进行，因为通常要使用内存需要向操作系统进行申请，
而申请内存的操作是[系统调用](http://zh.wikipedia.org/wiki/%E7%B3%BB%E7%BB%9F%E8%B0%83%E7%94%A8)，
系统调用代价非常大。所以预申请内存以减少系统调用的代价成本。

综合来讲需要权衡：资源的分配成本及频度。需要综合各个环节的成本，来达到总体最优。

在PHP内核中，COW是很常用的内存优化手段。在前面关于变量和内存的讨论中，
引用计数对变量的销毁与回收中起着至关重要的作用。同时PHP的COW也是基于引用计数来实现的。

## 写时复制的作用

PHP中的写时复制可以简单描述为：如果通过赋值的方式赋值给变量时不会申请新内存来存放
新变量所保存的值，而是简单的通过一个计数器来共用内存，只有在其中的一个引用指向变量的
值发生变化时才申请新空间来保存值内容以减少对内存的占用。

经过上面的描述，大家可能会COW有了个大概的印象，下面让我们看一个小例子，
可以容易看到COW在内存使用优化方面的明显作用：

	[php]
	<?php
	$j = 1;
    var_dump(memory_get_usage());

    $tipi = array_fill(0, 100000, 'php-internal');
    var_dump(memory_get_usage());

    $tipi_copy = $tipi;
    var_dump(memory_get_usage());

    foreach($tipi_copy as $i){
        $j += count($i); 
    }
    var_dump(memory_get_usage());

	//-----执行结果-----
	$ php t.php 
	int(630904)
	int(10479840)
	int(10479944)
	int(10480040)

上面的代码比较典型的突出了COW的作用，在数组变量`$tipi`被赋值给`$tipi_copy`时，
内存的使用并没有立刻增加一半，在循环遍历数`$tipi_copy`时也没有发生显著变化，
在这里`$tipi_copy`和`$tipi`变量的数据共同指向同一块内存，而没有复制。

也就是说，即使我们不使用引用，一个变量被赋值后，只要我们不改变变量的值 ，也不会新申请内存用来存放数据。
据此我们很容易就可以想到一些COW可以非常有效的控制内存使用的场景，
很多场景下我们通常只是使用变量进行计算而很少对其进行修改操作，如函数参数的传递，大数组的复制等。
这样就能节省内存。

在这个例子中，如果`$tipi_copy`的值发生了变化，`$tipi`的值是不应该发生变化的，
那么，此时PHP内核又会如何去做呢？再看看下面的示例：

	[php]
	<?php
    //$tipi = array_fill(0, 3, 'php-internal');  
    //这里不再使用array_fill来填充 ，为什么？
    $tipi[0] = 'php-internal';
    $tipi[1] = 'php-internal';
    $tipi[2] = 'php-internal';
    var_dump(memory_get_usage());

    $copy = $tipi;
    xdebug_debug_zval('tipi', 'copy');
    var_dump(memory_get_usage());

    $copy[0] = 'php-internal';
    xdebug_debug_zval('tipi', 'copy');
    var_dump(memory_get_usage());

	//-----执行结果-----
	$ php t.php 
	int(629384)
	tipi: (refcount=2, is_ref=0)=array (0 => (refcount=1, is_ref=0)='php-internal', 1 => (refcount=1, is_ref=0)='php-internal', 2 => (refcount=1, is_ref=0)='php-internal')
	copy: (refcount=2, is_ref=0)=array (0 => (refcount=1, is_ref=0)='php-internal', 1 => (refcount=1, is_ref=0)='php-internal', 2 => (refcount=1, is_ref=0)='php-internal')
	int(629512)
	tipi: (refcount=1, is_ref=0)=array (0 => (refcount=1, is_ref=0)='php-internal', 1 => (refcount=2, is_ref=0)='php-internal', 2 => (refcount=2, is_ref=0)='php-internal')
	copy: (refcount=1, is_ref=0)=array (0 => (refcount=1, is_ref=0)='php-internal', 1 => (refcount=2, is_ref=0)='php-internal', 2 => (refcount=2, is_ref=0)='php-internal')
	int(630088)

从上面例子我们可以看出，当一个数组整个被赋给一个变量时，不会申请同样大小的内存来保存内容，
当数组的值被改变时，Zend内核才重新申请内存，然后赋之以新值，但不影响其他值。
写时复制的最小粒度，是zval结构体，而对于zval结构体组成的集合（如数组和对象等），
在需要复制内存时，将复杂对象分解为最小粒度来处理。这样做就使内存中复杂对象中某一部分做修改时，
不必将该对象的所有元素全部“分离”出一份内存拷贝，所以上例中的1，2索引处的元素引用数量为2，
也就是并没有将整个数组进行复制操作，只对修改的部分复制，从而节省了内存的使用。

>**NOTE**
>前面实例中提到$tipi变量的值没有使用array_fill()来创建数组的原因是: array_fill()
>出于对内存的占用考虑，也采用了COW的策略，可能会影响对本例的演示，感兴趣的读者可以
>阅读：$PHP_SRC/ext/standard/array.c中PHP_FUNCTION(array_fill)的实现。

## 写时复制的实现

写时复制通常基于引用计数计数实现，多个引用指向同一快内存，在需要修改内存的时候如果发现
内存有多个指向则申请内存保存修改后的数据，引用计数的作用则用于标示该内存是否是共享的。

这里有一个比较典型的例子：
	
	[php]
	<?php
		$foo = 1;
		xdebug_debug_zval('foo');
		$bar = $foo;
		xdebug_debug_zval('foo');
		$bar = 2;
		xdebug_debug_zval('foo');
	?>
	//-----执行结果-----
	foo: (refcount=1, is_ref=0)=1
	foo: (refcount=2, is_ref=0)=1
	foo: (refcount=1, is_ref=0)=1
	
经过前面对变量章节的介绍，我们知道当$foo被赋值时，$foo变量的值的只由$foo变量指向。
当$foo的值被赋给$bar时，PHP并没有将内存复制一份交给$bar，而是把$foo和$bar指向同一个地址。
同时引用计数增加1，也就是新的2。
随后，我们更改了$bar的值，这时如果直接需该$bar变量指向的内存，则$foo的值也会跟着改变。
这不是我们想要的结果。于是，PHP内核将内存复制出来一份，并将其值更新为赋值的：2（这个操作也称为变量分离操作），
同时原$foo变量指向的内存只有$foo指向，所以引用计数更新为：refcount=1。

>**NOTE**
>上面小例子中的xdebug_debug_zval()是xdebug扩展中的一个函数，用于输出变量在zend内部的引用信息。
>如果你没有安装xdebug扩展，也可以使用debug_zval_dump()来代替。
>参考：<http://www.php.net/manual/zh/function.debug-zval-dump.php>

在很多场景下PHP都会进行写时赋值操作。比如：变量的多次赋值、函数参数传递，并在函数体内修改实参等。
以在执行赋值中需要进的操作：zend_assign_to_variable()函数（**Zend/zend_execute.c**）的关键代码段为例：

	[c]
    if (PZVAL_IS_REF(value) && Z_REFCOUNT_P(value) > 0) {
        ALLOC_ZVAL(variable_ptr);
        *variable_ptr_ptr = variable_ptr;
        *variable_ptr = *value;
        Z_SET_REFCOUNT_P(variable_ptr, 1);
        zval_copy_ctor(variable_ptr);
    } else {
        *variable_ptr_ptr = value;
        Z_ADDREF_P(value);
    }

从这段代码可以看出，如果要进行操作的值已经是引用类型，并且有变量指向这个值，
则重新分配内存，同时对原有值不影响，否则只是将value的地址赋与变量，同时将值的zval_value.refcount加1。
也就是标记这个值又有新的变量指向这个值，避免了内存复制。

为了便于理解，再展示下PHP中的变量存储结构体zval（Zend/zend.h）的定义：

	[c]
	typedef struct _zval_struct zval;
	...
	struct _zval_struct {
		/* Variable information */
		zvalue_value value;     /* value */
		zend_uint refcount__gc;
		zend_uchar type;    /* active type */
		zend_uchar is_ref__gc;
	};

PHP对值的写时复制的操作及垃圾收集机制均依赖于：**refcount__gc**与**is_ref__gc**字段。

>**NOTE**
>写时复制的规则比较繁琐，什么情况会导致写时复制及分离，是有非常多种情况的。
>在这里只是举一个简单的例子帮助大家理解，后续会在附录中列举PHP中所有写时复制的相关规则。

## 慎用引用**&**
引用和前面提到的变量的引用计数和PHP中的引用并不是同一个东西，
引用和C语言中的指针的类似，他们都可以通过不同的标示访问到同样的内容，
但是PHP的引用则只是简单的变量别名，没有C指令的灵活性和限制。

在PHP使用引用也需要对引用的行为有明确的认识才不至于误用，
避免带来一些比较难以理解的的Bug。

上面这个例子我们还可以理解，如果每个这种类似操作都要用户来关心。
那PHP就是变换了语法的C了。而下面的这个例子，与其说是语言特性，
倒不如说是更像BUG多一些。（事实上对此在PHP官方的邮件组里有也争论）

>**NOTE**
>PHP中有非常多让人觉得意外的行为，有些因为历史原因，不能破坏兼容性而选择
>暂时不修复，或者有的使用场景比较少。在PHP中只能尽量的避开这些陷阱。
>例如下面这个例子。

	[php]
	<?php
	$foo['love'] = 1;
	$bar  = &$foo['love'];
	$tipi = $foo;
	$tipi['love'] = '2';
	echo $foo['love'];
	
这个例子最后会输出 2 ， 大家会非常惊讶于$tipi怎么会影响到$foo, 
`$bar`变量的引用操作，将$foo['love']变成了引用。对`$tipi['love']`的修改
没有产生copy on write。
