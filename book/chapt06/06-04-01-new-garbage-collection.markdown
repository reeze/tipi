# 新的垃圾回收

如前面所说，在PHP中，主要的内存管理手段是引用计数，引入垃圾收集机制的目的是为了打破引用计数中的循环引用，从而防止因为这个而产生的内存泄露。
垃圾收集机制基于PHP的动态内存管理而存在。PHP5.3为引入垃圾收集机制，在变量存储的基本结构上有一些变动，如下所示：

    [c]
    struct _zval_struct {
        /* Variable information */
        zvalue_value value;		/* value */
        zend_uint refcount__gc;
        zend_uchar type;	/* active type */
        zend_uchar is_ref__gc;
    };

与PHP5.3之前的版本相比，引用计数字段refcount和是否引用字段is_ref都在其后面添加了__gc以用于新的的垃圾回收机制。
在PHP的源码风格中，大量的宏是一个非常鲜明的特点。这些宏相当于一个接口层，它屏蔽了接口层以下的一些底层实现，如，
ALLOC_ZVAL宏，这个宏在PHP5.3之前是直接调用PHP的内存管理分配函数emalloc分配内存，所分配的内存大小由变量的类型等大小决定。
在引入垃圾回收机制后，ALLOC_ZVAL宏直接采用新的垃圾回收单元结构，所分配的大小都是一样的，全部是zval_gc_info结构体所占内存大小，
并且在分配内存后，初始化这个结构体的垃圾回收机制。如下代码：

    [c]
    /* The following macroses override macroses from zend_alloc.h */
    #undef  ALLOC_ZVAL
    #define ALLOC_ZVAL(z) 									\
        do {												\
            (z) = (zval*)emalloc(sizeof(zval_gc_info));		\
            GC_ZVAL_INIT(z);								\
        } while (0)

zend_gc.h文件在zend.h的749行被引用：#include “zend_gc.h”
从而替换覆盖了在237行引用的zend_alloc.h文件中的ALLOC_ZVAL等宏
在新的的宏中，关键性的改变是对所分配内存大小和分配内容的改变，在以前纯粹的内存分配中添加了垃圾收集机制的内容，
所有的内容都包括在zval_gc_info结构体中：

    [c]
    typedef struct _zval_gc_info {
        zval z;
        union {
            gc_root_buffer       *buffered;
            struct _zval_gc_info *next;
        } u;
    } zval_gc_info;

对于任何一个ZVAL容器存储的变量，分配了一个zval结构，这个结构确保其和以zval变量分配的内存的开始对齐，
从而在zval_gc_info类型指针的强制转换时，其可以作为zval使用。在zval字段后面有一个联合体:u。
u包括gc_root_buffer结构的buffered字段和zval_gc_info结构的next字段。
这两个字段一个是表示垃圾收集机制缓存的根结点，一个是zval_gc_info列表的下一个结点，
垃圾收集机制缓存的结点无论是作为根结点，还是列表结点，都可以在这里体现。
ALLOC_ZVAL在分配了内存后会调用GC_ZVAL_INIT用来初始化替代了zval的zval_gc_info，
它会把zval_gc_info中的成员u的buffered字段设置成NULL，此字段仅在将其放入垃圾回收缓冲区时才会有值，否则会一直是NULL。
由于PHP中所有的变量都是以zval变量的形式存在，这里以zval_gc_info替换zval，从而成功实现垃圾收集机制在原有系统中的集成。

PHP的垃圾回收机制在PHP5.3中默认为开启，但是我们可以通过配置文件直接设置为禁用，其对应的配置字段为：zend.enable_gc。
在php.ini文件中默认是没有这个字段的，如果我们需要禁用此功能，则在php.ini中添加zend.enable_gc=0或zend.enable_gc=off。
与垃圾回收机制是否开启在PHP源码中一些相关的操作和字段。在zend.c文件中有如下代码：

    [c]

    static ZEND_INI_MH(OnUpdateGCEnabled) /* {{{ */
    {
        OnUpdateBool(entry, new_value, new_value_length, mh_arg1, mh_arg2, mh_arg3, stage TSRMLS_CC);

        if (GC_G(gc_enabled)) {
            gc_init(TSRMLS_C);
        }

        return SUCCESS;
    }
    /* }}} */

    ZEND_INI_BEGIN()
        ZEND_INI_ENTRY("error_reporting",				NULL,		ZEND_INI_ALL,		OnUpdateErrorReporting)
        STD_ZEND_INI_BOOLEAN("zend.enable_gc",				"1",	ZEND_INI_ALL,		OnUpdateGCEnabled,      gc_enabled,     zend_gc_globals,        gc_globals)
    #ifdef ZEND_MULTIBYTE
        STD_ZEND_INI_BOOLEAN("detect_unicode", "1", ZEND_INI_ALL, OnUpdateBool, detect_unicode, zend_compiler_globals, compiler_globals)
    #endif
    ZEND_INI_END()

zend.enable_gc对应的操作函数为ZEND_INI_MH(OnUpdateGCEnabled)，如果开启了垃圾回收机制，
即GC_G(gc_enabled)为真，则会调用gc_init函数执行垃圾回收机制的初始化操作。
gc_init函数在zend/zend_gc.c 121行，此函数会判断是否开启垃圾回收机制，
如果开启，则初始化整个机制，即直接调用malloc给整个缓存列表分配10000个gc_root_buffer内存空间。
这里的10000是硬编码在代码中的，以宏GC_ROOT_BUFFER_MAX_ENTRIES存在，如果需要修改这个值，则需要修改源码，重新编译PHP。
gc_init函数在预分配内存后调用gc_reset函数重置整个机制用到的一些全局变量，如设置gc运行的次数统计(gc_runs)和gc中垃圾的个数(collected)为0，
设置双向链表头结点的上一个结点和下一个结点指向自己等。除了这种提的一些用于垃圾回收机制的全局变量，还有其它一些使用较多的变量，部分说明如下：

    [c]
    typedef struct _zend_gc_globals {
	zend_bool         gc_enabled;	/* 是否开启垃圾收集机制 */
	zend_bool         gc_active;	/* 是否正在进行 */
 
	gc_root_buffer   *buf;				/* 预分配的缓冲区数组，默认为10000（preallocated arrays of buffers）   */
	gc_root_buffer    roots;			/* 列表的根结点（list of possible roots of cycles） */
	gc_root_buffer   *unused;			/* 没有使用过的缓冲区列表(list of unused buffers)           */
	gc_root_buffer   *first_unused;		/* 指向第一个没有使用过的缓冲区结点（pointer to first unused buffer）   */
	gc_root_buffer   *last_unused;		/* 指向最后一个没有使用过的缓冲区结点，此处为标记结束用(pointer to last unused buffer)    */
 
	zval_gc_info     *zval_to_free;		/* 将要释放的zval变量的临时列表（temporaryt list of zvals to free） */
	zval_gc_info     *free_list;		/* 临时变量，需要释放的列表开头 */
	zval_gc_info     *next_to_free;		/* 临时变量，下一个将要释放的变量位置*/
 
	zend_uint gc_runs;	/* gc运行的次数统计 */
	zend_uint collected;    /* gc中垃圾的个数 */
 
	// 省略...



