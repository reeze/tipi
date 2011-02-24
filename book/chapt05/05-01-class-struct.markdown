# 类和接口的内部结构


类是一种复合型的结构，其需要存储较多元化的数据，如属性，方法，以及自身的一些性质。类在PHP中是使用一种名为zend_class_entry的结构体来进行处理：

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


			zend_class_entry **interfaces;
			zend_uint num_interfaces;


			char*filename;
			zend_uint line_start;
			zend_uint line_end;
			char*doc_comment;
			zend_uint doc_comment_len;


			struct_zend_module_entry *module; // 类所在的模块入口：EG(current_module)
		};












