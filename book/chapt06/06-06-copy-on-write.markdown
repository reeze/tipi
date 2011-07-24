# 第六节 写时复制（Copy-On-Write）

写时复制（[Copy-on-Write](http://en.wikipedia.org/wiki/Copy-on-write)，也缩写为COW），顾名思义，就是在写入时才真正复制一份内存进行修改。
COW最早应用在*nix系统中对线程与内存使用的优化，后面广泛的被使用在各种编程语言中，如C++的STL等。
在PHP内核中，COW也是主要的内存优化手段。
在前面关于变量和内存的讨论中，引用计数对变量的销毁与回收中起着至关重要的标识作用。
引用计数存在的意义，就是为了使得COW可以正常运作，从而实现对内存的优化使用。

## 写时复制的作用
这里有一个非常典型的例子：
	
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
而是直接把$foo和$bar指向同一个地址。

由于内存块没有办法标识自己被几个指针同时使用，
就需要一个变量来标识这块内存是“被两个变量名指针同时指向的”，
结果引用计数就派上了用场，我们可以看到refcount=2;
最后，我们更改了$bar的值，这时如果两个变量再指向同一个内存地址的话，
其值就会同时改变，于是，PHP内核这时将内存复制出来一份，并将其值写为2
，（这个操作也会称为分离操作），
同时维护原$foo变量的引用计数：refcount=1。

从这个小例子可以看出，COW的作用是非常明显的，如果一个变量被赋值后，
根本没有进行修改，使用COW后就可以节省这部分内存。
即使变量的值立刻被改变，新值的内存分配也会洽如其分。

>**NOTE**
>上面小例子中的xdebug_debug_zval()是xdebug扩展中的一个函数，用于输出变量在zend内部的引用信息。
>如果你没有安装xdebug扩展，也可以使用debug_zval_dump()来代替。
>参考：http://www.php.net/manual/zh/function.debug-zval-dump.php

##写时复制的实现
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








