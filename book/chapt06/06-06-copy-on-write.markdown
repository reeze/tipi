# 第六节 写时复制（Copy-On-Write）

写时复制（[Copy-on-Write](http://en.wikipedia.org/wiki/Copy-on-write)，也缩写为COW），顾名思义，就是在写入时才真正复制一份内存进行修改。
COW最早应用在*nix系统中对线程与内存使用的优化，后面广泛的被使用在各种编程语言中，如C++的STL等。
在PHP内核中，COW也是主要的内存优化手段。
在前面关于变量和内存的讨论中，引用计数对变量的销毁与回收中起着至关重要的标识作用。
引用计数存在的意义，就是为了使得COW可以正常运作，从而实现对内存的优化使用。

## 写时复制的作用
经过上面的描述，大家可能会COW有了个主观的印象，下面让我们看一个小例子，
非常容易看到COW在内存使用优化方面的明显作用：

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

	?>
	//-----执行结果-----
	$ php t.php 
	int(630904)
	int(10479840)
	int(10479944)
	int(10480040)

上面的代码比较典型的突出了COW的作用，在一个数组变量$tipi被赋值给$tipi_copy时，
内存的使用并没有立刻增加一半，甚至在循环遍历数 $tipi_copy时，
实际上遍历的，仍是$tipi指向的同一块内存。

也就是说，即使我们不使用引用，一个变量被赋值后，只要我们不改变变量的值 ，也与使用引用一样。
进一步讲，就算变量的值立刻被改变，新值的内存分配也会洽如其分。
据此我们很容易就可以想到一些COW可以非常有效的控制内存使用的场景，
如函数参数的传递，大数组的复制等等。

在这个例子中，如果$tipi_copy的值发生了变化，$tipi的值是不应该发生变化的，
那么，此时PHP内核又会如何去做呢？我们引入下面的示例：

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

	?>
	//-----执行结果-----
	$ php t.php 
	int(629384)
	tipi: (refcount=2, is_ref=0)=array (0 => (refcount=1, is_ref=0)='php-internal', 1 => (refcount=1, is_ref=0)='php-internal', 2 => (refcount=1, is_ref=0)='php-internal')
	copy: (refcount=2, is_ref=0)=array (0 => (refcount=1, is_ref=0)='php-internal', 1 => (refcount=1, is_ref=0)='php-internal', 2 => (refcount=1, is_ref=0)='php-internal')
	int(629512)
	tipi: (refcount=1, is_ref=0)=array (0 => (refcount=1, is_ref=0)='php-internal', 1 => (refcount=2, is_ref=0)='php-internal', 2 => (refcount=2, is_ref=0)='php-internal')
	copy: (refcount=1, is_ref=0)=array (0 => (refcount=1, is_ref=0)='php-internal', 1 => (refcount=2, is_ref=0)='php-internal', 2 => (refcount=2, is_ref=0)='php-internal')
	int(630088)

从上面例子我们可以看出，当一个数组整个被赋给一个变量时，只是将内存将内存地址赋值给变量。
当数组的值被改变时，Zend内核重新申请了一块内存，然后赋之以新值，但不影响其他值的内存状态。
写时复制的最小粒度，就是zval结构体，
而对于zval结构体组成的集合（如数组和对象等），在需要复制内存时，将复杂对象分解为最小粒度来处理。
这样做就使内存中复杂对象中某一部分做修改时，不必将该对象的所有元素全部“分离”出一份内存拷贝，
从而节省了内存的使用。


##写时复制的实现
由于内存块没有办法标识自己被几个指针同时使用，
仅仅通过内存本身并没有办法知道什么时候应该进行复制工作，
这样就需要一个变量来标识这块内存是“被多少个变量名指针同时指向的”，
这个变量，就是前面关于变量的章节提到的：引用计数。

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
	
经过前文对变量的章节，我们可以理解当$foo被赋值时，$foo变量的引用计数为1。
当$foo的值被赋给$bar时，PHP并没有将内存直接复制一份交给$bar，
而是直接把$foo和$bar指向同一个地址。这时，我们可以看到refcount=2;
最后，我们更改了$bar的值，这时如果两个变量再指向同一个内存地址的话，
其值就会同时改变，于是，PHP内核这时将内存复制出来一份，并将其值写为2
，（这个操作也称为分离操作），
同时维护原$foo变量的引用计数：refcount=1。

>**NOTE**
>上面小例子中的xdebug_debug_zval()是xdebug扩展中的一个函数，用于输出变量在zend内部的引用信息。
>如果你没有安装xdebug扩展，也可以使用debug_zval_dump()来代替。
>参考：http://www.php.net/manual/zh/function.debug-zval-dump.php

写时复制应用的场景很多，最常见是赋值和函数传参。
在上面的例子中，就使用了zend_assign_to_variable()函数（**Zend/zend_execute.c**）
对变量的赋值进行了各种判断和处理。
其中最终处理代码如下：

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

从这段代码可以看出，如果要进行操作的值已经是引用类型（如已经被**&**操作符操作过）,
则直接重新分配内存，否则只是将value的地址赋与变量，同时将值的zval_value.refcount进行加1操作。

如果大家看过前面的章节，
应该对变量存储的结构体zval（Zend/zend.h）还有印象：

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

PHP对值的写时复制的操作，主要依赖于两个参数：**refcount__gc**与**is_ref__gc**。
如果是引用类型，则直接进行“分离”操作，即时分配内存，
否则会写时复制，也就是在修改其值的时候才进行内存的重新分配。

>**NOTE**
>写时复制的规则比较繁琐，什么情况会导致写时复制及分离，是有非常多种情况的。
>在这里只是举一个简单的例子帮助大家理解，后续会在附录中列举PHP中所有写时复制的相关规则。

## 写时复制的矛盾，PHP中不推荐使用**&**操作符的部分解释
上面是一个比较典型的例子，但现实中的PHP实现经过各种权衡，
甚至有时对一个特性的支持与否，是互相矛盾且难以取舍的。
比如，unset()看上去是用来把变量释放，然后把内存标记于空闲的。
可是，在下面的例子中，unset并没有使内存池的内存增加：

	[php]
	<?php
	$tipi = 10;
	$o_o  = &$tipi;
	unset($o_o);
	echo $tipi;
	?>

理论上$o_o是$tipi的引用，这两者应该指向同一块内存，其中一个被标识为回收，
另一个也应该被回收才是。但这是不可能的，因为内存本身并不知道都有哪些指针
指向了自已。在C中，o_o这时的值应该是无法预料的，
但PHP不想把这种维护变量引用的工作交给用户，于是，
使用了折中的方法，unset()此时只会把tipi变量名从hashtable中去掉，
而内存值的引用计数减1。实际的内存使用完全没有变化。

试想，如果$tipi是一个非常大的数组，或者是一个资源型的变量。
这种情形绝对是我们不想看到的。

上面这个例子我们还可以理解，如果每个这种类似操作都要用户来关心。
那PHP就是变换了语法的C了。而下面的这个例子，与其说是语言特性，
倒不如说是更像BUG多一些。（事实上对此在PHP官方的邮件组里有也争论）

	[php]
	<?php
	$foo ['love'] = 1;
	$bar  = &$foo['love'];
	$tipi = $foo;
	$tipi['love'] = '2';
	echo $foo['love'];
	?>
	
这个例子最后会输出 2 ， 大家会非常惊讶于$tipi怎么会影响到$foo, 
这完全是两个不同的变量么！至少我们希望是这样。

>**NOTE**
>关于这个例子的原理，相信大家仔细推敲一下，不难找到答案。
>我们也欢迎大家在下方留言，或者进行讨论。

最后，不推荐大家使用 **&** ，让PHP自己决定什么时候该使用引用好了，
除非你知道自己在做什么。








