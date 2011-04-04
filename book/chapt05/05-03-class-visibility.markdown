# 第三节 访问控制的实现

面向对象的三大特性(封装,继承,多态)，其中封装是一个非常重要的特性。封装隐藏了对象内部的细节和实现，
使对象能够集中而完整的描述并对应一个具体的事物，
只提供对外的访问接口，这样可以在不改变接口的前提下改变实现细节，而且能使对象自我完备。
除此之外，封装还可以增强安全性和简化编程.
在面向对象的语言中一般是通过访问控制来实现封装的特性。
PHP提供了public、protected 或 private三个关键字来实现访问控制。

假如要隐藏类的内部数据，可以使用private关键字将成员变量声明为私有成员变量。
要使用一个成员变量或方法仅能被当前类和其所在类的子类访问，可以使用protected关键字将它们声明为保护类型。
如果要对外提供接口，可以使用public关键字将其声明公有成员，从而在任何地方都可以被访问。
类中的成员方法和成员变量需要使用关键字public、protected 或 private 进行定义。

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

这三个访问控制并不是并列的关系，它们之间存在一个优先级的高低问题: public < protected < private.
而这三个常量是在语法解析时，就已经赋值给相关的变量。
在前面有提到当我们没有给成员方法或成员变量设置访问控制时，其默认值为public。
与常规的访问控制实现一样，它也是在语法解析阶段执行的。

    [c]
    method_modifiers:
            /* empty */
            { Z_LVAL($$.u.constant) = ZEND_ACC_PUBLIC; }
        |	non_empty_member_modifiers			{ $$ = $1;  
            if (!(Z_LVAL($$.u.constant) & ZEND_ACC_PPP_MASK))
            { Z_LVAL($$.u.constant) |= ZEND_ACC_PUBLIC; } }
    ;

虽然是在语法解析时就已经设置了访问控制，但其最终还是要存储在相关结构中。
在上面的语法解析过程中，访问控制已经存储在某个变量中，在遇到具体的成员方法或成员变量声明时，
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

这个还是上一小节中我们说明静态成员方法的示例，只是，这里我们将其访问控制从public变成了private.
当我们在PHP环境中运行这段代码时，程序会报错输出：Fatal error: Call to private method Tipi::t() from context '' in...

根据前一节的内容我们知道，如果要执行一个静态成员变量需要先获得类，再获得类的方法，最后执行访方法。
而是否有访问权限的检测过程的实现过程在获取类的方法过程中，即在zend_std_get_static_method函数中。
此函数在获取了类的方法后，会执行访问控制的检查过程。

    [c]
    if (fbc->op_array.fn_flags & ZEND_ACC_PUBLIC) {
		//公有方法，可以返回
	} else if (fbc->op_array.fn_flags & ZEND_ACC_PRIVATE) {
        //  私有方法，报错
	} else if ((fbc->common.fn_flags & ZEND_ACC_PROTECTED)) {
        //  保护方法，报错
	}

对于成员函数来说，其对于访问控制存储在函数结构体中的fn_flags字段中，
不管是函数本身的common结构体中的fn_flags，还是函数包含所有中代码代码集合op_array中的fn_flags.
