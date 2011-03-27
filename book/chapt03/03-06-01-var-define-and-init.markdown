# 变量的定义,初始化,赋值及销毁

在强类型的语言当中，当使用一个变量之前，我们需要先声明这个变量。然而，对于PHP来说，
在使用一个变量时，我们不需要声明，也不需要初始化，直接用就可以了。那为什么可以直接用呢？
下面我们看下原因。

## 变量的定义


## 变量的初始化
在PHP中，变量是不需要初始化的，用的时候就直接赋值了。
这里我们想说的是，在使用一个变量前初始化这个变量，这是一个很好的编程习惯，也是一种代码优化的方案。
以数组为例，我们来看下初始化和不初始化之间的区别。


## 变量的赋值

    [c]
    variable '=' expr		{ zend_check_writable_variable(&$1); zend_do_assign(&$$, &$1, &$3 TSRMLS_CC); }


    [c]
    static int ZEND_FASTCALL  ZEND_ASSIGN_SPEC_VAR_CONST_HANDLER(ZEND_OPCODE_HANDLER_ARGS)


## 变量的销毁
在PHP中销毁变量最常用的方法是使用unset函数。
unset函数并不是一个真正意义上的函数，它是一种语言结构。
在使用此函数时，它会根据变量的不同触发不同的操作。

PHP手册上的几个例子


    [c]
    T_UNSET_CAST expr	{ zend_do_cast(&$$, &$2, IS_NULL TSRMLS_CC); }

