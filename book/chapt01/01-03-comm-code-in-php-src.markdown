# 第四节 PHP实现中的常见代码
在PHP的源码中经常会看到一些宏或一些对于刚开始看源码的童鞋比较纠结的代码。这里提取中间的一些进行说明。
## 1. 关于"##"和"#"
***
在PHP的宏定义中，最常见的要数双井号。
### **双井号**
在C语言的宏中，"##"被称为 **连接符**（concatenator），用来把两个语言符号(Token)组合成单个语言符号。这里的语言符号不一定是宏的变量。并且双井号不能作为第一个或最后一个元素存在。如下所示源码：


    [c]
    PHP_FUNCTION(count);
    #define PHP_FUNCTION			ZEND_FUNCTION
    #define ZEND_FUNCTION(name)				ZEND_NAMED_FUNCTION(ZEND_FN(name))
    #define ZEND_FN(name) zif_##name
    #define ZEND_NAMED_FUNCTION(name)		void name(INTERNAL_FUNCTION_PARAMETERS)
    #define INTERNAL_FUNCTION_PARAMETERS int ht, zval *return_value, zval **return_value_ptr, \
    zval *this_ptr, int return_value_used TSRMLS_DC

    //  预处理器处理完以后PHP_FUCNTION(count);就展开为如下代码
    void zif_count(int ht, zval *return_value, zval **return_value_ptr, zval *this_ptr, int return_value_used TSRMLS_DC)

以上面的代码中只有一个"##"，它的作用一如之前所说，只是一个连接符，将zif和宏的变量name连接起来。

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

如上所示的代码，在宏定义中使用了 **do{ }while(0)** 的语句格式。如果我们搜索整个PHP的源码目录，会发现这样的语句还有很多。那为什么在宏定义时需要使用while语句呢?  
我们知道do-while循环语句是先执行再判断条件是否成立。当使用do{ }while(0)时，整个代码段在执行到while(0)时就结束了，这表示其执行了一次，而且仅执行了一次。
这种方式适用于宏定义中存在多语句的情况。如下所示代码：  

    [c]
    #define TEST(a, b)  a++;b++;

    if (expr)
        TEST(a, b);
    else
        do_else();

将代码进行预处理后，会变成：

    [c]
    if (expr)
        a++;b++;
    else
        do_else();

这样就会出现问题。如果使用do-while就不会出现上面所遇到的这种情况。


## 3. #line 预处理
***

    [c]
    #line 838 "Zend/zend_language_scanner.c"

\#line预处理用于改变当前的行号和文件名，如上所示代码，将当前的行号改变为838,文件名Zend/zend_language_scanner.c  
它的作用体现在编译器的编写中，我们知道编译器对C 源码编译过程中会产生一些中间文件，通过这条指令，可以保证文件名是固定的，不会被这些中间文件代替，有利于进行调试分析。
