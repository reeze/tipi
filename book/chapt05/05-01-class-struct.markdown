# 第一节 类的结构

在面向对象编程(OOP)中，我们最先接触的概念应该就是类了（不过在学习的过程中也会有人先接触对象这个概念的）。在平常的工作中，我们经常需要写类，设计类等等。
那么，类是什么？在PHP中，类是以哪种方式存储的？

首先，我们看下类是什么。类是用户定义的一种抽象数据类型，它是现实世界中某些具有共性事物的抽象。有时，我们也可以理解其为对象的类别。
类也可以看作是一种复合型的结构，其需要存储多元化的数据，如属性，方法，以及自身的一些性质等。

在PHP中，类的定义以class关键字开始，后面接类名，类名可以是任何非PHP保留字的名字。
在类名后面紧跟着一对花括号，这个里面就是类的实体了，它包括类所具有的属性，这些属性是对象的状态的抽象，其表现为PHP中支持的数据类型，也可以包括对象本身，通常我们称其为成员变量。
除了类的属性，类的实体中也包括类所具有的操作，这些操作是对象的行为的抽象，其表现为用操作名和实现该操作的方法，通常我们称其为成员方法或成员函数。看一个PHP写的类示例的代码：

    [php]
    class ParentClass {
    }

    interface Ifce {
            public function iMethod();
    }

    final class Tipi extends ParentClass implements Ifce{
            public static $sa = 'aaa';
            const CA = 'bbb';

            public function __constrct() {
            }

            public function iMethod() {
            }

            private function _access() {
            }

            public static function access() {
            }
    }


这展现了PHP中面向对象编程中的接口，继承，静态成员变量，静态方法，常量，访问控制等内容。这些也许已经比较熟悉了，那么这些结构在Zend引擎内部是如何实现的？类的这些方法、成员变量是如何存储的？这些访问控制，静态成员是如何标记的？

在后台我们将展示这个类在PHP的源码程序中是如何存储的。

我们在PHP的源码中很容易找到类的结构存放在zend_class_entry结构体中：

	[c]
		struct _zend_class_entry {
			char type;     // 类型：ZEND_INTERNAL_CLASS / ZEND_USER_CLASS
			char *name;// 类名称
			zend_uint name_length;                  // 即sizeof(name) - 1
			struct_zend_class_entry *parent; // 继承的父类
			intrefcount;  // 引用数
			zend_bool constants_updated;

			zend_uint ce_flags; // ZEND_ACC_IMPLICIT_ABSTRACT_CLASS: 类存在abstract方法
			// ZEND_ACC_EXPLICIT_ABSTRACT_CLASS: 在类名称前加了abstract关键字
			// ZEND_ACC_FINAL_CLASS
			// ZEND_ACC_INTERFACE
			HashTable function_table;      // 方法
			HashTable default_properties;          // 默认属性
			HashTable properties_info;     // 属性信息
			HashTable default_static_members;// 静态变量
			HashTable *static_members; // type == ZEND_USER_CLASS时，取&default_static_members;
			// type == ZEND_INTERAL_CLASS时，设为NULL
			HashTable constants_table;     // 常量
			struct _zend_function_entry *builtin_functions;// 方法定义入口


			union _zend_function *constructor;
			union _zend_function *destructor;
			union _zend_function *clone;


			/* 魔术方法 */
			union _zend_function *__get;
			union _zend_function *__set;
			union _zend_function *__unset;
			union _zend_function *__isset;
			union _zend_function *__call;
			union _zend_function *__tostring;
			union _zend_function *serialize_func;
			union _zend_function *unserialize_func;
			zend_class_iterator_funcs iterator_funcs;// 迭代

			/* 类句柄 */
			zend_object_value (*create_object)(zend_class_entry *class_type TSRMLS_DC);
			zend_object_iterator *(*get_iterator)(zend_class_entry *ce, zval *object,
                intby_ref TSRMLS_DC);

			/* 类声明的接口 */
			int(*interface_gets_implemented)(zend_class_entry *iface,
                    zend_class_entry *class_type TSRMLS_DC);


			/* 序列化回调函数指针 */
			int(*serialize)(zval *object, unsignedchar**buffer, zend_uint *buf_len,
                     zend_serialize_data *data TSRMLS_DC);
			int(*unserialize)(zval **object, zend_class_entry *ce,constunsignedchar*buf,
                    zend_uint buf_len, zend_unserialize_data *data TSRMLS_DC);


			zend_class_entry **interfaces;	//	类实现的接口
			zend_uint num_interfaces;	//	类实现的接口数


			char *filename;	//	类的存放文件地址 绝对地址
			zend_uint line_start;	//	类定义的开始行
			zend_uint line_end;	//	类定义的结束行
			char *doc_comment;
			zend_uint doc_comment_len;


			struct _zend_module_entry *module; // 类所在的模块入口：EG(current_module)
		};


类的定义是以class关键字开始，在Zend/zend_language_scanner.l文件中，找到class对应的token为T_CLASS。
根据此token，在Zend/zend_language_parser.y文件中，找到编译时调用的函数：

    [c]
    unticked_class_declaration_statement:
            class_entry_type T_STRING extends_from
                { zend_do_begin_class_declaration(&$1, &$2, &$3 TSRMLS_CC); }
                implements_list
                '{'
                    class_statement_list
                '}' { zend_do_end_class_declaration(&$1, &$2 TSRMLS_CC); }
        |	interface_entry T_STRING
                { zend_do_begin_class_declaration(&$1, &$2, NULL TSRMLS_CC); }
                interface_extends_list
                '{'
                    class_statement_list
                '}' { zend_do_end_class_declaration(&$1, &$2 TSRMLS_CC); }
    ;


    class_entry_type:
            T_CLASS			{ $$.u.opline_num = CG(zend_lineno); $$.u.EA.type = 0; }
        |	T_ABSTRACT T_CLASS { $$.u.opline_num = CG(zend_lineno); $$.u.EA.type = ZEND_ACC_EXPLICIT_ABSTRACT_CLASS; }
        |	T_FINAL T_CLASS { $$.u.opline_num = CG(zend_lineno); $$.u.EA.type = ZEND_ACC_FINAL_CLASS; }
    ;


从上面的语法说明可以找到关于final和abstract类的说明，这些类的类别的区分就在语法解析时就已经完成了。语法解析完后就可以知道一个类是抽象类还是final类，又或者这是一个普通的类。
定义类时调用了zend_do_begin_class_declaration和zend_do_end_class_declaration函数，
从这两个函数传入的参数，zend_do_begin_class_declaration函数用来处理类名，类的类别和父类,
zend_do_end_class_declaration函数用来处理接口和类的中间代码
这两个函数在Zend/zend_complie.c文件中可以找到其实现。

在zend_do_begin_class_declaration中，首先会对传入的类名作一个转化，统一成小写，这也是为什么类名不区分大小的原因，如下代码

    [php]
    <?php
    class TIPI {
    }

    class tipi {

    }

就会报错: Fatal error: Cannot redeclare class tipi。这个报错是在运行生成中间的代码时显示的。这将在后面说明。
在此函数中对于一些PHP的关键不能做


ZEND_DECLARE_CLASS_SPEC_HANDLER




判断类名是否正确的方法是哪个？
=======
** TODO **
