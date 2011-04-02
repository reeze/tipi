# 类的成员变量及方法

在上一小节，我们介绍了类的结构和声明过程，从而，我们知道了类的存储位置，类的类型设置等的实现方式。
在本小节，我们将介绍类的成员变量和成员方法。
首先，我们看一下，什么是成员变量，什么是成员方法。

类的成员变量在PHP中本质上是一个变量，只是这些变量都归属于某个类，并且给这些变量都加上访问控制。
类的成员变量也称为成员属性，它是现实世界实体属性的抽象，是可以用来描述对象状态的数据。
类的成员方法在PHP中本质上是一个函数，只是这个函数以类的方法存在，可能它是一个类方法也可能是一个实例方法，
并且在这些方法上都加上了类的访问控制。类的成员方法是现实世界实体行为的抽象，可以用来实现类的行为。


## 成员变量
在第三章介绍过变量，不过那些变量要么是定义在全局范围中，叫做全局变量，要么是定义在某个函数中，叫做局部变量。
成员变量是定义在类里面，并和方法处于同一层次。如下一个简单的PHP代码示例，定义了一个类，并且这个类有一个成员变量。

    [php]
    class Tipi {
        public $var;
    }


这样一个类的结构在PHP内核中的存储方式我们已经在上一小节介绍过了。现在，我们要讨论的是类的成员变量是存储方式是什么。
假如我们需要直接访问这个变量，整个访问过程是什么？
当然，以这个示例来说，访问这个成员变量是通过对象来访问，关于对象的相关知识我们将在后面的小节作详细的介绍。

当我们用VLD扩展查看以上代码生成的中间代码时，我们发现，并没有相关的中间代码输出。
这是因为成员变量在编译时间内已经注册到了类的结构中，那这个注册的过程是什么?这些成员变量注册的位置在哪？

我们从上一小节知道，类的声明调用的编译函数中有一个zend_do_begin_class_declaration函数。
此函数用来初始化类的一些信息，其中包括类的成员变量。其调用顺序为：
[zend_do_begin_class_declaration] --> [zend_initialize_class_data] --> [zend_hash_init_ex]

    [c]
    zend_hash_init_ex(&ce->default_properties, 0, NULL, zval_ptr_dtor_func, persistent_hashes, 0);

因为类的成员变量是放在一个HashTable中，所以，其数据的初始化是使用zend_hash_init_ex函数。

在声明类的时候初始化了类的成员变量所在的HashTable，之后如果有新的成员变量声明，
从Zend/zend_language_parser.y文件中找到声明成员变量的方法为：**zend_do_declare_property**。
这个函数首先判断成员变量不允许的一些情况：

* 接口中不允许使用成员变量
* 成员变量不能拥有抽象属性
* 不能声明成员变量为final
* 不能重复声明属性

这四种情况分别对应四个if语句，其中不能重复声明属性会有一个查询成员变量所在HashTable的过程。

在判断了这些情况后，函数会进行成员变量的初始化操作.

    [c]
    ALLOC_ZVAL(property);   //  分配内存

	if (value) {    //  成员变量有初始化数据
		*property = value->u.constant;
	} else {
		INIT_PZVAL(property);
		Z_TYPE_P(property) = IS_NULL;
	}

在初始化过程中，程序会先分配内存，如果这个成员变量有初始化的数据，则将数据直接赋值给该属性，否则初始化ZVAL，并将其类型设置为IS_NULL.
在初始化过程完成后，程序通过调用 **zend_declare_property_ex** 函数将此成员变量添加到指定的类结构中。

以上为成员变量的初始化和注册成员变量的过程,常规的成员变量最后都会注册到类的 **default_properties** 字段。
在我们平时的工作中，可能会用不到上面所说的这些过程，但是我们可能会使用get_class_vars()函数来查看类的成员变量。
此函数返回由类的默认属性组成的关联数组，此数组的元素以 varname => value 的形式存在。其实现核心代码如下：

    [c]
	if (zend_lookup_class(class_name, class_name_len, &pce TSRMLS_CC) == FAILURE) {
		RETURN_FALSE;
	} else {
		array_init(return_value);
		zend_update_class_constants(*pce TSRMLS_CC);
		add_class_vars(*pce, &(*pce)->default_properties, return_value TSRMLS_CC);
		add_class_vars(*pce, CE_STATIC_MEMBERS(*pce), return_value TSRMLS_CC);
	}

首先调用zend_lookup_class函数查找名为class_name的类，并将赋值给pce变量。
这个查找的过程最核心是一个HashTable的查找函数zend_hash_quick_find，它会查找EG(class_table)。
判断类是否存在，如果存在则直接返回。如果不存在，则需要判断是否可以自动加载，如果可以自动加载，则会加载类后再返回。
如果不能找到类，则返回FALSE。如果找到了类，则初始化返回的数组，更新类的静态成员变量，添加类的成员变量到返回的数组。
这里针对类的静态成员变量有一个更新的过程，关于这个过程我们在下面有关于静态成员变量中做相关介绍。

## 静态成员变量
类的静态成员变量是所有实例共用的，因此它也叫做类变量。
在PHP的类结构中，类本身的静态变量存放在 **default_static_members** 字段中。

与成员变量不同，类变量可以直接通过类名调用，这也体现其称作类变量的特别。一个PHP示例：

    [php]
    class Tipi {
        public static $var = 10;
    }

    Tipi::$var;

通过VLD扩展查看其生成的中间代码：

    [c]
    function name:  (null)
    number of ops:  6
    compiled vars:  !0 = $var
    line     # *  op                           fetch          ext  return  operands
    --------------------------------------------------------------------------------
    -
       2     0  >   EXT_STMT
             1      NOP
       6     2      EXT_STMT
             3      ZEND_FETCH_CLASS                                 :1      'Tipi'
             4      FETCH_R                      static member               'var'
             5    > RETURN                                                   1

    branch: #  0; line:     2-    6; sop:     0; eop:     5
    path #1: 0,
    Class Tipi: [no user functions]

给前面的内容和VLD生成的内容，我们可以知道PHP代码：Tipi::$var;　生成的中间代码包括ZEND_FETCH_CLASS和FETCH_R。
这是由于在编译时其调用了zend_do_fetch_static_member函数，而在此函数中又调用了zend_do_fetch_class函数，
从而会生成ZEND_FETCH_CLASS中间代码。它所对应的执行函数为 **ZEND_FETCH_CLASS_SPEC_CONST_HANDLER**。
此函数会调用zend_fetch_class函数（Zend/zend_execute_API.c）.
而zend_fetch_class函数最终也会调用 **zend_lookup_class_ex** 函数查找类。这与前面的查找方式一样。

类找到了，下面是查找类的静态成员变量，其最终调用的函数为：zend_std_get_static_property。
这里由于第二个参数的类型为 ZEND_FETCH_STATIC_MEMBER。这个函数最后是从 **static_members** 字段中查找对应的值返回。
而在查找前会和前面一样，执行zend_update_class_constants函数，从而更新此类的所有静态成员变量，其程序流程如图5.1所示：

![图5.1 静态变量更新流程图](../images/chapt05/05-02-01-class-static-vars.jpg)


## 成员方法
get_class_methods()

## 静态成员方法
因为类的静态成员方法通常也叫做类方法。