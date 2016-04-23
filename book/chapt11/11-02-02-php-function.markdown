# 在扩展中使用函数

我们在 hello-world 扩展的例子中，通过原型框架生成了一个简单的函数，修改之后如下

    [c]
    /* {{{ proto string tipi_hello_world(string name)
        */
    PHP_FUNCTION(tipi_hello_world)
    {
        char *name = NULL;
        int argc = ZEND_NUM_ARGS();
        size_t name_len;
    
        char *result = NULL;
        char *prefix = "hello world, ";
    
    #ifndef FAST_ZPP
        if (zend_parse_parameters(argc TSRMLS_CC, "s", &name, &name_len) == FAILURE) 
            return;
    #else
        ZEND_PARSE_PARAMETERS_START(1, 1)
             Z_PARAM_STRING(name, name_len)
        ZEND_PARSE_PARAMETERS_END();
    #endif
    
        result = (char *) ecalloc(strlen(prefix) + name_len + 1, sizeof(char));
        strncat(result, prefix, strlen(prefix));
        strncat(result, name, name_len);
    
        RETURN_STRING(result);
    }
    /* }}} */
    
## PHP_FUNCTION 宏

在 PHP 扩展中，所有的函数均以 `PHP_FUNCTION(extension_name){...}`的结构来表示，

    [c]
    /* main/php.h */
    #define PHP_FUNCTION			ZEND_FUNCTION
    /* Zend/zend_API.h */
    #define ZEND_FUNCTION(name)				ZEND_NAMED_FUNCTION(ZEND_FN(name))
    
    #define ZEND_FN(name) zif_##name
    #define ZEND_NAMED_FUNCTION(name)		void name(INTERNAL_FUNCTION_PARAMETERS)
    
    #define INTERNAL_FUNCTION_PARAMETERS zend_execute_data *execute_data, zval *return_value

相对 PHP5 中 `INTERNAL_FUNCTION_PARAMETERS` 宏表示的参数简单了很多。
在上例中 `return_value` 虽然没有显性地使用，实际通过 `RETURN_STRING` 隐形地使用了。

## 从函数中返回

扩展API包含丰富的用于从函数中返回值的宏。这些宏有两种主要风格：第一种是 `RETVAL_type()` 形式，它设置了返回值但C代码继续执行。
这通常使用在把控制交给脚本引擎前还希望做的一些清理工作的时候使用，然后再使用C的返回声明 `return` 返回到 PHP；
后一个宏更加普遍，其形式是 `RETURN_type()`，他设置了返回类型，同时返回控制到 PHP。

    [c]
    /* Zend/zend_API.h */
    #define RETURN_BOOL(b) 					{ RETVAL_BOOL(b); return; }
    #define RETURN_NULL() 					{ RETVAL_NULL(); return;}
    #define RETURN_LONG(l) 					{ RETVAL_LONG(l); return; }
    #define RETURN_DOUBLE(d) 				{ RETVAL_DOUBLE(d); return; }
    #define RETURN_STR(s) 					{ RETVAL_STR(s); return; }
    #define RETURN_INTERNED_STR(s)			{ RETVAL_INTERNED_STR(s); return; }
    #define RETURN_NEW_STR(s)				{ RETVAL_NEW_STR(s); return; }
    #define RETURN_STR_COPY(s)				{ RETVAL_STR_COPY(s); return; }
    #define RETURN_STRING(s) 				{ RETVAL_STRING(s); return; }
    #define RETURN_STRINGL(s, l) 			{ RETVAL_STRINGL(s, l); return; }
    #define RETURN_EMPTY_STRING() 			{ RETVAL_EMPTY_STRING(); return; }
    #define RETURN_RES(r) 					{ RETVAL_RES(r); return; }
    #define RETURN_ARR(r) 					{ RETVAL_ARR(r); return; }
    #define RETURN_OBJ(r) 					{ RETVAL_OBJ(r); return; }
    #define RETURN_ZVAL(zv, copy, dtor)		{ RETVAL_ZVAL(zv, copy, dtor); return; }
    #define RETURN_FALSE  					{ RETVAL_FALSE; return; }
    #define RETURN_TRUE   					{ RETVAL_TRUE; return; }

以 `RETURN_STRING` 为例

    [c]
    #define RETURN_STR(s) 					{ RETVAL_STR(s); return; }
    
    #define RETVAL_STR(s)			 		ZVAL_STR(return_value, s)

实际是将 `s` 赋值给 `return_value`，详细代码读者可以自己在源码中跟下。

与 `PHP_FUNCTION` 宏类似， `PHP_METHOD` 用来封装类中的方法。 