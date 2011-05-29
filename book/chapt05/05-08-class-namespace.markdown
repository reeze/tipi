# 第八节 命名空间

## 命名空间概述
在维基百科中，对[命名空间](http://zh.wikipedia.org/wiki/%E5%91%BD%E5%90%8D%E7%A9%BA%E9%97%B4)的定义是：
命名空间（英语：Namespace）表示标识符（identifier）的上下文（context）。一个标识符可在多个命名空间中定义，
它在不同命名空间中的含义是互不相干的。在编程语言中，命名空间是一种特殊的作用域，它包含了处于该作用域内的标识符，
且本身也用一个标识符来表示，这样便将一系列在逻辑上相关的标识符用一个标识符组织了起来。
函数和类的作用域可被视作隐式命名空间，它们和可见性、可访问性和对象生命周期不可分割的联系在一起。

命名空间可以看作是一种封装事物的方法，同时也可以看作是组织代码结构的一种形式，在很多语言中都可以见到这种抽象概念和组织形式。
在PHP中，命名空间用来解决在编写类库或应用程序时创建可重用的代码如类或函数时碰到的两类问题：

1. 用户编写的代码与PHP内部的类/函数/常量或第三方类/函数/常量之间的名字冲突。
1. 为很长的标识符名称(通常是为了缓解第一类问题而定义的)创建一个别名（或简短）的名称，提高源代码的可读性。

PHP从5.3.0版本开始支持命名空间特性。看一个定义和使用命名空间的示例：

    [php]
	<?php
    namespace tipi;
    class Exception {
        public static $var = 'think in php internal';
    }

    const E_ALL = "E_ALL IN Tipi";

    function strlen(){
        echo 'strlen in tipi';
    }

    echo Exception::$var;
    echo strlen(Exception::$var);

如上所示，定义了命名空间tipi,在这个命名空间内定义了一个Exception类，一个E_ALL常量和一个函数strlen。
这些类、常量和函数PHP默认已经实现。假如没有这个命名空间，声明这些类、常量或函数时会报函数重复声明或类重复声明的错误，
并且常量的定义也不会成功。

从PHP语言来看，命名空间通过 **namespace** 关键字定义，在命名空间内，可以包括任何合法的PHP代码，但是它的影响范围仅限于类、常量和函数。
从语法上来讲，PHP支持在一个文件中定义多个命名空间，但是不推荐这种代码组织方式。
当需要将全局的非命名空间中的代码与命名空间中的代码组合在一起，全局代码必须用一个不带名称的 namespace 语句加上大括号括起来。

此时，思考一下，在PHP内核中，命名空间的定义是如何实现的呢？
当在多个命名空间中存在多个相同的函数或类时，如何区分？
命名空间内的函数如何调用？

## 命名空间的定义
命名空间在PHP中的实现方案比较简单，不管是函数，类或者常量，
在声明的过程中都将命名空间与定义的函数名以\合并起来，作为函数名或类名存储在其对应的容器中。
如上面示例中的Exception类，最后存储的类名是tipi\Exception.
对于整个PHP实现的架构来说，这种实现方案的代价和对整个代码结构的调整都是最小的。

下面我们以Exception类为例说明整个命名空间的实现。
命名空间实现的关键字是namespace, 从此关键字开始我们可以找到在编译时处理此关键字的函数为 **zend_do_begin_namespace**.
在此函数中，关键是在对CG(current_namespace)的赋值操作，这个值在后面类声明或函数等声明时都会有用到。

在前面我们讲过，类声明的实现在编译时会调用Zend/zend_complie.c文件中的zend_do_begin_class_declaration函数，
在此函数中对于命名空间的处理代码如下：

    [c]
    if (CG(current_namespace)) {
		/* Prefix class name with name of current namespace */
		znode tmp;

		tmp.u.constant = *CG(current_namespace);
		zval_copy_ctor(&tmp.u.constant);
		zend_do_build_namespace_name(&tmp, &tmp, class_name TSRMLS_CC);
		class_name = &tmp;
		efree(lcname);
		lcname = zend_str_tolower_dup(Z_STRVAL(class_name->u.constant), Z_STRLEN(class_name->u.constant));
	}

这段代码的作用是如果当前存在命名空间，则给类名加上命名空间的前缀，
如前面提到示例中的tipi\Exception类，添加tipi\的操作就是在这里执行的。
在zend_do_build_namespace_name函数中最终会调用zend_do_build_full_name函数实现类名的合并。
在函数和常量的声明中存在同样的名称合并操作。这也是命名空间仅对类、常量和函数有效的原因。

## 使用命名空间
以函数调用为例，当需要调用函数时，会调用zend_do_begin_function_call函数。
在此函数中，当使用到命名空间时会检查函数名，其调用的函数为zend_resolve_non_class_name。
在zend_resolve_non_class_name函数中会根据类型作出判断并返回相关结果：

1. 完全限定名称的函数：
  程序首先会做此判断，其判断的依据是第一个字符是否为"\",这种情况下，在解析时会直接返回。
  如类似于\strlen这样以\开头的全局调用或类似于前面定义的\tipi\Exception调用。
1. 所有的非限定名称和限定名称（非完全限定名称）：根据当前的导入规则
  程序判断是否为别名，并从编译期间存储别名的HashTable中取出对应的命名空间名称，将其与现有的函数名合并。
  关于别名的存储及生成在后面的内容中会说明，
1. 在命名空间内部：
  所有的没有根据导入规则转换的限定名称均会在其前面加上当前的命名空间名称。最后判断是否在当前命名空间，
最终程序都会返回一个合并了命名空间的函数名。

### 别名/导入
允许通过别名引用或导入外部的完全限定名称，是命名空间的一个重要特征。
这有点类似于在类 unix 文件系统中可以创建对其它的文件或目录的符号连接。
PHP 命名空间支持 有两种使用别名或导入方式：为类名称使用别名，或为命名空间名称使用别名。

>**NOTE**
>PHP不支持导入函数或常量

在PHP中，别名是通过操作符 use 来实现的.从而我们可以从源码中找到编译时调用的函数是zend_do_use。
别名在编译为中间代码过程中存放在CG(current_import)中，这是一个HashTable.
zend_do_use整个函数的实现基本上是一个查找，判断是否错误，最后写入到HashTable的过程。
其中针对命名空间和类名都有导入的处理过程，而对于常量和函数来说却没有，
这就是PHP不支持导入函数或常量的根本原因所在。
