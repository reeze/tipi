# 第四节 匿名函数及闭包
匿名函数在编程语言中出现的比较早，最早出现在Lisp语言中，随后很多的编程语言都开始有这个功能了，
目前使用比较广泛的Javascript以及C#，PHP直到5.3才开始真正支持匿名函数，
C++的新标准[C++0x](http://en.wikipedia.org/wiki/C%2B%2B0x)也开始支持了。

匿名函数是一类不需要指定标示符，而又可以被调用的函数或子例程，匿名函数可以方便的作为参数传递给其他函数，
最常见应用是作为回调函数。


## 闭包(Closure)
说到匿名函数，就不得不提到闭包了，闭包是词法闭包(Lexical Closure)的简称，是引用了自由变量的函数，
这个被应用的自由变量将和这个函数一同存在，即使离开了创建它的环境也一样，所以闭包也可认为是有函数和与其相关引用组合而成的实体。
在一些语言中，在函数内定义另一个函数的时候，如果内部函数引用到外部函数的变量，则可能产生闭包。在运行外部函数时，
一个闭包就形成了。

这个词和匿名函数很容易被混用，其实这是两个不同的概念，这可能是因为很多语言实现匿名函数的时候允许形成闭包。

## 使用create_function()创建"匿名"函数
前面提到PHP5.3中才才开始正式支持匿名函数，说到这里可能会有细心读者有意见了，因为有个函数是可以生成匿名函数的: create_function函数，
在手册里可以查到这个[函数](http://cn2.php.net/create_function)在PHP4.1和PHP5中就有了，这个函数通常也能作为匿名回调函数使用，
例如如下:

	[php]
	<?php

	$array = array(1, 2, 3, 4);
	array_walk($array, create_function('$value', 'echo $value;'));
	

这段代码只是将数组中的值依次输出，当然也能做更多的事情。 那为什么这不算真正的匿名函数呢，
我们先看看这个函数的返回值，这个函数返回一个字符串，
通常我们可以像下面这样调用一个函数:

	[php]
	<?php

	function a() {
		echo 'function a';
	}

	$a = 'a';
	$a();

我们在实现回调函数的时候也可以采用这样的方式，例如:

	[php]
	<?php

	function do_something($callback) {
		// doing
		# ...

		// done
		$callback();
	}

这样就能实现在函数do_something()执行完成之后调用$callback指定的函数。回到create_function函数的返回值:
函数返回一个唯一的字符串函数名，出现错误的话则返回FALSE。这么说这个函数也只是动态的创建了一个函数，
而这个函数是**有函数名**的，也就是说，其实这并不是匿名的。只是创建了一个全局唯一的函数而已。

	[php]
	<?php
	$func = create_function('', 'echo "Function created dynamic";');
	echo $func; // lambda_1

	$func();    // Function created dynamic

	$my_func = 'lambda_1';
	$my_func(); // 不存在这个函数
	lambda_1(); // 不存在这个函数

上面这段代码的前面很好理解，create_function就是这么用的，后面通过函数名来调用却失败了，这就有些不好理解了，
php是怎么保证这个函数是全局唯一的? lambda_1看起来也是一个很普通的函数名，如果我们先定义一个叫做lambda_1的函数呢?
这里函数的返回字符串会是lambda_2，它在创建函数的时候会检查是否这个函数是否存在知道找到合适的函数名，
但如果我们在create_function之后定义一个叫做lambda_1的函数会怎么样呢? 这样就出现函数重复定义的问题了，
这样的实现恐怕不是最好的方法，实际上如果你真的定义了名为lambda_1的函数也是不会出现我所说的问题的。这究竟是怎么回事呢?
上面代码的倒数2两行也说明了这个问题，实际上并没有定义名为lambda_1的函数。

也就是说我们的lambda_1和create_function返回的lambda_1并不是一样的!? 怎么会这样呢? 那只能说明我们没有看到实质，
只看到了表面，表面是我们在echo的时候输出了lambda_1，而我们的lambda_1是我们自己敲入的. 
我们还是使用[debug_zval_dump](http://cn.php.net/manual/en/function.debug-zval-dump.php)函数来看看吧。

	[php]
	<?php
	$func = create_function('', 'echo "Hello";');

	$my_func_name = 'lambda_1';
	debug_zval_dump($func); 		// string(9) "lambda_1" refcount(2)
	debug_zval_dump($my_func_name); // string(8) "lambda_1" refcount(2)

看出来了吧，他们的长度居然不一样，长度不一样也即是说不是同一个函数，所以我们调用的函数当然是不存在的，
我们还是直接看看create_function函数到底都做了些什么吧。
该实现见: $PHP_SRC/Zend/zend_builtin_functions.c

	[c]
	#define LAMBDA_TEMP_FUNCNAME    "__lambda_func"

	ZEND_FUNCTION(create_function)
	{
		// ... 省去无关代码
		function_name = (char *) emalloc(sizeof("0lambda_")+MAX_LENGTH_OF_LONG);
		function_name[0] = '\0';  // <--- 这里
		do {
			function_name_length = 1 + sprintf(function_name + 1, "lambda_%d", ++EG(lambda_count));
		} while (zend_hash_add(EG(function_table), function_name, function_name_length+1, &new_function, sizeof(zend_function), NULL)==FAILURE);
		zend_hash_del(EG(function_table), LAMBDA_TEMP_FUNCNAME, sizeof(LAMBDA_TEMP_FUNCNAME));
		RETURN_STRINGL(function_name, function_name_length, 0);
	}

该函数在定义了一个函数之后，给函数起了个名字，它将函数名的第一个字符变为了'\0'也就是空字符，
然后在函数表中查找是否已经定义了这个函数，如果已经有了则生成新的函数名， 第一个字符为空字符的定义方式比较特殊， 
因为在用户代码中无法定义出这样的函数， 也就不存在命名冲突的问题了，这也算是种取巧(tricky)的做法，
在了解到这个特殊的函数之后，我们其实还是可以调用到这个函数的， 只要我们在函数名前加一个空字符就可以了， 
chr()函数可以帮我们生成这样的字符串， 例如前面创建的函数可以通过如下的方式访问到:

	[php]
	<?php
	
	$my_func = chr(0) . "lambda_1";
	$my_func(); // Hello


这种创建"匿名函数"的方式有一些缺点:

1. 函数的定义是通过字符串动态eval的， 这就无法进行基本的语法检查;
1. 这类函数和普通函数没有本质区别， 无法实现闭包的效果.


## 真正的匿名函数
在PHP5.3引入的众多功能中， 除了匿名函数还有一个特性值得讲讲: 
新引入的[__invoke](http://www.php.net/manual/en/language.oop5.magic.php#language.oop5.magic.invoke) 
[魔幻方法](http://www.php.net/manual/en/language.oop5.magic.php)。

### __invoke魔幻方法
这个魔幻方法被调用的时机是: 当一个对象当做函数调用的时候， 如果对象定义了\__invoke魔幻方法则这个函数会被调用，
这和C++中的操作符重载有些类似， 例如可以像下面这样使用:

	[php]
	<?php
	class Callme {
		public function __invoke($phone_num) {
			echo "Hello: $phone_num";
		}
	}

	$call = new Callme();
	$call(13810688888); // "Hello: 13810688888

### 匿名函数的实现
前面介绍了将对象作为函数调用的方法， 聪明的你可能想到在PHP实现匿名函数的方法了，
PHP中的匿名函数就的确是通过这种方式实现的。我们先来验证一下:

	[php]
	<?php
	$func = function() {
		echo "Hello, anonymous function";
	}

	echo gettype($func); 	// object
	echo get_class($func); 	// Closure

原来匿名函数也只是一个普通的类而已。熟悉Javascript的同学对匿名函数的使用方法很熟悉了，
PHP也使用和Javascript类似的语法来[定义](http://cn.php.net/manual/en/functions.anonymous.php)， 
匿名函数可以赋值给一个变量， 因为匿名函数其实是一个类实例， 所以能复制也是很容易理解的，
 在Javascript中可以将一个匿名函数赋值给一个对象的属性， 例如:

	[javascript]
	var a = {};
	a.call = function() {alert("called");}
	a.call(); // alert called

这在Javascript中很常见， 但在PHP中这样并不可以， 给对象的属性复制是不能被调用的， 这样使用将会导致类寻找类中定义的方法，
在PHP中属性名和定义的方法名是可以重复的， 这是由PHP的类模型所决定的， 当然PHP在这方面是可以改进的， 后续的版本中可能会允许这样的调用，
这样的话就更容易灵活的实现一些功能了。目前想要实现这样的效果也是有方法的: 使用另外一个魔幻方法\__call()，
至于怎么实现就留给各位读者当做习题吧。

### 闭包的使用
PHP使用闭包(Closure)来实现匿名函数， 匿名函数最强大的功能也就在匿名函数所提供的一些动态特性以及闭包效果，
匿名函数在定义的时候如果需要使用作用域外的变量需要使用如下的语法来实现:

	[php]
	<?php
	$name = 'TIPI Team';
	$func = function() use($name) {
		echo "Hello, $name";
	}

	$func(); // Hello TIPI Team

这个use语句看起来挺别扭的， 尤其是和Javascript比起来， 不过这也应该是PHP-Core综合考虑才使用的语法， 
因为和Javascript的作用域不同， PHP在函数内定义的变量默认就是局部变量， 而在Javascript中则相反，
除了显式定义的才是局部变量， PHP在变异的时候则无法确定变量是局部变量还是上层作用域内的变量， 
当然也可能有办法在编译时确定，不过这样对于语言的效率和复杂性就有很大的影响。

这个语法比较直接，如果需要访问上层作用域内的变量则需要使用use语句来申明， 这样也简单易读，
说到这里， 其实可以使用use来实现类似global语句的效果。

匿名函数在每次执行的时候都能访问到上层作用域内的变量， 这些变量在匿名函数被销毁之前始终保存着自己的状态，
例如如下的例子:

	[php]
	<?php
	function getCounter() {
		$i = 0;
		return function() use($i) { // 这里如果使用引用传入变量: use(&$i)
			echo ++$i;
		};
	}

	$counter = getCounter();
	$counter(); // 1
	$counter(); // 1

和Javascript中不同，这里两次函数调用并没有使$i变量自增，默认PHP是通过拷贝的方式传入上层变量进入匿名函数，
如果需要改变上层变量的值则需要通过引用的方式传递。所以上面得代码没有输出`1， 2`而是`1，1`。


### 闭包的实现
前面提到匿名函数是通过闭包来实现的， 现在我们开始看看闭包(类)是怎么实现的。
匿名函数和普通函数除了是否有变量名以外并没有区别，
闭包的实现代码在$PHP_SRC/Zend/zend_closure.c。匿名函数"对象化"的问题已经通过Closure实现，
 而对于匿名是怎么样访问到创建该匿名函数时的变量的呢?

例如如下这段代码:

	[php]
	<?php
	$i=100;
	$counter = function() use($i) {
		debug_zval_dump($i);
	};  

	$counter();

通过VLD来查看这段编码编译什么样的opcode了

	$ php -dvld.active=1 closure.php

	vars:  !0 = $i, !1 = $counter
	# *  op                           fetch          ext  return  operands
	------------------------------------------------------------------------
	0  >   ASSIGN                                                   !0, 100
	1      ZEND_DECLARE_LAMBDA_FUNCTION                             '%00%7Bclosure
	2      ASSIGN                                                   !1, ~1
	3      INIT_FCALL_BY_NAME                                       !1
	4      DO_FCALL_BY_NAME                              0          
	5    > RETURN                                                   1

	function name:  {closure}
	number of ops:  5
	compiled vars:  !0 = $i
	line     # *  op                           fetch          ext  return  operands
	--------------------------------------------------------------------------------
	  3     0  >   FETCH_R                      static              $0      'i'
			1      ASSIGN                                                   !0, $0
	  4     2      SEND_VAR                                                 !0
			3      DO_FCALL                                      1          'debug_zval_dump'
	  5     4    > RETURN                                                   null

上面根据情况去掉了一些无关的输出， 从上到下， 第1开始将100赋值给!0也就是变量$i， 随后执行ZEND_DECLARE_LAMBDA_FUNCTION，
那我们去相关的opcode执行函数中看看这里是怎么执行的， 这个opcode的处理函数位于$PHP_SRC/Zend/zend_vm_execute.h中:

	[c]
	static int ZEND_FASTCALL  ZEND_DECLARE_LAMBDA_FUNCTION_SPEC_CONST_CONST_HANDLER(ZEND_OPCODE_HANDLER_ARGS)
	{
		zend_op *opline = EX(opline);
		zend_function *op_array;
			  
		if (zend_hash_quick_find(EG(function_table), Z_STRVAL(opline->op1.u.constant), Z_STRLEN(opline->op1.u.constant), Z_LVAL(opline->op2.u.constant), (void *) &op_arra
	y) == FAILURE ||
			op_array->type != ZEND_USER_FUNCTION) {
			zend_error_noreturn(E_ERROR, "Base lambda function for closure not found");
		}

		zend_create_closure(&EX_T(opline->result.u.var).tmp_var, op_array TSRMLS_CC);

		ZEND_VM_NEXT_OPCODE();
	}   

该函数调用了zend_create_closure()函数来创建一个闭包对象, 
那我们继续看看位于$PHP_SRC/Zend/zend_closures.c的zend_create_closure()函数都做了些什么。

	[c]
	ZEND_API void zend_create_closure(zval *res, zend_function *func TSRMLS_DC)
	{
		zend_closure *closure;

		object_init_ex(res, zend_ce_closure);

		closure = (zend_closure *)zend_object_store_get_object(res TSRMLS_CC);

		closure->func = *func;

		if (closure->func.type == ZEND_USER_FUNCTION) { // 如果是用户定义的匿名函数
			if (closure->func.op_array.static_variables) {
				HashTable *static_variables = closure->func.op_array.static_variables;

				// 为函数申请存储静态变量的哈希表空间
				ALLOC_HASHTABLE(closure->func.op_array.static_variables); 
				zend_hash_init(closure->func.op_array.static_variables, zend_hash_num_elements(static_variables), NULL, ZVAL_PTR_DTOR, 0);
				
				// 循环当前静态变量列表， 使用zval_copy_static_var方法处理
				zend_hash_apply_with_arguments(static_variables TSRMLS_CC, (apply_func_args_t)zval_copy_static_var, 1, closure->func.op_array.static_variables);
			}
			(*closure->func.op_array.refcount)++;
		}

		closure->func.common.scope = NULL;
	}

如上段代码注释中所说, 继续看看zval_copy_static_var()函数的实现:

	[c]
	static int zval_copy_static_var(zval **p TSRMLS_DC, int num_args, va_list args, zend_hash_key *key)
	{
		HashTable *target = va_arg(args, HashTable*);
		zend_bool is_ref;

		// 只对通过use语句类型的静态变量进行取值操作， 否则匿名函数体内的静态变量也会影响到作用域之外的变量
		if (Z_TYPE_PP(p) & (IS_LEXICAL_VAR|IS_LEXICAL_REF)) {
			is_ref = Z_TYPE_PP(p) & IS_LEXICAL_REF;

			if (!EG(active_symbol_table)) {
				zend_rebuild_symbol_table(TSRMLS_C);
			}
			// 如果当前作用域内没有这个变量
			if (zend_hash_quick_find(EG(active_symbol_table), key->arKey, key->nKeyLength, key->h, (void **) &p) == FAILURE) {
				if (is_ref) {
					zval *tmp;

					// 如果是引用变量， 则创建一个临时变量一边在匿名函数定义之后对该变量进行操作
					ALLOC_INIT_ZVAL(tmp);
					Z_SET_ISREF_P(tmp);
					zend_hash_quick_add(EG(active_symbol_table), key->arKey, key->nKeyLength, key->h, &tmp, sizeof(zval*), (void**)&p);
				} else {
					// 如果不是引用则表示这个变量不存在
					p = &EG(uninitialized_zval_ptr);
					zend_error(E_NOTICE,"Undefined variable: %s", key->arKey);
				}
			} else {
				// 如果存在这个变量， 则根据是否是引用， 对变量进行引用或者复制
				if (is_ref) {
					SEPARATE_ZVAL_TO_MAKE_IS_REF(p);
				} else if (Z_ISREF_PP(p)) {
					SEPARATE_ZVAL(p);
				}
			}
		}
		if (zend_hash_quick_add(target, key->arKey, key->nKeyLength, key->h, p, sizeof(zval*), NULL) == SUCCESS) {
			Z_ADDREF_PP(p);
		}
		return ZEND_HASH_APPLY_KEEP;
	}

这个函数作为一个回调函数传递给`zend_hash_apply_with_arguments()`函数， 每次读取到hash表中的值之后由这个函数进行处理，
而这个函数对所有use语句定义的变量值赋值给这个匿名函数的静态变量， 这样匿名函数就能访问到use的变量了。
