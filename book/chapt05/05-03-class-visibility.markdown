# 第三节 访问控制的实现

面向对象的三大特性(封装、继承、多态)，其中封装是一个非常重要的特性。封装隐藏了对象内部的细节和实现，
使对象能够集中而完整的描述并对应一个具体的事物，
只提供对外的访问接口，这样可以在不改变接口的前提下改变实现细节，而且能使对象自我完备。
除此之外，封装还可以增强安全性和简化编程。
在面向对象的语言中一般是通过访问控制来实现封装的特性。
PHP提供了public、protected及private三个层次访问控制。这和其他面向对象的语言中对应的关键字语义一样。
这几个关键字都用于修饰类的成员:

* private 用于禁止除类本身以外(包括继承也属于非类本身)对成员的访问，用于隐藏类的内部数据和实现。
* protectd 用于禁止除本类以及继承该类的类以外的任何访问。同样用于封装类的实现，同时给予类一定的扩展能力，
  因为子类还是可以访问到这些成员。
* public 最好理解，被public修饰的成员可以被任意的访问。

>**NOTE**
>如果没有设置访问控制关键字，则类的成员方法和成员变量会被设置成默认的 public。

这三个关键字在语法解析时分别对应三种访问控制的标记：

    [c]
    member_modifier:
		T_PUBLIC				{ Z_LVAL($$.u.constant) = ZEND_ACC_PUBLIC; }
	|	T_PROTECTED				{ Z_LVAL($$.u.constant) = ZEND_ACC_PROTECTED; }
	|	T_PRIVATE				{ Z_LVAL($$.u.constant) = ZEND_ACC_PRIVATE; }


这三种访问控制的标记是PHP内核中定义的三个常量，在Zend/zend_complic.h中，其定义如下：

    [c]
    #define ZEND_ACC_PUBLIC		0x100
    #define ZEND_ACC_PROTECTED	0x200
    #define ZEND_ACC_PRIVATE	0x400
    #define ZEND_ACC_PPP_MASK  (ZEND_ACC_PUBLIC | ZEND_ACC_PROTECTED | ZEND_ACC_PRIVATE)

>**NOTE**
>我们经常使用16进制的数字表标示状态，例如上面的访问控制常量，
>0x100使用二进制表示就为 0001 0000 0000
>0x200为0010 0000 0000
>0x400为0100 0000 0000
>我们通过二进制的某个位来表示特定的意义，至于为什么ZEND_ACC_PUBLIC这几个常量后面多两个0，
>这是因为0x01和0x10已经被占用了，使用和其他不同意义的常量值不一样的值可以避免误用。
>通过简单的二进制&即可的除某个数值是否表示特定的意义，例如:某个常量为0011 0000 0000，这个数值和 0001 0000 0000 做&，
>如果结果为0则说明这个位上的值不为1，在上面的例子中就是这个访问控制不具有public的级别。
>当然PHP中不允许使用多个访问控制修饰符修饰同一个成员。这种处理方式在很多语言中都很常见。

在前面有提到当我们没有给成员方法或成员变量设置访问控制时，其默认值为public。
与常规的访问控制实现一样，也是在语法解析阶段进行的。

    [c]
    method_modifiers:
            /* empty */
            { Z_LVAL($$.u.constant) = ZEND_ACC_PUBLIC; }
        |	non_empty_member_modifiers			{ $$ = $1;
            if (!(Z_LVAL($$.u.constant) & ZEND_ACC_PPP_MASK))
            { Z_LVAL($$.u.constant) |= ZEND_ACC_PUBLIC; } }
    ;

虽然是在语法解析时就已经设置了访问控制，但其最终还是要存储在相关结构中。
在上面的语法解析过程中，访问控制已经存储在编译节点中，在编译具体的类成员时会传递给相关的结构。
此变量会作为一个参数传递给生成中间代码的函数。如在解析成员方法时，PHP内核是通过调用zend_do_begin_function_declaration
函数实现，此函数的第五个参数表示访问控制，在具体的代码中，

    [c]
    // ...省略
    fn_flags = Z_LVAL(fn_flags_znode->u.constant);
    // ... 省略

    op_array.fn_flags |= fn_flags;
    //  ...省略

如此，就将访问控制的相关参数传递给了将要执行的中间代码。
假如我们先现在有下面一段代码：

    [php]
    class Tipi{
        private static function t() {
            echo 1;
        }
    }

    Tipi::t();

这个还是上一小节中我们说明静态成员方法的示例，只是，这里我们将其访问控制从public变成了private。
执行这段代码会报错：Fatal error: Call to private method Tipi::t() from context '' in...

根据前一节的内容我们知道，如果要执行一个静态成员变量需要先获得类，再获得类的方法，最后执行访方法。
而是否有访问权限的检测的实现过程在获取类的方法过程中，即在zend_std_get_static_method函数中。
此函数在获取了类的方法后，会执行访问控制的检查过程。

    [c]
    if (fbc->op_array.fn_flags & ZEND_ACC_PUBLIC) {
		//公有方法，可以访问
	} else if (fbc->op_array.fn_flags & ZEND_ACC_PRIVATE) {
        //  私有方法，报错
	} else if ((fbc->common.fn_flags & ZEND_ACC_PROTECTED)) {
        //  保护方法，报错
	}

>**NOTE**
>见前面有关访问控制常量的讨论，这是使用的是 fbc->op_array.fn_flags & ZEND_ACC_PUBLIC 而不是使用==来判断访问控制类型，
>通过这种方式，op_array.fn_flags中可以保存不止访问控制的信息，所以flag使用的是复数。

对于成员函数来说，其对于访问控制存储在函数结构体中的fn_flags字段中，
不管是函数本身的common结构体中的fn_flags，还是函数包含所有中间代码的代码集合op_array中的fn_flags。

## 访问控制的小漏洞
先看一个小例子吧：

	[php]
	<?php

	class A {
		private $money = 10000;
		public function doSth($anotherA) {
			$anotherA->money = 10000000000;
		}

		public function getMoney() {
			return $this->money;
		}
	}

	$b = new A();
	echo $b->getMoney(); // 10000

	$a = new A();
	$a->doSth($b);
	echo $b->getMoney(); // 10000000000;

在$a变量的doSth()方法中我们直接修改了$b变量的私有成员money，当然我们不太可能这样写代码，从封装的角度来看，
这也是不应该的行为，从PHP实现的角度来看，这并不是一个功能，在其他语言中并不是这样表现的。这也是PHP面向对象不纯粹的表现之一。

下面我们从实现上面来看看是什么造就了这样的行为。以下函数为验证某个属性能否被访问的验证方法：

	[php]
	static int zend_verify_property_access(zend_property_info *property_info, zend_class_entry *ce TSRMLS_DC) /* {{{ */
	{
		switch (property_info->flags & ZEND_ACC_PPP_MASK) {
			case ZEND_ACC_PUBLIC:
				return 1;
			case ZEND_ACC_PROTECTED:
				return zend_check_protected(property_info->ce, EG(scope));
			case ZEND_ACC_PRIVATE:
				if ((ce==EG(scope) || property_info->ce == EG(scope)) && EG(scope)) {
					return 1;
				} else {
					return 0;
				}
				break;
		}
		return 0;
	}

在doSth()方法中，我们要访问$b对象的属性money，这是Zend引擎检查我们能否访问$b对象的这个属性，
这是Zend赢取获取$b对象的类，以及要访问的属性信息，首先要看看这个属性是否为public，公开的话直接访问就好了。
如果是protected的则继续调用zend_check_protected()函数检查，因为涉及到该类的父类，这里不继续跟这个函数了，
看看是private的情况下是什么情况，在函数doSth()执行的时候，这时的EG(scope)指向的正是类A，ce变量值得就是变量$b的类，
而$b的类就是类A，这样检查就判断成功返回1，也就表示可以访问。

至于成员函数的检查规则类似，就留给读者自己去探索了。
