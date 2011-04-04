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

整个继承的过程是以类结构为中心，当继承发生时，程序会先处理所有的接口。接口继承时，程序调用的是zend_do_implement_interface函数，
zend_do_implement_interface函数会合并接口中的常量列表和方法列表操作，这就是接口中不能有变量却可以有常量的实现原因。
在接口继承的过程中有对当前类的接口中是否存在同样接口的判断操作，如果已经存在了同样的接口，则此接口将不会继承。
在接口继承后，程序会合并类的成员变量、属性、常量、函数等，这些都是HashTable的merge操作。

在继承过程中，除了常规的函数合并后，还有魔法方法的合并，其调用的函数为do_inherit_parent_constructor(ce).
此方法实现魔术方法继承，如果子类中没有相关的魔术方法，则继承父类的对应方法。如下所示的PHP代码为子类没构造函数的情况

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

### 接口的实现



## 抽象类