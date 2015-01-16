# 第六节 PHP保留类及特殊类

在面向对象语言中，都会内置一些语言内置提供的基本功能类，比如JavaScript中的Array，Number等类，
PHP中也有很多这种类，比如Directory，stdClass，Exception等类，同时一些标准扩展比如PDO等扩展中也会定义一些类，
PHP中类是不允许重复定义的，所以在编写代码时不允许定义已经存在的类。

同时PHP中有一些特殊的类：self，static和parent，相信读者对这self和parent都比较熟悉了，而static特殊类是PHP5.3才引入的。

PHP中的static关键字非常多义:

* 在函数体内的修饰变量的static关键字用于定义静态局部变量。
* 用于修饰类成员函数和成员变量时用于声明静态成员。
* (PHP5.3)在作用域解析符(::)前又表示静态延迟绑定的特殊类。

这个关键字修饰的意义都表示"静态"，在[PHP手册中](http://cn.php.net/manual/en/language.oop5.paamayim-nekudotayim.php)提到self，
parent和static这几个关键字，但实际上除了static是关键字以外，其他两个均不是关键字，
在手册的[关键字列表](http://cn.php.net/manual/en/reserved.keywords.php)中也没有这两个关键字，
要验证这一点很简单:

	[php]
	<?php
	var_dump(self); // ->  string(4) "self"

上面的代码并没有报错，如果你把error_reporting(E_ALL)打开，就能看到实际是什么情况了:
运行这段代码会出现“ Notice: Use of undefined constant self - assumed 'self'“，
也就是说PHP把self当成一个普通常量了，尝试未定义的常量会把产量本身当成一个字符串，
例如上例的”self"，不过同时会出一个NOTICE，这就是说self这个标识符并没有什么特殊的。

	[php]
	<?php
	define('self'，"stdClass");
	echo self; // stdClass

>**NOTE**
>不同语言中的关键字的意义会有些区别，Wikipedia上的[解释](http://en.wikipedia.org/wiki/Keyword_(computer_programming))是：
>具有特殊含义的标识符或者单词，从这个意义上说$this也算是一个关键字，但在PHP的关键字列表中并没有。
>PHP的关键字和C/C++一样属于保留字(关键字)，关键字用于表示特定的语法形式，例如函数定义，流程控制等结构。
>这些关键字有他们的特定的使用场景，而上面提到的self和parent并没有这样的限制。


## self，parent，static类
前面已经说过self的特殊性。self是一个特殊类，它指向当前类，但只有在类定义内部才有效，
但也并不一定指向类本身这个特殊类，比如前面的代码，如果放在类方法体内运行，echo self; 
还是会输出常量self的值，而不是当前类，它不止要求在类的定义内部，还要求在类的上下文环境，
比如 new self()的时候，这时self就指向当前类，或者self::$static_varible，
self::CONSTANT类似的作用域解析符号(::)，这时的self才会作为指向本身的类而存在。

同理parent也和self类似。下面先看看在在类的环境下的编译吧$PHP_SRC/Zend/zend_language_parser.y:

	class_name_reference:
			class_name                      { zend_do_fetch_class(&$$, &$1 TSRMLS_CC); }
		|   dynamic_class_name_reference    { zend_do_end_variable_parse(&$1, BP_VAR_R, 0 TSRMLS_CC); zend_do_fetch_class(&$$, &$1 TSRMLS_CC); }
	;

在需要获取类名时会执行zend_do_fetch_class()函数：

	[c]
	void zend_do_fetch_class(znode *result, znode *class_name TSRMLS_DC)
	{
		// ...
		opline->opcode = ZEND_FETCH_CLASS;
		if (class_name->op_type == IS_CONST) {
			int fetch_type;

			fetch_type = zend_get_class_fetch_type(class_name->u.constant.value.str.val, class_name->u.constant.value.str.len);
			switch (fetch_type) {
				case ZEND_FETCH_CLASS_SELF:
				case ZEND_FETCH_CLASS_PARENT:
				case ZEND_FETCH_CLASS_STATIC:
					SET_UNUSED(opline->op2);
					opline->extended_value = fetch_type;
					zval_dtor(&class_name->u.constant);
					break;
				default:
					zend_resolve_class_name(class_name, &opline->extended_value, 0 TSRMLS_CC);
					opline->op2 = *class_name;
					break;
			}
		} else {
			opline->op2 = *class_name;
		}
		// ...
	}

上面省略了一些无关的代码，重点关注fetch_type变量。这是通过zend_get_class_fetch_type()函数获取到的。

	[c]
	int zend_get_class_fetch_type(const char *class_name, uint class_name_len)
	{
		if ((class_name_len == sizeof("self")-1) &&
			!memcmp(class_name, "self", sizeof("self")-1)) {
			return ZEND_FETCH_CLASS_SELF;
		} else if ((class_name_len == sizeof("parent")-1) &&
			!memcmp(class_name, "parent", sizeof("parent")-1)) {
			return ZEND_FETCH_CLASS_PARENT;
		} else if ((class_name_len == sizeof("static")-1) &&
			!memcmp(class_name, "static", sizeof("static")-1)) {
			return ZEND_FETCH_CLASS_STATIC;
		} else {
			return ZEND_FETCH_CLASS_DEFAULT;
		}
	}

前面的代码是Zend引擎编译类相关操作的代码，下面就到执行阶段了，self，parent等类的指向会在执行时进行获取，
找到执行opcode为ZEND_FETCH_CLASS的执行函数:

	[c]
	zend_class_entry *zend_fetch_class(const char *class_name, uint class_name_len, int fetch_type TSRMLS_DC)
	{
		zend_class_entry **pce;
		int use_autoload = (fetch_type & ZEND_FETCH_CLASS_NO_AUTOLOAD) == 0;
		int silent       = (fetch_type & ZEND_FETCH_CLASS_SILENT) != 0;

		fetch_type &= ZEND_FETCH_CLASS_MASK;

	check_fetch_type:
		switch (fetch_type) {
			case ZEND_FETCH_CLASS_SELF:
				if (!EG(scope)) {
					zend_error(E_ERROR, "Cannot access self:: when no class scope is active");
				}
				return EG(scope);
			case ZEND_FETCH_CLASS_PARENT:
				if (!EG(scope)) {
					zend_error(E_ERROR, "Cannot access parent:: when no class scope is active");
				}
				if (!EG(scope)->parent) {
					zend_error(E_ERROR, "Cannot access parent:: when current class scope has no parent");
				}
				return EG(scope)->parent;
			case ZEND_FETCH_CLASS_STATIC:
				if (!EG(called_scope)) {
					zend_error(E_ERROR, "Cannot access static:: when no class scope is active");
				}
				return EG(called_scope);
			case ZEND_FETCH_CLASS_AUTO: {
					fetch_type = zend_get_class_fetch_type(class_name, class_name_len);
					if (fetch_type!=ZEND_FETCH_CLASS_DEFAULT) {
						goto check_fetch_type;
					}
				}
				break;
		}

		if (zend_lookup_class_ex(class_name, class_name_len, use_autoload, &pce TSRMLS_CC) == FAILURE) {
			if (use_autoload) {
				if (!silent && !EG(exception)) {
					if (fetch_type == ZEND_FETCH_CLASS_INTERFACE) {
						zend_error(E_ERROR, "Interface '%s' not found", class_name);
					} else {
						zend_error(E_ERROR, "Class '%s' not found", class_name);
					}
					}
				}
			}
			return NULL;
		}
		return *pce;
	}

从这个函数就能看出端倪了，当需要获取self类的时候，则将EG(scope)类返回，而EG(scope)指向的正是当前类。
如果是parent类的话则从去EG(scope)->parent也就是当前类的父类，而static获取的时EG(called_scope)，
分别说说EG宏的这几个字段，前面已经介绍过EG宏，它可以展开为如下这个结构体:

	[c]
	struct _zend_executor_globals {
		// ...
		zend_class_entry *scope;
		zend_class_entry *called_scope; /* Scope of the calling class */
		// ...
	}

	struct _zend_class_entry {
		char type;
		char *name;
		zend_uint name_length;
		struct _zend_class_entry *parent;
	}
	#define struct _zend_class_entry zend_class_entry

其中的zend_class_entry就是PHP中类的内部结构表示，zend_class_entry有一个parent字段，也就是该类的父类。
在EG结构体中的中called_scope会在执行过程中将当前执行的类赋值给called_scope，例如如下代码:

	[php]
	<?php
	class A {
		public static function funcA() {
			static::funcB();
		}
	}

	class B extends A {
		public static function funcB() {
			echo  "B::funcB()";
		}
	}

	B::funcA();

代码B::funcA()执行的时候，实际执行的是B的父类A中定义的funcA函数，A::funcA()执行时当前的类(scope)指向的是类A，
而这个方法是从B类开始调用的，called_scope指向的是类B，static特殊类指向的正是called_scope，也就是当前类(触发方法调用的类)，
这也是延迟绑定的原理。
