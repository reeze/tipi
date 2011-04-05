# 第四节 类的继承, 多态及抽象类

面向对象的三大特性(封装,继承,多态)，在前一小节介绍了封装，这一小节我们将介绍继承和多态的实现。

## 继承

继承是一种关联类的层次模型，它可以建立类之间的关系，并实现代码重用，方便系统扩展。
继承提供了一种明确表述共性的方法。继承是一个新类从现有的类中派生的过程。
继承产生的新类继承了原始类的特性，新类称为原始类的派生类（或子类），
而原始类称为新类的基类（或父类）。派生类可以从基类那里继承方法和变量，
并且新类可以重载或增加新的方法，使之满足自己的定制化的需要。

在PHP的语法中，类定义的时候可以使用extends关键字来实现继承，一个类只能继承一个父类。
被继承的成员方法和成员变量可以使用同名的方法或变量重写，如果需要访问父类的成员方法或变量可以
使用parent关键字。

PHP内核将类的继承操作实现放在了"编译阶段"，因此，当我们使用VLD生成中间代码时会发现没有看到
关于继承的相关信息。通过对extends关键字的词法分析和语法分析，在Zend/zend_complie.c文件中
找到继承实现的编译函数zend_do_inheritance().其调用顺序如下:    
[zend_do_early_binding] --> [do_bind_inherited_class()] --> [zend_do_inheritance()]

    [c]
    ZEND_API void zend_do_inheritance(zend_class_entry *ce, zend_class_entry *parent_ce TSRMLS_DC)
    {
        //  ...省略  报错处理 接口不能从类继承，final类不能继承

        //  ...省略 序列化函数和反序列化函数 如果当前类没有，则取父类的

        /* Inherit interfaces */
        zend_do_inherit_interfaces(ce, parent_ce TSRMLS_CC);

        /* Inherit properties */
        zend_hash_merge(&ce->default_properties, &parent_ce->default_properties, (void (*)(void *)) zval_add_ref, NULL, sizeof(zval *), 0);
        if (parent_ce->type != ce->type) {
            /* User class extends internal class */
            zend_update_class_constants(parent_ce  TSRMLS_CC);
            zend_hash_apply_with_arguments(CE_STATIC_MEMBERS(parent_ce) TSRMLS_CC, (apply_func_args_t)inherit_static_prop, 1, &ce->default_static_members);
        } else {
            zend_hash_apply_with_arguments(&parent_ce->default_static_members TSRMLS_CC, (apply_func_args_t)inherit_static_prop, 1, &ce->default_static_members);
        }
        zend_hash_merge_ex(&ce->properties_info, &parent_ce->properties_info, (copy_ctor_func_t) (ce->type & ZEND_INTERNAL_CLASS ? zend_duplicate_property_info_internal : zend_duplicate_property_info), sizeof(zend_property_info), (merge_checker_func_t) do_inherit_property_access_check, ce);

        zend_hash_merge(&ce->constants_table, &parent_ce->constants_table, (void (*)(void *)) zval_add_ref, NULL, sizeof(zval *), 0);
        zend_hash_merge_ex(&ce->function_table, &parent_ce->function_table, (copy_ctor_func_t) do_inherit_method, sizeof(zend_function), (merge_checker_func_t) do_inherit_method_check, ce);
        do_inherit_parent_constructor(ce);

        if (ce->ce_flags & ZEND_ACC_IMPLICIT_ABSTRACT_CLASS && ce->type == ZEND_INTERNAL_CLASS) {
            ce->ce_flags |= ZEND_ACC_EXPLICIT_ABSTRACT_CLASS;
        } else if (!(ce->ce_flags & ZEND_ACC_IMPLEMENT_INTERFACES)) {
            /* The verification will be done in runtime by ZEND_VERIFY_ABSTRACT_CLASS */
            zend_verify_abstract_class(ce TSRMLS_CC);
        }
    }

整个继承的过程是以类结构为中心，当继承发生时，程序会先处理所有的接口。接口继承调用了zend_do_inherit_interfaces函数
此函数会遍历所有的接口列表，将接口写入到类结构的interfaces字段，并增加num_interfaces的计数统计。
在接口继承后，程序会合并类的成员变量、属性、常量、函数等，这些都是HashTable的merge操作。

在继承过程中，除了常规的函数合并后，还有魔法方法的合并，其调用的函数为do_inherit_parent_constructor(ce).
此函数实现魔术方法继承，如果子类中没有相关的魔术方法，则继承父类的对应方法。如下所示的PHP代码为子类没构造函数的情况

    [php]
    class Base {
        public function __construct() {
            echo 'Base __construct<br />';
        }
    }

    class Foo extends Base {

    }

    $foo = new Foo();

在PHP函数中运行，会输出：Base __construct

这显然继承了父类的构造方法，如果子类有自己的构造方法，并且需要调用父类的构造方法时
需要在子类的构造方法中调用父类的构造方法，PHP不会自动调用。

## 多态
多态是继数据抽象和继承后的第三个特性。顾名思义，多态即多种形态，相同方法调用实现不同的实现方式。
多态关注一个接口或基类，在编程时不必担心一个对象所属于的具体类。在面向对象的原则中
里氏代换原则（Liskov Substitution Principle, LSP）,依赖倒转原则（dependence inversion principle, DIP）等
都依赖于多态特性。而我们在平常工作中也会经常用到。

    [php]
    interface Animal {
        public function run();
    }

    class Dog implements Animal {
        public function run() {
            echo 'dog run';
        }
    }

    class  Cat implements Animal{
        public function run() {
            echo 'cat run';
        }
    }

    class Context {
        private $_animal;

        public function __construct(Animal $animal) {
            $this->_animal = $animal;
        }

        public function run() {
            $this->_animal->run();
        }
    }

    $dog = new Dog();
    $context = new Context($dog);
    $context->run();

    $cat = new Cat();
    $context = new Context();
    $context->run();

上面是策略模式示例性的简单实现。对于不同的动物，其跑的方式不一样，
当在环境中跑的时候，根据所传递进来的动物执行相对应的跑操作。
多态是一种编程的思想，但对于不同的语言，其实现也不同。
对于PHP的程序实现来说，关键点在于类型提示的实现。而类型提示是PHP5之后才有的特性。在此之前，
PHP本身就具有多态特性。

[<< 第三章 第五节 类型提示的实现 >>][type-hint-imp]已经说明了类型提示的实现，只是对于
对象的判断没有做深入的探讨。它已经指出对于类的类型提示实现函数为zend_verify_arg_type。
在此函数中，关于对象的关键代码如下：

    [c]
    if (Z_TYPE_P(arg) == IS_OBJECT) {
		need_msg = zend_verify_arg_class_kind(cur_arg_info, fetch_type, &class_name, &ce TSRMLS_CC);
		if (!ce || !instanceof_function(Z_OBJCE_P(arg), ce TSRMLS_CC)) {
			return zend_verify_arg_error(zf, arg_num, cur_arg_info, need_msg, class_name, "instance of ", Z_OBJCE_P(arg)->name TSRMLS_CC);
		}
	}

第一步，判断参数是否为对象，使用宏Z_TYPE_P，如果是转二步，否则跳到其它情况处理

第二步，获取类的类型验证信息，调用了zend_verify_arg_class_kind函数，此函数位于Zend/zend_execute.c文件中，
它会通过zend_fetch_class函数获取类信息，根据类的类型判断是否为接口，返回字符串"implement interface"或"be an instance of"

第三步，判断是否为指定类的实例，调用的函数是instanceof_function。此函数首先会遍历实例所在类的所有接口，
递归调用其本身，判断实例的接口是否为指定类的实例，如果是，则直接返回1，如果不是，在非仅接口的情况下，
循环遍历所有的父类，判断父类与指定的类是否相等，如果相等返回1，当函数执行完时仍没有找到，则返回0，表示不是类的实例。
instanceof_function函数的代码如下：

    [c]
    ZEND_API zend_bool instanceof_function_ex(const zend_class_entry *instance_ce, const zend_class_entry *ce, zend_bool interfaces_only TSRMLS_DC) /* {{{ */
    {
        zend_uint i;

        for (i=0; i<instance_ce->num_interfaces; i++) { //  递归遍历所有的接口
            if (instanceof_function(instance_ce->interfaces[i], ce TSRMLS_CC)) {
                return 1;
            }
        }
        if (!interfaces_only) {
            while (instance_ce) {   //  遍历所有的父类
                if (instance_ce == ce) {
                    return 1;
                }
                instance_ce = instance_ce->parent;
            }
        }

        return 0;
    }

第四步，如果不是指定类的实例，程序会调用zend_verify_arg_error报错，此函数最终会调用zend_error函数显示错误。


### 接口的实现
前面的PHP示例中有用到接口，而且在多态中，接口是一个不得不提的概念。接口是一些方法特征的集合，
是一种逻辑上的抽象，它没有方法的实现，因此这些方法可以在不同的地方被实现，可以有相同的名字而具有完全不同的行为。

而PHP内核对类和接口一视同仁，它们的内部结构一样。
这点在前面的类型提示实现中也有看到，不管是接口还是类，调用instanceof_function函数时传入的参数
和计算过程中使用的变量都是zend_class_entry类型。

[<< 第一节 类的结构和实现 >>][class-struct]中已经对于类的类型做了说明，在语法解析时，
PHP内核已经设置了其type=ZEND_ACC_INTERFACE,

    [c]
    interface_entry:
        T_INTERFACE		{ $$.u.opline_num = CG(zend_lineno);
                 $$.u.EA.type = ZEND_ACC_INTERFACE; }
    ;

而在声明类的函数zend_do_begin_class_declaration中，通过下列语句，将语法解析的类的类型赋值给类的ce_flags字段。

    [c]
    new_class_entry->ce_flags |= class_token->u.EA.type;

类结构的ce_flags字段的作用是标记类的类型。

接口与类除了在ce_flags字段不同外，在其它一些字段的表现上也不一样，如继承时，类只能继承一个父类，却可以实现多个接口。
二者在类的结构中存储在不同的字段，类的继承由于是一对一的关系，则每个类都有一个parent字段。
而接口实现是一个一对多的关系，每个类都会有一个二维指针存放接口的列表，还有一个存储接口数的字段num_interfaces.

接口也可以和类一样实现继承，并且只能是一个接口继承另一个接口。一个类可以实现多个接口，多个接口间以逗号隔开。
接口实现调用的是zend_do_implement_interface函数，
zend_do_implement_interface函数会合并接口中的常量列表和方法列表操作，这就是接口中不能有变量却可以有常量的实现原因。
在接口继承的过程中有对当前类的接口中是否存在同样接口的判断操作，如果已经存在了同样的接口，则此接口继承将不会执行。


## 抽象类

抽象类是相对于具体类来说的，抽象类仅提供一个类的部分实现。抽象类可以有实例变量，构造方法等。
抽象类可以同时拥有抽象方法和具体方法。一般来说，抽象类代表一个抽象的概念，它提供了一个继承的
出发点，理想情况下，所有的类都需要从抽象类继承而来。而具体类则不同，具体类可以实例化。
由于抽象类不可以实例化，因此所有抽象类应该都是作为继承的父类的。

在PHP中，抽象类的标志是abstract关键字。当一个类声明为abstract时，表示这是一个抽象类，
或者没有声明为abstract,但是在类中存在抽象方法。对于这两种情况，PHP内核作了区分，
其区分的字段是ce_flags,二者对应的值为ZEND_ACC_EXPLICIT_ABSTRACT_CLASS和ZEND_ACC_IMPLICIT_ABSTRACT_CLASS，
这两个值在前面的第一节已经做了介绍。

标记类为抽象类或标记成员方法为抽象方法的确认阶段是语法解析阶段。标记为抽象类与标记为接口等的过程一样。
而通过标记成员方法为抽象方法来确认一个类为抽象类则是在声明函数时实现的。从第四章中我们知道编译时声明函数是使用的
zend_do_begin_function_declaration函数。在此函数中有如下代码：

    [c]
    if (fn_flags & ZEND_ACC_ABSTRACT) {
			CG(active_class_entry)->ce_flags |= ZEND_ACC_IMPLICIT_ABSTRACT_CLASS;
		}
    }

若函数为抽象函数，则设置类的ce_flags为ZEND_ACC_IMPLICIT_ABSTRACT_CLASS，从而将这个类设置为抽象类。

不管是类的继承，还是多态，又或者接口，抽象类，这些特性都是围绕类的结构实现的。如果要真正理解这些特性，
需要更多的关注类的结构，基础往往很重要，而在程序员，数据结构就是程序的基础。

[type-hint-imp]: 		?p=chapt03/03-05-impl-of-type-hint
[class-struct]:         ?p=chapt05/05-01-class-struct