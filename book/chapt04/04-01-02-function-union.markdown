# 函数间的转换

在函数调用的执行代码中我们会看到这样一些强制转换：

    [c]
    EX(function_state).function = (zend_function *) op_array;

    或者：

    EG(active_op_array) = (zend_op_array *) EX(function_state).function;

这些不同结构间的强制转换是如何进行的呢？

首先我们来看zend_function的结构，在Zend/zend_compile.h文件中，其定义如下：

    [c]
    typedef union _zend_function {
        zend_uchar type;	/* MUST be the first element of this struct! */

        struct {
            zend_uchar type;  /* never used */
            char *function_name;
            zend_class_entry *scope;
            zend_uint fn_flags;
            union _zend_function *prototype;
            zend_uint num_args;
            zend_uint required_num_args;
            zend_arg_info *arg_info;
            zend_bool pass_rest_by_reference;
            unsigned char return_reference;
        } common;

        zend_op_array op_array;
        zend_internal_function internal_function;
    } zend_function;

这是一个联合体，我们来温习一下联合体的一些特性。
联合体的所有成员变量共享内存中的一块内存，在某个时刻只能有一个成员使用这块内存，
并且当使用某一个成员时，其仅能按照它的类型和内存大小修改对应的内存空间。
我们来看看一个例子：

    [c]
    #include <stdio.h>
    #include <stdlib.h>

    int main() {
        typedef  union _utype
        {
            int i;
            char ch[2];
        } utype;

        utype a;

        a.i = 10;
        a.ch[0] = '1';
        a.ch[1] = '1';

        printf("a.i= %d a.ch=%s",a.i, a.ch);
        getchar();

        return (EXIT_SUCCESS);
    }

程序输出：a.i= 12593 a.ch=11
当修改ch的值时，它会依据自己的规则覆盖i字段对应的内存空间。
'1'对应的ASCII码值是49，二进制为00110001，当ch字段的两个元素都为'1'时，此时内存中存储的二进制为 00110001 00110001
转成十进制，其值为12593。

回过头来看zend_function的结构，它也是一个联合体，第一个字段为type，
在common中第一个字段也为type，并且其后面注释为/* Never used*/，此处的type字段的作用就是为第一个字段的type留下内存空间。并且不让其它字段干扰了第一个字段。
我们再看zend_op_array的结构：

    [c]
    struct _zend_op_array {
        /* Common elements */
        zend_uchar type;
        char *function_name;
        zend_class_entry *scope;
        zend_uint fn_flags;
        union _zend_function *prototype;
        zend_uint num_args;
        zend_uint required_num_args;
        zend_arg_info *arg_info;
        zend_bool pass_rest_by_reference;
        unsigned char return_reference;
        /* END of common elements */

        zend_bool done_pass_two;
        ....//  其它字段
    }

这里的字段集和common的一样，于是在将zend_function转化成zend_op_array时并不会产生影响，这种转变是双向的。

再看zend_internal_function的结构：

    [c]
    typedef struct _zend_internal_function {
        /* Common elements */
        zend_uchar type;
        char * function_name;
        zend_class_entry *scope;
        zend_uint fn_flags;
        union _zend_function *prototype;
        zend_uint num_args;
        zend_uint required_num_args;
        zend_arg_info *arg_info;
        zend_bool pass_rest_by_reference;
        unsigned char return_reference;
        /* END of common elements */

        void (*handler)(INTERNAL_FUNCTION_PARAMETERS);
        struct _zend_module_entry *module;
    } zend_internal_function;

同样存在公共元素，和common结构体一样，我们可以将zend_function结构强制转化成zend_internal_function结构，并且这种转变是双向的。

总的来说zend_internal_function，zend_function，zend_op_array这三种结构在一定程序上存在公共的元素，
于是这些元素以联合体的形式共享内存，并且在执行过程中对于一个函数，这三种结构对应的字段在值上都是一样的，
于是可以在一些结构间发生完美的强制类型转换。
可以转换的列表如下：

* zend_function可以与zend_op_array互换
* zend_function可以与zend_internal_function互换

但是一个zend_op_array结构转换成zend_function是不能再次转变成zend_internal_function结构的，反之亦然。

其实zend_function就是一个混合的数据结构，这种结构在一定程序上节省了内存空间。

