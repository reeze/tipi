# 第七节 数据类型转换

PHP是弱类型的动态语言，在前面的章节中我们已经介绍了PHP的变量都存放在一个名为ZVAL的容器中，
ZVAL包含了变量的类型和各种类型变量的值。
PHP中的变量不需要显式的数据类型定义，可以给变量赋值任意类型的数据，
PHP变量之间的数据类型转换有两种: 隐式和显式转换。

## 隐式类型转换
隐式类型转换也被称为自动类型转换，是指不需要程序员书写代码，由编程语言自动完成的类型转换。
在PHP中，我们经常遇到的隐式转换有：

1．**直接的变量赋值操作**

在PHP中，直接对变量的赋值操作是隐式类型转换最简单的方式，也是我们最常见的一种方式，或许我们已经习以为常，从而没有感觉到变量的变化。
在直接赋值的操作中，变量的数据类型由赋予的值决定，即左值的数据类型由右值的数据类型决定。
比如，当把一个字符串类型的数据赋值给变量时，不管该变量以前是什么类型的变量，此时该变量就是一个字符串类型的变量。
看一段代码：

    [php]
    $string = "To love someone sincerely means to love all the people,  to love the world and life,  too."
    $integer = 10;
    $string = $integer;

上面的代码，当执行完第三行代码，$string变量的类型就是一个整形了。
通过VLD扩展可以查到第三次赋值操作的中间代码及操作数的类型，再找到赋值的最后实现为**zend_assign_to_variable**函数。
这在前面的小节中已经详细介绍过了。我们这个例子是很简单的一种赋值，在源码中是直接将$string的ZVAL容器的指针指向$integer变量指向的指针，
并将$integer的引用计数加1。这个操作在本质上改变了$string变量的内容，而原有的变量内容则被垃圾收集机制回收。关于赋值的具体细节，请返回上一节查看。

2．**运算式结果对变量的赋值操作**
我们常说的隐式类型转换是将一个表达式的结果赋值给一个变量，在运算的过程中发生了隐式的类型转换。
这种类型转换不仅仅在PHP语言，在其它众多的语言中也有见到，这是我们常规意义上的隐式类型转换。
这种类型转换又分为两种情况：

 * 表达式的操作数为同一数据类型 这种情况的作用以上面的直接变量的类型转换是同一种情况，只是此时右值变成了表达式的运算结果。
 * 表达式的操作数不为同的数据类型 这种情况的类型转换发生在表达式的运算符的计算过程中，在源码中也就是发生在运行符的实现过程中。

看一个字符串和整数的隐式数据类型转换:

	[php]
	<?php
	$a = 10;
	$b = 'a string ';

	echo $a . $b;

上面例子中字符串连接操作就存在自动数据类型转化，$a变量是数值类型，$b变量是字符串类型，
这里$b变量就是隐式(自动)的转换为字符串类型了。通常自动数据类型转换发生在特定的操作上下文中，
类似的还有求和操作"+"。具体的自动类型转换方式和特定的操作有关。
下面就以字符串连接操作为例说明隐式转换的实现:

脚本执行的时候字符串的连接操作是通过Zend/zend_operators.c文件中的如下函数进行:

	[c]
	ZEND_API int concat_function(zval *result, zval *op1, zval *op2 TSRMLS_DC) /* {{{ */
	{
			zval op1_copy, op2_copy;
			int use_copy1 = 0, use_copy2 = 0;

			if (Z_TYPE_P(op1) != IS_STRING) {
					zend_make_printable_zval(op1, &op1_copy, &use_copy1);
			}
			if (Z_TYPE_P(op2) != IS_STRING) {
					zend_make_printable_zval(op2, &op2_copy, &use_copy2);
			}
			// 省略
	}

可用看出如果字符串链接的两个操作数如果不是字符串的话，
则调用zend_make_printable_zval函数将操作数转换为"printable_zval"也就是字符串。

	[c]
	ZEND_API void zend_make_printable_zval(zval *expr, zval *expr_copy, int *use_copy)
	{
		if (Z_TYPE_P(expr)==IS_STRING) {
			*use_copy = 0;
			return;
		}
		switch (Z_TYPE_P(expr)) {
			case IS_NULL:
				Z_STRLEN_P(expr_copy) = 0;
				Z_STRVAL_P(expr_copy) = STR_EMPTY_ALLOC();
				break;
			case IS_BOOL:
				if (Z_LVAL_P(expr)) {
					Z_STRLEN_P(expr_copy) = 1;
					Z_STRVAL_P(expr_copy) = estrndup("1", 1);
				} else {
					Z_STRLEN_P(expr_copy) = 0;
					Z_STRVAL_P(expr_copy) = STR_EMPTY_ALLOC();
				}
				break;
			case IS_RESOURCE:
				// ...省略
			case IS_ARRAY:
				Z_STRLEN_P(expr_copy) = sizeof("Array") - 1;
				Z_STRVAL_P(expr_copy) = estrndup("Array", Z_STRLEN_P(expr_copy));
				break;
			case IS_OBJECT:
					// ... 省略
			case IS_DOUBLE:
				*expr_copy = *expr;
				zval_copy_ctor(expr_copy);
				zend_locale_sprintf_double(expr_copy ZEND_FILE_LINE_CC);
				break;
			default:
				*expr_copy = *expr;
				zval_copy_ctor(expr_copy);
				convert_to_string(expr_copy);
				break;
		}
		Z_TYPE_P(expr_copy) = IS_STRING;
		*use_copy = 1;
	}

这个函数根据不同的变量类型来返回不同的字符串类型，例如BOOL类型的数据返回0和1，
数组只是简单的返回Array等等，类似其他类型的数据转换也是类型，
都是根据操作数的不同类型的转换为相应的目标类型。在表达式计算完成后，表达式最后会有一个结果，
这个结果的数据类型就是整个表达式的数据类型。当执行赋值操作时，如果再有数据类型的转换发生，
则是直接变量赋值的数据类型转换了。

## 显式类型转换(强制类型转换)
在前面介绍了隐式类型转换，在我们的日常编码过程也会小心的使用这种转换，
这种不可见的操作可能与我们想象中的不一样，如整形和浮点数之间的转换。
当我们是一定需要某个数据类型的变量时，可以使用强制的数据类型转换，这样在代码的可读性等方面都会好些。
在PHP中的强制类型转换和C中的非常像:

	[php]
	<?php
	$double = 20.10;
	echo (int)$double;

PHP中允许的强制类型有:

- (int), (integer)  转换为整型
- (bool), (boolean) 转换为布尔类型
- (float), (double) 转换为浮点类型
- (string) 转换为字符串
- (array) 转换为数组
- (object) 转换为对象
- (unset) 转换为NULL

在Zend/zend_operators.c中实现了转换为这些目标类型的实现函数convert_to_*系列函数，
读者自行查看这些函数即可，这些数据类型转换类型中有一个我们比较少见的unset类型转换:

	[c]
	ZEND_API void convert_to_null(zval *op) /* {{{ */
	{
		if (Z_TYPE_P(op) == IS_OBJECT) {
			if (Z_OBJ_HT_P(op)->cast_object) {
				zval *org;
				TSRMLS_FETCH();

				ALLOC_ZVAL(org);
				*org = *op;
				if (Z_OBJ_HT_P(op)->cast_object(org, op, IS_NULL TSRMLS_CC) == SUCCESS) {
					zval_dtor(org);
					return;
				}
				*op = *org;
				FREE_ZVAL(org);
			}
		}

		zval_dtor(op);
		Z_TYPE_P(op) = IS_NULL;
	}

转换为NULL非常简单，对变量进行析构操作，然后将数据类型设为IS_NULL即可。
可能读者会好奇(unset)$a和unset($a)这两者有没有关系，其实并没有关系，
前者是将变量$a的类型变为NULL，这只是一个类型的变化，而后者是将这个变量释放，释放后当前作用域内该变量及不存在了。

除了上面提到的与C语言很像，在其它语言中也经常见到的强制数据转换，PHP中有一个极具PHP特色的强制类型转换。
PHP的标准扩展中提供了两个有用的方法settype()以及gettype()方法，前者可以动态的改变变量的数据类型，
gettype()方法则是返回变量的数据类型。在ext/standard/type.c文件中找到settype的实现源码：

	[c]
	PHP_FUNCTION(settype)
	{
		zval **var;
		char *type;
		int type_len = 0;

		if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "Zs", &var, &type, &type_len) == FAILURE) {
			return;
		}

		if (!strcasecmp(type, "integer")) {
			convert_to_long(*var);
		} else if (!strcasecmp(type, "int")) {
			convert_to_long(*var);
		} else if (!strcasecmp(type, "float")) {
			convert_to_double(*var);
		} else if (!strcasecmp(type, "double")) { /* deprecated */
			convert_to_double(*var);
		} else if (!strcasecmp(type, "string")) {
			convert_to_string(*var);
		} else if (!strcasecmp(type, "array")) {
			convert_to_array(*var);
		} else if (!strcasecmp(type, "object")) {
			convert_to_object(*var);
		} else if (!strcasecmp(type, "bool")) {
			convert_to_boolean(*var);
		} else if (!strcasecmp(type, "boolean")) {
			convert_to_boolean(*var);
		} else if (!strcasecmp(type, "null")) {
			convert_to_null(*var);
		} else if (!strcasecmp(type, "resource")) {
			php_error_docref(NULL TSRMLS_CC, E_WARNING, "Cannot convert to resource type");
			RETURN_FALSE;
		} else {
			php_error_docref(NULL TSRMLS_CC, E_WARNING, "Invalid type");
			RETURN_FALSE;
		}
		RETVAL_TRUE;
	}

这个极具PHP特色的强制类型转换就是这个函数，而这个函数是作为一个代理方法存在，
具体的转换规则由各个类型的处理函数处理，不管是自动还是强制类型转换，最终都会调用这些内部转换方法，
这和前面的强制类型转换在本质上是一样的。


