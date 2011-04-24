# 第一节 类的结构和实现
在面向对象编程(OOP)中,我们最先接触的概念应该就是类了. 在平常的工作中我们经常需要实现和设计类.
那么类是什么？在PHP中类是怎么存储的呢？继承,封装和多态是怎么实现的呢?

## 类的结构
首先我们看看类是什么. 类是用户定义的一种抽象数据类型,它是现实世界中某些具有共性事物的抽象.
有时我们也可以理解其为对象的类别. 类也可以看作是一种复合型的结构,其需要存储多元化的数据,
如属性,方法,以及自身的一些性质等.

类的定义以class关键字开始,后面接类名,类名可以是任何非PHP保留字的名字.
在类名后面紧跟着一对花括号,里面是类的实体, 包括类所具有的属性,这些属性是对象的状态的抽象,
其表现为PHP中支持的数据类型,也可以包括对象本身,通常我们称其为成员变量. 除了类的属性,
类的实体中也包括类所具有的操作,这些操作是对象的行为的抽象,其表现为用操作名和实现该操作的方法,
通常我们称其为成员方法或成员函数. 看类示例的代码：

    [php]
    class ParentClass {
    }

    interface Ifce {
            public function iMethod();
    }

    final class Tipi extends ParentClass implements Ifce {
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

这里定义了一个父类ParentClass,一个接口Ifce,一个子类Tipi. 子类继承父类ParentClass,
实现接口Ifce,并且有一个静态变量$sa,一个类常量 CA,一个公用方法,一个私有方法和一个公用静态方法.
这些结构在Zend引擎内部是如何实现的？类的方法、成员变量是如何存储的？访问控制,静态成员是如何标记的？

首先,我们看看类的内部存储结构:

	[c]
	struct _zend_class_entry {
		char type;     // 类型：ZEND_INTERNAL_CLASS / ZEND_USER_CLASS
		char *name;// 类名称
		zend_uint name_length;                  // 即sizeof(name) - 1
		struct　_zend_class_entry *parent; // 继承的父类
		int　refcount;  // 引用数
		zend_bool constants_updated;

		zend_uint ce_flags; // ZEND_ACC_IMPLICIT_ABSTRACT_CLASS: 类存在abstract方法
		// ZEND_ACC_EXPLICIT_ABSTRACT_CLASS: 在类名称前加了abstract关键字
		// ZEND_ACC_FINAL_CLASS
		// ZEND_ACC_INTERFACE
		HashTable function_table;      // 方法
		HashTable default_properties;          // 默认属性
		HashTable properties_info;     // 属性信息
		HashTable default_static_members;// 类本身所具有的静态变量
		HashTable *static_members; // type == ZEND_USER_CLASS时,取&default_static_members;
		// type == ZEND_INTERAL_CLASS时,设为NULL
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

取上面这个结构的部分字段,我们分析文章最开始的那段PHP代码在内核中的表现.
如表5.1所示：

<table>
  <tr>
    <th scope="col">字段名</th>
    <th scope="col">字段说明 </th>
    <th scope="col">ParentClass类</th>
    <th scope="col">Ifce接口</th>
    <th scope="col">Tipi类</th>
  </tr>
  <tr>
    <th scope="row">name</th>
    <td>类名</td>
    <td>ParentClass</td>
    <td>Ifce</td>
    <td>Tipi</td>
  </tr>
  <tr>
    <th scope="row">type</th>
    <td>类别</td>
    <td>2</td>
    <td>2</td>
    <td>2</td>
  </tr>
  <tr>
    <th scope="row">parent</th>
    <td>父类</td>
    <td>空</td>
    <td>空</td>
    <td>ParentClass类</td>
  </tr>
  <tr>
    <th scope="row">refcount</th>
    <td>引用计数</td>
    <td>1</td>
    <td>1</td>
    <td>2</td>
  </tr>
  <tr>
    <th scope="row">ce_flags</th>
    <td>类的类型</td>
    <td>0</td>
    <td>144</td>
    <td>524352</td>
  </tr>
  <tr>
    <th scope="row">function_table</th>
    <td>函数列表</td>
    <td>空</td>
    <td>
<ul>
<li>
function_name=iMethod  |  type=2  |  fn_flags=258</li>
<ul>
  </td>

    <td>
<ul>

<li>function_name=__construct  |  type=2  |  fn_flags=8448 </li>
<li>function_name=iMethod  |  type=2  |  fn_flags=65800</li>
<li>function_name=_access  |  type=2  |  fn_flags=66560 </li>
<li>function_name=access  |  type=2  |  fn_flags=257  </li>

<ul>
  </tr>
  <tr>
    <th scope="row">interfaces</th>
    <td>接口列表</td>
    <td>空</td>
    <td>空</td>
    <td>Ifce接口 接口数为1</td>
  </tr>
  <tr>
    <th scope="row">filename</th>
    <td>存放文件地址</td>
    <td>/tipi.php</td>
    <td>/tipi.php</td>
    <td>/ipi.php</td>
  </tr>
  <tr>
    <th scope="row">line_start</th>
    <td>类开始行数</td>
    <td>15</td>
    <td>18</td>
    <td>22</td>
  </tr>
  <tr>
    <th scope="row">line_end</th>
    <td>类结束行数</td>
    <td>16</td>
    <td>20</td>
    <td>38</td>
  </tr>
</table>

类的结构中,type有两种类型,数字标记为1和2. 分别为一下宏的定义, 也就是说用户定义的类和模块或者内置的类也是保存在这个结构里的:

    [c]
    #define ZEND_INTERNAL_CLASS         1
    #define ZEND_USER_CLASS             2

对于父类和接口,都是以 **struct　_zend_class_entry**　存在.这表示接口也是以类的形式存储，
其实现是一样的，只是在继承等操作时有单独的处理。
常规的成员方法以HashTable的方式存放在函数结构体中,而魔术方法则单独存在.

## 类的实现
类的定义是以class关键字开始,在Zend/zend_language_scanner.l文件中,找到class对应的token为T_CLASS.
根据此token,在Zend/zend_language_parser.y文件中,找到编译时调用的函数：

    [c]
    unticked_class_declaration_statement:
            class_entry_type T_STRING extends_from
                { zend_do_begin_class_declaration(&$1, &$2, &$3 TSRMLS_CC); }
                implements_list
                '{'
                    class_statement_list
                '}' { zend_do_end_class_declaration(&$1, &$2 TSRMLS_CC); }
        |	interface_entry T_STRING
                { zend_do_begin_class_declaration(&$1, &$2, NULL TSRMLS_CC); } interface_extends_list
                '{'
                    class_statement_list
                '}' { zend_do_end_class_declaration(&$1, &$2 TSRMLS_CC); }
    ;


    class_entry_type:
            T_CLASS			{ $$.u.opline_num = CG(zend_lineno); $$.u.EA.type = 0; }
        |	T_ABSTRACT T_CLASS { $$.u.opline_num = CG(zend_lineno); $$.u.EA.type = ZEND_ACC_EXPLICIT_ABSTRACT_CLASS; }
        |	T_FINAL T_CLASS { $$.u.opline_num = CG(zend_lineno); $$.u.EA.type = ZEND_ACC_FINAL_CLASS; }
    ;


上面的class_entry_type语法说明在语法分析阶段将类分为三种类型：常规类(T_CLASS),抽象类(T_ABSTRACT T_CLASS)和final类(T_FINAL T_CLASS ).
很明显它们的实现方式是在类名前加不同的关键字. 他们分别对应的类型在内核中的体现为:

* 常规类(T_CLASS) 对应的type=0
* 抽象类(T_ABSTRACT T_CLASS) 对应type=ZEND_ACC_EXPLICIT_ABSTRACT_CLASS
* final类(T_FINAL T_CLASS) 对应type=ZEND_ACC_FINAL_CLASS

除了上面的三种类型外,类还包含有另两种类型

* 另一种抽象类,它对应的type=ZEND_ACC_IMPLICIT_ABSTRACT_CLASS.
它在语法分析时并没有分析出来,因为这种类是由于其拥有抽象方法所产生的,即在类名前没有abstract关键字.
在PHP源码中,这个类别是在函数注册时判断成员函数是抽象方法或继承类中的成员方法是抽象方法时设置的.
* 接口,其type=ZEND_ACC_INTERFACE.接口类型的区分是在interface关键字解析时设置,见interface_entry:对应的语法说明.

这五种类型在Zend/zend_complie.h文件中定义如下：

    [c]
    #define ZEND_ACC_IMPLICIT_ABSTRACT_CLASS	0x10
    #define ZEND_ACC_EXPLICIT_ABSTRACT_CLASS	0x20
    #define ZEND_ACC_FINAL_CLASS	            0x40
    #define ZEND_ACC_INTERFACE		            0x80

常规类为0,在这里没有定义,并且在程序也是直接赋值为0. 


语法解析完后就可以知道一个类是抽象类还是final类,普通的类,又或者接口.
定义类时调用了zend_do_begin_class_declaration和zend_do_end_class_declaration函数,
从这两个函数传入的参数,zend_do_begin_class_declaration函数用来处理类名,类的类别和父类,
zend_do_end_class_declaration函数用来处理接口和类的中间代码
这两个函数在Zend/zend_complie.c文件中可以找到其实现.

在zend_do_begin_class_declaration中,首先会对传入的类名作一个转化,统一成小写,这也是为什么类名不区分大小的原因,如下代码

    [php]
    <?php
    class TIPI {
    }

    class tipi {

    }

运行时程序报错: Fatal error: Cannot redeclare class tipi. 这个报错是在运行生成中间的代码时显示的.
这个判断的过程在后面中间代码生成时说明.而关于类的名称的判断则是通过 **T_STRING** token,
在语法解析时做的判断, 但是这只能识别出类名是一个字符串. 假如类名为一些关键字, 如下代码：

    [php]
    class self {
    }

运行, 程序会显示： Fatal error: Cannot use 'self' as class name as it is reserved in...

以上的这个程序判断也是在 **zend_do_begin_class_declaration** 函数中进行.
与self一样, 还有parent, static两个关键字.当这个函数执行完后，我们会得到类声明生成的中间代码为：**ZEND_DECLARE_CLASS** 。
当然，如果我们是声明内部类的话，则生成的中间代码为： **ZEND_DECLARE_INHERITED_CLASS**.

根据生成的中间代码，我们在Zend/zend_vm_execute.h文件中找到其对应的执行函数 **ZEND_DECLARE_CLASS_SPEC_HANDLER**。
这个函数通过调用 **do_bind_class** 函数将此类加入到 EG(class_table) 。
在添加到列表的同时，也判断该类是否存在，如果存在，则添加失败，报我们之前提到的类重复声明错误，只是这个判断在编译开启时是不会生效的。

类的结构是以 **struct _zend_class_entry** 结构体为核心，类的实现是以class为中心作词法分析、语法分析等，
在这些过程中识别出类的类别，类的类名等，并将识别出来的结果存放到类的结构中。
下一节我们一起看看类所包含的成员变量和成员方法。
