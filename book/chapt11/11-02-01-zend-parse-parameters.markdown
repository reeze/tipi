# 扩展中参数的解析

在前面 `tipi_hello_world` 的扩展例子中，通过 `zend_parse_parameters` 获取到了
`tipi_hello_world()` 函数传入的字符串参数。下面我们首先对 API 进行讲解，然后说明其原理。
最后说明 Fast Parameter Parsing API。

## zend_parse_parameters 介绍

    [c]
    END_API int zend_parse_parameters(int num_args, const char *type_spec, ...)

`zend_parse_parameters` 解析参数，第一个参数是传递的参数个数。通常使用 `ZEND_NUM_ARGS()` 来获取。
第二个参数是一个字符串，指定了函数期望的各个参数的类型，后面紧跟着需要随参数值更新的变量列表。
因为PHP采用松散的变量定义和动态的类型判断，这样做就使得把不同类型的参数转化为期望的类型成为可能。

下表列出了可能指定的类型。我们从完整性考虑也列出了一些没有讨论到的类型。

类型指定符	|对应的C类型	        |描述
------------|-------------------|-------------------------
l	        |long	            |符号整数
d	        |double	            |浮点数
s	        |char *, int	    |二进制字符串，长度
b	        |zend_bool	        |逻辑型（1或0）
r	        |zval *	            |资源（文件指针，数据库连接等）
a	        |zval *	            |联合数组
o	        |zval *	            |任何类型的对象
O	        |zval *	            |指定类型的对象。需要提供目标对象的类类型
z	        |zval *	            |无任何操作的zval

例如下面的例子

    [c]
    zend_parse_parameters(ZEND_NUM_ARGS(), "sl", &str, &str_len, &n)

该表达式则是获取两个参数 `str` 和 `n`，字符串的类型是`s`，需要两个参数 `char *` 字符串和 `int` 长度；数字的类型 `l` ，只需要一个参数。

## zend_parse_parameters 动态获取参数的实现原理

由其源码可知

    [c]
    ZEND_API int zend_parse_parameters(int num_args, const char *type_spec, ...) /* {{{ */
    {
       va_list va;
       int retval;
       int flags = 0;
     
       va_start(va, type_spec);
       retval = zend_parse_va_args(num_args, type_spec, &va, flags);
       va_end(va);
     
       return retval;
    }

主要使用到了 `va_list`, `va_start`, `va_arg` 和 `va_end` 来获取可变个数的参数。
通过如下代码的练习即可明白其原理了

    [c]
    #include <stdarg.h>
    #include <stdio.h>
     
    int zend_parse_parameters(int num_args, const char *type_spec, ...);
     
    int main() {
     
        zend_parse_parameters(4, "abcd", 1, 2, 3, 4);
        return 0;
    }
     
    int zend_parse_parameters(int num_args, const char *type_spec, ...) {
        va_list va;
        const char *spec_walk;
        char c;
     
        va_start(va, type_spec);
        for (spec_walk = type_spec; *spec_walk; spec_walk++) {
            c = *spec_walk;
            printf("参数类型为 %c 值为: %d\n", c, va_arg(va, int));
        }
        va_end(va);
     
        return 0;
    }

输出结果为

    [shell]
    参数类型为 a 值为: 1
    参数类型为 b 值为: 2
    参数类型为 c 值为: 3
    参数类型为 d 值为: 4
    
## 使用 Fast Parameter Parsing API 取代 zend_parse_parameters
 
官方推荐在 PHP7 中使用 Fast Parameter Parsing API，不仅效率上又提升，并且代码表意更加明确，易读性强。
下面引用官方的说明，简短翻译说明（完整地内容请参考本节末尾的参考链接）

以 `PHP_FUNCTION(array_slice)` 为例

    [c]
    PHP_FUNCTION(array_slice)
    {

    /* 省略一系列的参数声明 */
    
    #ifndef FAST_ZPP
        if (zend_parse_parameters(ZEND_NUM_ARGS(), "al|zb", &input, &offset, &z_length, &preserve_keys) == FAILURE) {
            return;
        }
    #else
        ZEND_PARSE_PARAMETERS_START(2, 4)
            Z_PARAM_ARRAY(input)
            Z_PARAM_LONG(offset)
            Z_PARAM_OPTIONAL
            Z_PARAM_ZVAL(z_length)
            Z_PARAM_BOOL(preserve_keys)
        ZEND_PARSE_PARAMETERS_END();
    #endif
    
    /* 省略后面的逻辑代码 */
    
    }
    
在没有定义 `FAST_ZPP` 的情况下，使用 `zend_parse_parameters` 来解析，
`al|zb` 表示第一个参数为 `array`，第二个参数为 `long`，第三个参数为 `zval`，第四个参数为 `bool`。
并且后面两个参数为可选。

如果定了 `FAST_ZPP` （该定义在 PHP7 `Zend/zend_API.h` 中），则使用 Fast Parameter Parsing API 方式来解析参数。代码本身已经足以解释其作用了。
`ZEND_PARSE_PARAMETERS_START()` 的两个参数分别为最少参数数和最多参数数。
`Z_PARAM_ARRAY()` 则将参数视为数组，`Z_PARAM_LONG()` 将参数视为长整型。
而 `Z_PARAM_OPTIONAL` 则表示后面的参数为可选参数。完整地映射表关系，可参考本节最后的参考链接。

## 参考资料
* [用C/C++扩展你的PHP](http://www.laruence.com/2009/04/28/719.html)
* [PHP RFC: Fast Parameter Parsing API](https://wiki.php.net/rfc/fast_zpp)