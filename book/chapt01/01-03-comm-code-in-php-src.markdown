# 第三节 PHP实现中的常用代码
在PHP的源码中经常会看到一些宏或一些对于刚开始看源码的童鞋比较纠结的代码。这里提取中间的一些进行说明。
## 1. 关于"##"和"#"
***
在PHP的宏定义中，最常见的要数双井号。
### **双井号**
在C语言的宏中，"##"被称为 **连接符**（concatenator），用来把两个语言符号(Token)组合成单个语言符号。这里的语言符号不一定是宏的变量。并且双井号不能作为第一个或最后一个元素存在。如下所示源码：


    [c]
    #define PHP_FUNCTION			ZEND_FUNCTION
    #define ZEND_FUNCTION(name)				ZEND_NAMED_FUNCTION(ZEND_FN(name))
    #define ZEND_FN(name) zif_##name
    #define ZEND_NAMED_FUNCTION(name)		void name(INTERNAL_FUNCTION_PARAMETERS)
    #define INTERNAL_FUNCTION_PARAMETERS int ht, zval *return_value, zval **return_value_ptr, \
    zval *this_ptr, int return_value_used TSRMLS_DC

    PHP_FUNCTION(count);

    //  预处理器处理以后, PHP_FUCNTION(count);就展开为如下代码
    void zif_count(int ht, zval *return_value, zval **return_value_ptr, zval *this_ptr, int return_value_used TSRMLS_DC)

宏ZEND_FN(name)中有一个"##"，它的作用一如之前所说，是一个连接符，将zif和宏的变量name得值连接起来。

### **单井号**
"#"的功能是将其后面的宏参数进行 **字符串化操作** ，简单说就是在对它所引用的宏变量通过替换后在其左右各加上一个双引号，用比较官方的话说就是将语言符号(Token)转化为字符串。

  
## 2. 关于宏定义中的do-while
***
如下所示为PHP5.3新增加的垃圾收集机制中的一段代码：

    [c]
    #define ALLOC_ZVAL(z) 									\
	do {												\
		(z) = (zval*)emalloc(sizeof(zval_gc_info));		\
		GC_ZVAL_INIT(z);								\
	} while (0)

如上所示的代码，在宏定义中使用了 **do{ }while(0)** 语句格式。如果我们搜索整个PHP的源码目录，会发现这样的语句还有很多。那为什么在宏定义时需要使用do-while语句呢?
我们知道do-while循环语句是先执行再判断条件是否成立, 所以说至少会执行一次。当使用do{ }while(0)时代码肯定只执行一次, 肯定只执行一次的代码为什么要放在do-while语句里呢?
这种方式适用于宏定义中存在多语句的情况。如下所示代码：  

    [c]
    #define TEST(a, b)  a++;b++;

    if (expr)
        TEST(a, b);
    else
        do_else();

代码进行预处理后，会变成：

    [c]
    if (expr)
        a++;b++;
    else
        do_else();

这样if-else的结构就被破坏了: if后面有两个语句, 这样是无法编译通过的, 那为什么非要do-while而不是简单的用{}括起来呢.这样也能保证if后面只有一个语句。
例如上面的例子,在调用宏TEST的时候后面加了一个分号, 虽然这个分号可有可无, 但是出于习惯我们一般都会写上. 那如果是把宏里的代码用{}括起来,加上最后的那个分号.
还是不能通过编译. 所以一般的多表达式宏定义中都采用do-while(0)的方式.


## 3. #line 预处理
***

    [c]
    #line 838 "Zend/zend_language_scanner.c"

\#line预处理用于改变当前的行号和文件名。  
如上所示代码，将当前的行号改变为838,文件名Zend/zend_language_scanner.c  
它的作用体现在编译器的编写中，我们知道编译器对C 源码编译过程中会产生一些中间文件，通过这条指令，可以保证文件名是固定的，不会被这些中间文件代替，有利于进行调试分析。

