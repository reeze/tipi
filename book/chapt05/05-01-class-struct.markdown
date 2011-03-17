# 第一节 类的结构

在PHP中类的定义以class关键字开始，后面接类名，类名可以是任何非PHP保留字的名字。
在类名后面紧跟着一对花括号，类的成员函数和成员变量定义在这里。类是一种复合型的结构，其需要存储较多元化的数据，如属性，方法，以及自身的一些性质。
如下一段PHP代码：


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

我们在PHP的源码中很容易找到类的结构是存放在zend_class_entry结构体中：

	[c]
		struct _zend_class_entry {
			chartype;     // 类型：ZEND_INTERNAL_CLASS / ZEND_USER_CLASS
			char*name;// 类名称
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
			struct_zend_function_entry *builtin_functions;// 方法定义入口


			union_zend_function *constructor;
			union_zend_function *destructor;
			union_zend_function *clone;


			/* 魔术方法 */
			union_zend_function *__get;
			union_zend_function *__set;
			union_zend_function *__unset;
			union_zend_function *__isset;
			union_zend_function *__call;
			union_zend_function *__tostring;
			union_zend_function *serialize_func;
			union_zend_function *unserialize_func;
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


			char*filename;	//	类的存放文件地址 绝对地址
			zend_uint line_start;	//	类定义的开始行
			zend_uint line_end;	//	类定义的结束行
			char*doc_comment;
			zend_uint doc_comment_len;


			struct_zend_module_entry *module; // 类所在的模块入口：EG(current_module)
		};


在开头的PHP代码在








判断类名是否正确的方法是哪个？
